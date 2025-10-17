<?php
session_start();
include __DIR__ . '/../includes/db.php';
include '../includes/logger.php';

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM organisations WHERE unic_id = ?');
    $stmt->execute([$_GET['id']]);
    $old_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('DELETE FROM organisations WHERE unic_id = ?');
    $stmt->execute([$_GET['id']]);

    log_action($pdo, $_SESSION['user'], 'delete', 'organisations', $_GET['id'], $stmt->queryString, $old_data);
}

header('Location: admin_organisations.php');
exit;
?>