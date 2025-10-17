# Messenger Credentials Webhook Documentation

## Overview

The `get_messinger_credentials` webhook returns Facebook Messenger credentials and the related working point and organisation details. It is used by automation to fetch the data required to communicate via Facebook Messenger Platform.

## Endpoint

### URL
```
/webhooks/get_messinger_credentials.php
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

### Required Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `page_id` | string | Facebook Page ID | `1234567890` |

### Parameter Details
- `page_id`: Identifies the Facebook Page configured for a `working_point` in `workpoint_social_media`.

## Usage Examples

### GET Request Example

```
GET /webhooks/get_messinger_credentials.php?page_id=1234567890
```

```bash
curl "http://your-domain.com/webhooks/get_messinger_credentials.php?page_id=1234567890"
```

### POST Request Example

**URL:**
```
POST /webhooks/get_messinger_credentials.php
```

**Request Body:**
```json
{
  "page_id": "1234567890"
}
```

**cURL Command:**
```bash
curl -X POST "http://your-domain.com/webhooks/get_messinger_credentials.php" \
     -H "Content-Type: application/json" \
     -d '{"page_id":"1234567890"}'
```

## Response Format

### Success Response (200 OK)

```json
{
  "status": "success",
  "timestamp": "2025-08-30 12:30:25",
  "query": {"page_id": "1234567890"},
  "credentials": {
    "platform": "facebook_messenger",
    "facebook_page_id": "1234567890",
    "facebook_page_access_token": "...",
    "facebook_app_id": "...",
    "facebook_app_secret": "...",
    "facebook_webhook_verify_token": "Romy_1202",
    "facebook_webhook_url": "https://voice.rom2.co.uk/webhook/meta",
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
  "message": "Missing required parameter: page_id",
  "timestamp": "2025-08-30 12:30:25"
}
```

#### Not Found (404 Not Found)
```json
{
  "status": "error",
  "message": "No Facebook Messenger credentials found for page_id: 1234567890",
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
- `workpoint_social_media` (Messenger fields): `facebook_page_id`, `facebook_page_access_token`, `facebook_app_id`, `facebook_app_secret`, `is_active`, `last_test_status`, `last_test_at`, `last_test_message`, `workpoint_id`
- Constants (hardcoded): `FACEBOOK_WEBHOOK_VERIFY_TOKEN`, `FACEBOOK_WEBHOOK_URL` (defined in includes/db.php)
- `working_points`: `unic_id`, `name_of_the_place`, `address`, `lead_person_name`, `lead_person_phone_nr`, `workplace_phone_nr`, `booking_phone_nr`, `email`, `organisation_id`
- `organisations`: `unic_id`, `alias_name`, `oficial_company_name`, `email_address`, `www_address`, `country`

## Business Logic

1. Validate `page_id` is present.
2. Query `workpoint_social_media` for platform `facebook_messenger` with `facebook_page_id = page_id`.
3. Join `working_points` and `organisations` for enriched output.
4. Return credentials + working_point + company_details.
5. Log request/response via `WebhookLogger`.

## Logging and Monitoring

Uses `WebhookLogger` to store request metadata, response status/body, processing time, and related IDs.

### Log Structure Example
```json
{
  "webhook_name": "get_messinger_credentials",
  "request_method": "GET",
  "response_status_code": 200,
  "processing_time_ms": 25,
  "related_working_point_id": 1,
  "related_organisation_id": 1,
  "additional_data": {
    "platform": "facebook_messenger",
    "page_id": "1234567890",
    "is_active": 1
  }
}
```

## Error Handling
- Validates required parameters (400)
- Returns 404 when credentials are not found
- Returns 500 on database or unexpected errors

## Security Considerations
- Prepared statements used for all queries
- Only required credential fields are returned

## Testing

### GET
```
GET /webhooks/get_messinger_credentials.php?page_id=1234567890
```

### POST
```bash
curl -X POST -H "Content-Type: application/json" -d '{"page_id":"1234567890"}' \
  http://your-domain.com/webhooks/get_messinger_credentials.php
```

## Related Files
- `webhooks/get_messinger_credentials.php`
- `includes/webhook_logger.php`
- `includes/db.php`
