<?php
// Google OAuth Configuration for Calendar Integration
// This file handles the OAuth2 flow for Google Calendar API access

// Include database connection (only when available)
if (file_exists(__DIR__ . '/../includes/db.php')) {
    require_once __DIR__ . '/../includes/db.php';
}
if (file_exists(__DIR__ . '/../includes/logger.php')) {
    require_once __DIR__ . '/../includes/logger.php';
}

// Google OAuth Configuration
class GoogleOAuthConfig {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $scope;
    private $project_id;
    private $redirect_uris;
    
    public function __construct() {
        // Look for Google's credential file format first
        $google_cred_files = glob(__DIR__ . '/../config/client_secret_*.json');
        $config_file = __DIR__ . '/../config/google_oauth.json';
        
        if (!empty($google_cred_files)) {
            // Use Google's credential file format
            $config = json_decode(file_get_contents($google_cred_files[0]), true);
            if (isset($config['web'])) {
                $this->client_id = $config['web']['client_id'] ?? '';
                $this->client_secret = $config['web']['client_secret'] ?? '';
                $this->project_id = $config['web']['project_id'] ?? '';
                $this->redirect_uris = $config['web']['redirect_uris'] ?? [];
            } else {
                throw new Exception('Invalid Google credentials file format.');
            }
        } elseif (file_exists($config_file)) {
            // Use our custom format
            $config = json_decode(file_get_contents($config_file), true);
            $this->client_id = $config['client_id'] ?? '';
            $this->client_secret = $config['client_secret'] ?? '';
            $this->project_id = $config['project_id'] ?? '';
            $this->redirect_uris = $config['redirect_uris'] ?? [];
        } else {
            // Fallback to hardcoded values (REPLACE THESE WITH YOUR ACTUAL CREDENTIALS)
            $this->client_id = 'YOUR_GOOGLE_CLIENT_ID';
            $this->client_secret = 'YOUR_GOOGLE_CLIENT_SECRET';
        }
        
        // Validate credentials are set
        if ($this->client_id === 'YOUR_GOOGLE_CLIENT_ID' || empty($this->client_id)) {
            throw new Exception('Google OAuth Client ID not configured. Please place your Google credentials file in the config/ directory.');
        }
        
        if ($this->client_secret === 'YOUR_GOOGLE_CLIENT_SECRET' || empty($this->client_secret)) {
            throw new Exception('Google OAuth Client Secret not configured. Please place your Google credentials file in the config/ directory.');
        }
        
        // Use the redirect URI from Google's config if available, otherwise construct it
        if (!empty($this->redirect_uris)) {
            $this->redirect_uri = $this->redirect_uris[0];
        } else {
            $this->redirect_uri = $this->getBaseUrl() . '/admin/google_oauth_callback.php';
        }
        
        $this->scope = 'https://www.googleapis.com/auth/calendar';
    }
    
    private function getBaseUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $domain = $_SERVER['HTTP_HOST'];
        
        // Get the application root by removing /admin from the script path
        $script_dir = dirname($_SERVER['SCRIPT_NAME']);
        $app_root = str_replace('/admin', '', $script_dir);
        
        return $protocol . $domain . rtrim($app_root, '/');
    }
    
    public function getAuthUrl($specialist_id, $state = null) {
        if (!$state) {
            $state = bin2hex(random_bytes(16));
        }
        
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => $this->scope,
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state . '|' . $specialist_id
        ];
        
        return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
    }
    
    public function exchangeCodeForTokens($authorization_code, $actual_redirect_uri = null) {
        $token_url = 'https://oauth2.googleapis.com/token';
        
        // Use the actual redirect URI that was used to get the code, or fall back to configured one
        $redirect_uri_to_use = $actual_redirect_uri ?: $this->redirect_uri;
        
        $post_data = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $redirect_uri_to_use,
            'grant_type' => 'authorization_code',
            'code' => $authorization_code
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            $error_detail = '';
            $is_invalid_grant = false;
            
            if ($response) {
                $error_response = json_decode($response, true);
                if ($error_response && isset($error_response['error'])) {
                    $error_detail = ': ' . $error_response['error'];
                    if (isset($error_response['error_description'])) {
                        $error_detail .= ' - ' . $error_response['error_description'];
                    }
                    $is_invalid_grant = ($error_response['error'] === 'invalid_grant');
                }
            }
            
            if ($is_invalid_grant) {
                throw new Exception('Authorization code has expired or was already used. Please try connecting to Google Calendar again.');
            } else {
                throw new Exception('Failed to exchange authorization code for tokens: HTTP ' . $http_code . $error_detail);
            }
        }
        
        $token_data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !isset($token_data['access_token'])) {
            throw new Exception('Invalid token response from Google: ' . ($response ?: 'Empty response'));
        }
        
        return $token_data;
    }
    
    public function refreshAccessToken($refresh_token) {
        $token_url = 'https://oauth2.googleapis.com/token';
        
        $post_data = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('Failed to refresh access token: HTTP ' . $http_code);
        }
        
        $token_data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !isset($token_data['access_token'])) {
            throw new Exception('Invalid refresh token response from Google');
        }
        
        return $token_data;
    }
    
    public function getUserCalendars($access_token) {
        $url = 'https://www.googleapis.com/calendar/v3/users/me/calendarList';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('Failed to get user calendars: HTTP ' . $http_code);
        }
        
        $calendar_data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid calendar response from Google');
        }
        
        return $calendar_data['items'] ?? [];
    }
    
    public function getClientId() {
        return $this->client_id;
    }
    
    public function getClientSecret() {
        return $this->client_secret;
    }
    
    public function getRedirectUri() {
        return $this->redirect_uri;
    }
}
?> 