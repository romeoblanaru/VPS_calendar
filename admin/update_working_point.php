<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/credentials.php';

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user']) || !in_array($_SESSION['role'], ['organisation_user', 'admin_user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if this is a booking phone update action
$action = $_POST['action'] ?? '';
if ($action === 'update_booking_phone') {
    $workpoint_id = $_POST['workpoint_id'] ?? '';
    $booking_phone_nr = $_POST['booking_phone_nr'] ?? '';
    
    if (!$workpoint_id || !$booking_phone_nr) {
        echo json_encode(['success' => false, 'message' => 'Workpoint ID and booking phone number are required']);
        exit;
    }
    
    try {
        // Check if workpoint exists and user has permission
        $stmt = $pdo->prepare("SELECT organisation_id FROM working_points WHERE unic_id = ?");
        $stmt->execute([$workpoint_id]);
        $workpoint = $stmt->fetch();
        
        if (!$workpoint) {
            echo json_encode(['success' => false, 'message' => 'Workpoint not found']);
            exit;
        }
        
        // Check if user has permission to access this workpoint
        if ($_SESSION['role'] === 'organisation_user') {
            $stmt = $pdo->prepare("SELECT unic_id FROM organisations WHERE user = ?");
            $stmt->execute([$_SESSION['user']]);
            $org = $stmt->fetch();
            
            if (!$org || $workpoint['organisation_id'] != $org['unic_id']) {
                echo json_encode(['success' => false, 'message' => 'Access denied to this workpoint']);
                exit;
            }
        }
        
        // Get additional fields for Telnyx update
        $booking_sms_number = $_POST['booking_sms_number'] ?? null;
        $we_handling = $_POST['we_handling'] ?? null;
        $specialist_relevance = $_POST['specialist_relevance'] ?? null;

        // Update booking phone number and new fields
        $stmt = $pdo->prepare("UPDATE working_points SET booking_phone_nr = ?, booking_sms_number = ?, we_handling = ?, specialist_relevance = ? WHERE unic_id = ?");
        $result = $stmt->execute([$booking_phone_nr, $booking_sms_number, $we_handling, $specialist_relevance, $workpoint_id]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Booking phone number updated successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update booking phone number']);
        }
        
    } catch (PDOException $e) {
        error_log("Database error in update_working_point.php (booking phone): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit;
}

// Handle supervisor-only update (username/password/lead person/phone)
if ($action === 'update_supervisor') {
    $workpoint_id = $_POST['workpoint_id'] ?? '';
    $user = $_POST['user'] ?? '';
    $password = $_POST['password'] ?? '';
    $lead_person_name = $_POST['lead_person_name'] ?? '';
    $lead_person_phone_nr = $_POST['lead_person_phone_nr'] ?? '';

    if (!$workpoint_id || !$user || !$password || !$lead_person_name || !$lead_person_phone_nr) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT organisation_id FROM working_points WHERE unic_id = ?");
        $stmt->execute([$workpoint_id]);
        $workpoint = $stmt->fetch();
        if (!$workpoint) {
            echo json_encode(['success' => false, 'message' => 'Workpoint not found']);
            exit;
        }
        if ($_SESSION['role'] === 'organisation_user') {
            $stmt = $pdo->prepare("SELECT unic_id FROM organisations WHERE user = ?");
            $stmt->execute([$_SESSION['user']]);
            $org = $stmt->fetch();
            if (!$org || $workpoint['organisation_id'] != $org['unic_id']) {
                echo json_encode(['success' => false, 'message' => 'Access denied to this workpoint']);
                exit;
            }
        }

        // Uniqueness check across all tables, excluding current workpoint id
        $unique = checkUserPasswordUniqueness($pdo, $user, $password, ['working_points' => $workpoint_id]);
        if (!$unique['unique']) {
            echo json_encode(['success' => false, 'message' => $unique['message']]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE working_points SET user = ?, password = ?, lead_person_name = ?, lead_person_phone_nr = ? WHERE unic_id = ?");
        $ok = $stmt->execute([$user, $password, $lead_person_name, $lead_person_phone_nr, $workpoint_id]);
        echo json_encode($ok ? ['success' => true, 'message' => 'Supervisor updated successfully']
                             : ['success' => false, 'message' => 'Failed to update supervisor']);
    } catch (PDOException $e) {
        error_log("Database error in update_working_point.php (supervisor): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit;
}

// Get required fields for full workpoint update
$workpoint_id = $_POST['workpoint_id'] ?? '';
$name_of_the_place = $_POST['name_of_the_place'] ?? '';
$description_of_the_place = $_POST['description_of_the_place'] ?? '';
$address = $_POST['address'] ?? '';
$landmark = $_POST['landmark'] ?? '';
$directions = $_POST['directions'] ?? '';
$lead_person_name = $_POST['lead_person_name'] ?? '';
$lead_person_phone_nr = $_POST['lead_person_phone_nr'] ?? '';
$workplace_phone_nr = $_POST['workplace_phone_nr'] ?? '';
$booking_phone_nr = $_POST['booking_phone_nr'] ?? '';
$booking_sms_number = $_POST['booking_sms_number'] ?? null;
$user = $_POST['user'] ?? '';
$password = $_POST['password'] ?? '';
$email = $_POST['email'] ?? '';
$we_handling = $_POST['we_handling'] ?? '';
$specialist_relevance = $_POST['specialist_relevance'] ?? null;

// Validate required fields
if (!$workpoint_id || !$name_of_the_place || !$address || trim($landmark) === '' ||
    trim($directions) === '' || !$lead_person_name || !$lead_person_phone_nr ||
    !$workplace_phone_nr || !$booking_phone_nr || !$user || !$password ||
    trim($we_handling) === '' || trim($specialist_relevance) === '') {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    exit;
}

try {
    // Check if workpoint exists and user has permission
    $stmt = $pdo->prepare("SELECT organisation_id FROM working_points WHERE unic_id = ?");
    $stmt->execute([$workpoint_id]);
    $workpoint = $stmt->fetch();
    
    if (!$workpoint) {
        echo json_encode(['success' => false, 'message' => 'Workpoint not found']);
        exit;
    }
    
    // Check if user has permission to access this workpoint
    if ($_SESSION['role'] === 'organisation_user') {
        $stmt = $pdo->prepare("SELECT unic_id FROM organisations WHERE user = ?");
        $stmt->execute([$_SESSION['user']]);
        $org = $stmt->fetch();
        
        if (!$org || $workpoint['organisation_id'] != $org['unic_id']) {
            echo json_encode(['success' => false, 'message' => 'Access denied to this workpoint']);
            exit;
        }
    }
    
    // Get existing working point data for fallback values
    $stmt = $pdo->prepare("SELECT country, language FROM working_points WHERE unic_id = ?");
    $stmt->execute([$workpoint_id]);
    $existing_wp = $stmt->fetch();
    
    // Validate country and language with fallback to existing values
    $country = strtoupper(trim($_POST['country'] ?? ''));
    $language = strtoupper(trim($_POST['language'] ?? ''));
    
    // Use existing values if new ones are empty
    if (empty($country) && $existing_wp) {
        $country = $existing_wp['country'];
    }
    if (empty($language) && $existing_wp) {
        $language = $existing_wp['language'];
    }
    
    // Validate language (must be 2 characters if provided)
    if (!empty($language) && !preg_match('/^[A-Z]{2}$/', $language)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Language must be a 2-letter code (e.g., EN, RO, LT)']);
        exit;
    }
    
    // Validate country (must not be empty)
    if (empty($country)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Country is required']);
        exit;
    }
    
    // Update workpoint
    $stmt = $pdo->prepare("
        UPDATE working_points
        SET name_of_the_place = ?,
            description_of_the_place = ?,
            address = ?,
            landmark = ?,
            directions = ?,
            lead_person_name = ?,
            lead_person_phone_nr = ?,
            workplace_phone_nr = ?,
            booking_phone_nr = ?,
            booking_sms_number = ?,
            user = ?,
            password = ?,
            email = ?,
            country = ?,
            language = ?,
            we_handling = ?,
            specialist_relevance = ?
        WHERE unic_id = ?
    ");

    $result = $stmt->execute([
        $name_of_the_place,
        $description_of_the_place,
        $address,
        $landmark,
        $directions,
        $lead_person_name,
        $lead_person_phone_nr,
        $workplace_phone_nr,
        $booking_phone_nr,
        $booking_sms_number,
        $user,
        $password,
        $email,
        $country,
        $language,
        $we_handling,
        $specialist_relevance,
        $workpoint_id
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Workpoint updated successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update workpoint']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in update_working_point.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 