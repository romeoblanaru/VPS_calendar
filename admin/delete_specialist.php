<?php
// delete_specialist.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once '../includes/db.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Validate input
    if (!isset($_POST['specialist_id']) || !isset($_POST['password']) || !isset($_POST['action'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit();
    }
    
    $specialist_id = trim($_POST['specialist_id']);
    $password = trim($_POST['password']);
    $action = trim($_POST['action']);
    
    if ($action !== 'delete_specialist') {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit();
    }
    
    // Verify password (you may want to implement proper password verification)
    // For now, we'll just check if password is not empty
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Password is required']);
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // First, get specialist information for logging
        $stmt = $pdo->prepare("SELECT name, speciality FROM specialists WHERE unic_id = ?");
        $stmt->execute([$specialist_id]);
        $specialist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$specialist) {
            throw new Exception('Specialist not found');
        }
        
        // Delete all bookings for this specialist
        $stmt = $pdo->prepare("DELETE FROM booking WHERE id_specialist = ?");
        $stmt->execute([$specialist_id]);
        $bookingsDeleted = $stmt->rowCount();
        
        // Delete the specialist
        $stmt = $pdo->prepare("DELETE FROM specialists WHERE unic_id = ?");
        $stmt->execute([$specialist_id]);
        $specialistDeleted = $stmt->rowCount();
        
        if ($specialistDeleted === 0) {
            throw new Exception('Failed to delete specialist');
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Log the deletion
        $logMessage = "Specialist deleted: {$specialist['name']} ({$specialist['speciality']}) - Removed {$bookingsDeleted} bookings";
        error_log($logMessage);
        
        echo json_encode([
            'success' => true, 
            'message' => "Specialist '{$specialist['name']}' deleted successfully. Removed {$bookingsDeleted} bookings."
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Database error in delete_specialist.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    // Log the error for debugging
    error_log("General error in delete_specialist.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>