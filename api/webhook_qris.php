<?php
/**
 * QRIS Payment Gateway Webhook Handler
 * Processes POST notifications from the payment gateway
 */

// Include required files
require_once '../config.php';

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Webhook-Signature');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
        'message' => 'Only POST requests are allowed'
    ]);
    exit;
}

try {
    // Get raw POST data
    $rawInput = file_get_contents('php://input');

    if (empty($rawInput)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Empty request body',
            'message' => 'No data received'
        ]);
        exit;
    }

    // Decode JSON payload
    $payload = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON',
            'message' => 'Request body contains invalid JSON'
        ]);
        exit;
    }

    // Extract webhook signature from headers
    $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

    if (empty($signature)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Missing signature',
            'message' => 'Webhook signature is required'
        ]);
        exit;
    }

    // Verify webhook signature
    $paymentGateway = new PaymentGateway();

    if (!$paymentGateway->verifyWebhookSignature($payload, $signature)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid signature',
            'message' => 'Webhook signature verification failed'
        ]);
        exit;
    }

    // Log webhook request
    $webhookLogData = [
        'transaction_id' => $payload['transaction_id'] ?? null,
        'gateway_type' => 'qris',
        'payload' => json_encode($payload),
        'processed' => false,
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Validate required webhook fields
    $requiredFields = ['transaction_id', 'status', 'amount'];
    foreach ($requiredFields as $field) {
        if (!isset($payload[$field]) || empty($payload[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Missing required field',
                'message' => "Field '{$field}' is required"
            ]);
            exit;
        }
    }

    $transactionId = $payload['transaction_id'];
    $paymentStatus = $payload['status'];
    $paidAmount = (float) $payload['amount'];

    // Validate transaction ID format
    if (!Validator::transactionId($transactionId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid transaction ID'
        ]);
        exit;
    }

    $db = Database::getInstance();

    // Log webhook before processing
    $webhookId = $db->insert('webhook_logs', $webhookLogData);

    // Get transaction from database
    $transaction = $db->fetchOne(
        "SELECT t.*, p.name as package_name, p.duration_hours, p.profile_name
         FROM transactions t
         JOIN packages p ON t.package_id = p.id
         WHERE t.transaction_id = ?",
        [$transactionId]
    );

    if (empty($transaction)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Transaction not found',
            'message' => 'The specified transaction does not exist'
        ]);
        exit;
    }

    // Check if transaction is already processed
    if ($transaction['status'] !== 'pending') {
        logger()->info('Webhook received for already processed transaction', [
            'transaction_id' => $transactionId,
            'current_status' => $transaction['status'],
            'webhook_status' => $paymentStatus
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Transaction already processed',
            'status' => $transaction['status']
        ]);
        exit;
    }

    // Validate payment amount matches transaction amount
    if (abs($transaction['amount'] - $paidAmount) > 0.01) {
        logger()->warning('Payment amount mismatch', [
            'transaction_id' => $transactionId,
            'expected_amount' => $transaction['amount'],
            'received_amount' => $paidAmount
        ]);

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Amount mismatch',
            'message' => 'Payment amount does not match transaction amount'
        ]);
        exit;
    }

    // Start database transaction
    $db->beginTransaction();

    try {
        // Process based on payment status
        if ($paymentStatus === 'success' || $paymentStatus === 'paid') {
            // Payment successful - generate voucher
            $username = Validator::generateUsername();
            $password = Validator::generatePassword();

            // Update transaction status
            $db->update(
                'transactions',
                [
                    'status' => 'success',
                    'payment_gateway_ref' => $payload['charge_id'] ?? null
                ],
                'transaction_id = ?',
                [$transactionId]
            );

            // Create voucher record
            $voucherData = [
                'transaction_id' => $transactionId,
                'username' => $username,
                'password' => $password,
                'expires_at' => date('Y-m-d H:i:s', strtotime("{$transaction['duration_hours']} hours"))
            ];

            $voucherId = $db->insert('vouchers', $voucherData);

            if (!$voucherId) {
                throw new Exception('Failed to create voucher record');
            }

            // Create user in MikroTik
            $mikrotik = new MikroTik();
            $mikrotikResult = $mikrotik->createHotspotUser(
                $username,
                $password,
                $transaction['profile_name'],
                "Auto-created via Webhook - Transaction: {$transactionId}"
            );

            if ($mikrotikResult) {
                logger()->info('Voucher created via webhook', [
                    'transaction_id' => $transactionId,
                    'username' => $username,
                    'profile' => $transaction['profile_name'],
                    'webhook_id' => $webhookId
                ]);

                // Update webhook log as processed
                $db->update(
                    'webhook_logs',
                    ['processed' => true],
                    'id = ?',
                    [$webhookId]
                );

                // Commit transaction
                $db->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Payment processed and voucher created successfully',
                    'data' => [
                        'transaction_id' => $transactionId,
                        'status' => 'success',
                        'voucher_created' => true,
                        'username' => $username
                    ]
                ]);

            } else {
                throw new Exception('Failed to create MikroTik user: ' . $mikrotik->getLastError());
            }

        } elseif ($paymentStatus === 'failed' || $paymentStatus === 'cancelled' || $paymentStatus === 'expired') {
            // Payment failed - update transaction status
            $db->update(
                'transactions',
                ['status' => 'failed'],
                'transaction_id = ?',
                [$transactionId]
            );

            logger()->info('Payment failed via webhook', [
                'transaction_id' => $transactionId,
                'status' => $paymentStatus,
                'webhook_id' => $webhookId
            ]);

            // Update webhook log as processed
            $db->update(
                'webhook_logs',
                ['processed' => true],
                'id = ?',
                [$webhookId]
            );

            // Commit transaction
            $db->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Payment failure processed',
                'data' => [
                    'transaction_id' => $transactionId,
                    'status' => 'failed'
                ]
            ]);

        } else {
            // Unknown status
            logger()->warning('Unknown payment status received', [
                'transaction_id' => $transactionId,
                'status' => $paymentStatus,
                'webhook_id' => $webhookId
            ]);

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Unknown payment status',
                'message' => "Payment status '{$paymentStatus}' is not recognized"
            ]);
            exit;
        }

    } catch (Exception $e) {
        // Rollback database transaction on error
        $db->rollback();

        logger()->error('Webhook processing failed', [
            'transaction_id' => $transactionId,
            'webhook_id' => $webhookId,
            'error' => $e->getMessage()
        ]);

        throw $e;
    }

} catch (Exception $e) {
    // Log error
    logger()->error('Webhook Error in webhook_qris.php', [
        'error' => $e->getMessage(),
        'payload' => $payload ?? null,
        'signature' => $signature ?? null,
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => 'Failed to process webhook',
        'debug_info' => is_production() ? null : $e->getMessage()
    ]);
}

// Close database connection
if (isset($db)) {
    $db->close();
}

?>