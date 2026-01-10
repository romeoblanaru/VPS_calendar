# Check IP Address Webhook Documentation

## Overview
The `check_ip.php` webhook is designed to check IP addresses and phone numbers against the `ip_address` table to verify if they are already in use. This webhook is essential for VPN client management, allowing administrators to verify the uniqueness of IP addresses and phone numbers before assigning them to new clients.

**Key Features**: 
- **IP Address Validation**: Checks if an IP address is already assigned to a VPN client
- **Phone Number Lookup**: Finds the IP address associated with a specific phone number
- **Last 9 Digits Matching**: Matches phone numbers by their last 9 digits to avoid country code issues
- **Null Response Handling**: Returns `null` when values are not found, indicating they are available for use
- **Full Logging**: All requests and responses are logged using WebhookLogger
- **Multiple Request Methods**: Supports both GET and POST requests
- **JSON Response Format**: Structured responses for both success and error cases
- **List All Functionality**: Returns all IP addresses, phone numbers, and client names when no parameters are provided or when `list=all` is specified

## Endpoint

### URL
```
GET/POST: /webhooks/check_ip.php
```

### Method
- **GET** or **POST**

### Content-Type
```
application/json
```

### Authentication
None required

## Parameters

### Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| *none* | - | Returns all IP/phone/client associations | - |
| `list` | string | When set to 'all', returns all associations | `list=all` |
| `ip` | string | IP address to check for existing assignment | `192.168.1.100` |
| `nr` | string | Phone number to check for existing assignment | `+123456789` |

### Parameter Details

- **No parameters or `list=all`**: Returns all IP address associations in the database, including IP address, phone number, and client name (from notes field). Phone numbers in the response are formatted from right to left as "XXX XXXX XXX" (remaining digits, space, 4 digits, space, last 3 digits) with the '+' sign removed.
- **`ip`**: The IP address to check. Must be a valid IPv4 or IPv6 format. The webhook automatically handles URL encoding and whitespace removal. Returns the associated phone number if found, or `null` if the IP is available.
- **`nr`**: The phone number to check. Can be in any format (with or without country code, spaces, dashes, dots). The webhook automatically removes dots and handles URL encoding. The webhook matches the last 9 digits to avoid country code issues. Returns the associated IP address if found, or `null` if the phone number is available.

## Phone Number Matching Logic

The webhook uses a smart phone number matching system that matches the last 9 digits of phone numbers, regardless of country code or formatting:

1. **URL decode**: Decode any URL-encoded characters in the input
2. **Remove dots**: Remove all dots from the phone number (e.g., `123.456.7890` → `1234567890`)
3. **Clean the input**: Remove all non-digit characters (spaces, dashes, parentheses, plus signs)
4. **Extract last 9 digits**: Take the last 9 digits from the cleaned number
5. **Match against database**: Compare with the last 9 digits of stored phone numbers (also removing dots and spaces)

**Examples**:
- Input: `+123.456.7890` → Dots removed: `+1234567890` → Cleaned: `1234567890` → Matched: `234567890`
- Input: `123.456.78.90` → Dots removed: `1234567890` → Cleaned: `1234567890` → Matched: `234567890`
- Input: `+44 1234 567890` → Cleaned: `441234567890` → Matched: `234567890`
- Input: `(555) 123-45678` → Cleaned: `55512345678` → Matched: `512345678`

## IP Address Processing Logic

The webhook processes IP addresses through the following steps to ensure robust handling:

1. **Trim whitespace**: Remove leading and trailing spaces
2. **Remove internal whitespace**: Remove all spaces, tabs, newlines, and carriage returns
3. **URL decode**: Decode any URL-encoded characters (e.g., `%2E` → `.`)
4. **Validate format**: Ensure the result is a valid IPv4 or IPv6 address

**Examples**:
- Input: ` 192.168.1.100 ` → Trimmed: `192.168.1.100` → Valid IP
- Input: `192%2E168%2E1%2E100` → Decoded: `192.168.1.100` → Valid IP
- Input: `192 . 168 . 1 . 100` → Spaces removed: `192.168.1.100` → Valid IP

## Usage Examples

### List All Associations

**URL (No parameters):**
```
GET /webhooks/check_ip.php
```

**URL (With list parameter):**
```
GET /webhooks/check_ip.php?list=all
```

**cURL Command:**
```bash
curl "http://yourdomain.com/webhooks/check_ip.php"
# or
curl "http://yourdomain.com/webhooks/check_ip.php?list=all"
```

**Expected Response:**
```json
{
    "status": "success",
    "action": "list_all",
    "count": 3,
    "results": [
        {
            "ip_address": "192.168.1.100",
            "phone_number": "123 4567 890",
            "client_name": "John Doe"
        },
        {
            "ip_address": "192.168.1.101",
            "phone_number": "987 6543 210",
            "client_name": "Jane Smith"
        },
        {
            "ip_address": "192.168.1.102",
            "phone_number": "555 1234 567",
            "client_name": "Bob Johnson"
        }
    ],
    "timestamp": "2025-01-15 14:30:25"
}
```

### Check IP Address (GET)

**URL:**
```
GET /webhooks/check_ip.php?ip=192.168.1.100
```

**cURL Command:**
```bash
curl "http://yourdomain.com/webhooks/check_ip.php?ip=192.168.1.100"
```

**Expected Response (IP Found):**
```json
{
    "status": "success",
    "parameter": "ip",
    "value": "192.168.1.100",
    "result": "+123456789",
    "found": true,
    "timestamp": "2025-01-15 14:30:25"
}
```

**Expected Response (IP Available):**
```json
{
    "status": "success",
    "parameter": "ip",
    "value": "192.168.1.100",
    "result": null,
    "found": false,
    "timestamp": "2025-01-15 14:30:25"
}
```

### Check Phone Number (GET)

**URL:**
```
GET /webhooks/check_ip.php?nr=%2B123.456.7890
```

**Alternative Phone Number Formats:**
```bash
# Phone with dots
curl "http://your-domain.com/webhooks/check_ip.php?nr=123.456.7890"

# URL encoded phone with dots
curl "http://your-domain.com/webhooks/check_ip.php?nr=%2B123.456.7890"

# Phone with various formats
curl "http://your-domain.com/webhooks/check_ip.php?nr=%2B1%28555%29%20123-4567"
```

**Expected Response (Phone Number Found):**
```json
{
    "status": "success",
    "parameter": "nr",
    "value": "+123456789",
    "last_9_digits": "123456789",
    "result": "192.168.1.100",
    "found": true,
    "timestamp": "2025-01-15 14:30:25"
}
```

**Expected Response (Phone Number Available):**
```json
{
    "status": "success",
    "parameter": "nr",
    "value": "+123456789",
    "last_9_digits": "123456789",
    "result": null,
    "found": false,
    "timestamp": "2025-01-15 14:30:25"
}
```

### POST Request Examples

**Form Data POST:**
```bash
curl -X POST "http://your-domain.com/webhooks/check_ip.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "ip=192.168.1.100"
```

**JSON POST:**
```bash
curl -X POST "http://your-domain.com/webhooks/check_ip.php" \
  -H "Content-Type: application/json" \
  -d '{"nr": "+123456789"}'
```

## Response Format

### Success Response (HTTP 200)

#### IP Address Check Response
```json
{
    "status": "success",
    "parameter": "ip",
    "value": "192.168.1.100",
    "result": "+123456789",
    "found": true,
    "timestamp": "2025-01-15 14:30:25"
}
```

#### Phone Number Check Response
```json
{
    "status": "success",
    "parameter": "nr",
    "value": "+123456789",
    "last_9_digits": "123456789",
    "result": "192.168.1.100",
    "found": true,
    "timestamp": "2025-01-15 14:30:25"
}
```

### Response Fields

#### Individual Lookup Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `status` | string | Always "success" for successful requests |
| `parameter` | string | The parameter used ("ip" or "nr") |
| `value` | string | The original input value |
| `result` | string/null | The associated data if found, or `null` if not found |
| `found` | boolean | `true` if the value was found, `false` if not |
| `last_9_digits` | string | Only present for phone number checks - the last 9 digits used for matching |
| `timestamp` | string | ISO format timestamp of the request |

#### List All Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `status` | string | Always "success" for successful requests |
| `action` | string | Always "list_all" for list operations |
| `count` | integer | Number of records returned |
| `results` | array | Array of objects containing ip_address, phone_number, and client_name |
| `timestamp` | string | ISO format timestamp of the request |

### Error Response (HTTP 400)

```json
{
    "status": "error",
    "message": "For individual lookups, exactly one parameter required. Use either 'ip' or 'nr'. For full list, use no parameters or 'list=all'.",
    "timestamp": "2025-01-15 14:30:25"
}
```

## Error Scenarios

### Common Error Messages

| Error | Description | HTTP Code |
|-------|-------------|-----------|
| `For individual lookups, exactly one parameter required` | Wrong parameters for individual lookup | 400 |
| `Invalid IP address format` | IP address is not in valid format | 400 |
| `Phone number cannot be empty` | Empty phone number provided | 400 |
| `Phone number must have at least 9 digits` | Phone number too short | 400 |
| `Unsupported method` | Request method other than GET/POST | 400 |

## Use Cases

### VPN Client Management
- **Before assigning new IP**: Check if IP address is already in use
- **Before assigning new phone number**: Check if phone number is already assigned
- **Client lookup**: Find which IP address belongs to a specific phone number
- **Conflict resolution**: Identify duplicate assignments

### Network Administration
- **IP address planning**: Verify IP availability in VPN subnet
- **Phone number management**: Track which phone numbers are assigned to VPN clients
- **Audit trails**: Log all IP and phone number lookups for compliance

## Database Schema

The webhook queries the `ip_address` table with the following structure:

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

## Webhook Logger Integration

This webhook includes comprehensive logging functionality using the WebhookLogger class:

### Logged Information
- **Request Details**: Method, URL, parameters, headers, client IP
- **Response Data**: Status codes, response body, processing time
- **Related Entities**: IP address, phone number, lookup type
- **Additional Data**: Lookup results, phone number processing details

### Log Structure
```json
{
    "webhook_name": "check_ip",
    "request_method": "GET",
    "request_params": {
        "ip": "192.168.1.100"
    },
    "response_status_code": 200,
    "additional_data": {
        "lookup_type": "ip",
        "lookup_value": "192.168.1.100",
        "result_found": true,
        "associated_phone": "+123456789"
    }
}
```

### Error Logging
- **Validation Errors**: Missing parameters, invalid IP formats
- **Database Errors**: Connection issues, query failures
- **General Errors**: Unexpected exceptions with stack traces

### Monitoring
- All webhook calls are logged to the `webhook_logs` table
- Performance metrics are tracked for optimization
- Error patterns can be analyzed for system improvements

## Security Considerations

- **No authentication required**: This webhook is designed for internal use
- **Input validation**: All inputs are validated before database queries
- **SQL injection protection**: Uses prepared statements for all database queries
- **Rate limiting**: Consider implementing rate limiting for production use

## Testing

### Test with Sample Data

1. **Insert test data** into the `ip_address` table:
```sql
INSERT INTO ip_address (ip_address, phone_number, vpn_private_key, notes) 
VALUES ('192.168.1.100', '+123456789', 'test-key', 'Test entry');
```

2. **Test IP lookup**:
```bash
curl "http://your-domain.com/webhooks/check_ip.php?ip=192.168.1.100"
```

3. **Test phone number lookup**:
```bash
curl "http://your-domain.com/webhooks/check_ip.php?nr=%2B123456789"
```

4. **Test with non-existent values**:
```bash
curl "http://your-domain.com/webhooks/check_ip.php?ip=192.168.1.200"
curl "http://your-domain.com/webhooks/check_ip.php?nr=%2B987654321"
```

## Integration Examples

### Telnyx Integration
```javascript
// Check if IP is available before assignment
fetch('/webhooks/check_ip.php?ip=192.168.1.100')
  .then(response => response.json())
  .then(data => {
    if (data.result === null) {
      // IP is available for assignment
      console.log('IP available:', data.value);
    } else {
      // IP is already assigned
      console.log('IP assigned to:', data.result);
    }
  });
```

### VPN Management System
```php
// Check phone number availability
$phone = '+123456789';
$response = file_get_contents("http://yourdomain.com/webhooks/check_ip.php?nr=" . urlencode($phone));
$data = json_decode($response, true);

if ($data['result'] === null) {
    // Phone number is available
    echo "Phone number $phone is available for assignment";
} else {
    // Phone number is already assigned to IP
    echo "Phone number $phone is assigned to IP: " . $data['result'];
}
```

## Maintenance

Regular maintenance should include:
- Monitoring webhook response times through logs
- Reviewing IP address lookup performance
- Checking error logs for common validation issues
- Analyzing webhook usage patterns from logs
- Cleaning old log entries using `WebhookLogger::cleanOldLogs()`
- Monitoring database performance for IP lookups
