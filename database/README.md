# Database Setup for WiFi Voucher System

## Overview

This directory contains the database schema and setup files for the WiFi Voucher System.

## Files

- `schema.sql` - Complete database schema with tables, indexes, and sample data
- `README.md` - This file with setup instructions

## Database Tables

### 1. packages
Stores available voucher packages with pricing and duration information.

**Columns:**
- `id` - Primary key
- `name` - Package name (e.g., "Paket 1 Hari")
- `description` - Package description
- `price` - Package price in decimal format
- `duration_hours` - Access duration in hours
- `profile_name` - MikroTik profile name
- `is_active` - Whether package is available
- `created_at`, `updated_at` - Timestamps

### 2. transactions
Stores all transaction records and their payment status.

**Columns:**
- `id` - Primary key
- `transaction_id` - Unique transaction identifier (e.g., "TRX2023102201")
- `package_id` - Reference to packages table
- `amount` - Transaction amount
- `status` - Transaction status (pending, success, failed, expired)
- `payment_gateway_ref` - Payment gateway reference
- `created_at`, `updated_at` - Timestamps

### 3. vouchers
Stores generated vouchers with credentials.

**Columns:**
- `id` - Primary key
- `transaction_id` - Reference to transactions table
- `username` - Generated username
- `password` - Generated password
- `mikrotik_user_id` - MikroTik user ID
- `is_used` - Whether voucher has been used
- `expires_at` - Voucher expiration time
- `created_at` - Creation timestamp

### 4. api_logs
Logs API requests and responses for debugging.

### 5. webhook_logs
Logs incoming webhook requests from payment gateway.

## Setup Instructions

### 1. Database Creation

```bash
# MySQL/MariaDB
mysql -u root -p < database/schema.sql
```

### 2. Update Configuration

Edit `config.php` with your database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'wifi_voucher_system');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
```

### 3. Verify Setup

You can verify the database was created correctly:

```sql
USE wifi_voucher_system;
SHOW TABLES;
SELECT * FROM packages;
```

## Sample Data

The schema includes sample packages:
- Paket 1 Hari - Rp 5.000 (24 jam)
- Paket 3 Hari - Rp 12.000 (72 jam)
- Paket 7 Hari - Rp 25.000 (168 jam)
- Paket 30 Hari - Rp 75.000 (720 jam)

## Security Notes

- Use strong database passwords
- Limit database user privileges (only required permissions)
- Regularly backup the database
- Monitor the logs for suspicious activity
- Consider implementing connection pooling for production

## Performance Considerations

- Indexes are created on frequently queried columns
- Consider partitioning large tables by date
- Implement regular cleanup of old logs
- Monitor query performance and optimize as needed

## Backup and Recovery

Regular backups are recommended:

```bash
# Full backup
mysqldump -u root -p wifi_voucher_system > backup_$(date +%Y%m%d).sql

# Restore
mysql -u root -p wifi_voucher_system < backup_20231022.sql
```

## Troubleshooting

### Common Issues

1. **Connection Failed**: Check database credentials and connectivity
2. **Permission Denied**: Ensure database user has proper privileges
3. **Table Doesn't Exist**: Run the schema.sql file again
4. **Foreign Key Errors**: Ensure data integrity when manually inserting records

### Testing Connection

Create a test file `test_db.php`:

```php
<?php
require_once 'config.php';

try {
    $db = Database::getInstance();
    echo "Database connection successful!";

    // Test query
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM packages");
    echo "Found {$result['count']} packages in database.";

} catch (Exception $e) {
    echo "Database error: " . $e->getMessage();
}
?>
```