<?php
/**
 * Create Transaction API Endpoint
 * Handles POST requests to create new transactions and generate QRIS codes
 */

// Include required files
require_once '../config.php';

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
    // Get and decode JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    $requiredFields = ['package_id'];
    $validationErrors = Validator::required($input, $requiredFields);

    if (!empty($validationErrors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Validation failed',
            'errors' => $validationErrors
        ]);
        exit;
    }

    $packageId = (int) $input['package_id'];

    // Validate package ID format
    if (!Validator::integer($packageId) || $packageId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid package ID'
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

    // Get package information from database
    $db = Database::getInstance();
    $package = $db->fetchOne(
        "SELECT id, name, price, duration_hours, profile_name FROM packages WHERE id = ? AND is_active = 1",
        [$packageId],
        'i'
    );

    if (empty($package)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Package not found',
            'message' => 'The selected package is not available'
        ]);
        exit;
    }

    // Generate unique transaction ID
    $transactionId = Validator::generateTransactionId();

    // Check if transaction ID already exists (very rare, but just in case)
    $existingTransaction = $db->fetchOne(
        "SELECT id FROM transactions WHERE transaction_id = ?",
        [$transactionId]
    );

    if ($existingTransaction) {
        // Regenerate if collision occurs
        $transactionId = Validator::generateTransactionId();
    }

    // Start database transaction
    $db->beginTransaction();

    try {
        // Create transaction record in database
        $transactionData = [
            'transaction_id' => $transactionId,
            'package_id' => $packageId,
            'amount' => $package['price'],
            'status' => 'pending'
        ];

        $transactionDbId = $db->insert('transactions', $transactionData);

        if (!$transactionDbId) {
            throw new Exception('Failed to create transaction record');
        }

        // Log API request
        $apiLogData = [
            'endpoint' => 'buat_transaksi.php',
            'method' => 'POST',
            'request_data' => json_encode([
                'package_id' => $packageId,
                'transaction_id' => $transactionId,
                'amount' => $package['price']
            ]),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $db->insert('api_logs', $apiLogData);

        // Create payment charge with QRIS gateway
        $paymentGateway = new PaymentGateway();

        $chargeResult = $paymentGateway->createCharge(
            $transactionId,
            (float) $package['price'],
            "WiFi Voucher - {$package['name']}",
            [
                'package_name' => $package['name'],
                'duration_hours' => $package['duration_hours']
            ]
        );

        if (!$chargeResult['success']) {
            throw new Exception('Payment gateway error: ' . $chargeResult['error']);
        }

        // Commit database transaction
        $db->commit();

        // Log successful transaction creation
        logger()->info('Transaction created successfully', [
            'transaction_id' => $transactionId,
            'package_id' => $packageId,
            'amount' => $package['price'],
            'charge_id' => $chargeResult['charge_id']
        ]);

        // Prepare response data
        $response = [
            'success' => true,
            'data' => [
                'transaction_id' => $transactionId,
                'package' => [
                    'id' => $package['id'],
                    'name' => $package['name'],
                    'price' => (float) $package['price'],
                    'duration_hours' => $package['duration_hours']
                ],
                'payment' => [
                    'charge_id' => $chargeResult['charge_id'],
                    'amount' => (float) $package['price'],
                    'qr_code' => $chargeResult['qr_code'],
                    'qr_string' => $chargeResult['qr_string'],
                    'qr_url' => $chargeResult['qr_url'],
                    'expiry_time' => $chargeResult['expiry_time']
                ],
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ],
            'message' => 'Transaction created successfully. Please complete the payment.'
        ];

        http_response_code(201);
        echo json_encode($response);

    } catch (Exception $e) {
        // Rollback database transaction on error
        $db->rollback();

        // Log error
        logger()->error('Transaction creation failed', [
            'transaction_id' => $transactionId,
            'package_id' => $packageId,
            'error' => $e->getMessage()
        ]);

        throw $e;
    }

} catch (Exception $e) {
    // Log error
    logger()->error('API Error in buat_transaksi.php', [
        'error' => $e->getMessage(),
        'input' => $input ?? null,
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => 'Failed to create transaction. Please try again.',
        'debug_info' => is_production() ? null : $e->getMessage()
    ]);
}

// Close database connection
if (isset($db)) {
    $db->close();
}

?>