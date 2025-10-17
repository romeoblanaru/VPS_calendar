<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/db.php';
require_once '../includes/credentials.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin_user', 'organisation_user', 'workpoint_user'])) {
    http_response_code(403);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_specialist_data':
        getSpecialistData();
        break;
    case 'update_specialist':
        updateSpecialist();
        break;
    case 'delete_specialist':
        deleteSpecialist();
        break;
    case 'modify_schedule':
        modifySchedule();
        break;
    default:
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}

function getSpecialistData() {
    global $pdo;
    
    if (!isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Specialist ID is required']);
        exit;
    }
    
    $specialist_id = trim($_POST['specialist_id']);
    
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, ssa.daily_email_enabled 
            FROM specialists s 
            LEFT JOIN specialists_setting_and_attr ssa ON s.unic_id = ssa.specialist_id 
            WHERE s.unic_id = ?
        ");
        $stmt->execute([$specialist_id]);
        $specialist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$specialist) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Specialist not found']);
            exit;
        }
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'specialist' => $specialist
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error in getSpecialistData: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

function updateSpecialist() {
    global $pdo;
    
    $required_fields = ['specialist_id', 'name', 'speciality', 'user'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
            exit;
        }
    }
    
    $specialist_id = trim($_POST['specialist_id']);
    $name = trim($_POST['name']);
    $speciality = trim($_POST['speciality']);
    $email = trim($_POST['email'] ?? '');
    $phone_nr = trim($_POST['phone_nr'] ?? '');
    $user = trim($_POST['user']);
    $password = trim($_POST['password'] ?? '');
    $h_of_email_schedule = trim($_POST['h_of_email_schedule'] ?? '9');
    $m_of_email_schedule = trim($_POST['m_of_email_schedule'] ?? '0');
    
    try {
        // Check username+password uniqueness across all tables (exclude current specialist)
        $unique = checkUserPasswordUniqueness($pdo, $user, $password !== '' ? $password : (function() use ($pdo, $specialist_id) {
            $s = $pdo->prepare("SELECT password FROM specialists WHERE unic_id = ?");
            $s->execute([$specialist_id]);
            return (string)$s->fetchColumn();
        })(), ['specialists' => $specialist_id]);
        if (!$unique['unique']) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => $unique['message']]);
            exit;
        }
        
        // Build update query based on whether password is provided
        if (!empty($password)) {
            $stmt = $pdo->prepare("
                UPDATE specialists 
                SET name = ?, speciality = ?, email = ?, phone_nr = ?, user = ?, password = ?, h_of_email_schedule = ?, m_of_email_schedule = ?
                WHERE unic_id = ?
            ");
            $result = $stmt->execute([$name, $speciality, $email, $phone_nr, $user, $password, $h_of_email_schedule, $m_of_email_schedule, $specialist_id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE specialists 
                SET name = ?, speciality = ?, email = ?, phone_nr = ?, user = ?, h_of_email_schedule = ?, m_of_email_schedule = ?
                WHERE unic_id = ?
            ");
            $result = $stmt->execute([$name, $speciality, $email, $phone_nr, $user, $h_of_email_schedule, $m_of_email_schedule, $specialist_id]);
        }
        
        if ($result) {
            error_log("Admin updated specialist: specialist_id=$specialist_id, name=$name, user=$user");
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Specialist updated successfully'
            ]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to update specialist']);
        }
        
    } catch (PDOException $e) {
        error_log("Database error in updateSpecialist: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function deleteSpecialist() {
    global $pdo;
    
    if (!isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Specialist ID is required']);
        exit;
    }
    
    if (!isset($_POST['password']) || empty($_POST['password'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Password is required to confirm deletion']);
        exit;
    }
    
    $specialist_id = trim($_POST['specialist_id']);
    $password = trim($_POST['password']);
    
    try {
        // Verify password based on user role
        $valid_password = false;
        
        if ($_SESSION['role'] === 'admin_user') {
            // Check super_users table
            $stmt = $pdo->prepare("SELECT pasword FROM super_users WHERE unic_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && $password === $user['pasword']) {
                $valid_password = true;
            }
        } elseif ($_SESSION['role'] === 'organisation_user') {
            // Check organisations table
            $stmt = $pdo->prepare("SELECT pasword FROM organisations WHERE unic_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && $password === $user['pasword']) {
                $valid_password = true;
            }
        } elseif ($_SESSION['role'] === 'workpoint_user') {
            // Check working_points table
            $stmt = $pdo->prepare("SELECT password FROM working_points WHERE unic_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && $password === $user['password']) {
                $valid_password = true;
            }
        }
        
        if (!$valid_password) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid password']);
            exit;
        }
        
        // Get specialist details for logging
        $stmt = $pdo->prepare("SELECT name FROM specialists WHERE unic_id = ?");
        $stmt->execute([$specialist_id]);
        $specialist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$specialist) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Specialist not found']);
            exit;
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Delete all bookings for this specialist
            $stmt = $pdo->prepare("DELETE FROM booking WHERE id_specialist = ?");
            $stmt->execute([$specialist_id]);
            $bookings_deleted = $stmt->rowCount();
            
            // Delete all working program entries for this specialist
            $stmt = $pdo->prepare("DELETE FROM working_program WHERE specialist_id = ?");
            $stmt->execute([$specialist_id]);
            $programs_deleted = $stmt->rowCount();
            
            // Delete the specialist
            $stmt = $pdo->prepare("DELETE FROM specialists WHERE unic_id = ?");
            $stmt->execute([$specialist_id]);
            $specialist_deleted = $stmt->rowCount();
            
            if ($specialist_deleted > 0) {
                $pdo->commit();
                
                error_log("Admin deleted specialist: specialist_id=$specialist_id, specialist_name=" . $specialist['name'] . ", bookings_deleted=$bookings_deleted, programs_deleted=$programs_deleted");
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Specialist deleted successfully. Removed: ' . $bookings_deleted . ' bookings, ' . $programs_deleted . ' working programs.'
                ]);
            } else {
                $pdo->rollBack();
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to delete specialist']);
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (PDOException $e) {
        error_log("Database error in deleteSpecialist: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function modifySchedule() {
    global $pdo;
    
    if (!isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Specialist ID is required']);
        exit;
    }
    
    if (!isset($_POST['workpoint_id']) || empty($_POST['workpoint_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Workpoint ID is required']);
        exit;
    }
    
    $specialist_id = trim($_POST['specialist_id']);
    $workpoint_id = trim($_POST['workpoint_id']);
    
    try {
        // Get specialist and workpoint details for logging
        $stmt = $pdo->prepare("SELECT s.name as specialist_name, wp.name_of_the_place as wp_name FROM specialists s JOIN working_points wp ON wp.unic_id = ? WHERE s.unic_id = ?");
        $stmt->execute([$workpoint_id, $specialist_id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$details) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Specialist or workpoint not found']);
            exit;
        }
        
        // Redirect to the schedule modification page
        ob_clean();
        echo json_encode([
            'success' => true,
            'redirect_url' => "update_working_program.php?specialist_id=$specialist_id&workpoint_id=$workpoint_id"
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error in modifySchedule: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?> 