/**
 * WiFi Voucher System - Main JavaScript Application
 * Handles AJAX requests, UI interactions, and payment processing
 */

// Application State
const AppState = {
    currentTransaction: null,
    pollInterval: null,
    selectedPackage: null,
    isModalOpen: false
};

// Configuration
const Config = {
    apiBaseUrl: 'api',
    pollInterval: 3000, // 3 seconds
    maxPollAttempts: 200, // ~10 minutes
    toastTimeout: 5000,
    animationDuration: 300
};

// DOM Elements
const Elements = {
    // Modal
    paymentModal: document.getElementById('paymentModal'),
    modalOverlay: document.getElementById('modalOverlay'),
    modalClose: document.getElementById('modalClose'),

    // Payment sections
    qrSection: document.getElementById('qrSection'),
    qrPlaceholder: document.getElementById('qrPlaceholder'),
    qrCodeImage: document.getElementById('qrCodeImage'),
    qrExpiryTime: document.getElementById('qrExpiryTime'),
    transactionId: document.getElementById('transactionId'),

    // Status and voucher
    paymentStatus: document.getElementById('paymentStatus'),
    voucherSection: document.getElementById('voucherSection'),
    voucherUsername: document.getElementById('voucherUsername'),
    voucherPassword: document.getElementById('voucherPassword'),
    voucherExpiry: document.getElementById('voucherExpiry'),

    // Package info
    paymentPackageName: document.getElementById('paymentPackageName'),
    paymentPackagePrice: document.getElementById('paymentPackagePrice'),
    paymentPackageDuration: document.getElementById('paymentPackageDuration'),

    // Buttons
    newTransactionBtn: document.getElementById('newTransactionBtn'),
    downloadVoucherBtn: document.getElementById('downloadVoucherBtn'),
    togglePassword: document.getElementById('togglePassword'),

    // Loading and notifications
    loadingOverlay: document.getElementById('loadingOverlay'),
    toast: document.getElementById('toast'),
    toastMessage: document.getElementById('toastMessage'),
    toastIcon: document.getElementById('toastIcon'),
    toastClose: document.getElementById('toastClose'),

    // Package grid
    packagesGrid: document.getElementById('packagesGrid')
};

// Utility Functions
const Utils = {
    /**
     * Format currency amount
     * @param {number} amount
     * @returns {string}
     */
    formatCurrency(amount) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(amount);
    },

    /**
     * Format datetime
     * @param {string} dateString
     * @returns {string}
     */
    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('id-ID', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    /**
     * Calculate time remaining
     * @param {string} expiryDate
     * @returns {object}
     */
    getTimeRemaining(expiryDate) {
        const total = Date.parse(expiryDate) - Date.parse(new Date());
        const minutes = Math.floor((total / 1000 / 60) % 60);
        const hours = Math.floor((total / (1000 * 60 * 60)) % 24);

        return {
            total,
            hours,
            minutes
        };
    },

    /**
     * Copy text to clipboard
     * @param {string} text
     * @returns {Promise}
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (err) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            const success = document.execCommand('copy');
            document.body.removeChild(textArea);
            return success;
        }
    },

    /**
     * Debounce function
     * @param {Function} func
     * @param {number} wait
     * @returns {Function}
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// API Service
const API = {
    /**
     * Make API request
     * @param {string} endpoint
     * @param {object} options
     * @returns {Promise}
     */
    async request(endpoint, options = {}) {
        const url = `${Config.apiBaseUrl}/${endpoint}`;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };

        const finalOptions = { ...defaultOptions, ...options };

        // If there's a body, stringify it
        if (finalOptions.body && typeof finalOptions.body === 'object') {
            finalOptions.body = JSON.stringify(finalOptions.body);
        }

        try {
            const response = await fetch(url, finalOptions);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || `HTTP error! status: ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    },

    /**
     * Create transaction
     * @param {number} packageId
     * @returns {Promise}
     */
    async createTransaction(packageId) {
        return this.request('buat_transaksi.php', {
            method: 'POST',
            body: { package_id: packageId }
        });
    },

    /**
     * Check transaction status
     * @param {string} transactionId
     * @returns {Promise}
     */
    async checkStatus(transactionId) {
        return this.request(`cek_status.php?trx=${transactionId}`);
    }
};

// UI Service
const UI = {
    /**
     * Show loading overlay
     * @param {string} message
     */
    showLoading(message = 'Memproses...') {
        Elements.loadingOverlay.querySelector('p').textContent = message;
        Elements.loadingOverlay.style.display = 'flex';
    },

    /**
     * Hide loading overlay
     */
    hideLoading() {
        Elements.loadingOverlay.style.display = 'none';
    },

    /**
     * Show toast notification
     * @param {string} message
     * @param {string} type
     */
    showToast(message, type = 'info') {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };

        Elements.toastMessage.textContent = message;
        Elements.toastIcon.textContent = icons[type] || icons.info;

        // Remove existing classes
        Elements.toast.className = 'toast';
        Elements.toast.classList.add(type);

        Elements.toast.style.display = 'block';

        // Auto hide after timeout
        setTimeout(() => {
            this.hideToast();
        }, Config.toastTimeout);
    },

    /**
     * Hide toast notification
     */
    hideToast() {
        Elements.toast.style.display = 'none';
    },

    /**
     * Show modal
     */
    showModal() {
        Elements.paymentModal.classList.add('active');
        document.body.style.overflow = 'hidden';
        AppState.isModalOpen = true;
    },

    /**
     * Hide modal
     */
    hideModal() {
        Elements.paymentModal.classList.remove('active');
        document.body.style.overflow = '';
        AppState.isModalOpen = false;

        // Stop polling if active
        if (AppState.pollInterval) {
            clearInterval(AppState.pollInterval);
            AppState.pollInterval = null;
        }
    },

    /**
     * Set button loading state
     * @param {HTMLButtonElement} button
     * @param {boolean} loading
     */
    setButtonLoading(button, loading) {
        const btnText = button.querySelector('.btn-text');
        const btnLoading = button.querySelector('.btn-loading');

        if (loading) {
            button.disabled = true;
            if (btnText) btnText.style.display = 'none';
            if (btnLoading) btnLoading.style.display = 'flex';
        } else {
            button.disabled = false;
            if (btnText) btnText.style.display = 'inline';
            if (btnLoading) btnLoading.style.display = 'none';
        }
    },

    /**
     * Update QR code display
     * @param {object} paymentData
     */
    updateQRCode(paymentData) {
        const { qr_code, qr_string, qr_url, transaction_id, expiry_time } = paymentData;

        // Update transaction ID
        Elements.transactionId.textContent = transaction_id;

        // Set QR code
        if (qr_code) {
            Elements.qrCodeImage.src = qr_code;
        } else if (qr_url) {
            Elements.qrCodeImage.src = qr_url;
        } else if (qr_string) {
            // Generate QR code using external service
            Elements.qrCodeImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=256x256&data=${encodeURIComponent(qr_string)}`;
        }

        // Show QR code image
        Elements.qrCodeImage.style.display = 'block';
        Elements.qrPlaceholder.style.display = 'none';

        // Set expiry time
        if (expiry_time) {
            this.updateExpiryTime(expiry_time);
        }
    },

    /**
     * Update expiry time display
     * @param {string} expiryTime
     */
    updateExpiryTime(expiryTime) {
        const updateTime = () => {
            const remaining = Utils.getTimeRemaining(expiryTime);

            if (remaining.total <= 0) {
                Elements.qrExpiryTime.textContent = 'Kadaluarsa';
                this.showPaymentStatus('expired', 'Pembayaran telah kadaluarsa. Silakan buat transaksi baru.');
                return;
            }

            const hours = String(remaining.hours).padStart(2, '0');
            const minutes = String(remaining.minutes).padStart(2, '0');
            Elements.qrExpiryTime.textContent = `${hours}:${minutes}`;
        };

        updateTime();
        setInterval(updateTime, 1000); // Update every second
    },

    /**
     * Show payment status
     * @param {string} status
     * @param {string} message
     */
    showPaymentStatus(status, message) {
        Elements.paymentStatus.innerHTML = `
            <div class="payment-status-${status}">
                <div class="status-icon">
                    ${status === 'success' ? '✅' : status === 'failed' ? '❌' : status === 'expired' ? '⏰' : '⏳'}
                </div>
                <h4>${status === 'success' ? 'Pembayaran Berhasil!' : status === 'failed' ? 'Pembayaran Gagal' : status === 'expired' ? 'Kadaluarsa' : 'Menunggu Pembayaran'}</h4>
                <p>${message}</p>
            </div>
        `;
        Elements.paymentStatus.style.display = 'block';
    },

    /**
     * Show voucher information
     * @param {object} voucherData
     */
    showVoucher(voucherData) {
        const { username, password, expires_at } = voucherData;

        Elements.voucherUsername.value = username;
        Elements.voucherPassword.value = password;
        Elements.voucherExpiry.textContent = Utils.formatDateTime(expires_at);

        // Hide QR and status, show voucher
        Elements.qrSection.style.display = 'none';
        Elements.paymentStatus.style.display = 'none';
        Elements.voucherSection.style.display = 'block';
    },

    /**
     * Reset modal content
     */
    resetModal() {
        // Hide all sections
        Elements.qrSection.style.display = 'none';
        Elements.paymentStatus.style.display = 'none';
        Elements.voucherSection.style.display = 'none';

        // Reset QR code
        Elements.qrCodeImage.style.display = 'none';
        Elements.qrPlaceholder.style.display = 'flex';

        // Clear values
        Elements.transactionId.textContent = '------';
        Elements.qrExpiryTime.textContent = '--:--';
        Elements.voucherUsername.value = '';
        Elements.voucherPassword.value = '';
        Elements.voucherExpiry.textContent = '--';

        // Stop polling
        if (AppState.pollInterval) {
            clearInterval(AppState.pollInterval);
            AppState.pollInterval = null;
        }

        AppState.currentTransaction = null;
    }
};

// Payment Service
const Payment = {
    /**
     * Start payment process
     * @param {number} packageId
     * @param {object} packageInfo
     */
    async startPayment(packageId, packageInfo) {
        try {
            UI.showLoading('Membuat transaksi...');

            const response = await API.createTransaction(packageId);

            if (!response.success) {
                throw new Error(response.error || 'Gagal membuat transaksi');
            }

            const transactionData = response.data;
            AppState.currentTransaction = transactionData;

            // Update modal with package info
            Elements.paymentPackageName.textContent = packageInfo.name;
            Elements.paymentPackagePrice.textContent = Utils.formatCurrency(packageInfo.price).replace('Rp', '').trim();
            Elements.paymentPackageDuration.textContent = `Durasi: ${packageInfo.duration_hours} Jam`;

            // Show QR code
            UI.updateQRCode(transactionData.payment);
            Elements.qrSection.style.display = 'block';

            // Show modal
            UI.showModal();

            // Start polling for payment status
            this.startStatusPolling(transactionData.transaction_id);

            UI.showToast('Transaksi berhasil dibuat. Silakan selesaikan pembayaran.', 'success');

        } catch (error) {
            console.error('Payment start error:', error);
            UI.showToast(error.message || 'Gagal memulai pembayaran. Silakan coba lagi.', 'error');
        } finally {
            UI.hideLoading();
        }
    },

    /**
     * Start polling for payment status
     * @param {string} transactionId
     */
    startStatusPolling(transactionId) {
        let attempts = 0;

        AppState.pollInterval = setInterval(async () => {
            attempts++;

            try {
                const response = await API.checkStatus(transactionId);

                if (!response.success) {
                    console.error('Status check failed:', response.error);
                    return;
                }

                const { data, message } = response;

                if (data.status === 'success' && data.voucher) {
                    // Payment successful - show voucher
                    UI.showVoucher(data.voucher);
                    clearInterval(AppState.pollInterval);
                    AppState.pollInterval = null;
                    UI.showToast('Pembayaran berhasil! Voucher telah dibuat.', 'success');

                } else if (data.status === 'failed') {
                    // Payment failed
                    UI.showPaymentStatus('failed', message);
                    clearInterval(AppState.pollInterval);
                    AppState.pollInterval = null;

                } else if (data.status === 'expired') {
                    // Transaction expired
                    UI.showPaymentStatus('expired', message);
                    clearInterval(AppState.pollInterval);
                    AppState.pollInterval = null;

                } else if (data.status === 'pending') {
                    // Still pending - update payment status if available
                    if (data.payment_status && data.payment_status !== 'pending') {
                        UI.showPaymentStatus(data.payment_status, message);
                    }
                }

                // Stop polling after max attempts
                if (attempts >= Config.maxPollAttempts) {
                    clearInterval(AppState.pollInterval);
                    AppState.pollInterval = null;
                    UI.showPaymentStatus('timeout', 'Pemeriksaan status terlalu lama. Silakan refresh halaman atau coba lagi.');
                }

            } catch (error) {
                console.error('Status poll error:', error);
                // Continue polling on error, but don't exceed max attempts
            }
        }, Config.pollInterval);
    }
};

// Event Listeners
const EventListeners = {
    /**
     * Initialize all event listeners
     */
    init() {
        // Package card buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.package-btn')) {
                const button = e.target.closest('.package-btn');
                this.handlePackageClick(button);
            }
        });

        // Modal controls
        Elements.modalClose?.addEventListener('click', () => {
            UI.hideModal();
        });

        Elements.modalOverlay?.addEventListener('click', () => {
            UI.hideModal();
        });

        // Escape key to close modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && AppState.isModalOpen) {
                UI.hideModal();
            }
        });

        // New transaction button
        Elements.newTransactionBtn?.addEventListener('click', () => {
            UI.resetModal();
            UI.hideModal();
        });

        // Download voucher button
        Elements.downloadVoucherBtn?.addEventListener('click', () => {
            this.downloadVoucher();
        });

        // Toggle password visibility
        Elements.togglePassword?.addEventListener('click', () => {
            this.togglePasswordVisibility();
        });

        // Copy buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.copy-btn')) {
                this.handleCopyClick(e.target.closest('.copy-btn'));
            }
        });

        // Toast close button
        Elements.toastClose?.addEventListener('click', () => {
            UI.hideToast();
        });

        // Footer links (placeholder functionality)
        document.getElementById('howToUseLink')?.addEventListener('click', (e) => {
            e.preventDefault();
            UI.showToast('Panduan penggunaan akan segera tersedia.', 'info');
        });

        document.getElementById('faqLink')?.addEventListener('click', (e) => {
            e.preventDefault();
            UI.showToast('FAQ akan segera tersedia.', 'info');
        });

        document.getElementById('contactLink')?.addEventListener('click', (e) => {
            e.preventDefault();
            UI.showToast('Informasi kontak akan segera tersedia.', 'info');
        });
    },

    /**
     * Handle package card button click
     * @param {HTMLButtonElement} button
     */
    async handlePackageClick(button) {
        const packageId = parseInt(button.dataset.packageId);
        const packageName = button.dataset.packageName;
        const packagePrice = parseFloat(button.dataset.packagePrice);
        const packageDuration = parseInt(button.dataset.packageDuration);

        if (!packageId) {
            UI.showToast('Data paket tidak valid.', 'error');
            return;
        }

        const packageInfo = {
            id: packageId,
            name: packageName,
            price: packagePrice,
            duration_hours: packageDuration
        };

        // Set loading state
        UI.setButtonLoading(button, true);

        try {
            await Payment.startPayment(packageId, packageInfo);
        } catch (error) {
            console.error('Package click error:', error);
        } finally {
            UI.setButtonLoading(button, false);
        }
    },

    /**
     * Handle copy button click
     * @param {HTMLButtonElement} button
     */
    async handleCopyClick(button) {
        const targetId = button.dataset.copyTarget;
        const targetElement = document.getElementById(targetId);

        if (!targetElement) {
            UI.showToast('Gagal menyalin data.', 'error');
            return;
        }

        const text = targetElement.value || targetElement.textContent;

        try {
            const success = await Utils.copyToClipboard(text);

            if (success) {
                UI.showToast('Data berhasil disalin!', 'success');

                // Show visual feedback
                const originalHTML = button.innerHTML;
                button.innerHTML = '<span>✅</span>';
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                }, 2000);
            } else {
                throw new Error('Copy failed');
            }
        } catch (error) {
            UI.showToast('Gagal menyalin data. Silakan salin manual.', 'error');
        }
    },

    /**
     * Toggle password visibility
     */
    togglePasswordVisibility() {
        const passwordField = Elements.voucherPassword;
        const button = Elements.togglePassword;

        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            button.innerHTML = '<span>🙈</span>';
            button.title = 'Sembunyikan password';
        } else {
            passwordField.type = 'password';
            button.innerHTML = '<span>👁️</span>';
            button.title = 'Tampilkan password';
        }
    },

    /**
     * Download voucher as text file
     */
    downloadVoucher() {
        const username = Elements.voucherUsername.value;
        const password = Elements.voucherPassword.value;
        const expiry = Elements.voucherExpiry.textContent;
        const packageName = Elements.paymentPackageName.textContent;

        const voucherContent = `
VOUCHER WIFI
==================

Paket: ${packageName}
Username: ${username}
Password: ${password}
Berlaku Sampai: ${expiry}

==================
Generated: ${new Date().toLocaleString('id-ID')}
        `.trim();

        const blob = new Blob([voucherContent], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = url;
        a.download = `voucher-wifi-${username}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        UI.showToast('Voucher berhasil diunduh!', 'success');
    }
};

// Application Controller
const App = {
    /**
     * Initialize application
     */
    init() {
        console.log('WiFi Voucher System initialized');

        // Check if required elements exist
        if (!Elements.paymentModal) {
            console.error('Required modal elements not found');
            return;
        }

        // Initialize event listeners
        EventListeners.init();

        // Add fade-in animation to package cards
        const packageCards = document.querySelectorAll('.package-card');
        packageCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';

            setTimeout(() => {
                card.style.transition = 'all 0.5s ease-out';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Check URL parameters for auto-open transaction
        this.checkUrlParams();

        // Setup service worker for offline functionality (if supported)
        this.setupServiceWorker();
    },

    /**
     * Check URL parameters for auto-open functionality
     */
    checkUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const transactionId = urlParams.get('trx');

        if (transactionId) {
            // Auto-open modal with existing transaction
            UI.showModal();
            Payment.startStatusPolling(transactionId);
        }
    },

    /**
     * Setup service worker for offline functionality
     */
    setupServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(() => {
                    console.log('Service Worker registered');
                })
                .catch((error) => {
                    console.log('Service Worker registration failed:', error);
                });
        }
    }
};

// Initialize application when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        App.init();
    });
} else {
    App.init();
}

// Export for global access (for debugging)
window.WiFiVoucherApp = {
    App,
    API,
    UI,
    Payment,
    Utils,
    AppState,
    Config
};