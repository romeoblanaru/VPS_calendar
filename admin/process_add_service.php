<?php
/**
 * Process Service Management
 * Handles adding, editing, and deleting services for specialists at workpoints
 */

session_start();
require_once '../includes/db.php';
require_once '../includes/session.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add_service':
        addService($pdo);
        break;
    case 'edit_service':
        editService($pdo);
        break;
    case 'delete_service':
        deleteService($pdo);
        break;
    case 'assign_service':
        assignService($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function addService($pdo) {
    try {
        // Debug: Log received data
        error_log("Received service data: " . print_r($_POST, true));
        
        // Validate required fields (specialist_id is now optional)
        $required_fields = ['name_of_service', 'duration', 'price_of_service', 'workpoint_id'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
                return;
            }
        }
        
        $service_name = trim($_POST['name_of_service']);
        $duration = (int)$_POST['duration'];
        $price = (float)$_POST['price_of_service'];
        // Sanitize VAT: remove spaces and % then cast
        $vat_raw = isset($_POST['procent_vat']) ? $_POST['procent_vat'] : '0';
        $vat_clean = preg_replace('/\s|%/','', (string)$vat_raw);
        if ($vat_clean === '') {
            $vat_percentage = 0.0;
        } elseif (!is_numeric($vat_clean)) {
            echo json_encode(['success' => false, 'message' => 'VAT percentage must be numeric']);
            return;
        } else {
            $vat_percentage = (float)$vat_clean;
        }
        $specialist_id = !empty($_POST['specialist_id']) ? (int)$_POST['specialist_id'] : null;
        $workpoint_id = (int)$_POST['workpoint_id'];
        
        // Validate service name
        if (strlen($service_name) < 2 || strlen($service_name) > 255) {
            echo json_encode(['success' => false, 'message' => 'Service name must be between 2 and 255 characters']);
            return;
        }
        
        // Validate duration
        if ($duration <= 0 || $duration > 480) { // Max 8 hours
            echo json_encode(['success' => false, 'message' => 'Duration must be between 1 and 480 minutes']);
            return;
        }
        
        // Validate price
        if ($price < 0) {
            echo json_encode(['success' => false, 'message' => 'Price cannot be negative']);
            return;
        }
        
        // Validate VAT percentage
        if ($vat_percentage < 0 || $vat_percentage > 100) {
            echo json_encode(['success' => false, 'message' => 'VAT percentage must be between 0 and 100']);
            return;
        }
        
        // Check if the workpoint exists
        $stmt = $pdo->prepare("SELECT unic_id FROM working_points WHERE unic_id = ?");
        $stmt->execute([$workpoint_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Workpoint not found']);
            return;
        }
        
        // If specialist_id is provided, check if the specialist exists
        if ($specialist_id) {
            $stmt = $pdo->prepare("SELECT unic_id FROM specialists WHERE unic_id = ?");
            $stmt->execute([$specialist_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Specialist not found']);
                return;
            }
        }
        
        // Check if service already exists for this workpoint (and specialist if provided)
        // Also check for deleted services
        if ($specialist_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE id_specialist = ? AND id_work_place = ? AND name_of_service = ? AND (deleted IS NULL OR deleted = 0)");
            $stmt->execute([$specialist_id, $workpoint_id, $service_name]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE id_specialist IS NULL AND id_work_place = ? AND name_of_service = ? AND (deleted IS NULL OR deleted = 0)");
            $stmt->execute([$workpoint_id, $service_name]);
        }
        
        $existing_count = $stmt->fetchColumn();
        if ($existing_count > 0) {
            error_log("Service already exists - specialist_id: $specialist_id, workpoint_id: $workpoint_id, service_name: $service_name, count: $existing_count");
            echo json_encode(['success' => false, 'message' => 'Service already exists at this workpoint']);
            return;
        }
        
        // Insert the service
        $stmt = $pdo->prepare("
            INSERT INTO services (
                id_specialist,
                id_work_place,
                name_of_service,
                duration,
                price_of_service,
                procent_vat
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $specialist_id,
            $workpoint_id,
            $service_name,
            $duration,
            $price,
            $vat_percentage
        ]);
        
        if ($result) {
            // Get the inserted service ID
            $service_id = $pdo->lastInsertId();
            error_log("Service added successfully - ID: $service_id, specialist_id: $specialist_id, workpoint_id: $workpoint_id, name: $service_name");
            echo json_encode(['success' => true, 'message' => 'Service added successfully', 'service_id' => $service_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to insert service']);
        }
        
    } catch (Exception $e) {
        error_log("Error adding service: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function editService($pdo) {
    try {
        // Validate required fields
        $required_fields = ['service_id', 'name_of_service', 'duration', 'price_of_service'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
                return;
            }
        }
        
        $service_id = (int)$_POST['service_id'];
        $service_name = trim($_POST['name_of_service']);
        $duration = (int)$_POST['duration'];
        $price = (float)$_POST['price_of_service'];
        // Sanitize VAT: remove spaces and %; empty -> 0; non-numeric -> error
        $vat_raw = isset($_POST['procent_vat']) ? $_POST['procent_vat'] : '0';
        $vat_clean = preg_replace('/\s|%/','', (string)$vat_raw);
        if ($vat_clean === '') {
            $vat_percentage = 0.0;
        } elseif (!is_numeric($vat_clean)) {
            echo json_encode(['success' => false, 'message' => 'VAT percentage must be numeric']);
            return;
        } else {
            $vat_percentage = (float)$vat_clean;
        }
        
        // Validate service name
        if (strlen($service_name) < 2 || strlen($service_name) > 255) {
            echo json_encode(['success' => false, 'message' => 'Service name must be between 2 and 255 characters']);
            return;
        }
        
        // Validate duration
        if ($duration <= 0 || $duration > 480) { // Max 8 hours
            echo json_encode(['success' => false, 'message' => 'Duration must be between 1 and 480 minutes']);
            return;
        }
        
        // Validate price
        if ($price < 0) {
            echo json_encode(['success' => false, 'message' => 'Price cannot be negative']);
            return;
        }
        
        // Validate VAT percentage
        if ($vat_percentage < 0 || $vat_percentage > 100) {
            echo json_encode(['success' => false, 'message' => 'VAT percentage must be between 0 and 100']);
            return;
        }
        
        // Check if the service exists
        $stmt = $pdo->prepare("SELECT id_specialist, id_work_place FROM services WHERE unic_id = ?");
        $stmt->execute([$service_id]);
        $existing_service = $stmt->fetch();
        
        if (!$existing_service) {
            echo json_encode(['success' => false, 'message' => 'Service not found']);
            return;
        }
        
        // Check if service name already exists for this specialist and workpoint (excluding current service)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE id_specialist = ? AND id_work_place = ? AND name_of_service = ? AND unic_id != ?");
        $stmt->execute([$existing_service['id_specialist'], $existing_service['id_work_place'], $service_name, $service_id]);
        
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Service name already exists for this specialist at this workpoint']);
            return;
        }
        
        // Update the service
        $stmt = $pdo->prepare("
            UPDATE services 
            SET name_of_service = ?, duration = ?, price_of_service = ?, procent_vat = ?
            WHERE unic_id = ?
        ");
        
        $result = $stmt->execute([
            $service_name,
            $duration,
            $price,
            $vat_percentage,
            $service_id
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Service updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update service']);
        }
        
    } catch (Exception $e) {
        error_log("Error editing service: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function deleteService($pdo) {
    try {
        if (empty($_POST['service_id'])) {
            echo json_encode(['success' => false, 'message' => 'Service ID is required']);
            return;
        }
        
        $service_id = (int)$_POST['service_id'];
        
        // Check if the service exists and get its details
        $stmt = $pdo->prepare("SELECT name_of_service, id_specialist, id_work_place FROM services WHERE unic_id = ?");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch();
        
        if (!$service) {
            echo json_encode(['success' => false, 'message' => 'Service not found']);
            return;
        }
        
        // If service is assigned to a specialist, just unassign it
        if ($service['id_specialist']) {
            $stmt = $pdo->prepare("UPDATE services SET id_specialist = NULL WHERE unic_id = ?");
            $result = $stmt->execute([$service_id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Service "' . $service['name_of_service'] . '" unassigned from specialist']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to unassign service']);
            }
            return;
        }
        
        // If service is unassigned, check if it's in any bookings
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM booking WHERE service_id = ?");
        $stmt->execute([$service_id]);
        $booking_count = $stmt->fetchColumn();
        
        if ($booking_count > 0) {
            // Service is in bookings, mark as deleted instead of actually deleting
            $stmt = $pdo->prepare("UPDATE services SET deleted = 1 WHERE unic_id = ?");
            $result = $stmt->execute([$service_id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Service "' . $service['name_of_service'] . '" marked as deleted (has bookings)']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to mark service as deleted']);
            }
        } else {
            // Service is not in bookings, actually delete it
            $stmt = $pdo->prepare("DELETE FROM services WHERE unic_id = ?");
            $result = $stmt->execute([$service_id]);
            
            if ($result && $stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Service "' . $service['name_of_service'] . '" deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete service']);
            }
        }
        
    } catch (Exception $e) {
        error_log("Error deleting service: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function assignService($pdo) {
    try {
        if (empty($_POST['service_id'])) {
            echo json_encode(['success' => false, 'message' => 'Service ID is required']);
            return;
        }
        
        $service_id = (int)$_POST['service_id'];
        $target_specialist_id = !empty($_POST['target_specialist_id']) ? (int)$_POST['target_specialist_id'] : null;
        
        // Check if the service exists and get its details
        $stmt = $pdo->prepare("SELECT name_of_service, duration, price_of_service, procent_vat, id_work_place, id_specialist, deleted FROM services WHERE unic_id = ?");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch();
        
        if (!$service) {
            echo json_encode(['success' => false, 'message' => 'Service not found']);
            return;
        }
        
        // If target specialist is provided, check if they exist
        if ($target_specialist_id) {
            $stmt = $pdo->prepare("SELECT unic_id FROM specialists WHERE unic_id = ?");
            $stmt->execute([$target_specialist_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Target specialist not found']);
                return;
            }
        }
        
        // If service is currently assigned to a specialist and we're assigning to a different specialist
        if ($service['id_specialist'] && $target_specialist_id && $service['id_specialist'] != $target_specialist_id) {
            // Create a new service entry for the target specialist
            $stmt = $pdo->prepare("
                INSERT INTO services (
                    id_specialist,
                    id_work_place,
                    name_of_service,
                    duration,
                    price_of_service,
                    procent_vat,
                    deleted
                ) VALUES (?, ?, ?, ?, ?, ?, 0)
            ");
            
            $result = $stmt->execute([
                $target_specialist_id,
                $service['id_work_place'],
                $service['name_of_service'],
                $service['duration'],
                $service['price_of_service'],
                $service['procent_vat']
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Service copied to new specialist successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to copy service to new specialist']);
            }
        } else {
            // Usual route: just update the assignment (unassign or assign to same specialist)
            // If assigning to a specialist and service was deleted, set deleted = false
            if ($target_specialist_id && $service['deleted']) {
                $stmt = $pdo->prepare("UPDATE services SET id_specialist = ?, deleted = 0 WHERE unic_id = ?");
                $result = $stmt->execute([$target_specialist_id, $service_id]);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Service assigned to specialist successfully and restored from deleted status']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to assign service']);
                }
            } else {
                $stmt = $pdo->prepare("UPDATE services SET id_specialist = ? WHERE unic_id = ?");
                $result = $stmt->execute([$target_specialist_id, $service_id]);
                
                if ($result) {
                    $message = $target_specialist_id ? 
                        'Service assigned to specialist successfully' : 
                        'Service unassigned successfully';
                    echo json_encode(['success' => true, 'message' => $message]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to assign service']);
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Error assigning service: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?> 