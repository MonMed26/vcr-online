# WiFi Voucher System API Documentation

## Overview

The API provides three main endpoints for handling WiFi voucher purchases:

1. **Create Transaction** - Initiates a new transaction and generates QRIS payment code
2. **Check Status** - Checks payment status and retrieves voucher information
3. **Webhook Handler** - Processes payment notifications from the QRIS gateway

## Base URL

```
https://yourdomain.com/api
```

## Authentication

Currently, the API uses rate limiting based on IP addresses instead of authentication tokens. Each endpoint has CORS headers enabled for cross-origin requests.

## Endpoints

### 1. Create Transaction

**Endpoint:** `POST /api/buat_transaksi.php`

Creates a new transaction and generates a QRIS payment code.

#### Request Body

```json
{
  "package_id": 1
}
```

#### Response (Success - 201 Created)

```json
{
  "success": true,
  "data": {
    "transaction_id": "TRX20231022001",
    "package": {
      "id": 1,
      "name": "Paket 1 Hari",
      "price": 5000.00,
      "duration_hours": 24
    },
    "payment": {
      "charge_id": "CHG123456789",
      "amount": 5000.00,
      "qr_code": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
      "qr_string": "00020101021226570011ID.WEB.QRIS...",
      "qr_url": "https://api.qrserver.com/v1/create-qr-code/?size=256x256&data=...",
      "expiry_time": "2023-10-22 23:59:59"
    },
    "status": "pending",
    "created_at": "2023-10-22 15:30:00"
  },
  "message": "Transaction created successfully. Please complete the payment."
}
```

#### Response (Error - 400 Bad Request)

```json
{
  "success": false,
  "error": "Validation failed",
  "errors": {
    "package_id": "Field package_id is required"
  }
}
```

#### Response (Error - 404 Not Found)

```json
{
  "success": false,
  "error": "Package not found",
  "message": "The selected package is not available"
}
```

### 2. Check Status

**Endpoint:** `GET /api/cek_status.php?trx={transaction_id}`

Checks the payment status and retrieves voucher information if payment is successful.

#### Parameters

- `trx` (string, required): Transaction ID

#### Response (Payment Pending)

```json
{
  "success": true,
  "data": {
    "transaction_id": "TRX20231022001",
    "status": "pending",
    "package": {
      "id": 1,
      "name": "Paket 1 Hari",
      "duration_hours": 24,
      "price": 5000.00
    },
    "payment_status": "pending",
    "created_at": "2023-10-22 15:30:00",
    "updated_at": "2023-10-22 15:30:00"
  },
  "message": "Payment is still being processed. Please wait..."
}
```

#### Response (Payment Successful)

```json
{
  "success": true,
  "data": {
    "transaction_id": "TRX20231022001",
    "status": "success",
    "package": {
      "id": 1,
      "name": "Paket 1 Hari",
      "duration_hours": 24,
      "price": 5000.00
    },
    "voucher": {
      "username": "user123456",
      "password": "AbCdEf78",
      "expires_at": "2023-10-23 15:30:00",
      "is_used": false
    },
    "created_at": "2023-10-22 15:30:00",
    "updated_at": "2023-10-22 15:35:00"
  },
  "message": "Your voucher is ready to use!"
}
```

#### Response (Transaction Expired)

```json
{
  "success": true,
  "data": {
    "transaction_id": "TRX20231022001",
    "status": "expired",
    "package": {
      "id": 1,
      "name": "Paket 1 Hari",
      "duration_hours": 24,
      "price": 5000.00
    },
    "created_at": "2023-10-22 15:30:00",
    "updated_at": "2023-10-22 16:00:00"
  },
  "message": "Transaction has expired. Please create a new transaction."
}
```

### 3. Webhook Handler

**Endpoint:** `POST /api/webhook_qris.php`

Processes payment notifications from the QRIS payment gateway. This endpoint is called server-to-server by the payment gateway.

#### Headers

- `X-Webhook-Signature`: HMAC-SHA256 signature of the payload

#### Request Body

```json
{
  "transaction_id": "TRX20231022001",
  "status": "success",
  "amount": 5000.00,
  "charge_id": "CHG123456789",
  "payment_time": "2023-10-22 15:35:00",
  "payment_method": "QRIS"
}
```

#### Response (Success)

```json
{
  "success": true,
  "message": "Payment processed and voucher created successfully",
  "data": {
    "transaction_id": "TRX20231022001",
    "status": "success",
    "voucher_created": true,
    "username": "user123456"
  }
}
```

#### Response (Error - Invalid Signature)

```json
{
  "success": false,
  "error": "Invalid signature",
  "message": "Webhook signature verification failed"
}
```

## Error Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created (for transaction creation) |
| 400 | Bad Request (validation error) |
| 401 | Unauthorized (invalid webhook signature) |
| 404 | Not Found (transaction/package not found) |
| 405 | Method Not Allowed |
| 429 | Too Many Requests (rate limit exceeded) |
| 500 | Internal Server Error |

## Rate Limiting

- **Requests per minute**: 10 per IP address
- **Window**: 60 seconds
- **Headers**: Rate limit info is included in response headers

## Security Features

1. **Input Validation**: All inputs are validated and sanitized
2. **SQL Injection Prevention**: Using prepared statements
3. **Webhook Signature Verification**: HMAC-SHA256 signature validation
4. **Rate Limiting**: IP-based rate limiting
5. **CORS Configuration**: Proper CORS headers for cross-origin requests
6. **Security Headers**: X-Content-Type-Options, X-Frame-Options, etc.

## Transaction Flow

1. **Client** sends POST to `/api/buat_transaksi.php` with `package_id`
2. **Server** creates transaction record and calls payment gateway
3. **Payment Gateway** returns QR code data
4. **Server** returns QR code to client
5. **Client** displays QR code to user for scanning
6. **Client** polls `/api/cek_status.php?trx={transaction_id}` every 3-5 seconds
7. **Payment Gateway** sends webhook to `/api/webhook_qris.php` when payment is complete
8. **Server** processes webhook, creates voucher in MikroTik, and updates database
9. **Client** receives voucher information on next status check

## Testing

Use the provided `test_apis.php` script to test API integrations:

```bash
php test_apis.php
```

This will test:
- Database connection
- MikroTik API connectivity
- Payment Gateway API connectivity
- Validator functions
- Rate limiting

## Logging

All API requests and responses are logged to:
- **API Logs**: Stored in `api_logs` table
- **Webhook Logs**: Stored in `webhook_logs` table
- **Application Logs**: Stored in `logs/app.log`

## Monitoring

Monitor the following for system health:
- Database connection status
- MikroTik API availability
- Payment Gateway API response times
- Error rates in logs
- Transaction success rates

## Troubleshooting

### Common Issues

1. **Transaction Not Found**: Verify transaction ID format and database connectivity
2. **Payment Gateway Errors**: Check API credentials and gateway status
3. **MikroTik Connection Failed**: Verify IP, credentials, and firewall settings
4. **Rate Limiting**: Implement backoff strategy in client
5. **Webhook Not Processed**: Check signature validation and logs

### Debug Mode

Set `APP_ENV` to `'development'` in `config.php` to enable debug information in error responses.

**Never use debug mode in production!**