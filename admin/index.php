<?php
session_start();
include '../includes/session.php';
include __DIR__ . '/../includes/db.php';
include '../templates/navbar.php';

echo "<div class='container'>";
echo "<h2>Admin Panel</h2>";
echo "<p>Welcome, " . $_SESSION['user'] . "</p>";

echo "<ul>
    <li><a href='admin_organisations.php'>Manage Organisations</a></li>
    <li><a href='admin_workpoints.php'>Manage Working Points</a></li>
    <li><a href='admin_specialists.php'>Manage Specialists</a></li>
    <li><a href='admin_services.php'>Manage Services</a></li>
    <li><a href='admin_logs.php'><strong>View Logs & Revert Changes</strong></a></li>
</ul>";
echo "</div>";

include '../templates/footer.php';
?>