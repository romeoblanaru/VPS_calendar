<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['loggedin']) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$specialist_id = isset($_GET['specialist_id']) ? (int)$_GET['specialist_id'] : 0;

if (!$specialist_id) {
    echo json_encode(['success' => false, 'message' => 'No specialist ID provided']);
    exit;
}

try {
    // Get specialist details from database including password
    $stmt = $pdo->prepare("
        SELECT
            unic_id,
            name,
            speciality,
            email,
            phone_nr,
            user,
            password
        FROM specialists
        WHERE unic_id = ?
    ");
    $stmt->execute([$specialist_id]);

    $specialist = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($specialist) {
        echo json_encode([
            'success' => true,
            'specialist' => $specialist
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Specialist not found'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
