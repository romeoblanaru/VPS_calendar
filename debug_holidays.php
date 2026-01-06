<?php
require_once 'includes/db.php';

echo "<h3>Workpoint Holidays in Database:</h3>";
$stmt = $pdo->query("SELECT * FROM workingpoint_time_off ORDER BY workingpoint_id, date_off");
$holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1'>";
echo "<tr><th>ID</th><th>Workpoint ID</th><th>Date Off</th><th>Start Time</th><th>End Time</th><th>Is Recurring</th><th>Description</th></tr>";
foreach ($holidays as $h) {
    echo "<tr>";
    echo "<td>" . $h['id'] . "</td>";
    echo "<td>" . $h['workingpoint_id'] . "</td>";
    echo "<td>" . $h['date_off'] . "</td>";
    echo "<td>" . $h['start_time'] . "</td>";
    echo "<td>" . $h['end_time'] . "</td>";
    echo "<td>" . $h['is_recurring'] . "</td>";
    echo "<td>" . htmlspecialchars($h['description']) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Working Points:</h3>";
$stmt = $pdo->query("SELECT unic_id, name_of_the_place FROM working_points");
$wps = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1'>";
echo "<tr><th>Unic ID</th><th>Name</th></tr>";
foreach ($wps as $wp) {
    echo "<tr>";
    echo "<td>" . $wp['unic_id'] . "</td>";
    echo "<td>" . htmlspecialchars($wp['name_of_the_place']) . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
