<?php
session_start();
include 'includes/session.php';
include 'includes/db.php';
include 'templates/navbar.php';

echo "<div class='container'>";
echo "<h3>Organisations</h3>";

// Add form
echo "<form method='POST' action='process_add_org.php'>
    <input type='text' name='oficial_company_name' placeholder='Company Name' required>
    <input type='text' name='contact_name' placeholder='Contact Name' required>
    <input type='email' name='email_address' placeholder='Email'>
    <input type='text' name='country' placeholder='Country'>
    <button type='submit'>Add Organisation</button>
</form><hr>";

// List orgs
$stmt = $pdo->query('SELECT * FROM organisations');
$orgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($orgs as $org) {
    echo "<div style='border:1px solid #ccc; padding:10px; margin:10px 0;'>";
    echo "<strong>{$org['oficial_company_name']}</strong><br>";
    echo "Contact: {$org['contact_name']} | Email: {$org['email_address']} | Country: {$org['country']}<br>";
    echo "<a href='delete_org.php?id={$org['unic_id']}' onclick='return confirm("Delete this organization?")'>Delete</a>";
    echo "</div>";
}
echo "</div>";

include 'templates/footer.php';
?>