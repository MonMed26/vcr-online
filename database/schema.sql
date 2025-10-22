-- Database Schema for WiFi Voucher System
-- Created for vcr-online WiFi voucher purchase flow

CREATE DATABASE IF NOT EXISTS wifi_voucher_system;
USE wifi_voucher_system;

-- Table for storing voucher packages
CREATE TABLE packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration_hours INT NOT NULL,
    profile_name VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_price (price)
);

-- Table for storing transactions
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(50) NOT NULL UNIQUE,
    package_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'success', 'failed', 'expired') DEFAULT 'pending',
    payment_gateway_ref VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Table for storing generated vouchers
CREATE TABLE vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(50) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(100) NOT NULL,
    mikrotik_user_id VARCHAR(100),
    is_used BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE CASCADE,
    INDEX idx_transaction (transaction_id),
    INDEX idx_username (username),
    INDEX idx_expires (expires_at),
    INDEX idx_used (is_used)
);

-- Table for API logs (optional but recommended for debugging)
CREATE TABLE api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    request_data JSON,
    response_data JSON,
    status_code INT,
    response_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_endpoint (endpoint),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status_code)
);

-- Table for webhook logs
CREATE TABLE webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(50),
    gateway_type VARCHAR(50) DEFAULT 'qris',
    payload JSON,
    processed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE SET NULL,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_processed (processed),
    INDEX idx_created_at (created_at)
);

-- Insert sample packages for testing
INSERT INTO packages (name, description, price, duration_hours, profile_name) VALUES
('Paket 1 Hari', 'Akses WiFi selama 24 jam', 5000.00, 24, '1_Hari'),
('Paket 3 Hari', 'Akses WiFi selama 3 hari', 12000.00, 72, '3_Hari'),
('Paket 7 Hari', 'Akses WiFi selama 1 minggu', 25000.00, 168, '7_Hari'),
('Paket 30 Hari', 'Akses WiFi selama 1 bulan', 75000.00, 720, '30_Hari');