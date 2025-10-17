<?php
session_start();
include '../includes/session.php';
include __DIR__ . '/../includes/db.php';

$log_id = $_GET['id'] ?? '';
if (!$log_id) {
    die("Log ID required.");
}

$stmt = $pdo->prepare("SELECT * FROM logs WHERE id = ?");
$stmt->execute([$log_id]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    die("Log not found.");
}

$table = $log['table_name'];
$record_id = $log['record_id'];
$old_data = json_decode($log['old_data'], true);

if ($log['action_type'] === 'delete') {
    $columns = implode(', ', array_keys($old_data));
    $placeholders = implode(', ', array_fill(0, count($old_data), '?'));
    $stmt = $pdo->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");
    $stmt->execute(array_values($old_data));
} elseif ($log['action_type'] === 'update') {
    $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($old_data)));
    $stmt = $pdo->prepare("UPDATE $table SET $sets WHERE unic_id = ?");
    $params = array_values($old_data);
    $params[] = $record_id;
    $stmt->execute($params);
}

header("Location: admin_logs.php");
exit;
?>