<?php
/**
 * Booking Cache System
 * Implements efficient caching to reduce database queries and server load
 */

class BookingCache {
    private $cache_dir;
    private $cache_file;
    
    public function __construct($mode, $specialist_id = null, $workpoint_id = null) {
        $this->cache_dir = dirname(__DIR__) . '/cache/booking_changes';
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
        
        // Generate cache file name based on parameters
        $cache_key = $mode;
        if ($mode === 'specialist' && $specialist_id) {
            $cache_key .= '_spec_' . $specialist_id;
        } else if ($mode === 'supervisor' && $workpoint_id) {
            $cache_key .= '_wp_' . $workpoint_id;
        }
        
        $this->cache_file = $this->cache_dir . '/' . $cache_key . '.json';
    }
    
    /**
     * Get cached data if it exists and is not expired
     */
    public function get($max_age = 30) {
        if (!file_exists($this->cache_file)) {
            return null;
        }
        
        $cache_data = json_decode(file_get_contents($this->cache_file), true);
        
        if (!$cache_data || !isset($cache_data['timestamp'])) {
            return null;
        }
        
        // Check if cache is expired
        if (time() - $cache_data['timestamp'] > $max_age) {
            return null;
        }
        
        return $cache_data;
    }
    
    /**
     * Set cache data
     */
    public function set($data) {
        $cache_data = [
            'timestamp' => time(),
            'data' => $data
        ];
        
        file_put_contents($this->cache_file, json_encode($cache_data), LOCK_EX);
    }
    
    /**
     * Invalidate cache (delete file)
     */
    public function invalidate() {
        if (file_exists($this->cache_file)) {
            unlink($this->cache_file);
        }
    }
    
    /**
     * Get cache age in seconds
     */
    public function getAge() {
        if (!file_exists($this->cache_file)) {
            return null;
        }
        
        $cache_data = json_decode(file_get_contents($this->cache_file), true);
        
        if (!$cache_data || !isset($cache_data['timestamp'])) {
            return null;
        }
        
        return time() - $cache_data['timestamp'];
    }
    
    /**
     * Check if cache exists and is fresh
     */
    public function isFresh($max_age = 30) {
        $age = $this->getAge();
        return $age !== null && $age <= $max_age;
    }
}
?>




