#!/usr/bin/env php
<?php
/**
 * Alternative booking monitor that polls for changes
 * Since we can't use triggers due to MySQL restrictions
 */

chdir(dirname(__DIR__));

require_once 'includes/db.php';
require_once 'includes/nchan_publisher.php';

// Track last known state
$last_check = time();
$known_bookings = [];

// Initialize with current bookings
$stmt = $pdo->query("SELECT unic_id, id_specialist, id_work_place, updated FROM booking");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $known_bookings[$row['unic_id']] = $row['updated'];
}

echo "[" . date('Y-m-d H:i:s') . "] Booking monitor started. Tracking " . count($known_bookings) . " bookings\n";

while (true) {
    try {
        // Check for new or updated bookings
        $stmt = $pdo->prepare("
            SELECT b.*, 
                   s.name as specialist_name,
                   w.name as workplace_name
            FROM booking b
            LEFT JOIN specialist s ON b.id_specialist = s.id
            LEFT JOIN working_point w ON b.id_work_place = w.id
            WHERE b.day_of_creation >= ? 
               OR b.updated >= ?
            ORDER BY b.updated DESC
        ");
        
        $check_time = date('Y-m-d H:i:s', $last_check - 5); // 5 second overlap
        $stmt->execute([$check_time, $check_time]);
        
        $changes = [];
        $current_ids = [];
        
        while ($booking = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $current_ids[] = $booking['unic_id'];
            
            if (!isset($known_bookings[$booking['unic_id']])) {
                // New booking
                $changes[] = [
                    'type' => 'create',
                    'booking' => $booking
                ];
                $known_bookings[$booking['unic_id']] = $booking['updated'];
                
            } elseif ($known_bookings[$booking['unic_id']] != $booking['updated']) {
                // Updated booking
                $changes[] = [
                    'type' => 'update',
                    'booking' => $booking
                ];
                $known_bookings[$booking['unic_id']] = $booking['updated'];
            }
        }
        
        // Check for deleted bookings
        $all_ids = $pdo->query("SELECT unic_id FROM booking")->fetchAll(PDO::FETCH_COLUMN);
        $deleted_ids = array_diff(array_keys($known_bookings), $all_ids);
        
        foreach ($deleted_ids as $deleted_id) {
            $changes[] = [
                'type' => 'delete',
                'booking' => ['unic_id' => $deleted_id]
            ];
            unset($known_bookings[$deleted_id]);
        }
        
        // Publish changes
        foreach ($changes as $change) {
            $booking_data = [
                'booking_id' => $change['booking']['unic_id'],
                'specialist_id' => $change['booking']['id_specialist'] ?? null,
                'working_point_id' => $change['booking']['id_work_place'] ?? null,
                'client_full_name' => $change['booking']['client_full_name'] ?? null,
                'booking_start_datetime' => $change['booking']['booking_start_datetime'] ?? null,
                'booking_end_datetime' => $change['booking']['booking_end_datetime'] ?? null,
                'specialist_name' => $change['booking']['specialist_name'] ?? null,
                'workplace_name' => $change['booking']['workplace_name'] ?? null
            ];
            
            if (publishBookingEvent($change['type'], $booking_data)) {
                echo "[" . date('Y-m-d H:i:s') . "] Published {$change['type']} event for booking {$booking_data['booking_id']}\n";
            }
        }
        
        $last_check = time();
        
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
    }
    
    // Check every 2 seconds
    sleep(2);
}