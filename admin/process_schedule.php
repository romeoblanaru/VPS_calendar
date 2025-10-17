<?php
session_start();
include __DIR__ . '/../includes/db.php';
include '../includes/logger.php';

$spec_id = $_POST['specialist_id'];
$wp_id = $_POST['workpoint_id'];

$days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
foreach ($days as $day) {
    $stmt = $pdo->prepare("REPLACE INTO working_program (specialist_id, working_place_id, day_of_week,
        shift1_start, shift1_end, shift2_start, shift2_end, shift3_start, shift3_end)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $spec_id,
        $wp_id,
        $day,
        $_POST[$day.'_1start'],
        $_POST[$day.'_1end'],
        $_POST[$day.'_2start'] ?? null,
        $_POST[$day.'_2end'] ?? null,
        $_POST[$day.'_3start'] ?? null,
        $_POST[$day.'_3end'] ?? null
    ]);
    log_action($pdo, $_SESSION['user'], 'update', 'working_program', $spec_id, $stmt->queryString);
}

header("Location: admin_specialists.php");
exit;
?>