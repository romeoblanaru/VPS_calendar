#!/usr/bin/env php
<?php
/**
 * Worker process that monitors booking_event_queue table
 * and publishes events to nchan
 * 
 * Run this as a background service or cron job
 */

// Change to calendar directory
chdir(dirname(__DIR__));

require_once 'includes/db.php';
require_once 'includes/nchan_publisher.php';

// Configuration
$sleep_interval = 1; // Check every 1 second
$batch_size = 100;   // Process up to 100 events at once

echo "[" . date('Y-m-d H:i:s') . "] Booking event worker started\n";

// Main loop
while (true) {
    try {
        // Fetch unprocessed events
        $stmt = $pdo->prepare("
            SELECT * FROM booking_event_queue 
            WHERE processed = FALSE 
            ORDER BY created_at ASC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $batch_size, PDO::PARAM_INT);
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($events) > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Processing " . count($events) . " events\n";
            
            foreach ($events as $event) {
                // Decode booking data
                $booking_data = json_decode($event['booking_data'], true);
                
                // Publish to nchan
                $published = publishBookingEvent($event['event_type'], $booking_data);
                
                // Mark as processed regardless of publish result to prevent infinite loops
                // The specialist channel is the most important one, and it's succeeding
                $updateStmt = $pdo->prepare("UPDATE booking_event_queue SET processed = TRUE WHERE id = ?");
                $updateStmt->execute([$event['id']]);
                
                if ($published) {
                    echo "  ✓ Published {$event['event_type']} event for booking {$booking_data['booking_id']}\n";
                } else {
                    echo "  ⚠ Partial publish failure for event {$event['id']} (marking as processed to prevent loop)\n";
                }
            }
        }
        
        // Clean up old processed events (older than 1 hour)
        $cleanupStmt = $pdo->prepare("
            DELETE FROM booking_event_queue 
            WHERE processed = TRUE 
            AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $cleanupStmt->execute();
        
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
    }
    
    // Sleep before next check
    sleep($sleep_interval);
}