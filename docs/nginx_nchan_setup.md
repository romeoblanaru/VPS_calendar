# Nginx nchan Setup for Real-time Bookings

## 1. Install Nginx with nchan module

```bash
# For Ubuntu/Debian
sudo apt-get install nginx-extras

# Verify nchan is installed
nginx -V 2>&1 | grep -o ngx_nchan_module
```

## 2. Nginx Configuration

Add to your site config:

```nginx
# Publisher endpoint (for PHP to publish)
location = /publish/booking {
    nchan_publisher;
    nchan_channel_id $arg_channel;
    nchan_store_messages on;
    nchan_message_timeout 5m;
    
    # Only allow from localhost (PHP)
    allow 127.0.0.1;
    deny all;
}

# Subscriber endpoint (for browsers SSE)
location ~ ^/events/(specialist|workpoint|admin)/(\d+)$ {
    nchan_subscriber eventsource;
    nchan_channel_id $1_$2;
    
    # CORS headers if needed
    add_header 'Access-Control-Allow-Origin' '$http_origin' always;
    add_header 'Access-Control-Allow-Credentials' 'true' always;
}

# Optional: Channel stats
location /nchan_stub_status {
    nchan_stub_status;
    allow 127.0.0.1;
    deny all;
}
```

## 3. Update PHP to publish via nchan

```php
// In process_booking.php, replace Redis publish with:
function publishBookingEvent($event_type, $data) {
    $event = [
        'type' => $event_type,
        'timestamp' => time(),
        'data' => $data
    ];
    
    // Publish to specialist channel
    if (isset($data['specialist_id'])) {
        $channel = 'specialist_' . $data['specialist_id'];
        publishToNchan($channel, $event);
    }
    
    // Publish to workpoint channel
    if (isset($data['working_point_id'])) {
        $channel = 'workpoint_' . $data['working_point_id'];
        publishToNchan($channel, $event);
    }
}

function publishToNchan($channel, $data) {
    $url = "http://localhost/publish/booking?channel=" . urlencode($channel);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}
```

## 4. Update JavaScript

```javascript
// Simple connection to nchan
const eventSource = new EventSource('/events/specialist/<?= $specialist_id ?>');

eventSource.onmessage = function(event) {
    const data = JSON.parse(event.data);
    console.log('Booking update:', data);
    
    // Reload page on updates
    if (data.type === 'create' || data.type === 'update' || data.type === 'delete') {
        window.location.reload();
    }
};

eventSource.onerror = function(error) {
    console.error('SSE error:', error);
};
```

## 5. Testing

```bash
# Test publishing
curl -X POST http://localhost/publish/booking?channel=specialist_2 \
  -H "Content-Type: application/json" \
  -d '{"type":"test","data":{"message":"Hello"}}'

# View channel stats
curl http://localhost/nchan_stub_status
```

## Why nchan is Perfect for This

1. **Zero blocking** - Nginx handles all connections asynchronously
2. **Minimal setup** - Just Nginx config, no new services
3. **Production ready** - Used by Reddit, Disqus, and others
4. **Built-in features**:
   - Message history/replay
   - Channel multiplexing
   - WebSocket and SSE support
   - Automatic client reconnection
   - Message buffering

5. **Performance** - Can handle 100K+ concurrent connections on modest hardware