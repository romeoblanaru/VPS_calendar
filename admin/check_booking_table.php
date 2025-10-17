<?php
session_start();
require_once '../includes/db.php';

// Simple security check
if (!isset($_SESSION['user_id'])) {
    die("Access denied");
}

try {
    echo "<h3>Booking Table Structure:</h3>";
    
    // Get columns from booking table
    $stmt = $pdo->query("SHOW COLUMNS FROM booking");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show sample data
    echo "<h3>Sample Booking Data (first 5 rows):</h3>";
    $stmt = $pdo->query("SELECT * FROM booking LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($rows)) {
        echo "<table border='1'>";
        // Headers
        echo "<tr>";
        foreach (array_keys($rows[0]) as $key) {
            echo "<th>$key</th>";
        }
        echo "</tr>";
        
        // Data
        foreach ($rows as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars(substr($value ?? '', 0, 50)) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Also check specialist_time_off table
    echo "<h3>Specialist Time Off Table Structure:</h3>";
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM specialist_time_off");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (PDOException $e) {
        echo "specialist_time_off table not found: " . $e->getMessage();
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>