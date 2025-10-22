<?php
/**
 * Check Status API Endpoint
 * Handles GET requests to check payment status and retrieve voucher information
 */

// Include required files
require_once '../config.php';

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
        'message' => 'Only GET requests are allowed'
    ]);
    exit;
}

try {
    // Get and validate transaction ID from query parameters
    $transactionId = $_GET['trx'] ?? '';

    if (empty($transactionId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Transaction ID required',
            'message' => 'Please provide a transaction ID (trx parameter)'
        ]);
        exit;
    }

    // Validate transaction ID format
    if (!Validator::transactionId($transactionId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid transaction ID format'
        ]);
        exit;
    }

    // Rate limiting check (by IP address)
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!Validator::checkRateLimit($clientIp, RATE_LIMIT_REQUESTS, RATE_LIMIT_WINDOW)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests. Please try again later.'
        ]);
        exit;
    }

    // Get transaction from database
    $db = Database::getInstance();
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

    // Log API request
    $apiLogData = [
        'endpoint' => 'cek_status.php',
        'method' => 'GET',
        'request_data' => json_encode(['transaction_id' => $transactionId]),
        'created_at' => date('Y-m-d H:i:s')
    ];

    $db->insert('api_logs', $apiLogData);

    // Prepare base response
    $response = [
        'success' => true,
        'data' => [
            'transaction_id' => $transactionId,
            'status' => $transaction['status'],
            'package' => [
                'id' => $transaction['package_id'],
                'name' => $transaction['package_name'],
                'duration_hours' => $transaction['duration_hours'],
                'price' => (float) $transaction['amount']
            ],
            'created_at' => $transaction['created_at'],
            'updated_at' => $transaction['updated_at']
        ]
    ];

    // If transaction is pending, check payment gateway status
    if ($transaction['status'] === 'pending') {
        // Check if transaction is expired
        if (Validator::isTransactionExpired($transactionId)) {
            // Update status to expired in database
            $db->update(
                'transactions',
                ['status' => 'expired'],
                'transaction_id = ?',
                [$transactionId]
            );

            $response['data']['status'] = 'expired';
            $response['message'] = 'Transaction has expired. Please create a new transaction.';

            logger()->info('Transaction expired', ['transaction_id' => $transactionId]);

        } else {
            // Check payment gateway status
            $paymentGateway = new PaymentGateway();
            $paymentStatus = $paymentGateway->checkStatus($transactionId);

            if ($paymentStatus['success']) {
                $gatewayStatus = $paymentStatus['status'];

                // Update database based on payment status
                if ($gatewayStatus === 'success' || $gatewayStatus === 'paid') {
                    // Payment successful - update transaction status
                    $db->update(
                        'transactions',
                        [
                            'status' => 'success',
                            'payment_gateway_ref' => $paymentStatus['charge_id']
                        ],
                        'transaction_id = ?',
                        [$transactionId]
                    );

                    // Generate voucher credentials
                    $username = Validator::generateUsername();
                    $password = Validator::generatePassword();

                    // Create voucher in database
                    $voucherData = [
                        'transaction_id' => $transactionId,
                        'username' => $username,
                        'password' => $password,
                        'expires_at' => date('Y-m-d H:i:s', strtotime("{$transaction['duration_hours']} hours"))
                    ];

                    $db->insert('vouchers', $voucherData);

                    // Create user in MikroTik
                    $mikrotik = new MikroTik();
                    $mikrotikResult = $mikrotik->createHotspotUser(
                        $username,
                        $password,
                        $transaction['profile_name'],
                        "Transaction: {$transactionId}"
                    );

                    if ($mikrotikResult) {
                        logger()->info('Voucher created successfully', [
                            'transaction_id' => $transactionId,
                            'username' => $username,
                            'profile' => $transaction['profile_name']
                        ]);
                    } else {
                        logger()->error('Failed to create MikroTik user', [
                            'transaction_id' => $transactionId,
                            'username' => $username,
                            'error' => $mikrotik->getLastError()
                        ]);
                    }

                    // Update response with voucher information
                    $response['data']['status'] = 'success';
                    $response['data']['voucher'] = [
                        'username' => $username,
                        'password' => $password,
                        'expires_at' => $voucherData['expires_at'],
                        'duration_hours' => $transaction['duration_hours']
                    ];
                    $response['message'] = 'Payment successful! Your voucher is ready.';

                } elseif ($gatewayStatus === 'failed' || $gatewayStatus === 'cancelled') {
                    // Payment failed - update transaction status
                    $db->update(
                        'transactions',
                        ['status' => 'failed'],
                        'transaction_id = ?',
                        [$transactionId]
                    );

                    $response['data']['status'] = 'failed';
                    $response['message'] = 'Payment failed or was cancelled.';

                    logger()->info('Payment failed', [
                        'transaction_id' => $transactionId,
                        'gateway_status' => $gatewayStatus
                    ]);

                } else {
                    // Still pending
                    $response['message'] = 'Payment is still being processed. Please wait...';
                }

                $response['data']['payment_status'] = $gatewayStatus;

            } else {
                // Failed to check payment gateway status
                logger()->warning('Failed to check payment status', [
                    'transaction_id' => $transactionId,
                    'error' => $paymentStatus['error'] ?? 'Unknown error'
                ]);

                $response['message'] = 'Unable to check payment status. Please try again.';
            }
        }
    }

    // If transaction is successful, get voucher information
    elseif ($transaction['status'] === 'success') {
        $voucher = $db->fetchOne(
            "SELECT username, password, expires_at, is_used FROM vouchers WHERE transaction_id = ?",
            [$transactionId]
        );

        if ($voucher) {
            $response['data']['voucher'] = [
                'username' => $voucher['username'],
                'password' => $voucher['password'],
                'expires_at' => $voucher['expires_at'],
                'is_used' => (bool) $voucher['is_used']
            ];
            $response['message'] = 'Your voucher is ready to use!';
        } else {
            logger()->error('Voucher not found for successful transaction', [
                'transaction_id' => $transactionId
            ]);
            $response['message'] = 'Voucher information not found. Please contact support.';
        }
    }

    // Handle other statuses
    else {
        switch ($transaction['status']) {
            case 'failed':
                $response['message'] = 'Payment failed. Please create a new transaction.';
                break;
            case 'expired':
                $response['message'] = 'Transaction has expired. Please create a new transaction.';
                break;
            default:
                $response['message'] = 'Unknown transaction status.';
        }
    }

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    // Log error
    logger()->error('API Error in cek_status.php', [
        'error' => $e->getMessage(),
        'transaction_id' => $transactionId ?? null,
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => 'Failed to check transaction status. Please try again.',
        'debug_info' => is_production() ? null : $e->getMessage()
    ]);
}

// Close database connection
if (isset($db)) {
    $db->close();
}

?>