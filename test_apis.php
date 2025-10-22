<?php
/**
 * API Testing Script
 * Test MikroTik and Payment Gateway integrations
 */

require_once 'config.php';

// Enable error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>WiFi Voucher System - API Testing</h1>";

// Test Database Connection
echo "<h2>Database Connection Test</h2>";
try {
    $db = Database::getInstance();
    echo "<p style='color: green;'>✅ Database connection successful!</p>";

    // Test query
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM packages");
    echo "<p>Found {$result['count']} packages in database.</p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Test MikroTik API
echo "<h2>MikroTik API Test</h2>";
try {
    $mikrotik = new MikroTik();

    echo "<h3>Connection Test</h3>";
    if ($mikrotik->testConnection()) {
        echo "<p style='color: green;'>✅ MikroTik connection successful!</p>";

        // Get system info
        $systemInfo = $mikrotik->getSystemInfo();
        if (!empty($systemInfo)) {
            echo "<h4>System Information:</h4>";
            echo "<pre>" . print_r($systemInfo, true) . "</pre>";
        }

        // Get hotspot profiles
        $profiles = $mikrotik->getHotspotProfiles();
        if (!empty($profiles)) {
            echo "<h4>Available Hotspot Profiles:</h4>";
            echo "<ul>";
            foreach ($profiles as $profile) {
                $name = $profile['name'] ?? 'Unknown';
                echo "<li>" . htmlspecialchars($name) . "</li>";
            }
            echo "</ul>";
        }

        // Test user creation (with test credentials)
        echo "<h3>User Creation Test</h3>";
        $testUsername = 'test_user_' . date('His');
        $testPassword = 'test123456';
        $testProfile = '1_Hari'; // Update with your actual profile

        if ($mikrotik->createHotspotUser($testUsername, $testPassword, $testProfile, 'Test User')) {
            echo "<p style='color: green;'>✅ Test user created successfully!</p>";
            echo "<p>Username: {$testUsername}</p>";
            echo "<p>Password: {$testPassword}</p>";
            echo "<p>Profile: {$testProfile}</p>";

            // Clean up - delete test user
            sleep(2);
            if ($mikrotik->deleteHotspotUser($testUsername)) {
                echo "<p style='color: orange;'>🧹 Test user cleaned up successfully.</p>";
            } else {
                echo "<p style='color: orange;'>⚠️ Warning: Could not clean up test user. Manual deletion may be required.</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Failed to create test user: " . $mikrotik->getLastError() . "</p>";
        }

    } else {
        echo "<p style='color: red;'>❌ MikroTik connection failed: " . $mikrotik->getLastError() . "</p>";
        echo "<p>Please check your MikroTik configuration in config.php:</p>";
        echo "<ul>";
        echo "<li>IP Address: " . MIKROTIK_HOST . "</li>";
        echo "<li>Port: " . MIKROTIK_PORT . "</li>";
        echo "<li>Username: " . MIKROTIK_USERNAME . "</li>";
        echo "<li>Password: [Hidden]</li>";
        echo "</ul>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ MikroTik API test failed: " . $e->getMessage() . "</p>";
}

// Test Payment Gateway API
echo "<h2>Payment Gateway API Test</h2>";
try {
    $payment = new PaymentGateway();

    echo "<h3>Connection Test</h3>";
    if ($payment->testConnection()) {
        echo "<p style='color: green;'>✅ Payment Gateway connection successful!</p>";

        // Get merchant info
        $merchantInfo = $payment->getMerchantInfo();
        if ($merchantInfo['success']) {
            echo "<h4>Merchant Information:</h4>";
            echo "<ul>";
            echo "<li>Merchant ID: " . htmlspecialchars($merchantInfo['merchant_id']) . "</li>";
            echo "<li>Merchant Name: " . htmlspecialchars($merchantInfo['merchant_name']) . "</li>";
            echo "<li>Status: " . htmlspecialchars($merchantInfo['status']) . "</li>";
            echo "</ul>";
        }

        // Test charge creation (in test mode)
        echo "<h3>Test Charge Creation</h3>";
        $testTransactionId = Validator::generateTransactionId();
        $testAmount = 5000.00;

        $chargeResult = $payment->createCharge(
            $testTransactionId,
            $testAmount,
            'Test Payment - WiFi Voucher System'
        );

        if ($chargeResult['success']) {
            echo "<p style='color: green;'>✅ Test charge created successfully!</p>";
            echo "<p>Transaction ID: {$testTransactionId}</p>";
            echo "<p>Amount: Rp " . number_format($testAmount, 2) . "</p>";
            echo "<p>Charge ID: " . ($chargeResult['charge_id'] ?? 'N/A') . "</p>";

            if (!empty($chargeResult['qr_url'])) {
                echo "<p>QR Code URL: <a href='" . htmlspecialchars($chargeResult['qr_url']) . "' target='_blank'>View QR Code</a></p>";
            }

            // Check status after a delay
            echo "<h3>Status Check Test</h3>";
            sleep(3);
            $statusResult = $payment->checkStatus($testTransactionId);
            if ($statusResult['success']) {
                echo "<p style='color: green;'>✅ Status check successful!</p>";
                echo "<p>Payment Status: " . htmlspecialchars($statusResult['status']) . "</p>";
                echo "<p>Amount: Rp " . number_format($statusResult['amount'], 2) . "</p>";
            } else {
                echo "<p style='color: orange;'>⚠️ Status check: " . htmlspecialchars($statusResult['error']) . "</p>";
            }

            // Clean up - cancel test charge
            $cancelResult = $payment->cancelCharge($testTransactionId);
            if ($cancelResult['success']) {
                echo "<p style='color: orange;'>🧹 Test charge cancelled.</p>";
            }

        } else {
            echo "<p style='color: red;'>❌ Failed to create test charge: " . htmlspecialchars($chargeResult['error']) . "</p>";
        }

    } else {
        echo "<p style='color: red;'>❌ Payment Gateway connection failed: " . $payment->getLastError() . "</p>";
        echo "<p>Please check your Payment Gateway configuration in config.php:</p>";
        echo "<ul>";
        echo "<li>API URL: " . QrisGateway['api_url'] . "</li>";
        echo "<li>Merchant ID: " . QrisGateway['merchant_id'] . "</li>";
        echo "<li>API Key: [Hidden]</li>";
        echo "</ul>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Payment Gateway API test failed: " . $e->getMessage() . "</p>";
}

// Test Validator functions
echo "<h2>Validator Functions Test</h2>";

// Test transaction ID generation
echo "<h3>Transaction ID Generation</h3>";
for ($i = 0; $i < 5; $i++) {
    $transactionId = Validator::generateTransactionId();
    echo "<p>Generated: {$transactionId}</p>";
}

// Test username/password generation
echo "<h3>Credential Generation</h3>";
for ($i = 0; $i < 3; $i++) {
    $username = Validator::generateUsername();
    $password = Validator::generatePassword();
    echo "<p>Username: {$username}, Password: {$password}</p>";
}

// Test webhook signature
echo "<h3>Webhook Signature Test</h3>";
$testPayload = [
    'transaction_id' => 'TRX123456',
    'status' => 'success',
    'amount' => 5000
];
$signature = generate_webhook_signature($testPayload);
$isValid = verify_webhook_signature($testPayload, $signature);

echo "<p>Test Payload: " . json_encode($testPayload) . "</p>";
echo "<p>Generated Signature: {$signature}</p>";
echo "<p>Signature Valid: " . ($isValid ? "✅ Yes" : "❌ No") . "</p>";

// Test rate limiting
echo "<h3>Rate Limiting Test</h3>";
$testIp = '127.0.0.1';
for ($i = 0; $i < 12; $i++) {
    $allowed = Validator::checkRateLimit($testIp, 10, 60);
    echo "<p>Request " . ($i + 1) . ": " . ($allowed ? "✅ Allowed" : "❌ Rate Limited") . "</p>";
}

echo "<h2>Test Complete</h2>";
echo "<p>Check the results above to ensure all integrations are working correctly.</p>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Fix any failed API connections</li>";
echo "<li>Update configuration values in config.php</li>";
echo "<li>Proceed to backend API endpoint implementation</li>";
echo "</ol>";

?>