<?php
error_reporting(0);
ini_set('display_errors', 0);

// Custom error handler to catch all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "PHP Error: $errstr in $errfile on line $errline"
    ]);
    exit;
});

// Start output buffering
ob_start();

try {
    session_start();
    require_once '../includes/db.php';
    require_once '../includes/google_calendar_sync.php';
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error loading required files: ' . $e->getMessage()
    ]);
    exit;
}

// Clear any output that might have been generated
ob_clean();

// Check session - must be logged in
if (!isset($_SESSION['user'])) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated. Please log in.'
    ]);
    ob_end_flush();
    exit;
}

header('Content-Type: application/json');

/**
 * Extract phone number using enhanced logic
 */
function extractPhoneNumber($text) {
    // First attempt: Look for labeled phone numbers
    if (preg_match('/(?:phone|tel|telefon|mobile|cell)[\s:]*([+\d\s\-()]+)/i', $text, $matches)) {
        $phone = preg_replace('/[^0-9+]/', '', $matches[1]);
        if (strlen($phone) >= 9) {
            return $phone;
        }
    }
    
    // Second attempt: Split words and look for 9+ consecutive digits
    $words = preg_split('/\s+/', $text);
    foreach ($words as $word) {
        // Remove dots but keep other characters
        $word_cleaned = str_replace('.', '', $word);
        // Extract just digits
        $digits = preg_replace('/[^0-9]/', '', $word_cleaned);
        if (strlen($digits) >= 9) {
            return $digits;
        }
    }
    
    // Third attempt: Remove dashes and check again
    foreach ($words as $word) {
        // Remove dots and dashes
        $word_cleaned = str_replace(['.', '-'], '', $word);
        // Extract just digits
        $digits = preg_replace('/[^0-9]/', '', $word_cleaned);
        if (strlen($digits) >= 9) {
            return $digits;
        }
    }
    
    // Fallback
    return '0000000000';
}

try {
    $data = $_POST ?? [];
    $specialist_id = (int)($data['specialist_id'] ?? 0);
    $from_date = $data['from_date'] ?? date('Y-m-d');
    $to_date = $data['to_date'] ?? date('Y-m-d', strtotime('+30 days'));
    $service_mappings = $data['service_mappings'] ?? [];
    $preview_only = isset($data['preview_only']) && $data['preview_only'] === 'true';
    
    if (!$specialist_id) {
        throw new Exception('Specialist ID is required');
    }
    
    // Get Google Calendar connection
    try {
        $gc_conn = get_google_calendar_connection($pdo, $specialist_id);
        if (!$gc_conn) {
            throw new Exception('No Google Calendar connection found for specialist ID: ' . $specialist_id);
        }
        if ($gc_conn['status'] !== 'active') {
            throw new Exception('Google Calendar connection is not active. Status: ' . $gc_conn['status']);
        }
        if (!isset($gc_conn['access_token'])) {
            throw new Exception('No access token found in Google Calendar connection');
        }
    } catch (Exception $e) {
        throw new Exception('Error getting Google Calendar connection: ' . $e->getMessage());
    }
    
    // Get specialist info
    $stmt = $pdo->prepare("SELECT * FROM specialists WHERE unic_id = ?");
    $stmt->execute([$specialist_id]);
    $specialist = $stmt->fetch();
    
    if (!$specialist) {
        throw new Exception('Specialist not found');
    }
    
    // Get default service for imported bookings (specialist-specific services)
    $stmt = $pdo->prepare("
        SELECT s.* FROM services s
        WHERE s.id_specialist = ?
        ORDER BY s.unic_id ASC
        LIMIT 1
    ");
    $stmt->execute([$specialist_id]);
    $default_service = $stmt->fetch();
    
    if (!$default_service) {
        throw new Exception('No services found for this specialist. Please add services first.');
    }
    
    // Get default workplace
    $stmt = $pdo->prepare("
        SELECT wp.* FROM working_points wp
        INNER JOIN working_program wpr ON wp.unic_id = wpr.working_place_id
        WHERE wpr.specialist_id = ?
        ORDER BY wp.unic_id ASC
        LIMIT 1
    ");
    $stmt->execute([$specialist_id]);
    $default_workplace = $stmt->fetch();
    
    if (!$default_workplace) {
        throw new Exception('No workplace found for this specialist');
    }
    
    // Import events from Google Calendar using direct API calls (same as working sync)
    try {
        // Build the URL for listing events
        $calendar_id = $gc_conn['calendar_id'] ?? 'primary';
        $url = "https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendar_id) . "/events";
        
        // Set up time parameters
        $timeMin = new DateTime($from_date . ' 00:00:00');
        $timeMax = new DateTime($to_date . ' 23:59:59');
        
        // Build query parameters
        $params = [
            'timeMin' => $timeMin->format(DateTime::RFC3339),
            'timeMax' => $timeMax->format(DateTime::RFC3339),
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
            'maxResults' => 100
        ];
        
        $url .= '?' . http_build_query($params);
        
        // Make API request using cURL (same approach as working sync)
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $gc_conn['access_token'],
                'Accept: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('cURL Error: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            $error_data = json_decode($response, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : "HTTP Error $httpCode";
            throw new Exception('Google Calendar API error: ' . $error_message);
        }
        
        $events_data = json_decode($response, true);
        if (!isset($events_data['items'])) {
            throw new Exception('Invalid response from Google Calendar API');
        }
        
        $events = $events_data['items'];
        
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $event_details = []; // Store details about each event found
        
        foreach ($events as $event) {
            try {
                // Store event info for debugging
                $event_info = [
                    'id' => $event['id'] ?? 'no-id',
                    'summary' => $event['summary'] ?? 'No Title',
                    'start' => $event['start']['dateTime'] ?? $event['start']['date'] ?? 'no-start',
                    'end' => $event['end']['dateTime'] ?? $event['end']['date'] ?? 'no-end',
                    'all_day' => !isset($event['start']['dateTime']),
                    'status' => 'processing'
                ];
                
                // Skip all-day events
                if (!isset($event['start']['dateTime'])) {
                    $event_info['status'] = 'skipped';
                    $event_info['reason'] = 'all-day event';
                    $event_details[] = $event_info;
                    $skipped++;
                    continue;
                }
                
                // Extract event details
                $event_title = $event['summary'] ?? 'No Title';
                $event_description = $event['description'] ?? '';
                $event_location = $event['location'] ?? '';
                
                // Combine all text for phone extraction
                $all_text = $event_title . ' ' . $event_description . ' ' . $event_location;
                
                // Extract phone number
                $phone_number = extractPhoneNumber($all_text);
                $event_info['phone_extracted'] = $phone_number;
                
                // Store original client name
                $client_name = $event_title;
                if (preg_match('/(?:client|customer|name)[:\s]*([^,\n]+)/i', $event_description, $matches)) {
                    $client_name = trim($matches[1]);
                }
                
                // Determine service based on mapping and extract client name
                $service_id = $default_service['unic_id'];
                $event_text_lower = strtolower($all_text);
                $found_keyword = null;
                
                foreach ($service_mappings as $mapping) {
                    if (!empty($mapping['keywords']) && !empty($mapping['service_id'])) {
                        $keywords = array_map('trim', explode(',', $mapping['keywords']));
                        foreach ($keywords as $keyword) {
                            if (!empty($keyword) && stripos($event_text_lower, strtolower($keyword)) !== false) {
                                $service_id = $mapping['service_id'];
                                $found_keyword = $keyword;
                                break 2;
                            }
                        }
                    }
                }
                
                // Get service details
                $stmt = $pdo->prepare("SELECT * FROM services WHERE unic_id = ?");
                $stmt->execute([$service_id]);
                $service = $stmt->fetch();
                
                if (!$service) {
                    $service = $default_service;
                }
                
                // If we found a keyword, remove it from the client name
                if ($found_keyword) {
                    // Remove the keyword (case-insensitive) from the client name
                    $client_name_cleaned = preg_replace('/\b' . preg_quote($found_keyword, '/') . '\b/i', '', $client_name);
                    // Also check if service name itself appears in the title
                    $client_name_cleaned = preg_replace('/\b' . preg_quote($service['name_of_service'], '/') . '\b/i', '', $client_name_cleaned);
                    // Clean up extra spaces and trim
                    $client_name_cleaned = trim(preg_replace('/\s+/', ' ', $client_name_cleaned));
                    
                    // Only use cleaned name if it's not empty
                    if (!empty($client_name_cleaned)) {
                        $client_name = $client_name_cleaned;
                    }
                }
                
                $event_info['client_name'] = $client_name;
                $event_info['service_matched'] = $service['name_of_service'];
                
                // Parse event times
                $start_datetime = new DateTime($event['start']['dateTime']);
                $end_datetime = new DateTime($event['end']['dateTime']);
                
                // Check if booking already exists (to prevent duplicates)
                $stmt = $pdo->prepare("
                    SELECT unic_id FROM booking 
                    WHERE id_specialist = ? 
                    AND booking_start_datetime = ? 
                    AND client_full_name = ?
                ");
                $stmt->execute([
                    $specialist_id,
                    $start_datetime->format('Y-m-d H:i:s'),
                    $client_name
                ]);
                
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $event_info['status'] = 'skipped';
                    $event_info['reason'] = 'already exists in database';
                    $event_details[] = $event_info;
                    $skipped++;
                    continue;
                }
                
                // If preview only, mark as "will import" and skip actual insertion
                if ($preview_only) {
                    $event_info['status'] = 'will_import';
                    $event_details[] = $event_info;
                    $imported++;
                    continue;
                }
                
                // Create booking (only if not preview)
                $stmt = $pdo->prepare("
                    INSERT INTO booking (
                        id_specialist,
                        service_id,
                        id_work_place,
                        client_full_name,
                        client_phone_nr,
                        booking_start_datetime,
                        booking_end_datetime,
                        received_through,
                        day_of_creation,
                        google_event_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $specialist_id,
                    $service['unic_id'],
                    $default_workplace['unic_id'],
                    $client_name,
                    $phone_number,
                    $start_datetime->format('Y-m-d H:i:s'),
                    $end_datetime->format('Y-m-d H:i:s'),
                    'GoogleCal_Import',
                    date('Y-m-d H:i:s'),
                    $event['id'] ?? null
                ]);
                
                $event_info['status'] = 'imported';
                $event_info['booking_id'] = $pdo->lastInsertId();
                $event_details[] = $event_info;
                $imported++;
                
            } catch (Exception $e) {
                $event_info['status'] = 'error';
                $event_info['error'] = $e->getMessage();
                $event_details[] = $event_info;
                $errors[] = "Failed to import event '" . ($event['summary'] ?? 'Unknown') . "': " . $e->getMessage();
            }
        }
        
        if ($preview_only) {
            $message = "Preview: $imported events will be imported";
            if ($skipped > 0) {
                $message .= ", $skipped events will be skipped";
            }
        } else {
            $message = "Import completed: $imported events imported";
            if ($skipped > 0) {
                $message .= ", $skipped events skipped";
            }
        }
        if (!empty($errors)) {
            $message .= ". Some errors occurred.";
        }
        
        ob_clean(); // Clear any output before sending JSON
        echo json_encode([
            'success' => true,
            'preview_mode' => $preview_only,
            'message' => $message,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'debug_info' => [
                'calendar_id' => $calendar_id,
                'date_range' => [
                    'from' => $from_date,
                    'to' => $to_date,
                    'from_formatted' => $timeMin->format(DateTime::RFC3339),
                    'to_formatted' => $timeMax->format(DateTime::RFC3339)
                ],
                'total_events_found' => count($events),
                'events' => $event_details,
                'service_mappings' => $service_mappings
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch events from Google Calendar: ' . $e->getMessage());
    }
    
} catch (Exception $e) {
    error_log("Google Calendar import error: " . $e->getMessage());
    ob_clean(); // Clear any output before sending JSON
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    ob_end_flush();
    exit;
}

// Ensure clean exit
ob_end_flush();
?>