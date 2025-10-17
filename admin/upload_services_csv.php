<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user']) || !in_array($_SESSION['role'], ['workpoint_user', 'organisation_user', 'admin_user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['workpoint_id']) || empty($_POST['workpoint_id'])) {
    echo json_encode(['success' => false, 'message' => 'Workpoint ID is required']);
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid CSV file']);
    exit;
}

$workpoint_id = trim($_POST['workpoint_id']);
$csv_file = $_FILES['csv_file'];

// Validate file type
$file_extension = strtolower(pathinfo($csv_file['name'], PATHINFO_EXTENSION));
if ($file_extension !== 'csv') {
    echo json_encode(['success' => false, 'message' => 'Please upload a CSV file']);
    exit;
}

try {
    // Check if workpoint exists
    $stmt = $pdo->prepare("SELECT unic_id FROM working_points WHERE unic_id = ?");
    $stmt->execute([$workpoint_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Workpoint not found']);
        exit;
    }
    
    // Read CSV file
    $handle = fopen($csv_file['tmp_name'], 'r');
    if (!$handle) {
        echo json_encode(['success' => false, 'message' => 'Could not read CSV file']);
        exit;
    }
    
    // Read header row
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        echo json_encode(['success' => false, 'message' => 'CSV file is empty']);
        exit;
    }
    
    // Validate required columns
    $required_columns = ['name_of_service', 'duration', 'price_of_service'];
    $header_lower = array_map('strtolower', $header);
    
    foreach ($required_columns as $required) {
        if (!in_array($required, $header_lower)) {
            fclose($handle);
            echo json_encode(['success' => false, 'message' => "Missing required column: $required"]);
            exit;
        }
    }
    
    // Find column indices
    $name_index = array_search('name_of_service', $header_lower);
    $duration_index = array_search('duration', $header_lower);
    $price_index = array_search('price_of_service', $header_lower);
    $vat_index = array_search('procent_vat', $header_lower); // May be false if not present
    
    $inserted_count = 0;
    $errors = [];
    $row_number = 1; // Start from 1 since we already read the header
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            // Validate row has enough columns
            if (count($row) < max($name_index, $duration_index, $price_index) + 1) {
                $errors[] = "Row $row_number: Insufficient columns";
                continue;
            }
            
            $service_name = trim($row[$name_index]);
            $duration = trim($row[$duration_index]);
            $price = trim($row[$price_index]);
            $vat = ($vat_index !== false && isset($row[$vat_index])) ? trim($row[$vat_index]) : '0.00';
            
            // Validate VAT
            if ($vat === '' || !is_numeric($vat) || $vat < 0 || $vat > 100) {
                $vat = '0.00';
            }
            
            // Validate service name
            if (empty($service_name) || strlen($service_name) < 2 || strlen($service_name) > 255) {
                $errors[] = "Row $row_number: Invalid service name";
                continue;
            }
            
            // Validate duration
            if (!is_numeric($duration) || $duration <= 0 || $duration > 480) {
                $errors[] = "Row $row_number: Duration must be between 1 and 480 minutes";
                continue;
            }
            
            // Validate price
            if (!is_numeric($price) || $price < 0) {
                $errors[] = "Row $row_number: Price must be a positive number";
                continue;
            }
            
            // Check if service already exists at this workpoint
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE id_work_place = ? AND name_of_service = ?");
            $stmt->execute([$workpoint_id, $service_name]);
            
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Row $row_number: Service '$service_name' already exists at this workpoint";
                continue;
            }
            
            // Insert service (unassigned initially)
            $stmt = $pdo->prepare("
                INSERT INTO services (
                    id_specialist,
                    id_work_place,
                    name_of_service,
                    duration,
                    price_of_service,
                    procent_vat
                ) VALUES (NULL, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$workpoint_id, $service_name, (int)$duration, (float)$price, (float)$vat])) {
                $inserted_count++;
            } else {
                $errors[] = "Row $row_number: Failed to insert service";
            }
        }
        
        fclose($handle);
        
        if (count($errors) > 0) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => 'CSV upload completed with errors',
                'inserted' => $inserted_count,
                'errors' => $errors
            ]);
        } else {
            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'message' => "Successfully imported $inserted_count services"
            ]);
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        fclose($handle);
        error_log("Error processing CSV: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error processing CSV file']);
    }
    
} catch (Exception $e) {
    error_log("Error uploading services CSV: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 