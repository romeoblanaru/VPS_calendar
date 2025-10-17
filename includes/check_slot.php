<?php
function isSlotAvailable($pdo, $id_specialist, $id_work_place, $start_dt, $end_dt) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM booking
        WHERE id_specialist = ? AND id_work_place = ? AND (
            (booking_start_datetime < ? AND booking_end_datetime > ?) OR
            (booking_start_datetime >= ? AND booking_start_datetime < ?)
        )");
    $stmt->execute([$id_specialist, $id_work_place, $end_dt, $start_dt, $start_dt, $end_dt]);
    $count = $stmt->fetchColumn();
    return $count == 0;
}
?>