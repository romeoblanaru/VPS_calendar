<?php
/**
 * Booking Changes Cleanup Script
 * Maintains the booking_changes and client_last_check tables by removing old records
 * Should be run as a daily cron job to prevent table bloat
 */

require_once '../includes/db.php';
require_once '../includes/session.php';

// Check if user has admin privileges (if run via web)
if (isset($_SESSION['user']) && $_SESSION['role'] !== 'admin_user') {
    die('Access denied. Admin privileges required.');
}

try {
    $pdo->beginTransaction();
    
    // Clean up old booking changes (keep last 7 days)
    $stmt = $pdo->prepare("DELETE FROM booking_changes WHERE change_timestamp < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $changes_deleted = $stmt->rowCount();
    
    // Clean up old client check records (keep last 24 hours)
    $stmt = $pdo->prepare("DELETE FROM client_last_check WHERE last_check_timestamp < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $stmt->execute();
    $clients_deleted = $stmt->rowCount();
    
    // Optimize tables after cleanup
    $pdo->exec("OPTIMIZE TABLE booking_changes");
    $pdo->exec("OPTIMIZE TABLE client_last_check");
    
    $pdo->commit();
    
    $message = "Cleanup completed successfully:\n";
    $message .= "- Deleted {$changes_deleted} old booking change records\n";
    $message .= "- Deleted {$clients_deleted} old client check records\n";
    $message .= "- Tables optimized\n";
    $message .= "- Timestamp: " . date('Y-m-d H:i:s') . "\n";
    
    // Log the cleanup
    error_log("[Booking Changes Cleanup] " . str_replace("\n", " | ", $message));
    
    // If run via web, show results
    if (isset($_SESSION['user'])) {
        echo "<pre>" . htmlspecialchars($message) . "</pre>";
    } else {
        // If run via CLI, output to console
        echo $message;
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    $error_message = "Cleanup failed: " . $e->getMessage();
    error_log("[Booking Changes Cleanup Error] " . $error_message);
    
    if (isset($_SESSION['user'])) {
        echo "<pre>Error: " . htmlspecialchars($error_message) . "</pre>";
    } else {
        echo "Error: " . $error_message . "\n";
    }
    
    exit(1);
}
?> 