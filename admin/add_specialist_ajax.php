<?php
session_start();
require_once '../includes/db.php';

// Debug logging
error_log("AJAX Request - Session data: " . print_r($_SESSION, true));
error_log("AJAX Request - POST data: " . print_r($_POST, true));

/**
 * Function to check if user+password combination is unique across all tables
 * @param PDO $pdo Database connection
 * @param string $username Username to check
 * @param string $password Password to check
 * @return array Result with 'unique' boolean and 'message' if not unique
 */
function checkUserPasswordUniqueness($pdo, $username, $password) {
    $tables = [
        'organisations' => ['user_col' => 'user', 'pass_col' => 'pasword'], // Note: typo in DB field name
        'specialists' => ['user_col' => 'user', 'pass_col' => 'password'],
        'super_users' => ['user_col' => 'user', 'pass_col' => 'pasword'], // Note: typo in DB field name
        'working_points' => ['user_col' => 'user', 'pass_col' => 'password']
    ];

    foreach ($tables as $table => $columns) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$columns['user_col']} = ? AND {$columns['pass_col']} = ?");
        $stmt->execute([$username, $password]);
        
        if ($stmt->fetchColumn() > 0) {
            return [
                'unique' => false,
                'message' => "Username and password combination already exists in {$table} table"
            ];
        }
    }

    return ['unique' => true];
}

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id'])) {
    error_log("AJAX Error: user_id not set in session");
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Check if user has appropriate role based on session
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin_user', 'organisation_user', 'workpoint_user'])) {
    error_log("AJAX Error: Invalid role - " . ($_SESSION['role'] ?? 'not set'));
    echo json_encode(['success' => false, 'error' => 'Insufficient privileges']);
    exit;
}

// Set user role from session
$user_role = $_SESSION['role'];

// Handle POST request for adding specialist
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['name', 'speciality', 'email', 'user', 'password', 'h_of_email_schedule'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                error_log("AJAX Error: Missing required field: $field");
                echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
                exit;
            }
        }
        
        // Special validation for m_of_email_schedule - allow 0 as valid value
        if (!isset($_POST['m_of_email_schedule']) || $_POST['m_of_email_schedule'] === '') {
            error_log("AJAX Error: Missing required field: m_of_email_schedule");
            echo json_encode(['success' => false, 'error' => "Missing required field: m_of_email_schedule"]);
            exit;
        }

        // Get organisation ID based on user role
        $organisation_id = null;
        error_log("AJAX Debug: User role = " . $user_role);
        error_log("AJAX Debug: Session user_id = " . $_SESSION['user_id']);
        error_log("AJAX Debug: Working points = " . print_r($_POST['working_points'] ?? [], true));
        
        if ($user_role === 'admin_user') {
            // For admin, get organisation from working_points table
            if (!empty($_POST['working_points'])) {
                $workpoint_id = $_POST['working_points'][0];
                error_log("AJAX Debug: Admin - workpoint_id = " . $workpoint_id);
                $stmt = $pdo->prepare("SELECT organisation_id FROM working_points WHERE unic_id = ?");
                $stmt->execute([$workpoint_id]);
                $workpoint = $stmt->fetch();
                if ($workpoint) {
                    $organisation_id = $workpoint['organisation_id'];
                    error_log("AJAX Debug: Admin - found organisation_id = " . $organisation_id);
                } else {
                    error_log("AJAX Debug: Admin - workpoint not found");
                }
            } else {
                error_log("AJAX Debug: Admin - no working_points provided");
            }
        } elseif ($user_role === 'organisation_user') {
            // For organisation_user, get from organisations table
            error_log("AJAX Debug: Organisation user - checking organisations table");
            $stmt = $pdo->prepare("SELECT unic_id FROM organisations WHERE unic_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user_data = $stmt->fetch();
            if ($user_data) {
                $organisation_id = $user_data['unic_id'];
                error_log("AJAX Debug: Organisation user - found organisation_id = " . $organisation_id);
            } else {
                error_log("AJAX Debug: Organisation user - organisation not found");
            }
        } elseif ($user_role === 'workpoint_user') {
            // For workpoint_user, get from working_points table
            if (!empty($_POST['working_points'])) {
                $workpoint_id = $_POST['working_points'][0];
                error_log("AJAX Debug: Workpoint user - workpoint_id = " . $workpoint_id);
                $stmt = $pdo->prepare("SELECT organisation_id FROM working_points WHERE unic_id = ?");
                $stmt->execute([$workpoint_id]);
                $workpoint = $stmt->fetch();
                if ($workpoint) {
                    $organisation_id = $workpoint['organisation_id'];
                    error_log("AJAX Debug: Workpoint user - found organisation_id = " . $organisation_id);
                } else {
                    error_log("AJAX Debug: Workpoint user - workpoint not found");
                }
            } else {
                error_log("AJAX Debug: Workpoint user - no working_points provided");
            }
        } else {
            error_log("AJAX Debug: Unknown user role = " . $user_role);
        }

        // Fallback: try to get organisation_id from form data if provided
        if (!$organisation_id && !empty($_POST['organisation_id'])) {
            $organisation_id = $_POST['organisation_id'];
            error_log("AJAX Debug: Using organisation_id from form data = " . $organisation_id);
        }
        
        if (!$organisation_id) {
            error_log("AJAX Error: Could not determine organisation ID");
            echo json_encode(['success' => false, 'error' => 'Could not determine organisation ID']);
            exit;
        }

        // Check if user+password combination is unique across all tables
        $uniquenessCheck = checkUserPasswordUniqueness($pdo, $_POST['user'], $_POST['password']);
        if (!$uniquenessCheck['unique']) {
            echo json_encode(['success' => false, 'error' => $uniquenessCheck['message']]);
            exit;
        }

        // Email duplicate check removed - allow duplicate emails

        // Generate unique ID for specialist - get the last unic_id and increment by 1
        $stmt = $pdo->prepare("SELECT MAX(unic_id) as max_id FROM specialists");
        $stmt->execute();
        $result = $stmt->fetch();
        $specialist_id = ($result['max_id'] ?? 0) + 1;

        // Insert specialist
        error_log("AJAX Debug: Inserting specialist with ID = " . $specialist_id . ", organisation_id = " . $organisation_id);
        
        $stmt = $pdo->prepare("
            INSERT INTO specialists (unic_id, name, speciality, email, phone_nr, user, password, organisation_id, h_of_email_schedule, m_of_email_schedule) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $specialist_id,
            $_POST['name'],
            $_POST['speciality'],
            $_POST['email'],
            $_POST['phone_nr'] ?? '',
            $_POST['user'],
            $_POST['password'],
            $organisation_id,
            $_POST['h_of_email_schedule'],
            $_POST['m_of_email_schedule']
        ]);

        // Create working program entries if schedule data is provided
        error_log("AJAX Debug: Schedule data = " . print_r($_POST['schedule'] ?? [], true));
        error_log("AJAX Debug: Working points data = " . print_r($_POST['working_points'] ?? [], true));
        
        if (!empty($_POST['schedule']) && !empty($_POST['working_points'])) {
            $workpoint_id = $_POST['working_points'][0];
            error_log("AJAX Debug: Working program - workpoint_id = " . $workpoint_id);
            
            // Verify this workpoint exists and get its details
            $stmt = $pdo->prepare("SELECT unic_id, name_of_the_place FROM working_points WHERE unic_id = ?");
            $stmt->execute([$workpoint_id]);
            $workpoint_verify = $stmt->fetch();
            if ($workpoint_verify) {
                error_log("AJAX Debug: Working program - verified workpoint: " . $workpoint_verify['name_of_the_place'] . " (ID: " . $workpoint_verify['unic_id'] . ")");
            } else {
                error_log("AJAX Debug: Working program - workpoint not found in database!");
            }
            
            foreach ($_POST['schedule'] as $day => $shifts) {
                // Check if any shift has times
                $has_times = false;
                for ($shift = 1; $shift <= 3; $shift++) {
                    $start_key = "shift{$shift}_start";
                    $end_key = "shift{$shift}_end";
                    if (!empty($shifts[$start_key]) && !empty($shifts[$end_key])) {
                        $has_times = true;
                        break;
                    }
                }
                
                if ($has_times) {
                    error_log("AJAX Debug: Inserting working program for day = " . $day . ", specialist_id = " . $specialist_id . ", workpoint_id = " . $workpoint_id . ", organisation_id = " . $organisation_id);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO working_program (specialist_id, working_place_id, organisation_id, day_of_week, shift1_start, shift1_end, shift2_start, shift2_end, shift3_start, shift3_end) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $specialist_id,
                        $workpoint_id,
                        $organisation_id,
                        ucfirst($day),
                        $shifts['shift1_start'] ?? '00:00:00',
                        $shifts['shift1_end'] ?? '00:00:00',
                        $shifts['shift2_start'] ?? '00:00:00',
                        $shifts['shift2_end'] ?? '00:00:00',
                        $shifts['shift3_start'] ?? '00:00:00',
                        $shifts['shift3_end'] ?? '00:00:00'
                    ]);
                }
            }
        }

        echo json_encode(['success' => true, 'specialist_id' => $specialist_id]);

    } catch (Exception $e) {
        error_log("Error adding specialist: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?> 