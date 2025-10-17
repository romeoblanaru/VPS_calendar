<?php
session_start();
include '../includes/session.php';
include __DIR__ . '/../includes/db.php';
include '../templates/navbar.php';

echo "<div class='container'><h3>Change Logs</h3>";

$stmt = $pdo->query("SELECT * FROM logs ORDER BY action_time DESC LIMIT 50");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'><tr>
<th>ID</th><th>User</th><th>Time</th><th>Action</th>
<th>Table</th><th>Record ID</th><th>Revert</th></tr>";

foreach ($logs as $log) {
    echo "<tr>
        <td>{$log['id']}</td>
        <td>{$log['user']}</td>
        <td>{$log['action_time']}</td>
        <td>{$log['action_type']}</td>
        <td>{$log['table_name']}</td>
        <td>{$log['record_id']}</td>
        <td><a href='revert_log.php?id={$log['id']}'>Revert</a></td>
    </tr>";
}
echo "</table></div>";

include '../templates/footer.php';
?>