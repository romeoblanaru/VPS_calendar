#!/usr/bin/env php
<?php
/**
 * Database Cleanup Script
 * Deletes records older than 6 days from specified tables
 * Run weekly on Sunday nights via cron
 */

// Database connection
$host = 'localhost';
$db = 'nuuitasi_calendar4';
$user = 'nuuitasi_calendar';
$pass = 'Romeo_calendar1202';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database cleanup script - Connection failed: " . $e->getMessage());
    exit(1);
}

// Tables to clean up with their timestamp column
$tables = [
    'webhook_logs',
    'booking_sms_queue',
    'gcal_worker_signals',
    'google_calendar_sync_queue'
];

$timestamp_column = 'created_at';
$retention_days = 6;

// Calculate cutoff date (6 days ago)
$cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

echo "=== Database Cleanup Script ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "Deleting records older than: {$cutoff_date}\n\n";

$total_deleted = 0;

foreach ($tables as $table) {
    try {
        // Count records to be deleted
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$timestamp_column}` < ?");
        $count_stmt->execute([$cutoff_date]);
        $count = $count_stmt->fetchColumn();

        if ($count > 0) {
            // Delete old records
            $delete_stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE `{$timestamp_column}` < ?");
            $delete_stmt->execute([$cutoff_date]);

            $deleted = $delete_stmt->rowCount();
            $total_deleted += $deleted;

            echo "Table '{$table}': Deleted {$deleted} records\n";
            error_log("Database cleanup - Table '{$table}': Deleted {$deleted} records older than {$cutoff_date}");
        } else {
            echo "Table '{$table}': No old records to delete\n";
        }

    } catch (PDOException $e) {
        echo "ERROR in table '{$table}': " . $e->getMessage() . "\n";
        error_log("Database cleanup script - Error in table '{$table}': " . $e->getMessage());
    }
}

echo "\n=== Cleanup Complete ===\n";
echo "Total records deleted: {$total_deleted}\n";
echo "Finished at: " . date('Y-m-d H:i:s') . "\n";

error_log("Database cleanup script completed - Total deleted: {$total_deleted} records");

exit(0);
?>
