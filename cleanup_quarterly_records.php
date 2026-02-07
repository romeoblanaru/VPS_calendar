#!/usr/bin/env php
<?php
/**
 * Quarterly Database Cleanup Script
 * Deletes records older than 100 days from specified tables
 * Run every 100 days via cron
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
    error_log("Quarterly cleanup script - Connection failed: " . $e->getMessage());
    exit(1);
}

// Tables to clean up with their specific timestamp columns
$tables = [
    'booking_canceled' => 'day_of_creation',
    'conversation_memory' => 'dat_time'
];

$retention_days = 100;

// Calculate cutoff date (100 days ago)
$cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

echo "=== Quarterly Database Cleanup Script ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "Deleting records older than: {$cutoff_date}\n";
echo "Retention period: {$retention_days} days\n\n";

$total_deleted = 0;

foreach ($tables as $table => $timestamp_column) {
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

            echo "Table '{$table}' (column: {$timestamp_column}): Deleted {$deleted} records\n";
            error_log("Quarterly cleanup - Table '{$table}': Deleted {$deleted} records older than {$cutoff_date}");
        } else {
            echo "Table '{$table}' (column: {$timestamp_column}): No old records to delete\n";
        }

    } catch (PDOException $e) {
        echo "ERROR in table '{$table}': " . $e->getMessage() . "\n";
        error_log("Quarterly cleanup script - Error in table '{$table}': " . $e->getMessage());
    }
}

echo "\n=== Quarterly Cleanup Complete ===\n";
echo "Total records deleted: {$total_deleted}\n";
echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
echo "Next cleanup will run in 100 days\n";

error_log("Quarterly cleanup script completed - Total deleted: {$total_deleted} records");

exit(0);
?>
