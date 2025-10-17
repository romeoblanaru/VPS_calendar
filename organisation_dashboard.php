<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/lang_loader.php';

// Check if user is logged in and has organisation_user role
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'organisation_user') {
    header('Location: login.php');
    exit;
}

// Get organisation details
$stmt = $pdo->prepare("SELECT * FROM organisations WHERE user = ?");
$stmt->execute([$_SESSION['user']]);
$organisation = $stmt->fetch();

if (!$organisation) {
    header('Location: login.php');
    exit;
}

// Get all workpoints for this organisation
$stmt = $pdo->prepare("SELECT * FROM working_points WHERE organisation_id = ? ORDER BY name_of_the_place");
$stmt->execute([$organisation['unic_id']]);
$workpoints = $stmt->fetchAll();

// Get all specialists for this organisation
$stmt = $pdo->prepare("SELECT * FROM specialists WHERE organisation_id = ? ORDER BY name");
$stmt->execute([$organisation['unic_id']]);
$specialists = $stmt->fetchAll();

// Get statistics for each workpoint
$workpoint_stats = [];
foreach ($workpoints as $workpoint) {
    // Count specialists assigned to this workpoint with at least one non-zero shift
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT specialist_id) as specialist_count 
        FROM working_program 
        WHERE working_place_id = ?
          AND ((shift1_start <> '00:00:00' AND shift1_end <> '00:00:00')
            OR (shift2_start <> '00:00:00' AND shift2_end <> '00:00:00')
            OR (shift3_start <> '00:00:00' AND shift3_end <> '00:00:00'))
    ");
    $stmt->execute([$workpoint['unic_id']]);
    $specialist_count = $stmt->fetchColumn();
    
    // Count unassigned specialists (no non-zero shifts at this workpoint)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unassigned_count 
        FROM specialists s 
        WHERE s.organisation_id = ? 
          AND NOT EXISTS (
            SELECT 1 FROM working_program wp
            WHERE wp.working_place_id = ?
              AND wp.specialist_id = s.unic_id
              AND ((wp.shift1_start <> '00:00:00' AND wp.shift1_end <> '00:00:00')
                OR (wp.shift2_start <> '00:00:00' AND wp.shift2_end <> '00:00:00')
                OR (wp.shift3_start <> '00:00:00' AND wp.shift3_end <> '00:00:00'))
          )
    ");
    $stmt->execute([$organisation['unic_id'], $workpoint['unic_id']]);
    $unassigned_count = $stmt->fetchColumn();
    
    // Count upcoming bookings (future bookings)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as upcoming_count 
        FROM booking 
        WHERE id_work_place = ? 
        AND booking_start_datetime > NOW()
    ");
    $stmt->execute([$workpoint['unic_id']]);
    $upcoming_count = $stmt->fetchColumn();
    
    // Count past bookings in last 30 days
    $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as past_count 
        FROM booking 
        WHERE id_work_place = ? 
        AND booking_start_datetime BETWEEN ? AND NOW()
    ");
    $stmt->execute([$workpoint['unic_id'], $thirty_days_ago]);
    $past_count = $stmt->fetchColumn();
    
    $workpoint_stats[$workpoint['unic_id']] = [
        'specialist_count' => $specialist_count,
        'unassigned_count' => $unassigned_count,
        'upcoming_count' => $upcoming_count,
        'past_count' => $past_count
    ];
}

// Get specialist statistics per workpoint
$specialist_stats = [];
foreach ($workpoints as $workpoint) {
    $stmt = $pdo->prepare("
        SELECT 
            s.unic_id,
            s.name,
            s.speciality,
            COUNT(CASE WHEN b.booking_start_datetime >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') 
                        AND b.booking_start_datetime < DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 END) AS last_month_bookings,
            COUNT(CASE WHEN b.booking_start_datetime >= DATE_FORMAT(CURDATE(), '%Y-%m-01') 
                        AND b.booking_start_datetime < DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') THEN 1 END) AS this_month_bookings,
            COUNT(CASE WHEN b.booking_start_datetime > NOW() THEN 1 END) AS upcoming_bookings
        FROM specialists s
        LEFT JOIN working_program wp ON s.unic_id = wp.specialist_id AND wp.working_place_id = ?
        LEFT JOIN booking b ON s.unic_id = b.id_specialist AND b.id_work_place = ?
        WHERE s.organisation_id = ? AND wp.working_place_id = ?
        GROUP BY s.unic_id, s.name, s.speciality
        ORDER BY s.name
    ");
    $stmt->execute([$workpoint['unic_id'], $workpoint['unic_id'], $organisation['unic_id'], $workpoint['unic_id']]);
    $specialist_stats[$workpoint['unic_id']] = $stmt->fetchAll();
}

// Organisation-wide unassigned specialists (not assigned to any workpoint)
$stmt = $pdo->prepare("
    SELECT s.name, s.speciality
    FROM specialists s
    WHERE s.organisation_id = ?
      AND s.unic_id NOT IN (
        SELECT DISTINCT specialist_id FROM working_program
      )
    ORDER BY s.name
");
$stmt->execute([$organisation['unic_id']]);
$org_unassigned = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organisation Dashboard - <?= htmlspecialchars($organisation['oficial_company_name']) ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .dashboard-header {
            background: #393e46;
            color: #fff;
            padding: 24px 40px 16px 40px;
            margin-bottom: 30px;
        }
        .dashboard-header h1 {
            margin: 0 0 6px 0;
            font-size: 2.1em;
        }
        .dashboard-header p {
            margin: 0;
            opacity: 0.9;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        /* Extra outer spacing for first and last widget */
        .stats-grid .stat-card:first-child { margin-left: 30px; }
        .stats-grid .stat-card:last-child { margin-right: 30px; }
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #00adb5;
            position: relative;
        }
        .stat-card h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.2em;
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #00adb5;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        .stat-breakdown {
            margin-top: 8px;
            color: #666;
            font-size: 0.85em;
            line-height: 1.3;
        }
        .stat-breakdown-box {
            position: absolute;
            left: 50%;
            top: 45%;
            transform: translate(-50%, -50%);
            background: #f8f9fa;
            border: 1px solid #eee;
            padding: 8px 10px;
            border-radius: 6px;
            max-width: 85%;
            text-align: left;
            font-size: 0.85em;
            color: #555;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        .workpoint-section {
            background: #fff;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .workpoint-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 12px 16px;
        }
        .workpoint-header h3 {
            margin: 0;
            color: #333;
            flex: 1 1 auto;
        }
        .wp-name-editable {
            cursor: pointer;
        }
        .wp-name-editable:hover {
            text-decoration: underline;
            color: #0056b3;
        }
        .workpoint-subtitle {
            margin-top: 2px;
            color: #666;
            font-size: 0.9em;
            line-height: 1.4;
            flex: 1 1 100%;
            overflow: visible;
        }
        .workpoint-actions {
            display: flex;
            gap: 10px;
            flex: 0 0 auto;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }
        .btn-primary {
            background: #00adb5;
            color: #fff;
        }
        .btn-primary:hover {
            background: #008a91;
        }
        .btn-secondary {
            background: #6c757d;
            color: #fff;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-danger {
            background: #dc3545;
            color: #fff;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .workpoint-content {
            padding: 20px;
        }
        .workpoint-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .workpoint-stat {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .workpoint-stat-number {
            font-size: 1.8em;
            font-weight: bold;
            color: #00adb5;
        }
        .workpoint-stat-label {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .specialists-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .specialists-table th,
        .specialists-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            font-size: 0.9em;
        }
        .specialists-table th.text-center,
        .specialists-table td.text-center {
            text-align: center;
        }
        .specialists-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .specialists-table tr:hover {
            background: #f8f9fa;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #000;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        /* Specialist Schedule Management Styles */
        .specialist-schedule-card {
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .specialist-schedule-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .specialist-header h5 {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .schedule-table-container {
            overflow-x: auto;
            margin: 15px 0;
        }
        
        .schedule-table-container table {
            margin-bottom: 0;
            width: 100%;
        }
        
        .specialist-actions {
            border-top: 1px solid #dee2e6;
            padding-top: 15px;
        }
        
        .specialist-actions .btn {
            margin-left: 10px;
        }
        
        .table th, .table td {
            vertical-align: middle;
            text-align: center;
        }
        
        .table th:first-child {
            text-align: left;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .alert {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .unassigned-specialists {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        .unassigned-specialists h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }
        .unassigned-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .unassigned-list li {
            padding: 5px 0;
            border-bottom: 1px solid #ffeaa7;
        }
        .unassigned-list li:last-child {
            border-bottom: none;
        }
        
        /* Add new styles for workpoint container */
        .workpoints-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2%;
            width: 100%;
        }
        
        .workpoint-box {
            width: 47%;
            margin-bottom: 20px;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            overflow: hidden;
        }
        
        /* For single workpoint, center it */
        .workpoint-box:only-child {
            margin-right: auto;
            margin-left: auto;
        }
        
        @media (max-width: 1200px) {
            .workpoint-box {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'templates/navbar.php'; ?>
    
    <div class="dashboard-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
        <div>
            <h1><?= htmlspecialchars($organisation['oficial_company_name']) ?> - Organisation Dashboard</h1>
            <p>Manage your workpoints, specialists, and view booking statistics</p>
        </div>
        <div>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>

    <!-- Modify Schedule Modal (reused supervisor design) -->
    <div id="modifyScheduleModal" class="modify-modal-overlay" style="display:none;">
        <div class="modify-modal">
            <div class="modify-modal-header">
                <h3 id="modifyScheduleTitle">📋 MODIFY SCHEDULE</h3>
                <span class="modify-modal-close" onclick="closeModifyScheduleModal()">&times;</span>
            </div>
            <div class="modify-modal-body">
                <form id="modifyScheduleForm">
                    <input type="hidden" id="modifyScheduleSpecialistId" name="specialist_id">
                    <input type="hidden" id="modifyScheduleWorkpointId" name="workpoint_id">
                    <div class="individual-edit-section">
                        <h4 style="font-size: 14px;">📋 Working Schedule</h4>
                        <div class="schedule-editor-table-container">
                            <table class="schedule-editor-table">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th colspan="3">Shift 1</th>
                                        <th colspan="3">Shift 2</th>
                                        <th colspan="3">Shift 3</th>
                                    </tr>
                                    <tr>
                                        <th></th>
                                        <th>Start</th>
                                        <th>End</th>
                                        <th></th>
                                        <th>Start</th>
                                        <th>End</th>
                                        <th></th>
                                        <th>Start</th>
                                        <th>End</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="modifyScheduleEditorTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="individual-edit-section">
                        <h4 style="font-size: 14px; margin-bottom: 10px;">⚡ Quick Options</h4>
                        <div class="schedule-editor-table-container">
                            <div class="quick-options-compact">
                                <div class="quick-options-row">
                                    <div class="quick-option-group">
                                        <select id="modifyQuickOptionsDaySelect" class="form-control" style="font-size: 11px; width: 66px;">
                                            <option value="mondayToFriday">Mon-Fri</option>
                                            <option value="saturday">Saturday</option>
                                            <option value="sunday">Sunday</option>
                                        </select>
                                    </div>
                                    <div class="quick-option-group">
                                        <label style="font-size: 11px; margin-right: 2px; text-align: right; min-width: 50px; display: inline-block;">Shift 1:</label>
                                        <div class="time-inputs">
                                            <input type="time" id="modifyShift1Start" class="form-control" placeholder="S">
                                            <input type="time" id="modifyShift1End" class="form-control" placeholder="E">
                                        </div>
                                    </div>
                                    <div class="quick-option-group">
                                        <label style="font-size: 11px; margin-right: 2px; text-align: right; min-width: 50px; display: inline-block;">Shift 2:</label>
                                        <div class="time-inputs">
                                            <input type="time" id="modifyShift2Start" class="form-control" placeholder="S">
                                            <input type="time" id="modifyShift2End" class="form-control" placeholder="E">
                                        </div>
                                    </div>
                                    <div class="quick-option-group">
                                        <label style="font-size: 11px; margin-right: 2px; text-align: right; min-width: 50px; display: inline-block;">Shift 3:</label>
                                        <div class="time-inputs">
                                            <input type="time" id="modifyShift3Start" class="form-control" placeholder="S">
                                            <input type="time" id="modifyShift3End" class="form-control" placeholder="E">
                                        </div>
                                    </div>
                                    <div class="quick-option-group">
                                        <button type="button" onclick="applyModifyAllShifts()" style="background: #007bff; color: white; border: none; padding: 4px 12px; border-radius: 4px; font-size: 11px; cursor: pointer;">Apply</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="modifyScheduleError" class="modify-error" style="display: none;"></div>
                    <div class="modify-modal-buttons">
                        <button type="button" class="btn-modify" onclick="updateScheduleFromModal()">Update Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Overall Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Workpoints</h3>
                <div class="stat-number"><?= count($workpoints) ?></div>
                <div class="stat-label">Active locations</div>
            </div>
            <div class="stat-card">
                <h3>Total Specialists</h3>
                <div class="stat-number"><?= count($specialists) ?></div>
                <div class="stat-label">Registered professionals</div>
            </div>
            <div class="stat-card">
                <h3>Total Upcoming Bookings</h3>
                <div class="stat-number">
                    <?php
                    $total_upcoming = 0;
                    foreach ($workpoint_stats as $stats) {
                        $total_upcoming += $stats['upcoming_count'];
                    }
                    echo $total_upcoming;
                    ?>
                </div>
                <div class="stat-label">Future appointments</div>
                <?php if (count($workpoints) > 1): ?>
                    <div class="stat-breakdown-box">
                        <?php foreach ($workpoints as $wp): ?>
                            <?php $cnt = (int)($workpoint_stats[$wp['unic_id']]['upcoming_count'] ?? 0); ?>
                            <?php if ($cnt > 0): ?>
                                <div><?= $cnt ?> for <?= htmlspecialchars($wp['name_of_the_place']) ?></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <h3>Bookings Last 30 Days</h3>
                <div class="stat-number">
                    <?php
                    $total_past = 0;
                    foreach ($workpoint_stats as $stats) {
                        $total_past += $stats['past_count'];
                    }
                    echo $total_past;
                    ?>
                </div>
                <div class="stat-label">Past appointments</div>
                <?php if (count($workpoints) > 1): ?>
                    <div class="stat-breakdown-box">
                        <?php foreach ($workpoints as $wp): ?>
                            <?php $cnt = (int)($workpoint_stats[$wp['unic_id']]['past_count'] ?? 0); ?>
                            <?php if ($cnt > 0): ?>
                                <div><?= $cnt ?> for <?= htmlspecialchars($wp['name_of_the_place']) ?></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Workpoints Management -->
        <div class="workpoint-section">
            <div class="workpoint-header">
                <h3>Workpoints Management</h3>
                <div class="workpoint-actions">
                    <button class="btn btn-primary" onclick="openAddWorkpointModal()">Add New Workpoint</button>
                </div>
            </div>
            <div class="workpoint-content">
                <?php if (empty($workpoints)): ?>
                    <p>No workpoints found. Add your first workpoint to get started.</p>
                <?php else: ?>
                    <div class="workpoints-container">
                        <?php foreach ($workpoints as $workpoint): ?>
                            <div class="workpoint-box">
                                <div class="workpoint-header">
                                    <h3 onclick="editWorkpoint(<?= $workpoint['unic_id'] ?>)" title="click to edit details" class="wp-name-editable"><?= htmlspecialchars($workpoint['name_of_the_place']) ?></h3>
                                    <div class="workpoint-actions">
                                        <a href="workpoint_supervisor_dashboard.php?workpoint_id=<?= $workpoint['unic_id'] ?>" class="btn btn-primary">Enter Working Point</a>
                                        <button class="btn btn-primary" onclick="manageSupervisor(<?= $workpoint['unic_id'] ?>)">Manage Supervisor</button>
                                        <button class="btn btn-success" onclick="manageSMSTemplate(<?= $workpoint['unic_id'] ?>, '<?= htmlspecialchars($workpoint['name_of_the_place'], ENT_QUOTES) ?>')">SMS Template</button>
                                        <button class="btn btn-danger" onclick="deleteWorkpoint(<?= $workpoint['unic_id'] ?>)">Delete</button>
                                    </div>
                                    <div class="workpoint-subtitle">
                                        <span><strong>Address:</strong> <?= htmlspecialchars($workpoint['address']) ?></span> &nbsp; | &nbsp;
                                        <span><strong>Lead:</strong> <?= htmlspecialchars($workpoint['lead_person_name']) ?></span> &nbsp; | &nbsp;
                                        <span><strong>Lead Phone:</strong> <?= htmlspecialchars($workpoint['lead_person_phone_nr']) ?></span> &nbsp; | &nbsp;
                                        <span><strong>Workplace Phone:</strong> <?= htmlspecialchars($workpoint['workplace_phone_nr']) ?></span> &nbsp; | &nbsp;
                                        <span><strong>Booking:</strong> <?= htmlspecialchars($workpoint['booking_phone_nr']) ?></span> &nbsp; | &nbsp;
                                        <span><strong>Email:</strong> <?= htmlspecialchars($workpoint['email'] ?? '') ?></span> &nbsp; | &nbsp;
                                        <span><strong>Supervisor:</strong> <?= htmlspecialchars($workpoint['user']) ?> / <?= htmlspecialchars($workpoint['password']) ?></span>
                                    </div>
                                </div>
                                <div class="workpoint-content">
                                    <div class="workpoint-stats">
                                        <div class="workpoint-stat">
                                            <div class="workpoint-stat-number"><?= $workpoint_stats[$workpoint['unic_id']]['specialist_count'] ?></div>
                                            <div class="workpoint-stat-label">Assigned Specialists</div>
                                        </div>
                                        <div class="workpoint-stat">
                                            <div class="workpoint-stat-number"><?= $workpoint_stats[$workpoint['unic_id']]['upcoming_count'] ?></div>
                                            <div class="workpoint-stat-label">Upcoming Bookings</div>
                                        </div>
                                        <div class="workpoint-stat">
                                            <div class="workpoint-stat-number"><?= $workpoint_stats[$workpoint['unic_id']]['past_count'] ?></div>
                                            <div class="workpoint-stat-label">Bookings (30 days)</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Specialists at this workpoint -->
                                    <div style="margin-top: 30px;">
                                        <?php if (isset($specialist_stats[$workpoint['unic_id']]) && !empty($specialist_stats[$workpoint['unic_id']])): ?>
                                            <table class="specialists-table">
                                                <thead>
                                                    <tr>
                                                        <th>Specialists at this Workpoint</th>
                                                        <th>Speciality</th>
                                                        <th class="text-center">Bookings Last Month</th>
                                                        <th class="text-center">This Month</th>
                                                        <th class="text-center">Upcoming</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($specialist_stats[$workpoint['unic_id']] as $specialist): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($specialist['name']) ?></td>
                                                            <td><?= htmlspecialchars($specialist['speciality']) ?></td>
                                                            <td class="text-center"><?= $specialist['last_month_bookings'] ?></td>
                                                            <td class="text-center"><?= $specialist['this_month_bookings'] ?></td>
                                                            <td class="text-center"><?= $specialist['upcoming_bookings'] ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <p>No specialists assigned to this workpoint.</p>
                                        <?php endif; ?>
                                    </div>

                                    

                                    <!-- Specialist Schedule Management -->
                                    <div style="margin-top: 30px;">
                                        <h4><i class="fas fa-user-md"></i> Specialist Schedule Management</h4>
                                        <?php
                                        // List specialists for this organisation that have non-zero shifts at this workpoint (avoid duplicates)
                                        $stmt = $pdo->prepare("
                                            SELECT s.*
                                            FROM specialists s
                                            WHERE s.organisation_id = ?
                                              AND EXISTS (
                                                SELECT 1 FROM working_program wp
                                                WHERE wp.specialist_id = s.unic_id
                                                  AND wp.working_place_id = ?
                                                  AND ((wp.shift1_start <> '00:00:00' AND wp.shift1_end <> '00:00:00')
                                                    OR (wp.shift2_start <> '00:00:00' AND wp.shift2_end <> '00:00:00')
                                                    OR (wp.shift3_start <> '00:00:00' AND wp.shift3_end <> '00:00:00'))
                                              )
                                            ORDER BY s.name
                                        ");
                                        $stmt->execute([$organisation['unic_id'], $workpoint['unic_id']]);
                                        $workpoint_specialists = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        if (!empty($workpoint_specialists)):
                                            foreach ($workpoint_specialists as $spec):
                                                // Fetch working program rows for this specialist at this workpoint
                                                $stmt = $pdo->prepare("
                                                    SELECT * FROM working_program
                                                    WHERE specialist_id = ? AND working_place_id = ?
                                                    ORDER BY day_of_week
                                                ");
                                                $stmt->execute([$spec['unic_id'], $workpoint['unic_id']]);
                                                $working_programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        ?>
                                            <div class="specialist-schedule-card" style="margin-bottom: 20px; padding: 15px; border: 1px solid #dee2e6; border-radius: 8px; background-color: #f8f9fa; overflow: hidden;">
                                                <div class="specialist-header" style="margin-bottom: 10px; display: grid; grid-template-columns: minmax(0,1fr) auto; align-items: baseline; gap: 8px; width: 100%;">
                                                    <h5 style="margin: 0; color: #495057;">
                                                        <i class="fas fa-user-md"></i>
                                                        <?= htmlspecialchars($spec['name']) ?>
                                                        <small style="color: #6c757d;">(<?= htmlspecialchars($spec['speciality']) ?>)</small>
                                                    </h5>
                                                    <div style="font-size: 13px; color: #6c757d; max-width: 100%; text-align: right; overflow-wrap: anywhere; word-break: break-word; white-space: normal; justify-self: end;">
                                                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($spec['email']) ?> |
                                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($spec['phone_nr']) ?> |
                                                        <strong>Login:</strong> <?= htmlspecialchars($spec['user']) ?> / <?= htmlspecialchars($spec['password']) ?>
                                                    </div>
                                                </div>

                                                <div class="schedule-table-container" style="width: 100%;">
                                                    <table class="table table-sm table-bordered" style="font-size: 13px; background-color: white; cursor: pointer; width: 100%; table-layout: fixed;" onclick="openModifyScheduleModal(<?= intval($spec['unic_id']) ?>, <?= intval($workpoint['unic_id']) ?>)">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th style="font-size: 13px;"></th>
                                                                <th style="font-size: 13px;">Monday</th>
                                                                <th style="font-size: 13px;">Tuesday</th>
                                                                <th style="font-size: 13px;">Wednesday</th>
                                                                <th style="font-size: 13px;">Thursday</th>
                                                                <th style="font-size: 13px;">Friday</th>
                                                                <th style="font-size: 13px;">Saturday</th>
                                                                <th style="font-size: 13px;">Sunday</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            // Build schedule structure
                                                            $scheduleData = [];
                                                            $daysOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                                            foreach ($daysOrder as $day) {
                                                                $scheduleData[$day] = [
                                                                    'shift1' => ['start' => '--:--', 'end' => '--:--'],
                                                                    'shift2' => ['start' => '--:--', 'end' => '--:--'],
                                                                    'shift3' => ['start' => '--:--', 'end' => '--:--']
                                                                ];
                                                            }

                                                            foreach ($working_programs as $wpRow) {
                                                                $day = $wpRow['day_of_week'];
                                                                if (isset($scheduleData[$day])) {
                                                                    $scheduleData[$day]['shift1'] = [
                                                                        'start' => substr((string)$wpRow['shift1_start'], 0, 5),
                                                                        'end' => substr((string)$wpRow['shift1_end'], 0, 5)
                                                                    ];
                                                                    $scheduleData[$day]['shift2'] = [
                                                                        'start' => substr((string)$wpRow['shift2_start'], 0, 5),
                                                                        'end' => substr((string)$wpRow['shift2_end'], 0, 5)
                                                                    ];
                                                                    $scheduleData[$day]['shift3'] = [
                                                                        'start' => substr((string)$wpRow['shift3_start'], 0, 5),
                                                                        'end' => substr((string)$wpRow['shift3_end'], 0, 5)
                                                                    ];
                                                                }
                                                            }

                                                            // Determine active shifts only
                                                            $activeShifts = [];
                                                            for ($shift = 1; $shift <= 3; $shift++) {
                                                                $shiftKey = 'shift' . $shift;
                                                                $hasTime = false;
                                                                foreach ($daysOrder as $day) {
                                                                    $st = $scheduleData[$day][$shiftKey]['start'] ?? '--:--';
                                                                    $et = $scheduleData[$day][$shiftKey]['end'] ?? '--:--';
                                                                    if ($st !== '--:--' && $st !== '00:00' && $et !== '--:--' && $et !== '00:00') {
                                                                        $hasTime = true;
                                                                        break;
                                                                    }
                                                                }
                                                                if ($hasTime) { $activeShifts[] = $shift; }
                                                            }

                                                            if (!empty($activeShifts)):
                                                                foreach ($activeShifts as $shift):
                                                                    $shiftKey = 'shift' . $shift;
                                                            ?>
                                                                <tr>
                                                                    <td style="font-size: 13px;"><strong>Shift <?= $shift ?></strong></td>
                                                                    <?php foreach ($daysOrder as $day):
                                                                        $st = $scheduleData[$day][$shiftKey]['start'];
                                                                        $et = $scheduleData[$day][$shiftKey]['end'];
                                                                        if ($st === '00:00' || $st === '--:--') { $st = '--:--'; }
                                                                        if ($et === '00:00' || $et === '--:--') { $et = '--:--'; }
                                                                    ?>
                                                                        <td style="font-size: 13px;"><?= $st . '–' . $et ?></td>
                                                                    <?php endforeach; ?>
                                                                </tr>
                                                            <?php endforeach; else: ?>
                                                                <tr>
                                                                    <td colspan="8" style="font-size: 13px; text-align: center;">No program set</td>
                                                                </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>

                                                
                                            </div>
                                        <?php endforeach; else: ?>
                                            <div style="text-align: center; padding: 20px; color: #6c757d;">
                                                <i class="fas fa-info-circle"></i> No specialists assigned to this workpoint.
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Unassigned specialists (organisation-wide) -->
                                    <?php if (!empty($org_unassigned)): ?>
                                        <div class="unassigned-specialists">
                                            <h4>Unassigned Specialists (<?= count($org_unassigned) ?>)</h4>
                                            <ul class="unassigned-list">
                                                <?php foreach ($org_unassigned as $spec): ?>
                                                    <li><?= htmlspecialchars($spec['name']) ?> - <?= htmlspecialchars($spec['speciality']) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Workpoint Modal -->
    <div id="addWorkpointModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addWorkpointModal')">&times;</span>
            <h2>Add New Workpoint</h2>
            <form id="addWorkpointForm" method="POST" action="admin/process_add_workpoint.php" onsubmit="submitAddWorkpointForm(event)">
                <input type="hidden" name="organisation_id" value="<?= $organisation['unic_id'] ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name_of_the_place">Workpoint Name *</label>
                        <input type="text" id="name_of_the_place" name="name_of_the_place" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address *</label>
                        <input type="text" id="address" name="address" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="lead_person_name">Lead Person *</label>
                        <input type="text" id="lead_person_name" name="lead_person_name" required>
                    </div>
                    <div class="form-group">
                        <label for="lead_person_phone_nr">Lead Person Phone *</label>
                        <input type="text" id="lead_person_phone_nr" name="lead_person_phone_nr" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="workplace_phone_nr">Workplace Phone *</label>
                        <input type="text" id="workplace_phone_nr" name="workplace_phone_nr" required>
                    </div>
                    <div class="form-group">
                        <label for="booking_phone_nr">Booking Phone *</label>
                        <input type="text" id="booking_phone_nr" name="booking_phone_nr" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="supervisor_user">Supervisor Username *</label>
                        <input type="text" id="supervisor_user" name="user" required>
                    </div>
                    <div class="form-group">
                        <label for="supervisor_password">Supervisor Password *</label>
                        <input type="text" id="supervisor_password" name="password" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email (Optional)</label>
                        <input type="email" id="email" name="email">
                    </div>
                    <div class="form-group">
                        <label for="country_display">Country *</label>
                        <input type="text" id="country_display" placeholder="Type to search countries..." autocomplete="off">
                        <input type="hidden" id="country" name="country" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="language">Language *</label>
                        <input type="text" id="language" name="language" maxlength="2" pattern="[A-Z]{2}" placeholder="e.g., EN, RO, LT" title="Enter 2-letter language code" required style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')">
                    </div>
                    <div class="form-group">
                        <!-- Empty group to maintain layout -->
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addWorkpointModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Workpoint</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Workpoint Modal -->
    <div id="editWorkpointModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editWorkpointModal')">&times;</span>
            <h2>Edit Workpoint</h2>
            <form id="editWorkpointForm" method="POST" action="admin/update_working_point.php" onsubmit="submitEditWorkpointForm(event)">
                <input type="hidden" id="edit_workpoint_id" name="workpoint_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_name_of_the_place">Workpoint Name *</label>
                        <input type="text" id="edit_name_of_the_place" name="name_of_the_place" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_address">Address *</label>
                        <input type="text" id="edit_address" name="address" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_lead_person_name">Lead Person *</label>
                        <input type="text" id="edit_lead_person_name" name="lead_person_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_lead_person_phone_nr">Lead Person Phone *</label>
                        <input type="text" id="edit_lead_person_phone_nr" name="lead_person_phone_nr" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_workplace_phone_nr">Workplace Phone *</label>
                        <input type="text" id="edit_workplace_phone_nr" name="workplace_phone_nr" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_booking_phone_nr">Booking Phone *</label>
                        <input type="text" id="edit_booking_phone_nr" name="booking_phone_nr" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_supervisor_user">Supervisor Username *</label>
                        <input type="text" id="edit_supervisor_user" name="user" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_supervisor_password">Supervisor Password *</label>
                        <input type="text" id="edit_supervisor_password" name="password" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_email">Email (Optional)</label>
                        <input type="email" id="edit_email" name="email">
                    </div>
                    <div class="form-group" style="display: flex; gap: 10px; align-items: flex-end;">
                        <div style="flex: 1;">
                            <label for="edit_country_display">Country *</label>
                            <input type="text" id="edit_country_display" placeholder="Type to search countries..." autocomplete="off" style="width: 100%;">
                            <input type="hidden" id="edit_country" name="country" required>
                        </div>
                        <div style="width: 60px;">
                            <label for="edit_language" style="font-size: 12px;">Lang *</label>
                            <input type="text" id="edit_language" name="language" maxlength="2" pattern="[A-Z]{2}" placeholder="EN" title="Enter 2-letter language code" required style="text-transform: uppercase; width: 50px; text-align: center; font-size: 12px;" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')">
                        </div>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editWorkpointModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Workpoint</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manage Supervisor Modal -->
    <div id="manageSupervisorModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('manageSupervisorModal')">&times;</span>
            <h2>Manage Workpoint Supervisor</h2>
            <form id="manageSupervisorForm" method="POST" action="admin/update_working_point.php" onsubmit="submitManageSupervisorForm(event)">
                <input type="hidden" id="supervisor_workpoint_id" name="workpoint_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="manage_supervisor_user">Supervisor Username *</label>
                        <input type="text" id="manage_supervisor_user" name="user" required>
                    </div>
                    <div class="form-group">
                        <label for="manage_supervisor_password">Supervisor Password *</label>
                        <input type="text" id="manage_supervisor_password" name="password" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="manage_supervisor_lead_person">Responsible Person *</label>
                    <input type="text" id="manage_supervisor_lead_person" name="lead_person_name" required>
                </div>

                <div class="form-group">
                    <label for="manage_supervisor_phone">Responsible Person Phone *</label>
                    <input type="text" id="manage_supervisor_phone" name="lead_person_phone_nr" required>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('manageSupervisorModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Supervisor</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddWorkpointModal() {
            document.getElementById('addWorkpointModal').style.display = 'block';
        }

        function editWorkpoint(workpointId) {
            // Fetch workpoint data and populate the form
            fetch(`admin/get_working_point_data.php?workpoint_id=${workpointId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const workpoint = data.workpoint;
                        document.getElementById('edit_workpoint_id').value = workpoint.unic_id;
                        document.getElementById('edit_name_of_the_place').value = workpoint.name_of_the_place;
                        document.getElementById('edit_address').value = workpoint.address;
                        document.getElementById('edit_lead_person_name').value = workpoint.lead_person_name;
                        document.getElementById('edit_lead_person_phone_nr').value = workpoint.lead_person_phone_nr;
                        document.getElementById('edit_workplace_phone_nr').value = workpoint.workplace_phone_nr;
                        document.getElementById('edit_booking_phone_nr').value = workpoint.booking_phone_nr;
                        document.getElementById('edit_email').value = workpoint.email || '';
                        document.getElementById('editWorkpointModal').style.display = 'block';
                    } else {
                        alert('Error loading workpoint data: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading workpoint data');
                });
        }

        function manageSupervisor(workpointId) {
            // Fetch workpoint data and populate the supervisor form
            fetch(`admin/get_working_point_data.php?workpoint_id=${workpointId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const workpoint = data.workpoint;
                        document.getElementById('supervisor_workpoint_id').value = workpoint.unic_id;
                        document.getElementById('manage_supervisor_user').value = workpoint.user;
                        document.getElementById('manage_supervisor_password').value = workpoint.password;
                        document.getElementById('manage_supervisor_lead_person').value = workpoint.lead_person_name;
                        document.getElementById('manage_supervisor_phone').value = workpoint.lead_person_phone_nr;
                        document.getElementById('manageSupervisorModal').style.display = 'block';
                    } else {
                        alert('Error loading workpoint data: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading workpoint data');
                });
        }

        function deleteWorkpoint(workpointId) {
            if (confirm('Are you sure you want to delete this workpoint? This action cannot be undone.')) {
                fetch('admin/delete_workpoint.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `workpoint_id=${workpointId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Workpoint deleted successfully');
                        location.reload();
                    } else {
                        alert('Error deleting workpoint: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting workpoint');
                });
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function submitAddWorkpointForm(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            
            fetch(form.getAttribute('action'), {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Workpoint added successfully');
                    closeModal('addWorkpointModal');
                    location.reload();
                } else {
                    alert('Error adding workpoint: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding workpoint');
            });
        }

        function submitEditWorkpointForm(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Workpoint updated successfully');
                    closeModal('editWorkpointModal');
                    location.reload();
                } else {
                    alert('Error updating workpoint: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating workpoint');
            });
        }

        function submitManageSupervisorForm(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.set('action', 'update_supervisor');
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal('manageSupervisorModal');
                    location.reload();
                } else {
                    alert('Error updating supervisor: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating supervisor');
            });
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Specialist Schedule Management Functions (modal-based, same flow as supervisor)
        function openModifyScheduleModal(specialistId, workpointId) {
            const modal = document.getElementById('modifyScheduleModal');
            if (!modal) return;
            document.getElementById('modifyScheduleSpecialistId').value = specialistId;
            document.getElementById('modifyScheduleWorkpointId').value = workpointId;
            const errorElement = document.getElementById('modifyScheduleError');
            if (errorElement) errorElement.style.display = 'none';
            loadModifyScheduleEditor();
            loadScheduleDataForModal(specialistId, workpointId);
            modal.style.display = 'flex';
        }

        function closeModifyScheduleModal() {
            const modal = document.getElementById('modifyScheduleModal');
            if (modal) modal.style.display = 'none';
            const form = document.getElementById('modifyScheduleForm');
            if (form) form.reset();
            const errorElement = document.getElementById('modifyScheduleError');
            if (errorElement) errorElement.style.display = 'none';
        }

        function loadScheduleDataForModal(specialistId, workpointId) {
            const formData = new FormData();
            formData.append('action', 'get_schedule');
            formData.append('specialist_id', specialistId);
            formData.append('workpoint_id', workpointId);
            fetch('admin/modify_schedule_ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const titleElement = document.getElementById('modifyScheduleTitle');
                        if (titleElement) {
                            titleElement.textContent = `📋 Modify Schedule: ${data.details.specialist_name} at ${data.details.workpoint_name}`;
                        }
                        const days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
                        const lookup = {};
                        (data.schedule || []).forEach(item => {
                            const d = (item.day_of_week || '').toLowerCase();
                            if (!d) return;
                            lookup[d] = {
                                shift1_start: item.shift1_start,
                                shift1_end: item.shift1_end,
                                shift2_start: item.shift2_start,
                                shift2_end: item.shift2_end,
                                shift3_start: item.shift3_start,
                                shift3_end: item.shift3_end
                            };
                        });
                        days.forEach(day => {
                            const d = lookup[day] || {};
                            const s1s = document.querySelector(`input[name="modify_shift1_start_${day}"]`);
                            const s1e = document.querySelector(`input[name="modify_shift1_end_${day}"]`);
                            const s2s = document.querySelector(`input[name="modify_shift2_start_${day}"]`);
                            const s2e = document.querySelector(`input[name="modify_shift2_end_${day}"]`);
                            const s3s = document.querySelector(`input[name="modify_shift3_start_${day}"]`);
                            const s3e = document.querySelector(`input[name="modify_shift3_end_${day}"]`);
                            if (s1s) s1s.value = d.shift1_start || '';
                            if (s1e) s1e.value = d.shift1_end || '';
                            if (s2s) s2s.value = d.shift2_start || '';
                            if (s2e) s2e.value = d.shift2_end || '';
                            if (s3s) s3s.value = d.shift3_start || '';
                            if (s3e) s3e.value = d.shift3_end || '';
                        });
                    } else {
                        const err = document.getElementById('modifyScheduleError');
                        if (err) { err.textContent = 'Failed to load schedule data: ' + (data.message || ''); err.style.display = 'block'; }
                    }
                })
                .catch(() => {
                    const err = document.getElementById('modifyScheduleError');
                    if (err) { err.textContent = 'An error occurred while loading the schedule.'; err.style.display = 'block'; }
                });
        }

        function loadModifyScheduleEditor() {
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            const tableBody = document.getElementById('modifyScheduleEditorTableBody');
            tableBody.innerHTML = '';
            days.forEach(day => {
                const d = day.toLowerCase();
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="day-name">${day}</td>
                    <td><input type="time" class="modify-shift1-start-time" name="modify_shift1_start_${d}" value=""></td>
                    <td><input type="time" class="modify-shift1-end-time" name="modify_shift1_end_${d}" value=""></td>
                    <td><button type="button" class="btn-clear-shift" onclick="clearModifyShift(this, 1)">Clear</button></td>
                    <td><input type="time" class="modify-shift2-start-time" name="modify_shift2_start_${d}" value=""></td>
                    <td><input type="time" class="modify-shift2-end-time" name="modify_shift2_end_${d}" value=""></td>
                    <td><button type="button" class="btn-clear-shift" onclick="clearModifyShift(this, 2)">Clear</button></td>
                    <td><input type="time" class="modify-shift3-start-time" name="modify_shift3_start_${d}" value=""></td>
                    <td><input type="time" class="modify-shift3-end-time" name="modify_shift3_end_${d}" value=""></td>
                    <td><button type="button" class="btn-clear-shift" onclick="clearModifyShift(this, 3)">Clear</button></td>`;
                tableBody.appendChild(row);
            });
        }

        function clearModifyShift(button, shiftNum) {
            const row = button.closest('tr');
            row.querySelector(`.modify-shift${shiftNum}-start-time`).value = '';
            row.querySelector(`.modify-shift${shiftNum}-end-time`).value = '';
        }

        function applyModifyAllShifts() {
            const range = document.getElementById('modifyQuickOptionsDaySelect').value;
            const days = range === 'mondayToFriday' ? ['monday','tuesday','wednesday','thursday','friday'] : (range === 'saturday' ? ['saturday'] : ['sunday']);
            const s1s = document.getElementById('modifyShift1Start').value;
            const s1e = document.getElementById('modifyShift1End').value;
            const s2s = document.getElementById('modifyShift2Start').value;
            const s2e = document.getElementById('modifyShift2End').value;
            const s3s = document.getElementById('modifyShift3Start').value;
            const s3e = document.getElementById('modifyShift3End').value;
            days.forEach(day => {
                const exists = document.querySelector(`input[name=\"modify_shift1_start_${day}\"]`);
                if (exists) {
                    document.querySelector(`input[name=\"modify_shift1_start_${day}\"]`).value = s1s;
                    document.querySelector(`input[name=\"modify_shift1_end_${day}\"]`).value = s1e;
                    document.querySelector(`input[name=\"modify_shift2_start_${day}\"]`).value = s2s;
                    document.querySelector(`input[name=\"modify_shift2_end_${day}\"]`).value = s2e;
                    document.querySelector(`input[name=\"modify_shift3_start_${day}\"]`).value = s3s;
                    document.querySelector(`input[name=\"modify_shift3_end_${day}\"]`).value = s3e;
                }
            });
        }

        function updateScheduleFromModal() {
            const formData = new FormData();
            formData.append('action', 'update_schedule');
            formData.append('specialist_id', document.getElementById('modifyScheduleSpecialistId').value);
            formData.append('workpoint_id', document.getElementById('modifyScheduleWorkpointId').value);
            const days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
            const schedule = {};
            days.forEach(day => {
                schedule[day] = {
                    shift1_start: document.querySelector(`input[name=\"modify_shift1_start_${day}\"]`)?.value || '',
                    shift1_end: document.querySelector(`input[name=\"modify_shift1_end_${day}\"]`)?.value || '',
                    shift2_start: document.querySelector(`input[name=\"modify_shift2_start_${day}\"]`)?.value || '',
                    shift2_end: document.querySelector(`input[name=\"modify_shift2_end_${day}\"]`)?.value || '',
                    shift3_start: document.querySelector(`input[name=\"modify_shift3_start_${day}\"]`)?.value || '',
                    shift3_end: document.querySelector(`input[name=\"modify_shift3_end_${day}\"]`)?.value || ''
                };
            });
            formData.append('schedule', JSON.stringify(schedule));
            const btn = event.target;
            const original = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = 'Updating...';
            fetch('admin/modify_schedule_ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeModifyScheduleModal();
                        location.reload();
                    } else {
                        const err = document.getElementById('modifyScheduleError');
                        if (err) { err.textContent = data.message || 'Failed to update schedule.'; err.style.display = 'block'; }
                    }
                })
                .catch(() => {
                    const err = document.getElementById('modifyScheduleError');
                    if (err) { err.textContent = 'An error occurred while updating the schedule.'; err.style.display = 'block'; }
                })
                .finally(() => { btn.disabled = false; btn.innerHTML = original; });
        }
        
        function manageSMSTemplate(workpointId, workpointName) {
            // Create modal for SMS template management
            const modalHtml = `
                <div id="smsTemplateModal" class="modal" style="display: block; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; overflow-y: auto;">
                    <div class="modal-content" style="background: #fff; max-width: 900px; margin: 3% auto; padding: 20px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); position: relative;">
                        <div class="modal-header" style="border-bottom: 1px solid #dee2e6; padding-bottom: 15px; margin-bottom: 20px;">
                            <h5 class="modal-title" style="margin: 0; font-size: 1.25rem;">SMS Templates for ${workpointName}</h5>
                            <button type="button" class="btn-close" onclick="closeSMSTemplateModal()" style="position: absolute; right: 20px; top: 20px;"></button>
                        </div>
                        <div class="modal-body" style="padding: 0;">
                            <div style="background-color: #d1ecf1; border: 1px solid #bee5eb; border-radius: 0.25rem; padding: 12px 20px; margin-bottom: 20px;">
                                <strong>Available Variables:</strong><br>
                                <div style="display: flex; gap: 40px; margin-top: 10px;">
                                    <div style="flex: 1;">
                                        • <code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">{booking_id}</code> - Booking ID<br>
                                        • <code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">{organisation_alias}</code> - Organisation name<br>
                                        • <code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">{workpoint_name}</code> - Working point name<br>
                                        • <code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">{workpoint_address}</code> - Address<br>
                                        • <code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">{workpoint_phone}</code> - Phone number<br>
                                        • <code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">{service_name}</code> - Service name
                                    </div>
                                    <div style="flex: 1;">
                                        • <code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">{start_time}</code> - Start time (HH:mm)<br>
                                        • <code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">{end_time}</code> - End time (HH:mm)<br>
                                        • <code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">{booking_date}</code> - Full date<br>
                                        • <code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">{client_name}</code> - Client name<br>
                                        • <code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">{specialist_name}</code> - Specialist name
                                    </div>
                                </div>
                            </div>
                            
                            <div style="background-color: #fff3cd; border: 1px solid #ffeeba; border-radius: 0.25rem; padding: 15px; margin-bottom: 20px;">
                                <h6 style="margin: 0 0 5px 0;">Exclude SMS notifications when booking action comes from:</h6>
                                <small style="color: #856404; display: block; margin-bottom: 10px;">If a booking is cancelled/created/updated via these channels, NO SMS will be sent (to avoid duplicate notifications)</small>
                                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                                    <label style="cursor: pointer;"><input type="checkbox" id="channel_PHONE" value="PHONE" style="margin-right: 5px;">Phone Call</label>
                                    <label style="cursor: pointer;"><input type="checkbox" id="channel_SMS" value="SMS" style="margin-right: 5px;" checked>SMS</label>
                                    <label style="cursor: pointer;"><input type="checkbox" id="channel_WEB" value="WEB" style="margin-right: 5px;">Web Portal</label>
                                    <label style="cursor: pointer;"><input type="checkbox" id="channel_WHATSAPP" value="WHATSAPP" style="margin-right: 5px;">WhatsApp</label>
                                    <label style="cursor: pointer;"><input type="checkbox" id="channel_MESSENGER" value="MESSENGER" style="margin-right: 5px;">Messenger</label>
                                </div>
                            </div>
                            
                            <form id="smsTemplateForm">
                                <input type="hidden" id="sms_workpoint_id" value="${workpointId}">
                                
                                <!-- Cancellation Template -->
                                <div style="margin-bottom: 25px; border: 1px solid #e9ecef; padding: 15px; border-radius: 5px;">
                                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Cancellation Template:</label>
                                    <textarea id="sms_cancellation_template" rows="3" placeholder="Loading..." style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;"></textarea>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                                        <small style="color: #6c757d;">This template will be used when sending SMS notifications for cancelled bookings.</small>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="resetToDefaultTemplate('cancellation')" style="padding: 2px 8px; font-size: 0.75rem;">Reset to Default</button>
                                    </div>
                                </div>
                                
                                <!-- Creation Template -->
                                <div style="margin-bottom: 25px; border: 1px solid #e9ecef; padding: 15px; border-radius: 5px;">
                                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Creation Template:</label>
                                    <textarea id="sms_creation_template" rows="3" placeholder="Loading..." style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;"></textarea>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                                        <small style="color: #6c757d;">This template will be used when sending SMS notifications for new bookings.</small>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="resetToDefaultTemplate('creation')" style="padding: 2px 8px; font-size: 0.75rem;">Reset to Default</button>
                                    </div>
                                </div>
                                
                                <!-- Update Template -->
                                <div style="margin-bottom: 25px; border: 1px solid #e9ecef; padding: 15px; border-radius: 5px;">
                                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Update Template:</label>
                                    <textarea id="sms_update_template" rows="3" placeholder="Loading..." style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;"></textarea>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                                        <small style="color: #6c757d;">This template will be used when sending SMS notifications for booking updates.</small>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="resetToDefaultTemplate('update')" style="padding: 2px 8px; font-size: 0.75rem;">Reset to Default</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer" style="border-top: 1px solid #dee2e6; padding-top: 15px; margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                            <button type="button" class="btn btn-secondary" onclick="closeSMSTemplateModal()">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="saveSMSTemplate()">Save All Templates</button>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('smsTemplateModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Load current templates
            fetch(`admin/get_sms_template.php?workpoint_id=${workpointId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Load templates
                        document.getElementById('sms_cancellation_template').value = data.cancellation_template || getDefaultTemplate('cancellation');
                        document.getElementById('sms_creation_template').value = data.creation_template || getDefaultTemplate('creation');
                        document.getElementById('sms_update_template').value = data.update_template || getDefaultTemplate('update');
                        
                        // Load excluded channels (default is SMS excluded)
                        const excludedChannels = data.excluded_channels ? data.excluded_channels.split(',') : ['SMS'];
                        document.querySelectorAll('input[id^="channel_"]').forEach(checkbox => {
                            checkbox.checked = excludedChannels.includes(checkbox.value);
                        });
                    } else {
                        document.getElementById('sms_cancellation_template').value = getDefaultTemplate('cancellation');
                        document.getElementById('sms_creation_template').value = getDefaultTemplate('creation');
                        document.getElementById('sms_update_template').value = getDefaultTemplate('update');
                    }
                })
                .catch(error => {
                    console.error('Error loading template:', error);
                    document.getElementById('sms_cancellation_template').value = getDefaultTemplate('cancellation');
                    document.getElementById('sms_creation_template').value = getDefaultTemplate('creation');
                    document.getElementById('sms_update_template').value = getDefaultTemplate('update');
                });
        }
        
        function closeSMSTemplateModal() {
            const modal = document.getElementById('smsTemplateModal');
            if (modal) {
                modal.remove();
            }
        }
        
        function getDefaultTemplate(type) {
            switch(type) {
                case 'cancellation':
                    return 'Your Booking ID:{booking_id} at {organisation_alias} - {workpoint_name} ({workpoint_address}) for {service_name} at {start_time} - {booking_date} was canceled. Call {workpoint_phone} if needed.';
                case 'creation':
                    return 'Booking confirmed! ID:{booking_id} at {organisation_alias} - {workpoint_name} for {service_name} on {booking_date} at {start_time}. Location: {workpoint_address}';
                case 'update':
                    return 'Booking ID:{booking_id} updated. New time: {booking_date} at {start_time} for {service_name} at {workpoint_name}. Call {workpoint_phone} if needed.';
                default:
                    return '';
            }
        }
        
        function resetToDefaultTemplate(type) {
            document.getElementById(`sms_${type}_template`).value = getDefaultTemplate(type);
        }
        
        function saveSMSTemplate() {
            const workpointId = document.getElementById('sms_workpoint_id').value;
            const cancellationTemplate = document.getElementById('sms_cancellation_template').value;
            const creationTemplate = document.getElementById('sms_creation_template').value;
            const updateTemplate = document.getElementById('sms_update_template').value;
            
            // Get excluded channels (checked = excluded)
            const excludedChannels = [];
            document.querySelectorAll('input[id^="channel_"]:checked').forEach(checkbox => {
                excludedChannels.push(checkbox.value);
            });
            
            if (!cancellationTemplate.trim() || !creationTemplate.trim() || !updateTemplate.trim()) {
                alert('Please enter all templates');
                return;
            }
            
            const formData = new FormData();
            formData.append('workpoint_id', workpointId);
            formData.append('cancellation_template', cancellationTemplate);
            formData.append('creation_template', creationTemplate);
            formData.append('update_template', updateTemplate);
            formData.append('excluded_channels', excludedChannels.join(','));
            
            fetch('admin/save_sms_template.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('SMS templates saved successfully!');
                    closeSMSTemplateModal();
                } else {
                    alert('Error saving templates: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving templates');
            });
        }
    </script>
    
    <!-- Country Autocomplete -->
    <script src="includes/country_autocomplete.js"></script>
    <script>
        // Initialize country autocomplete when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize for add workpoint modal
            createCountryAutocomplete('country_display', 'country');
            
            // Initialize for edit workpoint modal
            createCountryAutocomplete('edit_country_display', 'edit_country');
        });

        // Override the editWorkpoint function to populate country/language fields
        function editWorkpoint(workpointId) {
            fetch('admin/get_working_point_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'wp_id=' + encodeURIComponent(workpointId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.workpoint) {
                    const wp = data.workpoint;
                    
                    // Populate basic fields
                    document.getElementById('edit_workpoint_id').value = wp.unic_id;
                    document.getElementById('edit_name_of_the_place').value = wp.name_of_the_place || '';
                    document.getElementById('edit_address').value = wp.address || '';
                    document.getElementById('edit_lead_person_name').value = wp.lead_person_name || '';
                    document.getElementById('edit_lead_person_phone_nr').value = wp.lead_person_phone_nr || '';
                    document.getElementById('edit_workplace_phone_nr').value = wp.workplace_phone_nr || '';
                    document.getElementById('edit_booking_phone_nr').value = wp.booking_phone_nr || '';
                    document.getElementById('edit_supervisor_user').value = wp.user || '';
                    document.getElementById('edit_supervisor_password').value = wp.password || '';
                    document.getElementById('edit_email').value = wp.email || '';
                    
                    // Populate country and language fields
                    if (wp.country) {
                        // Set both display and hidden fields
                        document.getElementById('edit_country').value = wp.country;
                        setCountryValue('edit_country_display', wp.country);
                    }
                    document.getElementById('edit_language').value = (wp.language || '').toUpperCase();
                    
                    // Show modal
                    document.getElementById('editWorkpointModal').style.display = 'block';
                } else {
                    alert('Failed to load workpoint data: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error loading workpoint data:', error);
                alert('An error occurred while loading workpoint data');
            });
        }

        // Add validation to form submissions
        function submitAddWorkpointForm(event) {
            event.preventDefault();
            
            const countryCode = document.getElementById('country').value;
            const language = document.getElementById('language').value;
            
            // Validate country
            if (!countryCode || countryCode.trim() === '') {
                alert('Please select a country');
                return false;
            }
            
            // Validate language
            if (!language || !language.match(/^[A-Z]{2}$/)) {
                alert('Language must be a 2-letter code (e.g., EN, RO, LT)');
                return false;
            }
            
            // Submit form
            const form = event.target;
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal('addWorkpointModal');
                    location.reload(); // Refresh to show new workpoint
                } else {
                    alert('Error: ' + (data.message || 'Failed to add workpoint'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the workpoint');
            });
        }

        function submitEditWorkpointForm(event) {
            event.preventDefault();
            
            const countryCode = document.getElementById('edit_country').value;
            const language = document.getElementById('edit_language').value;
            
            // Validate country (more lenient - allow if already set)
            if (!countryCode || countryCode.trim() === '') {
                alert('Please select a country');
                return false;
            }
            
            // Validate language format if it's provided
            if (language && language.trim() !== '' && !language.match(/^[A-Z]{2}$/)) {
                alert('Language must be a 2-letter code (e.g., EN, RO, LT)');
                return false;
            }
            
            // Submit form
            const form = event.target;
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal('editWorkpointModal');
                    location.reload(); // Refresh to show updated workpoint
                } else {
                    alert('Error: ' + (data.message || 'Failed to update workpoint'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the workpoint');
            });
        }
    </script>
</body>
</html> 