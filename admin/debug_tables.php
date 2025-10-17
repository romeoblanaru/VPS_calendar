<?php
session_start();
require_once '../includes/db.php';

// Check if supervisor mode
if (!isset($_SESSION['user_id'])) {
    die("Access denied");
}

try {
    // Get all tables in the database
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Available tables in database:</h3>";
    echo "<pre>";
    print_r($tables);
    echo "</pre>";
    
    // Look for tables that might contain bookings/appointments
    foreach ($tables as $table) {
        if (strpos($table, 'book') !== false || strpos($table, 'appoint') !== false || strpos($table, 'reserv') !== false) {
            echo "<h4>Structure of table: $table</h4>";
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<pre>";
            print_r($columns);
            echo "</pre>";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>