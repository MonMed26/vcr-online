<?php
/**
 * Manual Workflow Test Script
 * Simulates the complete user journey for testing purposes
 */

require_once 'config.php';

// Enable error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>WiFi Voucher System - Manual Workflow Test</h1>\n";

// Test function to display results
function showResult($test, $result, $details = '') {
    $status = $result ? '✅ PASSED' : '❌ FAILED';
    echo "<div style='margin: 10px 0; padding: 10px; border-left: 4px solid " . ($result ? '#4CAF50' : '#f44336') . "; background: #f5f5f5;'>";
    echo "<strong>{$test}:</strong> {$status}";
    if ($details) {
        echo "<br><small>{$details}</small>";
    }
    echo "</div>";
}

// Test 1: Check System Status
echo "<h2>1. System Status Check</h2>\n";

// Database
try {
    $db = Database::getInstance();
    $packages = $db->fetchAll("SELECT COUNT(*) as count FROM packages WHERE is_active = 1");
    showResult("Database Connection", true, "Found {$packages[0]['count']} active packages");
} catch (Exception $e) {
    showResult("Database Connection", false, $e->getMessage());
}

// MikroTik
try {
    $mikrotik = new MikroTik();
    $connected = $mikrotik->testConnection();
    showResult("MikroTik Connection", $connected, $connected ? "Connected successfully" : $mikrotik->getLastError());
} catch (Exception $e) {
    showResult("MikroTik Connection", false, $e->getMessage());
}

// Payment Gateway
try {
    $payment = new PaymentGateway();
    $connected = $payment->testConnection();
    showResult("Payment Gateway Connection", $connected, $connected ? "Connected successfully" : $payment->getLastError());
} catch (Exception $e) {
    showResult("Payment Gateway Connection", false, $e->getMessage());
}

// Test 2: Package Selection
echo "<h2>2. Package Selection</h2>\n";

try {
    $db = Database::getInstance();
    $packages = $db->fetchAll("SELECT * FROM packages WHERE is_active = 1 ORDER BY price ASC");

    if (empty($packages)) {
        showResult("Available Packages", false, "No active packages found");
    } else {
        showResult("Available Packages", true, count($packages) . " packages available");

        echo "<h3>Available Packages:</h3>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Price</th><th>Duration</th><th>Action</th></tr>";

        foreach ($packages as $package) {
            echo "<tr>";
            echo "<td>{$package['id']}</td>";
            echo "<td>" . htmlspecialchars($package['name']) . "</td>";
            echo "<td>Rp " . number_format($package['price'], 0, ',', '.') . "</td>";
            echo "<td>{$package['duration_hours']} hours</td>";
            echo "<td><button onclick='testPackage({$package['id']})'>Test This Package</button></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    showResult("Package Selection", false, $e->getMessage());
}

// Test 3: Transaction Creation
echo "<h2>3. Transaction Creation Test</h2>\n";

if (isset($_POST['test_package_id'])) {
    $packageId = (int) $_POST['test_package_id'];

    try {
        // Simulate API call
        $payload = json_encode(['package_id' => $packageId]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'http://localhost/api/buat_transaksi.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            showResult("Transaction Creation", false, "Curl error: {$error}");
        } elseif ($httpCode !== 201) {
            showResult("Transaction Creation", false, "HTTP {$httpCode}: {$response}");
        } else {
            $data = json_decode($response, true);
            if ($data['success']) {
                showResult("Transaction Creation", true, "Transaction ID: {$data['data']['transaction_id']}");
                $_SESSION['test_transaction_id'] = $data['data']['transaction_id'];

                echo "<div style='background: #e3f2fd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
                echo "<h4>Transaction Created Successfully!</h4>";
                echo "<p><strong>Transaction ID:</strong> {$data['data']['transaction_id']}</p>";
                echo "<p><strong>Package:</strong> {$data['data']['package']['name']}</p>";
                echo "<p><strong>Amount:</strong> Rp " . number_format($data['data']['package']['price'], 0, ',', '.') . "</p>";

                if (!empty($data['data']['payment']['qr_url'])) {
                    echo "<p><strong>QR Code:</strong></p>";
                    echo "<img src='{$data['data']['payment']['qr_url']}' alt='QR Code' style='max-width: 200px; border: 1px solid #ddd;'>";
                }

                echo "<p><button onclick='checkTransactionStatus(\"{$data['data']['transaction_id']}\")'>Check Status</button></p>";
                echo "</div>";
            } else {
                showResult("Transaction Creation", false, $data['error'] ?? 'Unknown error');
            }
        }
    } catch (Exception $e) {
        showResult("Transaction Creation", false, $e->getMessage());
    }
} else {
    echo "<p>Select a package from the table above to test transaction creation.</p>";
}

// Test 4: Status Check
echo "<h2>4. Transaction Status Check</h2>\n";

if (isset($_POST['check_transaction_id'])) {
    $transactionId = $_POST['check_transaction_id'];

    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "http://localhost/api/cek_status.php?trx=" . urlencode($transactionId),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            showResult("Status Check", false, "Curl error: {$error}");
        } elseif ($httpCode !== 200) {
            showResult("Status Check", false, "HTTP {$httpCode}: {$response}");
        } else {
            $data = json_decode($response, true);
            if ($data['success']) {
                showResult("Status Check", true, "Status: {$data['data']['status']}");

                echo "<div style='background: #f0f0f0; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
                echo "<h4>Transaction Status:</h4>";
                echo "<p><strong>Transaction ID:</strong> {$data['data']['transaction_id']}</p>";
                echo "<p><strong>Status:</strong> {$data['data']['status']}</p>";
                echo "<p><strong>Package:</strong> {$data['data']['package']['name']}</p>";

                if (isset($data['data']['voucher'])) {
                    echo "<h5>🎉 Voucher Information:</h5>";
                    echo "<p><strong>Username:</strong> {$data['data']['voucher']['username']}</p>";
                    echo "<p><strong>Password:</strong> {$data['data']['voucher']['password']}</p>";
                    echo "<p><strong>Expires:</strong> {$data['data']['voucher']['expires_at']}</p>";
                }

                echo "<p><strong>Message:</strong> {$data['message']}</p>";
                echo "</div>";
            } else {
                showResult("Status Check", false, $data['error'] ?? 'Unknown error');
            }
        }
    } catch (Exception $e) {
        showResult("Status Check", false, $e->getMessage());
    }
} else {
    echo "<p>Enter a transaction ID to check its status:</p>";
    echo "<form method='post'>";
    echo "<input type='text' name='check_transaction_id' placeholder='Transaction ID' style='padding: 8px; margin: 5px;'>";
    echo "<button type='submit' style='padding: 8px 15px;'>Check Status</button>";
    echo "</form>";
}

// Test 5: MikroTik User Creation
echo "<h2>5. MikroTik User Creation Test</h2>\n";

if (isset($_POST['test_mikrotik'])) {
    try {
        $mikrotik = new MikroTik();

        if (!$mikrotik->testConnection()) {
            showResult("MikroTik User Creation", false, "Not connected to MikroTik");
        } else {
            $testUsername = 'test_' . date('His');
            $testPassword = 'Test123456';
            $testProfile = '1_Hari';

            $result = $mikrotik->createHotspotUser($testUsername, $testPassword, $testProfile, 'Manual Test User');

            if ($result) {
                showResult("MikroTik User Creation", true, "User '{$testUsername}' created successfully");

                // Verify user was created
                sleep(2);
                $user = $mikrotik->getHotspotUser($testUsername);
                if ($user) {
                    showResult("User Verification", true, "User found in MikroTik");

                    // Cleanup
                    if ($mikrotik->deleteHotspotUser($testUsername)) {
                        showResult("User Cleanup", true, "Test user deleted successfully");
                    } else {
                        showResult("User Cleanup", false, "Failed to delete test user");
                    }
                } else {
                    showResult("User Verification", false, "User not found in MikroTik");
                }
            } else {
                showResult("MikroTik User Creation", false, $mikrotik->getLastError());
            }
        }
    } catch (Exception $e) {
        showResult("MikroTik User Creation", false, $e->getMessage());
    }
} else {
    echo "<form method='post'>";
    echo "<button type='submit' name='test_mikrotik' style='padding: 10px 20px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer;'>Test MikroTik User Creation</button>";
    echo "</form>";
    echo "<p><small>This will create a test user and then delete it to verify MikroTik integration.</small></p>";
}

// Test 6: Database Integrity
echo "<h2>6. Database Integrity Test</h2>\n";

try {
    $db = Database::getInstance();

    // Test foreign key constraints
    $testTransactionId = 'TEST_' . date('YmdHis');

    // Try to insert voucher without transaction (should fail)
    try {
        $db->insert('vouchers', [
            'transaction_id' => $testTransactionId,
            'username' => 'test_user',
            'password' => 'test_pass'
        ]);
        showResult("Foreign Key Constraint", false, "Should not allow voucher without transaction");
    } catch (Exception $e) {
        showResult("Foreign Key Constraint", true, "Correctly prevented invalid voucher creation");
    }

    // Test transaction consistency
    $package = $db->fetchOne("SELECT id, price FROM packages WHERE is_active = 1 LIMIT 1");
    if ($package) {
        // Create test transaction
        $transactionId = Validator::generateTransactionId();
        $db->insert('transactions', [
            'transaction_id' => $transactionId,
            'package_id' => $package['id'],
            'amount' => $package['price'],
            'status' => 'pending'
        ]);

        // Create voucher
        $db->insert('vouchers', [
            'transaction_id' => $transactionId,
            'username' => 'test_user_' . time(),
            'password' => 'test_pass'
        ]);

        // Verify consistency
        $transaction = $db->fetchOne("SELECT t.*, p.price as package_price FROM transactions t JOIN packages p ON t.package_id = p.id WHERE t.transaction_id = ?", [$transactionId]);
        $voucher = $db->fetchOne("SELECT * FROM vouchers WHERE transaction_id = ?", [$transactionId]);

        $consistent = ($transaction && $voucher && $transaction['amount'] == $transaction['package_price']);
        showResult("Transaction Consistency", $consistent, $consistent ? "Transaction and voucher data consistent" : "Data inconsistency detected");

        // Cleanup
        $db->delete('vouchers', 'transaction_id = ?', [$transactionId]);
        $db->delete('transactions', 'transaction_id = ?', [$transactionId]);
    }

} catch (Exception $e) {
    showResult("Database Integrity", false, $e->getMessage());
}

// JavaScript for interactive testing
?>
<script>
function testPackage(packageId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="test_package_id" value="' + packageId + '">';
    document.body.appendChild(form);
    form.submit();
}

function checkTransactionStatus(transactionId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="check_transaction_id" value="' + transactionId + '">';
    document.body.appendChild(form);
    form.submit();
}

// Auto-refresh status every 5 seconds if there's an active test transaction
<?php if (isset($_SESSION['test_transaction_id'])): ?>
setTimeout(() => {
    checkTransactionStatus('<?php echo $_SESSION['test_transaction_id']; ?>');
}, 5000);
<?php endif; ?>
</script>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #2196F3; }
h2 { color: #1976D2; border-bottom: 2px solid #2196F3; padding-bottom: 5px; }
h3 { color: #424242; }
table { width: 100%; margin: 10px 0; }
th { background: #f5f5f5; padding: 10px; text-align: left; }
td { padding: 10px; }
button { background: #4CAF50; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; }
button:hover { background: #45a049; }
</style>

<?php
echo "<h2>Summary</h2>";
echo "<p>This manual test allows you to interactively test the complete workflow:</p>";
echo "<ol>";
echo "<li>Select a package to create a transaction</li>";
echo "<li>Check the transaction status</li>";
echo "<li>Test MikroTik user creation</li>";
echo "<li>Verify database integrity</li>";
echo "</ol>";
echo "<p><strong>Note:</strong> This test creates real data in the database. Make sure to clean up test transactions manually if needed.</p>";
?>