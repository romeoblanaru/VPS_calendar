<?php
/**
 * Webhook: Gathering Information
 * 
 * This webhook receives phone number parameters and returns comprehensive
 * information about a working point, its specialists, services, and schedules
 * formatted for AI voice bot consumption.
 * 
 * Parameters:
 * - assigned_phone_nr (required): Phone number to identify the working point
 * - start_date (optional, YYYY-MM-DD): Start date for availability range
 * - end_date (optional, YYYY-MM-DD): End date for availability range
 * 
 * Returns: JSON response with company details, working point info, specialists,
 *          services, schedules, and available slots (14 days by default or within provided date range)
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

// Configuration: Number of digits to match from the end of phone numbers
$PHONE_MATCH_DIGITS = 8;

// Initialize webhook logger
$logger = new WebhookLogger($pdo, 'gathering_information');

/**
 * Function to get timezone offset based on country
 */
function getTimezoneByCountry($country) {
    $timezones = [
        'RO' => 'Europe/Bucharest',   // Romania
        'LT' => 'Europe/Vilnius',     // Lithuania  
        'UK' => 'Europe/London',      // United Kingdom
        'DE' => 'Europe/Berlin',      // Germany
        'FR' => 'Europe/Paris',       // France
        'ES' => 'Europe/Madrid',      // Spain
        'IT' => 'Europe/Rome',        // Italy
        'US' => 'America/New_York',   // USA
        'CA' => 'America/Toronto',    // Canada
    ];
    
    return $timezones[$country] ?? 'UTC';
}

/**
 * Function to get available time slots for a specialist on a specific date
 * This function calculates available time by subtracting booked periods from shift periods
 */
function getAvailableSlots($pdo, $specialist_id, $working_place_id, $date) {
    try {
        // Get working program for the specialist on this day
        $dayOfWeek = date('l', strtotime($date)); // Monday, Tuesday, etc.
        
        $stmt = $pdo->prepare("
            SELECT shift1_start, shift1_end, shift2_start, shift2_end, shift3_start, shift3_end
            FROM working_program 
            WHERE specialist_id = ? AND working_place_id = ? AND day_of_week = ?
        ");
        $stmt->execute([$specialist_id, $working_place_id, $dayOfWeek]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$schedule) {
            return [];
        }
        
        // Get existing bookings for this date and specialist
        $bookingStmt = $pdo->prepare("
            SELECT TIME(booking_start_datetime) as start_time, TIME(booking_end_datetime) as end_time
            FROM booking 
            WHERE id_specialist = ? AND DATE(booking_start_datetime) = ? 
            ORDER BY booking_start_datetime
        ");
        $bookingStmt->execute([$specialist_id, $date]);
        $bookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $availableSlots = [];
        
        // Process each shift
        $shifts = [
            ['start' => $schedule['shift1_start'], 'end' => $schedule['shift1_end']],
            ['start' => $schedule['shift2_start'], 'end' => $schedule['shift2_end']],
            ['start' => $schedule['shift3_start'], 'end' => $schedule['shift3_end']]
        ];
        
        foreach ($shifts as $shift) {
            if ($shift['start'] && $shift['end'] && $shift['start'] !== '00:00:00' && $shift['end'] !== '00:00:00') {
                
                // Convert shift times to minutes for easier calculation
                $shiftStartMinutes = timeToMinutes($shift['start']);
                $shiftEndMinutes = timeToMinutes($shift['end']);
                
                // Create array of available periods (initially the entire shift)
                $availablePeriods = [['start' => $shiftStartMinutes, 'end' => $shiftEndMinutes]];
                
                // Remove booked periods from available periods
                foreach ($bookings as $booking) {
                    $bookingStartMinutes = timeToMinutes($booking['start_time']);
                    $bookingEndMinutes = timeToMinutes($booking['end_time']);
                    
                    $newAvailablePeriods = [];
                    
                    foreach ($availablePeriods as $period) {
                        // If booking doesn't overlap with this period, keep it as is
                        if ($bookingEndMinutes <= $period['start'] || $bookingStartMinutes >= $period['end']) {
                            $newAvailablePeriods[] = $period;
                        } else {
                            // Split the period around the booking
                            
                            // Add period before booking (if any)
                            if ($period['start'] < $bookingStartMinutes) {
                                $newAvailablePeriods[] = [
                                    'start' => $period['start'], 
                                    'end' => min($bookingStartMinutes, $period['end'])
                                ];
                            }
                            
                            // Add period after booking (if any)
                            if ($period['end'] > $bookingEndMinutes) {
                                $newAvailablePeriods[] = [
                                    'start' => max($bookingEndMinutes, $period['start']), 
                                    'end' => $period['end']
                                ];
                            }
                        }
                    }
                    
                    $availablePeriods = $newAvailablePeriods;
                }
                
                // Convert back to time format and add to available slots
                foreach ($availablePeriods as $period) {
                    // Only include periods that are at least 15 minutes long
                    if ($period['end'] - $period['start'] >= 15) {
                        $availableSlots[] = [
                            'start' => minutesToTime($period['start']),
                            'end' => minutesToTime($period['end'])
                        ];
                    }
                }
            }
        }
        
        return $availableSlots;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Convert time string (HH:MM:SS) to minutes since midnight
 */
function timeToMinutes($timeString) {
    $parts = explode(':', $timeString);
    return ($parts[0] * 60) + $parts[1];
}

/**
 * Convert minutes since midnight to time string (HH:MM)
 */
function minutesToTime($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%02d:%02d', $hours, $mins);
}

/**
 * Main webhook logic
 */
try {
    // Get parameters from GET or POST
    $assigned_phone_nr = $_GET['assigned_phone_nr'] ?? $_POST['assigned_phone_nr'] ?? null;
    $start_date_param = $_GET['start_date'] ?? $_POST['start_date'] ?? null;
    $end_date_param = $_GET['end_date'] ?? $_POST['end_date'] ?? null;
    // Optional filters
    $filter_specialist_id = isset($_GET['specialist_id']) ? (int)$_GET['specialist_id'] : (isset($_POST['specialist_id']) ? (int)$_POST['specialist_id'] : null);
    if (!is_int($filter_specialist_id) || $filter_specialist_id <= 0) { $filter_specialist_id = null; }
    $filter_service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : (isset($_POST['service_id']) ? (int)$_POST['service_id'] : null);
    if (!is_int($filter_service_id) || $filter_service_id <= 0) { $filter_service_id = null; }
    
    // Validate required parameter
    if (!$assigned_phone_nr) {
        $errorResponse = [
            'error' => 'Missing required parameter: assigned_phone_nr',
            'status' => 'error'
        ];
        
        http_response_code(400);
        echo json_encode($errorResponse);
        
        // Log the error
        $logger->logError('Missing required parameter: assigned_phone_nr', null, 400);
        exit();
    }
    
    // Clean phone number to get only digits
    $clean_assigned_phone = preg_replace('/[^0-9]/', '', $assigned_phone_nr);
    
    // Get the last N digits for matching
    $phone_suffix = substr($clean_assigned_phone, -$PHONE_MATCH_DIGITS);
    
    // Find working point by phone number (matching last N digits)
    $stmt = $pdo->prepare("
        SELECT wp.*, o.alias_name, o.www_address, o.unic_id as org_id, o.country, 
               o.oficial_company_name, o.email_address 
        FROM working_points wp 
        JOIN organisations o ON wp.organisation_id = o.unic_id 
        WHERE RIGHT(REPLACE(wp.booking_phone_nr, ' ', ''), ?) = ?
    ");
    $stmt->execute([$PHONE_MATCH_DIGITS, $phone_suffix]);
    $workingPoint = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$workingPoint) {
        $errorResponse = [
            'error' => 'Working point not found for the provided phone number (matched last ' . $PHONE_MATCH_DIGITS . ' digits: ' . $phone_suffix . ')',
            'status' => 'error'
        ];
        
        http_response_code(404);
        echo json_encode($errorResponse);
        
        // Log the error
        $logger->logError('Working point not found for phone number: ' . $assigned_phone_nr . ' (matched last ' . $PHONE_MATCH_DIGITS . ' digits: ' . $phone_suffix . ')', null, 404);
        exit();
    }
    
    // Build response structure
    $response = [
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'company_details' => [
            'unic_id' => $workingPoint['org_id'],
            'alias_name' => $workingPoint['alias_name'],
            'www_address' => $workingPoint['www_address'] ?? 'unavailable',
            'official_company_name' => $workingPoint['oficial_company_name'] ?? 'unavailable',
            'email_address' => $workingPoint['email_address'] ?? 'unavailable',
            'country' => $workingPoint['country'] ?? 'unavailable'
        ],
        'working_point' => [
            'unic_id' => $workingPoint['unic_id'],
            'name_of_the_place' => $workingPoint['name_of_the_place'],
            'address' => $workingPoint['address'],
            'lead_person_name' => $workingPoint['lead_person_name'],
            'lead_person_phone_nr' => $workingPoint['lead_person_phone_nr'],
            'workplace_phone_nr' => $workingPoint['workplace_phone_nr'],
            'email' => $workingPoint['email'],
            'booking_phone_nr' => $workingPoint['booking_phone_nr'] ?? 'unavailable',
            'country' => $workingPoint['country'] ?? 'unavailable',
            'language' => $workingPoint['language'] ?? 'unavailable',
            'we_handle' => $workingPoint['we_handle'] ?? 'unavailable',
            'specialist_relevance' => $workingPoint['specialist_relevance'] ?? 'unavailable'
        ],
        'specialists' => []
    ];
    
    // Calculate timezone based on organization country and set it
    $timezone = getTimezoneByCountry($workingPoint['country']);
    date_default_timezone_set($timezone);

    // Determine date range for availability only if both dates are valid
    $datesRange = [];
    $rangeStart = null;
    $rangeEnd = null;
    $rangeIsValid = false;

    $startObj = !empty($start_date_param) ? DateTime::createFromFormat('Y-m-d', $start_date_param) : null;
    $startValid = $startObj && $startObj->format('Y-m-d') === $start_date_param;
    $endObj = !empty($end_date_param) ? DateTime::createFromFormat('Y-m-d', $end_date_param) : null;
    $endValid = $endObj && $endObj->format('Y-m-d') === $end_date_param;
    if ($startValid) {
        if ($endValid && $endObj >= $startObj) {
            $rangeIsValid = true;
            $rangeStart = $startObj->format('Y-m-d');
            $rangeEnd = $endObj->format('Y-m-d');
        } else {
            // Single day window when only start_date is valid
            $rangeIsValid = true;
            $rangeStart = $startObj->format('Y-m-d');
            $rangeEnd = $rangeStart;
        }
        $cursor = strtotime($rangeStart);
        $endTs = strtotime($rangeEnd);
        while ($cursor <= $endTs) {
            $datesRange[] = date('Y-m-d', $cursor);
            $cursor = strtotime('+1 day', $cursor);
        }
    }
    
    // Get specialists for this working point
    if ($filter_specialist_id) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT s.*, ssa.specialist_nr_visible_to_client, ssa.specialist_email_visible_to_client
            FROM specialists s
            LEFT JOIN specialists_setting_and_attr ssa ON s.unic_id = ssa.specialist_id
            WHERE s.unic_id = ? AND s.unic_id IN (
                SELECT DISTINCT wp.specialist_id FROM working_program wp WHERE wp.working_place_id = ?
            )
        ");
        $stmt->execute([$filter_specialist_id, $workingPoint['unic_id']]);
    } else {
        $stmt = $pdo->prepare("
            SELECT DISTINCT s.*, ssa.specialist_nr_visible_to_client, ssa.specialist_email_visible_to_client
            FROM specialists s
            LEFT JOIN specialists_setting_and_attr ssa ON s.unic_id = ssa.specialist_id
            WHERE s.unic_id IN (
                SELECT DISTINCT wp.specialist_id 
                FROM working_program wp 
                WHERE wp.working_place_id = ?
            )
        ");
        $stmt->execute([$workingPoint['unic_id']]);
    }
    $specialists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($specialists as $specialist) {
        $specialistData = [
            'unic_id' => $specialist['unic_id'],
            'name' => $specialist['name'],
            'speciality' => $specialist['speciality'],
            'email' => ($specialist['specialist_email_visible_to_client'] == 1) ? 
                      $specialist['email'] : 'unavailable',
            'phone_number' => ($specialist['specialist_nr_visible_to_client'] == 1) ? 
                            $specialist['phone_nr'] : 'unavailable',
            'services' => [],
            'working_program' => []
        ];
        
        // Get services for this specialist at this working point
        if ($filter_service_id) {
            $serviceStmt = $pdo->prepare("
                SELECT unic_id, name_of_service, duration, price_of_service, procent_vat 
                FROM services 
                WHERE unic_id = ? AND id_specialist = ? AND id_work_place = ? 
                AND (deleted IS NULL OR deleted = 0 OR deleted != 1)
                AND (suspended IS NULL OR suspended = 0 OR suspended != 1)
            ");
            $serviceStmt->execute([$filter_service_id, $specialist['unic_id'], $workingPoint['unic_id']]);
        } else {
            $serviceStmt = $pdo->prepare("
                SELECT unic_id, name_of_service, duration, price_of_service, procent_vat 
                FROM services 
                WHERE id_specialist = ? AND id_work_place = ? 
                AND (deleted IS NULL OR deleted = 0 OR deleted != 1)
                AND (suspended IS NULL OR suspended = 0 OR suspended != 1)
            ");
            $serviceStmt->execute([$specialist['unic_id'], $workingPoint['unic_id']]);
        }
        $services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($services as $service) {
            $specialistData['services'][] = [
                'unic_id' => $service['unic_id'],
                'name_of_service' => $service['name_of_service'],
                'duration' => $service['duration'],
                'price_of_service' => $service['price_of_service'],
                'procent_vat' => $service['procent_vat'] ?? 0
            ];
        }
        
        // Include working program only when date range is missing or invalid
        if (!$rangeIsValid) {
            $programStmt = $pdo->prepare("
                SELECT day_of_week, shift1_start, shift1_end, shift2_start, shift2_end, shift3_start, shift3_end
                FROM working_program 
                WHERE specialist_id = ? AND working_place_id = ?
                ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
            ");
            $programStmt->execute([$specialist['unic_id'], $workingPoint['unic_id']]);
            $programs = $programStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($programs as $program) {
                $dayProgram = [
                    'day_of_week' => $program['day_of_week'],
                    'shifts' => []
                ];
                $shifts = [
                    ['start' => $program['shift1_start'], 'end' => $program['shift1_end']],
                    ['start' => $program['shift2_start'], 'end' => $program['shift2_end']],
                    ['start' => $program['shift3_start'], 'end' => $program['shift3_end']]
                ];
                foreach ($shifts as $shift) {
                    if ($shift['start'] && $shift['end'] && $shift['start'] !== '00:00:00' && $shift['end'] !== '00:00:00') {
                        $dayProgram['shifts'][] = [
                            'start' => substr($shift['start'], 0, 5),
                            'end' => substr($shift['end'], 0, 5)
                        ];
                    }
                }
                if (!empty($dayProgram['shifts'])) {
                    $specialistData['working_program'][] = $dayProgram;
                }
            }
        }
        
        // Calculate available slots only if a valid date range was provided
        if ($rangeIsValid) {
            $specialistAvailableSlots = [];
            foreach ($datesRange as $date) {
                $slots = getAvailableSlots($pdo, $specialist['unic_id'], $workingPoint['unic_id'], $date);
                if (!empty($slots)) {
                    $specialistAvailableSlots[] = [
                        'date' => $date,
                        'day_of_week' => date('l', strtotime($date)),
                        'slots' => $slots
                    ];
                }
            }
            $specialistData['available_slots'] = $specialistAvailableSlots;
        } else {
            $specialistData['available_slots'] = 'not valid start_date';
        }
        
        $response['specialists'][] = $specialistData;
    }
    
    $response['timezone_used'] = $timezone;
    $response['slots_calculated_for_days'] = $rangeIsValid ? count($datesRange) : 0;
    $response['time_period_checked'] = $rangeIsValid ? ($rangeStart . ' to ' . $rangeEnd) : 'not provided';
    
    // Log successful call
    $logger->logSuccess($response, null, [
        'working_point_id' => $workingPoint['unic_id'],
        'organisation_id' => $workingPoint['org_id'],
        'additional_data' => [
            'phone_number_provided' => $assigned_phone_nr,
            'phone_suffix_matched' => $phone_suffix,
            'match_digits_used' => $PHONE_MATCH_DIGITS,
            'specialists_count' => count($specialists),
            'timezone_used' => $timezone,
            'slots_calculated_for_days' => $rangeIsValid ? count($datesRange) : 0
        ]
    ]);
    
    // Return JSON response
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    // Database error
    $errorResponse = [
        'error' => 'Database error occurred',
        'status' => 'error'
    ];
    
    http_response_code(500);
    echo json_encode($errorResponse);
    
    // Log the database error
    $logger->logError(
        'Database error: ' . $e->getMessage(),
        $e->getTraceAsString(),
        500,
        [
            'working_point_id' => $workingPoint['unic_id'] ?? null,
            'organisation_id' => $workingPoint['org_id'] ?? null
        ]
    );
    
} catch (Exception $e) {
    // General error
    $errorResponse = [
        'error' => 'An unexpected error occurred',
        'status' => 'error'
    ];
    
    http_response_code(500);
    echo json_encode($errorResponse);
    
    // Log the general error
    $logger->logError(
        'Unexpected error: ' . $e->getMessage(),
        $e->getTraceAsString(),
        500
    );
}
?> 