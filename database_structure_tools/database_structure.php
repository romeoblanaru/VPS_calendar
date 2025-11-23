<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Access denied");
}

header('Content-Type: text/plain; charset=utf-8');

try {
    // Get database name
    $dbname = $pdo->query("SELECT DATABASE()")->fetchColumn();
    
    $output = "# Database Structure for: $dbname\n\n";
    $output .= "Generated on: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $output .= "## Tables in Database\n\n";
    foreach ($tables as $index => $table) {
        $output .= ($index + 1) . ". $table\n";
    }
    $output .= "\n---\n\n";
    
    // Get structure for each table
    foreach ($tables as $table) {
        $output .= "## Table: `$table`\n\n";
        
        // Get column information
        $columns = $pdo->query("SHOW FULL COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        
        $output .= "| Field | Type | Null | Key | Default | Extra | Comment |\n";
        $output .= "|-------|------|------|-----|---------|-------|---------||\n";
        
        foreach ($columns as $column) {
            $output .= "| " . $column['Field'] . " | " . $column['Type'] . " | " . $column['Null'] . " | " . $column['Key'] . " | " . ($column['Default'] ?? 'NULL') . " | " . $column['Extra'] . " | " . $column['Comment'] . " |\n";
        }
        
        $output .= "\n";
        
        // Get indexes
        $indexes = $pdo->query("SHOW INDEX FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($indexes)) {
            $output .= "### Indexes:\n";
            $indexGroups = [];
            foreach ($indexes as $index) {
                $indexGroups[$index['Key_name']][] = $index;
            }
            
            foreach ($indexGroups as $indexName => $indexCols) {
                $unique = $indexCols[0]['Non_unique'] == 0 ? 'UNIQUE' : '';
                $cols = array_map(function($col) { return $col['Column_name']; }, $indexCols);
                $output .= "- `$indexName` $unique (" . implode(', ', $cols) . ")\n";
            }
            $output .= "\n";
        }
        
        // Get row count
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $output .= "**Row Count:** $count\n\n";
        
        // For key tables, show sample data structure
        if (in_array($table, ['booking', 'specialists', 'working_points', 'organisations', 'working_program', 'specialist_time_off'])) {
            $output .= "### Sample Data (first 3 rows):\n```\n";
            $sample = $pdo->query("SELECT * FROM `$table` LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($sample)) {
                // Show column headers
                $output .= implode(' | ', array_keys($sample[0])) . "\n";
                // Show data
                foreach ($sample as $row) {
                    $values = array_map(function($val) { 
                        return is_null($val) ? 'NULL' : substr(str_replace(["\n", "\r"], ' ', $val), 0, 20); 
                    }, array_values($row));
                    $output .= implode(' | ', $values) . "\n";
                }
            }
            $output .= "```\n\n";
        }
        
        $output .= "---\n\n";
    }
    
    // Save to markdown file
    file_put_contents('DATABASE_STRUCTURE.md', $output);
    
    echo "Database structure has been documented in DATABASE_STRUCTURE.md\n\n";
    echo $output;
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>