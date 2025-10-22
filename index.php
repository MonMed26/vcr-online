<?php
/**
 * WiFi Voucher System - Main Page
 * Frontend interface for purchasing WiFi vouchers
 */

require_once 'config.php';

// Get available packages from database
try {
    $db = Database::getInstance();
    $packages = $db->fetchAll(
        "SELECT id, name, description, price, duration_hours FROM packages WHERE is_active = 1 ORDER BY price ASC"
    );
} catch (Exception $e) {
    $packages = [];
    $error = 'Unable to load packages. Please try again later.';
}

// Set page title and meta
$pageTitle = 'WiFi Voucher - Beli Voucher Internet Mudah & Cepat';
$pageDescription = 'Beli voucher WiFi online dengan pembayaran QRIS. Proses instant, langsung aktif.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="keywords" content="WiFi, voucher, internet, QRIS, online, hotspot">
    <meta name="author" content="WiFi Voucher System">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo BASE_URL; ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Meta tag for security -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img src="assets/images/wifi-icon.svg" alt="WiFi Icon" width="40" height="40">
                    <h1>WiFi Voucher</h1>
                </div>
                <div class="header-info">
                    <span class="status-indicator online">
                        <span class="status-dot"></span>
                        System Online
                    </span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main">
        <div class="container">
            <!-- Hero Section -->
            <section class="hero">
                <div class="hero-content">
                    <h2>Internet Cepat, Pembayaran Mudah</h2>
                    <p>Beli voucher WiFi dengan pembayaran QRIS. Proses instant, langsung aktif!</p>
                    <div class="hero-features">
                        <div class="feature-item">
                            <span class="feature-icon">⚡</span>
                            <span>Instant Activation</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon">🔒</span>
                            <span>Payment Secure</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon">📱</span>
                            <span>QRIS Payment</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Package Selection Section -->
            <section class="packages-section" id="packages">
                <div class="section-header">
                    <h3>Pilih Paket WiFi</h3>
                    <p>Pilih paket yang sesuai dengan kebutuhan Anda</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <span class="alert-icon">⚠️</span>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <div class="packages-grid" id="packagesGrid">
                    <?php if (empty($packages)): ?>
                        <div class="no-packages">
                            <div class="no-packages-icon">📦</div>
                            <h4>Paket Tidak Tersedia</h4>
                            <p>Maaf, saat ini tidak ada paket yang tersedia. Silakan coba lagi nanti.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($packages as $package): ?>
                            <div class="package-card" data-package-id="<?php echo $package['id']; ?>">
                                <div class="package-header">
                                    <h4 class="package-name"><?php echo htmlspecialchars($package['name']); ?></h4>
                                    <?php if (!empty($package['description'])): ?>
                                        <p class="package-description"><?php echo htmlspecialchars($package['description']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="package-duration">
                                    <span class="duration-icon">⏰</span>
                                    <span class="duration-text"><?php echo $package['duration_hours']; ?> Jam</span>
                                </div>

                                <div class="package-price">
                                    <span class="price-currency">Rp</span>
                                    <span class="price-amount"><?php echo number_format($package['price'], 0, ',', '.'); ?></span>
                                </div>

                                <button class="btn btn-primary package-btn"
                                        data-package-id="<?php echo $package['id']; ?>"
                                        data-package-name="<?php echo htmlspecialchars($package['name']); ?>"
                                        data-package-price="<?php echo $package['price']; ?>"
                                        data-package-duration="<?php echo $package['duration_hours']; ?>">
                                    <span class="btn-text">Beli Sekarang</span>
                                    <span class="btn-loading" style="display: none;">
                                        <span class="spinner"></span>
                                        Memproses...
                                    </span>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- How It Works Section -->
            <section class="how-it-works">
                <div class="section-header">
                    <h3>Cara Pembelian</h3>
                    <p>Mudah dan cepat dalam 3 langkah</p>
                </div>

                <div class="steps-grid">
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h4>Pilih Paket</h4>
                            <p>Pilih paket WiFi yang sesuai dengan kebutuhan Anda</p>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h4>Scan QRIS</h4>
                            <p>Scan kode QRIS dengan aplikasi e-wallet Anda</p>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h4>Internet Aktif!</h4>
                            <p>Dapatkan voucher dan nikmati internet instantly</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Payment Modal -->
    <div class="modal" id="paymentModal">
        <div class="modal-overlay" id="modalOverlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Selesaikan Pembayaran</h3>
                <button class="modal-close" id="modalClose">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <!-- Package Info -->
                <div class="payment-package-info">
                    <div class="payment-package-details">
                        <h4 id="paymentPackageName">Paket 1 Hari</h4>
                        <p id="paymentPackageDuration">Durasi: 24 Jam</p>
                    </div>
                    <div class="payment-package-price">
                        <span class="price-currency">Rp</span>
                        <span id="paymentPackagePrice">5.000</span>
                    </div>
                </div>

                <!-- QR Code Section -->
                <div class="qr-section" id="qrSection" style="display: none;">
                    <div class="qr-header">
                        <h4>Scan Kode QRIS</h4>
                        <p>Gunakan aplikasi e-wallet untuk scan kode QR</p>
                    </div>

                    <div class="qr-code-container">
                        <div class="qr-placeholder" id="qrPlaceholder">
                            <div class="qr-loading">
                                <div class="spinner"></div>
                                <p>Generating QR Code...</p>
                            </div>
                        </div>
                        <img id="qrCodeImage" style="display: none;" alt="QR Code">
                    </div>

                    <div class="qr-info">
                        <div class="qr-expiry">
                            <span class="expiry-icon">⏰</span>
                            <span>Kadaluarsa: <span id="qrExpiryTime">--:--</span></span>
                        </div>
                        <div class="qr-transaction-id">
                            <span>ID Transaksi: <strong id="transactionId">------</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Payment Status Section -->
                <div class="payment-status" id="paymentStatus" style="display: none;">
                    <!-- Status will be dynamically inserted here -->
                </div>

                <!-- Voucher Section -->
                <div class="voucher-section" id="voucherSection" style="display: none;">
                    <div class="voucher-success">
                        <div class="success-icon">✅</div>
                        <h4>Pembayaran Berhasil!</h4>
                        <p>Voucher WiFi Anda telah dibuat</p>
                    </div>

                    <div class="voucher-credentials">
                        <div class="voucher-item">
                            <label>Username:</label>
                            <div class="voucher-value">
                                <input type="text" id="voucherUsername" readonly>
                                <button class="copy-btn" data-copy-target="voucherUsername">
                                    <span>📋</span>
                                </button>
                            </div>
                        </div>

                        <div class="voucher-item">
                            <label>Password:</label>
                            <div class="voucher-value">
                                <input type="password" id="voucherPassword" readonly>
                                <button class="copy-btn" data-copy-target="voucherPassword">
                                    <span>📋</span>
                                </button>
                                <button class="toggle-password-btn" id="togglePassword">
                                    <span>👁️</span>
                                </button>
                            </div>
                        </div>

                        <div class="voucher-item">
                            <label>Berlaku Sampai:</label>
                            <div class="voucher-value">
                                <span id="voucherExpiry">--</span>
                            </div>
                        </div>
                    </div>

                    <div class="voucher-actions">
                        <button class="btn btn-outline" id="newTransactionBtn">Beli Lagi</button>
                        <button class="btn btn-primary" id="downloadVoucherBtn">Download Voucher</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="loading-content">
            <div class="spinner large"></div>
            <p>Memproses permintaan...</p>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast" style="display: none;">
        <div class="toast-content">
            <span class="toast-icon" id="toastIcon">ℹ️</span>
            <span class="toast-message" id="toastMessage"></span>
        </div>
        <button class="toast-close" id="toastClose">&times;</button>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h5>WiFi Voucher System</h5>
                    <p>Sistem pembelian voucher WiFi yang mudah dan cepat dengan pembayaran QRIS.</p>
                </div>

                <div class="footer-section">
                    <h5>Bantuan</h5>
                    <ul>
                        <li><a href="#" id="howToUseLink">Cara Penggunaan</a></li>
                        <li><a href="#" id="faqLink">FAQ</a></li>
                        <li><a href="#" id="contactLink">Kontak</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h5>Metode Pembayaran</h5>
                    <div class="payment-methods">
                        <span class="payment-method">QRIS</span>
                        <span class="payment-method">E-Wallet</span>
                        <span class="payment-method">Mobile Banking</span>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> WiFi Voucher System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="assets/js/app.js"></script>
</body>
</html>