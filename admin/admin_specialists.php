<?php
session_start();
include '../includes/session.php';
include __DIR__ . '/../includes/db.php';
include '../templates/navbar.php';

$role = $_SESSION['role'] ?? '';
$dual_role = $_SESSION['dual_role'] ?? '';
$user = $_SESSION['user'] ?? '';

if (!in_array($role, ['super_user', 'organisation_user', 'workpoint_supervisor'])) {
    header('Location: ../index.php');
    exit;
}

echo "<div class='container'><h3>Specialists</h3>";

// Fetch working points available to the user
if ($role === 'super_user') {
    $wp_stmt = $pdo->query("SELECT * FROM working_points");
} elseif ($role === 'organisation_user') {
    $org_stmt = $pdo->prepare("SELECT unic_id FROM organisations WHERE user = ?");
    $org_stmt->execute([$user]);
    $org = $org_stmt->fetch();
    $wp_stmt = $pdo->prepare("SELECT * FROM working_points WHERE organisation_id = ?");
    $wp_stmt->execute([$org['unic_id']]);
} elseif ($role === 'workpoint_supervisor' || $dual_role === 'workpoint_supervisor') {
    // Simulate: fetch the only working point tied to org
    $org_stmt = $pdo->prepare("SELECT unic_id FROM organisations WHERE user = ?");
    $org_stmt->execute([$user]);
    $org = $org_stmt->fetch();
    $wp_stmt = $pdo->prepare("SELECT * FROM working_points WHERE organisation_id = ?");
    $wp_stmt->execute([$org['unic_id']]);
}
$wps = $wp_stmt->fetchAll(PDO::FETCH_ASSOC);

// Specialist form
echo "<form method='POST' action='process_add_specialist.php'>
    <input type='text' name='name' placeholder='Name' required>
    <input type='text' name='speciality' placeholder='Speciality' required>
    <input type='email' name='email' placeholder='Email' required>
    <input type='text' name='phone_nr' placeholder='Phone (optional)'>";

if ($role === 'super_user' || $role === 'organisation_user') {
    echo "<label>Assign to Working Points:</label><br>";
    foreach ($wps as $wp) {
        echo "<input type='checkbox' name='working_points[]' value='{$wp['unic_id']}'> {$wp['name_of_the_place']}<br>";
    }
} else {
    // workpoint_supervisor sees only their workpoint
    $only_wp = $wps[0];
    echo "<input type='hidden' name='working_points[]' value='{$only_wp['unic_id']}'>";
    echo "<p>Assigned to: {$only_wp['name_of_the_place']}</p>";
}

echo "<button type='submit'>Add Specialist</button></form><hr>";

// Load specialists
if ($role === 'super_user') {
    $stmt = $pdo->query("SELECT * FROM specialists");
} elseif ($role === 'organisation_user') {
    $stmt = $pdo->prepare("SELECT * FROM specialists WHERE organisation_id = ?");
    $stmt->execute([$org['unic_id']]);
} elseif ($role === 'workpoint_supervisor' || $dual_role === 'workpoint_supervisor') {
    // Get specialists who work at this workpoint based on working_program table
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.* 
        FROM specialists s 
        INNER JOIN working_program wp ON s.unic_id = wp.specialist_id 
        WHERE wp.working_place_id = ?
    ");
    $stmt->execute([$only_wp['unic_id']]);
}
$specialists = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($specialists as $sp) {
    // Get working points for this specialist from working_program table
    $wp_stmt = $pdo->prepare("
        SELECT DISTINCT wp.name_of_the_place 
        FROM working_points wp 
        INNER JOIN working_program wpr ON wp.unic_id = wpr.working_place_id 
        WHERE wpr.specialist_id = ?
        ORDER BY wp.name_of_the_place
    ");
    $wp_stmt->execute([$sp['unic_id']]);
    $working_points = $wp_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<div style='border:1px solid #ccc; padding:10px; margin:10px 0;'>";
    echo "<strong>{$sp['name']}</strong> ({$sp['speciality']})<br>";
    echo "Email: {$sp['email']} | Phone: {$sp['phone_nr']}<br>";
    echo "<small>Working Points: " . implode(', ', $working_points) . "</small><br>";
    echo "<a href='delete_specialist.php?id={$sp['unic_id']}' onclick='return confirm(\"Delete this specialist?\")'>Delete</a>";
    echo "</div>";
}
echo "</div>";

include '../templates/footer.php';
?>
<!-- Schedule Modal -->
<div class='modal fade' id='scheduleModal' tabindex='-1' aria-labelledby='scheduleModalLabel' aria-hidden='true'>
  <div class='modal-dialog modal-lg'>
    <div class='modal-content'>
      <form method='POST' action='process_schedule.php'>
        <div class='modal-header'>
          <h5 class='modal-title' id='scheduleModalLabel'>Set Working Schedule</h5>
          <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
        </div>
        <div class='modal-body'>
          <input type='hidden' name='specialist_id' id='specialist_id'>
          <div class='mb-3'>
            <label>Select Workpoint</label>
            <select name='workpoint_id' id='workpoint_id' class='form-select' required>
              <!-- Workpoints will be populated via JavaScript -->
            </select>
          </div>
          <table class='table table-bordered'>
            <thead><tr><th>Day</th><th>Shift 1</th><th>Shift 2</th><th>Shift 3</th></tr></thead>
            <tbody>
              <tr><td>Mon</td><td><input type='time' name='Mon_1start'> to <input type='time' name='Mon_1end'></td><td><input type='time' name='Mon_2start'> to <input type='time' name='Mon_2end'></td><td><input type='time' name='Mon_3start'> to <input type='time' name='Mon_3end'></td></tr>
<tr><td>Tue</td><td><input type='time' name='Tue_1start'> to <input type='time' name='Tue_1end'></td><td><input type='time' name='Tue_2start'> to <input type='time' name='Tue_2end'></td><td><input type='time' name='Tue_3start'> to <input type='time' name='Tue_3end'></td></tr>
<tr><td>Wed</td><td><input type='time' name='Wed_1start'> to <input type='time' name='Wed_1end'></td><td><input type='time' name='Wed_2start'> to <input type='time' name='Wed_2end'></td><td><input type='time' name='Wed_3start'> to <input type='time' name='Wed_3end'></td></tr>
<tr><td>Thu</td><td><input type='time' name='Thu_1start'> to <input type='time' name='Thu_1end'></td><td><input type='time' name='Thu_2start'> to <input type='time' name='Thu_2end'></td><td><input type='time' name='Thu_3start'> to <input type='time' name='Thu_3end'></td></tr>
<tr><td>Fri</td><td><input type='time' name='Fri_1start'> to <input type='time' name='Fri_1end'></td><td><input type='time' name='Fri_2start'> to <input type='time' name='Fri_2end'></td><td><input type='time' name='Fri_3start'> to <input type='time' name='Fri_3end'></td></tr>
<tr><td>Sat</td><td><input type='time' name='Sat_1start'> to <input type='time' name='Sat_1end'></td><td><input type='time' name='Sat_2start'> to <input type='time' name='Sat_2end'></td><td><input type='time' name='Sat_3start'> to <input type='time' name='Sat_3end'></td></tr>
<tr><td>Sun</td><td><input type='time' name='Sun_1start'> to <input type='time' name='Sun_1end'></td><td><input type='time' name='Sun_2start'> to <input type='time' name='Sun_2end'></td><td><input type='time' name='Sun_3start'> to <input type='time' name='Sun_3end'></td></tr>
            </tbody>
          </table>
        </div>
        <div class='modal-footer'>
          <button type='submit' class='btn btn-primary'>Save Schedule</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openScheduleModal(specialistId, wpList) {
  document.getElementById('specialist_id').value = specialistId;
  const wpSelect = document.getElementById('workpoint_id');
  wpSelect.innerHTML = '';
  wpList.split(',').forEach(function(wp) {
    wpSelect.innerHTML += `<option value='${wp}'>Workpoint ID ${wp}</option>`;
  });
  new bootstrap.Modal(document.getElementById('scheduleModal')).show();
}
</script>
