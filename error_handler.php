<?php
/**
 * Global Error Handler for WiFi Voucher System
 * Provides centralized error handling and logging
 */

// Set custom error handler
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');

// Register shutdown function for fatal errors
register_shutdown_function('shutdownHandler');

/**
 * Custom error handler
 * @param int $errno
 * @param string $errstr
 * @param string $errfile
 * @param int $errline
 * @return bool
 */
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    // Don't handle errors if suppressed with @
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $errorTypes = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];

    $errorType = $errorTypes[$errno] ?? 'Unknown Error';

    $errorData = [
        'type' => $errorType,
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline,
        'context' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
        'timestamp' => date('Y-m-d H:i:s'),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];

    // Log error
    logError($errorData);

    // For production, don't display errors
    if (is_production()) {
        return true;
    }

    // For development, display error details
    if (ini_get('display_errors')) {
        displayError($errorData);
    }

    return true;
}

/**
 * Custom exception handler
 * @param Throwable $exception
 */
function customExceptionHandler($exception) {
    $errorData = [
        'type' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
        'timestamp' => date('Y-m-d H:i:s'),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];

    // Log exception
    logError($errorData);

    // For API endpoints, return JSON error response
    $isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;

    if ($isApiRequest) {
        header('Content-Type: application/json');
        http_response_code(500);

        $response = [
            'success' => false,
            'error' => 'Internal server error',
            'message' => is_production() ? 'An unexpected error occurred' : $exception->getMessage()
        ];

        if (!is_production()) {
            $response['debug_info'] = [
                'type' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ];
        }

        echo json_encode($response);
        exit;
    }

    // For web pages, display error page
    if (is_production()) {
        displayErrorPage(500, 'Internal Server Error');
    } else {
        displayError($errorData);
    }
}

/**
 * Shutdown function for fatal errors
 */
function shutdownHandler() {
    $error = error_get_last();

    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $errorData = [
            'type' => 'Fatal Error',
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
            'timestamp' => date('Y-m-d H:i:s'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        logError($errorData);

        // For production, show error page
        if (is_production()) {
            displayErrorPage(500, 'Internal Server Error');
        }
    }
}

/**
 * Log error to file and database
 * @param array $errorData
 */
function logError($errorData) {
    // Log to file
    $logFile = __DIR__ . '/logs/errors.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logMessage = sprintf(
        "[%s] %s: %s in %s on line %d\n",
        $errorData['timestamp'],
        $errorData['type'],
        $errorData['message'],
        $errorData['file'],
        $errorData['line']
    );

    // Add context if available
    if (isset($errorData['context'])) {
        $logMessage .= "Context: " . json_encode($errorData['context']) . "\n";
    }

    // Add request information
    $logMessage .= sprintf(
        "Request: %s %s | IP: %s | UA: %s\n",
        $errorData['request_method'],
        $errorData['request_uri'],
        $errorData['client_ip'],
        $errorData['user_agent']
    );

    $logMessage .= str_repeat('-', 80) . "\n";

    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

    // Log to database if available
    try {
        if (class_exists('Database')) {
            $db = Database::getInstance();

            $errorLogData = [
                'endpoint' => $errorData['request_uri'],
                'method' => $errorData['request_method'],
                'request_data' => json_encode([
                    'error_type' => $errorData['type'],
                    'error_message' => $errorData['message'],
                    'file' => $errorData['file'],
                    'line' => $errorData['line'],
                    'client_ip' => $errorData['client_ip']
                ]),
                'response_data' => json_encode([
                    'error' => true,
                    'context' => $errorData['context'] ?? null
                ]),
                'status_code' => 500,
                'response_time_ms' => 0,
                'created_at' => $errorData['timestamp']
            ];

            $db->insert('api_logs', $errorLogData);
        }
    } catch (Exception $e) {
        // If database logging fails, continue with file logging
        error_log("Failed to log error to database: " . $e->getMessage());
    }
}

/**
 * Display error for development
 * @param array $errorData
 */
function displayError($errorData) {
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/html; charset=UTF-8');
    }

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Application Error</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                max-width: 800px;
                margin: 0 auto;
                padding: 2rem;
                background-color: #f8f9fa;
                color: #333;
            }
            .error-container {
                background: white;
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                border-left: 4px solid #dc3545;
            }
            .error-type {
                color: #dc3545;
                font-weight: bold;
                font-size: 1.2rem;
                margin-bottom: 0.5rem;
            }
            .error-message {
                font-size: 1.1rem;
                margin-bottom: 1rem;
            }
            .error-details {
                background: #f8f9fa;
                padding: 1rem;
                border-radius: 4px;
                margin: 1rem 0;
                font-family: 'Courier New', monospace;
                font-size: 0.9rem;
            }
            .stack-trace {
                background: #f1f3f4;
                padding: 1rem;
                border-radius: 4px;
                margin: 1rem 0;
                font-family: 'Courier New', monospace;
                font-size: 0.85rem;
                white-space: pre-wrap;
                max-height: 300px;
                overflow-y: auto;
            }
            .request-info {
                background: #e9ecef;
                padding: 1rem;
                border-radius: 4px;
                margin: 1rem 0;
                font-size: 0.9rem;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-type"><?php echo htmlspecialchars($errorData['type']); ?></div>
            <div class="error-message"><?php echo htmlspecialchars($errorData['message']); ?></div>

            <div class="error-details">
                <strong>File:</strong> <?php echo htmlspecialchars($errorData['file']); ?><br>
                <strong>Line:</strong> <?php echo $errorData['line']; ?><br>
                <strong>Time:</strong> <?php echo $errorData['timestamp']; ?>
            </div>

            <?php if (isset($errorData['context'])): ?>
                <div class="stack-trace">
                    <strong>Stack Trace:</strong><br>
                    <?php echo htmlspecialchars(print_r($errorData['context'], true)); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($errorData['trace'])): ?>
                <div class="stack-trace">
                    <strong>Exception Trace:</strong><br>
                    <?php echo htmlspecialchars($errorData['trace']); ?>
                </div>
            <?php endif; ?>

            <div class="request-info">
                <strong>Request Information:</strong><br>
                URI: <?php echo htmlspecialchars($errorData['request_uri']); ?><br>
                Method: <?php echo htmlspecialchars($errorData['request_method']); ?><br>
                IP: <?php echo htmlspecialchars($errorData['client_ip']); ?><br>
                User Agent: <?php echo htmlspecialchars($errorData['user_agent']); ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Display error page for production
 * @param int $code
 * @param string $message
 */
function displayErrorPage($code, $message) {
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/html; charset=UTF-8');
    }

    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Terjadi Kesalahan - WiFi Voucher</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                background-color: #f8f9fa;
                color: #333;
            }
            .error-container {
                text-align: center;
                max-width: 500px;
                padding: 2rem;
            }
            .error-code {
                font-size: 4rem;
                font-weight: bold;
                color: #dc3545;
                margin-bottom: 1rem;
            }
            .error-message {
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }
            .error-description {
                color: #666;
                margin-bottom: 2rem;
                line-height: 1.6;
            }
            .btn {
                display: inline-block;
                padding: 0.75rem 1.5rem;
                background-color: #007bff;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                transition: background-color 0.2s;
            }
            .btn:hover {
                background-color: #0056b3;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-code"><?php echo $code; ?></div>
            <div class="error-message">Terjadi Kesalahan</div>
            <div class="error-description">
                Maaf, terjadi kesalahan pada sistem kami. Kami sedang bekerja untuk memperbaikinya.
                Silakan coba lagi beberapa saat.
            </div>
            <a href="/" class="btn">Kembali ke Beranda</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

?>