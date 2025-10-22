<?php
/**
 * QRIS Payment Gateway Client
 * Handles communication with QRIS payment gateway API
 */

class PaymentGateway {
    private $apiUrl;
    private $apiKey;
    private $merchantId;
    private $webhookSecret;
    private $timeout;
    private $lastError;
    private $lastResponse;

    /**
     * Constructor
     */
    public function __construct() {
        $config = QrisGateway;
        $this->apiUrl = $config['api_url'];
        $this->apiKey = $config['api_key'];
        $this->merchantId = $config['merchant_id'];
        $this->webhookSecret = $config['webhook_secret'];
        $this->timeout = $config['timeout'];
    }

    /**
     * Create payment charge
     * @param string $transactionId
     * @param float $amount
     * @param string $description
     * @param array $customerInfo
     * @return array|null
     */
    public function createCharge($transactionId, $amount, $description = '', $customerInfo = []) {
        try {
            $payload = [
                'merchant_id' => $this->merchantId,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'description' => $description ?: "WiFi Voucher Purchase - {$transactionId}",
                'expiry_minutes' => QrisGateway['expiry_minutes'],
                'callback_url' => BASE_URL . '/api/webhook_qris.php',
                'customer_info' => $customerInfo
            ];

            $response = $this->makeRequest('/charge/create', 'POST', $payload);

            if ($response && isset($response['status']) && $response['status'] === 'success') {
                logger()->info("QRIS charge created", [
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'charge_id' => $response['charge_id'] ?? null
                ]);

                return [
                    'success' => true,
                    'charge_id' => $response['charge_id'] ?? $transactionId,
                    'qr_code' => $response['qr_code'] ?? null,
                    'qr_string' => $response['qr_string'] ?? null,
                    'qr_url' => $response['qr_url'] ?? null,
                    'expiry_time' => $response['expiry_time'] ?? null,
                    'amount' => $amount
                ];
            }

            $this->lastError = $response['message'] ?? 'Failed to create charge';
            logger()->error("QRIS charge creation failed", [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'error' => $this->lastError,
                'response' => $response
            ]);

            return [
                'success' => false,
                'error' => $this->lastError,
                'response' => $response
            ];

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            logger()->error("QRIS charge creation exception", [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check payment status
     * @param string $transactionId
     * @return array|null
     */
    public function checkStatus($transactionId) {
        try {
            $payload = [
                'merchant_id' => $this->merchantId,
                'transaction_id' => $transactionId
            ];

            $response = $this->makeRequest('/charge/status', 'POST', $payload);

            if ($response && isset($response['status'])) {
                logger()->debug("QRIS status checked", [
                    'transaction_id' => $transactionId,
                    'status' => $response['status'],
                    'payment_status' => $response['payment_status'] ?? null
                ]);

                return [
                    'success' => true,
                    'status' => $response['payment_status'] ?? 'unknown',
                    'charge_id' => $response['charge_id'] ?? $transactionId,
                    'amount' => $response['amount'] ?? 0,
                    'paid_amount' => $response['paid_amount'] ?? 0,
                    'payment_time' => $response['payment_time'] ?? null,
                    'payment_method' => $response['payment_method'] ?? null,
                    'expiry_time' => $response['expiry_time'] ?? null
                ];
            }

            $this->lastError = $response['message'] ?? 'Failed to check status';
            logger()->error("QRIS status check failed", [
                'transaction_id' => $transactionId,
                'error' => $this->lastError,
                'response' => $response
            ]);

            return [
                'success' => false,
                'error' => $this->lastError,
                'response' => $response
            ];

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            logger()->error("QRIS status check exception", [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel payment
     * @param string $transactionId
     * @return array|null
     */
    public function cancelCharge($transactionId) {
        try {
            $payload = [
                'merchant_id' => $this->merchantId,
                'transaction_id' => $transactionId,
                'reason' => 'User cancelled or expired'
            ];

            $response = $this->makeRequest('/charge/cancel', 'POST', $payload);

            if ($response && isset($response['status']) && $response['status'] === 'success') {
                logger()->info("QRIS charge cancelled", [
                    'transaction_id' => $transactionId
                ]);

                return [
                    'success' => true,
                    'message' => $response['message'] ?? 'Charge cancelled successfully'
                ];
            }

            $this->lastError = $response['message'] ?? 'Failed to cancel charge';
            logger()->error("QRIS charge cancellation failed", [
                'transaction_id' => $transactionId,
                'error' => $this->lastError,
                'response' => $response
            ]);

            return [
                'success' => false,
                'error' => $this->lastError
            ];

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            logger()->error("QRIS charge cancellation exception", [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify webhook signature
     * @param array $payload
     * @param string $signature
     * @return bool
     */
    public function verifyWebhookSignature($payload, $signature) {
        $expectedSignature = hash_hmac('sha256', json_encode($payload), $this->webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Make HTTP request to payment gateway
     * @param string $endpoint
     * @param string $method
     * @param array $data
     * @return array|null
     */
    private function makeRequest($endpoint, $method = 'GET', $data = []) {
        $url = $this->apiUrl . $endpoint;
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'User-Agent: WiFi-Voucher-System/1.0'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => false
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $this->lastResponse = [
            'url' => $url,
            'method' => $method,
            'data' => $data,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error
        ];

        if ($error) {
            throw new Exception("Curl error: {$error}");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("HTTP error: {$httpCode} - {$response}");
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }

        return $decodedResponse;
    }

    /**
     * Get last error
     * @return string|null
     */
    public function getLastError() {
        return $this->lastError;
    }

    /**
     * Get last response
     * @return array|null
     */
    public function getLastResponse() {
        return $this->lastResponse;
    }

    /**
     * Test connection to payment gateway
     * @return bool
     */
    public function testConnection() {
        try {
            $payload = [
                'merchant_id' => $this->merchantId,
                'test' => true
            ];

            $response = $this->makeRequest('/test', 'POST', $payload);

            if ($response && isset($response['status']) && $response['status'] === 'success') {
                logger()->info("Payment gateway connection test successful");
                return true;
            }

            $this->lastError = $response['message'] ?? 'Connection test failed';
            logger()->error("Payment gateway connection test failed", [
                'error' => $this->lastError,
                'response' => $response
            ]);

            return false;

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            logger()->error("Payment gateway connection test exception", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get merchant information
     * @return array|null
     */
    public function getMerchantInfo() {
        try {
            $payload = [
                'merchant_id' => $this->merchantId
            ];

            $response = $this->makeRequest('/merchant/info', 'POST', $payload);

            if ($response && isset($response['status']) && $response['status'] === 'success') {
                return [
                    'success' => true,
                    'merchant_id' => $response['merchant_id'] ?? $this->merchantId,
                    'merchant_name' => $response['merchant_name'] ?? '',
                    'status' => $response['merchant_status'] ?? 'unknown'
                ];
            }

            return [
                'success' => false,
                'error' => $response['message'] ?? 'Failed to get merchant info'
            ];

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate QR code image from QR string
     * @param string $qrString
     * @param int $size
     * @return string|null
     */
    public function generateQRImage($qrString, $size = 256) {
        try {
            // This would require a QR code library
            // For now, return placeholder URL or use external service
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($qrString);

            return $qrUrl;

        } catch (Exception $e) {
            logger()->error("Failed to generate QR image", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Format amount for payment gateway
     * @param float $amount
     * @return int
     */
    public function formatAmount($amount) {
        // Convert to cents (multiply by 100) for most payment gateways
        return (int) round($amount * 100);
    }

    /**
     * Parse amount from payment gateway response
     * @param int $amount
     * @return float
     */
    public function parseAmount($amount) {
        // Convert from cents to dollars
        return (float) $amount / 100;
    }
}

?>