<?php
/**
 * Setup database triggers for booking events
 */

require_once 'includes/db.php';

echo "=== Setting up Database Triggers for Real-time Updates ===\n\n";

// Read SQL file
$sql = file_get_contents('database/booking_triggers.sql');

// Execute each statement separately
$statements = [
    // Create event queue table
    "CREATE TABLE IF NOT EXISTS booking_event_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_type VARCHAR(20),
        specialist_id INT,
        working_point_id INT,
        booking_data JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed BOOLEAN DEFAULT FALSE,
        INDEX idx_processed (processed, created_at)
    )",
    
    // Drop existing triggers if they exist
    "DROP TRIGGER IF EXISTS booking_after_insert",
    "DROP TRIGGER IF EXISTS booking_after_update", 
    "DROP TRIGGER IF EXISTS booking_before_delete",
];

// Execute table creation and cleanup
foreach ($statements as $stmt) {
    try {
        echo "Executing: " . substr($stmt, 0, 50) . "...\n";
        $pdo->exec($stmt);
        echo "✓ Success\n\n";
    } catch (PDOException $e) {
        echo "✗ Error: " . $e->getMessage() . "\n\n";
    }
}

// Now create triggers with proper delimiter handling
$triggers = [
    'INSERT' => "
    CREATE TRIGGER booking_after_insert
    AFTER INSERT ON booking
    FOR EACH ROW
    BEGIN
        INSERT INTO booking_event_queue (event_type, specialist_id, working_point_id, booking_data)
        VALUES (
            'create',
            NEW.id_specialist,
            NEW.id_work_place,
            JSON_OBJECT(
                'booking_id', NEW.unic_id,
                'specialist_id', NEW.id_specialist,
                'working_point_id', NEW.id_work_place,
                'client_full_name', NEW.client_full_name,
                'booking_start_datetime', NEW.booking_start_datetime,
                'booking_end_datetime', NEW.booking_end_datetime
            )
        );
    END",
    
    'UPDATE' => "
    CREATE TRIGGER booking_after_update
    AFTER UPDATE ON booking
    FOR EACH ROW
    BEGIN
        INSERT INTO booking_event_queue (event_type, specialist_id, working_point_id, booking_data)
        VALUES (
            'update',
            NEW.id_specialist,
            NEW.id_work_place,
            JSON_OBJECT(
                'booking_id', NEW.unic_id,
                'specialist_id', NEW.id_specialist,
                'working_point_id', NEW.id_work_place,
                'client_full_name', NEW.client_full_name,
                'booking_start_datetime', NEW.booking_start_datetime,
                'booking_end_datetime', NEW.booking_end_datetime,
                'old_specialist_id', OLD.id_specialist,
                'old_working_point_id', OLD.id_work_place
            )
        );
    END",
    
    'DELETE' => "
    CREATE TRIGGER booking_before_delete
    BEFORE DELETE ON booking
    FOR EACH ROW
    BEGIN
        INSERT INTO booking_event_queue (event_type, specialist_id, working_point_id, booking_data)
        VALUES (
            'delete',
            OLD.id_specialist,
            OLD.id_work_place,
            JSON_OBJECT(
                'booking_id', OLD.unic_id,
                'specialist_id', OLD.id_specialist,
                'working_point_id', OLD.id_work_place,
                'client_full_name', OLD.client_full_name,
                'booking_start_datetime', OLD.booking_start_datetime,
                'booking_end_datetime', OLD.booking_end_datetime
            )
        );
    END"
];

foreach ($triggers as $type => $trigger) {
    try {
        echo "Creating $type trigger...\n";
        $pdo->exec($trigger);
        echo "✓ Success\n\n";
    } catch (PDOException $e) {
        echo "✗ Error: " . $e->getMessage() . "\n\n";
    }
}

echo "=== Testing Trigger ===\n";
echo "Inserting test booking...\n";

$stmt = $pdo->prepare("
    INSERT INTO booking (
        id_specialist, client_full_name, client_phone_nr,
        day_of_creation, booking_start_datetime, booking_end_datetime,
        id_work_place, service_id, received_through
    ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)
");

$stmt->execute([
    2, 'Trigger Test', '+1234567890',
    date('Y-m-d H:i:s', strtotime('+2 days')),
    date('Y-m-d H:i:s', strtotime('+2 days +1 hour')),
    1, 1, 'Trigger Test'
]);

$booking_id = $pdo->lastInsertId();
echo "Created booking ID: $booking_id\n\n";

// Check if event was queued
$stmt = $pdo->query("SELECT * FROM booking_event_queue WHERE processed = FALSE ORDER BY id DESC LIMIT 1");
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "✓ Event queued successfully!\n";
    print_r($event);
} else {
    echo "✗ No event found in queue\n";
}

echo "\n=== Setup Complete ===\n";
echo "To start processing events, either:\n";
echo "1. Run the worker: php workers/booking_event_worker.php\n";
echo "2. Add to crontab: * * * * * /usr/bin/php /srv/project_1/calendar/cron/process_booking_events.php\n";