<?php
/**
 * Maintenance Mode Page
 * Displayed when the system is under maintenance
 */

// Set maintenance mode status
$maintenanceActive = false; // Set to true to enable maintenance mode

if ($maintenanceActive) {
    // Allow access for administrators (you can customize this)
    $allowedIPs = ['127.0.0.1', '::1']; // Add admin IPs here
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!in_array($clientIP, $allowedIPs)) {
        // Show maintenance page
        http_response_code(503);
        header('Retry-After: 3600'); // Suggest retry after 1 hour
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Sistem Dalam Perbaikan - WiFi Voucher</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                }
                .maintenance-container {
                    text-align: center;
                    max-width: 600px;
                    padding: 2rem;
                }
                .maintenance-icon {
                    font-size: 4rem;
                    margin-bottom: 1rem;
                    animation: pulse 2s infinite;
                }
                @keyframes pulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.1); }
                }
                .maintenance-title {
                    font-size: 2rem;
                    margin-bottom: 1rem;
                }
                .maintenance-message {
                    font-size: 1.1rem;
                    line-height: 1.6;
                    margin-bottom: 2rem;
                    opacity: 0.9;
                }
                .progress-bar {
                    width: 100%;
                    height: 4px;
                    background-color: rgba(255,255,255,0.3);
                    border-radius: 2px;
                    overflow: hidden;
                    margin: 2rem 0;
                }
                .progress-fill {
                    height: 100%;
                    background-color: #4CAF50;
                    animation: progress 2s ease-in-out infinite;
                }
                @keyframes progress {
                    0% { width: 0%; left: 0; }
                    50% { width: 70%; }
                    100% { width: 100%; left: 0; }
                }
                .contact-info {
                    font-size: 0.9rem;
                    opacity: 0.8;
                }
                .contact-info a {
                    color: #4CAF50;
                    text-decoration: none;
                }
            </style>
        </head>
        <body>
            <div class="maintenance-container">
                <div class="maintenance-icon">🔧</div>
                <h1 class="maintenance-title">Sistem Dalam Perbaikan</h1>
                <p class="maintenance-message">
                    Kami sedang melakukan pemeliharaan sistem untuk memberikan layanan yang lebih baik.<br>
                    Sistem akan kembali正常 dalam waktu singkat. Mohon kesabaran Anda.
                </p>

                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>

                <p class="maintenance-message">
                    Estimasi waktu: 1-2 jam
                </p>

                <div class="contact-info">
                    <p>Untuk informasi lebih lanjut, hubungi:</p>
                    <p>Email: support@wifivoucher.com<br>
                    Telpon: (021) 1234-5678</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

?>