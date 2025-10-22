<?php
/**
 * Logger Class for WiFi Voucher System
 * Simple file-based logging system
 */

class Logger {
    private $logFile;
    private $maxSize;
    private $maxFiles;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logFile = LOG_FILE;
        $this->maxSize = LOG_MAX_SIZE;
        $this->maxFiles = LOG_FILES_TO_KEEP;

        // Create log directory if it doesn't exist
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Log message with level
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, $context = []) {
        if (!LOG_ENABLED) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;

        $this->writeLog($logEntry);
    }

    /**
     * Log debug message
     * @param string $message
     * @param array $context
     */
    public function debug($message, $context = []) {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Log info message
     * @param string $message
     * @param array $context
     */
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }

    /**
     * Log warning message
     * @param string $message
     * @param array $context
     */
    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Log error message
     * @param string $message
     * @param array $context
     */
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Write log to file with rotation
     * @param string $logEntry
     */
    private function writeLog($logEntry) {
        // Check if log rotation is needed
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxSize) {
            $this->rotateLogs();
        }

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Rotate log files
     */
    private function rotateLogs() {
        $pathInfo = pathinfo($this->logFile);
        $baseName = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        $directory = $pathInfo['dirname'];

        // Remove oldest log file if it exists
        $oldestLog = $directory . '/' . $baseName . '.' . $this->maxFiles . $extension;
        if (file_exists($oldestLog)) {
            unlink($oldestLog);
        }

        // Rotate existing log files
        for ($i = $this->maxFiles - 1; $i > 0; $i--) {
            $oldLog = $directory . '/' . $baseName . '.' . $i . $extension;
            $newLog = $directory . '/' . $baseName . '.' . ($i + 1) . $extension;

            if (file_exists($oldLog)) {
                rename($oldLog, $newLog);
            }
        }

        // Move current log to .1
        $firstLog = $directory . '/' . $baseName . '.1' . $extension;
        rename($this->logFile, $firstLog);
    }

    /**
     * Get recent log entries
     * @param int $lines
     * @return array
     */
    public function getRecentLogs($lines = 50) {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $handle = fopen($this->logFile, 'r');
        $logs = [];

        if ($handle) {
            // Go to end of file
            fseek($handle, 0, SEEK_END);
            $position = ftell($handle);
            $lineCount = 0;

            // Read backwards line by line
            while ($position > 0 && $lineCount < $lines) {
                $position--;
                fseek($handle, $position);
                $char = fgetc($handle);

                if ($char === "\n" || $position === 0) {
                    if ($position === 0) {
                        $line = fgets($handle);
                    } else {
                        $line = fgets($handle);
                    }

                    if ($line !== false) {
                        array_unshift($logs, trim($line));
                        $lineCount++;
                    }
                }
            }

            fclose($handle);
        }

        return $logs;
    }
}

// Global logger instance
if (!function_exists('logger')) {
    function logger() {
        static $logger = null;
        if ($logger === null) {
            $logger = new Logger();
        }
        return $logger;
    }
}

?>