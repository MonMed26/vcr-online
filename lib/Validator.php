<?php
/**
 * Validator Class for WiFi Voucher System
 * Input validation and sanitization
 */

class Validator {
    /**
     * Validate required fields
     * @param array $data
     * @param array $required
     * @return array
     */
    public static function required($data, $required) {
        $errors = [];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = "Field {$field} is required";
            }
        }

        return $errors;
    }

    /**
     * Validate email
     * @param string $email
     * @return bool
     */
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate numeric value
     * @param mixed $value
     * @return bool
     */
    public static function numeric($value) {
        return is_numeric($value);
    }

    /**
     * Validate positive number
     * @param mixed $value
     * @return bool
     */
    public static function positive($value) {
        return is_numeric($value) && $value > 0;
    }

    /**
     * Validate integer
     * @param mixed $value
     * @return bool
     */
    public static function integer($value) {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validate minimum length
     * @param string $value
     * @param int $min
     * @return bool
     */
    public static function minLength($value, $min) {
        return strlen($value) >= $min;
    }

    /**
     * Validate maximum length
     * @param string $value
     * @param int $max
     * @return bool
     */
    public static function maxLength($value, $max) {
        return strlen($value) <= $max;
    }

    /**
     * Validate length between min and max
     * @param string $value
     * @param int $min
     * @param int $max
     * @return bool
     */
    public static function lengthBetween($value, $min, $max) {
        $length = strlen($value);
        return $length >= $min && $length <= $max;
    }

    /**
     * Validate transaction ID format
     * @param string $transactionId
     * @return bool
     */
    public static function transactionId($transactionId) {
        return preg_match('/^[A-Z0-9]{8,20}$/', $transactionId);
    }

    /**
     * Validate package exists in database
     * @param int $packageId
     * @return bool
     */
    public static function packageExists($packageId) {
        try {
            $db = Database::getInstance();
            $query = "SELECT id FROM packages WHERE id = ? AND is_active = 1";
            $result = $db->fetchOne($query, [$packageId], 'i');
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Validate transaction exists
     * @param string $transactionId
     * @return bool
     */
    public static function transactionExists($transactionId) {
        try {
            $db = Database::getInstance();
            $query = "SELECT id FROM transactions WHERE transaction_id = ?";
            $result = $db->fetchOne($query, [$transactionId]);
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Sanitize string input
     * @param string $input
     * @return string
     */
    public static function sanitize($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize alphanumeric string
     * @param string $input
     * @return string
     */
    public static function sanitizeAlphanumeric($input) {
        return preg_replace('/[^a-zA-Z0-9]/', '', $input);
    }

    /**
     * Generate secure random string
     * @param int $length
     * @return string
     */
    public static function generateRandomString($length = 10) {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Generate transaction ID
     * @return string
     */
    public static function generateTransactionId() {
        return TRANSACTION_ID_PREFIX . date('Ymd') . strtoupper(substr(uniqid(), -6));
    }

    /**
     * Generate username
     * @return string
     */
    public static function generateUsername() {
        return VOUCHER_USERNAME_PREFIX . substr(uniqid(), -6);
    }

    /**
     * Generate password
     * @param int $length
     * @return string
     */
    public static function generatePassword($length = 8) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $password;
    }

    /**
     * Validate webhook signature
     * @param array $payload
     * @param string $signature
     * @return bool
     */
    public static function validateWebhookSignature($payload, $signature) {
        $expectedSignature = hash_hmac(HASH_ALGO, json_encode($payload), QrisGateway['webhook_secret']);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Validate amount against package price
     * @param int $packageId
     * @param float $amount
     * @return bool
     */
    public static function validatePackageAmount($packageId, $amount) {
        try {
            $db = Database::getInstance();
            $query = "SELECT price FROM packages WHERE id = ? AND is_active = 1";
            $result = $db->fetchOne($query, [$packageId], 'i');

            if (empty($result)) {
                return false;
            }

            return abs(floatval($result['price']) - $amount) < 0.01; // Allow small floating point differences
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if transaction is expired
     * @param string $transactionId
     * @return bool
     */
    public static function isTransactionExpired($transactionId) {
        try {
            $db = Database::getInstance();
            $query = "SELECT created_at FROM transactions WHERE transaction_id = ?";
            $result = $db->fetchOne($query, [$transactionId]);

            if (empty($result)) {
                return true;
            }

            $createdAt = new DateTime($result['created_at']);
            $now = new DateTime();
            $expiryMinutes = QrisGateway['expiry_minutes'];

            $diff = $now->getTimestamp() - $createdAt->getTimestamp();
            return $diff > ($expiryMinutes * 60);
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * Validate rate limiting
     * @param string $identifier
     * @param int $maxRequests
     * @param int $timeWindow
     * @return bool
     */
    public static function checkRateLimit($identifier, $maxRequests = null, $timeWindow = null) {
        $maxRequests = $maxRequests ?? RATE_LIMIT_REQUESTS;
        $timeWindow = $timeWindow ?? RATE_LIMIT_WINDOW;

        $cacheKey = "rate_limit_{$identifier}";
        $currentCount = apcu_fetch($cacheKey, $success);

        if (!$success) {
            apcu_store($cacheKey, 1, $timeWindow);
            return true;
        }

        if ($currentCount >= $maxRequests) {
            return false;
        }

        apcu_inc($cacheKey);
        return true;
    }
}

?>