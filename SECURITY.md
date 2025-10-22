# Security Guidelines for WiFi Voucher System

This document outlines the security measures and best practices implemented in the WiFi Voucher System.

## Security Overview

The system implements multiple layers of security to protect against common web vulnerabilities and ensure safe handling of user data and payment processing.

## Implemented Security Measures

### 1. Input Validation and Sanitization

- **Database Queries**: All database queries use prepared statements with parameterized queries to prevent SQL injection
- **User Input**: All user inputs are validated using the `Validator` class before processing
- **XSS Prevention**: Output is properly escaped using `htmlspecialchars()` with ENT_QUOTES flag
- **File Upload**: No file upload functionality is exposed to users

### 2. Authentication and Authorization

- **API Rate Limiting**: IP-based rate limiting prevents abuse of API endpoints
- **Session Management**: Secure session configuration with HTTP-only cookies
- **Access Control**: API endpoints have proper access controls and validation

### 3. Payment Security

- **Webhook Signature Verification**: All incoming webhooks from the payment gateway are verified using HMAC-SHA256
- **Transaction Integrity**: Payment amounts are validated against package prices before processing
- **Secure Data Handling**: Sensitive payment data is never stored permanently

### 4. Data Protection

- **Password Security**: Generated passwords use cryptographically secure random functions
- **Data Encryption**: Configuration contains encryption keys for sensitive data
- **Database Security**: Database user should have limited privileges (only required operations)

### 5. Network Security

- **HTTPS Required**: All production deployments should use HTTPS
- **Security Headers**: Proper security headers are set including CSP, HSTS, X-Frame-Options
- **CORS Configuration**: Cross-origin requests are properly configured

## Configuration Security

### Database Security

```php
// Use a dedicated database user with limited privileges
define('DB_USER', 'wifi_voucher_user'); // Not root
define('DB_PASS', 'strong_password_here'); // Use strong password
```

### API Keys and Secrets

```php
// Store secrets in environment variables, not in code
define('MIKROTIK_PASSWORD', getenv('MIKROTIK_PASSWORD'));
define('QrisGateway', [
    'api_key' => getenv('QRIS_API_KEY'),
    'webhook_secret' => getenv('WEBHOOK_SECRET')
]);
```

### Encryption Keys

```php
// Use strong, randomly generated keys
define('JWT_SECRET', 'your_256_character_random_string_here');
define('ENCRYPTION_KEY', 'your_32_character_encryption_key');
```

## Security Headers

The following security headers are automatically set:

```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (is_production()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
```

## Rate Limiting

API endpoints are protected by rate limiting:

- **Default**: 10 requests per minute per IP address
- **Configurable**: Adjust `RATE_LIMIT_REQUESTS` and `RATE_LIMIT_WINDOW` in config.php
- **Implementation**: Uses APCu for fast, memory-based rate limiting

## Input Validation Rules

### Transaction ID Validation

```php
public static function transactionId($transactionId) {
    return preg_match('/^[A-Z0-9]{8,20}$/', $transactionId);
}
```

### Package Validation

- Must be a positive integer
- Must exist in database and be active
- Price is validated against database value

### Webhook Validation

```php
public static function validateWebhookSignature($payload, $signature) {
    $expectedSignature = hash_hmac('sha256', json_encode($payload), QrisGateway['webhook_secret']);
    return hash_equals($expectedSignature, $signature);
}
```

## Error Handling Security

- **Production Mode**: Error details are hidden in production
- **Error Logging**: All errors are logged with full details for debugging
- **Graceful Degradation**: System continues to function even when components fail

## Logging and Monitoring

### Security Events Logged

- Failed login attempts
- Invalid webhook signatures
- Rate limit violations
- Database connection failures
- API errors and exceptions

### Log Security

- Log files are stored outside the web root
- Log rotation prevents log files from growing too large
- Sensitive information is masked in logs

## Database Security

### Recommended Database User Privileges

```sql
-- Create limited user for the application
CREATE USER 'wifi_voucher_user'@'localhost' IDENTIFIED BY 'strong_password';

-- Grant only necessary privileges
GRANT SELECT, INSERT, UPDATE ON wifi_voucher_system.* TO 'wifi_voucher_user'@'localhost';

-- NO DROP, CREATE, ALTER, INDEX, or REFERENCES privileges
```

### Database Connection Security

- Connections use SSL when available
- Connection timeouts are set to prevent hanging
- Prepared statements prevent SQL injection

## MikroTik Security

### API Security

```php
// Use dedicated API user with limited permissions
define('MIKROTIK_USERNAME', 'api_user'); // Not admin
define('MIKROTIK_PASSWORD', 'strong_mikrotik_password');
```

### Recommended MikroTik Configuration

```
# Create API user with limited rights
/user add name=api_user group=full password=strong_password

# Restrict API access to specific IPs
/ip service set www address=192.168.1.0/24

# Use firewall rules to restrict API access
/ip firewall filter add chain=input protocol=tcp dst-port=8728 src-address=192.168.1.0/24 action=accept
```

## Payment Gateway Security

### Webhook Security

- All webhooks must include a valid signature
- Signature verification prevents spoofed webhooks
- Transaction amounts are validated before processing

### Data Handling

- No credit card data is stored
- Payment tokens are handled securely
- Transaction IDs are non-sequential and random

## File System Security

### Directory Permissions

```
# Web root - readable by web server
/var/www/html/          755
/api/                   755
/assets/                755

# Sensitive directories - not web accessible
/database/              700
/logs/                  700
/config.php             600
```

### .htaccess Protection

```apache
# Prevent access to sensitive files
<Files "config.php">
    Require all denied
</Files>

<Files "*.log">
    Require all denied
</Files>

# Prevent directory listing
Options -Indexes

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>
```

## Monitoring and Alerting

### Security Metrics to Monitor

- Failed authentication attempts
- Unusual API usage patterns
- Webhook signature failures
- Database connection errors
- High error rates

### Recommended Monitoring Tools

- **Log Monitoring**: Use tools like ELK Stack or Splunk
- **Performance Monitoring**: New Relic, DataDog, or similar
- **Security Scanning**: Regular vulnerability scanning
- **Database Monitoring**: Query performance and connection pooling

## Deployment Security

### Production Deployment Checklist

- [ ] Change all default passwords
- [ ] Set up HTTPS with valid SSL certificate
- [ ] Configure proper file permissions
- [ ] Set up database with limited privileges
- [ ] Configure rate limiting
- [ ] Set up monitoring and alerting
- [ ] Enable log rotation
- [ ] Test webhook signature verification
- [ ] Verify security headers are present
- [ ] Run security scan on the application

### Environment Variables

Use environment variables for sensitive configuration:

```bash
# Database
export DB_HOST=localhost
export DB_NAME=wifi_voucher_system
export DB_USER=wifi_voucher_user
export DB_PASS=strong_password_here

# MikroTik
export MIKROTIK_HOST=192.168.1.1
export MIKROTIK_USERNAME=api_user
export MIKROTIK_PASSWORD=strong_mikrotik_password

# Payment Gateway
export QRIS_API_KEY=your_api_key_here
export WEBHOOK_SECRET=your_webhook_secret_here

# Security
export JWT_SECRET=your_256_character_random_string
export ENCRYPTION_KEY=your_32_character_key
```

## Security Testing

### Automated Security Testing

Run the built-in security tests:

```bash
php integration_test.php
```

### Manual Security Testing

Use the manual workflow test to verify security:

```bash
php manual_workflow_test.php
```

### Security Checklist

- [ ] SQL injection protection verified
- [ ] XSS prevention working
- [ ] CSRF protection implemented
- [ ] File upload security
- [ ] Authentication and authorization
- [ ] Session security
- [ ] Rate limiting functional
- [ ] Webhook signature verification
- [ ] Error handling doesn't leak information
- [ ] Security headers present

## Incident Response

### Security Incident Response Plan

1. **Detection**: Monitor logs and alerts for suspicious activity
2. **Assessment**: Determine the scope and impact of the incident
3. **Containment**: Isolate affected systems if necessary
4. **Investigation**: Analyze logs and evidence
5. **Recovery**: Restore systems and data from backups
6. **Prevention**: Implement measures to prevent recurrence

### Emergency Contacts

- Security Team: security@company.com
- System Administrator: admin@company.com
- Development Team: dev@company.com

## Compliance

This system is designed to comply with:

- **OWASP Top 10**: Protection against common web vulnerabilities
- **GDPR**: Data protection and privacy principles
- **PCI DSS**: Payment Card Industry standards (if applicable)
- **Local Regulations**: Indonesian data protection laws

## Regular Security Maintenance

### Monthly Tasks

- Review and rotate API keys and secrets
- Check for security updates in dependencies
- Review security logs for suspicious activity
- Update security rules and configurations

### Quarterly Tasks

- Conduct security audit
- Perform penetration testing
- Review and update security documentation
- Train development team on security best practices

### Annual Tasks

- Complete security assessment
- Update security architecture as needed
- Review compliance requirements
- Update incident response plan

---

**Last Updated**: October 2024
**Version**: 1.0

For security issues or concerns, please contact the security team at security@company.com.