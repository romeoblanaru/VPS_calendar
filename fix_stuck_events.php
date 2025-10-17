<?php
// Script to fix stuck events in booking_event_queue

require_once __DIR__ . '/includes/db.php';

echo "Checking for stuck events...\n\n";

// Check unprocessed events
$stmt = $pdo->query("
    SELECT id, event_type, specialist_id, working_point_id, created_at
    FROM booking_event_queue 
    WHERE processed = FALSE 
    ORDER BY created_at ASC
");

$stuck_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($stuck_events) > 0) {
    echo "Found " . count($stuck_events) . " stuck events:\n";
    foreach ($stuck_events as $event) {
        echo "  Event ID: {$event['id']} - Type: {$event['event_type']} - Created: {$event['created_at']}\n";
    }
    
    echo "\nMarking all stuck events as processed...\n";
    $updateStmt = $pdo->prepare("UPDATE booking_event_queue SET processed = TRUE WHERE processed = FALSE");
    $updateStmt->execute();
    echo "✓ Marked " . $updateStmt->rowCount() . " events as processed\n";
} else {
    echo "No stuck events found!\n";
}

echo "\nCleaning up old processed events...\n";
$cleanupStmt = $pdo->prepare("
    DELETE FROM booking_event_queue 
    WHERE processed = TRUE 
    AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$cleanupStmt->execute();
echo "✓ Deleted " . $cleanupStmt->rowCount() . " old processed events\n";

echo "\nDone!\n";