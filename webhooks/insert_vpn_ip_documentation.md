# Insert VPN IP Address Webhook

## Overview
The `insert_vpn_ip.php` webhook allows you to insert new VPN IP address records into the `ip_address` table. This webhook is designed to handle VPN configuration data insertion with proper validation and error handling.

**Key Features**: 
- **Complete VPN Configuration**: Handles IP address, phone number, and public key insertion
- **Phone Number Cleaning**: Automatically removes dots, plus signs, and spaces from phone numbers
- **Duplicate Prevention**: Prevents duplicate IP addresses and phone numbers
- **Smart Phone Number Matching**: Uses last 8 digits matching to avoid country code issues
- **Full Logging**: All operations are logged using WebhookLogger
- **Multiple Request Methods**: Supports both GET and POST requests
- **JSON Response Format**: Structured responses for both success and error cases
- **Comprehensive Validation**: IP format, phone number length, and uniqueness checks

## Endpoint
```
POST /webhooks/insert_vpn_ip.php
```

## Parameters

### Required Parameters
- **`ip_address`** (string): The IP address to insert (must be unique)
- **`phone_number`** (string): The phone number associated with this IP
- **`vpn_public_key`** (string): The VPN public key for this configuration

### Optional Parameters
- **`notes`** (string): Additional notes about this configuration

## Request Format

### Form Data (application/x-www-form-urlencoded)
```
ip_address=192.168.1.200&phone_number=+44.777.123.456&vpn_public_key=test-key-123&notes=Test configuration
```

### JSON (application/json)
```json
{
    "ip_address": "192.168.1.200",
    "phone_number": "+44.777.123.456",
    "vpn_public_key": "test-key-123",
    "notes": "Test configuration"
}
```

## Response Format

### Success Response (HTTP 200)
```json
{
    "status": "success",
    "message": "VPN IP address record inserted successfully",
    "data": {
        "id": 4,
        "ip_address": "192.168.1.200",
        "phone_number": "+44.777.123.456",
        "notes": "Test configuration",
        "date_inserted": "2025-08-20 22:35:00"
    },
    "timestamp": "2025-08-20 22:35:00"
}
```

### Error Response (HTTP 400)
```json
{
    "status": "error",
    "message": "IP address 192.168.1.200 already exists and is associated with phone number: +44.777.123.456",
    "timestamp": "2025-08-20 22:35:00"
}
```

## Validation Rules

### IP Address Validation
- Must be a valid IP address format (IPv4 or IPv6)
- Must be unique (not already exist in the database)
- Whitespace is automatically removed
- URL encoding is supported

### Phone Number Validation
- Must have at least 8 digits after cleaning
- Supports various formats (with dots, plus signs, spaces)
- Automatically removes dots, plus signs, and spaces before storing in database
- Prevents duplicate phone numbers (based on last 8 digits)

### VPN Public Key Validation
- Cannot be empty
- No specific format requirements (flexible for different key types)

## Error Scenarios

### 1. Missing Required Parameters
```json
{
    "status": "error",
    "message": "Missing required parameters: vpn_public_key",
    "timestamp": "2025-08-20 22:35:00"
}
```

### 2. Invalid IP Address Format
```json
{
    "status": "error",
    "message": "Invalid IP address format: 192.168.1.999",
    "timestamp": "2025-08-20 22:35:00"
}
```

### 3. Duplicate IP Address
```json
{
    "status": "error",
    "message": "IP address 192.168.1.200 already exists and is associated with phone number: +44.777.123.456",
    "timestamp": "2025-08-20 22:35:00"
}
```

### 4. Duplicate Phone Number
```json
{
    "status": "error",
    "message": "Phone number ending with 12345678 already exists and is associated with IP: 192.168.1.100",
    "timestamp": "2025-08-20 22:35:00"
}
```

### 5. Invalid Phone Number
```json
{
    "status": "error",
    "message": "Phone number must have at least 8 digits after cleaning",
    "timestamp": "2025-08-20 22:35:00"
}
```

### 6. Wrong HTTP Method
```json
{
    "status": "error",
    "message": "Only POST method is allowed for this webhook",
    "timestamp": "2025-08-20 22:35:00"
}
```

## Usage Examples

### cURL Example
```bash
curl -X POST \
  http://localhost/webhooks/insert_vpn_ip.php \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'ip_address=192.168.1.200&phone_number=+44.777.123.456&vpn_public_key=test-key-123&notes=Test configuration'
```

### JavaScript Fetch Example
```javascript
const data = {
    ip_address: '192.168.1.200',
    phone_number: '+44.777.123.456',
    vpn_public_key: 'test-key-123',
    notes: 'Test configuration'
};

fetch('/webhooks/insert_vpn_ip.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify(data)
})
.then(response => response.json())
.then(result => console.log(result))
.catch(error => console.error('Error:', error));
```

### PHP cURL Example
```php
$data = [
    'ip_address' => '192.168.1.200',
    'phone_number' => '+44.777.123.456',
    'vpn_public_key' => 'test-key-123',
    'notes' => 'Test configuration'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/webhooks/insert_vpn_ip.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
print_r($result);
```

## Webhook Logger Integration

This webhook includes comprehensive logging functionality using the WebhookLogger class:

### Logged Information
- **Request Details**: Method, URL, parameters, headers, client IP
- **Response Data**: Status codes, response body, processing time
- **Related Entities**: IP address, phone number, VPN configuration
- **Additional Data**: Phone number cleaning details, duplicate checks

### Log Structure
```json
{
    "webhook_name": "insert_vpn_ip",
    "request_method": "POST",
    "request_params": {
        "ip_address": "192.168.1.200",
        "phone_number": "+44.777.123.456",
        "vpn_public_key": "test-key-123"
    },
    "response_status_code": 200,
    "additional_data": {
        "phone_number_cleaned": "44777123456",
        "last_8_digits": "12345678",
        "duplicate_check_passed": true,
        "insertion_successful": true
    }
}
```

### Error Logging
- **Validation Errors**: Missing parameters, invalid IP formats, phone number issues
- **Database Errors**: Connection issues, query failures, duplicate violations
- **General Errors**: Unexpected exceptions with stack traces

### Monitoring
- All webhook calls are logged to the `webhook_logs` table
- Performance metrics are tracked for optimization
- Error patterns can be analyzed for system improvements

## Security Features

- **Input Validation**: All inputs are validated and sanitized
- **SQL Injection Protection**: Uses prepared statements
- **Duplicate Prevention**: Prevents duplicate IP addresses and phone numbers
- **Logging**: All operations are logged for audit purposes
- **Error Handling**: Comprehensive error handling with detailed messages

## Database Schema

The webhook inserts data into the `ip_address` table with the following structure:

```sql
CREATE TABLE `ip_address` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ip_address` varchar(45) NOT NULL,
    `phone_number` varchar(20) NOT NULL,
    `vpn_private_key` text NOT NULL,
    `date_of_insertion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ip_address` (`ip_address`),
    KEY `phone_number` (`phone_number`),
    KEY `date_of_insertion` (`date_of_insertion`)
);
```

## Notes

- The webhook automatically cleans phone numbers before storing (removes dots, plus signs, spaces)
- IP addresses are validated for proper format
- All operations are logged using the WebhookLogger system
- The webhook returns HTTP 400 for errors and HTTP 200 for success
- Phone numbers are matched using the last 8 digits to avoid country code issues
- Stored phone numbers are cleaned and consistent for reliable matching

## Maintenance

Regular maintenance should include:
- Monitoring webhook response times through logs
- Reviewing VPN configuration insertion performance
- Checking error logs for common validation issues
- Analyzing webhook usage patterns from logs
- Cleaning old log entries using `WebhookLogger::cleanOldLogs()`
- Monitoring duplicate prevention effectiveness
- Reviewing phone number cleaning accuracy
