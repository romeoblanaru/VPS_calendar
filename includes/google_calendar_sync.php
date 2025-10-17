<?php
/**
 * Google Calendar Sync Helper
 *
 * Provides lightweight functions to queue Google Calendar synchronization
 * for booking events (create, update, delete) and to read connection status
 * for a specialist. This avoids blocking requests and centralizes trigger logic.
 */

if (!function_exists('queue_google_calendar_sync')) {
	/**
	 * Queue a Google Calendar sync event with near-real-time signal
	 *
	 * @param PDO $pdo
	 * @param string $eventType One of: created, updated, deleted
	 * @param int|null $bookingId The booking unic_id (null for deletes if not available)
	 * @param int $specialistId Specialist unic_id
	 * @param array $payload Optional payload snapshot (e.g., booking fields)
	 * @return void
	 */
	function queue_google_calendar_sync(PDO $pdo, string $eventType, ?int $bookingId, int $specialistId, array $payload = []): void
	{
		try {
			// Verify specialist has Google credentials
			$credStmt = $pdo->prepare("SELECT id, status FROM google_calendar_credentials WHERE specialist_id = ? LIMIT 1");
			$credStmt->execute([$specialistId]);
			$credentials = $credStmt->fetch(PDO::FETCH_ASSOC);
			if (!$credentials || !in_array($credentials['status'], ['connected', 'active', 'enabled'])) {
				// No active connection; skip queuing to avoid noise
				return;
			}

			// Queue the sync operation (existing behavior)
			$queueStmt = $pdo->prepare("INSERT INTO google_calendar_sync_queue (event_type, booking_id, specialist_id, payload, status, attempts, created_at, updated_at)
				VALUES (?, ?, ?, ?, 'pending', 0, NOW(), NOW())");
			$queueStmt->execute([
				$eventType,
				$bookingId,
				$specialistId,
				json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
			]);
			
			// Send signal to wake up worker for near-real-time sync (3-5 seconds)
			signal_google_calendar_worker($pdo, $specialistId, $bookingId, $eventType);
			
		} catch (Throwable $e) {
			// Silent fail; do not block the main flow
			error_log('Google Calendar queue error: ' . $e->getMessage());
		}
	}
}

if (!function_exists('get_google_calendar_connection')) {
	/**
	 * Get Google Calendar connection row for a specialist
	 *
	 * @param PDO $pdo
	 * @param int $specialistId
	 * @return array|null
	 */
	function get_google_calendar_connection(PDO $pdo, int $specialistId): ?array
	{
		try {
			$stmt = $pdo->prepare("SELECT id, specialist_id, specialist_name, calendar_id, calendar_name, status, access_token, refresh_token, expires_at, updated_at FROM google_calendar_credentials WHERE specialist_id = ? LIMIT 1");
			$stmt->execute([$specialistId]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			return $row ?: null;
		} catch (Throwable $e) {
			error_log('Google Calendar fetch connection error: ' . $e->getMessage());
			return null;
		}
	}
}

if (!function_exists('signal_google_calendar_worker')) {
	/**
	 * Send signal to wake up Google Calendar worker for near-real-time sync
	 * 
	 * @param PDO $pdo
	 * @param int $specialistId Specialist who triggered the sync
	 * @param int|null $bookingId Related booking ID (optional)
	 * @param string $eventType Type of booking event
	 * @return void
	 */
	function signal_google_calendar_worker(PDO $pdo, int $specialistId, ?int $bookingId, string $eventType): void
	{
		try {
			// Create signal record to wake up worker
			$signalStmt = $pdo->prepare("INSERT INTO gcal_worker_signals (specialist_id, booking_id, event_type) VALUES (?, ?, ?)");
			$signalStmt->execute([$specialistId, $bookingId, $eventType]);
		} catch (Throwable $e) {
			// Silent fail; signal is optional optimization, don't break main flow
			error_log('Google Calendar signal error: ' . $e->getMessage());
		}
	}
}

if (!function_exists('build_google_booking_payload')) {
	/**
	 * Build a minimal payload snapshot for queueing
	 *
	 * @param array $bookingRow Booking row from DB
	 * @return array
	 */
	function build_google_booking_payload(array $bookingRow): array
	{
		return [
			'booking_id' => (int)($bookingRow['unic_id'] ?? 0),
			'client_full_name' => $bookingRow['client_full_name'] ?? null,
			'client_phone_nr' => $bookingRow['client_phone_nr'] ?? null,
			'booking_start_datetime' => $bookingRow['booking_start_datetime'] ?? null,
			'booking_end_datetime' => $bookingRow['booking_end_datetime'] ?? null,
			'id_specialist' => (int)($bookingRow['id_specialist'] ?? 0),
			'id_work_place' => (int)($bookingRow['id_work_place'] ?? 0),
			'service_id' => isset($bookingRow['service_id']) ? (int)$bookingRow['service_id'] : null,
			'received_through' => $bookingRow['received_through'] ?? null,
			'day_of_creation' => $bookingRow['day_of_creation'] ?? null,
			'unic_id' => (int)($bookingRow['unic_id'] ?? 0),
		];
	}
} 