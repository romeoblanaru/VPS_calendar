<?php
// process_add_org.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once '../includes/db.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Validate required fields
    $required_fields = ['alias_name', 'oficial_company_name'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        echo json_encode(['success' => false, 'error' => 'Required fields missing: ' . implode(', ', $missing_fields)]);
        exit();
    }
    
    // Sanitize and prepare data
    $alias_name = trim($_POST['alias_name']);
    $oficial_company_name = trim($_POST['oficial_company_name']);
    $contact_name = isset($_POST['contact_name']) ? trim($_POST['contact_name']) : '';
    $position = isset($_POST['position']) ? trim($_POST['position']) : '';
    $email_address = isset($_POST['email_address']) ? trim($_POST['email_address']) : '';
    $www_address = isset($_POST['www_address']) ? trim($_POST['www_address']) : '';
    $company_head_office_address = isset($_POST['company_head_office_address']) ? trim($_POST['company_head_office_address']) : '';
    $company_phone_nr = isset($_POST['company_phone_nr']) ? trim($_POST['company_phone_nr']) : '';
    $country = isset($_POST['country']) ? trim($_POST['country']) : '';
    $owner_name = isset($_POST['owner_name']) ? trim($_POST['owner_name']) : '';
    $owner_phone_nr = isset($_POST['owner_phone_nr']) ? trim($_POST['owner_phone_nr']) : '';
    $user = isset($_POST['user']) ? trim($_POST['user']) : '';
    $pasword = isset($_POST['pasword']) ? trim($_POST['pasword']) : '';
    
    // Generate unique ID for the organisation
    $unic_id = uniqid('ORG_', true);
    
    // Prepare SQL statement
    $sql = "INSERT INTO organisations (
        unic_id, alias_name, oficial_company_name, contact_name, position, 
        email_address, www_address, company_head_office_address, company_phone_nr, 
        country, owner_name, owner_phone_nr, user, pasword
    ) VALUES (
        :unic_id, :alias_name, :oficial_company_name, :contact_name, :position,
        :email_address, :www_address, :company_head_office_address, :company_phone_nr,
        :country, :owner_name, :owner_phone_nr, :user, :pasword
    )";
    
    $stmt = $pdo->prepare($sql);
    
    // Execute the statement
    $result = $stmt->execute([
        'unic_id' => $unic_id,
        'alias_name' => $alias_name,
        'oficial_company_name' => $oficial_company_name,
        'contact_name' => $contact_name,
        'position' => $position,
        'email_address' => $email_address,
        'www_address' => $www_address,
        'company_head_office_address' => $company_head_office_address,
        'company_phone_nr' => $company_phone_nr,
        'country' => $country,
        'owner_name' => $owner_name,
        'owner_phone_nr' => $owner_phone_nr,
        'user' => $user,
        'pasword' => $pasword
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Organisation added successfully',
            'organisation_id' => $unic_id
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to add organisation to database']);
    }
    
} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Database error in process_add_org.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} catch (Exception $e) {
    // Log the error for debugging
    error_log("General error in process_add_org.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred while processing the request']);
}
?>
