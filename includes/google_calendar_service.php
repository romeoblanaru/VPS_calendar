<?php
require_once __DIR__ . '/google_calendar_sync.php';

// Check if vendor/autoload.php exists, otherwise use alternative approach
$vendor_autoload = __DIR__ . '/../vendor/autoload.php';
$google_api_path = __DIR__ . '/google-api-php-client/autoload.php';

if (!file_exists($vendor_autoload) && !file_exists($google_api_path)) {
    // Google API client not available - use cURL implementation
    
    /**
     * Initialize Google Calendar service using cURL
     */
    function initializeGoogleCalendarService($gc_connection) {
        if (!$gc_connection || !isset($gc_connection['access_token'])) {
            throw new Exception('Invalid Google Calendar connection');
        }
        
        return new GoogleCalendarCurlService($gc_connection);
    }
    
    /**
     * Simple Google Calendar service implementation using cURL
     */
    class GoogleCalendarCurlService {
        private $gc_connection;
        private $access_token;
        
        public function __construct($gc_connection) {
            $this->gc_connection = $gc_connection;
            $token_data = json_decode($gc_connection['access_token'], true);
            $this->access_token = $token_data['access_token'] ?? '';
            
            if (!$this->access_token) {
                throw new Exception('No access token available');
            }
            
            // Check if token is expired
            if (isset($token_data['expires_in']) && isset($token_data['created'])) {
                $expires_at = $token_data['created'] + $token_data['expires_in'];
                if (time() >= $expires_at && isset($token_data['refresh_token'])) {
                    // Token is expired, refresh it
                    $this->refreshAccessToken($token_data['refresh_token']);
                }
            }
        }
        
        private function refreshAccessToken($refresh_token) {
            global $pdo;
            
            // Load Google OAuth config
            $config_file = __DIR__ . '/../config/google_oauth.json';
            if (!file_exists($config_file)) {
                throw new Exception('Google OAuth config file not found');
            }
            $config = json_decode(file_get_contents($config_file), true);
            $web_config = $config['web'] ?? [];
            
            $url = 'https://oauth2.googleapis.com/token';
            $data = [
                'refresh_token' => $refresh_token,
                'client_id' => $web_config['client_id'] ?? '',
                'client_secret' => $web_config['client_secret'] ?? '',
                'grant_type' => 'refresh_token'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $new_token_data = json_decode($response, true);
                if (isset($new_token_data['access_token'])) {
                    $this->access_token = $new_token_data['access_token'];
                    
                    // Update token in database
                    $full_token_data = json_decode($this->gc_connection['access_token'], true);
                    $full_token_data['access_token'] = $new_token_data['access_token'];
                    $full_token_data['created'] = time();
                    if (isset($new_token_data['expires_in'])) {
                        $full_token_data['expires_in'] = $new_token_data['expires_in'];
                    }
                    
                    $stmt = $pdo->prepare("UPDATE google_calendar_credentials SET access_token = ? WHERE id = ?");
                    $stmt->execute([json_encode($full_token_data), $this->gc_connection['id']]);
                }
            }
        }
        
        public function __get($name) {
            if ($name === 'events') {
                return new GoogleCalendarEventsResource($this->access_token, $this->gc_connection);
            }
        }
    }
    
    /**
     * Events resource for Google Calendar using cURL
     */
    class GoogleCalendarEventsResource {
        private $access_token;
        private $gc_connection;
        
        public function __construct($access_token, $gc_connection) {
            $this->access_token = $access_token;
            $this->gc_connection = $gc_connection;
        }
        
        public function listEvents($calendarId, $params) {
            $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendarId) . '/events';
            $url .= '?' . http_build_query($params);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->access_token,
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 200) {
                $error_data = json_decode($response, true);
                $error_message = $error_data['error']['message'] ?? 'Unknown error';
                throw new Exception('Google Calendar API error: ' . $error_message);
            }
            
            $data = json_decode($response, true);
            
            // Convert to object structure that matches Google API client
            $result = new stdClass();
            $result->items = [];
            
            if (isset($data['items'])) {
                foreach ($data['items'] as $item) {
                    $event = new stdClass();
                    $event->id = $item['id'];
                    $event->summary = $item['summary'] ?? '';
                    $event->description = $item['description'] ?? '';
                    $event->location = $item['location'] ?? '';
                    
                    // Handle start time
                    $event->start = new stdClass();
                    if (isset($item['start']['dateTime'])) {
                        $event->start->dateTime = $item['start']['dateTime'];
                    } else {
                        $event->start->dateTime = null; // All-day event
                    }
                    
                    // Handle end time
                    $event->end = new stdClass();
                    if (isset($item['end']['dateTime'])) {
                        $event->end->dateTime = $item['end']['dateTime'];
                    } else {
                        $event->end->dateTime = null; // All-day event
                    }
                    
                    $result->items[] = $event;
                }
            }
            
            // Create wrapper with getItems method
            return new class($result->items) {
                private $items;
                
                public function __construct($items) {
                    $this->items = $items;
                }
                
                public function getItems() {
                    return array_map(function($item) {
                        return new class($item) {
                            private $data;
                            
                            public function __construct($data) {
                                $this->data = $data;
                            }
                            
                            public function getId() {
                                return $this->data->id;
                            }
                            
                            public function getSummary() {
                                return $this->data->summary;
                            }
                            
                            public function getDescription() {
                                return $this->data->description;
                            }
                            
                            public function getLocation() {
                                return $this->data->location;
                            }
                            
                            public function getStart() {
                                return new class($this->data->start) {
                                    private $start;
                                    
                                    public function __construct($start) {
                                        $this->start = $start;
                                    }
                                    
                                    public function getDateTime() {
                                        return $this->start->dateTime;
                                    }
                                };
                            }
                            
                            public function getEnd() {
                                return new class($this->data->end) {
                                    private $end;
                                    
                                    public function __construct($end) {
                                        $this->end = $end;
                                    }
                                    
                                    public function getDateTime() {
                                        return $this->end->dateTime;
                                    }
                                };
                            }
                        };
                    }, $this->items);
                }
            };
        }
    }
    
} else {
    // Google API client is available
    if (file_exists($vendor_autoload)) {
        require_once $vendor_autoload;
    } else {
        require_once $google_api_path;
    }
    
    use Google\Client;
    use Google\Service\Calendar;

/**
 * Initialize Google Calendar service with credentials
 */
function initializeGoogleCalendarService($gc_connection) {
    if (!$gc_connection || !isset($gc_connection['access_token'])) {
        throw new Exception('Invalid Google Calendar connection');
    }
    
    $client = new Client();
    $client->setApplicationName('My Bookings Calendar');
    $client->setScopes(Calendar::CALENDAR);
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');
    
    // Load config
    $config_file = __DIR__ . '/../config/google_oauth.json';
    if (file_exists($config_file)) {
        $client->setAuthConfig($config_file);
    } else {
        throw new Exception('Google OAuth config file not found');
    }
    
    // Set access token
    $token = json_decode($gc_connection['access_token'], true);
    $client->setAccessToken($token);
    
    // Check if token is expired and refresh if needed
    if ($client->isAccessTokenExpired()) {
        if (isset($token['refresh_token'])) {
            $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
            $new_token = $client->getAccessToken();
            
            // Update token in database
            global $pdo;
            $stmt = $pdo->prepare("UPDATE google_calendar_credentials SET access_token = ? WHERE id = ?");
            $stmt->execute([json_encode($new_token), $gc_connection['id']]);
        } else {
            throw new Exception('Access token expired and no refresh token available');
        }
    }
    
    return new Calendar($client);
}

// Remove duplicate function - it's already defined in google_calendar_sync.php
?>