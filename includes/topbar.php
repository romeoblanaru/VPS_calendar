<?php
if (!isset($_SESSION)) session_start();
$user_role = $_SESSION['role'] ?? '';
$user_name = $_SESSION['user'] ?? 'User';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">📅 Booking Panel</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">

<?php if ($user_role === 'admin_user'): ?>
        <li class="nav-item"><a class="nav-link" href="admin/admin_dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="logs.php">Logs</a></li>
<?php endif; ?>

<?php if ($user_role === 'organisation_user'): ?>
        <li class="nav-item"><a class="nav-link" href="organisation_dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="services_organisation_manage.php">Services</a></li>
        <li class="nav-item"><a class="nav-link" href="organisation_specialists.php">Specialists</a></li>
        <li class="nav-item"><a class="nav-link" href="organisation_shifts.php">Shifts</a></li>
<?php endif; ?>

<?php if ($user_role === 'workpoint_user'): ?>
        <li class="nav-item"><a class="nav-link" href="workpoint_supervisor_dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="services_manage.php">Services</a></li>
<?php endif; ?>

<?php if ($user_role === 'specialist_user'): ?>
        <li class="nav-item"><a class="nav-link" href="specialist_calendar.php">My Calendar</a></li>
<?php endif; ?>

      </ul>
      <span class="navbar-text text-white me-3">
        Logged in as: <strong><?php echo htmlspecialchars($user_name); ?></strong>
      </span>
      <a class="btn btn-sm btn-outline-light" href="logout.php">Logout</a>
    </div>
  </div>
</nav>
