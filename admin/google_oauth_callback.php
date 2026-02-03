<?php
// Google OAuth Callback Handler
// This file handles the callback from Google OAuth and processes the authorization code

// Enable error reporting only in debug mode
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Start session but don't require authentication for OAuth callback
session_start();

// Include files with error handling
try {
    require_once '../includes/db.php';
} catch (Exception $e) {
    error_log('Database connection failed in OAuth callback: ' . $e->getMessage());
    die('Database connection error. Please contact administrator.');
}

// Include logger with proper fallback
$logger_available = false;
if (file_exists('../includes/logger.php')) {
    try {
        require_once '../includes/logger.php';
        $logger_available = class_exists('Logger');
    } catch (Throwable $e) {
        error_log('Failed to include logger.php: ' . $e->getMessage());
        $logger_available = false;
    }
}

// Create fallback Logger class if the real one isn't available
if (!$logger_available && !class_exists('Logger')) {
    class Logger {
        public function log($message, $category = 'general') {
            error_log("[$category] $message");
        }
    }
}

require_once 'google_oauth_config.php';

// Debug mode - add ?debug=1 to enable
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';

if ($debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    echo "<!DOCTYPE html><html><head><title>Google Calendar OAuth</title></head><body>";
    echo "<h2>Google Calendar OAuth Processing...</h2>";
}

// Add debug logging
error_log('OAuth Callback accessed with parameters: ' . print_r($_GET, true));

if ($debug_mode) {
    echo "<h3>Request Parameters:</h3>";
    echo "<pre>" . print_r($_GET, true) . "</pre>";
}

try {
    // Check for authorization code
    if (!isset($_GET['code'])) {
        throw new Exception('Authorization code not received');
    }
    
    // Check for state parameter
    if (!isset($_GET['state'])) {
        throw new Exception('State parameter missing');
    }
    
    // Parse state to get specialist_id
    $state_parts = explode('|', $_GET['state']);
    if (count($state_parts) !== 2) {
        throw new Exception('Invalid state parameter');
    }
    
    $state_token = $state_parts[0];
    $specialist_id = (int)$state_parts[1];
    
    // Verify state token exists in database and is recent (within last hour)
    $stmt = $pdo->prepare("SELECT specialist_name FROM google_calendar_credentials WHERE specialist_id = ? AND oauth_state = ? AND status = 'pending' AND updated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute([$specialist_id, $state_token]);
    $credential_row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$credential_row) {
        throw new Exception('Invalid or expired state token');
    }
    
    // Check if this is a calendar selection POST (second step)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_calendar'])) {
        // This is the calendar selection step - get stored token data
        $stmt = $pdo->prepare("SELECT access_token, refresh_token, token_expires_at FROM google_calendar_credentials WHERE specialist_id = ? AND oauth_state = ? AND status = 'pending'");
        $stmt->execute([$specialist_id, $state_token]);
        $stored_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stored_data || !$stored_data['access_token']) {
            throw new Exception('Token data not found. Please try the connection process again.');
        }
        
        $token_data = [
            'access_token' => $stored_data['access_token'],
            'refresh_token' => $stored_data['refresh_token']
        ];
        $expires_at = strtotime($stored_data['token_expires_at']);
        
        if ($debug_mode) {
            echo "<h3>Calendar Selection Step:</h3>";
            echo "Using stored token data...<br>";
        }
        
    } else {
        // This is the first step - exchange authorization code for tokens
        $oauth = new GoogleOAuthConfig();
        
        if ($debug_mode) {
            echo "<h3>OAuth Configuration:</h3>";
            echo "Client ID: " . substr($oauth->getClientId(), 0, 20) . "...<br>";
            echo "Configured Redirect URI: " . $oauth->getRedirectUri() . "<br>";
        }
        
        // Determine the actual redirect URI that was used to get this code
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $domain = $_SERVER['HTTP_HOST'];
        $script_path = $_SERVER['SCRIPT_NAME'];
        $actual_redirect_uri = $protocol . $domain . $script_path;
        
        if ($debug_mode) {
            echo "Actual Redirect URI: " . $actual_redirect_uri . "<br>";
            echo "<h3>Token Exchange:</h3>";
            echo "Attempting to exchange authorization code for tokens...<br>";
        }
        
        error_log('Using actual redirect URI for token exchange: ' . $actual_redirect_uri);
        
        $token_data = $oauth->exchangeCodeForTokens($_GET['code'], $actual_redirect_uri);
        $expires_at = isset($token_data['expires_in']) ? time() + $token_data['expires_in'] : time() + 3600;
        
        if ($debug_mode) {
            echo "✓ Token exchange successful!<br>";
            echo "Access token received: " . substr($token_data['access_token'], 0, 20) . "...<br>";
        }
        
        // Store token data temporarily for calendar selection
        $stmt = $pdo->prepare("UPDATE google_calendar_credentials SET access_token = ?, refresh_token = ?, token_expires_at = FROM_UNIXTIME(?), updated_at = NOW() WHERE specialist_id = ? AND oauth_state = ?");
        $stmt->execute([
            $token_data['access_token'],
            $token_data['refresh_token'] ?? null,
            $expires_at,
            $specialist_id,
            $state_token
        ]);
    }
    
    // Get user's calendars
    if ($debug_mode) {
        echo "<h3>Calendar Discovery:</h3>";
        echo "Fetching user's calendars...<br>";
    }
    
    // Make sure we have the OAuth object
    if (!isset($oauth)) {
        $oauth = new GoogleOAuthConfig();
    }
    
    $calendars = $oauth->getUserCalendars($token_data['access_token']);
    
    if ($debug_mode) {
        echo "Found " . count($calendars) . " calendars<br>";
    }
    
    // If user has multiple calendars, let them choose
    if (count($calendars) > 1 && !isset($_POST['selected_calendar'])) {
        // Show calendar selection page
        if ($debug_mode) {
            echo "<h3>Multiple Calendars Found - Please Choose:</h3>";
            echo "<form method='POST' action=''>";
            echo "<input type='hidden' name='code' value='" . htmlspecialchars($_GET['code']) . "'>";
            echo "<input type='hidden' name='state' value='" . htmlspecialchars($_GET['state']) . "'>";
            echo "<div style='margin: 20px 0;'>";
            
            foreach ($calendars as $calendar) {
                $is_primary = isset($calendar['primary']) && $calendar['primary'];
                $summary = $calendar['summary'] ?? $calendar['id'];
                $access_role = $calendar['accessRole'] ?? 'unknown';
                
                echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px;'>";
                echo "<label style='cursor: pointer; display: block;'>";
                echo "<input type='radio' name='selected_calendar' value='" . htmlspecialchars($calendar['id']) . "'" . ($is_primary ? ' checked' : '') . " style='margin-right: 10px;'>";
                echo "<strong>" . htmlspecialchars($summary) . "</strong>";
                if ($is_primary) echo " <span style='color: #28a745;'>(Primary)</span>";
                echo "<br><small style='color: #666;'>ID: " . htmlspecialchars($calendar['id']) . " | Access: " . htmlspecialchars($access_role) . "</small>";
                echo "</label>";
                echo "</div>";
            }
            
            echo "</div>";
            echo "<button type='submit' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Use Selected Calendar</button>";
            echo "</form>";
            echo "</body></html>";
            exit;
                 } else {
             // In production, show calendar selection inline
             echo "<!DOCTYPE html><html><head><title>Select Google Calendar</title>";
             echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
             echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>";
             echo "</head><body class='bg-light'>";
             echo "<div class='container mt-5'><div class='row justify-content-center'><div class='col-md-8'>";
             echo "<div class='card shadow'><div class='card-header bg-primary text-white'>";
             echo "<h4 class='mb-0'><i class='fas fa-calendar'></i> Select Google Calendar</h4></div>";
             echo "<div class='card-body'>";
             echo "<p class='text-muted'>We found multiple calendars in your Google account. Please select which calendar you want to sync your bookings with:</p>";
             echo "<form method='POST' action=''>";
             echo "<input type='hidden' name='code' value='" . htmlspecialchars($_GET['code']) . "'>";
             echo "<input type='hidden' name='state' value='" . htmlspecialchars($_GET['state']) . "'>";
             
             foreach ($calendars as $calendar) {
                 $is_primary = isset($calendar['primary']) && $calendar['primary'];
                 $summary = $calendar['summary'] ?? $calendar['id'];
                 $access_role = $calendar['accessRole'] ?? 'unknown';
                 
                 echo "<div class='card mb-3' style='cursor: pointer; transition: all 0.2s ease;' onclick='document.getElementById(\"cal_" . htmlspecialchars($calendar['id']) . "\").checked = true;'>";
                 echo "<div class='card-body'>";
                 echo "<div class='form-check'>";
                 echo "<input class='form-check-input' type='radio' name='selected_calendar' value='" . htmlspecialchars($calendar['id']) . "' id='cal_" . htmlspecialchars($calendar['id']) . "'" . ($is_primary ? ' checked' : '') . ">";
                 echo "<label class='form-check-label w-100' for='cal_" . htmlspecialchars($calendar['id']) . "'>";
                 echo "<h6 class='mb-1'>" . htmlspecialchars($summary);
                 if ($is_primary) echo " <span class='badge bg-success'>Primary</span>";
                 echo "</h6>";
                 echo "<small class='text-muted'>Access: " . htmlspecialchars(ucfirst($access_role)) . " | ID: " . htmlspecialchars($calendar['id']) . "</small>";
                 echo "</label></div></div></div>";
             }
             
             echo "<div class='d-flex justify-content-between'>";
             echo "<a href='../booking_specialist_view.php?specialist_id={$specialist_id}' class='btn btn-secondary'><i class='fas fa-arrow-left'></i> Cancel</a>";
             echo "<button type='submit' class='btn btn-primary' id='submitBtn'><i class='fas fa-check'></i> Use Selected Calendar</button>";
             echo "</div></form></div></div></div></div></div>";
             
             // Add JavaScript for better UX
             echo "<script>";
             echo "document.querySelectorAll('input[name=\"selected_calendar\"]').forEach(radio => {";
             echo "  radio.addEventListener('change', function() {";
             echo "    document.getElementById('submitBtn').style.background = '#28a745';";
             echo "    document.getElementById('submitBtn').innerHTML = '<i class=\"fas fa-check\"></i> Connecting...';";
             echo "    setTimeout(() => document.querySelector('form').submit(), 500);";
             echo "  });";
             echo "});";
             echo "</script>";
             echo "</body></html>";
             exit;
         }
    }
    
    // Select the calendar (either the only one, or the user's choice, or primary as fallback)
    $selected_calendar = null;
    
    if (isset($_POST['selected_calendar'])) {
        // User made a choice
        $selected_calendar_id = $_POST['selected_calendar'];
        foreach ($calendars as $calendar) {
            if ($calendar['id'] === $selected_calendar_id) {
                $selected_calendar = $calendar;
                break;
            }
        }
    } elseif (count($calendars) === 1) {
        // Only one calendar available
        $selected_calendar = $calendars[0];
    } else {
        // Find primary calendar as fallback
        foreach ($calendars as $calendar) {
            if (isset($calendar['primary']) && $calendar['primary'] === true) {
                $selected_calendar = $calendar;
                break;
            }
        }
    }
    
    if (!$selected_calendar) {
        throw new Exception('Could not determine which calendar to use');
    }
    
    if ($debug_mode) {
        echo "Selected calendar: " . ($selected_calendar['summary'] ?? 'Calendar') . "<br>";
        echo "Calendar ID: " . $selected_calendar['id'] . "<br>";
    }
    
    // Store credentials in database
    $stmt = $pdo->prepare("
        UPDATE google_calendar_credentials 
        SET access_token = ?, 
            refresh_token = ?, 
            calendar_id = ?, 
            calendar_name = ?,
            expires_at = FROM_UNIXTIME(?), 
            status = 'active',
            oauth_state = NULL,
            updated_at = NOW()
        WHERE specialist_id = ? AND oauth_state = ?
    ");
    
    $stmt->execute([
        $token_data['access_token'],
        $token_data['refresh_token'] ?? null,
        $selected_calendar['id'],
        $selected_calendar['summary'] ?? 'Selected Calendar',
        $expires_at,
        $specialist_id,
        $state_token
    ]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Failed to update credentials');
    }
    
    // Log successful connection
    $logger = new Logger();
    $logger->log("Google Calendar connected successfully for specialist {$credential_row['specialist_name']} (ID: {$specialist_id})", 'google_calendar');
    
    if ($debug_mode) {
        echo "<h3>Success!</h3>";
        echo "✓ Credentials stored successfully for specialist: {$credential_row['specialist_name']}<br>";
        echo "✓ Calendar connected: {$selected_calendar['summary']}<br>";
        echo "<p>Redirecting back to booking page in 2 seconds...</p>";
        echo "<p><a href='../booking_specialist_view.php?specialist_id={$specialist_id}&gcal_success=1' style='background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Continue to Booking Page (Manual)</a></p>";
        echo "<script>setTimeout(function(){ window.location.href = '../booking_specialist_view.php?specialist_id={$specialist_id}&gcal_success=1&refresh=1'; }, 2000);</script>";
        echo "</body></html>";
        exit;
    }
    
    // Redirect back to booking page with success message
    $redirect_url = '../booking_specialist_view.php?specialist_id=' . $specialist_id . '&gcal_success=1&refresh=1';
    header('Location: ' . $redirect_url);
    exit;
    
} catch (Exception $e) {
    error_log('Google OAuth Callback Error: ' . $e->getMessage());
    
    if ($debug_mode) {
        echo "<h3>Error!</h3>";
        echo "<div style='color: red; background: #ffe6e6; padding: 10px; border: 1px solid #ff0000;'>";
        echo "✗ OAuth Error: " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "</div>";
        echo "<p><a href='../booking_specialist_view.php" . (isset($specialist_id) ? "?specialist_id=$specialist_id" : "") . "'>Back to Booking Page</a></p>";
        echo "</body></html>";
        exit;
    }
    
    // Redirect back with error message
    $error_msg = urlencode($e->getMessage());
    $redirect_url = '../booking_specialist_view.php?gcal_error=' . $error_msg;
    
    if (isset($specialist_id)) {
        $redirect_url .= '&specialist_id=' . $specialist_id;
    }
    
    header('Location: ' . $redirect_url);
    exit;
}
?> 