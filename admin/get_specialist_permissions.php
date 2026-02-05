<?php
session_start();
require_once '../includes/db.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$specialist_id = $_POST['specialist_id'] ?? 0;

if (!$specialist_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid specialist ID']);
    exit;
}

try {
    // Get specialist settings and permissions
    $query = "
        SELECT
            specialist_nr_visible_to_client as phone_visible,
            specialist_email_visible_to_client as email_visible,
            1 as can_modify_schedule,
            1 as can_view_clients,
            1 as can_manage_bookings
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
            'can_modify_schedule' => true,
            'can_view_clients' => true,
            'can_manage_bookings' => true
        ];
    }

    // Convert to boolean values
    $permissions = [
        'phone_visible' => (bool)$settings['phone_visible'],
        'email_visible' => (bool)$settings['email_visible'],
        'can_modify_schedule' => (bool)$settings['can_modify_schedule'],
        'can_view_clients' => (bool)$settings['can_view_clients'],
        'can_manage_bookings' => (bool)$settings['can_manage_bookings']
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