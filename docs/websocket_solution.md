# WebSocket Solution with Ratchet

## Installation
```bash
composer require cboden/ratchet
```

## WebSocket Server (ws-server.php)
```php
<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class BookingNotifier implements MessageComponentInterface {
    protected $clients;
    protected $redis;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg);
        if ($data->type === 'subscribe') {
            $from->specialistId = $data->specialistId;
        }
    }

    public function checkRedis() {
        // Check Redis for updates and notify clients
        foreach ($this->clients as $client) {
            if (isset($client->specialistId)) {
                $update = $this->redis->rPop('bookings:queue:specialist:' . $client->specialistId);
                if ($update) {
                    $client->send($update);
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
    }
}

// Run with: php ws-server.php
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new BookingNotifier()
        )
    ),
    8080
);

$server->run();
```