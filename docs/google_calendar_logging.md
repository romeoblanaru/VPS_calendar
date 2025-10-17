# Google Calendar Enhanced Logging Documentation

## Overview
The Google Calendar integration now features comprehensive logging that provides detailed information about all operations, API calls, and webhook activities.

## Log Files
- **Main Log**: `/srv/project_1/calendar/logs/google-calendar-worker.log`  
  Contains all standard operations, API requests/responses, and success messages

- **Error Log**: `/srv/project_1/calendar/logs/google-calendar-worker-error.log`  
  Contains errors, exceptions, and failed operations

## Log Categories

### 1. OPERATION
Logs when an operation starts (CREATE, UPDATE, DELETE)
```
[2025-09-20 23:15:30] [OPERATION] Operation: DELETE | Booking ID: 220 | client_name: Romeo Ilie | phone: 123 | service: Woman Hair cut
```

### 2. DELETE
Detailed deletion tracking with status and reasons
```
[2025-09-20 23:15:31] [DELETE] DELETION: Booking 220 | Google Event ID: gcuheplvuvbteqck7p5jnt0js4 | Status: SUCCESS
  message: Event deleted from Google Calendar
  booking_cleared: true
```

### 3. API_REQUEST
Shows exact API calls being made
```
[2025-09-20 23:15:30] [API_REQUEST] API Request: DELETE https://www.googleapis.com/calendar/v3/calendars/xxx/events/yyy
  Headers: {"Authorization":"Bearer abc123...","Content-Type":"application/json"}
```

### 4. API_RESPONSE
Shows API responses with status and data
```
[2025-09-20 23:15:31] [API_RESPONSE] API Response: Status 204
```

### 5. SUCCESS
Success confirmations with details
```
[2025-09-20 23:15:31] [SUCCESS] SUCCESS: Deleted Google Calendar event | booking_id: 220 | event_id: gcuheplvuvbteqck7p5jnt0js4
```

### 6. ERROR
Detailed error information with context
```
[2025-09-20 23:15:45] [ERROR] ERROR in DELETE_EVENT: HTTP 410 - Resource has been deleted (Code: 410)
  Context: {"booking_id":219,"event_id":"hbd2gl2sc4r9j9u3kvofemgq98"}
```

### 7. WEBHOOK
Webhook events and responses
```
[2025-09-20 23:15:50] [WEBHOOK] Webhook Event: booking_created | Booking ID: 221
  Payload: {"client_name":"John Doe","service":"Haircut"}
  Response: {"status":"success"}
```

### 8. QUEUE
Queue processing status
```
[2025-09-20 23:16:00] [QUEUE] Queue: SIGNALS_PROCESSED | count: 3
```

## Viewing Logs

### 1. Using the Log Viewer Script
```bash
# View last 50 lines with colors
php /srv/project_1/calendar/view_google_calendar_logs.php

# Follow logs in real-time (like tail -f)
php /srv/project_1/calendar/view_google_calendar_logs.php --follow

# View last 100 lines
php /srv/project_1/calendar/view_google_calendar_logs.php --lines=100

# Filter by operation type
php /srv/project_1/calendar/view_google_calendar_logs.php --type=DELETE

# Filter by booking ID
php /srv/project_1/calendar/view_google_calendar_logs.php --booking=220
```

### 2. Using Standard Commands
```bash
# View recent logs
tail -50 /srv/project_1/calendar/logs/google-calendar-worker.log

# Follow logs
tail -f /srv/project_1/calendar/logs/google-calendar-worker.log

# Search for specific booking
grep "Booking ID: 220" /srv/project_1/calendar/logs/google-calendar-worker.log

# View only errors
tail -f /srv/project_1/calendar/logs/google-calendar-worker-error.log
```

### 3. From Admin Dashboard
Navigate to PHP Workers → Google Calendar Worker → Logs

Note: The admin dashboard currently shows the error log. To see the main log, use the command line tools above.

## Understanding Delete Operations

When a booking is deleted, you'll see a sequence like this:

1. **Operation Start**:
   ```
   [OPERATION] Operation: DELETE | Booking ID: 220 | client_name: Romeo Ilie
   ```

2. **API Request**:
   ```
   [API_REQUEST] API Request: DELETE https://www.googleapis.com/calendar/v3/.../events/gcuheplvuvbteqck7p5jnt0js4
   ```

3. **API Response**:
   ```
   [API_RESPONSE] API Response: Status 204
   ```

4. **Deletion Result**:
   ```
   [DELETE] DELETION: Booking 220 | Google Event ID: gcuheplvuvbteqck7p5jnt0js4 | Status: SUCCESS
   ```

## Troubleshooting

### Common Issues

1. **"Resource has been deleted (Code: 410)"**
   - This means the event was already deleted from Google Calendar
   - The system treats this as a successful deletion

2. **No logs appearing**
   - Check if the worker is running: `ps aux | grep process_google_calendar_queue`
   - Restart the worker if needed

3. **Logs in error file instead of main log**
   - This is normal for the current setup
   - Use the view script or check the error log file

### Debug Mode
To enable verbose logging when running manually:
```bash
php /srv/project_1/calendar/process_google_calendar_queue_enhanced.php --manual --verbose
```

## Log Rotation
Consider setting up log rotation to manage file sizes:
```bash
# Add to /etc/logrotate.d/google-calendar
/srv/project_1/calendar/logs/google-calendar-worker*.log {
    daily
    rotate 7
    compress
    missingok
    notifempty
}
```