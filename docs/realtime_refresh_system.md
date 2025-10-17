# Real-time Booking Refresh System

## Overview
This system provides real-time updates for bookings using a **push-based architecture** with Redis pub/sub and Server-Sent Events (SSE). When a booking changes, the update is pushed instantly to all relevant browsers without any polling. Designed to handle ~500 concurrent clients with minimal server load.

## Architecture

### Components
1. **Redis Manager** (`/includes/redis_config.php`) - Handles Redis connections and pub/sub operations
2. **SSE Endpoint** (`/api/bookings_sse.php`) - Streams real-time updates to browsers
3. **Version Endpoint** (`/api/bookings_version.php`) - Lightweight polling fallback
4. **JavaScript Client** (`/assets/js/realtime-bookings.js`) - Browser-side event handling
5. **Booking Operations** (`process_booking.php`) - Publishes events on booking changes

### How It Works

1. **Push-Based Event Flow:**
   ```
   Booking Change → PHP publishes to Redis → Redis pushes to ALL subscribers → Browsers refresh instantly
   ```
   - Zero polling when idle - connections just wait for events
   - Instant updates (typically < 1 second)
   - No database queries during normal operation

2. **SSE (Primary Method):**
   - Browser opens long-lived HTTP connection to `/api/bookings_sse.php`
   - PHP subscribes to Redis channel and waits for events
   - When event arrives, it's immediately pushed to browser
   - **Key:** The connection stays open and idle until an event occurs

3. **Smart Filtering:**
   - Events are filtered server-side based on user role
   - Specialists only receive their own booking updates
   - Supervisors only receive their workpoint's updates
   - Reduces unnecessary refreshes

4. **Polling Fallback:**
   - ONLY activates if SSE connection fails
   - Polls `/api/bookings_version.php` every 7 seconds
   - Checks version numbers in Redis (no database queries)
   - Minimal load even in fallback mode

## Configuration

### Redis Setup
```bash
# Install Redis
sudo apt-get install redis-server

# Start Redis
sudo systemctl start redis
sudo systemctl enable redis

# Verify Redis is running
redis-cli ping
# Should return: PONG
```

### PHP Redis Extension
```bash
# Install PHP Redis extension
sudo apt-get install php-redis

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm  # or your PHP version
```

### Redis Configuration (optional)
Edit `/srv/project_1/calendar/includes/redis_config.php`:
```php
private $config = [
    'host' => '127.0.0.1',
    'port' => 6379,
    'timeout' => 2.0,
    'database' => 0,
    'password' => null,  // Set if using Redis auth
];
```

## Usage

### Status Button
The real-time status button in the top panel shows:
- 🟢 Green: Connected (SSE or polling active)
- 🟡 Yellow: Reconnecting
- 🔴 Red: Disconnected/Error

Click the button to enable/disable real-time updates.

### Event Types
- `create` - New booking created
- `update` - Booking modified
- `delete` - Booking cancelled

### Filtering
Updates are filtered by:
- **Specialist mode**: Only sees their own bookings
- **Supervisor mode**: Only sees bookings for their workpoint
- **Admin mode**: Sees all bookings

## Testing the System

### Quick Test
1. Open booking page and verify status shows "Real-time (SSE)"
2. Create test booking:
   ```bash
   php test_booking_push.php
   ```
3. Browser should refresh within 1-2 seconds

### Manual Testing via UI
1. Open booking page in Browser A (as specialist)
2. Open same page in Browser B (different tab/browser)
3. Create booking in Browser A
4. Browser B should refresh automatically

## Troubleshooting

### SSE Not Working / Yellow "Reconnecting" Status
1. **Check Redis is running:**
   ```bash
   redis-cli ping
   # Should return: PONG
   ```

2. **Verify PHP Redis extension:**
   ```bash
   php -m | grep redis
   # Should show: redis
   ```

3. **Check SSE endpoint directly:**
   - Open browser developer tools (F12)
   - Look for SSE connection in Network tab
   - Should show `bookings_sse.php` with status "200 OK" and type "eventsource"

4. **Common issues:**
   - Nginx buffering (fixed with `X-Accel-Buffering: no` header)
   - PHP execution timeout (fixed with `set_time_limit(0)`)
   - Output buffering (fixed with `ob_end_clean()`)

### No Updates Received
1. **Test Redis pub/sub manually:**
   ```bash
   # Terminal 1 - Subscribe
   redis-cli
   > subscribe bookings:updates
   
   # Terminal 2 - Publish test
   redis-cli
   > publish bookings:updates '{"type":"test","data":{"test":true}}'
   ```

2. **Check event filtering:**
   - Specialists only see their own bookings
   - Supervisors only see their workpoint
   - Verify specialist_id/workpoint_id match

3. **Verify publish calls in process_booking.php:**
   - Look for `publishBookingEvent()` after INSERT/UPDATE/DELETE
   - Check Redis connection in error logs

### High Server Load
This should NOT happen with proper SSE implementation. If it does:
1. Check for multiple reconnection attempts
2. Verify SSE connections are long-lived (not reconnecting constantly)
3. Monitor with: `ss -an | grep :80 | wc -l` (count of connections)

### Page Loading Slowly
Run the SQL indexes if not already done:
```sql
ALTER TABLE booking ADD INDEX idx_workpoint_date (id_work_place, booking_start_datetime);
ALTER TABLE booking ADD INDEX idx_specialist_date (id_specialist, booking_start_datetime);
```

## Performance Considerations

### Why This Architecture Scales
1. **Push vs Pull:**
   - Traditional polling: 500 clients × query every 5s = 100 queries/second
   - SSE push: 0 queries/second when idle, instant updates when needed

2. **Resource Usage:**
   - Each SSE connection uses minimal memory (~1-2MB)
   - Connections are idle 99% of the time (just waiting)
   - Redis handles all fan-out in memory (microseconds)
   - No database load for checking updates

3. **Capacity:**
   - 500 concurrent SSE connections = ~500-1000MB RAM
   - Modern servers can handle thousands of idle connections
   - Bottleneck is usually PHP-FPM worker pool, not the architecture

## Security
- Authentication required for all endpoints
- Events filtered server-side by user permissions  
- No sensitive booking data exposed in version checks
- Redis can be configured with password authentication
- SSE connections inherit session security

## Architecture Benefits

### Instant Updates
- Sub-second latency from booking change to browser refresh
- No polling delays or missed updates
- Better user experience

### Minimal Server Load  
- Idle connections use almost no CPU
- Redis pub/sub is extremely efficient
- No database polling overhead

### Graceful Degradation
- Falls back to version polling if SSE fails
- Version polling still more efficient than full queries
- System remains functional even without Redis