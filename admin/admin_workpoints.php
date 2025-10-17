<?php
session_start();
include '../includes/session.php';
include __DIR__ . '/../includes/db.php';
include '../templates/navbar.php';

$role = $_SESSION['role'] ?? '';
$user = $_SESSION['user'] ?? '';

if (!in_array($role, ['super_user', 'organisation_user'])) {
    header('Location: ../index.php');
    exit;
}

echo "<div class='container'>";
echo "<h3>Working Points</h3>";

// Fetch organisations
$org_stmt = $pdo->query("SELECT * FROM organisations");
$orgs = $org_stmt->fetchAll(PDO::FETCH_ASSOC);

// Add form (only for super_user or organisation_user)
echo "<form method='POST' action='process_add_workpoint.php'>
    <input type='text' name='name_of_the_place' placeholder='Place Name' required>
    <input type='text' name='address' placeholder='Address' required>
    <input type='text' name='lead_person_name' placeholder='Lead Person' required>
    <input type='text' name='lead_person_phone_nr' placeholder='Phone' required>
    <input type='text' name='workplace_phone_nr' placeholder='Workplace Phone' required>
    <input type='text' name='booking_phone_nr' placeholder='Booking Phone' required>
    <input type='email' name='email' placeholder='Email (optional)'>
    <input type='text' id='simple_country_display' placeholder='Type to search countries...' autocomplete='off' style='width: 200px;' required>
    <input type='hidden' name='country' id='simple_country' required>
    <input type='text' name='language' placeholder='Language (e.g., EN)' maxlength='2' pattern='[a-zA-Z]{2}' required style='text-transform: uppercase;'>";

if ($role === 'super_user') {
    echo "<select name='organisation_id' required>
        <option value=''>Select Organisation</option>";
    foreach ($orgs as $org) {
        echo "<option value='{$org['unic_id']}'>{$org['oficial_company_name']}</option>";
    }
    echo "</select>";
} else {
    // Get organisation ID of current user
    $stmt = $pdo->prepare("SELECT * FROM organisations WHERE user = ?");
    $stmt->execute([$user]);
    $org = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<input type='hidden' name='organisation_id' value='{$org['unic_id']}'>";
    echo "<p>Organisation: {$org['oficial_company_name']}</p>";
}
echo "<button type='submit'>Add Workpoint</button>
</form><hr>";

// List all workpoints
if ($role === 'super_user') {
    $stmt = $pdo->query("SELECT wp.*, o.oficial_company_name FROM working_points wp
                         LEFT JOIN organisations o ON wp.organisation_id = o.unic_id");
} else {
    $stmt = $pdo->prepare("SELECT wp.*, o.oficial_company_name FROM working_points wp
                           LEFT JOIN organisations o ON wp.organisation_id = o.unic_id
                           WHERE o.user = ?");
    $stmt->execute([$user]);
}
$wps = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($wps as $wp) {
    echo "<div style='border:1px solid #ccc; padding:10px; margin:10px 0;'>";
    echo "<strong>{$wp['name_of_the_place']}</strong> ({$wp['oficial_company_name']})<br>";
    echo "Lead: {$wp['lead_person_name']} | Workplace Phone: {$wp['workplace_phone_nr']} | Booking Phone: {$wp['booking_phone_nr']}<br>";
    echo "Address: {$wp['address']}<br>";
    echo "<a href='delete_workpoint.php?id={$wp['unic_id']}' onclick='return confirm(\"Delete this workpoint?\")'>Delete</a>";
    echo "</div>";
}
echo "</div>";

?>

<script src="../includes/country_autocomplete.js"></script>
<script>
    // Initialize country autocomplete when page loads
    document.addEventListener('DOMContentLoaded', function() {
        createCountryAutocomplete('simple_country_display', 'simple_country');
    });
</script>

<?php include '../templates/footer.php'; ?>