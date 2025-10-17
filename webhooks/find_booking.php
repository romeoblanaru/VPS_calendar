<?php
/**
 * Find Booking Webhook
 * 
 * This webhook finds the last two most recent bookings for a specific client
 * based on their full name and phone number. It supports both POST and GET methods.
 * 
 * Parameters:
 * - full_name (required): Full name of the client
 * - caler_phone_nr (required): Phone number of the client
 * - booking_id (optional): If provided, search by this ID only (must be active)
 * 
 * Returns: JSON response with the last two most recent bookings including all details
 *          such as booking ID, service details, specialist info, workplace info, etc.
 *          Also includes a match_score indicating the quality of the name match.
 */

// Include required files
require_once '../includes/db.php';
require_once '../includes/webhook_logger.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize webhook logger
$logger = new WebhookLogger($pdo, 'find_booking');

try {
    // Get request data (support both GET and POST)
    $requestData = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Handle GET request - decode URL parameters
        $requestData = $_GET;
        
        // Decode URL-encoded parameters
        if (isset($requestData['full_name'])) {
            $requestData['full_name'] = urldecode($requestData['full_name']);
        }
        if (isset($requestData['caler_phone_nr'])) {
            $requestData['caler_phone_nr'] = urldecode($requestData['caler_phone_nr']);
        }
        if (isset($requestData['booking_id'])) {
            $requestData['booking_id'] = urldecode($requestData['booking_id']);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle POST request
        $requestData = $_POST;
        
        // If no POST data, try to get JSON input
        if (empty($requestData)) {
            $input = file_get_contents('php://input');
            if ($input) {
                $requestData = json_decode($input, true) ?: [];
            }
        }
    } else {
        throw new Exception("Only GET and POST methods are allowed for this webhook");
    }
    
    // Log the incoming request
    $logData = [
        'method' => $_SERVER['REQUEST_METHOD'],
        'parameters' => $requestData,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    // Check if no parameters were provided
    if (empty($requestData)) {
        throw new Exception("Missing required parameters: full_name, caler_phone_nr");
    }

    // If booking_id is provided, take the priority branch
    if (isset($requestData['booking_id']) && trim((string)$requestData['booking_id']) !== '') {
        $bookingId = trim((string)$requestData['booking_id']);
        if (!ctype_digit($bookingId)) {
            throw new Exception("Invalid booking_id. It must be a positive integer");
        }

        // Active means booking_start_datetime strictly greater than now
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            SELECT 
                b.unic_id as booking_id,
                b.id_specialist,
                b.id_work_place,
                b.day_of_creation,
                b.service_id,
                b.booking_start_datetime,
                b.booking_end_datetime,
                b.client_full_name,
                b.client_phone_nr,
                s.name as specialist_name,
                s.speciality as specialist_speciality,
                s.email as specialist_email,
                s.phone_nr as specialist_phone,
                wp.name_of_the_place as workplace_name,
                wp.address as workplace_address,
                wp.lead_person_name as workplace_lead_person,
                wp.lead_person_phone_nr as workplace_lead_phone,
                wp.workplace_phone_nr as workplace_phone,
                wp.booking_phone_nr as workplace_booking_phone,
                wp.email as workplace_email,
                sv.name_of_service as service_name,
                sv.duration as service_duration,
                sv.price_of_service as service_price,
                sv.procent_vat as service_vat
            FROM `booking` b
            LEFT JOIN `specialists` s ON b.id_specialist = s.unic_id
            LEFT JOIN `working_points` wp ON b.id_work_place = wp.unic_id
            LEFT JOIN `services` sv ON b.service_id = sv.unic_id
            WHERE b.unic_id = ? AND b.booking_start_datetime > ?
            ORDER BY b.booking_start_datetime DESC
        ");
        
        $stmt->execute([$bookingId, $now]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            throw new Exception("No active booking found with the provided booking_id");
        }

        $response = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'search_criteria' => [
                'booking_id' => (int)$bookingId,
                'active_only' => true
            ],
            'match_analysis' => [
                'mode' => 'by_booking_id',
                'total_phone_matches' => 0,
                'valid_name_matches' => 0,
                'highest_match_score' => null
            ],
            'bookings_found' => 1,
            'bookings' => []
        ];

        $bookingData = [
            'match_score' => 10,
            'booking_id' => $booking['booking_id'],
            'booking_details' => [
                'day_of_creation' => $booking['day_of_creation'],
                'booking_start_datetime' => $booking['booking_start_datetime'],
                'booking_end_datetime' => $booking['booking_end_datetime']
            ],
            'client_info' => [
                'full_name' => $booking['client_full_name'],
                'phone_number' => $booking['client_phone_nr']
            ],
            'specialist_info' => [
                'id' => $booking['id_specialist'] ?? 'unavailable',
                'name' => $booking['specialist_name'] ?? 'unavailable',
                'speciality' => $booking['specialist_speciality'] ?? 'unavailable',
                'email' => $booking['specialist_email'] ?? 'unavailable',
                'phone_number' => $booking['specialist_phone'] ?? 'unavailable'
            ],
            'workplace_info' => [
                'id' => $booking['id_work_place'] ?? 'unavailable',
                'name' => $booking['workplace_name'] ?? 'unavailable',
                'address' => $booking['workplace_address'] ?? 'unavailable',
                'lead_person_name' => $booking['workplace_lead_person'] ?? 'unavailable',
                'lead_person_phone' => $booking['workplace_lead_phone'] ?? 'unavailable',
                'workplace_phone' => $booking['workplace_phone'] ?? 'unavailable',
                'booking_phone' => $booking['workplace_booking_phone'] ?? 'unavailable',
                'email' => $booking['workplace_email'] ?? 'unavailable'
            ],
            'service_info' => [
                'id' => $booking['service_id'] ?? 'unavailable',
                'name' => $booking['service_name'] ?? 'unavailable',
                'duration_minutes' => $booking['service_duration'] ?? 'unavailable',
                'price' => $booking['service_price'] ?? 'unavailable',
                'vat_percentage' => $booking['service_vat'] ?? 'unavailable'
            ]
        ];
        $response['bookings'][] = $bookingData;

        // Log success for booking_id path
        $logger->logSuccess($response, null, [
            'additional_data' => [
                'operation_type' => 'find_booking_by_id',
                'booking_id' => (int)$bookingId
            ]
        ]);

        http_response_code(200);
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Validate required parameters for name/phone flow
    $requiredParams = ['full_name', 'caler_phone_nr'];
    $missingParams = [];
    
    foreach ($requiredParams as $param) {
        if (!isset($requestData[$param]) || trim($requestData[$param]) === '') {
            $missingParams[] = $param;
        }
    }
    
    if (!empty($missingParams)) {
        throw new Exception("Missing required parameters: " . implode(', ', $missingParams));
    }
    
    // Extract and clean parameters
    $fullName = trim($requestData['full_name']);
    $calerPhoneNr = trim($requestData['caler_phone_nr']);
    
    // Clean phone number (remove spaces, dots, plus signs)
    $cleanedPhoneNr = preg_replace('/[\s\.\+]/', '', $calerPhoneNr);
    
    // Validate phone number has at least 8 digits
    if (strlen($cleanedPhoneNr) < 8) {
        throw new Exception("Phone number must have at least 8 digits after cleaning");
    }
    
    // Get last 8 digits for matching
    $last8Digits = substr($cleanedPhoneNr, -8);

    // Current time for active-only filtering
    $now = date('Y-m-d H:i:s');
    
    // First, find all records with matching phone number (last 8 digits) and active only
    $stmt = $pdo->prepare("
        SELECT 
            b.unic_id as booking_id,
            b.id_specialist,
            b.id_work_place,
            b.day_of_creation,
            b.service_id,
            b.booking_start_datetime,
            b.booking_end_datetime,
            b.client_full_name,
            b.client_phone_nr,
            s.name as specialist_name,
            s.speciality as specialist_speciality,
            s.email as specialist_email,
            s.phone_nr as specialist_phone,
            wp.name_of_the_place as workplace_name,
            wp.address as workplace_address,
            wp.lead_person_name as workplace_lead_person,
            wp.lead_person_phone_nr as workplace_lead_phone,
            wp.workplace_phone_nr as workplace_phone,
            wp.booking_phone_nr as workplace_booking_phone,
            wp.email as workplace_email,
            sv.name_of_service as service_name,
            sv.duration as service_duration,
            sv.price_of_service as service_price,
            sv.procent_vat as service_vat
        FROM `booking` b
        LEFT JOIN `specialists` s ON b.id_specialist = s.unic_id
        LEFT JOIN `working_points` wp ON b.id_work_place = wp.unic_id
        LEFT JOIN `services` sv ON b.service_id = sv.unic_id
        WHERE RIGHT(REPLACE(REPLACE(REPLACE(b.client_phone_nr, ' ', ''), '.', ''), '+', ''), 8) = ?
          AND b.booking_start_datetime > ?
        ORDER BY b.booking_start_datetime DESC
    ");
    
    $stmt->execute([$last8Digits, $now]);
    $allBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($allBookings)) {
        throw new Exception("No active bookings found with phone number ending in: " . $last8Digits);
    }
    
    // Split incoming full name into words
    $incomingNameWords = array_filter(explode(' ', strtolower(trim($fullName))));
    
    // Calculate match score for each booking
    $scoredBookings = [];
    
    foreach ($allBookings as $booking) {
        $clientName = strtolower(trim($booking['client_full_name']));
        $clientNameWords = array_filter(explode(' ', $clientName));
        
        $matchScore = calculateNameMatchScore($incomingNameWords, $clientNameWords);
        
        $scoredBookings[] = [
            'booking' => $booking,
            'match_score' => $matchScore
        ];
    }
    
    // Sort by match score (highest first) and then by date (newest first)
    usort($scoredBookings, function($a, $b) {
        if ($a['match_score'] !== $b['match_score']) {
            return $b['match_score'] - $a['match_score']; // Higher score first
        }
        // If same score, sort by date (newest first)
        return strtotime($b['booking']['booking_start_datetime']) - strtotime($a['booking']['booking_start_datetime']);
    });
    
    // Get the top 2 bookings
    $topBookings = array_slice($scoredBookings, 0, 2);
    
    // Check if we have any valid matches (score >= 3)
    $validBookings = array_filter($topBookings, function($item) {
        return $item['match_score'] >= 3;
    });
    
    if (empty($validBookings)) {
        throw new Exception("Name does not match any of our active booking records for this phone number");
    }
    
    // Build response structure
    $response = [
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'search_criteria' => [
            'client_full_name' => $fullName,
            'caler_phone_nr' => $calerPhoneNr,
            'phone_number_cleaned' => $cleanedPhoneNr,
            'last_8_digits_matched' => $last8Digits,
            'active_only' => true
        ],
        'match_analysis' => [
            'total_phone_matches' => count($allBookings),
            'valid_name_matches' => count($validBookings),
            'highest_match_score' => max(array_column($validBookings, 'match_score'))
        ],
        'bookings_found' => count($validBookings),
        'bookings' => []
    ];
    
    // Process each valid booking
    foreach ($validBookings as $scoredBooking) {
        $booking = $scoredBooking['booking'];
        $matchScore = $scoredBooking['match_score'];
        
        $bookingData = [
            'match_score' => $matchScore,
            'booking_id' => $booking['booking_id'],
            'booking_details' => [
                'day_of_creation' => $booking['day_of_creation'],
                'booking_start_datetime' => $booking['booking_start_datetime'],
                'booking_end_datetime' => $booking['booking_end_datetime']
            ],
            'client_info' => [
                'full_name' => $booking['client_full_name'],
                'phone_number' => $booking['client_phone_nr']
            ],
            'specialist_info' => [
                'id' => $booking['id_specialist'] ?? 'unavailable',
                'name' => $booking['specialist_name'] ?? 'unavailable',
                'speciality' => $booking['specialist_speciality'] ?? 'unavailable',
                'email' => $booking['specialist_email'] ?? 'unavailable',
                'phone_number' => $booking['specialist_phone'] ?? 'unavailable'
            ],
            'workplace_info' => [
                'id' => $booking['id_work_place'] ?? 'unavailable',
                'name' => $booking['workplace_name'] ?? 'unavailable',
                'address' => $booking['workplace_address'] ?? 'unavailable',
                'lead_person_name' => $booking['workplace_lead_person'] ?? 'unavailable',
                'lead_person_phone' => $booking['workplace_lead_phone'] ?? 'unavailable',
                'workplace_phone' => $booking['workplace_phone'] ?? 'unavailable',
                'booking_phone' => $booking['workplace_booking_phone'] ?? 'unavailable',
                'email' => $booking['workplace_email'] ?? 'unavailable'
            ],
            'service_info' => [
                'id' => $booking['service_id'] ?? 'unavailable',
                'name' => $booking['service_name'] ?? 'unavailable',
                'duration_minutes' => $booking['service_duration'] ?? 'unavailable',
                'price' => $booking['service_price'] ?? 'unavailable',
                'vat_percentage' => $booking['service_vat'] ?? 'unavailable'
            ]
        ];
        
        $response['bookings'][] = $bookingData;
    }
    
    // Log the search operation
    $logger->logSuccess($response, null, [
        'additional_data' => [
            'operation_type' => 'find_bookings',
            'phone_number_cleaned' => $cleanedPhoneNr,
            'last_8_digits' => $last8Digits,
            'bookings_found' => count($validBookings),
            'total_phone_matches' => count($allBookings),
            'highest_match_score' => max(array_column($validBookings, 'match_score'))
        ]
    ]);
    
    // Return success response
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Log the error
    $logger->logError($e->getMessage(), $e->getTraceAsString(), 400, [
        'additional_data' => [
            'operation_type' => 'find_bookings',
            'phone_number_cleaned' => $cleanedPhoneNr ?? null,
            'last_8_digits' => $last8Digits ?? null,
            'client_name' => $fullName ?? null,
            'client_phone' => $calerPhoneNr ?? null
        ]
    ]);
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Calculate name match score based on word matching
 * 
 * @param array $incomingWords Array of incoming name words
 * @param array $clientWords Array of client name words from database
 * @return int Match score (10, 6, 3, or 0)
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
        
        $bestMatch = 0; // 0 = no match, 1 = partial, 2 = exact
        
        foreach ($clientWords as $clientWord) {
            $clientWord = trim($clientWord);
            if (empty($clientWord)) continue;
            
            // Exact match
            if (strtolower($incomingWord) === strtolower($clientWord)) {
                $bestMatch = 2;
                break;
            }
            
            // Partial match (one word contains the other)
            if (strlen($incomingWord) >= 3 && strlen($clientWord) >= 3) {
                if (stripos($incomingWord, $clientWord) !== false || 
                    stripos($clientWord, $incomingWord) !== false) {
                    $bestMatch = max($bestMatch, 1);
                }
            }
            
            // Fuzzy match for diacritics and typos (similarity > 80%)
            if (strlen($incomingWord) >= 3 && strlen($clientWord) >= 3) {
                $similarity = similar_text(strtolower($incomingWord), strtolower($clientWord), $percent);
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
        return 10; // All words match exactly
    } elseif ($exactMatches > 0 || $partialMatches > 0) {
        if ($exactMatches >= $partialMatches) {
            return 6; // Good match (mostly exact)
        } else {
            return 3; // Partial match (fuzzy/diacritics)
        }
    }
    
    return 0; // No match
}
?>
