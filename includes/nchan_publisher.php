<?php
/**
 * Nchan Publisher for Real-time Updates
 * Publishes events to Nginx nchan channels
 */

class NchanPublisher {
    // Use internal HTTP server to avoid HTTPS/HTTP2 timeout issues
    private $publishUrl = 'http://127.0.0.1:8083/internal/publish/booking';
    
    /**
     * Publish booking event to nchan channels
     */
    public function publishBookingEvent($event_type, $data) {
        $event = [
            'type' => $event_type,
            'timestamp' => time(),
            'data' => $data
        ];
        
        $success = true;
        
        // Publish to specialist channel
        if (isset($data['specialist_id'])) {
            $channel = 'specialist_' . $data['specialist_id'];
            if (!$this->publishToChannel($channel, $event)) {
                $success = false;
            }
        }
        
        // Publish to workpoint channel  
        if (isset($data['working_point_id'])) {
            $channel = 'workpoint_' . $data['working_point_id'];
            if (!$this->publishToChannel($channel, $event)) {
                $success = false;
            }
        }
        
        // Publish to admin channel
        $this->publishToChannel('admin_all', $event);
        
        return $success;
    }
    
    /**
     * Publish to a specific nchan channel
     */
    private function publishToChannel($channel, $data) {
        $url = $this->publishUrl . '?channel=' . urlencode($channel);
        
        // Send JSON data - nchan will format as SSE
        $jsonData = json_encode($data);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // 2 second timeout
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For self-signed certs
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // For self-signed certs
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Nchan publish success: $channel");
            return true;
        } else {
            error_log("Nchan publish failed: $channel - HTTP $httpCode");
            return false;
        }
    }
}

// Helper function for easy access
function publishBookingEvent($event_type, $data) {
    static $publisher = null;
    if ($publisher === null) {
        $publisher = new NchanPublisher();
    }
    return $publisher->publishBookingEvent($event_type, $data);
}