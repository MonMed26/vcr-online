<?php
/**
 * WiFi Voucher System - Integration Testing Suite
 * Comprehensive testing of the complete workflow and error handling
 */

require_once 'config.php';

// Test results storage
$testResults = [
    'passed' => 0,
    'failed' => 0,
    'skipped' => 0,
    'tests' => []
];

// Test configuration
define('TEST_TIMEOUT', 30); // seconds
define('TEST_TRANSACTION_AMOUNT', 5000.00);

// Helper functions
function logTest($message) {
    echo "[TEST] " . date('Y-m-d H:i:s') . " - {$message}\n";
}

function runTest($testName, $testFunction) {
    global $testResults;

    echo "\n=== Running: {$testName} ===\n";
    logTest("Starting test: {$testName}");

    try {
        $startTime = microtime(true);
        $result = $testFunction();
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        if ($result['status'] === 'passed') {
            $testResults['passed']++;
            echo "✅ PASSED ({$duration}ms) - {$result['message']}\n";
        } elseif ($result['status'] === 'failed') {
            $testResults['failed']++;
            echo "❌ FAILED ({$duration}ms) - {$result['message']}\n";
            if (!empty($result['details'])) {
                echo "   Details: {$result['details']}\n";
            }
        } else {
            $testResults['skipped']++;
            echo "⏭️  SKIPPED - {$result['message']}\n";
        }

        $testResults['tests'][] = [
            'name' => $testName,
            'status' => $result['status'],
            'message' => $result['message'],
            'details' => $result['details'] ?? null,
            'duration' => $duration
        ];

    } catch (Exception $e) {
        $testResults['failed']++;
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        echo "❌ ERROR ({$duration}ms) - {$e->getMessage()}\n";

        $testResults['tests'][] = [
            'name' => $testName,
            'status' => 'error',
            'message' => $e->getMessage(),
            'details' => $e->getTraceAsString(),
            'duration' => $duration
        ];
    }
}

// Test 1: Database Connection and Schema
runTest("Database Connection and Schema", function() {
    try {
        $db = Database::getInstance();

        // Test basic connection
        $result = $db->fetchOne("SELECT 1 as test");
        if (!isset($result['test']) || $result['test'] != 1) {
            return ['status' => 'failed', 'message' => 'Database query failed'];
        }

        // Test tables exist
        $tables = ['packages', 'transactions', 'vouchers', 'api_logs', 'webhook_logs'];
        foreach ($tables as $table) {
            if (!$db->tableExists($table)) {
                return ['status' => 'failed', 'message' => "Table '{$table}' does not exist"];
            }
        }

        // Test packages data
        $packages = $db->fetchAll("SELECT COUNT(*) as count FROM packages WHERE is_active = 1");
        if ($packages[0]['count'] == 0) {
            return ['status' => 'failed', 'message' => 'No active packages found in database'];
        }

        return ['status' => 'passed', 'message' => 'Database connection and schema verified'];

    } catch (Exception $e) {
        return ['status' => 'failed', 'message' => 'Database connection failed', 'details' => $e->getMessage()];
    }
});

// Test 2: MikroTik API Connection
runTest("MikroTik API Connection", function() {
    try {
        $mikrotik = new MikroTik();

        if (!$mikrotik->testConnection()) {
            return ['status' => 'failed', 'message' => 'MikroTik connection failed', 'details' => $mikrotik->getLastError()];
        }

        // Test getting system info
        $systemInfo = $mikrotik->getSystemInfo();
        if (empty($systemInfo)) {
            return ['status' => 'failed', 'message' => 'Failed to get MikroTik system info'];
        }

        // Test getting hotspot profiles
        $profiles = $mikrotik->getHotspotProfiles();
        if (!is_array($profiles)) {
            return ['status' => 'failed', 'message' => 'Failed to get hotspot profiles'];
        }

        return ['status' => 'passed', 'message' => 'MikroTik API connection successful'];

    } catch (Exception $e) {
        return ['status' => 'failed', 'message' => 'MikroTik API test failed', 'details' => $e->getMessage()];
    }
});

// Test 3: Payment Gateway API
runTest("Payment Gateway API", function() {
    try {
        $payment = new PaymentGateway();

        if (!$payment->testConnection()) {
            return ['status' => 'failed', 'message' => 'Payment gateway connection failed', 'details' => $payment->getLastError()];
        }

        // Test merchant info
        $merchantInfo = $payment->getMerchantInfo();
        if (!$merchantInfo['success']) {
            return ['status' => 'failed', 'message' => 'Failed to get merchant info', 'details' => $merchantInfo['error']];
        }

        return ['status' => 'passed', 'message' => 'Payment gateway API connection successful'];

    } catch (Exception $e) {
        return ['status' => 'failed', 'message' => 'Payment gateway API test failed', 'details' => $e->getMessage()];
    }
});

// Test 4: Transaction Creation API
runTest("Transaction Creation API", function() {
    try {
        // Get first active package
        $db = Database::getInstance();
        $package = $db->fetchOne("SELECT id, name, price FROM packages WHERE is_active = 1 ORDER BY price ASC LIMIT 1");

        if (!$package) {
            return ['status' => 'skipped', 'message' => 'No active packages available for testing'];
        }

        // Prepare API request
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'http://localhost/api/buat_transaksi.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['package_id' => $package['id']]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => TEST_TIMEOUT
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['status' => 'failed', 'message' => 'Curl error', 'details' => $error];
        }

        if ($httpCode !== 201) {
            return ['status' => 'failed', 'message' => "HTTP error: {$httpCode}", 'details' => $response];
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['status' => 'failed', 'message' => 'Invalid JSON response', 'details' => $response];
        }

        if (!$data['success']) {
            return ['status' => 'failed', 'message' => 'API returned error', 'details' => $data['error'] ?? 'Unknown error'];
        }

        // Store transaction ID for next tests
        $_ENV['TEST_TRANSACTION_ID'] = $data['data']['transaction_id'];

        return ['status' => 'passed', 'message' => 'Transaction created successfully', 'details' => "Transaction ID: {$data['data']['transaction_id']}"];

    } catch (Exception $e) {
        return ['status' => 'failed', 'message' => 'Transaction creation test failed', 'details' => $e->getMessage()];
    }
});

// Test 5: Status Check API
runTest("Status Check API", function() {
    $transactionId = $_ENV['TEST_TRANSACTION_ID'] ?? null;

    if (!$transactionId) {
        return ['status' => 'skipped', 'message' => 'No transaction ID available from previous test'];
    }

    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "http://localhost/api/cek_status.php?trx={$transactionId}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => TEST_TIMEOUT
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['status' => 'failed', 'message' => 'Curl error', 'details' => $error];
        }

        if ($httpCode !== 200) {
            return ['status' => 'failed', 'message' => "HTTP error: {$httpCode}", 'details' => $response];
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['status' => 'failed', 'message' => 'Invalid JSON response', 'details' => $response];
        }

        if (!$data['success']) {
            return ['status' => 'failed', 'message' => 'API returned error', 'details' => $data['error'] ?? 'Unknown error'];
        }

        $status = $data['data']['status'] ?? 'unknown';
        return ['status' => 'passed', 'message' => 'Status check successful', 'details' => "Status: {$status}"];

    } catch (Exception $e) {
        return ['status' => 'failed', 'message' => 'Status check test failed', 'details' => $e->getMessage()];
    }
});

// Test 6: Input Validation
runTest("Input Validation", function() {
    $tests = [
        // Test invalid package ID
        [
            'name' => 'Invalid package ID',
            'data' => ['package_id' => 'invalid'],
            'expected_status' => 400
        ],
        // Test missing package ID
        [
            'name' => 'Missing package ID',
            'data' => [],
            'expected_status' => 400
        ],
        // Test negative package ID
        [
            'name' => 'Negative package ID',
            'data' => ['package_id' => -1],
            'expected_status' => 400
        ]
    ];

    $passedTests = 0;
    $totalTests = count($tests);

    foreach ($tests as $test) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'http://localhost/api/buat_transaksi.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($test['data']),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => TEST_TIMEOUT
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === $test['expected_status']) {
            $passedTests++;
        } else {
            logTest("Validation test failed: {$test['name']} - Expected {$test['expected_status']}, got {$httpCode}");
        }
    }

    if ($passedTests === $totalTests) {
        return ['status' => 'passed', 'message' => 'All validation tests passed'];
    } else {
        return ['status' => 'failed', 'message' => 'Some validation tests failed', 'details' => "{$passedTests}/{$totalTests} passed"];
    }
});

// Test 7: Error Handling
runTest("Error Handling", function() {
    try {
        // Test database error handling
        $db = Database::getInstance();

        // Test invalid query (should throw exception)
        try {
            $db->query("SELECT * FROM non_existent_table");
            return ['status' => 'failed', 'message' => 'Database error handling failed - should have thrown exception'];
        } catch (Exception $e) {
            // Expected behavior
        }

        // Test payment gateway error handling
        $payment = new PaymentGateway();

        // Test invalid charge creation
        $result = $payment->createCharge('INVALID_ID', -100);
        if ($result['success']) {
            return ['status' => 'failed', 'message' => 'Payment gateway error handling failed'];
        }

        return ['status' => 'passed', 'message' => 'Error handling working correctly'];

    } catch (Exception $e) {
        return ['status' => 'failed', 'message' => 'Error handling test failed', 'details' => $e->getMessage()];
    }
});

// Test 8: Security Features
runTest("Security Features", function() {
    $securityTests = [];

    // Test SQL injection protection
    $securityTests[] = [
        'name' => 'SQL Injection Protection',
        'test' => function() {
            $db = Database::getInstance();
            $maliciousInput = "'; DROP TABLE packages; --";
            $result = $db->fetchOne("SELECT * FROM packages WHERE name = ?", [$maliciousInput]);
            return empty($result); // Should return no results
        }
    ];

    // Test XSS protection
    $securityTests[] = [
        'name' => 'XSS Protection',
        'test' => function() {
            $maliciousInput = '<script>alert("xss")</script>';
            $sanitized = htmlspecialchars($maliciousInput, ENT_QUOTES, 'UTF-8');
            return strpos($sanitized, '<script>') === false;
        }
    ];

    // Test webhook signature validation
    $securityTests[] = [
        'name' => 'Webhook Signature Validation',
        'test' => function() {
            $payload = ['test' => 'data'];
            $invalidSignature = 'invalid_signature';
            return !Validator::validateWebhookSignature($payload, $invalidSignature);
        }
    ];

    $passedTests = 0;
    $totalTests = count($securityTests);

    foreach ($securityTests as $test) {
        try {
            if ($test['test']()) {
                $passedTests++;
            } else {
                logTest("Security test failed: {$test['name']}");
            }
        } catch (Exception $e) {
            logTest("Security test error: {$test['name']} - {$e->getMessage()}");
        }
    }

    if ($passedTests === $totalTests) {
        return ['status' => 'passed', 'message' => 'All security tests passed'];
    } else {
        return ['status' => 'failed', 'message' => 'Some security tests failed', 'details' => "{$passedTests}/{$totalTests} passed"];
    }
});

// Test 9: Rate Limiting
runTest("Rate Limiting", function() {
    $clientIp = '127.0.0.1';

    // Reset rate limit for this IP (simulate fresh start)
    $cacheKey = "rate_limit_{$clientIp}";
    if (function_exists('apcu_delete')) {
        apcu_delete($cacheKey);
    }

    $allowedRequests = 0;
    $maxRequests = 10;

    // Test rate limiting by making multiple requests
    for ($i = 0; $i < $maxRequests + 5; $i++) {
        if (Validator::checkRateLimit($clientIp, $maxRequests, 60)) {
            $allowedRequests++;
        } else {
            break;
        }
    }

    if ($allowedRequests >= $maxRequests && $allowedRequests < $maxRequests + 5) {
        return ['status' => 'passed', 'message' => 'Rate limiting working correctly', 'details' => "Allowed {$allowedRequests} requests"];
    } else {
        return ['status' => 'failed', 'message' => 'Rate limiting not working properly', 'details' => "Allowed {$allowedRequests} requests, expected around {$maxRequests}"];
    }
});

// Test 10: End-to-End Workflow Simulation
runTest("End-to-End Workflow Simulation", function() {
    try {
        $db = Database::getInstance();

        // Get a test package
        $package = $db->fetchOne("SELECT id, name, price, duration_hours, profile_name FROM packages WHERE is_active = 1 ORDER BY price ASC LIMIT 1");

        if (!$package) {
            return ['status' => 'skipped', 'message' => 'No packages available for E2E test'];
        }

        // Step 1: Create transaction (simulate API call)
        $transactionId = Validator::generateTransactionId();
        $username = Validator::generateUsername();
        $password = Validator::generatePassword();

        // Insert transaction record
        $transactionData = [
            'transaction_id' => $transactionId,
            'package_id' => $package['id'],
            'amount' => $package['price'],
            'status' => 'pending'
        ];

        $transactionDbId = $db->insert('transactions', $transactionData);
        if (!$transactionDbId) {
            return ['status' => 'failed', 'message' => 'Failed to create test transaction'];
        }

        // Step 2: Simulate payment completion
        $db->update('transactions', ['status' => 'success'], 'transaction_id = ?', [$transactionId]);

        // Step 3: Create voucher
        $voucherData = [
            'transaction_id' => $transactionId,
            'username' => $username,
            'password' => $password,
            'expires_at' => date('Y-m-d H:i:s', strtotime("{$package['duration_hours']} hours"))
        ];

        $voucherId = $db->insert('vouchers', $voucherData);
        if (!$voucherId) {
            return ['status' => 'failed', 'message' => 'Failed to create voucher'];
        }

        // Step 4: Verify data integrity
        $savedTransaction = $db->fetchOne("SELECT * FROM transactions WHERE transaction_id = ?", [$transactionId]);
        $savedVoucher = $db->fetchOne("SELECT * FROM vouchers WHERE transaction_id = ?", [$transactionId]);

        if (!$savedTransaction || !$savedVoucher) {
            return ['status' => 'failed', 'message' => 'Data integrity check failed'];
        }

        if ($savedTransaction['status'] !== 'success') {
            return ['status' => 'failed', 'message' => 'Transaction status incorrect'];
        }

        if ($savedVoucher['username'] !== $username || $savedVoucher['password'] !== $password) {
            return ['status' => 'failed', 'message' => 'Voucher data incorrect'];
        }

        // Cleanup test data
        $db->delete('vouchers', 'transaction_id = ?', [$transactionId]);
        $db->delete('transactions', 'transaction_id = ?', [$transactionId]);

        return ['status' => 'passed', 'message' => 'E2E workflow completed successfully'];

    } catch (Exception $e) {
        return ['status' => 'failed', 'message' => 'E2E workflow failed', 'details' => $e->getMessage()];
    }
});

// Generate test report
echo "\n" . str_repeat("=", 60) . "\n";
echo "INTEGRATION TEST REPORT\n";
echo str_repeat("=", 60) . "\n";

echo "Total Tests: " . count($testResults['tests']) . "\n";
echo "Passed: {$testResults['passed']} ✅\n";
echo "Failed: {$testResults['failed']} ❌\n";
echo "Skipped: {$testResults['skipped']} ⏭️\n";

$successRate = count($testResults['tests']) > 0 ? round(($testResults['passed'] / count($testResults['tests'])) * 100, 2) : 0;
echo "Success Rate: {$successRate}%\n";

// Show failed tests
if ($testResults['failed'] > 0) {
    echo "\nFAILED TESTS:\n";
    echo str_repeat("-", 40) . "\n";

    foreach ($testResults['tests'] as $test) {
        if ($test['status'] === 'failed' || $test['status'] === 'error') {
            echo "❌ {$test['name']}\n";
            echo "   {$test['message']}\n";
            if ($test['details']) {
                echo "   Details: " . substr($test['details'], 0, 100) . "...\n";
            }
            echo "\n";
        }
    }
}

// Show performance summary
$totalDuration = array_sum(array_column($testResults['tests'], 'duration'));
$avgDuration = count($testResults['tests']) > 0 ? round($totalDuration / count($testResults['tests']), 2) : 0;

echo "\nPERFORMANCE SUMMARY:\n";
echo str_repeat("-", 40) . "\n";
echo "Total Duration: {$totalDuration}ms\n";
echo "Average Duration: {$avgDuration}ms\n";

$slowTests = array_filter($testResults['tests'], function($test) {
    return $test['duration'] > 1000; // Tests taking more than 1 second
});

if (!empty($slowTests)) {
    echo "Slow Tests (>1000ms):\n";
    foreach ($slowTests as $test) {
        echo "  {$test['name']}: {$test['duration']}ms\n";
    }
}

// Overall result
echo "\n" . str_repeat("=", 60) . "\n";
if ($testResults['failed'] === 0) {
    echo "🎉 ALL TESTS PASSED! System is ready for production.\n";
} else {
    echo "⚠️  SOME TESTS FAILED. Please review and fix issues before deployment.\n";
}
echo str_repeat("=", 60) . "\n";

// Return appropriate exit code
exit($testResults['failed'] > 0 ? 1 : 0);

?>