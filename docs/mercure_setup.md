# Mercure Hub Setup for Real-time Updates

## What is Mercure?
Mercure is a protocol for real-time push notifications that works great with PHP. It handles all the SSE complexity for you.

## Installation

1. Download Mercure binary:
```bash
wget https://github.com/dunglas/mercure/releases/download/v0.14.10/mercure_0.14.10_Linux_x86_64.tar.gz
tar xvf mercure_0.14.10_Linux_x86_64.tar.gz
```

2. Create config file `mercure.env`:
```env
MERCURE_PUBLISHER_JWT_KEY='!ChangeThisSecretKey!'
MERCURE_SUBSCRIBER_JWT_KEY='!ChangeThisSecretKey!'
MERCURE_ALLOW_ANONYMOUS=1
MERCURE_CORS_ALLOWED_ORIGINS='*'
```

3. Run Mercure:
```bash
./mercure --config mercure.env
```

## PHP Integration

```php
// Publisher (in process_booking.php)
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

$update = new Update(
    'https://my-bookings.co.uk/bookings/specialist/2',
    json_encode(['type' => 'booking_update', 'data' => $bookingData])
);

$hub->publish($update);
```

## JavaScript Client

```javascript
const eventSource = new EventSource('http://localhost:3000/.well-known/mercure?topic=' + 
  encodeURIComponent('https://my-bookings.co.uk/bookings/specialist/2'));

eventSource.onmessage = event => {
    const data = JSON.parse(event.data);
    window.location.reload();
};
```