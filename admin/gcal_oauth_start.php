<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once 'google_oauth_config.php';

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
	
	// Get specialist details
	$specStmt = $pdo->prepare("SELECT name FROM specialists WHERE unic_id=?");
	$specStmt->execute([$specialist_id]);
	$spec_name = $specStmt->fetchColumn() ?: 'Unknown';
	
	// Initialize Google OAuth
	$oauth = new GoogleOAuthConfig();
	$state = bin2hex(random_bytes(16));
	$auth_url = $oauth->getAuthUrl($specialist_id, $state);
	
	// Add cache busting parameter to ensure fresh OAuth URL
	$auth_url .= '&cache_bust=' . time();
	
	// Clean up any old pending states for this specialist (older than 1 hour)
	$pdo->prepare("DELETE FROM google_calendar_credentials WHERE specialist_id = ? AND status = 'pending' AND updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)")->execute([$specialist_id]);
	
	// Store the new state for validation and mark as pending
	$pdo->prepare("INSERT INTO google_calendar_credentials (specialist_id, specialist_name, status, oauth_state, updated_at) VALUES (?, ?, 'pending', ?, NOW()) ON DUPLICATE KEY UPDATE specialist_name=VALUES(specialist_name), status='pending', oauth_state=VALUES(oauth_state), updated_at=NOW()")->execute([$specialist_id, $spec_name, $state]);
	
	echo json_encode([
		'success' => true, 
		'oauth_url' => $auth_url,
		'redirect' => true,
		'message' => 'Redirecting to Google for authorization...'
	]);
} catch (Throwable $e) {
	echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 