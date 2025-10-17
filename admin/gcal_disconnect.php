<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

try {
	if (!isset($_SESSION['user'])) {
		echo json_encode(['success' => false, 'message' => 'Not authenticated']);
		exit;
	}
	$specialist_id = isset($_POST['specialist_id']) ? (int)$_POST['specialist_id'] : 0;
	if (!$specialist_id) {
		echo json_encode(['success' => false, 'message' => 'Missing specialist_id']);
		exit;
	}
	$stmt = $pdo->prepare("UPDATE google_calendar_credentials SET status='disabled', access_token=NULL, refresh_token=NULL, calendar_id=NULL, updated_at=NOW() WHERE specialist_id=?");
	$stmt->execute([$specialist_id]);
	echo json_encode(['success' => true]);
} catch (Throwable $e) {
	echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 