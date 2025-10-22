<?php
/**
 * MikroTik RouterOS API Client
 * Handles communication with MikroTik hotspot user management
 */

class MikroTik {
    private $host;
    private $port;
    private $username;
    private $password;
    private $timeout;
    private $socket;
    private $connected = false;
    private $lastError;

    /**
     * Constructor
     */
    public function __construct() {
        $this->host = MIKROTIK_HOST;
        $this->port = MIKROTIK_PORT;
        $this->username = MIKROTIK_USERNAME;
        $this->password = MIKROTIK_PASSWORD;
        $this->timeout = MIKROTIK_TIMEOUT;
    }

    /**
     * Connect to MikroTik RouterOS
     * @return bool
     */
    public function connect() {
        try {
            $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

            if (!$this->socket) {
                throw new Exception("Connection failed: {$errstr} (Error: {$errno})");
            }

            socket_set_timeout($this->socket, $this->timeout);

            // Read login prompt
            $response = $this->read();
            if ($response === false || strpos($response, '!done') === false) {
                throw new Exception("Invalid login response");
            }

            // Login
            $this->write('/login');
            $this->write('=name=' . $this->username, false);
            $this->write('=password=' . $this->password);

            $response = $this->read();
            if ($response === false) {
                throw new Exception("Login failed");
            }

            // Check for challenge response
            if (strpos($response, '!trap') !== false) {
                // Extract challenge
                if (preg_match('/=ret=([a-f0-9]+)/i', $response, $matches)) {
                    $challenge = hex2bin($matches[1]);
                    $password = md5(chr(0) . $this->password . $challenge);

                    // Send challenge response
                    $this->write('/login');
                    $this->write('=name=' . $this->username, false);
                    $this->write('=response=00' . bin2hex($password));

                    $response = $this->read();
                    if ($response === false || strpos($response, '!done') === false) {
                        throw new Exception("Challenge authentication failed");
                    }
                } else {
                    throw new Exception("Invalid challenge format");
                }
            }

            $this->connected = true;
            logger()->info("Connected to MikroTik RouterOS at {$this->host}:{$this->port}");
            return true;

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            logger()->error("MikroTik connection failed", ['error' => $e->getMessage()]);
            $this->connected = false;
            return false;
        }
    }

    /**
     * Disconnect from MikroTik
     */
    public function disconnect() {
        if ($this->socket && $this->connected) {
            $this->write('/quit');
            fclose($this->socket);
            $this->connected = false;
            logger()->info("Disconnected from MikroTik RouterOS");
        }
    }

    /**
     * Check if connected
     * @return bool
     */
    public function isConnected() {
        return $this->connected;
    }

    /**
     * Get last error
     * @return string|null
     */
    public function getLastError() {
        return $this->lastError;
    }

    /**
     * Write command to MikroTik
     * @param string $command
     * @param bool $newLine
     */
    private function write($command, $newLine = true) {
        if (!$this->socket) {
            throw new Exception("Not connected to MikroTik");
        }

        $command = $newLine ? $command . "\n" : $command;
        fwrite($this->socket, $command);
        logger()->debug("MikroTik command sent", ['command' => trim($command)]);
    }

    /**
     * Read response from MikroTik
     * @return string|false
     */
    private function read() {
        if (!$this->socket) {
            throw new Exception("Not connected to MikroTik");
        }

        $response = '';
        while (true) {
            $line = fgets($this->socket);
            if ($line === false) {
                return false;
            }

            $response .= $line;

            // End of response marker
            if (rtrim($line) === '!done') {
                break;
            }
        }

        logger()->debug("MikroTik response received", ['response' => $response]);
        return $response;
    }

    /**
     * Execute command and get response
     * @param string $command
     * @param array $params
     * @return array
     */
    public function execute($command, $params = []) {
        if (!$this->connected) {
            if (!$this->connect()) {
                throw new Exception("Cannot execute command: Not connected");
            }
        }

        try {
            // Send command
            $this->write($command);

            // Send parameters
            foreach ($params as $key => $value) {
                $this->write('=' . $key . '=' . $value);
            }

            // End command
            $this->write('.tag=command');

            $response = $this->read();
            return $this->parseResponse($response);

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            logger()->error("MikroTik command failed", [
                'command' => $command,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Parse MikroTik API response
     * @param string $response
     * @return array
     */
    private function parseResponse($response) {
        $result = [];
        $lines = explode("\n", $response);
        $currentItem = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            if ($line === '!re') {
                // Start new record
                $currentItem = [];
                continue;
            }

            if ($line === '!done') {
                // End of response
                if ($currentItem !== null) {
                    $result[] = $currentItem;
                }
                break;
            }

            if ($line === '!trap') {
                // Error response
                continue;
            }

            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);

                if ($currentItem !== null) {
                    $currentItem[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Create hotspot user
     * @param string $username
     * @param string $password
     * @param string $profile
     * @param string $comment
     * @return bool
     */
    public function createHotspotUser($username, $password, $profile, $comment = '') {
        try {
            $params = [
                'name' => $username,
                'password' => $password,
                'profile' => $profile,
                'comment' => $comment ?: "Auto-generated via WiFi Voucher System"
            ];

            $response = $this->execute('/ip/hotspot/user/add', $params);

            // Check if user was created successfully
            if (!empty($response) && isset($response[0]['!re'])) {
                logger()->info("MikroTik hotspot user created", [
                    'username' => $username,
                    'profile' => $profile
                ]);
                return true;
            }

            // Try to get the user ID from response
            if (!empty($response) && isset($response[0]['.id'])) {
                logger()->info("MikroTik hotspot user created", [
                    'username' => $username,
                    'profile' => $profile,
                    'mikrotik_id' => $response[0]['.id']
                ]);
                return true;
            }

            throw new Exception("Invalid response when creating user");

        } catch (Exception $e) {
            logger()->error("Failed to create MikroTik hotspot user", [
                'username' => $username,
                'profile' => $profile,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get hotspot user by username
     * @param string $username
     * @return array|null
     */
    public function getHotspotUser($username) {
        try {
            $params = ['?name' => $username];
            $response = $this->execute('/ip/hotspot/user/print', $params);

            if (!empty($response)) {
                return $response[0];
            }

            return null;

        } catch (Exception $e) {
            logger()->error("Failed to get MikroTik hotspot user", [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Delete hotspot user
     * @param string $username
     * @return bool
     */
    public function deleteHotspotUser($username) {
        try {
            $user = $this->getHotspotUser($username);
            if (!$user || !isset($user['.id'])) {
                return false;
            }

            $params = ['.id' => $user['.id']];
            $this->execute('/ip/hotspot/user/remove', $params);

            logger()->info("MikroTik hotspot user deleted", ['username' => $username]);
            return true;

        } catch (Exception $e) {
            logger()->error("Failed to delete MikroTik hotspot user", [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get available hotspot profiles
     * @return array
     */
    public function getHotspotProfiles() {
        try {
            $response = $this->execute('/ip/hotspot/user/profile/print');
            return $response;

        } catch (Exception $e) {
            logger()->error("Failed to get MikroTik hotspot profiles", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Test connection
     * @return bool
     */
    public function testConnection() {
        try {
            if (!$this->connected) {
                if (!$this->connect()) {
                    return false;
                }
            }

            // Try to get system identity
            $response = $this->execute('/system/identity/print');
            return !empty($response);

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Get system info
     * @return array
     */
    public function getSystemInfo() {
        try {
            $identity = $this->execute('/system/identity/print');
            $resource = $this->execute('/system/resource/print');

            return [
                'identity' => !empty($identity) ? $identity[0] : null,
                'resource' => !empty($resource) ? $resource[0] : null
            ];

        } catch (Exception $e) {
            logger()->error("Failed to get MikroTik system info", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Destructor
     */
    public function __destruct() {
        $this->disconnect();
    }
}

?>