<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/google_calendar_sync.php';

header('Content-Type: application/json');

try {
	if (!isset($_SESSION['user'])) {
		echo json_encode(['success' => false, 'message' => 'Not authenticated']);
		exit;
	}
	$specialist_id = isset($_GET['specialist_id']) ? (int)$_GET['specialist_id'] : (int)($_POST['specialist_id'] ?? 0);
	if (!$specialist_id) {
		echo json_encode(['success' => false, 'message' => 'Missing specialist_id']);
		exit;
	}
	$conn = get_google_calendar_connection($pdo, $specialist_id);
	echo json_encode(['success' => true, 'connection' => $conn]);
} catch (Throwable $e) {
	echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 