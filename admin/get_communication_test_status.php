<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get workpoint_id from request
$workpoint_id = isset($_GET['workpoint_id']) ? intval($_GET['workpoint_id']) : 0;

if (!$workpoint_id) {
    echo json_encode(['success' => false, 'message' => 'Working point ID is required']);
    exit;
}

try {
    $response = [
        'success' => true,
        'whatsapp_status' => null,
        'facebook_status' => null
    ];

    // Get WhatsApp test status from existing table
    $stmt = $pdo->prepare("
        SELECT last_test_status as test_status,
               last_test_message as test_message,
               last_test_at as test_timestamp
        FROM workpoint_social_media
        WHERE workpoint_id = ? AND platform = 'whatsapp_business'
    ");
    $stmt->execute([$workpoint_id]);
    $whatsapp = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($whatsapp && $whatsapp['test_status']) {
        $response['whatsapp_status'] = [
            'status' => $whatsapp['test_status'],
            'message' => $whatsapp['test_message'] ?: 'No message',
            'timestamp' => $whatsapp['test_timestamp']
        ];

        // Clear testing status if it's been more than 30 seconds
        if ($whatsapp['test_status'] === 'testing') {
            $testTime = strtotime($whatsapp['test_timestamp']);
            if (time() - $testTime > 30) {
                // Update to failed if testing for too long
                $updateStmt = $pdo->prepare("
                    UPDATE workpoint_social_media
                    SET last_test_status = 'failed',
                        last_test_message = 'Test timeout - no response received'
                    WHERE workpoint_id = ? AND platform = 'whatsapp_business'
                ");
                $updateStmt->execute([$workpoint_id]);

                $response['whatsapp_status']['status'] = 'failed';
                $response['whatsapp_status']['message'] = 'Test timeout - no response received';
            }
        }
    }

    // Get Facebook test status from existing table
    $stmt = $pdo->prepare("
        SELECT last_test_status as test_status,
               last_test_message as test_message,
               last_test_at as test_timestamp
        FROM workpoint_social_media
        WHERE workpoint_id = ? AND platform = 'facebook_messenger'
    ");
    $stmt->execute([$workpoint_id]);
    $facebook = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($facebook && $facebook['test_status']) {
        $response['facebook_status'] = [
            'status' => $facebook['test_status'],
            'message' => $facebook['test_message'] ?: 'No message',
            'timestamp' => $facebook['test_timestamp']
        ];

        // Clear testing status if it's been more than 30 seconds
        if ($facebook['test_status'] === 'testing') {
            $testTime = strtotime($facebook['test_timestamp']);
            if (time() - $testTime > 30) {
                // Update to failed if testing for too long
                $updateStmt = $pdo->prepare("
                    UPDATE workpoint_social_media
                    SET last_test_status = 'failed',
                        last_test_message = 'Test timeout - no response received'
                    WHERE workpoint_id = ? AND platform = 'facebook_messenger'
                ");
                $updateStmt->execute([$workpoint_id]);

                $response['facebook_status']['status'] = 'failed';
                $response['facebook_status']['message'] = 'Test timeout - no response received';
            }
        }
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Database error in get_communication_test_status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>