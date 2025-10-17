#!/usr/bin/env php
<?php
/**
 * Cron job to process booking events
 * Run every minute: * * * * * /usr/bin/php /srv/project_1/calendar/cron/process_booking_events.php
 */

chdir(dirname(__DIR__));

require_once 'includes/db.php';
require_once 'includes/nchan_publisher.php';

// Process all pending events
$stmt = $pdo->prepare("
    SELECT * FROM booking_event_queue 
    WHERE processed = FALSE 
    ORDER BY created_at ASC 
    LIMIT 1000
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$processed = 0;
foreach ($events as $event) {
    $booking_data = json_decode($event['booking_data'], true);
    
    if (publishBookingEvent($event['event_type'], $booking_data)) {
        $updateStmt = $pdo->prepare("UPDATE booking_event_queue SET processed = TRUE WHERE id = ?");
        $updateStmt->execute([$event['id']]);
        $processed++;
    }
}

if ($processed > 0) {
    echo date('Y-m-d H:i:s') . " - Processed $processed events\n";
}

// Cleanup old events
$pdo->exec("DELETE FROM booking_event_queue WHERE processed = TRUE AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");