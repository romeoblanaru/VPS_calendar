# Gathering Information Webhook Documentation

## Overview
The `gathering_information.php` webhook is designed to provide comprehensive information about a working point, its specialists, services, and schedules for AI voice bot consumption. This webhook is triggered by phone number-based queries and returns structured JSON data.

**Key Features**: 
- Each specialist's data includes their complete information (services, schedules, and optionally availability for a specified date range) in one organized section
- Enhanced company details including official company name, email address, and country information
- Working point information includes the booking phone number for appointment scheduling
- Comprehensive data structure optimized for AI voice bot consumption

## Phone Number Matching

The webhook uses a configurable phone number matching system that matches the last N digits of phone numbers, regardless of country code or formatting. This allows the webhook to work with phone numbers in various formats:

- `+123456789` (with country code)
- `123456789` (without country code)
- `+44 1234 56789` (with spaces)
- `123456789` (plain digits)

### Configuration

At the top of the webhook file, you can configure the number of digits to match:

```php
$PHONE_MATCH_DIGITS = 8;  // Change to 9 or 10 as needed
```

### Matching Logic

1. **Clean the input phone number**: Remove all non-digit characters
2. **Extract last N digits**: Take the last `$PHONE_MATCH_DIGITS` digits
3. **Match against database**: Compare with the last N digits of stored phone numbers

**Example**: If `$PHONE_MATCH_DIGITS = 8`:
- Input: `+123456789` → Cleaned: `123456789` → Matched: `23456789`
- Input: `+44 1234 56789` → Cleaned: `44123456789` → Matched: `12345678`
- Database: `+123456789` → Cleaned: `123456789` → Matched: `23456789`

## Endpoint
```
GET/POST: /webhooks/gathering_information.php
```

## Parameters

### Required Parameters
- **assigned_phone_nr** (string): The phone number associated with a working point's booking system. This is used to identify which working point the inquiry is about.

### Optional Parameters
- **start_date** (string, YYYY-MM-DD): Start date for the availability window. If omitted, no availability is returned.
- **end_date** (string, YYYY-MM-DD): End date for the availability window. If omitted, no availability is returned.
- **specialist_id** (integer, optional): When provided, limits the response to the specified specialist only.
- **service_id** (integer, optional): When provided, limits the services (and implicit availability calculation scope) to the specified service only.

### Request with Filters and Date Range (POST)
```
POST /webhooks/gathering_information.php
Content-Type: application/x-www-form-urlencoded

assigned_phone_nr=+123456789&start_date=2025-01-10&end_date=2025-01-12&specialist_id=101&service_id=5001
```

## Usage Examples

### Basic Request (GET)
```
GET /webhooks/gathering_information.php?assigned_phone_nr=+123456789
%2B encoding for "+"
GET /webhooks/gathering_information.php?assigned_phone_nr=%2B123456789
```

### Request with Custom Date Range (POST)
```
POST /webhooks/gathering_information.php
Content-Type: application/x-www-form-urlencoded

assigned_phone_nr=+123456789&start_date=2025-01-10&end_date=2025-01-20
```

### cURL Example
```bash
curl -X POST "http://yourdomain.com/webhooks/gathering_information.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "assigned_phone_nr=+123456789&start_date=2025-01-10&end_date=2025-01-20"
```

## Response Format

### Success Response (HTTP 200)
```json
{
  "status": "success",
  "timestamp": "2025-01-13 10:30:00",
  "company_details": {
    "unic_id": 1,
    "alias_name": "BeautyCo",
    "www_address": "www.beautyco.com",
    "official_company_name": "Beauty Company Ltd",
    "email_address": "info@beautyco.com",
    "country": "RO"
  },
  "working_point": {
    "unic_id": 1,
    "name_of_the_place": "Central Branch",
    "address": "Main St 1, City",
    "lead_person_name": "Alice Manager",
    "lead_person_phone_nr": "44111222335",
    "workplace_phone_nr": "44111222336",
    "email": "central@beautyco.com",
    "booking_phone_nr": "+123456789",
    "language": "EN"
  },
  "specialists": [
    {
      "unic_id": 1,
      "name": "Dana Zahareviciene",
      "speciality": "Hair Stylist",
      "email": "dana@beautyco.com",
      "phone_number": "37062012395",
      "services": [
        {
          "unic_id": 1,
          "name_of_service": "Haircut - Women",
          "duration": 60,
          "price_of_service": 50.00,
          "procent_vat": 21.00
        }
      ],
      "working_program": [
        {
          "day_of_week": "Monday",
          "shifts": [
            {
              "start": "09:00",
              "end": "12:00"
            },
            {
              "start": "13:00",
              "end": "17:00"
            }
          ]
        }
      ],
      "available_slots": [
        {
          "date": "2025-01-13",
          "day_of_week": "Monday",
          "slots": [
            {
              "start": "09:00",
              "end": "10:30"
            },
            {
              "start": "11:15",
              "end": "12:00"
            },
            {
              "start": "13:00",
              "end": "17:00"
            }
          ]
        },
        {
          "date": "2025-01-14",
          "day_of_week": "Tuesday",
          "slots": [
            {
              "start": "09:00",
              "end": "12:00"
            }
          ]
        }
      ]
    }
  ],
  "timezone_used": "Europe/Bucharest",
  "slots_calculated_for_days": 14,
  "time_period_checked": "2025-01-13 to 2025-01-26"
}
```

### Error Response (HTTP 400)
```json
{
  "error": "Missing required parameter: assigned_phone_nr",
  "status": "error"
}
```

### Error Response (HTTP 404)
```json
{
  "error": "Working point not found for the provided phone number (matched last 8 digits: 12345678)",
  "status": "error"
}
```

### Error Response (HTTP 500)
```json
{
  "error": "Database error occurred",
  "status": "error"
}
```

## Data Privacy Features

The webhook implements privacy controls for specialist information:

- **Phone Number Visibility**: Specialist phone numbers are only included in the response if `specialist_nr_visible_to_client` is set to `true` in the database. Otherwise, "unavailable" is returned.
- **Email Visibility**: Specialist email addresses are only included if `specialist_email_visible_to_client` is set to `true`. Otherwise, "unavailable" is returned.

## Available Slots Logic

When valid `start_date` and `end_date` are provided, the webhook generates available booking slots for each specialist by:
1. **Timezone Calculation**: Uses organization country to determine correct timezone
2. **Individual Specialist Slots**: Each specialist has their own `available_slots` array within their data
3. **Date Range Coverage**: Calculates availability for each day in the inclusive range from `start_date` to `end_date`
4. **Accurate Slot Calculation**: For each shift (shift1, shift2, shift3):
   - Takes the full shift period (e.g., 10:00-12:00)
   - Subtracts existing bookings from that period (e.g., 10:30-11:15)
   - Returns remaining available periods (e.g., 10:00-10:30 and 11:15-12:00)
5. **Minimum Period**: Only returns slots that are at least 15 minutes long
6. **Smart Splitting**: Automatically splits shift periods around existing bookings

### Example of Slot Calculation

**Scenario:**
- Specialist has shift1: 10:00-12:00 and shift2: 13:00-17:00
- Existing booking: 10:30-11:15

**Result:**
```json
"slots": [
  {
    "start": "10:00",
    "end": "10:30"
  },
  {
    "start": "11:15", 
    "end": "12:00"
  },
  {
    "start": "13:00",
    "end": "17:00"
  }
]
```

This shows how the webhook intelligently calculates available time by subtracting booked periods from shift periods.

## Timezone Support

The webhook automatically determines the correct timezone based on the organization's country:

| Country Code | Timezone |
|--------------|----------|
| RO | Europe/Bucharest (Romania) |
| LT | Europe/Vilnius (Lithuania) |
| UK | Europe/London (United Kingdom) |
| DE | Europe/Berlin (Germany) |
| FR | Europe/Paris (France) |
| ES | Europe/Madrid (Spain) |
| IT | Europe/Rome (Italy) |
| US | America/New_York (USA) |
| CA | America/Toronto (Canada) |
| *Other* | UTC (Default) |

The timezone is used to calculate the current time. Availability is only generated when valid start_date and end_date are provided.

## Date Range for Availability

You can control the availability window by providing `start_date` and `end_date`.
- If both are omitted, no availability is returned (shorter payload).
- If both are valid dates (YYYY-MM-DD) and `end_date` >= `start_date`, availability is calculated within that inclusive range.
- If only `start_date` is valid and `end_date` is missing/invalid, the webhook returns availability for that single `start_date` day.
- If invalid, `available_slots` contains the message "not valid start_date" and no slots are returned.

## Database Tables Used

- **working_points**: To identify the working point by phone number
- **organisations**: For company details
- **specialists**: For specialist information
- **services**: For service details and pricing
- **working_program**: For specialist schedules
- **specialists_setting_and_attr**: For privacy settings
- **booking**: For checking booked times when calculating availability

## Recent Changes

**Updated Response Structure (Latest Version):**
- ✅ Added `booking_phone_nr` and `language` to the `working_point` section
- ✅ Added `country`, `we_handle`, and `specialist_relevance` fields to the `working_point` section
- ✅ Added `official_company_name`, `email_address`, and `country` to the `company_details` section
- ✅ Added optional `start_date` and `end_date` parameters to control availability range; response now includes `time_period_checked` and correct `slots_calculated_for_days` based on the chosen range
- ❌ Removed `client_phone_nr` parameter and client history from the response

**Database Query Updates:**
- Enhanced the SELECT statement to include `o.oficial_company_name` and `o.email_address` from the organisations table
- The `country` field was already being selected and is now included in the response
- Added `country`, `language`, `we_handle`, and `specialist_relevance` fields from working_points table to the response payload

## Response Fields Description

### Company Details
- `unic_id`: Unique identifier for the organization
- `alias_name`: Short/display name of the company
- `www_address`: Company website URL
- `official_company_name`: Official registered company name
- `email_address`: Company contact email address
- `country`: Country code (e.g., RO, LT, UK, DE, FR, ES, IT, US, CA)

### Working Point
- `unic_id`: Unique identifier for the working point
- `name_of_the_place`: Name of the branch/location
- `address`: Physical address
- `lead_person_name`: Manager/lead person name
- `lead_person_phone_nr`: Manager's phone number
- `workplace_phone_nr`: General workplace phone
- `email`: Working point email address
- `booking_phone_nr`: Phone number used for booking appointments
- `country`: Country code where the working point is located
- `language`: Primary language used at this working point
- `we_handle`: Information about what services/cases this working point handles
- `specialist_relevance`: Relevance or importance level of specialists at this working point

### Specialist Information
- `unic_id`: Unique identifier for the specialist
- `name`: Specialist's full name
- `speciality`: Area of expertise
- `email`: Contact email (privacy-controlled)
- `phone_number`: Contact phone (privacy-controlled)
- `services`: Array of services offered by this specialist
- `working_program`: Weekly schedule with shifts (included only when start/end dates are missing or invalid)
- `available_slots`: Availability calendar for this specialist within the provided date range (omitted when dates are missing/invalid)

### Services
- `unic_id`: Unique service identifier
- `name_of_service`: Service name
- `duration`: Service duration in minutes
- `price_of_service`: Service price
- `procent_vat`: VAT percentage

### Working Program
- `day_of_week`: Day name (Monday, Tuesday, etc.)
- `shifts`: Array of work shifts with start and end times

### Specialist Available Slots (within each specialist)
- `date`: Date (YYYY-MM-DD format)
- `day_of_week`: Day name (Monday, Tuesday, etc.)
- `slots`: Array of available time periods (calculated by subtracting bookings from shifts)

### Timezone and Calculation Info
- `timezone_used`: The timezone applied based on organization country
- `slots_calculated_for_days`: Number of days calculated (0 when dates are missing/invalid)

### Client History
- `client_phone_nr`: Client's phone number
- `bookings`: Array of booking records with full details

## Error Handling

The webhook includes comprehensive error handling for:
- Missing required parameters
- Database connection issues
- Invalid phone numbers
- Data retrieval failures

## CORS Support

The webhook includes CORS headers to support cross-origin requests from web applications and handles OPTIONS preflight requests.

## Security Considerations

1. **Input Validation**: All parameters are validated before database queries
2. **SQL Injection Prevention**: Uses prepared statements for all database queries
3. **Privacy Controls**: Respects specialist privacy settings
4. **Error Information**: Avoids exposing sensitive database information in error messages

## Webhook Logger Integration

This webhook includes comprehensive logging functionality using the WebhookLogger class:

### Logged Information
- **Request Details**: Method, URL, parameters, headers, client IP
- **Response Data**: Status codes, response body, processing time
- **Related Entities**: Working point ID, organization ID, specialist count
- **Additional Data**: Timezone used, slots calculated, time period checked

### Log Structure
```json
{
    "webhook_name": "gathering_information",
    "request_method": "GET",
    "request_params": {
        "assigned_phone_nr": "+123456789",
        "start_date": "2025-01-10",
        "end_date": "2025-01-20"
    },
    "response_status_code": 200,
    "processing_time_ms": 245,
    "related_working_point_id": 1,
    "related_organisation_id": 1,
    "additional_data": {
        "specialists_count": 5,
        "timezone_used": "Europe/Bucharest",
        "slots_calculated_for_days": 11,
        "time_period_checked": "2025-01-10 to 2025-01-20"
    }
}
```

### Error Logging
- **Validation Errors**: Missing parameters, invalid phone numbers
- **Database Errors**: Connection issues, query failures
- **General Errors**: Unexpected exceptions with stack traces

### Monitoring
- All webhook calls are logged to the `webhook_logs` table
- Performance metrics are tracked for optimization
- Error patterns can be analyzed for system improvements

## Integration Notes

This webhook is designed for integration with AI voice bot systems that need comprehensive information about beauty salon operations, including real-time availability and client history for personalized service.

## Testing

To test the webhook:
1. Ensure the database contains working point data with valid phone numbers
2. Use a tool like Postman or cURL to send requests
3. Verify the JSON response structure matches the expected format
4. Test both with and without client phone numbers
5. Verify privacy settings are respected
6. Check webhook logs in the database for monitoring

## Maintenance

Regular maintenance should include:
- Monitoring webhook response times through logs
- Updating available slots logic if business rules change
- Reviewing privacy settings compliance
- Checking error logs for common issues
- Analyzing webhook usage patterns from logs
- Cleaning old log entries using `WebhookLogger::cleanOldLogs()` 