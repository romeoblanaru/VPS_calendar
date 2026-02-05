<?php
session_start();
require_once '../includes/db.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in - support both loggedin and user_id
if (!isset($_SESSION['loggedin']) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Accept both GET and POST requests
$specialist_id = isset($_GET['specialist_id']) ? (int)$_GET['specialist_id'] : (isset($_POST['specialist_id']) ? (int)$_POST['specialist_id'] : 0);

if (!$specialist_id) {
    echo json_encode(['success' => false, 'message' => 'No specialist ID provided']);
    exit;
}

try {
    // Get specialist settings and permissions
    $query = "
        SELECT
            specialist_nr_visible_to_client as phone_visible,
            specialist_email_visible_to_client as email_visible,
            specialist_can_delete_booking as can_delete_booking,
            specialist_can_modify_booking as can_modify_booking,
            specialist_can_add_services as can_add_services,
            specialist_can_modify_services as can_modify_services,
            specialist_can_delete_services as can_delete_services
        FROM specialists_setting_and_attr
        WHERE specialist_id = ?
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$specialist_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no settings exist, create default permissions
    if (!$settings) {
        $settings = [
            'phone_visible' => false,
            'email_visible' => false,
            'can_delete_booking' => true,
            'can_modify_booking' => true,
            'can_add_services' => true,
            'can_modify_services' => true,
            'can_delete_services' => true
        ];
    }

    // Convert to boolean values
    $permissions = [
        'phone_visible' => (bool)($settings['phone_visible'] ?? 0),
        'email_visible' => (bool)($settings['email_visible'] ?? 0),
        'can_delete_booking' => (bool)($settings['can_delete_booking'] ?? 1),
        'can_modify_booking' => (bool)($settings['can_modify_booking'] ?? 1),
        'can_add_services' => (bool)($settings['can_add_services'] ?? 1),
        'can_modify_services' => (bool)($settings['can_modify_services'] ?? 1),
        'can_delete_services' => (bool)($settings['can_delete_services'] ?? 1)
    ];

    echo json_encode([
        'success' => true,
        'permissions' => $permissions
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>