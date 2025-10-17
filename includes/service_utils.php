<?php
function getServiceDuration($pdo, $service_id) {
    $stmt = $pdo->prepare("SELECT minutes_to_finish FROM services WHERE unic_id = ?");
    $stmt->execute([$service_id]);
    $row = $stmt->fetch();
    return $row ? (int)$row['minutes_to_finish'] : 0;
}

function calculateEndTime($start_datetime, $duration_minutes) {
    $start = new DateTime($start_datetime);
    $start->modify("+{$duration_minutes} minutes");
    return $start->format('Y-m-d H:i:s');
}
?>