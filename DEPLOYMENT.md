# WiFi Voucher System - Deployment Guide

This guide provides step-by-step instructions for deploying the WiFi Voucher System in a production environment.

## System Requirements

### Server Requirements

- **Operating System**: Ubuntu 20.04+ / CentOS 8+ / RHEL 8+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: 8.0+ with required extensions
- **Database**: MySQL 8.0+ or MariaDB 10.5+
- **Memory**: Minimum 2GB RAM
- **Storage**: Minimum 20GB disk space
- **Network**: Stable internet connection for payment gateway and MikroTik access

### PHP Extensions Required

```bash
php-mysqli
php-curl
php-json
php-mbstring
php-xml
php-intl
php-apcu (for rate limiting)
```

## Prerequisites

### 1. MikroTik RouterOS Configuration

Ensure your MikroTik device is configured for hotspot:

```
# Enable Hotspot service
/ip hotspot enable

# Create user profiles
/ip hotspot user profile add name="1_Hari" session-timeout=1d
/ip hotspot user profile add name="3_Hari" session-timeout=3d
/ip hotspot user profile add name="7_Hari" session-timeout=7d
/ip hotspot user profile add name="30_Hari" session-timeout=30d

# Enable API service
/ip service enable api
/ip service set api address=192.168.1.0/24 port=8728
```

### 2. Payment Gateway Setup

- Register with your QRIS payment gateway provider
- Obtain API credentials (API key, Merchant ID, Webhook Secret)
- Configure webhook URL: `https://yourdomain.com/api/webhook_qris.php`
- Test API connectivity

## Installation Steps

### 1. Server Setup

#### Update System Packages

```bash
# Ubuntu/Debian
sudo apt update && sudo apt upgrade -y

# CentOS/RHEL
sudo yum update -y
```

#### Install Web Server

```bash
# Apache (Ubuntu/Debian)
sudo apt install apache2 -y

# Nginx (Ubuntu/Debian)
sudo apt install nginx -y

# Apache (CentOS/RHEL)
sudo yum install httpd -y

# Nginx (CentOS/RHEL)
sudo yum install nginx -y
```

#### Install PHP and Extensions

```bash
# Ubuntu/Debian
sudo apt install php8.1 php8.1-mysqli php8.1-curl php8.1-json php8.1-mbstring php8.1-xml php8.1-intl php8.1-apcu -y

# CentOS/RHEL (with EPEL)
sudo yum install php81 php81-mysqli php81-curl php81-json php81-mbstring php81-xml php81-intl php81-pecl-apcu -y
```

#### Install Database

```bash
# MySQL (Ubuntu/Debian)
sudo apt install mysql-server -y
sudo mysql_secure_installation

# MariaDB (Ubuntu/Debian)
sudo apt install mariadb-server mariadb-client -y
sudo mysql_secure_installation

# CentOS/RHEL
sudo yum install mysql-server -y
sudo systemctl enable --now mysqld
sudo mysql_secure_installation
```

### 2. Database Setup

#### Create Database and User

```sql
-- Log in to MySQL as root
mysql -u root -p

-- Create database
CREATE DATABASE wifi_voucher_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create dedicated user
CREATE USER 'wifi_voucher_user'@'localhost' IDENTIFIED BY 'StrongPassword123!';

-- Grant privileges
GRANT SELECT, INSERT, UPDATE, DELETE ON wifi_voucher_system.* TO 'wifi_voucher_user'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;

-- Exit MySQL
EXIT;
```

#### Import Database Schema

```bash
# Import the schema
mysql -u wifi_voucher_user -p wifi_voucher_system < database/schema.sql

# Verify tables were created
mysql -u wifi_voucher_user -p -e "USE wifi_voucher_system; SHOW TABLES;"
```

### 3. Application Deployment

#### Clone or Upload Files

```bash
# Option 1: Git Clone (if using Git)
cd /var/www/html
sudo git clone https://github.com/yourusername/wifi-voucher-system.git .
sudo chown -R www-data:www-data /var/www/html

# Option 2: Upload files
# Upload all files to /var/www/html/
sudo chown -R www-data:www-data /var/www/html
```

#### Set File Permissions

```bash
# Set appropriate permissions
sudo find /var/www/html -type d -exec chmod 755 {} \;
sudo find /var/www/html -type f -exec chmod 644 {} \;

# Special permissions for sensitive files
sudo chmod 600 /var/www/html/config.php
sudo chmod 600 /var/www/html/.env*

# Create logs directory
sudo mkdir -p /var/www/html/logs
sudo chown www-data:www-data /var/www/html/logs
sudo chmod 750 /var/www/html/logs
```

### 4. Configuration

#### Update Configuration File

Edit `config.php` with your production settings:

```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'wifi_voucher_system');
define('DB_USER', 'wifi_voucher_user');
define('DB_PASS', 'StrongPassword123!'); // Your database password

// MikroTik Configuration
define('MIKROTIK_HOST', '192.168.1.1'); // Your MikroTik IP
define('MIKROTIK_PORT', 8728);
define('MIKROTIK_USERNAME', 'api_user'); // Your MikroTik API user
define('MIKROTIK_PASSWORD', 'MikroTikPassword123!'); // Your MikroTik password

// QRIS Payment Gateway Configuration
define('QrisGateway', [
    'api_url' => 'https://your-payment-gateway.com/api',
    'api_key' => 'your_production_api_key',
    'merchant_id' => 'your_merchant_id',
    'webhook_secret' => 'your_webhook_secret_key',
    'timeout' => 30,
    'expiry_minutes' => 30
]);

// Application Settings
define('APP_ENV', 'production'); // Set to 'production' for live deployment
define('BASE_URL', 'https://yourdomain.com'); // Your domain
define('JWT_SECRET', 'your_256_character_random_string');
define('ENCRYPTION_KEY', 'your_32_character_encryption_key');
?>
```

#### Environment Variables (Optional but Recommended)

Create `.env` file in project root:

```bash
# Database Configuration
DB_HOST=localhost
DB_NAME=wifi_voucher_system
DB_USER=wifi_voucher_user
DB_PASS=StrongPassword123!

# MikroTik Configuration
MIKROTIK_HOST=192.168.1.1
MIKROTIK_USERNAME=api_user
MIKROTIK_PASSWORD=MikroTikPassword123!

# Payment Gateway
QRIS_API_KEY=your_production_api_key
QRIS_MERCHANT_ID=your_merchant_id
WEBHOOK_SECRET=your_webhook_secret_key

# Security
JWT_SECRET=your_256_character_random_string
ENCRYPTION_KEY=your_32_character_encryption_key
```

### 5. Web Server Configuration

#### Apache Configuration

Create virtual host file:

```bash
sudo nano /etc/apache2/sites-available/wifi-voucher.conf
```

Content:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/html
    DirectoryIndex index.php index.html

    # Security
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Protect sensitive files
    <Files config.php>
        Require all denied
    </Files>
    <Files "*.log">
        Require all denied
    </Files>
    <Directory "/var/www/html/logs">
        Require all denied
    </Directory>

    # Error and access logs
    ErrorLog ${APACHE_LOG_DIR}/wifi-voucher-error.log
    CustomLog ${APACHE_LOG_DIR}/wifi-voucher-access.log combined
</VirtualHost>
```

Enable site and modules:

```bash
sudo a2ensite wifi-voucher.conf
sudo a2enmod rewrite
sudo a2enmod headers
sudo systemctl reload apache2
```

#### Nginx Configuration

Create server block:

```bash
sudo nano /etc/nginx/sites-available/wifi-voucher
```

Content:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/html;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    # Protect sensitive files
    location ~ /(config\.php|\.env|logs/) {
        deny all;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Static files
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Logs
    access_log /var/log/nginx/wifi-voucher-access.log;
    error_log /var/log/nginx/wifi-voucher-error.log;
}
```

Enable site:

```bash
sudo ln -s /etc/nginx/sites-available/wifi-voucher /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 6. SSL Certificate Setup

#### Let's Encrypt (Recommended)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache -y  # For Apache
sudo apt install certbot python3-certbot-nginx -y    # For Nginx

# Obtain and install certificate
sudo certbot --apache -d yourdomain.com    # For Apache
sudo certbot --nginx -d yourdomain.com     # For Nginx

# Set up auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### 7. PHP Configuration

Edit PHP configuration:

```bash
# For Apache with mod_php
sudo nano /etc/php/8.1/apache2/php.ini

# For Nginx with PHP-FPM
sudo nano /etc/php/8.1/fpm/php.ini
```

Key settings:

```ini
; Production settings
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
max_execution_time = 30
memory_limit = 128M
post_max_size = 10M
upload_max_filesize = 10M

; Session security
session.cookie_secure = 1
session.cookie_httponly = 1
session.use_strict_mode = 1
session.cookie_samesite = Strict

; OPcache (performance)
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
opcache.revalidate_freq=2
```

Restart PHP service:

```bash
# Apache
sudo systemctl restart apache2

# Nginx with PHP-FPM
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx
```

### 8. Firewall Configuration

#### UFW (Ubuntu)

```bash
# Allow essential ports
sudo ufw allow ssh
sudo ufw allow 'Apache Full'  # or 'Nginx Full'
sudo ufw enable

# If MikroTik is on different server, allow API access
sudo ufw allow from 192.168.1.0/24 to any port 8728
```

#### firewalld (CentOS/RHEL)

```bash
# Allow essential ports
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

## Post-Deployment Testing

### 1. Basic Functionality Test

```bash
# Test database connection
php -r "require_once 'config.php'; \$db = Database::getInstance(); echo 'Database OK\n';"

# Test MikroTik connection
php test_apis.php

# Run integration tests
php integration_test.php
```

### 2. Manual Workflow Test

Access your deployment in a web browser:

```bash
# Open in browser
https://yourdomain.com/manual_workflow_test.php
```

### 3. API Testing

Test API endpoints:

```bash
# Create transaction
curl -X POST https://yourdomain.com/api/buat_transaksi.php \
  -H "Content-Type: application/json" \
  -d '{"package_id":1}'

# Check status (replace with actual transaction ID)
curl "https://yourdomain.com/api/cek_status.php?trx=TRX12345678"
```

## Monitoring and Maintenance

### 1. Log Monitoring

Monitor these log files:

```bash
# Application logs
tail -f /var/www/html/logs/app.log

# Web server logs
tail -f /var/log/apache2/wifi-voucher-error.log    # Apache
tail -f /var/log/nginx/wifi-voucher-error.log      # Nginx

# PHP logs
tail -f /var/log/php_errors.log

# System logs
sudo journalctl -f -u apache2    # Apache
sudo journalctl -f -u nginx      # Nginx
```

### 2. Database Maintenance

```bash
# Create backup script
sudo nano /usr/local/bin/backup-wifi-voucher.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/wifi-voucher"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="wifi_voucher_system"

mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u wifi_voucher_user -p'Password123!' $DB_NAME | gzip > $BACKUP_DIR/db_backup_$DATE.sql.gz

# File backup
tar -czf $BACKUP_DIR/files_backup_$DATE.tar.gz /var/www/html

# Clean old backups (keep last 7 days)
find $BACKUP_DIR -name "*.gz" -mtime +7 -delete
```

```bash
sudo chmod +x /usr/local/bin/backup-wifi-voucher.sh

# Set up cron job for daily backups
sudo crontab -e
# Add: 0 2 * * * /usr/local/bin/backup-wifi-voucher.sh
```

### 3. Performance Monitoring

Install monitoring tools:

```bash
# Install htop for system monitoring
sudo apt install htop -y

# Install netdata for comprehensive monitoring
bash <(curl -Ss https://my-netdata.io/kickstart.sh)
```

## Security Hardening

### 1. Security Updates

```bash
# Set up automatic security updates
sudo apt install unattended-upgrades -y
sudo dpkg-reconfigure unattended-upgrades
```

### 2. Fail2ban Setup

```bash
sudo apt install fail2ban -y
sudo systemctl enable fail2ban

# Create custom configuration
sudo nano /etc/fail2ban/jail.local
```

```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 3

[apache-auth]
enabled = true
port = http,https
filter = apache-auth
logpath = /var/log/apache2/error.log

[nginx-http-auth]
enabled = true
port = http,https
filter = nginx-http-auth
logpath = /var/log/nginx/error.log
```

```bash
sudo systemctl restart fail2ban
```

## Troubleshooting

### Common Issues

#### 1. Database Connection Failed

```bash
# Check database status
sudo systemctl status mysql

# Check credentials
mysql -u wifi_voucher_user -p -e "SELECT 1;"

# Check PHP database extension
php -m | grep mysqli
```

#### 2. MikroTik Connection Failed

```bash
# Test network connectivity
telnet 192.168.1.1 8728

# Check MikroTik API service
ssh admin@192.168.1.1 "/ip service print where name=api"
```

#### 3. Payment Gateway Issues

```bash
# Test API connectivity
curl -X POST https://payment-gateway.com/api/test \
  -H "Authorization: Bearer your_api_key"

# Check webhook URL
curl -X POST https://yourdomain.com/api/webhook_qris.php \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: test" \
  -d '{"test": "data"}'
```

#### 4. File Permissions Issues

```bash
# Check web server user
ps aux | grep -E "(apache|nginx|www-data)" | head -1

# Reset permissions
sudo chown -R www-data:www-data /var/www/html
sudo find /var/www/html -type d -exec chmod 755 {} \;
sudo find /var/www/html -type f -exec chmod 644 {} \;
sudo chmod 600 /var/www/html/config.php
```

## Performance Optimization

### 1. Database Optimization

```sql
-- Add indexes for better performance
CREATE INDEX idx_transactions_status_created ON transactions(status, created_at);
CREATE INDEX idx_vouchers_expires_at ON vouchers(expires_at);
CREATE INDEX idx_api_logs_created_at ON api_logs(created_at);
```

### 2. Caching Setup

```bash
# Install and configure Redis (optional)
sudo apt install redis-server -y
sudo systemctl enable redis-server
```

### 3. CDN Configuration

Consider using a CDN for static assets:

```html
<!-- Update base URL for assets in index.php -->
<link rel="stylesheet" href="https://cdn.yourdomain.com/assets/css/style.css">
```

## Scaling Considerations

### 1. Load Balancing

For high traffic deployments:

- Use multiple web servers behind a load balancer
- Implement session affinity (sticky sessions)
- Use shared storage for logs and uploads

### 2. Database Scaling

- Read replicas for read-heavy operations
- Database connection pooling
- Regular query optimization

### 3. Monitoring and Alerting

- Set up monitoring dashboards
- Configure alerts for high error rates
- Monitor payment gateway response times

## Support

For deployment issues:

1. Check the troubleshooting section
2. Review the error logs
3. Run the integration tests
4. Contact the development team with detailed error information

---

**Version**: 1.0
**Last Updated**: October 2024

For additional support, please refer to the SECURITY.md file or contact the development team.