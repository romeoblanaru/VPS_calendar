<?php
/**
 * Redis Configuration for Real-time Updates
 * 
 * This file handles Redis connection and provides helper functions
 * for publishing booking events and managing version counters.
 */

class RedisManager {
    private static $instance = null;
    private $redis = null;
    private $connected = false;
    
    // Redis configuration
    private $config = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 2.0,
        'database' => 0,
        'password' => null, // Set if Redis requires authentication
    ];
    
    // Channel names
    const CHANNEL_BOOKINGS = 'bookings:updates';
    const KEY_VERSION = 'bookings:version';
    const KEY_VERSION_PREFIX = 'bookings:version:';
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        try {
            $this->redis = new Redis();
            $this->connected = $this->redis->connect(
                $this->config['host'], 
                $this->config['port'], 
                $this->config['timeout']
            );
            
            if ($this->connected && $this->config['password']) {
                $this->redis->auth($this->config['password']);
            }
            
            if ($this->connected && $this->config['database'] > 0) {
                $this->redis->select($this->config['database']);
            }
            
            // Set serializer for complex data
            if ($this->connected) {
                $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
            }
            
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            $this->connected = false;
        }
    }
    
    public function isConnected() {
        return $this->connected;
    }
    
    /**
     * Publish a booking event to Redis
     */
    public function publishBookingEvent($event_type, $data) {
        if (!$this->connected) return false;
        
        try {
            $event = [
                'type' => $event_type, // 'create', 'update', 'delete'
                'timestamp' => time(),
                'data' => $data
            ];
            
            $eventJson = json_encode($event);
            
            // Push to queue for specific specialist
            if (isset($data['specialist_id'])) {
                $this->redis->lPush('bookings:queue:specialist:' . $data['specialist_id'], $eventJson);
                $this->incrementVersion('specialist:' . $data['specialist_id']);
            }
            
            // Push to queue for specific workpoint
            if (isset($data['working_point_id'])) {
                $this->redis->lPush('bookings:queue:workpoint:' . $data['working_point_id'], $eventJson);
                $this->incrementVersion('workpoint:' . $data['working_point_id']);
            }
            
            // Push to global queue
            $this->redis->lPush('bookings:queue:global', $eventJson);
            
            // Increment global version
            $this->incrementVersion();
            
            // Set TTL on queues to prevent memory issues (1 hour)
            if (isset($data['specialist_id'])) {
                $this->redis->expire('bookings:queue:specialist:' . $data['specialist_id'], 3600);
            }
            if (isset($data['working_point_id'])) {
                $this->redis->expire('bookings:queue:workpoint:' . $data['working_point_id'], 3600);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Redis publish failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current booking version
     */
    public function getVersion($key = '') {
        if (!$this->connected) return 0;
        
        try {
            $versionKey = $key ? self::KEY_VERSION_PREFIX . $key : self::KEY_VERSION;
            $version = $this->redis->get($versionKey);
            return $version ? intval($version) : 0;
        } catch (Exception $e) {
            error_log("Redis getVersion failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Increment booking version
     */
    public function incrementVersion($key = '') {
        if (!$this->connected) return false;
        
        try {
            $versionKey = $key ? self::KEY_VERSION_PREFIX . $key : self::KEY_VERSION;
            return $this->redis->incr($versionKey);
        } catch (Exception $e) {
            error_log("Redis incrementVersion failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Subscribe to booking updates (for SSE)
     */
    public function subscribe($callback, $channels = null) {
        if (!$this->connected) return false;
        
        try {
            if ($channels === null) {
                $channels = [self::CHANNEL_BOOKINGS];
            }
            
            $this->redis->subscribe($channels, $callback);
            return true;
        } catch (Exception $e) {
            error_log("Redis subscribe failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get Redis instance for advanced operations
     */
    public function getRedis() {
        return $this->redis;
    }
    
    /**
     * Cleanup on destruct
     */
    public function __destruct() {
        if ($this->connected && $this->redis) {
            try {
                $this->redis->close();
            } catch (Exception $e) {
                // Ignore close errors
            }
        }
    }
}

/**
 * Helper functions for easy access
 */
function publishBookingEvent($event_type, $data) {
    return RedisManager::getInstance()->publishBookingEvent($event_type, $data);
}

function getBookingVersion($key = '') {
    return RedisManager::getInstance()->getVersion($key);
}

function incrementBookingVersion($key = '') {
    return RedisManager::getInstance()->incrementVersion($key);
}