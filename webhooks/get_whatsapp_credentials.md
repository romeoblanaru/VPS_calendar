# WhatsApp Credentials Webhook Documentation

## Overview

The `get_whatsapp_credentilas` webhook returns WhatsApp Business credentials and the related working point and organisation details. It is used by automation to fetch the data required to communicate via WhatsApp Business API.

## Endpoint

### URL
```
/webhooks/get_whatsapp_credentilas.php
```

### Method
- GET or POST

### Content-Type
```
application/json
```

### Authentication
None required

## Parameters

### Required
Provide at least one of the following identifiers:

| Parameter | Type | Description | Aliases |
|-----------|------|-------------|---------|
| `whatsapp_phone_nr_id` | string | WhatsApp Phone Number ID | `whatsapp_phone_number_id` |
| `whatsapp_business_acount_id` | string | WhatsApp Business Account ID | `whatsapp_business_account_id`, `id_nr` (legacy) |

Notes:
- If both are provided, the webhook prefers `whatsapp_phone_nr_id`.

## Usage Examples

### GET by Phone Number ID
```
GET /webhooks/get_whatsapp_credentilas.php?whatsapp_phone_nr_id=123456789012345
```

### GET by Business Account ID
```
GET /webhooks/get_whatsapp_credentilas.php?whatsapp_business_acount_id=3323131312312
```

### POST JSON (either parameter)
```bash
curl -X POST "http://your-domain.com/webhooks/get_whatsapp_credentilas.php" \
     -H "Content-Type: application/json" \
     -d '{"whatsapp_phone_nr_id":"123456789012345"}'
```

## Response Format

### Success Response (200 OK)
```json
{
  "status": "success",
  "timestamp": "2025-08-30 12:30:25",
  "query": {"whatsapp_phone_nr_id": "123456789012345"},
  "credentials": {
    "platform": "whatsapp_business",
    "whatsapp_phone_number": "+123456789",
    "whatsapp_phone_number_id": "123456789012345",
    "whatsapp_business_account_id": "3323131312312",
    "whatsapp_access_token": "...",
    "whatsapp_webhook_verify_token": "Romy_1202",
    "whatsapp_webhook_url": "https://voice.rom2.co.uk/webhook/meta",
    "is_active": 1,
    "last_test_status": "success",
    "last_test_at": "2025-08-29 10:00:00",
    "last_test_message": "OK"
  },
  "working_point": {
    "unic_id": 1,
    "name_of_the_place": "Central Branch",
    "address": "Main St 1, City",
    "lead_person_name": "Alice Manager",
    "lead_person_phone_nr": "44111222335",
    "workplace_phone_nr": "44111222336",
    "booking_phone_nr": "+123456789",
    "email": "central@beautyco.com"
  },
  "company_details": {
    "unic_id": 1,
    "alias_name": "BeautyCo",
    "official_company_name": "Beauty Co",
    "email_address": "info@beautyco.com",
    "www_address": "www.beautyco.com",
    "country": "LT"
  }
}
```

### Error Responses

#### Missing Parameter (400 Bad Request)
```json
{
  "status": "error",
  "message": "Missing required parameter: provide whatsapp_phone_nr_id or whatsapp_business_acount_id",
  "timestamp": "2025-08-30 12:30:25"
}
```

#### Not Found (200 with unsuccessful)
```json
{
  "status": "unsuccesful",
  "message": "No WhatsApp credentials found for: {\"whatsapp_phone_nr_id\":\"123456789012345\"}",
  "timestamp": "2025-08-30 12:30:25"
}
```

#### Server Error (500 Internal Server Error)
```json
{
  "status": "error",
  "message": "Database error occurred",
  "timestamp": "2025-08-30 12:30:25"
}
```

## Database Schema

Reads from:
- `workpoint_social_media` (WhatsApp fields): `whatsapp_phone_number`, `whatsapp_phone_number_id`, `whatsapp_business_account_id`, `whatsapp_access_token`, `is_active`, `last_test_status`, `last_test_at`, `last_test_message`, `workpoint_id`
- Constants (hardcoded): `WHATSAPP_WEBHOOK_VERIFY_TOKEN`, `WHATSAPP_WEBHOOK_URL` (defined in includes/db.php)
- `working_points`: `unic_id`, `name_of_the_place`, `address`, `lead_person_name`, `lead_person_phone_nr`, `workplace_phone_nr`, `booking_phone_nr`, `email`, `organisation_id`
- `organisations`: `unic_id`, `alias_name`, `oficial_company_name`, `email_address`, `www_address`, `country`

## Business Logic
1. Validate at least one identifier is present.
2. Prefer lookup by `whatsapp_phone_nr_id`; otherwise use `whatsapp_business_acount_id`.
3. Join `working_points` and `organisations` for enriched output.
4. Return credentials + working_point + company_details.
5. Log request/response via `WebhookLogger` with query metadata.

## Logging and Monitoring
Uses `WebhookLogger` to store request metadata, response status/body, processing time, and related IDs.

### Log Structure Example
```json
{
  "webhook_name": "get_whatsapp_credentilas",
  "request_method": "GET",
  "response_status_code": 200,
  "processing_time_ms": 25,
  "related_working_point_id": 1,
  "related_organisation_id": 1,
  "additional_data": {
    "platform": "whatsapp_business",
    "whatsapp_phone_nr_id": "123456789012345",
    "is_active": 1
  }
}
```

## Error Handling
- Validates required parameters (400)
- Returns not-found message when credentials are not found (200)
- Returns 500 on database or unexpected errors

## Security Considerations
- Prepared statements used for all queries
- Only required credential fields are returned

## Testing

### GET
```
GET /webhooks/get_whatsapp_credentilas.php?whatsapp_phone_nr_id=123456789012345
```

### POST
```bash
curl -X POST -H "Content-Type: application/json" -d '{"whatsapp_business_acount_id":"3323131312312"}' \
  http://your-domain.com/webhooks/get_whatsapp_credentilas.php
```

## Related Files
- `webhooks/get_whatsapp_credentilas.php`
- `includes/webhook_logger.php`
- `includes/db.php`
