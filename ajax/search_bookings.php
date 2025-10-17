<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Wrap everything in try-catch to catch any errors
try {
    session_start();
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/timezone_config.php';

    // Check if user is logged in
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $mode = $_GET['mode'] ?? '';
    $search = $_GET['search'] ?? '';
    $response = ['bookings' => []];
    $specialist_id = null;
    $workpoint_id = null;

// Validate search term
if (strlen($search) < 2) {
    echo json_encode($response);
    exit;
}

// Auto-detect if search contains numbers (for ID search)
$searchById = false;
if (preg_match('/^\d+$/', trim($search))) {
    // Only numbers - search by ID
    $searchById = true;
    $searchId = trim($search);
} else {
    // Contains text - prepare for name search with fuzzy matching
    $searchTerm = '%' . $search . '%';
}

try {
    if ($mode === 'supervisor') {
        $workpoint_id = $_GET['workpoint_id'] ?? 0;
        
        // Get working point for timezone
        $wpStmt = $pdo->prepare("SELECT * FROM working_points WHERE unic_id = ?");
        $wpStmt->execute([$workpoint_id]);
        $workpoint = $wpStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get current time in workpoint timezone
        $currentTime = $workpoint ? getCurrentTimeInWorkingPointTimezoneOnly($workpoint) : date('Y-m-d H:i:s');
        
        if ($searchById) {
            // Search by booking ID in booking table first
            $stmt = $pdo->prepare("
                SELECT 
                    b.*, 
                    s.name_of_service as service_name,
                    sp.name as specialist_name,
                    sp.speciality as specialist_speciality,
                    sa.back_color as specialist_color,
                    sa.foreground_color as specialist_fg_color,
                    DATE_FORMAT(b.booking_start_datetime, '%Y-%m-%d') as booking_date,
                    NULL as cancellation_time,
                    NULL as canceled_by,
                    'active' as booking_status,
                    CASE 
                        WHEN b.booking_start_datetime < ? THEN 'past'
                        ELSE 'future'
                    END as time_status,
                    CASE 
                        WHEN b.day_of_creation IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, b.day_of_creation, NOW()) / 60.0
                        ELSE TIMESTAMPDIFF(MINUTE, b.booking_start_datetime, NOW()) / 60.0
                    END as hours_since_creation
                FROM booking b
                LEFT JOIN services s ON b.service_id = s.unic_id
                LEFT JOIN specialists sp ON b.id_specialist = sp.unic_id
                LEFT JOIN specialists_setting_and_attr sa ON sp.unic_id = sa.specialist_id
                WHERE b.id_work_place = ?
                AND b.unic_id = ?
            ");
            $stmt->execute([$currentTime, $workpoint_id, $searchId]);
            $activeBooking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If not found in booking table, check booking_canceled
            if (!$activeBooking) {
                $stmt = $pdo->prepare("
                    SELECT 
                        bc.*, 
                        s.name_of_service as service_name,
                        sp.name as specialist_name,
                        sp.speciality as specialist_speciality,
                        sa.back_color as specialist_color,
                        sa.foreground_color as specialist_fg_color,
                        DATE_FORMAT(bc.booking_start_datetime, '%Y-%m-%d') as booking_date,
                        bc.cancellation_time,
                        bc.made_by as canceled_by,
                        'canceled' as booking_status,
                        'past' as time_status
                    FROM booking_canceled bc
                    LEFT JOIN services s ON bc.service_id = s.unic_id
                    LEFT JOIN specialists sp ON bc.id_specialist = sp.unic_id
                    LEFT JOIN specialists_setting_and_attr sa ON sp.unic_id = sa.specialist_id
                    WHERE bc.id_work_place = ?
                    AND bc.unic_id = ?
                ");
                $stmt->execute([$workpoint_id, $searchId]);
                $canceledBooking = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $bookings = $canceledBooking ? [$canceledBooking] : [];
            } else {
                $bookings = [$activeBooking];
            }
        } else {
            // Search by name using fuzzy matching logic from find_booking.php
            $searchWords = array_filter(explode(' ', strtolower(trim($search))));
            
            // First get all bookings from workpoint
            $stmt = $pdo->prepare("
                SELECT 
                    b.*, 
                    s.name_of_service as service_name,
                    sp.name as specialist_name,
                    sp.speciality as specialist_speciality,
                    sa.back_color as specialist_color,
                    sa.foreground_color as specialist_fg_color,
                    DATE_FORMAT(b.booking_start_datetime, '%Y-%m-%d') as booking_date,
                    CASE 
                        WHEN b.booking_start_datetime < ? THEN 'past'
                        ELSE 'future'
                    END as time_status,
                    CASE 
                        WHEN b.day_of_creation IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, b.day_of_creation, NOW()) / 60.0
                        ELSE TIMESTAMPDIFF(MINUTE, b.booking_start_datetime, NOW()) / 60.0
                    END as hours_since_creation
                FROM booking b
                LEFT JOIN services s ON b.service_id = s.unic_id
                LEFT JOIN specialists sp ON b.id_specialist = sp.unic_id
                LEFT JOIN specialists_setting_and_attr sa ON sp.unic_id = sa.specialist_id
                WHERE b.id_work_place = ?
                AND b.client_full_name LIKE ?
                ORDER BY COALESCE(b.day_of_creation, b.booking_start_datetime) DESC
                LIMIT 100
            ");
            $stmt->execute([$currentTime, $workpoint_id, $searchTerm]);
        }
        
    } else if ($mode === 'specialist') {
        $specialist_id = $_GET['specialist_id'] ?? 0;
        
        // Ensure specialist_id is not empty
        if (empty($specialist_id)) {
            $response['error'] = 'No specialist ID provided';
            echo json_encode($response);
            exit;
        }
        
        
        if ($searchById) {
            // Search by booking ID in booking table first
            $stmt = $pdo->prepare("
                SELECT 
                    b.*, 
                    s.name_of_service as service_name,
                    DATE_FORMAT(b.booking_start_datetime, '%Y-%m-%d') as booking_date,
                    NULL as cancellation_time,
                    NULL as canceled_by,
                    'active' as booking_status,
                    CASE 
                        WHEN b.booking_start_datetime < NOW() THEN 'past'
                        ELSE 'future'
                    END as time_status,
                    CASE 
                        WHEN b.day_of_creation IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, b.day_of_creation, NOW()) / 60.0
                        ELSE TIMESTAMPDIFF(MINUTE, b.booking_start_datetime, NOW()) / 60.0
                    END as hours_since_creation
                FROM booking b
                LEFT JOIN services s ON b.service_id = s.unic_id
                WHERE b.id_specialist = ?
                AND b.unic_id = ?
            ");
            $stmt->execute([$specialist_id, $searchId]);
            $activeBooking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If not found in booking table, check booking_canceled
            if (!$activeBooking) {
                $stmt = $pdo->prepare("
                    SELECT 
                        bc.*, 
                        s.name_of_service as service_name,
                        DATE_FORMAT(bc.booking_start_datetime, '%Y-%m-%d') as booking_date,
                        bc.cancellation_time,
                        bc.made_by as canceled_by,
                        'canceled' as booking_status,
                        'past' as time_status
                    FROM booking_canceled bc
                    LEFT JOIN services s ON bc.service_id = s.unic_id
                    WHERE bc.id_specialist = ?
                    AND bc.unic_id = ?
                ");
                $stmt->execute([$specialist_id, $searchId]);
                $canceledBooking = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $bookings = $canceledBooking ? [$canceledBooking] : [];
            } else {
                $bookings = [$activeBooking];
            }
        } else {
            // Search by name
            $stmt = $pdo->prepare("
                SELECT 
                    b.*, 
                    s.name_of_service as service_name,
                    DATE_FORMAT(b.booking_start_datetime, '%Y-%m-%d') as booking_date,
                    CASE 
                        WHEN b.booking_start_datetime < NOW() THEN 'past'
                        ELSE 'future'
                    END as time_status,
                    CASE 
                        WHEN b.day_of_creation IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, b.day_of_creation, NOW()) / 60.0
                        ELSE TIMESTAMPDIFF(MINUTE, b.booking_start_datetime, NOW()) / 60.0
                    END as hours_since_creation
                FROM booking b
                LEFT JOIN services s ON b.service_id = s.unic_id
                WHERE b.id_specialist = ?
                AND b.client_full_name LIKE ?
                ORDER BY COALESCE(b.day_of_creation, b.booking_start_datetime) DESC
                LIMIT 100
            ");
            $stmt->execute([$specialist_id, $searchTerm]);
        }
    } else {
        // No valid mode provided
        $response['error'] = 'Invalid mode provided';
        echo json_encode($response);
        exit;
    }
    
    // For non-ID searches, fetch the results
    if (!$searchById) {
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Process bookings to add cancellation info
    foreach ($bookings as &$booking) {
        if (isset($booking['booking_status']) && $booking['booking_status'] === 'canceled') {
            // Calculate hours since cancellation
            if ($booking['cancellation_time']) {
                $cancellationDate = new DateTime($booking['cancellation_time']);
                $now = new DateTime();
                $diff = $now->diff($cancellationDate);
                $hours = ($diff->days * 24) + $diff->h + ($diff->i / 60);
                $booking['hours_since_creation'] = $hours;
                $booking['hours_since_cancellation'] = $hours;
            }
            // Add canceled status text
            $booking['booking_status_text'] = 'Canceled' . ($booking['canceled_by'] ? ' by ' . $booking['canceled_by'] : '');
        }
    }
    
    // If searching by name, apply fuzzy matching
    if (!$searchById && !empty($bookings)) {
        $searchWords = array_filter(explode(' ', strtolower(trim($search))));
        $scoredBookings = [];
        
        // Add debug info
        $response['debug'] = [
            'total_found' => count($bookings),
            'search_words' => $searchWords,
            'mode' => $mode,
            'specialist_id' => isset($specialist_id) ? $specialist_id : 'not set',
            'workpoint_id' => isset($workpoint_id) ? $workpoint_id : 'not set'
        ];
        
        foreach ($bookings as $booking) {
            $clientName = strtolower(trim($booking['client_full_name']));
            $clientNameWords = array_filter(explode(' ', $clientName));
            
            $matchScore = calculateNameMatchScore($searchWords, $clientNameWords);
            
            if ($matchScore >= 3) { // Only include matches with score 3 or higher
                $booking['match_score'] = $matchScore;
                $scoredBookings[] = $booking;
            }
        }
        
        // Sort by match score (highest first) and then by date (newest first)
        usort($scoredBookings, function($a, $b) {
            if ($a['match_score'] !== $b['match_score']) {
                return $b['match_score'] - $a['match_score'];
            }
            return strtotime($b['booking_start_datetime']) - strtotime($a['booking_start_datetime']);
        });
        
        $response['bookings'] = array_slice($scoredBookings, 0, 50); // Limit to top 50 matches
    } else {
        $response['bookings'] = $bookings;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    $response['error'] = 'Database error: ' . $e->getMessage();
    $response['trace'] = $e->getTraceAsString();
    error_log('Search error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
}

header('Content-Type: application/json');
echo json_encode($response);

} catch (Throwable $t) {
    // Catch any error including fatal errors
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Fatal error: ' . $t->getMessage(),
        'file' => $t->getFile(),
        'line' => $t->getLine(),
        'trace' => $t->getTraceAsString()
    ]);
    exit;
}

/**
 * Calculate name match score based on word matching
 * (Same logic as find_booking.php)
 */
function calculateNameMatchScore($incomingWords, $clientWords) {
    if (empty($incomingWords) || empty($clientWords)) {
        return 0;
    }
    
    $exactMatches = 0;
    $partialMatches = 0;
    $totalIncomingWords = count($incomingWords);
    
    foreach ($incomingWords as $incomingWord) {
        $incomingWord = trim($incomingWord);
        if (empty($incomingWord)) continue;
        
        $bestMatch = 0;
        
        foreach ($clientWords as $clientWord) {
            $clientWord = trim($clientWord);
            if (empty($clientWord)) continue;
            
            // Exact match
            if (strtolower($incomingWord) === strtolower($clientWord)) {
                $bestMatch = 2;
                break;
            }
            
            // Partial match
            if (strlen($incomingWord) >= 3 && strlen($clientWord) >= 3) {
                if (stripos($incomingWord, $clientWord) !== false || 
                    stripos($clientWord, $incomingWord) !== false) {
                    $bestMatch = max($bestMatch, 1);
                }
            }
            
            // Fuzzy match for diacritics and typos
            if (strlen($incomingWord) >= 3 && strlen($clientWord) >= 3) {
                similar_text(strtolower($incomingWord), strtolower($clientWord), $percent);
                if ($percent > 80) {
                    $bestMatch = max($bestMatch, 1);
                }
            }
        }
        
        if ($bestMatch === 2) {
            $exactMatches++;
        } elseif ($bestMatch === 1) {
            $partialMatches++;
        }
    }
    
    // Calculate score
    if ($exactMatches === $totalIncomingWords) {
        return 10;
    } elseif ($exactMatches > 0 || $partialMatches > 0) {
        if ($exactMatches >= $partialMatches) {
            return 6;
        } else {
            return 3;
        }
    }
    
    return 0;
}