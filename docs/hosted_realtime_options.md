# Hosted Real-time Solutions

## Why Use a Hosted Service?
- No server blocking issues
- Handles millions of connections
- Works with PHP's synchronous nature
- Usually free for small usage

## 1. Pusher (pusher.com)

### PHP Code:
```php
require 'vendor/autoload.php';

$pusher = new Pusher\Pusher(
    'app_key',
    'app_secret', 
    'app_id',
    ['cluster' => 'eu']
);

// After booking insert
$pusher->trigger('specialist-2', 'booking-update', [
    'type' => 'create',
    'booking' => $bookingData
]);
```

### JavaScript:
```javascript
const pusher = new Pusher('app_key', {
    cluster: 'eu'
});

const channel = pusher.subscribe('specialist-2');
channel.bind('booking-update', function(data) {
    window.location.reload();
});
```

## 2. Ably (ably.com)

### PHP Code:
```php
$ably = new Ably\AblyRest('api_key');
$channel = $ably->channels->get('bookings:specialist:2');
$channel->publish('update', [
    'type' => 'create',
    'booking' => $bookingData
]);
```

### JavaScript:
```javascript
const ably = new Ably.Realtime('api_key');
const channel = ably.channels.get('bookings:specialist:2');
channel.subscribe('update', (message) => {
    window.location.reload();
});
```

## 3. Firebase Realtime Database

### PHP Code:
```php
$firebase = new \Kreait\Firebase\Factory();
$database = $firebase->createDatabase();

$database->getReference('bookings/updates/specialist_2')
    ->push([
        'type' => 'create',
        'timestamp' => time(),
        'booking' => $bookingData
    ]);
```

### JavaScript:
```javascript
firebase.database().ref('bookings/updates/specialist_2')
    .on('child_added', (snapshot) => {
        window.location.reload();
    });
```