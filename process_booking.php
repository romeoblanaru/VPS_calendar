<?php
/**
 * Process Booking Actions
 * Handles adding, editing, and canceling bookings
 */

// Prevent any output before JSON response
ob_start();

session_start();
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once __DIR__ . '/includes/google_calendar_sync.php';
// nchan_publisher not needed - database triggers handle events

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add_booking':
        addBooking($pdo);
        break;
    case 'get_services':
        getServices($pdo);
        break;
    case 'delete_booking':
        deleteBooking($pdo);
        break;
    case 'modify_booking':
        modifyBooking($pdo);
        break;
    case 'get_booking_details':
        getBookingDetails($pdo);
        break;
    case 'get_specialists_for_workpoint':
        getSpecialistsForWorkpoint($pdo);
        break;
    case 'get_specialist_workpoints':
        getSpecialistWorkpoints($pdo);
        break;
    case 'check_shift_conflict':
        checkShiftConflict($pdo);
        break;
    default:
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getServices($pdo) {
    $specialist_id = (int)($_POST['specialist_id'] ?? 0);
    $workpoint_id = (int)($_POST['workpoint_id'] ?? 0);
    
    if (!$workpoint_id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Missing workpoint_id']);
        return;
    }
    
    try {
        // Check user role to determine which services to show
        $user_role = $_SESSION['role'] ?? '';
        $user_specialist_id = $_SESSION['specialist_id'] ?? 0;
        
        if ($user_role === 'specialist_user') {
            // Specialists can only see their own services
            $stmt = $pdo->prepare("
                SELECT s.unic_id, s.name_of_service, s.duration, s.price_of_service, s.procent_vat, s.id_specialist,
                       sp.name as specialist_name
                FROM services s
                LEFT JOIN specialists sp ON s.id_specialist = sp.unic_id
                WHERE s.id_work_place = ? AND s.id_specialist = ? AND (s.deleted = 0 OR s.deleted IS NULL) AND (s.suspended = 0 OR s.suspended IS NULL)
                ORDER BY s.name_of_service
            ");
            $stmt->execute([$workpoint_id, $user_specialist_id]);
        } else {
            // Admin, workpoint supervisors, and organisation users can see all services at the workpoint
            $stmt = $pdo->prepare("
                SELECT s.unic_id, s.name_of_service, s.duration, s.price_of_service, s.procent_vat, s.id_specialist,
                       sp.name as specialist_name
                FROM services s
                LEFT JOIN specialists sp ON s.id_specialist = sp.unic_id
                WHERE s.id_work_place = ? AND (s.deleted = 0 OR s.deleted IS NULL) AND (s.suspended = 0 OR s.suspended IS NULL)
                ORDER BY 
                    CASE WHEN s.id_specialist = ? THEN 0 ELSE 1 END, -- Specialist's services first
                    s.name_of_service
            ");
            $stmt->execute([$workpoint_id, $specialist_id]);
        }
        
        $services = $stmt->fetchAll();
        
        ob_end_clean();
        echo json_encode(['success' => true, 'services' => $services]);
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error fetching services: ' . $e->getMessage()]);
    }
}

function addBooking($pdo) {
    try {
        // Process booking data
        
        // Validate required fields
        $required_fields = ['client', 'client_phone_nr', 'date', 'time', 'specialist_id', 'service_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
                return;
            }
        }
        
        $client_name = trim($_POST['client']);
        $client_phone = trim($_POST['client_phone_nr']);
        
        // Validate phone number format (allow digits, spaces, dashes, plus signs, parentheses)
        if (!preg_match('/^[\d\s\-\+\(\)]+$/', $client_phone)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
            return;
        }
        
        // Limit phone number to 20 characters
        if (strlen($client_phone) > 20) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Phone number too long (max 20 characters)']);
            return;
        }
        
        $date = $_POST['date'];
        $time = $_POST['time'];
        $specialist_id = (int)$_POST['specialist_id'];
        $service_id = (int)$_POST['service_id'];
        $workpoint_id = (int)($_POST['workpoint_id'] ?? 0);
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            return;
        }
        
        // Validate time format
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid time format']);
            return;
        }
        
        // Get service details to get duration
        $stmt = $pdo->prepare("SELECT duration, id_work_place FROM services WHERE unic_id = ?");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch();
        
        if (!$service) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Service not found']);
            return;
        }
        
        $duration = (int)$service['duration'];
        $workpoint_id = $workpoint_id ?: $service['id_work_place'];

        
        // Validate duration
        if ($duration <= 0 || $duration > 480) { // Max 8 hours
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid service duration']);
            return;
        }
        
        // Create datetime strings
        $start_datetime = $date . ' ' . $time . ':00';
        
        // Calculate end time based on duration
        $end_time = date('H:i:s', strtotime($time . ':00') + ($duration * 60));
        $end_datetime = $date . ' ' . $end_time;
        
        // Check if the specialist exists and get their organization
        $stmt = $pdo->prepare("SELECT unic_id, organisation_id, name FROM specialists WHERE unic_id = ?");
        $stmt->execute([$specialist_id]);
        $specialist = $stmt->fetch();
        if (!$specialist) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Specialist not found']);
            return;
        }
        
        // Check user permissions for adding bookings
        $user_role = $_SESSION['role'] ?? '';
        $user_specialist_id = $_SESSION['specialist_id'] ?? 0;
        $user_workpoint_id = $_SESSION['workpoint_id'] ?? 0;
        
        
        
        // Admin can add bookings for any specialist
        if ($user_role === 'admin_user') {
            // Allow booking
        }
        // Specialist can add bookings for themselves
        elseif ($user_role === 'specialist_user' && $specialist_id == $user_specialist_id) {
            // Allow booking - specialists can always book for themselves
            // If workpoint_id is missing, we'll use the service's default workpoint
            // No additional permission checks needed for specialists booking for themselves
        }
        // Workpoint supervisor can add bookings for specialists at their workpoint
        elseif ($user_role === 'workpoint_user' && $user_workpoint_id > 0) {
            // Check if the specialist works at this supervisor's workpoint
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM working_program wp 
                WHERE wp.specialist_id = ? AND wp.working_place_id = ?
            ");
            $stmt->execute([$specialist_id, $user_workpoint_id]);
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'You do not have permission to add bookings for this specialist']);
                return;
            }
        }
        // Organisation user can add bookings for specialists in their organisation
        elseif ($user_role === 'organisation_user') {
            // Check if the specialist belongs to this organisation
            if ($specialist['organisation_id'] != $_SESSION['organisation_id']) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'You do not have permission to add bookings for this specialist']);
                return;
            }
        }
        // All other roles are denied
        else {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'You do not have permission to add bookings']);
            return;
        }
        
        // Check for booking conflicts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM booking 
            WHERE id_specialist = ? 
            AND DATE(booking_start_datetime) = ? 
            AND (
                (booking_start_datetime <= ? AND booking_end_datetime > ?) OR
                (booking_start_datetime < ? AND booking_end_datetime >= ?) OR
                (booking_start_datetime >= ? AND booking_end_datetime <= ?)
            )
        ");
        $stmt->execute([$specialist_id, $date, $start_datetime, $start_datetime, $end_datetime, $end_datetime, $start_datetime, $end_datetime]);
        
        if ($stmt->fetchColumn() > 0) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Booking conflict: Time slot already occupied']);
            return;
        }
        
        // Get working point for timezone
        $stmt = $pdo->prepare("SELECT * FROM working_points WHERE unic_id = ?");
        $stmt->execute([$workpoint_id]);
        $working_point = $stmt->fetch();
        
        // Create the booking record time in the working point's timezone
        $booking_creation_time = '';
        if ($working_point) {
            require_once 'includes/timezone_config.php';
            $booking_creation_time = getCurrentTimeInWorkingPointTimezoneOnly($working_point);
        } else {
            $booking_creation_time = date('Y-m-d H:i:s');
        }
        
        // Build received_through field with user information
        $username = $_SESSION['user'] ?? 'unknown';
        $user_role = $_SESSION['role'] ?? '';
        
        // Get appropriate display name based on role
        switch ($user_role) {
            case 'admin_user':
                $display_name = 'Admin';
                break;
            case 'specialist_user':
                $display_name = $_SESSION['specialist_name'] ?? 'Specialist';
                break;
            case 'organisation_user':
                $display_name = $_SESSION['organisation_name'] ?? 'Org';
                break;
            case 'workpoint_user':
                $display_name = $_SESSION['workpoint_name'] ?? 'WP';
                break;
            default:
                $display_name = 'User';
        }
        
        // Create format: "Web-UI Full Name / user" to fit in 20 chars
        $received_through = "Web-UI " . $display_name . " / " . $username;
        // Truncate to 20 characters to match database constraint
        $received_through = substr($received_through, 0, 20);
        
        // Handle SMS sending preference
        $send_sms = isset($_POST['send_sms']) ? $_POST['send_sms'] : '1'; // Default to send
        if ($send_sms === '0') {
            // Set session variable to force no SMS
            $pdo->exec("SET @force_sms = 'no'");
        } elseif ($send_sms === '1') {
            // Set session variable to force yes SMS
            $pdo->exec("SET @force_sms = 'yes'");
        }
        
        // Insert the booking - using client_full_name and client_phone_nr as per database schema
        $stmt = $pdo->prepare("
            INSERT INTO booking (
                id_specialist, 
                client_full_name, 
                client_phone_nr,
                day_of_creation, 
                booking_start_datetime, 
                booking_end_datetime, 
                id_work_place,
                service_id,
                received_through
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $specialist_id,
            $client_name,
            $client_phone,
            $booking_creation_time,
            $start_datetime,
            $end_datetime,
            $workpoint_id,
            $service_id,
            $received_through
        ]);
        
        if ($result) {
            $newId = (int)$pdo->lastInsertId();
            // Fetch row for payload snapshot
            $rowStmt = $pdo->prepare("SELECT * FROM booking WHERE unic_id = ?");
            $rowStmt->execute([$newId]);
            $row = $rowStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            queue_google_calendar_sync($pdo, 'created', $newId, $specialist_id, build_google_booking_payload($row));
            
            // Database trigger will handle publishing the event
            
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Booking added successfully']);
        } else {
            $errorInfo = $stmt->errorInfo();
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to insert booking', 'errorInfo' => $errorInfo]);
        }
        
    } catch (Exception $e) {
        error_log("Error adding booking: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function deleteBooking($pdo) {
    try {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        $send_sms = $_POST['send_sms'] ?? 'default'; // Keep as string/int, not bool
        
        // Debug logging
        error_log("deleteBooking - booking_id: $booking_id, send_sms raw value: " . var_export($send_sms, true));
        
        if (!$booking_id) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing booking ID']);
            return;
        }
        
        // Check if booking exists and user has permission to delete it
        // Get full booking details including service, workpoint, and specialist info
        $stmt = $pdo->prepare("
            SELECT b.*, 
                   s.organisation_id, s.name as specialist_name,
                   sv.name_of_service,
                   wp.name_of_the_place as workpoint_name, 
                   wp.address as workpoint_address, 
                   wp.workplace_phone_nr as workpoint_phone,
                   wp.booking_phone_nr as booking_phone,
                   o.alias_name as organisation_alias
            FROM booking b 
            JOIN specialists s ON b.id_specialist = s.unic_id 
            LEFT JOIN services sv ON b.service_id = sv.unic_id
            LEFT JOIN working_points wp ON b.id_work_place = wp.unic_id
            LEFT JOIN organisations o ON s.organisation_id = o.unic_id
            WHERE b.unic_id = ?
        ");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
            return;
        }
        
        // Check user permissions
        $user_role = $_SESSION['role'] ?? '';
        $user_specialist_id = $_SESSION['specialist_id'] ?? 0;
        $user_workpoint_id = $_SESSION['workpoint_id'] ?? 0;
        
        // Admin can delete any booking
        if ($user_role === 'admin_user') {
            // Allow deletion
        }
        // Specialist can delete their own bookings
        elseif ($user_role === 'specialist_user' && $booking['id_specialist'] == $user_specialist_id) {
            // Allow deletion
        }
        // Workpoint supervisor can delete bookings for specialists at their workpoint
        elseif ($user_role === 'workpoint_user' && $user_workpoint_id > 0) {
            // Check if the booking is for a specialist at this supervisor's workpoint
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM working_program wp 
                WHERE wp.specialist_id = ? AND wp.working_place_id = ?
            ");
            $stmt->execute([$booking['id_specialist'], $user_workpoint_id]);
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this booking']);
                return;
            }
        }
        // Organisation user can delete bookings for specialists in their organisation
        elseif ($user_role === 'organisation_user') {
            // Check if the booking is for a specialist in this organisation
            if ($booking['organisation_id'] != $_SESSION['organisation_id']) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this booking']);
                return;
            }
        }
        // All other roles are denied
        else {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this booking']);
            return;
        }
        
        // Get user's full name for made_by field
        $user_full_name = '';
        switch ($_SESSION['role']) {
            case 'admin_user':
                $user_full_name = $_SESSION['user'] ?? 'Admin User';
                break;
            case 'specialist_user':
                $user_full_name = $_SESSION['specialist_name'] ?? $_SESSION['user'] ?? 'Specialist';
                break;
            case 'organisation_user':
                $user_full_name = $_SESSION['organisation_name'] ?? $_SESSION['user'] ?? 'Organisation User';
                break;
            case 'workpoint_user':
                $user_full_name = $_SESSION['workpoint_name'] ?? $_SESSION['user'] ?? 'Workpoint User';
                break;
            default:
                $user_full_name = $_SESSION['user'] ?? 'Unknown User';
        }
        
        $made_by = "WEB-PAGE (user=" . $_SESSION['user'] . " / " . $user_full_name . ")";
        
        // Backup the booking to booking_canceled table before deletion
        $cancellationTime = date('Y-m-d H:i:s');
        
        $backupStmt = $pdo->prepare("
            INSERT INTO booking_canceled (
                id_specialist, id_work_place, service_id, booking_start_datetime, 
                booking_end_datetime, client_full_name, client_phone_nr, received_through,
                received_call_date, client_transcript_conversation, day_of_creation, 
                unic_id, organisation_id, cancellation_time, made_by, google_event_id
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");
        
        $backupResult = $backupStmt->execute([
            $booking['id_specialist'],
            $booking['id_work_place'],
            $booking['service_id'],
            $booking['booking_start_datetime'],
            $booking['booking_end_datetime'],
            $booking['client_full_name'],
            $booking['client_phone_nr'],
            $booking['received_through'],
            $booking['received_call_date'],
            $booking['client_transcript_conversation'],
            $booking['day_of_creation'],
            $booking['unic_id'],
            $booking['organisation_id'],
            $cancellationTime,
            $made_by,
            $booking['google_event_id'] ?? null
        ]);
        
        if (!$backupResult) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to backup booking to canceled table']);
            return;
        }
        
        $backupId = $pdo->lastInsertId();
        
        // Queue Google Calendar sync before deletion
        queue_google_calendar_sync($pdo, 'deleted', $booking_id, (int)$booking['id_specialist'], build_google_booking_payload($booking));
        
        // Handle SMS sending preference
        if ($send_sms === 0 || $send_sms === '0') {
            // Set session variable to force no SMS
            $pdo->exec("SET @force_sms = 'no'");
            error_log("deleteBooking - Setting @force_sms = 'no'");
        } elseif ($send_sms === 1 || $send_sms === '1') {
            // Set session variable to force yes SMS
            $pdo->exec("SET @force_sms = 'yes'");
            error_log("deleteBooking - Setting @force_sms = 'yes'");
        } else {
            // Default - let the system decide based on channel
            $pdo->exec("SET @force_sms = 'default'");
            error_log("deleteBooking - Setting @force_sms = 'default'");
        }
        
        // Delete the booking
        $deleteStmt = $pdo->prepare("DELETE FROM booking WHERE unic_id = ?");
        $deleteResult = $deleteStmt->execute([$booking_id]);
        
        if ($deleteResult) {
            $rowsAffected = $deleteStmt->rowCount();
            if ($rowsAffected === 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'No booking was deleted. Booking ID may have been already removed.']);
                return;
            }
            
            // Database trigger will handle publishing the event and SMS notification
            
            ob_end_clean();
            echo json_encode([
                'success' => true, 
                'message' => 'Booking cancelled and moved to cancelled bookings',
                'backup_id' => $backupId,
                'made_by' => $made_by,
                'sms_sent' => $send_sms
            ]);
        } else {
            $errorInfo = $deleteStmt->errorInfo();
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to delete booking', 'errorInfo' => $errorInfo]);
        }
        
    } catch (Exception $e) {
        error_log("Error deleting booking: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function checkShiftConflict($pdo) {
    try {
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        $service_id = (int)($_POST['service_id'] ?? 0);
        $specialist_id = (int)($_POST['specialist_id'] ?? 0);
        $workpoint_id = (int)($_POST['workpoint_id'] ?? 0);
        
        if (!$date || !$time || !$service_id || !$specialist_id) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
            return;
        }
        
        // Get service duration
        $stmt = $pdo->prepare("SELECT duration FROM services WHERE unic_id = ?");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch();
        
        if (!$service) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Service not found']);
            return;
        }
        
        $duration = (int)$service['duration'];
        
        // Calculate end time
        $end_time = date('H:i:s', strtotime($time . ':00') + ($duration * 60));
        
        // Get day of week
        $day_of_week = date('l', strtotime($date)); // Returns Monday, Tuesday, etc.
        
        // Get working program for this specialist and workpoint on this day
        error_log("DEBUG: specialist_id=$specialist_id, workpoint_id=$workpoint_id, day_of_week=$day_of_week");
        $stmt = $pdo->prepare("
            SELECT shift1_start, shift1_end, shift2_start, shift2_end, shift3_start, shift3_end 
            FROM working_program 
            WHERE specialist_id = ? AND working_place_id = ? AND day_of_week = ?
        ");
        $stmt->execute([$specialist_id, $workpoint_id, $day_of_week]);
        $working_program = $stmt->fetch();
        error_log("DEBUG: working_program row=" . print_r($working_program, true));
        
        if (!$working_program) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'No working schedule found for this day']);
            return;
        }
        
        // Check if booking extends beyond the shift where it starts
        $conflicts = [];
        $shift_times = [
            ['start' => $working_program['shift1_start'], 'end' => $working_program['shift1_end']],
            ['start' => $working_program['shift2_start'], 'end' => $working_program['shift2_end']],
            ['start' => $working_program['shift3_start'], 'end' => $working_program['shift3_end']]
        ];
        
        // Find which shift the booking starts in
        $booking_start_time = $time . ':00';
        $booking_shift = null;
        
        foreach ($shift_times as $index => $shift) {
            if ($shift['start'] && $shift['end'] && $shift['start'] !== '00:00:00' && $shift['end'] !== '00:00:00') {
                // Check if booking starts within this shift
                if ($booking_start_time >= $shift['start'] && $booking_start_time < $shift['end']) {
                    $booking_shift = $index;
                    break;
                }
            }
        }
        
        $conflicts = [];
        if ($booking_shift !== null) {
            $shift = $shift_times[$booking_shift];
            if ($end_time > $shift['end']) {
                $conflicts[] = [
                    'shift' => $booking_shift + 1,
                    'shift_start' => $shift['start'],
                    'shift_end' => $shift['end'],
                    'booking_start' => $booking_start_time,
                    'booking_end' => $end_time
                ];
            }
        }
        // If booking does not start in any shift, do not add a conflict and allow booking to proceed
        
        ob_end_clean();
        if (!empty($conflicts)) {
            echo json_encode([
                'success' => true, 
                'has_conflict' => true, 
                'conflicts' => $conflicts,
                'message' => 'Booking extends beyond shift end time'
            ]);
        } else {
            echo json_encode([
                'success' => true, 
                'has_conflict' => false,
                'message' => 'No shift conflicts'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Error checking shift conflict: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getBookingDetails($pdo) {
    try {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        
        if (!$booking_id) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing booking ID']);
            return;
        }
        
        // Get booking details with specialist and workpoint info
        $stmt = $pdo->prepare("
            SELECT b.*, s.name as specialist_name, wp.name_of_the_place as workpoint_name, svc.name_of_service
            FROM booking b
            LEFT JOIN specialists s ON b.id_specialist = s.unic_id
            LEFT JOIN working_points wp ON b.id_work_place = wp.unic_id
            LEFT JOIN services svc ON b.service_id = svc.unic_id
            WHERE b.unic_id = ?
        ");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
            return;
        }
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'booking' => $booking
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting booking details: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function modifyBooking($pdo) {
    try {
        // Validate required fields
        $required_fields = ['booking_id', 'client', 'client_phone_nr', 'date', 'time', 'service_id', 'specialist_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
                return;
            }
        }
        
        $booking_id = (int)$_POST['booking_id'];
        $client_name = trim($_POST['client']);
        $client_phone = trim($_POST['client_phone_nr']);
        $date = $_POST['date'];
        $time = $_POST['time'];
        $service_id = (int)$_POST['service_id'];
        $specialist_id = (int)$_POST['specialist_id'];
        $original_date = $_POST['original_date'] ?? '';
        $original_time = $_POST['original_time'] ?? '';
        
        // Validate phone number format
        if (!preg_match('/^[\d\s\-\+\(\)]+$/', $client_phone)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
            return;
        }
        
        // Limit phone number to 20 characters
        if (strlen($client_phone) > 20) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Phone number too long (max 20 characters)']);
            return;
        }
        
        // Validate date and time formats
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            return;
        }
        
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid time format']);
            return;
        }
        
        // Get current booking details
        $stmt = $pdo->prepare("
            SELECT b.*, s.organisation_id 
            FROM booking b 
            JOIN specialists s ON b.id_specialist = s.unic_id 
            WHERE b.unic_id = ?
        ");
        $stmt->execute([$booking_id]);
        $current_booking = $stmt->fetch();
        
        if (!$current_booking) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
            return;
        }
        
        // Validate the new specialist exists
        $stmt = $pdo->prepare("SELECT unic_id, organisation_id, name FROM specialists WHERE unic_id = ?");
        $stmt->execute([$specialist_id]);
        $new_specialist = $stmt->fetch();
        
        if (!$new_specialist) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Selected specialist not found']);
            return;
        }
        
        // Check if specialist belongs to the same organisation
        if ($new_specialist['organisation_id'] != $current_booking['organisation_id']) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Cannot move booking to specialist from different organisation']);
            return;
        }
        
        // Check user permissions (same as deleteBooking)
        $user_role = $_SESSION['role'] ?? '';
        $user_specialist_id = $_SESSION['specialist_id'] ?? 0;
        $user_workpoint_id = $_SESSION['workpoint_id'] ?? 0;
        
        // Admin can modify any booking
        if ($user_role === 'admin_user') {
            // Allow modification
        }
        // Specialist can modify their own bookings
        elseif ($user_role === 'specialist_user' && $current_booking['id_specialist'] == $user_specialist_id) {
            // Allow modification
        }
        // Workpoint supervisor can modify bookings for specialists at their workpoint
        elseif ($user_role === 'workpoint_user' && $user_workpoint_id > 0) {
            // Check if the booking is for a specialist at this supervisor's workpoint
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM working_program wp 
                WHERE wp.specialist_id = ? AND wp.working_place_id = ?
            ");
            $stmt->execute([$current_booking['id_specialist'], $user_workpoint_id]);
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'You do not have permission to modify this booking']);
                return;
            }
        }
        // Organisation user can modify bookings for specialists in their organisation
        elseif ($user_role === 'organisation_user') {
            // Check if the booking is for a specialist in this organisation
            if ($current_booking['organisation_id'] != $_SESSION['organisation_id']) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'You do not have permission to modify this booking']);
                return;
            }
        }
        // All other roles are denied
        else {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'You do not have permission to modify this booking']);
            return;
        }
        
        // Get service details
        $stmt = $pdo->prepare("SELECT duration FROM services WHERE unic_id = ?");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch();
        
        if (!$service) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Service not found']);
            return;
        }
        
        $duration = (int)$service['duration'];
        
        // Validate duration
        if ($duration <= 0 || $duration > 480) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid service duration']);
            return;
        }
        
        // Create datetime strings
        $start_datetime = $date . ' ' . $time . ':00';
        $end_time = date('H:i:s', strtotime($time . ':00') + ($duration * 60));
        $end_datetime = $date . ' ' . $end_time;
        
        // Check if date/time has changed
        $date_time_changed = false;
        if ($original_date && $original_time) {
            $original_datetime = $original_date . ' ' . $original_time . ':00';
            $date_time_changed = ($start_datetime !== $original_datetime);
        }
        
        // Check if the new specialist is working at this workpoint and time
        require_once __DIR__ . '/includes/calendar_functions.php';
        // Use workpoint_id from POST if provided and not empty, otherwise use the existing booking's workplace
        $workpoint_id = (isset($_POST['workpoint_id']) && $_POST['workpoint_id'] !== '' && $_POST['workpoint_id'] != '0') 
            ? (int)$_POST['workpoint_id'] 
            : (int)$current_booking['id_work_place'];
        $working_hours = getWorkingHours($pdo, $specialist_id, $workpoint_id, $date);
        if (!$working_hours || empty($working_hours)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Specialist is not scheduled to work at this workpoint on the selected day.']);
            return;
        }
        // Check if the booking fits within any shift
        $booking_start = $time . ':00';
        $booking_end = date('H:i:s', strtotime($booking_start) + ($duration * 60));
        $fits = false;
        foreach ($working_hours as $shift) {
            if ($booking_start >= $shift['start'] && $booking_end <= $shift['end']) {
                $fits = true;
                break;
            }
        }
        if (!$fits) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Specialist is not working at this time on the selected day.']);
            return;
        }

        // Check for conflicts with the new specialist (excluding the current booking)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM booking 
            WHERE id_specialist = ? 
            AND DATE(booking_start_datetime) = ? 
            AND unic_id != ?
            AND (
                (booking_start_datetime <= ? AND booking_end_datetime > ?) OR
                (booking_start_datetime < ? AND booking_end_datetime >= ?) OR
                (booking_start_datetime >= ? AND booking_end_datetime <= ?)
            )
        ");
        $stmt->execute([
            $specialist_id, 
            $date, 
            $booking_id,
            $start_datetime, 
            $start_datetime, 
            $end_datetime, 
            $end_datetime, 
            $start_datetime, 
            $end_datetime
        ]);
        
        if ($stmt->fetchColumn() > 0) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Booking conflict: Time slot already occupied by the selected specialist']);
            return;
        }
        
        // Handle SMS sending preference
        $send_sms = isset($_POST['send_sms']) ? $_POST['send_sms'] : '1'; // Default to send
        if ($send_sms === '0') {
            // Set session variable to force no SMS
            $pdo->exec("SET @force_sms = 'no'");
        } elseif ($send_sms === '1') {
            // Set session variable to force yes SMS
            $pdo->exec("SET @force_sms = 'yes'");
        }
        
        // Update the booking
        $stmt = $pdo->prepare("
            UPDATE booking SET 
                client_full_name = ?,
                client_phone_nr = ?,
                booking_start_datetime = ?,
                booking_end_datetime = ?,
                service_id = ?,
                id_specialist = ?
            WHERE unic_id = ?
        ");
        
        $result = $stmt->execute([
            $client_name,
            $client_phone,
            $start_datetime,
            $end_datetime,
            $service_id,
            $specialist_id,
            $booking_id
        ]);
        
        if ($result) {
            // Build a fresh snapshot for sync
            $rowStmt = $pdo->prepare("SELECT * FROM booking WHERE unic_id = ?");
            $rowStmt->execute([$booking_id]);
            $row = $rowStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            queue_google_calendar_sync($pdo, 'updated', $booking_id, $specialist_id, build_google_booking_payload($row));
            
            // Database trigger will handle publishing the event
            
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Booking modified successfully']);
        } else {
            $errorInfo = $stmt->errorInfo();
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to modify booking', 'errorInfo' => $errorInfo]);
        }
        
    } catch (Exception $e) {
        error_log("Error modifying booking: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getSpecialistsForWorkpoint($pdo) {
    try {
        $workpoint_id = (int)($_POST['workpoint_id'] ?? 0);
        
        if (!$workpoint_id) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing workpoint ID']);
            return;
        }
        
        // Get specialists for this workpoint
        $stmt = $pdo->prepare("
            SELECT DISTINCT s.unic_id, s.name, s.speciality
            FROM specialists s
            INNER JOIN working_program wp ON s.unic_id = wp.specialist_id 
            WHERE wp.working_place_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$workpoint_id]);
        $specialists = $stmt->fetchAll();
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'specialists' => $specialists
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting specialists for workpoint: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getSpecialistWorkpoints($pdo) {
    try {
        $specialist_id = (int)($_POST['specialist_id'] ?? 0);
        
        if (!$specialist_id) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing specialist ID']);
            return;
        }
        
        // Get workpoints for this specialist
        $stmt = $pdo->prepare("
            SELECT DISTINCT wp.unic_id as wp_id, wp.name_of_the_place as wp_name, wp.address as wp_address
            FROM working_points wp
            INNER JOIN working_program wpr ON wp.unic_id = wpr.working_place_id 
            WHERE wpr.specialist_id = ?
            ORDER BY wp.name_of_the_place
        ");
        $stmt->execute([$specialist_id]);
        $workpoints = $stmt->fetchAll();
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'workpoints' => $workpoints
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting specialist workpoints: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?> 