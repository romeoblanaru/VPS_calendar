<?php
// Suppress warnings to ensure clean JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/google_calendar_sync.php';
require_once __DIR__ . '/../includes/google_calendar_event_manager.php';
require_once __DIR__ . '/google_oauth_config.php';
require_once __DIR__ . '/../includes/timezone_mapping.php';



header('Content-Type: application/json');
set_time_limit(60); // Allow more time for processing

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
	
	// Get Google Calendar credentials
	$stmt = $pdo->prepare("SELECT * FROM google_calendar_credentials WHERE specialist_id = ? AND status = 'active' LIMIT 1");
	$stmt->execute([$specialist_id]);
	$credentials = $stmt->fetch(PDO::FETCH_ASSOC);
	
	if (!$credentials) {
		echo json_encode(['success' => false, 'message' => 'Google Calendar not connected for this specialist']);
		exit;
	}
	
	// Check if token is expired and refresh if needed
	if ($credentials['expires_at']) {
		$expires_at = new DateTime($credentials['expires_at']);
		$now = new DateTime();
		$now->add(new DateInterval('PT5M')); // Add 5 minutes buffer
		
		if ($now >= $expires_at) {
			// Token is expired, refresh it
			try {
				$oauth_config = new GoogleOAuthConfig();
				$new_tokens = $oauth_config->refreshAccessToken($credentials['refresh_token']);
				
				// Update the database with new tokens
				$stmt = $pdo->prepare("UPDATE google_calendar_credentials SET 
					access_token = ?, 
					expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
					updated_at = NOW() 
					WHERE id = ?");
				
				$expires_in = $new_tokens['expires_in'] ?? 3600;
				$stmt->execute([$new_tokens['access_token'], $expires_in, $credentials['id']]);
				
				// Update our credentials array
				$credentials['access_token'] = $new_tokens['access_token'];
				
			} catch (Exception $e) {
				echo json_encode(['success' => false, 'message' => 'Failed to refresh Google Calendar token: ' . $e->getMessage()]);
				exit;
			}
		}
	}
	
	// Get all future bookings for this specialist with details
	$stmt = $pdo->prepare("
		SELECT b.unic_id, b.booking_start_datetime, b.booking_end_datetime, 
		       b.client_full_name, b.client_phone_nr, b.received_through, 
		       s.name_of_service, wp.country
		FROM booking b
		LEFT JOIN services s ON b.service_id = s.unic_id
		LEFT JOIN working_points wp ON b.id_work_place = wp.unic_id
		WHERE b.id_specialist = ? 
		AND b.booking_start_datetime > NOW() 
		ORDER BY b.booking_start_datetime ASC
	");
	$stmt->execute([$specialist_id]);
	$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	if (empty($bookings)) {
		echo json_encode(['success' => true, 'message' => 'No future bookings found to sync', 'events' => []]);
		exit;
	}
	
	// Initialize professional Event Manager
	$eventManager = new GoogleCalendarEventManager($pdo);
	
	$sync_results = [];
	$success_count = 0;
	$error_count = 0;
	
	// Process each booking immediately using Event Manager
	foreach ($bookings as $booking) {
		$result = [
			'booking_id' => $booking['unic_id'],
			'client_name' => $booking['client_full_name'],
			'service' => $booking['name_of_service'],
			'start_time' => $booking['booking_start_datetime'],
			'end_time' => $booking['booking_end_datetime'],
			'status' => 'processing'
		];
		
		try {
			$booking_id = (int)$booking['unic_id'];
			
			// Build event data using Event Manager (includes timezone handling)
			$event_data = $eventManager->buildEventData(
				$booking,
				$booking['name_of_service'],
				$booking['country']
			);
			
			$result['google_event_data'] = $event_data;
			
			// Check if booking already has Google Event ID to prevent duplicates
			$existing_event_id = $eventManager->getBookingEventId($booking_id);
			
			if ($existing_event_id) {
				// Update existing event to prevent duplicates
				$sync_result = $eventManager->updateEvent($booking_id, $credentials, $event_data);
			} else {
				// Create new event
				$sync_result = $eventManager->createEvent($booking_id, $credentials, $event_data);
			}
			
			if ($sync_result['success']) {
				$result['status'] = 'success';
				$result['google_event_id'] = $sync_result['google_event_id'];
				$result['action'] = $sync_result['action']; // 'created' or 'updated'
				$result['google_event_link'] = "https://calendar.google.com/calendar/event?eid=" . base64_encode($sync_result['google_event_id']);
				$success_count++;
			} else {
				$result['status'] = 'error';
				$result['error'] = $sync_result['error'];
				$error_count++;
			}
			
		} catch (Exception $e) {
			$result['status'] = 'error';
			$result['error'] = $e->getMessage();
			$error_count++;
		}
		
		$sync_results[] = $result;
	}
	
	echo json_encode([
		'success' => true,
		'message' => "Synchronized $success_count events successfully" . ($error_count > 0 ? ", $error_count failed" : ""),
		'events' => $sync_results,
		'summary' => [
			'total' => count($bookings),
			'success' => $success_count,
			'errors' => $error_count
		]
	]);
	
} catch (Throwable $e) {
	echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 