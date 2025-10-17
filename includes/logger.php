<?php
function log_action($pdo, $user, $action_type, $table_name, $record_id, $sql_query, $old_data = null) {
    $stmt = $pdo->prepare('INSERT INTO logs (user, action_time, action_type, table_name, record_id, sql_query, old_data)
                           VALUES (?, NOW(), ?, ?, ?, ?, ?)');
    $stmt->execute([
        $user,
        $action_type,
        $table_name,
        $record_id,
        $sql_query,
        json_encode($old_data)
    ]);
}
?>