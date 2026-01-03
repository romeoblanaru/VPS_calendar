<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/lang_loader.php';

// If arriving with a specific workpoint, set it in session
if (isset($_GET['workpoint_id'])) {
	$_SESSION['workpoint_id'] = (int)$_GET['workpoint_id'];
}

// Allow both workpoint and organisation users to access supervisor dashboard
if (!isset($_SESSION['user']) || !in_array($_SESSION['role'], ['workpoint_user', 'organisation_user'])) {
	header('Location: login.php');
    exit;
}

// Load organisation
$stmt = $pdo->prepare("SELECT * FROM organisations WHERE unic_id = ?");
$stmt->execute([$_SESSION['organisation_id']]);
$organisation = $stmt->fetch();

// Load current workpoint
$workpoint = null;
if (!empty($_SESSION['workpoint_id'])) {
$stmt = $pdo->prepare("SELECT * FROM working_points WHERE unic_id = ?");
$stmt->execute([$_SESSION['workpoint_id']]);
$workpoint = $stmt->fetch();
}
if (!$workpoint) {
	$workpoint = ['name_of_the_place' => 'Unknown Workpoint', 'address' => ''];
}

// JSON stats endpoint for workpoint statistics (restored)
if (isset($_GET['action']) && $_GET['action'] === 'workpoint_stats') {
	header('Content-Type: application/json');
	try {
		$workpointId = isset($_GET['workpoint_id']) ? (int)$_GET['workpoint_id'] : ((int)($_SESSION['workpoint_id'] ?? 0));
		if ($workpointId <= 0) {
			echo json_encode(['success' => false, 'message' => 'Missing workpoint id']);
			exit;
		}
		// Most wanted specialist in last 30 days
		$stmt = $pdo->prepare("SELECT b.id_specialist, COUNT(*) AS cnt FROM booking b WHERE b.id_work_place = ? AND b.booking_start_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY b.id_specialist ORDER BY cnt DESC LIMIT 1");
		$stmt->execute([$workpointId]);
		$top = $stmt->fetch();
		$topSpecialist = null;
		if ($top && $top['id_specialist']) {
			$s = $pdo->prepare("SELECT name FROM specialists WHERE unic_id = ?");
			$s->execute([$top['id_specialist']]);
			$name = ($s->fetch()['name'] ?? 'Unknown');
			$topSpecialist = ['id' => (int)$top['id_specialist'], 'name' => $name, 'bookings' => (int)$top['cnt']];
		}
		// Active future bookings
		$stmt = $pdo->prepare("SELECT COUNT(*) FROM booking WHERE id_work_place = ? AND booking_start_datetime > NOW()");
		$stmt->execute([$workpointId]);
		$activeFuture = (int)$stmt->fetchColumn();
		// Bookings per month for last year
		$stmt = $pdo->prepare("SELECT DATE_FORMAT(booking_start_datetime, '%Y-%m') AS ym, COUNT(*) AS cnt FROM booking WHERE id_work_place = ? AND booking_start_datetime >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym ORDER BY ym ASC");
		$stmt->execute([$workpointId]);
		$rows = $stmt->fetchAll();
		$perMonth = [];
		foreach ($rows as $r) { $perMonth[] = ['month' => $r['ym'], 'count' => (int)$r['cnt']]; }
		// Busiest and most relaxed day of week in last year
		$stmt = $pdo->prepare("SELECT DAYNAME(booking_start_datetime) AS d, COUNT(*) AS cnt FROM booking WHERE id_work_place = ? AND booking_start_datetime >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY d ORDER BY cnt DESC");
		$stmt->execute([$workpointId]);
		$dow = $stmt->fetchAll();
		$busiest = null; $relaxed = null;
		if ($dow) {
			$busiest = ['day' => $dow[0]['d'], 'count' => (int)$dow[0]['cnt']];
			$last = $dow[count($dow)-1];
			$relaxed = ['day' => $last['d'], 'count' => (int)$last['cnt']];
		}
		echo json_encode([
			'success' => true,
			'data' => [
				'topSpecialist' => $topSpecialist,
				'activeFuture' => $activeFuture,
				'perMonth' => $perMonth,
				'busiest' => $busiest,
				'relaxed' => $relaxed,
			]
		]);
	} catch (Exception $e) {
		echo json_encode(['success' => false, 'message' => $e->getMessage()]);
	}
	exit;
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workpoint Dashboard - <?= htmlspecialchars($workpoint['name_of_the_place']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/workpoint_supervisor_dashboard.css" rel="stylesheet">
    <style>
        .specialist-section .fa-chevron-down {
            transition: transform 0.3s ease;
            font-size: 0.8rem;
        }
        .specialist-section .dropdown.show .fa-chevron-down {
            transform: rotate(180deg);
        }
        .card-header .btn:hover {
            background-color: rgba(255,255,255,0.3) !important;
        }
        /* Ensure specialist cards don't clip dropdowns */
        .specialist-widget {
            position: relative;
            overflow: visible !important;
        }
        .specialist-widget .card {
            overflow: visible !important;
        }
        .specialist-widget .card-body {
            overflow: visible !important;
            position: relative;
        }
        /* Create proper stacking context for cards with open dropdowns */
        .specialist-widget:has(.dropdown-menu.show) {
            z-index: 9999 !important;
            position: relative;
        }
        /* Ensure dropdowns appear above everything */
        .specialist-widget .dropdown-menu.show {
            z-index: 10000 !important;
        }
        /* Remove static positioning - let dropdowns position naturally */
        .specialist-section .dropdown {
            position: relative !important;
        }
        /* Ensure dropdown containers don't overflow */
        .specialist-section {
            position: static;
            overflow: visible !important;
        }
        
        /* Force Bootstrap dropdowns to be on top */
        .dropdown-menu {
            z-index: 10000 !important;
        }
        
        /* Ensure the specialist row doesn't create stacking issues */
        #specialistsWidgetsContainer {
            position: relative;
            z-index: 1;
        }
        
        /* When a dropdown is shown, elevate the entire card */
        .specialist-widget:has(.show) {
            z-index: 9999 !important;
            position: relative !important;
        }
        
        /* Remove dropend behavior - force dropdowns to always drop down */
        .specialist-widget .dropdown-menu {
            transform: none !important;
            top: 100% !important;
            left: 0 !important;
            right: auto !important;
        }
        
        /* Add specialist button hover effect */
        .btn-light:hover {
            background-color: #e9ecef !important;
        }
        
        /* Ensure consistent dropdown header styling */
        .specialist-section {
            margin-top: 10px;
        }
        
        .specialist-section > .dropdown > div[data-bs-toggle="dropdown"] {
            padding: 8px 0 !important;
        }
        
        /* Remove hover effects for dropdown headers */
        .specialist-section .dropdown > div[data-bs-toggle="dropdown"]:hover {
            background-color: transparent !important;
            padding: 8px 0 !important;
            margin: 0 !important;
        }
        
        /* Service item styling */
        .service-item {
            border-radius: 4px;
            padding: 8px;
        }
        
        .service-details {
            font-size: 0.85em;
            color: #6c757d;
            margin-top: 4px;
        }
    </style>
    
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div class="flex-grow-1">
                    <h1 style="font-size: 60%;"><i class="fas fa-map-marker-alt"></i> Workpoint Dashboard</h1>
                    <h2><?= htmlspecialchars($workpoint['name_of_the_place']) ?></h2>
                    <p class="text-muted mb-2">
                        <i class="fas fa-building"></i> <?= htmlspecialchars($organisation['alias_name']) ?><br>
                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($workpoint['address']) ?>
                    </p>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-primary me-3"><?= htmlspecialchars($_SESSION['user']) ?></span>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'organisation_user'): ?>
                            <a href="organisation_dashboard.php" class="btn btn-outline-secondary btn-sm" style="font-size: 0.75rem; padding: 0.2rem 0.5rem;">Back to Organisation</a>
                        <?php else: ?>
                            <a href="logout.php" class="btn btn-outline-danger btn-sm" style="font-size: 0.75rem; padding: 0.2rem 0.5rem;">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                                    <div class="d-flex">
                        <div class="me-3">
                            <div class="card" style="width: 225px; height: 180px;">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title"><i class="fas fa-calendar"></i> Calendar Management</h6>
                                    <p class="card-text flex-grow-1" style="font-size: 0.9rem;">Manage bookings and schedules for this workpoint.</p>
                                    <a href="booking_view_page.php?working_point_user_id=<?= $_SESSION['workpoint_id'] ?>&supervisor_mode=true" class="btn btn-primary btn-sm">Go To Calendar</a>
                                </div>
                            </div>
                        </div>
                        <div class="me-3" style="display:none;">
                            <div class="card" style="width: 225px; height: 180px;">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title" style="display:none;"><i class="fas fa-user-md"></i> Specialist Management</h6>
                                    <p class="card-text flex-grow-1" style="display:none; font-size: 0.9rem;">Manage specialists working at this location.</p>
                                    <button class="btn btn-primary btn-sm" onclick="loadCurrentSpecialists()" style="display:none;">Manage Specialists</button>
                                </div>
                            </div>
                        </div>
                        <div class="me-3">
                            <div class="card" style="width: 225px; height: 180px;">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title"><i class="fas fa-cogs"></i> Services Management</h6>
                                    <p class="card-text flex-grow-1" style="font-size: 0.9rem;">Manage and redistribute services across specialists.</p>
                                    <button class="btn btn-primary btn-sm" onclick="openServicesManagementModal()">Manage Services</button>
                                </div>
                            </div>
                        </div>
                        <div class="me-3">
                            <div class="card" style="width: 225px; height: 180px;">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title"><i class="fas fa-chart-bar"></i> Statistics</h6>
                                    <p class="card-text flex-grow-1" style="font-size: 0.9rem;">View workpoint statistics and performance metrics.</p>
                                    <button class="btn btn-primary btn-sm" onclick="openStatisticsModal()">View Statistics</button>
                                </div>
                            </div>
                        </div>
                        <div class="me-3">
                            <div class="card" style="width: 225px; height: 180px;">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title"><i class="fas fa-comments"></i> Communication</h6>
                                    <p class="card-text flex-grow-1" style="font-size: 0.9rem;">Configure WhatsApp Business and Facebook Messenger connections.</p>
                                    <button class="btn btn-success btn-sm" onclick="openCommunicationSetupModal()">Setup Communication</button>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="card" style="width: 225px; height: 180px;">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title"><i class="fas fa-calendar-times"></i> Workpoint Holidays</h6>
                                    <p class="card-text flex-grow-1" style="font-size: 0.9rem;">Manage business closures and partial opening hours for holidays.</p>
                                    <button class="btn btn-warning btn-sm" onclick="openWorkpointHolidaysModal()">Manage Holidays</button>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>
            
            <!-- Manage Specialist Panel -->
            <div id="manageSpecialistPanel" class="mt-4" style="margin-top: 50px !important;">
                <div class="card" style="border-width: 3px;">
                    <div class="card-header text-white d-flex justify-content-between align-items-center" style="background-color: #007bff;">
                        <div class="d-flex align-items-center">
                            <h5 class="mb-0"><i class="fas fa-user-md"></i> Manage Specialists</h5>
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            <button class="btn btn-light" 
                                    onclick="openAddSpecialistModal()" 
                                    data-bs-toggle="tooltip" 
                                    data-bs-placement="top" 
                                    data-bs-title="Click to add a new specialist to this working point"
                                    style="width: 32px; height: 32px; padding: 0; border-radius: 4px; display: flex; align-items: center; justify-content: center; transition: background-color 0.2s;">
                                <i class="fas fa-plus" style="font-size: 16px;"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="specialistsWidgetsContainer" class="row">
                            <!-- Specialist widgets will be loaded here via AJAX -->
                            <div class="text-center text-muted col-12">
                                <i class="fas fa-spinner fa-spin"></i> Loading specialists...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
            
        </div>
    </div>
    
    <!-- Add Specialist Modal -->
    <?php include 'includes/add_specialist_modal.php'; ?>

    <!-- Edit Specialist Modal -->
    <div class="modal fade" id="editSpecialistModal" tabindex="-1" aria-labelledby="editSpecialistModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSpecialistModalLabel">
                        <i class="fas fa-edit"></i> Edit Specialist Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editSpecialistForm">
                        <input type="hidden" id="editSpecialistId" name="specialist_id">
                        <div class="mb-3">
                            <label for="editSpecialistName" class="form-label">Name</label>
                            <input type="text" class="form-control" id="editSpecialistName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="editSpecialistSpecialty" class="form-label">Specialty</label>
                            <input type="text" class="form-control" id="editSpecialistSpecialty" name="speciality" required>
                        </div>
                        <div class="mb-3">
                            <label for="editSpecialistPhone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="editSpecialistPhone" name="phone_nr" required>
                        </div>
                        <div class="mb-3">
                            <label for="editSpecialistEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="editSpecialistEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="editSpecialistUser" class="form-label">Username</label>
                            <input type="text" class="form-control" id="editSpecialistUser" name="user" required style="background-color: #e6f7ff;">
                        </div>
                        <div class="mb-3">
                            <label for="editSpecialistPassword" class="form-label">Password</label>
                            <input type="text" class="form-control" id="editSpecialistPassword" name="password" required style="background-color: #e6f7ff;">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitEditSpecialist()">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Color Picker Modal -->
    <div class="modal fade" id="colorPickerModal" tabindex="-1" aria-labelledby="colorPickerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="colorPickerModalLabel">
                        <i class="fas fa-palette"></i> Change Specialist Colors
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="colorPickerForm">
                        <input type="hidden" id="colorSpecialistId" name="specialist_id">
                        <input type="hidden" id="colorSpecialistName" name="specialist_name">
                        
                        <div class="mb-3">
                            <label class="form-label">Specialist: <strong id="colorSpecialistNameDisplay"></strong></label>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="backColorPicker" class="form-label">Background Color</label>
                                    <input type="color" class="form-control form-control-color" id="backColorPicker" name="back_color" value="#667eea">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="foreColorPicker" class="form-label">Text Color</label>
                                    <input type="color" class="form-control form-control-color" id="foreColorPicker" name="foreground_color" value="#ffffff">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Color Family Variations</label>
                                    <div id="colorVariations" class="border rounded p-2" style="min-height: 120px;">
                                        <div class="text-center text-muted">
                                            <small>Select a background color to see variations</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Quick Color Presets</label>
                            <div class="row">
                                <div class="col-4 mb-2">
                                    <button type="button" class="btn btn-sm w-100" style="background-color: #667eea; color: white;" onclick="setPresetColors('#667eea', '#ffffff')">Blue</button>
                                </div>
                                <div class="col-4 mb-2">
                                    <button type="button" class="btn btn-sm w-100" style="background-color: #28a745; color: white;" onclick="setPresetColors('#28a745', '#ffffff')">Green</button>
                                </div>
                                <div class="col-4 mb-2">
                                    <button type="button" class="btn btn-sm w-100" style="background-color: #dc3545; color: white;" onclick="setPresetColors('#dc3545', '#ffffff')">Red</button>
                                </div>
                                <div class="col-4 mb-2">
                                    <button type="button" class="btn btn-sm w-100" style="background-color: #ffc107; color: black;" onclick="setPresetColors('#ffc107', '#000000')">Yellow</button>
                                </div>
                                <div class="col-4 mb-2">
                                    <button type="button" class="btn btn-sm w-100" style="background-color: #6f42c1; color: white;" onclick="setPresetColors('#6f42c1', '#ffffff')">Purple</button>
                                </div>
                                <div class="col-4 mb-2">
                                    <button type="button" class="btn btn-sm w-100" style="background-color: #fd7e14; color: white;" onclick="setPresetColors('#fd7e14', '#ffffff')">Orange</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Preview</label>
                            <div id="colorPreview" class="p-3 rounded" style="background-color: #667eea; color: #ffffff; border: 1px solid #dee2e6;">
                                <strong>Sample Specialist Name</strong><br>
                                <small>This is how the specialist will appear in the calendar</small>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="generateRandomColors()">
                        <i class="fas fa-random"></i> Random Colors
                    </button>
                    <button type="button" class="btn btn-primary" onclick="submitColorChange()">
                        <i class="fas fa-save"></i> Apply Colors
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modify Schedule Modal -->
    <div id="modifyScheduleModal" class="modify-modal-overlay">
        <div class="modify-modal">
            <div class="modify-modal-header">
                <h3 id="modifyScheduleTitle">📋 MODIFY SCHEDULE</h3>
                <span class="modify-modal-close" onclick="closeModifyScheduleModal()">&times;</span>
                </div>
            <div class="modify-modal-body">
                <div class="org-name-row" style="display: none;">
                    <span class="modify-icon-inline">📋</span>
                    <div class="org-name-large"></div>
                        </div>
                
                <form id="modifyScheduleForm">
                    <input type="hidden" id="modifyScheduleSpecialistId" name="specialist_id">
                    <input type="hidden" id="modifyScheduleWorkpointId" name="workpoint_id">
                    
                    <!-- Individual Day Editor -->
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
                                <tbody id="modifyScheduleEditorTableBody">
                                    <!-- Days will be populated here -->
                                </tbody>
                            </table>
                    </div>
                </div>
                    
                    <!-- Quick Options Section -->
                    <div class="individual-edit-section">
                        <h4 style="font-size: 14px; margin-bottom: 10px;">âš¡ Quick Options</h4>
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

    <!-- Email Modal -->
    <div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="emailModalLabel">
                        <i class="fas fa-envelope"></i> Send Email
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="emailForm">
                        <input type="hidden" id="emailSpecialistId" name="specialist_id">
                        <div class="mb-3">
                            <label for="emailSubject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="emailSubject" name="subject" required>
                        </div>
                        <div class="mb-3">
                            <label for="emailMessage" class="form-label">Message</label>
                            <textarea class="form-control" id="emailMessage" name="message" rows="5" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="sendEmailMessage()">
                        <i class="fas fa-paper-plane"></i> Send Email
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Modal -->
    <div class="modal fade" id="statisticsModal" tabindex="-1" aria-labelledby="statisticsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statisticsModalLabel">
                        <i class="fas fa-chart-bar"></i> Workpoint Statistics
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="statisticsContent">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6><i class="fas fa-users"></i> Specialist Overview</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="specialistStats">
                                            <div class="text-center">
                                                <i class="fas fa-spinner fa-spin"></i> Loading statistics...
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6><i class="fas fa-calendar-check"></i> Booking Statistics</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="bookingStats">
                                            <div class="text-center">
                                                <i class="fas fa-spinner fa-spin"></i> Loading statistics...
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="exportStatistics()">
                        <i class="fas fa-download"></i> Export Report
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Services Management Modal -->
    <div class="modal fade" id="servicesManagementModal" tabindex="-1" aria-labelledby="servicesManagementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="servicesManagementModalLabel">
                        <i class="fas fa-cogs"></i> Services Management
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="servicesManagementContent">
                        <div class="text-center text-muted">
                            <i class="fas fa-spinner fa-spin"></i> Loading services...
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Service Modal -->
    <div class="modal fade" id="addServiceModal" tabindex="-1" aria-labelledby="addServiceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addServiceModalLabel">
                        <i class="fas fa-plus"></i> Add New Service
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addServiceForm">
                        <div class="mb-3">
                            <label for="addServiceName" class="form-label">Service Name *</label>
                            <input type="text" class="form-control" id="addServiceName" name="name_of_service" required>
                        </div>
                        <div class="mb-3">
                            <label for="addServiceDuration" class="form-label">Duration (minutes) *</label>
                            <select class="form-select" id="addServiceDuration" name="duration" required>
                                <option value="">Select duration...</option>
                                <!-- 10-minute increments from 10 to 180 minutes (3 hours) -->
                                <option value="10">10 minutes</option>
                                <option value="20">20 minutes</option>
                                <option value="30">30 minutes</option>
                                <option value="40">40 minutes</option>
                                <option value="50">50 minutes</option>
                                <option value="60">60 minutes (1 hour)</option>
                                <option value="70">70 minutes</option>
                                <option value="80">80 minutes</option>
                                <option value="90">90 minutes (1.5 hours)</option>
                                <option value="100">100 minutes</option>
                                <option value="110">110 minutes</option>
                                <option value="120">120 minutes (2 hours)</option>
                                <option value="130">130 minutes</option>
                                <option value="140">140 minutes</option>
                                <option value="150">150 minutes (2.5 hours)</option>
                                <option value="160">160 minutes</option>
                                <option value="170">170 minutes</option>
                                <option value="180">180 minutes (3 hours)</option>
                                <!-- 30-minute increments from 210 to 600 minutes (10 hours) -->
                                <option value="210">210 minutes (3.5 hours)</option>
                                <option value="240">240 minutes (4 hours)</option>
                                <option value="270">270 minutes (4.5 hours)</option>
                                <option value="300">300 minutes (5 hours)</option>
                                <option value="330">330 minutes (5.5 hours)</option>
                                <option value="360">360 minutes (6 hours)</option>
                                <option value="390">390 minutes (6.5 hours)</option>
                                <option value="420">420 minutes (7 hours)</option>
                                <option value="450">450 minutes (7.5 hours)</option>
                                <option value="480">480 minutes (8 hours)</option>
                                <option value="510">510 minutes (8.5 hours)</option>
                                <option value="540">540 minutes (9 hours)</option>
                                <option value="570">570 minutes (9.5 hours)</option>
                                <option value="600">600 minutes (10 hours)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="addServicePrice" class="form-label">Price without VAT *</label>
                            <input type="number" class="form-control" id="addServicePrice" name="price_of_service" min="0" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="addServiceVat" class="form-label">VAT Percentage *</label>
                            <input type="number" class="form-control" id="addServiceVat" name="procent_vat" min="0" max="100" step="0.01" value="0.00" required>
                            <small class="text-muted">Enter the VAT percentage (e.g., 21.00 for 21%)</small>
                        </div>
                        <div class="mb-3">
                            <label for="addServiceSpecialist" class="form-label">Assign to Specialist (Optional)</label>
                            <select class="form-select" id="addServiceSpecialist" name="specialist_id">
                                <option value="">Unassigned - Assign later</option>
                            </select>
                            <small class="text-muted">You can assign this service to a specialist now or later.</small>
                        </div>
                        <input type="hidden" name="workpoint_id" value="<?= $_SESSION['workpoint_id'] ?>">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" form="addServiceForm">
                        <i class="fas fa-plus"></i> Add Service
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Service Modal -->
    <div class="modal fade" id="editServiceModal" tabindex="-1" aria-labelledby="editServiceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editServiceModalLabel">
                        <i class="fas fa-edit"></i> Edit Service
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editServiceForm">
                        <input type="hidden" id="editServiceId" name="service_id">
                        <div class="mb-3">
                            <label for="editServiceName" class="form-label">Service Name</label>
                            <input type="text" class="form-control" id="editServiceName" name="name_of_service" required>
                        </div>
                        <div class="mb-3">
                            <label for="editServiceDuration" class="form-label">Duration (minutes)</label>
                            <select class="form-select" id="editServiceDuration" name="duration" required>
                                <option value="">Select duration...</option>
                                <!-- 10-minute increments from 10 to 180 minutes (3 hours) -->
                                <option value="10">10 minutes</option>
                                <option value="20">20 minutes</option>
                                <option value="30">30 minutes</option>
                                <option value="40">40 minutes</option>
                                <option value="50">50 minutes</option>
                                <option value="60">60 minutes (1 hour)</option>
                                <option value="70">70 minutes</option>
                                <option value="80">80 minutes</option>
                                <option value="90">90 minutes (1.5 hours)</option>
                                <option value="100">100 minutes</option>
                                <option value="110">110 minutes</option>
                                <option value="120">120 minutes (2 hours)</option>
                                <option value="130">130 minutes</option>
                                <option value="140">140 minutes</option>
                                <option value="150">150 minutes (2.5 hours)</option>
                                <option value="160">160 minutes</option>
                                <option value="170">170 minutes</option>
                                <option value="180">180 minutes (3 hours)</option>
                                <!-- 30-minute increments from 210 to 600 minutes (10 hours) -->
                                <option value="210">210 minutes (3.5 hours)</option>
                                <option value="240">240 minutes (4 hours)</option>
                                <option value="270">270 minutes (4.5 hours)</option>
                                <option value="300">300 minutes (5 hours)</option>
                                <option value="330">330 minutes (5.5 hours)</option>
                                <option value="360">360 minutes (6 hours)</option>
                                <option value="390">390 minutes (6.5 hours)</option>
                                <option value="420">420 minutes (7 hours)</option>
                                <option value="450">450 minutes (7.5 hours)</option>
                                <option value="480">480 minutes (8 hours)</option>
                                <option value="510">510 minutes (8.5 hours)</option>
                                <option value="540">540 minutes (9 hours)</option>
                                <option value="570">570 minutes (9.5 hours)</option>
                                <option value="600">600 minutes (10 hours)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editServicePrice" class="form-label">Price</label>
                            <input type="number" class="form-control" id="editServicePrice" name="price_of_service" min="0" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="editServiceVat" class="form-label">VAT Percentage</label>
                            <input type="number" class="form-control" id="editServiceVat" name="procent_vat" min="0" max="100" step="0.01">
                            <small class="text-muted">Enter the VAT percentage (e.g., 21.00 for 21%)</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitEditService()">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Redistribute Service Modal -->
    <div class="modal fade" id="redistributeServiceModal" tabindex="-1" aria-labelledby="redistributeServiceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="redistributeServiceModalLabel">
                        <i class="fas fa-share"></i> Redistribute Service
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="redistributeServiceForm">
                        <input type="hidden" id="redistributeServiceId" name="service_id">
                        <div class="mb-3">
                            <label class="form-label">Service</label>
                            <p class="form-control-plaintext" id="redistributeServiceName"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Specialist</label>
                            <p class="form-control-plaintext" id="redistributeCurrentSpecialist"></p>
                        </div>
                        <div class="mb-3">
                            <label for="redistributeTargetSpecialist" class="form-label">Move to Specialist</label>
                            <select class="form-select" id="redistributeTargetSpecialist" name="target_specialist_id" required>
                                <option value="">Select a specialist...</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="submitRedistributeService()">
                        <i class="fas fa-share"></i> Move Service
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- CSV Upload Modal -->
    <div class="modal fade" id="csvUploadModal" tabindex="-1" aria-labelledby="csvUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="csvUploadModalLabel">
                        <i class="fas fa-upload"></i> Upload Services from CSV
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Select a CSV file to upload services. The CSV should have columns for "name_of_service", "duration", "price_of_service", and optionally "procent_vat" (VAT %).</p>
                    <input type="file" class="form-control" id="csvFileInput" accept=".csv">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="uploadCsv()">
                        <i class="fas fa-upload"></i> Upload CSV
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Service Modal -->
    <div class="modal fade" id="assignServiceModal" tabindex="-1" aria-labelledby="assignServiceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignServiceModalLabel">
                        <i class="fas fa-user-plus"></i> Assign Service to Specialist
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="assignServiceForm">
                        <input type="hidden" id="assignServiceId" name="service_id">
                        <div class="mb-2">
                            <label for="assignServiceName" class="form-label">Service Name</label>
                            <p class="form-control-plaintext" id="assignServiceName"></p>
                        </div>
                        <div class="mb-2">
                            <label for="assignTargetSpecialist" class="form-label">Assign to Specialist</label>
                            <select class="form-select" id="assignTargetSpecialist" name="target_specialist_id" required>
                                <option value="">Unassigned</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info" onclick="submitAssignService()">
                        <i class="fas fa-user-plus"></i> Assign Service
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- SMS Modal (Paid users only) -->
    <div id="smsModal" class="modal" style="display:none; position:fixed; left:0; top:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index: 9999;">
        <div class="modal-content" style="background:#fff; max-width:500px; margin:5% auto; padding:20px; border-radius:8px;">
            <span class="close" onclick="closeModal('smsModal')" style="float:right; cursor:pointer; font-size:22px;">&times;</span>
            <h2>Send SMS</h2>
            <form id="smsForm" onsubmit="return submitSmsForm(event)">
                <input type="hidden" id="smsSpecialistId">
                <div class="form-group">
                    <label>Specialist</label>
                    <input type="text" id="smsSpecialistName" readonly>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" id="smsPhone" readonly>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea id="smsMessage" rows="4" placeholder="Type your message..."></textarea>
                </div>
                <div style="text-align: right; margin-top: 12px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('smsModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Locked Feature Modal (for non-paid users) -->
    <div id="smsLockedModal" class="modal" style="display:none; position:fixed; left:0; top:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index: 9999;">
        <div class="modal-content" style="background:#fff; max-width:420px; margin:10% auto; padding:20px; border-radius:8px;">
            <span class="close" onclick="closeModal('smsLockedModal')" style="float:right; cursor:pointer; font-size:22px;">&times;</span>
            <h2>SMS Feature</h2>
            <p>This feature is available for paid users only.</p>
            <div style="text-align: right; margin-top: 12px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('smsLockedModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Communication Setup Modal -->
    <div id="communicationSetupModal" class="modal" style="display:none; position:fixed; left:0; top:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index: 9999;">
        <div class="modal-content" style="background:#fff; max-width:900px; margin:2% auto; padding:20px; border-radius:8px; max-height:90vh; overflow-y:auto;">
            <!-- Modal Header with Title and Close Button -->
            <div class="d-flex justify-content-between align-items-center mb-3" style="border-bottom: 1px solid #dee2e6; padding-bottom: 15px;">
                <h3 class="mb-0"><i class="fas fa-comments"></i> Communication Setup</h3>
                <div class="d-flex align-items-center">
                    <button class="btn btn-success btn-sm" onclick="manageSMSTemplate()">
                        <i class="fas fa-sms"></i> SMS Template
                    </button>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <span class="close" onclick="closeModal('communicationSetupModal')" style="cursor:pointer; font-size:24px; color:#6c757d; font-weight:bold;">&times;</span>
                </div>
            </div>
            

            <p class="text-muted">Configure WhatsApp Business and Facebook Messenger connections for this workpoint.</p>
            
            <form id="communicationSetupForm">
                <!-- Debug: Hidden field to test form submission -->
                <input type="hidden" name="debug_test" value="form_working">
                <div class="row">
                    <!-- WhatsApp Business Section -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fab fa-whatsapp"></i> WhatsApp Business</h5>
                                <div class="d-flex align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="whatsappActive" name="whatsapp_active">
                                        <label class="form-check-label text-white" for="whatsappActive">
                                            Enable
                                        </label>
                                    </div>
                                    <span aria-hidden="true">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                                    <button type="button" class="btn btn-sm btn-light ms-2" onclick="refreshWhatsAppCredentials()" title="Refresh WhatsApp Credentials" style="padding: 1px 4px; font-size: 0.7rem; line-height: 1;">
                                        <i class="fas fa-sync-alt" style="font-size: 0.8em;"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Test Status Display -->
                                <div class="mb-2 d-flex justify-content-between align-items-center" id="whatsappTestStatus" style="display: none;">
                                    <div>
                                        <small id="whatsappTestMessage" class="text-muted" title="" style="font-size: 0.75rem;"></small>
                                    </div>
                                    <div>
                                        <small id="whatsappTestStatusBadge" class="badge" style="font-size: 0.7rem;"></small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="whatsappPhoneNumber" class="form-label">WhatsApp Phone Number</label>
                                    <input type="tel" class="form-control" id="whatsappPhoneNumber" name="whatsapp_phone_number" placeholder="+1234567890">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="whatsappPhoneNumberId" class="form-label">Phone Number ID</label>
                                    <input type="text" class="form-control" id="whatsappPhoneNumberId" name="whatsapp_phone_number_id" placeholder="WhatsApp Business Phone Number ID">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="whatsappBusinessAccountId" class="form-label">Business Account ID</label>
                                    <input type="text" class="form-control" id="whatsappBusinessAccountId" name="whatsapp_business_account_id" placeholder="Meta WhatsApp Business Account ID">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="whatsappAccessToken" class="form-label">Access Token</label>
                                    <input type="password" class="form-control" id="whatsappAccessToken" name="whatsapp_access_token" placeholder="WhatsApp Business API access token">
                                </div>
                                
                                <!-- Hardcoded Webhook Configuration -->
                                <div class="mb-3" style="background-color: #e3f2fd; border: 1px solid #90caf9; border-radius: 8px; padding: 15px; margin: 10px 0;">

                                    
                                    <div class="mb-3">
                                        <label class="form-label" style="color: #1976d2; font-weight: 500;">Webhook Verify Token</label>
                                        <input type="text" class="form-control" id="whatsappWebhookVerifyToken" value="Romy_1202" readonly style="background-color: #f5f5f5; border: 1px solid #ccc;">

                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label" style="color: #1976d2; font-weight: 500;">Webhook URL</label>
                                        <input type="url" class="form-control" id="whatsappWebhookUrl" value="https://voice.rom2.co.uk/webhook/meta" readonly style="background-color: #f5f5f5; border: 1px solid #ccc;">

                                    </div>
                                </div>
                                
                                <button type="button" class="btn btn-outline-success" id="whatsappTestBtn" onclick="testWhatsAppConnection()">
                                    <i class="fas fa-plug"></i> Test Connection
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Facebook Messenger Section -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fab fa-facebook-messenger"></i> Facebook Messenger</h5>
                                <div class="d-flex align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="facebookActive" name="facebook_active">
                                        <label class="form-check-label text-white" for="facebookActive">
                                            Enable
                                        </label>
                                    </div>
                                    <span aria-hidden="true">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                                    <button type="button" class="btn btn-sm btn-light ms-2" onclick="refreshFacebookCredentials()" title="Refresh Facebook Credentials" style="padding: 1px 4px; font-size: 0.7rem; line-height: 1;">
                                        <i class="fas fa-sync-alt" style="font-size: 0.8em;"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Test Status Display -->
                                <div class="mb-2 d-flex justify-content-between align-items-center" id="facebookTestStatus" style="display: none;">
                                    <div>
                                        <small id="facebookTestMessage" class="text-muted" title="" style="font-size: 0.75rem;"></small>
                                    </div>
                                    <div>
                                        <small id="facebookTestStatusBadge" class="badge" style="font-size: 0.7rem;"></small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="facebookPageId" class="form-label">Page ID</label>
                                    <input type="text" class="form-control" id="facebookPageId" name="facebook_page_id" placeholder="Facebook Page ID">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="facebookPageAccessToken" class="form-label">Page Access Token</label>
                                    <input type="password" class="form-control" id="facebookPageAccessToken" name="facebook_page_access_token" placeholder="Facebook Page Access Token">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="facebookAppId" class="form-label">App ID</label>
                                    <input type="text" class="form-control" id="facebookAppId" name="facebook_app_id" placeholder="Facebook App ID">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="facebookAppSecret" class="form-label">App Secret</label>
                                    <input type="password" class="form-control" id="facebookAppSecret" name="facebook_app_secret" placeholder="Facebook App Secret">
                                </div>
                                
                                <!-- Hardcoded Webhook Configuration -->
                                <div class="mb-3" style="background-color: #e3f2fd; border: 1px solid #90caf9; border-radius: 8px; padding: 15px; margin: 10px 0;">

                                    
                                    <div class="mb-3">
                                        <label class="form-label" style="color: #1976d2; font-weight: 500;">Webhook Verify Token</label>
                                        <input type="text" class="form-control" id="facebookWebhookVerifyToken" value="Romy_1202" readonly style="background-color: #f5f5f5; border: 1px solid #ccc;">

                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label" style="color: #1976d2; font-weight: 500;">Webhook URL</label>
                                        <input type="url" class="form-control" id="facebookWebhookUrl" value="https://voice.rom2.co.uk/webhook/meta" readonly style="background-color: #f5f5f5; border: 1px solid #ccc;">

                                    </div>
                                </div>
                                
                                <button type="button" class="btn btn-outline-primary" id="facebookTestBtn" onclick="testFacebookConnection()">
                                    <i class="fas fa-plug"></i> Test Connection
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <button type="button" class="btn btn-secondary me-2" onclick="closeModal('communicationSetupModal')">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveCommunicationSettings()">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/add_specialist_modal.js"></script>
    <script>
        window.SUPERVISOR_CTX = {
            organisationId: '<?= isset($_SESSION['organisation_id']) ? $_SESSION['organisation_id'] : '' ?>',
            workpointId: '<?= isset($_SESSION['workpoint_id']) ? $_SESSION['workpoint_id'] : '' ?>',
            isPaidUser: <?= (isset($_SESSION['is_paid_user']) && $_SESSION['is_paid_user']) ? 'true' : 'false' ?>
        };
        window.isPaidUser = window.SUPERVISOR_CTX.isPaidUser;
    </script>
    <script src="assets/js/supervisor_dashboard.js"></script>
    <script src="assets/js/workpoint_supervisor_dashboard.extracted.js"></script>
    <script src="assets/js/specialists_cards_bootstrap.js?v=<?php echo time(); ?>"></script>
    <script>

        // Load schedule editor
        function loadScheduleEditor() {
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            const tableBody = document.getElementById('scheduleEditorTableBody');
            tableBody.innerHTML = '';
            
            days.forEach(day => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="day-name">${day}</td>
                    <td><input type="time" class="shift1-start-time" name="shift1_start_${day.toLowerCase()}" value=""></td>
                    <td><input type="time" class="shift1-end-time" name="shift1_end_${day.toLowerCase()}" value=""></td>
                    <td><button type="button" class="btn-clear-shift" onclick="clearShift(this, 1)">Clear</button></td>
                    <td><input type="time" class="shift2-start-time" name="shift2_start_${day.toLowerCase()}" value=""></td>
                    <td><input type="time" class="shift2-end-time" name="shift2_end_${day.toLowerCase()}" value=""></td>
                    <td><button type="button" class="btn-clear-shift" onclick="clearShift(this, 2)">Clear</button></td>
                    <td><input type="time" class="shift3-start-time" name="shift3_start_${day.toLowerCase()}" value=""></td>
                    <td><input type="time" class="shift3-end-time" name="shift3_end_${day.toLowerCase()}" value=""></td>
                    <td><button type="button" class="btn-clear-shift" onclick="clearShift(this, 3)">Clear</button></td>
                `;
                tableBody.appendChild(row);
            });
        }

        // Open Add Specialist Modal
        function openAddSpecialistModal() {
            document.getElementById('addSpecialistModal').style.display = 'flex';
            
            // Set workpoint info
            const workpointId = '<?= $_SESSION['workpoint_id'] ?>';
            const organisationId = '<?= $_SESSION['organisation_id'] ?>';
            
            document.getElementById('workpointId').value = workpointId;
            document.getElementById('organisationId').value = organisationId;
            
            // Generate random 4-digit number and prepopulate user and password fields
            const randomNumber = Math.floor(Math.random() * 9000) + 1000; // Generates 4-digit number
            const defaultCredentials = 'test' + randomNumber;
            
            document.getElementById('specialistUser').value = defaultCredentials;
            document.getElementById('specialistPassword').value = defaultCredentials;
            
            // Apply grey borders to Send Email Hour, minutes, user and password inputs
            document.getElementById('emailScheduleHour').classList.add('grey-border');
            document.getElementById('emailScheduleMinute').classList.add('grey-border');
            document.getElementById('specialistUser').classList.add('grey-border');
            document.getElementById('specialistPassword').classList.add('grey-border');
            
            // Set workpoint info if provided
            if (workpointId) {
                document.getElementById('workpointSelect').style.display = 'none';
                document.getElementById('workpointLabel').style.display = 'none';
                
                // Get workpoint details
                fetch('admin/get_working_point_details.php?workpoint_id=' + workpointId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('workingScheduleTitle').textContent = '📋 Working Schedule at ' + data.workpoint.name_of_the_place + ' (' + data.workpoint.address + ')';
                        }
                    })
                    .catch(error => {
                        // Handle error silently
                    });
                
                // Load available specialists for this organisation and workpoint
                if (typeof loadAvailableSpecialists === 'function') {
                    loadAvailableSpecialists(organisationId, workpointId);
                } else {
                    console.error('loadAvailableSpecialists function not defined');
                }
            } else {
                document.getElementById('workpointSelect').style.display = 'block';
                document.getElementById('workpointLabel').style.display = 'block';
                document.getElementById('workpointLabel').textContent = 'Assign to Working Point *';
                
                // Load working points for this organisation
                if (typeof loadWorkingPointsForOrganisation === 'function') {
                    loadWorkingPointsForOrganisation(organisationId);
                } else {
                    console.error('loadWorkingPointsForOrganisation function not defined');
                }
            }
            
            // Load schedule editor
            if (typeof loadScheduleEditor === 'function') {
                loadScheduleEditor();
            } else {
                console.error('loadScheduleEditor function not defined');
            }
        }

        // Load current specialists via AJAX
        function loadCurrentSpecialists() {
            const specialistsContainer = document.getElementById('specialistsWidgetsContainer');
            
            fetch('admin/get_specialists_with_settings.php?workpoint_id=<?= $_SESSION['workpoint_id'] ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displaySpecialistWidgets(data.specialists);
                    } else {
                        specialistsContainer.innerHTML = '<div class="text-center text-danger col-12"><i class="fas fa-exclamation-triangle"></i> Error loading specialists</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    specialistsContainer.innerHTML = '<div class="text-center text-danger col-12"><i class="fas fa-exclamation-triangle"></i> Error loading specialists</div>';
                });
        }

        // Display specialists as widgets
        function displaySpecialistWidgets(specialists) {
            const specialistsContainer = document.getElementById('specialistsWidgetsContainer');
            
            if (specialists.length === 0) {
                specialistsContainer.innerHTML = '<div class="text-center text-muted col-12"><i class="fas fa-info-circle"></i> No specialists found</div>';
                return;
            }
            


            // Filter out duplicates by unic_id, keeping the first occurrence
            const uniqueSpecialists = [];
            const seenIds = new Set();
            for (const specialist of specialists) {
                if (!seenIds.has(specialist.unic_id)) {
                    uniqueSpecialists.push(specialist);
                    seenIds.add(specialist.unic_id);
                }
            }

            let html = '';
            uniqueSpecialists.forEach(specialist => {
                const backColor = specialist.back_color || '#ffffff';
                const foreColor = specialist.foreground_color || '#000000';
                const borderColor = backColor === '#ffffff' ? '#dee2e6' : backColor;
                
                html += `
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="card specialist-widget" style="border: 2px solid ${borderColor};">
                            <div class="card-header d-flex justify-content-between align-items-center" style="background-color: ${backColor}; color: ${foreColor};">
                                <h6 class="mb-0">
                                    <i class="fas fa-user-md"></i> [ID: ${specialist.unic_id}] ${specialist.name}
                                </h6>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm p-1" style="background-color: ${backColor === '#ffffff' ? 'rgba(0,0,0,0.1)' : 'rgba(255,255,255,0.2)'}; border: 1px solid ${backColor === '#ffffff' ? 'rgba(0,0,0,0.2)' : 'rgba(255,255,255,0.3)'}; color: ${foreColor}; width: 28px; height: 28px;" 
                                            onclick="openEditSpecialistModal('${specialist.unic_id}', '${specialist.name}', '${specialist.speciality}', '${specialist.phone_nr}', '${specialist.email}', '${specialist.user}', '${specialist.password}', '${backColor}', '${foreColor}')" 
                                            data-bs-toggle="tooltip" 
                                            data-bs-placement="top"
                                            data-bs-title="Modify specialist details">
                                        <i class="fas fa-edit" style="font-size: 12px;"></i>
                                    </button>
                                    <button class="btn btn-sm p-1" style="background-color: ${backColor === '#ffffff' ? 'rgba(0,0,0,0.1)' : 'rgba(255,255,255,0.2)'}; border: 1px solid ${backColor === '#ffffff' ? 'rgba(0,0,0,0.2)' : 'rgba(255,255,255,0.3)'}; color: ${foreColor}; width: 28px; height: 28px;" 
                                            onclick="openColorPickerModal('${specialist.unic_id}', '${backColor}', '${foreColor}', '${specialist.name}')" 
                                            data-bs-toggle="tooltip" 
                                            data-bs-placement="top"
                                            data-bs-title="Change specialist colors">
                                        <i class="fas fa-palette" style="font-size: 12px;"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Section 1: Top Section with Details and Buttons -->
                                <div class="top-section">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="specialist-info">
                                            <p class="mb-1"><strong>Specialty:</strong> ${specialist.speciality}</p>
                                            <p class="mb-1">
                                                <i class="fas fa-phone"></i> ${specialist.phone_nr}
                                                <i class="${specialist.specialist_nr_visible_to_client == 1 ? 'fas fa-eye text-success' : 'fas fa-eye-slash text-muted'}" 
                                                   data-bs-toggle="tooltip" 
                                                   data-bs-placement="top" 
                                                   data-bs-title="${specialist.specialist_nr_visible_to_client == 1 ? 'Phone visible to clients' : 'Phone hidden from clients'}" 
                                                   style="font-size: 0.8rem; margin-left: 5px;"></i>
                                            </p>
                                            <p class="mb-1">
                                                <i class="fas fa-envelope"></i> ${specialist.email || 'No email'}
                                                <i class="${specialist.specialist_email_visible_to_client == 1 ? 'fas fa-eye text-success' : 'fas fa-eye-slash text-muted'}" 
                                                   data-bs-toggle="tooltip" 
                                                   data-bs-placement="top" 
                                                   data-bs-title="${specialist.specialist_email_visible_to_client == 1 ? 'Email visible to clients' : 'Email hidden from clients'}" 
                                                   style="font-size: 0.8rem; margin-left: 5px;"></i>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-end" style="position: absolute; top: 10px; right: 10px;">
                                        <button class="btn btn-sm btn-outline-info" 
                                                onclick="openScheduleModal('${specialist.unic_id}')" 
                                                data-bs-toggle="tooltip" 
                                                data-bs-placement="left"
                                                data-bs-title="Modify schedule"
                                                style="width: 28px; height: 28px; padding: 0; font-size: 0.75rem;">
                                            <i class="fas fa-calendar"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Section 2: Specialist Settings -->
                                <div class="specialist-section">
                                    <div class="dropdown dropend w-100">
                                        <div style="cursor: pointer; padding: 8px 0;" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <small class="text-muted mb-0"><strong>Specialist Settings:</strong></small>
                                                <i class="fas fa-chevron-down text-muted"></i>
                                            </div>
                                        </div>
                                        <ul class="dropdown-menu w-100" style="margin-top: 5px; max-height: 400px; overflow-y: auto; z-index: 10000;">
                                            <li class="px-3 py-1">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="email_notification_${specialist.unic_id}" 
                                                           ${specialist.daily_email_enabled ? 'checked' : ''} 
                                                           onchange="toggleEmailNotification('${specialist.unic_id}', this.checked)">
                                                    <label class="form-check-label" for="email_notification_${specialist.unic_id}">
                                                        <i class="fas fa-bell"></i> Schedule Notification
                                                    </label>
                                                </div>
                                            </li>
                                            <li class="px-3 py-1">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="nr_visible_${specialist.unic_id}" 
                                                           ${specialist.specialist_nr_visible_to_client == 1 ? 'checked' : ''} 
                                                           onchange="togglePermission('${specialist.unic_id}', 'specialist_nr_visible_to_client', this.checked)">
                                                    <label class="form-check-label" for="nr_visible_${specialist.unic_id}">
                                                        <i class="fas fa-phone"></i> Phone visible to clients
                                                    </label>
                                                </div>
                                            </li>
                                            <li class="px-3 py-1">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="email_visible_${specialist.unic_id}" 
                                                           ${specialist.specialist_email_visible_to_client == 1 ? 'checked' : ''} 
                                                           onchange="togglePermission('${specialist.unic_id}', 'specialist_email_visible_to_client', this.checked)">
                                                    <label class="form-check-label" for="email_visible_${specialist.unic_id}">
                                                        <i class="fas fa-envelope"></i> Email visible to clients
                                                    </label>
                                                </div>
                                            </li>
                                            <li><hr class="dropdown-divider" style="width: 90%; margin: 0 auto; border-color: #dee2e6;"></li>
                                            <li class="px-3 py-2">
                                                <a href="#" class="text-decoration-none" onclick="openColorModal('${specialist.unic_id}', '${specialist.back_color}', '${specialist.foreground_color}'); return false;">
                                                    <i class="fas fa-palette"></i> Change Specialist Colors
                                                </a>
                                            </li>
                                            <li class="px-3 py-2">
                                                <a href="#" class="text-decoration-none" onclick="openScheduleModal('${specialist.unic_id}'); return false;">
                                                    <i class="fas fa-calendar-alt"></i> Change Specialist Schedule
                                                </a>
                                            </li>
                                            <li class="px-3 py-2">
                                                <a href="#" class="text-decoration-none" onclick="openModifyDetailsModal('${specialist.unic_id}', '${specialist.name}', '${specialist.email}', '${specialist.phone_nr}'); return false;">
                                                    <i class="fas fa-user-edit"></i> Modify Specialist Details
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <!-- Section 3: Specialist Services -->
                                <div class="specialist-section">
                                    <div class="dropdown dropend w-100">
                                        <div style="cursor: pointer; padding: 8px 0;" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <small class="text-muted mb-0"><strong>Specialist Services:</strong></small>
                                                <i class="fas fa-chevron-down text-muted"></i>
                                            </div>
                                        </div>
                                        <ul class="dropdown-menu" style="margin-top: 5px; max-height: 300px; overflow-y: auto; z-index: 10000; width: 120%;">
                                            ${specialist.services && specialist.services.length > 0 ? 
                                                specialist.services.map(service => `
                                                    <li class="px-3 py-1">
                                                        <div class="service-item" style="cursor: pointer; transition: background-color 0.2s; padding: 4px 0;" 
                                                             onclick="editSpecialistService('${service.service_id}', '${service.name_of_service.replace(/'/g, "\\'")}', ${service.duration}, ${service.price_of_service}, 0)"
                                                             onmouseover="this.style.backgroundColor='#e9ecef'"
                                                             onmouseout="this.style.backgroundColor='transparent'">
                                                            <span class="service-name" style="font-size: 0.9rem;">${service.name_of_service}</span>
                                                            <div class="service-details" style="font-size: 0.8rem; margin-top: 2px;">
                                                                <span class="service-duration"><i class="fas fa-clock"></i> ${service.duration} min</span>
                                                                <span class="service-price"><i class="fas fa-dollar-sign"></i> ${service.price_of_service}€</span>
                                                            </div>
                                                        </div>
                                                    </li>
                                                `).join('') : 
                                                '<li class="px-3 py-1"><div class="no-services">No services assigned</div></li>'
                                            }
                                            <li><hr class="dropdown-divider" style="width: 90%; margin: 0 auto; border-color: #dee2e6;"></li>
                                            <li class="px-3 py-1">
                                                <a href="#" class="text-decoration-none text-success" style="font-size: 0.9rem;" onclick="openAddServiceModalForSpecialist('${specialist.unic_id}'); return false;">
                                                    <i class="fas fa-plus-circle"></i> Add new service
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <!-- Section 4: Specialist Permissions -->
                                <div class="specialist-section">
                                    <div class="dropdown dropend w-100">
                                        <div style="cursor: pointer; padding: 8px 0;" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <small class="text-muted mb-0"><strong>Specialist Permissions:</strong></small>
                                                <i class="fas fa-chevron-down text-muted"></i>
                                            </div>
                                        </div>
                                        <ul class="dropdown-menu w-100" style="margin-top: 5px; max-height: 400px; overflow-y: auto; z-index: 10000;">
                                            <li class="px-3 py-1">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="can_delete_booking_${specialist.unic_id}" 
                                                           ${specialist.specialist_can_delete_booking == 1 ? 'checked' : ''} 
                                                           onchange="togglePermission('${specialist.unic_id}', 'specialist_can_delete_booking', this.checked)">
                                                    <label class="form-check-label" for="can_delete_booking_${specialist.unic_id}">
                                                        <i class="fas fa-trash"></i> Delete booking permission
                                                    </label>
                                                </div>
                                            </li>
                                            <li class="px-3 py-1">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="can_modify_booking_${specialist.unic_id}" 
                                                           ${specialist.specialist_can_modify_booking == 1 ? 'checked' : ''} 
                                                           onchange="togglePermission('${specialist.unic_id}', 'specialist_can_modify_booking', this.checked)">
                                                    <label class="form-check-label" for="can_modify_booking_${specialist.unic_id}">
                                                        <i class="fas fa-edit"></i> Modify booking permission
                                                    </label>
                                                </div>
                                            </li>
                                            <li><hr class="dropdown-divider" style="width: 90%; margin: 0 auto; border-color: #dee2e6;"></li>
                                            <li class="px-3 py-1">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="can_add_services_${specialist.unic_id}" 
                                                           ${specialist.specialist_can_add_services == 1 ? 'checked' : ''} 
                                                           onchange="togglePermission('${specialist.unic_id}', 'specialist_can_add_services', this.checked)">
                                                    <label class="form-check-label" for="can_add_services_${specialist.unic_id}">
                                                        <i class="fas fa-plus-circle"></i> Add services permission
                                                    </label>
                                                </div>
                                            </li>
                                            <li class="px-3 py-1">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="can_modify_services_${specialist.unic_id}" 
                                                           ${specialist.specialist_can_modify_services == 1 ? 'checked' : ''} 
                                                           onchange="togglePermission('${specialist.unic_id}', 'specialist_can_modify_services', this.checked)">
                                                    <label class="form-check-label" for="can_modify_services_${specialist.unic_id}">
                                                        <i class="fas fa-wrench"></i> Modify services permission
                                                    </label>
                                                </div>
                                            </li>
                                            <li class="px-3 py-1">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="can_delete_services_${specialist.unic_id}" 
                                                           ${specialist.specialist_can_delete_services == 1 ? 'checked' : ''} 
                                                           onchange="togglePermission('${specialist.unic_id}', 'specialist_can_delete_services', this.checked)">
                                                    <label class="form-check-label" for="can_delete_services_${specialist.unic_id}">
                                                        <i class="fas fa-trash"></i> Delete services permission
                                                    </label>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            specialistsContainer.innerHTML = html;
            
            // Initialize Bootstrap tooltips for the color buttons
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Placeholder for SMS modal
        function openSmsModal(id, name, phone) {
            if (!window.isPaidUser) {
                document.getElementById('smsLockedModal').style.display = 'block';
                return;
            }
            document.getElementById('smsSpecialistId').value = id;
            document.getElementById('smsSpecialistName').value = name;
            document.getElementById('smsPhone').value = phone;
            document.getElementById('smsMessage').value = '';
            document.getElementById('smsModal').style.display = 'block';
        }

        // Submit Add Specialist
        function submitAddSpecialist() {
            const formData = new FormData(document.getElementById('addSpecialistForm'));
            formData.append('organisation_id', '<?= $_SESSION['organisation_id'] ?>');
            formData.append('workpoint_id', '<?= $_SESSION['workpoint_id'] ?>');
            
            // Add schedule data
            const scheduleData = collectScheduleData();
            formData.append('schedule_data', JSON.stringify(scheduleData));
            
            fetch('admin/process_add_specialist.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeAddSpecialistModal();
                    document.getElementById('addSpecialistForm').reset();
                    loadCurrentSpecialists(); // Reload the list
                    alert('Specialist added successfully!');
                } else {
                    const errorDiv = document.getElementById('addSpecialistError');
                    errorDiv.textContent = data.message || 'Failed to add specialist';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const errorDiv = document.getElementById('addSpecialistError');
                errorDiv.textContent = 'Error adding specialist';
                errorDiv.style.display = 'block';
            });
        }

        // Handle specialist selection
        function handleSpecialistSelection() {
            const specialistSelect = document.getElementById('specialistSelection');
            const selectedValue = specialistSelect.value;
            
            // Reset form fields
            clearFormFields();
            
            if (selectedValue === 'new') {
                // Enable fields for new specialist
                enableFormFields();
                // Set default values for email schedule
                document.getElementById('emailScheduleHour').value = '9';
                document.getElementById('emailScheduleMinute').value = '0';
            } else if (selectedValue) {
                // Disable fields for existing specialist
                disableFormFields();
                // Load existing specialist data
                fetch(`admin/get_specialist_data.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'specialist_id=' + encodeURIComponent(selectedValue)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const specialist = data.specialist;
                        
                        // Populate form fields
                        document.getElementById('specialistName').value = specialist.name || '';
                        document.getElementById('specialistSpeciality').value = specialist.speciality || '';
                        document.getElementById('specialistEmail').value = specialist.email || '';
                        document.getElementById('specialistPhone').value = specialist.phone_nr || '';
                        document.getElementById('specialistUser').value = specialist.user || '';
                        
                        // Show password in plain text
                        const passwordField = document.getElementById('specialistPassword');
                        passwordField.value = specialist.password || '';
                        passwordField.type = 'text';
                        
                        // Set email schedule fields
                        document.getElementById('emailScheduleHour').value = specialist.h_of_email_schedule || '9';
                        document.getElementById('emailScheduleMinute').value = specialist.m_of_email_schedule || '0';
                        
                        // Make all fields read-only and set background color
                        disableFormFields();
                    }
                })
                .catch(error => {
                    console.error('Error loading specialist data:', error);
                });
            }
        }

        // Enable form fields
        function enableFormFields() {
            const fields = ['specialistName', 'specialistSpeciality', 'specialistEmail', 'specialistPhone', 'specialistUser', 'specialistPassword', 'emailScheduleHour', 'emailScheduleMinute'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.readOnly = false;
                    field.disabled = false;
                    field.style.backgroundColor = '#fff';
                }
            });
        }

        // Disable form fields
        function disableFormFields() {
            const fields = ['specialistName', 'specialistSpeciality', 'specialistEmail', 
                          'specialistPhone', 'specialistUser', 'specialistPassword', 
                          'emailScheduleHour', 'emailScheduleMinute'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.readOnly = true;
                    field.style.backgroundColor = '#f8f9fa';
                    field.style.cursor = 'not-allowed';
                    // Add a light border to show it's disabled
                    field.style.border = '1px solid #dee2e6';
                }
            });
        }

        // Clear form fields
        function clearFormFields() {
            const fields = ['specialistName', 'specialistSpeciality', 'specialistEmail', 'specialistPhone', 'specialistUser', 'specialistPassword', 'emailScheduleHour', 'emailScheduleMinute'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.value = '';
                }
            });
            // Reset to default values
            document.getElementById('emailScheduleHour').value = '9';
            document.getElementById('emailScheduleMinute').value = '0';
        }

        // Load specialist data
        function loadSpecialistData(specialistId) {
            fetch(`admin/get_specialist_data.php?specialist_id=${specialistId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const specialist = data.specialist;
                        
                        // Populate form fields
                        document.getElementById('specialistName').value = specialist.name || '';
                        document.getElementById('specialistSpeciality').value = specialist.speciality || '';
                        document.getElementById('specialistEmail').value = specialist.email || '';
                        document.getElementById('specialistPhone').value = specialist.phone_nr || '';
                        document.getElementById('specialistUser').value = specialist.user || '';
                        
                        // Show password in plain text
                        const passwordField = document.getElementById('specialistPassword');
                        passwordField.value = specialist.password || '';
                        passwordField.type = 'text';
                        
                        document.getElementById('emailScheduleHour').value = specialist.h_of_email_schedule || '9';
                        document.getElementById('emailScheduleMinute').value = specialist.m_of_email_schedule || '0';

                        // Make fields read-only for existing specialist
                        const fields = ['specialistName', 'specialistSpeciality', 'specialistEmail', 
                                      'specialistPhone', 'specialistUser', 'specialistPassword', 
                                      'emailScheduleHour', 'emailScheduleMinute'];
                        fields.forEach(fieldId => {
                            const field = document.getElementById(fieldId);
                            if (field) {
                                field.readOnly = true;
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading specialist data:', error);
                });
        }

        // Load available specialists
        function loadAvailableSpecialists(organisationId, workpointId) {
            fetch(`admin/get_available_specialists.php?organisation_id=${organisationId}&workpoint_id=${workpointId}`)
                .then(response => response.json())
                .then(data => {
                    const specialistSelect = document.getElementById('specialistSelection');
                    
                    // Clear existing options except the first two
                    while (specialistSelect.children.length > 2) {
                        specialistSelect.removeChild(specialistSelect.lastChild);
                    }
                    
                    if (data.success && data.specialists.length > 0) {
                        data.specialists.forEach(specialist => {
                            const option = document.createElement('option');
                            option.value = specialist.unic_id;
                            option.textContent = `${specialist.name} - ${specialist.speciality}`;
                            specialistSelect.appendChild(option);
                        });
                    } else {
                        // Add a message if no specialists available
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'No available specialists found';
                        option.disabled = true;
                        specialistSelect.appendChild(option);
                    }
                })
                .catch(error => {
                    console.error('Error loading specialists:', error);
                });
        }

        // Load working points for organisation
        function loadWorkingPointsForOrganisation(organisationId) {
            fetch('admin/get_working_points.php?organisation_id=' + organisationId)
                .then(response => response.json())
                .then(data => {
                    const workpointSelect = document.getElementById('workpointSelect');
                    workpointSelect.innerHTML = '<option value="">Select a working point...</option>';
                    
                    data.forEach(wp => {
                        const option = document.createElement('option');
                        option.value = wp.unic_id;
                        option.textContent = wp.name_of_the_place + ' - ' + wp.address;
                        workpointSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading working points:', error);
                });
        }

        // Load schedule editor
        function loadScheduleEditor() {
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            const tableBody = document.getElementById('scheduleEditorTableBody');
            tableBody.innerHTML = '';
            
            days.forEach(day => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="day-name">${day}</td>
                    <td><input type="time" class="shift1-start-time" name="shift1_start_${day.toLowerCase()}" value=""></td>
                    <td><input type="time" class="shift1-end-time" name="shift1_end_${day.toLowerCase()}" value=""></td>
                    <td><button type="button" class="btn-clear-shift" onclick="clearShift(this, 1)">Clear</button></td>
                    <td><input type="time" class="shift2-start-time" name="shift2_start_${day.toLowerCase()}" value=""></td>
                    <td><input type="time" class="shift2-end-time" name="shift2_end_${day.toLowerCase()}" value=""></td>
                    <td><button type="button" class="btn-clear-shift" onclick="clearShift(this, 2)">Clear</button></td>
                    <td><input type="time" class="shift3-start-time" name="shift3_start_${day.toLowerCase()}" value=""></td>
                    <td><input type="time" class="shift3-end-time" name="shift3_end_${day.toLowerCase()}" value=""></td>
                    <td><button type="button" class="btn-clear-shift" onclick="clearShift(this, 3)">Clear</button></td>
                `;
                tableBody.appendChild(row);
            });
        }

        // Clear shift
        function clearShift(button, shiftNumber) {
            const row = button.closest('tr');
            const dayName = row.querySelector('.day-name').textContent.toLowerCase();
            
            const startInput = row.querySelector(`input[name="shift${shiftNumber}_start_${dayName}"]`);
            const endInput = row.querySelector(`input[name="shift${shiftNumber}_end_${dayName}"]`);
            
            if (startInput) startInput.value = '';
            if (endInput) endInput.value = '';
        }

        // Apply all shifts
        function applyAllShifts() {
            const daySelect = document.getElementById('quickOptionsDaySelect').value;
            const shift1Start = document.getElementById('shift1Start').value;
            const shift1End = document.getElementById('shift1End').value;
            const shift2Start = document.getElementById('shift2Start').value;
            const shift2End = document.getElementById('shift2End').value;
            const shift3Start = document.getElementById('shift3Start').value;
            const shift3End = document.getElementById('shift3End').value;
            
            let days = [];
            if (daySelect === 'mondayToFriday') {
                days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
            } else if (daySelect === 'saturday') {
                days = ['saturday'];
            } else if (daySelect === 'sunday') {
                days = ['sunday'];
            }
            
            days.forEach(day => {
                const row = document.querySelector(`input[name="shift1_start_${day}"]`).closest('tr');
                
                if (shift1Start && shift1End) {
                    row.querySelector(`input[name="shift1_start_${day}"]`).value = shift1Start;
                    row.querySelector(`input[name="shift1_end_${day}"]`).value = shift1End;
                }
                
                if (shift2Start && shift2End) {
                    row.querySelector(`input[name="shift2_start_${day}"]`).value = shift2Start;
                    row.querySelector(`input[name="shift2_end_${day}"]`).value = shift2End;
                }
                
                if (shift3Start && shift3End) {
                    row.querySelector(`input[name="shift3_start_${day}"]`).value = shift3Start;
                    row.querySelector(`input[name="shift3_end_${day}"]`).value = shift3End;
                }
            });
        }

        // Collect schedule data
        function collectScheduleData() {
            const scheduleData = {};
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            
            days.forEach(day => {
                scheduleData[day] = {
                    day_of_week: day,
                    shift1_start: document.querySelector(`input[name="shift1_start_${day}"]`)?.value || '',
                    shift1_end: document.querySelector(`input[name="shift1_end_${day}"]`)?.value || '',
                    shift2_start: document.querySelector(`input[name="shift2_start_${day}"]`)?.value || '',
                    shift2_end: document.querySelector(`input[name="shift2_end_${day}"]`)?.value || '',
                    shift3_start: document.querySelector(`input[name="shift3_start_${day}"]`)?.value || '',
                    shift3_end: document.querySelector(`input[name="shift3_end_${day}"]`)?.value || ''
                };
            });
            
            return scheduleData;
        }

        // Open Color Picker Modal
        function openColorPickerModal(specialistId, currentBackColor, currentForeColor, specialistName) {
            document.getElementById('colorSpecialistId').value = specialistId;
            document.getElementById('colorSpecialistName').value = specialistName;
            document.getElementById('colorSpecialistNameDisplay').textContent = specialistName;
            document.getElementById('backColorPicker').value = currentBackColor;
            document.getElementById('foreColorPicker').value = currentForeColor;
            updateColorPreview(currentBackColor, currentForeColor);
            generateColorVariations(currentBackColor);
            
            // Set modal header color to match specialist card
            const modalHeader = document.querySelector('#colorPickerModal .modal-header');
            if (modalHeader && currentBackColor && currentForeColor) {
                modalHeader.style.backgroundColor = currentBackColor;
                modalHeader.style.color = currentForeColor;
                modalHeader.querySelector('.btn-close').style.color = currentForeColor;
                modalHeader.querySelector('.btn-close').style.opacity = '0.8';
            }
            
            // Add event listeners for live preview
            document.getElementById('backColorPicker').addEventListener('input', function() {
                const backColor = this.value;
                const foreColor = document.getElementById('foreColorPicker').value;
                updateColorPreview(backColor, foreColor);
                generateColorVariations(backColor);
            });
            document.getElementById('foreColorPicker').addEventListener('input', function() {
                updateColorPreview(document.getElementById('backColorPicker').value, this.value);
            });
            
            new bootstrap.Modal(document.getElementById('colorPickerModal')).show();
        }

        // Update color preview
        function updateColorPreview(backColor, foreColor) {
            const preview = document.getElementById('colorPreview');
            preview.style.backgroundColor = backColor;
            preview.style.color = foreColor;
        }

        // Set preset colors
        function setPresetColors(backColor, foreColor) {
            document.getElementById('backColorPicker').value = backColor;
            document.getElementById('foreColorPicker').value = foreColor;
            updateColorPreview(backColor, foreColor);
            generateColorVariations(backColor);
        }

        // Generate random colors
        function generateRandomColors() {
            const colors = [
                '#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe',
                '#43e97b', '#38f9d7', '#fa709a', '#fee140', '#a8edea', '#fed6e3',
                '#ffecd2', '#fcb69f', '#ff9a9e', '#fecfef', '#fecfef', '#fad0c4',
                '#ffd1ff', '#a8caba', '#5d4e75', '#ffecd2', '#fcb69f', '#667eea'
            ];
            
            const randomBackColor = colors[Math.floor(Math.random() * colors.length)];
            const randomForeColor = getContrastColor(randomBackColor);
            
            document.getElementById('backColorPicker').value = randomBackColor;
            document.getElementById('foreColorPicker').value = randomForeColor;
            updateColorPreview(randomBackColor, randomForeColor);
            generateColorVariations(randomBackColor);
        }

        // Generate color variations for the same family
        function generateColorVariations(baseColor) {
            const variationsContainer = document.getElementById('colorVariations');
            const variations = getColorVariations(baseColor);
            
            let html = '<div class="row">';
            variations.forEach((variation, index) => {
                const contrastColor = getContrastColor(variation);
                html += `
                    <div class="col-6 mb-2">
                        <button type="button" class="btn btn-sm w-100" 
                                style="background-color: ${variation}; color: ${contrastColor}; border: 1px solid #dee2e6;" 
                                onclick="setPresetColors('${variation}', '${contrastColor}')" 
                                title="Variation ${index + 1}">
                            <small>Variation ${index + 1}</small>
                        </button>
                    </div>
                `;
            });
            html += '</div>';
            
            variationsContainer.innerHTML = html;
        }

        // Get color variations (lighter and darker shades)
        function getColorVariations(baseColor) {
            const hex = baseColor.replace('#', '');
            const r = parseInt(hex.substr(0, 2), 16);
            const g = parseInt(hex.substr(2, 2), 16);
            const b = parseInt(hex.substr(4, 2), 16);
            
            const variations = [];
            
            // Original color
            variations.push(baseColor);
            
            // Lighter variations (add 20%, 40%, 60%)
            for (let i = 1; i <= 3; i++) {
                const factor = 0.2 * i;
                const newR = Math.min(255, Math.round(r + (255 - r) * factor));
                const newG = Math.min(255, Math.round(g + (255 - g) * factor));
                const newB = Math.min(255, Math.round(b + (255 - b) * factor));
                variations.push(`#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`);
            }
            
            // Darker variations (subtract 20%, 40%, 60%)
            for (let i = 1; i <= 3; i++) {
                const factor = 0.2 * i;
                const newR = Math.max(0, Math.round(r * (1 - factor)));
                const newG = Math.max(0, Math.round(g * (1 - factor)));
                const newB = Math.max(0, Math.round(b * (1 - factor)));
                variations.push(`#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`);
            }
            
            return variations;
        }

        // Submit color change
        function submitColorChange() {
            const specialistId = document.getElementById('colorSpecialistId').value;
            const backColor = document.getElementById('backColorPicker').value;
            const foreColor = document.getElementById('foreColorPicker').value;
            
            const formData = new FormData();
            formData.append('specialist_id', specialistId);
            formData.append('back_color', backColor);
            formData.append('foreground_color', foreColor);
            
            fetch('admin/update_specialist_colors.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('colorPickerModal')).hide();
                    loadCurrentSpecialists(); // Reload specialists to show new colors
                } else {
                    alert('Error: ' + (data.message || 'Failed to update colors'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating colors');
            });
        }

        // Get contrast color for text
        function getContrastColor(hexColor) {
            const hex = hexColor.replace('#', '');
            const r = parseInt(hex.substr(0, 2), 16);
            const g = parseInt(hex.substr(2, 2), 16);
            const b = parseInt(hex.substr(4, 2), 16);
            const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
            return luminance > 0.5 ? '#000000' : '#ffffff';
        }

        // Open Edit Specialist Modal
        function openEditSpecialistModal(specialistId, name, specialty, phone, email, user, password, backColor, foreColor) {
            document.getElementById('editSpecialistId').value = specialistId;
            document.getElementById('editSpecialistName').value = name;
            document.getElementById('editSpecialistSpecialty').value = specialty;
            document.getElementById('editSpecialistPhone').value = phone;
            document.getElementById('editSpecialistEmail').value = email;
            document.getElementById('editSpecialistUser').value = user || '';
            document.getElementById('editSpecialistPassword').value = password || '';
            
            // Set modal header color to match specialist card
            const modalHeader = document.querySelector('#editSpecialistModal .modal-header');
            if (modalHeader && backColor && foreColor) {
                modalHeader.style.backgroundColor = backColor;
                modalHeader.style.color = foreColor;
                modalHeader.querySelector('.btn-close').style.color = foreColor;
                modalHeader.querySelector('.btn-close').style.opacity = '0.8';
            }
            
            new bootstrap.Modal(document.getElementById('editSpecialistModal')).show();
        }

        // Submit Edit Specialist
        function submitEditSpecialist() {
            const form = document.getElementById('editSpecialistForm');
            const formData = new FormData(form);
            formData.append('action', 'update_specialist');
            formData.append('organisation_id', '<?= $_SESSION['organisation_id'] ?>');
            
            fetch('admin/modify_specialist_details.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editSpecialistModal')).hide();
                    loadCurrentSpecialists(); // Reload specialists
                    alert('Specialist updated successfully!');
                } else {
                    alert('Error: ' + (data.message || 'Failed to update specialist'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating specialist');
            });
        }

        // Color Modal Function
        function openColorModal(specialistId, currentBackColor, currentForeColor) {
            // Create a simple color picker modal
            const modalHtml = `
                <div class="modal fade" id="colorModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Change Specialist Colors</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="backColorPicker" class="form-label">Background Color</label>
                                    <input type="color" class="form-control form-control-color" id="backColorPicker" value="${currentBackColor}">
                                </div>
                                <div class="mb-3">
                                    <label for="foreColorPicker" class="form-label">Text Color</label>
                                    <input type="color" class="form-control form-control-color" id="foreColorPicker" value="${currentForeColor}">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="saveSpecialistColors('${specialistId}')">Save Colors</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('colorModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('colorModal'));
            modal.show();
        }

        function saveSpecialistColors(specialistId) {
            const backColor = document.getElementById('backColorPicker').value;
            const foreColor = document.getElementById('foreColorPicker').value;
            
            const formData = new FormData();
            formData.append('specialist_id', specialistId);
            formData.append('back_color', backColor);
            formData.append('foreground_color', foreColor);
            
            fetch('admin/update_specialist_colors.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('colorModal'));
                    modal.hide();
                    
                    // Refresh specialists list
                    loadCurrentSpecialists();
                } else {
                    alert('Error updating colors: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating colors');
            });
        }

        // Modify Details Modal Function
        function openModifyDetailsModal(specialistId, name, email, phone) {
            const modalHtml = `
                <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Modify Specialist Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="specialistName" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="specialistName" value="${name}">
                                </div>
                                <div class="mb-3">
                                    <label for="specialistEmail" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="specialistEmail" value="${email || ''}">
                                </div>
                                <div class="mb-3">
                                    <label for="specialistPhone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="specialistPhone" value="${phone || ''}">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="saveSpecialistDetails('${specialistId}')">Save Details</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('detailsModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            modal.show();
        }

        function saveSpecialistDetails(specialistId) {
            const name = document.getElementById('specialistName').value;
            const email = document.getElementById('specialistEmail').value;
            const phone = document.getElementById('specialistPhone').value;
            
            const formData = new FormData();
            formData.append('specialist_id', specialistId);
            formData.append('name', name);
            formData.append('email', email);
            formData.append('phone_nr', phone);
            
            fetch('admin/update_specialist_details.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('detailsModal'));
                    modal.hide();
                    
                    // Refresh specialists list
                    loadCurrentSpecialists();
                } else {
                    alert('Error updating details: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating details');
            });
        }

        // Schedule Modification Modal Functions
        function openScheduleModal(specialistId) {
            const workpointId = '<?= $_SESSION['workpoint_id'] ?>';
            openModifyScheduleModal(specialistId, workpointId);
        }

        function openModifyScheduleModal(specialistId, workpointId) {
            const modal = document.getElementById('modifyScheduleModal');
            if (!modal) {
                console.error('Modify schedule modal not found!');
                return;
            }
            
            // Set modal IDs
            document.getElementById('modifyScheduleSpecialistId').value = specialistId;
            document.getElementById('modifyScheduleWorkpointId').value = workpointId;
            
            // Clear previous error messages
            const errorElement = document.getElementById('modifyScheduleError');
            if (errorElement) errorElement.style.display = 'none';
            
            // Load schedule data
            loadScheduleDataForModal(specialistId, workpointId);
            
            // Load the schedule editor table
            loadModifyScheduleEditor();
            
            // Show modal
            modal.style.display = 'flex';
        }

        function closeModifyScheduleModal() {
            document.getElementById('modifyScheduleModal').style.display = 'none';
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
            
            fetch('admin/modify_schedule_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const details = data.details;
                    const schedule = data.schedule;
                    
                    // Update modal title with specialist and workpoint info
                    const titleElement = document.getElementById('modifyScheduleTitle');
                    if (titleElement) {
                        titleElement.innerHTML = `📋 Modify Schedule: <strong>${details.specialist_name}</strong> at <strong>${details.workpoint_name}</strong>`;
                    }
                    
                    // Populate schedule form with current data
                    const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                    
                    // Create a lookup for existing schedule data
                    const scheduleLookup = {};
                    schedule.forEach(item => {
                        const day = item.day_of_week.toLowerCase();
                        scheduleLookup[day] = {
                            shift1_start: item.shift1_start,
                            shift1_end: item.shift1_end,
                            shift2_start: item.shift2_start,
                            shift2_end: item.shift2_end,
                            shift3_start: item.shift3_start,
                            shift3_end: item.shift3_end
                        };
                    });
                    
                    // Populate form fields
                    days.forEach(day => {
                        const dayData = scheduleLookup[day] || {};
                        
                        // Set shift 1 times
                        const shift1Start = document.querySelector(`input[name="modify_shift1_start_${day}"]`);
                        const shift1End = document.querySelector(`input[name="modify_shift1_end_${day}"]`);
                        if (shift1Start) shift1Start.value = dayData.shift1_start || '';
                        if (shift1End) shift1End.value = dayData.shift1_end || '';
                        
                        // Set shift 2 times
                        const shift2Start = document.querySelector(`input[name="modify_shift2_start_${day}"]`);
                        const shift2End = document.querySelector(`input[name="modify_shift2_end_${day}"]`);
                        if (shift2Start) shift2Start.value = dayData.shift2_start || '';
                        if (shift2End) shift2End.value = dayData.shift2_end || '';
                        
                        // Set shift 3 times
                        const shift3Start = document.querySelector(`input[name="modify_shift3_start_${day}"]`);
                        const shift3End = document.querySelector(`input[name="modify_shift3_end_${day}"]`);
                        if (shift3Start) shift3Start.value = dayData.shift3_start || '';
                        if (shift3End) shift3End.value = dayData.shift3_end || '';
                    });
                } else {
                    console.error('Failed to load schedule data:', data.message);
                    document.getElementById('modifyScheduleError').textContent = 'Failed to load schedule data: ' + data.message;
                    document.getElementById('modifyScheduleError').style.display = 'block';
                }
                })
                .catch(error => {
                console.error('Error loading schedule data:', error);
                document.getElementById('modifyScheduleError').textContent = 'An error occurred while loading schedule data.';
                document.getElementById('modifyScheduleError').style.display = 'block';
            });
        }

        function updateScheduleFromModal() {
            const formData = new FormData();
            formData.append('action', 'update_schedule');
            formData.append('specialist_id', document.getElementById('modifyScheduleSpecialistId').value);
            formData.append('workpoint_id', document.getElementById('modifyScheduleWorkpointId').value);
            
            // Build schedule data structure
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            const scheduleData = {};
            
            days.forEach(day => {
                scheduleData[day] = {
                    shift1_start: document.querySelector(`input[name="modify_shift1_start_${day}"]`)?.value || '',
                    shift1_end: document.querySelector(`input[name="modify_shift1_end_${day}"]`)?.value || '',
                    shift2_start: document.querySelector(`input[name="modify_shift2_start_${day}"]`)?.value || '',
                    shift2_end: document.querySelector(`input[name="modify_shift2_end_${day}"]`)?.value || '',
                    shift3_start: document.querySelector(`input[name="modify_shift3_start_${day}"]`)?.value || '',
                    shift3_end: document.querySelector(`input[name="modify_shift3_end_${day}"]`)?.value || ''
                };
            });
            
            // Add schedule data as JSON string
            formData.append('schedule', JSON.stringify(scheduleData));
            
            // Disable submit button
            const submitBtn = event.target;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Updating...';
            submitBtn.disabled = true;
            
            fetch('admin/modify_schedule_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Schedule updated successfully!');
                    closeModifyScheduleModal();
                    // Reload the page to show updated data
                    location.reload();
                } else {
                    document.getElementById('modifyScheduleError').textContent = data.message || 'Failed to update schedule.';
                    document.getElementById('modifyScheduleError').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error updating schedule:', error);
                document.getElementById('modifyScheduleError').textContent = 'An error occurred while updating the schedule.';
                document.getElementById('modifyScheduleError').style.display = 'block';
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }

        function clearModifyShift(button, shiftNum) {
            const row = button.closest('tr');
            const startInput = row.querySelector(`.modify-shift${shiftNum}-start-time`);
            const endInput = row.querySelector(`.modify-shift${shiftNum}-end-time`);
            startInput.value = '';
            endInput.value = '';
        }

        function applyModifyAllShifts() {
            const dayRange = document.getElementById('modifyQuickOptionsDaySelect').value;
            let days;
            
            switch(dayRange) {
                case 'mondayToFriday':
                    days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
                    break;
                case 'saturday':
                    days = ['saturday'];
                    break;
                case 'sunday':
                    days = ['sunday'];
                    break;
                default:
                    return;
            }
            
            // Get shift times
            const shift1Start = document.getElementById('modifyShift1Start').value;
            const shift1End = document.getElementById('modifyShift1End').value;
            const shift2Start = document.getElementById('modifyShift2Start').value;
            const shift2End = document.getElementById('modifyShift2End').value;
            const shift3Start = document.getElementById('modifyShift3Start').value;
            const shift3End = document.getElementById('modifyShift3End').value;
            
            // Check if at least one shift has values
            const hasShift1 = shift1Start && shift1End;
            const hasShift2 = shift2Start && shift2End;
            const hasShift3 = shift3Start && shift3End;
            
            if (!hasShift1 && !hasShift2 && !hasShift3) {
                return;
            }
            
            // Apply shifts to all selected days
            days.forEach(day => {
                const row = document.querySelector(`tr:has(input[name="modify_shift1_start_${day}"])`);
                if (row) {
                    // Apply Shift 1 only if values are provided
                    if (hasShift1) {
                        const startInput = row.querySelector('.modify-shift1-start-time');
                        const endInput = row.querySelector('.modify-shift1-end-time');
                        if (startInput && endInput) {
                            startInput.value = shift1Start;
                            endInput.value = shift1End;
                        }
                    }
                    
                    // Apply Shift 2 only if values are provided
                    if (hasShift2) {
                        const startInput = row.querySelector('.modify-shift2-start-time');
                        const endInput = row.querySelector('.modify-shift2-end-time');
                        if (startInput && endInput) {
                            startInput.value = shift2Start;
                            endInput.value = shift2End;
                        }
                    }
                    
                    // Apply Shift 3 only if values are provided
                    if (hasShift3) {
                        const startInput = row.querySelector('.modify-shift3-start-time');
                        const endInput = row.querySelector('.modify-shift3-end-time');
                        if (startInput && endInput) {
                            startInput.value = shift3Start;
                            endInput.value = shift3End;
                        }
                    }
                }
            });
        }

        function loadModifyScheduleEditor() {
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            const tableBody = document.getElementById('modifyScheduleEditorTableBody');
            tableBody.innerHTML = '';
            
            days.forEach(day => {
                const dayLower = day.toLowerCase();
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="day-name">${day}</td>
                    <td><input type="time" class="modify-shift1-start-time" name="modify_shift1_start_${dayLower}" value=""></td>
                    <td><input type="time" class="modify-shift1-end-time" name="modify_shift1_end_${dayLower}" value=""></td>
                    <td><button type="button" class="btn-clear-shift" onclick="clearModifyShift(this, 1)">Clear</button></td>
                    <td><input type="time" class="modify-shift2-start-time" name="modify_shift2_start_${dayLower}" value=""></td>
                    <td><input type="time" class="modify-shift2-end-time" name="modify_shift2_end_${dayLower}" value=""></td>
                    <td><button type="button" class="btn-clear-shift" onclick="clearModifyShift(this, 2)">Clear</button></td>
                    <td><input type="time" class="modify-shift3-start-time" name="modify_shift3_start_${dayLower}" value=""></td>
                    <td><input type="time" class="modify-shift3-end-time" name="modify_shift3_end_${dayLower}" value=""></td>
                    <td><button type="button" class="btn-clear-shift" onclick="clearModifyShift(this, 3)">Clear</button></td>
                `;
                tableBody.appendChild(row);
            });
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const modifyScheduleModal = document.getElementById('modifyScheduleModal');
            if (event.target === modifyScheduleModal) {
                closeModifyScheduleModal();
            }
        });

        // Save Schedule (legacy function - can be removed)
        function saveSchedule() {
            // This would save the schedule changes
            alert('Schedule saving functionality will be implemented');
            bootstrap.Modal.getInstance(document.getElementById('scheduleModal')).hide();
        }

        // Open Email Modal
        function openEmailModal(specialistId, specialistName, specialistEmail) {
            document.getElementById('emailSpecialistId').value = specialistId;
            document.getElementById('emailSubject').value = `Message for ${specialistName}`;
            document.getElementById('emailMessage').value = `Dear ${specialistName},\n\n`;
            
            new bootstrap.Modal(document.getElementById('emailModal')).show();
        }

        // Send Email Message
        function sendEmailMessage() {
            const formData = new FormData(document.getElementById('emailForm'));
            
            fetch('admin/send_specialist_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('emailModal')).hide();
                    alert('Email sent successfully!');
                } else {
                    alert('Error: ' + (data.message || 'Failed to send email'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error sending email');
            });
        }

        // Statistics functions moved to assets/js/supervisor_dashboard.js

        // Toggle email notification function
        function toggleEmailNotification(specialistId, enabled) {
            const formData = new FormData();
            formData.append('specialist_id', specialistId);
            formData.append('daily_email_enabled', enabled ? 1 : 0);
            
            fetch('admin/update_specialist_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const status = enabled ? 'enabled' : 'disabled';
                    console.log(`Email notifications ${status} for specialist ${specialistId}`);
                } else {
                    alert('Error: ' + (data.message || 'Failed to update notification settings'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating notification settings');
            });
        }

        // Toggle permission function
        function togglePermission(specialistId, permissionField, enabled) {
            console.log('Toggling permission:', specialistId, permissionField, enabled);
            
            const formData = new FormData();
            formData.append('specialist_id', specialistId);
            formData.append('permission_field', permissionField);
            formData.append('permission_value', enabled ? 1 : 0);
            
            // Show loading state
            // Map permission fields to actual checkbox IDs
            let checkboxId;
            switch(permissionField) {
                case 'specialist_can_delete_booking':
                    checkboxId = 'can_delete_booking_' + specialistId;
                    break;
                case 'specialist_can_modify_booking':
                    checkboxId = 'can_modify_booking_' + specialistId;
                    break;
                case 'specialist_can_add_services':
                    checkboxId = 'can_add_services_' + specialistId;
                    break;
                case 'specialist_can_modify_services':
                    checkboxId = 'can_modify_services_' + specialistId;
                    break;
                case 'specialist_can_delete_services':
                    checkboxId = 'can_delete_services_' + specialistId;
                    break;
                case 'specialist_nr_visible_to_client':
                    checkboxId = 'nr_visible_' + specialistId;
                    break;
                case 'specialist_email_visible_to_client':
                    checkboxId = 'email_visible_' + specialistId;
                    break;
                default:
                    checkboxId = permissionField + '_' + specialistId;
            }
            
            const checkbox = document.getElementById(checkboxId);
            if (!checkbox) {
                console.error('Checkbox not found for ID:', checkboxId);
                console.log('Available checkbox IDs:');
                const allCheckboxes = document.querySelectorAll('input[type="checkbox"]');
                allCheckboxes.forEach(cb => console.log('- ' + cb.id));
                alert('Error: Checkbox element not found');
                return;
            }
            
            const originalChecked = checkbox.checked;
            checkbox.disabled = true;
            
            fetch('admin/update_specialist_permissions_enhanced.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        throw new Error('Invalid JSON response: ' + text);
                    }
                });
            })
            .then(data => {
                console.log('Parsed response:', data);
                if (data.success) {
                    console.log('Permission updated successfully');
                    // Keep the checkbox in its new state
                    checkbox.disabled = false;
                } else {
                    console.error('Permission update failed:', data.message);
                    // Revert checkbox to original state
                    checkbox.checked = originalChecked;
                    checkbox.disabled = false;
                    alert('Error: ' + (data.message || 'Failed to update permission settings'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Revert checkbox to original state
                checkbox.checked = originalChecked;
                checkbox.disabled = false;
                alert('Error updating permission settings: ' + error.message);
            });
        }

        // Delete specialist function
        function deleteSpecialist(specialistId) {
            if (confirm('Are you sure you want to delete this specialist?')) {
                fetch('admin/delete_specialist.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        specialist_id: specialistId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Specialist deleted successfully!');
                        loadCurrentSpecialists(); // Reload the list
                    } else {
                        alert('Error: ' + (data.message || 'Failed to delete specialist'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting specialist');
                });
            }
        }

        // Open Services Management Modal
        function openServicesManagementModal() {
            const modal = new bootstrap.Modal(document.getElementById('servicesManagementModal'));
            document.getElementById('servicesManagementContent').innerHTML = '<div class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading services...</div>';
            modal.show();
            
            // Load services for this workpoint
            loadServicesForWorkpoint();
        }

        // Load services for the current workpoint
        function loadServicesForWorkpoint() {
            const workpointId = '<?= $_SESSION['workpoint_id'] ?>';
            
            fetch(`admin/get_services_for_workpoint.php?workpoint_id=${workpointId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayServicesManagement(data.grouped_services, data.specialists, data.workpoint_id);
                    } else {
                        document.getElementById('servicesManagementContent').innerHTML = 
                            `<div class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Error: ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error loading services:', error);
                    document.getElementById('servicesManagementContent').innerHTML = 
                        '<div class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading services</div>';
                });
        }

        // Display services management interface
        function displayServicesManagement(groupedServices, specialists, workpointId) {
            let html = `
                <div class="row mb-3">
                    <div class="col-md-6 text-start">
                        <button class="btn btn-success" onclick="openAddServiceModal()">
                            <i class="fas fa-plus"></i> Add New Service
                        </button>
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-info me-2" onclick="openCsvUploadModal()">
                            <i class="fas fa-upload"></i> Upload CSV
                        </button>
                        <button class="btn btn-info" onclick="downloadServicesCsv()">
                            <i class="fas fa-download"></i> Download CSV
                        </button>
                    </div>
                </div>
            `;

            // First, display all services (assigned and unassigned)
            html += `
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-list"></i> All Services</h6>
                    </div>
                    <div class="card-body">
                        <div id="servicesList">
                            <div class="text-center text-muted">
                                <i class="fas fa-spinner fa-spin"></i> Loading services...
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Then, display services grouped by specialist (for reference)
            if (groupedServices.length > 0) {
                html += `
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-user-md"></i> Services by Specialist</h6>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                `;
                
                groupedServices.forEach(group => {
                    html += `
                        <div style="min-width: 300px; max-width: 400px; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; background-color: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 15px;">
                            <h6 class="text-primary mb-3" style="border-bottom: 2px solid #007bff; padding-bottom: 5px;">
                                <i class="fas fa-user-md"></i> ${group.specialist_name} 
                                <small class="text-muted">(${group.specialist_speciality})</small>
                            </h6>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    `;
                    
                    group.services.forEach(service => {
                        html += `
                            <div style="width: 150px; min-height: 80px; border: 1px solid #dee2e6; border-radius: 6px; padding: 6px; background-color: #f8f9fa; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                <div style="font-weight: bold; font-size: 13px; margin-bottom: 4px; color: #495057; line-height: 1.2;">${service.name_of_service}</div>
                                <div style="font-size: 11px; color: #6c757d; line-height: 1.2;">
                                    <div><i class="fas fa-clock"></i> ${service.duration} min</div>
                                    <div><i class="fas fa-dollar-sign"></i> ${service.price_of_service} + VAT</div>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += `
                            </div>
                        </div>
                    `;
                });
                
                html += `
                            </div>
                        </div>
                    </div>
                `;
            }

            document.getElementById('servicesManagementContent').innerHTML = html;
            
            // Load all services (including unassigned ones)
            loadAllServices();
        }

        // Load all services (including unassigned)
        function loadAllServices() {
            const workpointId = '<?= $_SESSION['workpoint_id'] ?>';
            // Fetch both services and specialist color settings
            Promise.all([
                fetch(`admin/get_all_services_for_workpoint.php?workpoint_id=${workpointId}`).then(r => r.json()),
                fetch(`admin/get_specialists_with_settings.php?workpoint_id=${workpointId}`).then(r => r.json())
            ]).then(([servicesData, specialistsData]) => {
                if (servicesData.success && specialistsData.success) {
                    displayServicesList(servicesData.services, specialistsData.specialists);
                } else {
                    document.getElementById('servicesList').innerHTML = `<div class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading services or specialist colors</div>`;
                }
            }).catch(error => {
                console.error('Error loading services or specialist colors:', error);
                document.getElementById('servicesList').innerHTML = '<div class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading services or specialist colors</div>';
            });
        }

        // Display services list (main focus)
        function displayServicesList(services, specialistsWithColors) {
            let html = '';
            if (services.length === 0) {
                html = `
                    <div class="text-center text-muted">
                        <i class="fas fa-info-circle"></i> No services found for this workpoint.
                        <br><small>Add services or upload a CSV file to get started.</small>
                    </div>
                `;
            } else {
                // Build a map of specialist_id to color info
                const colorMap = {};
                specialistsWithColors.forEach(sp => {
                    colorMap[sp.unic_id] = {
                        back: sp.back_color,
                        fore: sp.foreground_color
                    };
                });
                // Group services by specialist, keep unassigned separate
                const assigned = [];
                const unassigned = [];
                services.forEach(service => {
                    if (service.specialist_name) {
                        assigned.push(service);
                    } else {
                        unassigned.push(service);
                    }
                });
                // Sort assigned by specialist name, then service name
                assigned.sort((a, b) => {
                    if (a.specialist_name === b.specialist_name) {
                        return a.name_of_service.localeCompare(b.name_of_service);
                    }
                    return a.specialist_name.localeCompare(b.specialist_name);
                });
                // Sort unassigned by service name
                unassigned.sort((a, b) => a.name_of_service.localeCompare(b.name_of_service));
                // Concatenate: assigned first, then unassigned
                const ordered = assigned.concat(unassigned);
                html = `
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Actions</th>
                                    <th>Service Name</th>
                                    <th>Duration</th>
                                    <th>Price</th>
                                    <th>VAT %</th>
                                    <th>Assigned To</th>
                                    <th>Booking Count</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                ordered.forEach(service => {
                    let assignedTo = '<span class="text-muted">Unassigned</span>';
                    if (service.specialist_name && colorMap[service.id_specialist]) {
                        const color = colorMap[service.id_specialist];
                        assignedTo = `<span style="background:${color.back};color:${color.fore};padding:2px 8px;border-radius:6px;display:inline-block;min-width:80px;">${service.specialist_name} (${service.specialist_speciality})</span>`;
                    } else if (service.specialist_name) {
                        assignedTo = `${service.specialist_name} (${service.specialist_speciality})`;
                    }
                    
                    // Add strikethrough style for deleted services
                    const isDeleted = service.deleted == 1;
                    const deletedStyle = isDeleted ? 'text-decoration: line-through; opacity: 0.6;' : '';
                    
                    html += `
                        <tr style="${deletedStyle}">
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-primary" style="padding: 1px 4px;" onclick="editService('${service.service_id}', '${service.name_of_service}', ${service.duration}, ${service.price_of_service}, ${service.procent_vat || 0})" title="Edit Service">
                                        <i class="fas fa-edit" style="font-size: 80%;"></i>
                                    </button>&nbsp;&nbsp;
                                    <button class="btn btn-outline-info" style="padding: 1px 4px;" onclick="assignService('${service.service_id}', '${service.name_of_service}', '${service.specialist_id || ''}')" title="Assign to Specialist">
                                        <i class="fas fa-user-plus" style="font-size: 80%;"></i>
                                    </button>&nbsp;&nbsp;
                                    <button class="btn btn-outline-danger" style="padding: 1px 4px;" onclick="deleteService('${service.service_id}', '${service.name_of_service}', ${service.id_specialist ? 'true' : 'false'})" title="${service.id_specialist ? 'Unassign from Specialist' : 'Delete Service'}">
                                        <i class="fas fa-trash" style="font-size: 80%;"></i>
                                    </button>
                                </div>
                            </td>
                            <td><strong>[${service.service_id}] ${service.name_of_service}</strong></td>
                            <td>${service.duration} min</td>
                            <td>$${service.price_of_service}</td>
                            <td>${service.procent_vat || '0.00'}%</td>
                            <td>${assignedTo}</td>
                            <td>
                                <div class="d-flex align-items-center justify-content-center">
                                    <span class="badge me-1" style="background-color: #e9ecef; color: #6c757d;" title="Past bookings">${service.past_booking_count || 0}</span>
                                    <span class="badge bg-info" title="Future bookings">${service.future_booking_count || 0}</span>
                                </div>
                            </td>
                        </tr>
                    `;
                });
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
            }
            document.getElementById('servicesList').innerHTML = html;
        }

        // Assign Service to Specialist
        function assignService(serviceId, serviceName, currentSpecialistId) {
            document.getElementById('assignServiceId').value = serviceId;
            document.getElementById('assignServiceName').textContent = serviceName;
            
            // Load specialists for assignment
            loadSpecialistsForAssignment();
            
            const modal = new bootstrap.Modal(document.getElementById('assignServiceModal'));
            modal.show();
        }

        // Load specialists for assignment
        function loadSpecialistsForAssignment() {
            const workpointId = '<?= $_SESSION['workpoint_id'] ?>';
            
            fetch(`admin/get_specialists_with_settings.php?workpoint_id=${workpointId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('assignTargetSpecialist');
                        select.innerHTML = '<option value="">Unassigned</option>';
                        
                        data.specialists.forEach(specialist => {
                            const option = document.createElement('option');
                            option.value = specialist.unic_id;
                            option.textContent = `${specialist.name} (${specialist.speciality})`;
                            option.style.color = specialist.back_color || '#000000';
                            option.style.backgroundColor = '#ffffff';
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading specialists:', error);
                });
        }

        // Open CSV Upload Modal
        function openCsvUploadModal() {
            const modal = new bootstrap.Modal(document.getElementById('csvUploadModal'));
            modal.show();
        }

        // Submit Add Service (kept for backward compatibility but not used)
        function submitAddService() {
            // This function is deprecated - form submission is now handled by submit event listeners
            console.log('submitAddService called - this should not happen with the new form submission');
            document.getElementById('addServiceForm').requestSubmit();
        }

        // Submit Edit Service
        function submitEditService() {
            const formData = new FormData(document.getElementById('editServiceForm'));
            formData.append('action', 'edit_service');
            
            fetch('admin/process_add_service.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editServiceModal')).hide();
                    loadServicesForWorkpoint(); // Reload the list
                    alert(data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error editing service:', error);
                alert('Error editing service');
            });
        }

        // Submit Assign Service
        function submitAssignService() {
            const serviceId = document.getElementById('assignServiceId').value;
            const targetSpecialistId = document.getElementById('assignTargetSpecialist').value;
            
            if (!targetSpecialistId) {
                alert('Please select a target specialist');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'assign_service');
            formData.append('service_id', serviceId);
            formData.append('target_specialist_id', targetSpecialistId);
            
            fetch('admin/process_add_service.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('assignServiceModal')).hide();
                    loadAllServices(); // Reload the list
                    alert(data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error assigning service:', error);
                alert('Error assigning service');
            });
        }

        // Submit Redistribute Service
        function submitRedistributeService() {
            const serviceId = document.getElementById('redistributeServiceId').value;
            const targetSpecialistId = document.getElementById('redistributeTargetSpecialist').value;
            
            if (!targetSpecialistId) {
                alert('Please select a target specialist');
                return;
            }
            
            // First, get the service details
            const workpointId = '<?= $_SESSION['workpoint_id'] ?>';
            
            fetch(`admin/get_services_for_workpoint.php?workpoint_id=${workpointId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Find the service details
                        let serviceDetails = null;
                        for (const group of data.grouped_services) {
                            for (const service of group.services) {
                                if (service.service_id == serviceId) {
                                    serviceDetails = service;
                                    break;
                                }
                            }
                            if (serviceDetails) break;
                        }
                        
                        if (!serviceDetails) {
                            alert('Service not found');
                            return;
                        }
                        
                        // Create new service for target specialist
                        const formData = new FormData();
                        formData.append('action', 'add_service');
                        formData.append('specialist_id', targetSpecialistId);
                        formData.append('workpoint_id', workpointId);
                        formData.append('name_of_service', serviceDetails.name_of_service);
                        formData.append('duration', serviceDetails.duration);
                        formData.append('price_of_service', serviceDetails.price_of_service);
                        
                        return fetch('admin/process_add_service.php', {
                            method: 'POST',
                            body: formData
                        });
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Delete the original service
                        const deleteFormData = new FormData();
                        deleteFormData.append('action', 'delete_service');
                        deleteFormData.append('service_id', serviceId);
                        
                        return fetch('admin/process_add_service.php', {
                            method: 'POST',
                            body: deleteFormData
                        });
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('redistributeServiceModal')).hide();
                        loadServicesForWorkpoint(); // Reload the list
                        alert('Service redistributed successfully');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error redistributing service:', error);
                    alert('Error redistributing service');
                });
        }

        // Delete Service
        function deleteService(serviceId, serviceName, isAssigned) {
            const action = isAssigned ? 'unassign' : 'delete';
            const message = isAssigned ? 
                `Are you sure you want to unassign the service "${serviceName}" from the specialist?` :
                `Are you sure you want to delete the service "${serviceName}"?`;
            
            if (!confirm(message)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_service');
            formData.append('service_id', serviceId);
            
            fetch('admin/process_add_service.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadAllServices(); // Reload the list
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error deleting service:', error);
                alert('Error deleting service');
            });
        }

        // Function to open Add Service Modal for a specific specialist
        function openAddServiceModalForSpecialist(specialistId) {
            // Alternative approach: Find all specialist widgets and match by ID
            let specialistName = 'Unknown';
            let specialistSpeciality = '';
            
            // First try to find the card containing this specialist ID
            const allCards = document.querySelectorAll('.specialist-widget');
            
            for (const card of allCards) {
                const headerText = card.querySelector('.card-header h6')?.textContent || '';
                
                // Check if this card contains our specialist ID
                if (headerText.includes(`[ID: ${specialistId}]`)) {
                    // Extract name from header - need to handle whitespace and newlines
                    const cleanText = headerText.replace(/\s+/g, ' ').trim();
                    
                    const match = cleanText.match(/\[ID:\s*([^\]]+)\]\s+(.+)$/);
                    
                    if (match) {
                        specialistName = match[2].trim();
                    } else {
                        // Fallback: try simpler extraction
                        const parts = cleanText.split('] ');
                        if (parts.length > 1) {
                            specialistName = parts[1].trim();
                        }
                    }
                    
                    // Get speciality
                    const specialityElement = card.querySelector('.top-section small');
                    if (specialityElement) {
                        const specialityText = specialityElement.textContent;
                        const specialityMatch = specialityText.match(/Speciality:\s*(.+?)(?:\s*Phone:|$)/);
                        if (specialityMatch) {
                            specialistSpeciality = specialityMatch[1].trim();
                        }
                    }
                    break;
                }
            }
            
            const modal = document.getElementById('addServiceModal');
            
            // Set workpoint ID
            const workpointIdInput = modal.querySelector('input[name="workpoint_id"]');
            if (workpointIdInput) {
                workpointIdInput.value = '<?= $_SESSION['workpoint_id'] ?>';
            }
            
            // Use the existing specialist dropdown
            const specialistSelect = document.getElementById('addServiceSpecialist');
            if (specialistSelect) {
                // Clear existing options
                const displayText = specialistSpeciality && specialistSpeciality.trim() ? `${specialistName} (${specialistSpeciality})` : specialistName;
                specialistSelect.innerHTML = `<option value="${specialistId}" selected>${displayText}</option>`;
                // Make it readonly by disabling it
                specialistSelect.disabled = true;
                specialistSelect.style.backgroundColor = '#e9ecef';
                
                // Ensure the value is preserved when form is submitted
                // Add a hidden input with the same name to ensure value is sent
                let hiddenInput = modal.querySelector('input[name="specialist_id"][type="hidden"]');
                if (!hiddenInput) {
                    hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'specialist_id';
                    specialistSelect.parentElement.appendChild(hiddenInput);
                }
                hiddenInput.value = specialistId;
            }
            
            // Clear the form but keep specialist assignment
            const form = document.getElementById('addServiceForm');
            if (form) {
                const tempSpecialistId = specialistId;
                const tempWorkpointId = '<?= $_SESSION['workpoint_id'] ?>';
                const tempSpecialistName = specialistName;
                const tempSpecialistSpeciality = specialistSpeciality;
                
                form.reset();
                
                // Restore values after reset
                if (workpointIdInput) workpointIdInput.value = tempWorkpointId;
                
                // Restore specialist dropdown
                if (specialistSelect) {
                    const displayText = tempSpecialistSpeciality && tempSpecialistSpeciality.trim() ? `${tempSpecialistName} (${tempSpecialistSpeciality})` : tempSpecialistName;
                    specialistSelect.innerHTML = `<option value="${tempSpecialistId}" selected>${displayText}</option>`;
                    specialistSelect.disabled = true;
                    specialistSelect.style.backgroundColor = '#e9ecef';
                }
                
                // Restore hidden input
                const hiddenInput = modal.querySelector('input[name="specialist_id"][type="hidden"]');
                if (hiddenInput) hiddenInput.value = tempSpecialistId;
            }
            
            // Remove any existing submit handler
            const existingHandler = form.specialistSubmitHandler;
            if (existingHandler) {
                form.removeEventListener('submit', existingHandler);
            }
            
            // Add new submit handler to refresh after save
            const submitHandler = function(e) {
                e.preventDefault();
                
                // Temporarily enable the specialist select to ensure value is submitted
                if (specialistSelect && specialistSelect.disabled) {
                    specialistSelect.disabled = false;
                }
                
                const formData = new FormData(form);
                formData.append('action', 'add_service');
                
                // Re-disable the select
                if (specialistSelect) {
                    specialistSelect.disabled = true;
                }
                
                fetch('admin/process_add_service.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal
                        bootstrap.Modal.getInstance(modal).hide();
                        // Clear form
                        form.reset();
                        // Refresh specialists to show new service
                        loadCurrentSpecialists();
                        // Show success message
                        alert('Service added successfully!');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error adding service');
                });
            };
            
            form.addEventListener('submit', submitHandler);
            form.specialistSubmitHandler = submitHandler;
            
            new bootstrap.Modal(modal).show();
        }

        // Function to edit a specialist service
        function editSpecialistService(serviceId, serviceName, duration, price, vat) {
            // Create or get the edit service modal
            let modal = document.getElementById('editServiceModal');
            if (!modal) {
                // Create the modal if it doesn't exist
                const modalHtml = `
                    <div class="modal fade" id="editServiceModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Service</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form id="editServiceForm">
                                    <div class="modal-body">
                                        <input type="hidden" name="service_id" id="editServiceId">
                                        <div class="mb-3">
                                            <label class="form-label">Service Name</label>
                                            <input type="text" class="form-control" name="name_of_service" id="editServiceName" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Duration (minutes)</label>
                                            <input type="number" class="form-control" name="duration" id="editServiceDuration" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Price (€)</label>
                                            <input type="number" class="form-control" name="price_of_service" id="editServicePrice" step="0.01" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                modal = document.getElementById('editServiceModal');
                
                // Add submit handler
                document.getElementById('editServiceForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(e.target);
                    
                    fetch('admin/process_edit_service.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            bootstrap.Modal.getInstance(modal).hide();
                            loadCurrentSpecialists(); // Refresh the specialists list
                            alert('Service updated successfully!');
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error updating service');
                    });
                });
            }
            
            // Fill the form with service data
            document.getElementById('editServiceId').value = serviceId;
            document.getElementById('editServiceName').value = serviceName;
            document.getElementById('editServiceDuration').value = duration;
            document.getElementById('editServicePrice').value = price;
            
            // Show the modal
            new bootstrap.Modal(modal).show();
        }

        // Auto-load specialists when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadCurrentSpecialists();
            
            // Handle dropdown z-index management
            document.addEventListener('shown.bs.dropdown', function (event) {
                // Find the parent specialist widget
                const widget = event.target.closest('.specialist-widget');
                if (widget) {
                    // Reset all specialist widgets z-index
                    document.querySelectorAll('.specialist-widget').forEach(w => {
                        w.style.zIndex = '';
                    });
                    // Set high z-index for the current widget
                    widget.style.zIndex = '9999';
                }
            });
            
            document.addEventListener('hidden.bs.dropdown', function (event) {
                // Reset z-index when dropdown is closed
                const widget = event.target.closest('.specialist-widget');
                if (widget) {
                    widget.style.zIndex = '';
                }
            });
        });

        // Open Add Service Modal
        function openAddServiceModal() {
            const modalElement = document.getElementById('addServiceModal');
            const modal = new bootstrap.Modal(modalElement);
            const form = document.getElementById('addServiceForm');
            
            // Reset form
            form.reset();
            
            // Enable specialist dropdown and populate it
            const specialistSelect = document.getElementById('addServiceSpecialist');
            if (specialistSelect) {
                specialistSelect.disabled = false;
                specialistSelect.style.backgroundColor = '';
            }
            
            // Remove any hidden specialist_id input that may have been added
            const hiddenInput = modalElement.querySelector('input[name="specialist_id"][type="hidden"]');
            if (hiddenInput) {
                hiddenInput.remove();
            }
            
            // Remove any existing submit handler from specialist mode
            if (form.specialistSubmitHandler) {
                form.removeEventListener('submit', form.specialistSubmitHandler);
                form.specialistSubmitHandler = null;
            }
            
            // Add general submit handler
            const generalHandler = function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                formData.append('action', 'add_service');
                
                fetch('admin/process_add_service.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(modalElement).hide();
                        form.reset();
                        loadServices(); // Refresh services list
                        alert('Service added successfully!');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error adding service');
                });
            };
            
            // Remove old handler if exists
            if (form.generalSubmitHandler) {
                form.removeEventListener('submit', form.generalSubmitHandler);
            }
            
            form.addEventListener('submit', generalHandler);
            form.generalSubmitHandler = generalHandler;
            
            populateSpecialistsForAdd();
            modal.show();
        }

        // Edit Service
        function editService(serviceId, serviceName, duration, price, vat) {
            document.getElementById('editServiceId').value = serviceId;
            document.getElementById('editServiceName').value = serviceName;
            document.getElementById('editServiceDuration').value = duration;
            document.getElementById('editServicePrice').value = price;
            document.getElementById('editServiceVat').value = vat;
            
            const modal = new bootstrap.Modal(document.getElementById('editServiceModal'));
            modal.show();
        }

        // Upload CSV
        function uploadCsv() {
            const fileInput = document.getElementById('csvFileInput');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a CSV file');
                return;
            }
            
            const formData = new FormData();
            formData.append('csv_file', file);
            formData.append('workpoint_id', '<?= $_SESSION['workpoint_id'] ?>');
            
            fetch('admin/upload_services_csv.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('csvUploadModal')).hide();
                    loadAllServices(); // Reload the list
                    alert(data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error uploading CSV:', error);
                alert('Error uploading CSV file');
            });
        }

        // Populate specialists for add service modal
        function populateSpecialistsForAdd() {
            const workpointId = '<?= $_SESSION['workpoint_id'] ?>';
            
            fetch(`admin/get_services_for_workpoint.php?workpoint_id=${workpointId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('addServiceSpecialist');
                        select.innerHTML = '<option value="">Unassigned</option>';
                        
                        data.specialists.forEach(specialist => {
                            const option = document.createElement('option');
                            option.value = specialist.unic_id;
                            option.textContent = `${specialist.name} (${specialist.speciality})`;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading specialists:', error);
                });
        }

        // Download Services CSV
        function downloadServicesCsv() {
            const workpointId = '<?= $_SESSION['workpoint_id'] ?>';
            
            fetch(`admin/download_services_csv.php?workpoint_id=${workpointId}`)
                .then(response => {
                    if (response.ok) {
                        return response.blob();
                    } else {
                        throw new Error('Failed to download CSV');
                    }
                })
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = `services_workpoint_${workpointId}_${new Date().toISOString().split('T')[0]}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                })
                .catch(error => {
                    console.error('Error downloading CSV:', error);
                    alert('Error downloading CSV file');
                });
        }

        function closeAddSpecialistModal() {
            document.getElementById('addSpecialistModal').style.display = 'none';
        }

        function submitSmsForm(event) {
            event.preventDefault();
            // TODO: integrate SMS provider endpoint here
            closeModal('smsModal');
            return false;
        }

        // Paid flag exposed to client
        window.isPaidUser = <?php echo (isset($_SESSION['is_paid_user']) && $_SESSION['is_paid_user']) ? 'true' : 'false'; ?>;

        // Simple modal closer by id
        function closeModal(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
            // Stop polling when communication modal is closed
            if (id === 'communicationSetupModal') {
                stopStatusPolling();
            }
        }

        // Ensure openSmsModal exists and uses paid gate
        function openSmsModal(id, name, phone) {
            if (!window.isPaidUser) {
                document.getElementById('smsLockedModal').style.display = 'block';
                return;
            }
            document.getElementById('smsSpecialistId').value = id;
            document.getElementById('smsSpecialistName').value = name;
            document.getElementById('smsPhone').value = phone;
            document.getElementById('smsMessage').value = '';
            document.getElementById('smsModal').style.display = 'block';
        }

        function submitSmsForm(event) {
            event.preventDefault();
            // TODO: integrate SMS provider endpoint here
            closeModal('smsModal');
            return false;
        }

        // Communication Setup Functions
        function openCommunicationSetupModal() {
            const workpointId = '<?= $_SESSION['workpoint_id'] ?>';
            loadCommunicationSettings(workpointId);
            document.getElementById('communicationSetupModal').style.display = 'block';
            // Start continuous polling when modal opens
            startStatusPolling();
        }

        function loadCommunicationSettings(workpointId) {
            const workpointIdToUse = workpointId || '<?= $_SESSION['workpoint_id'] ?>';
            
            fetch(`admin/get_communication_settings.php?workpoint_id=${workpointIdToUse}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateCommunicationForm(data.settings);
                    } else {
                        console.error('Error loading communication settings:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        function populateCommunicationForm(settings) {
            console.log('Populating communication form with settings:', settings);
            
            // WhatsApp settings
            if (settings.whatsapp_business) {
                console.log('WhatsApp settings found:', settings.whatsapp_business);
                document.getElementById('whatsappPhoneNumber').value = settings.whatsapp_business.whatsapp_phone_number || '';
                document.getElementById('whatsappPhoneNumberId').value = settings.whatsapp_business.whatsapp_phone_number_id || '';
                document.getElementById('whatsappBusinessAccountId').value = settings.whatsapp_business.whatsapp_business_account_id || '';
                document.getElementById('whatsappAccessToken').value = settings.whatsapp_business.whatsapp_access_token || '';
                // Webhook values are hardcoded and readonly - no need to populate from database
                const isActive = settings.whatsapp_business.is_active == 1;
                document.getElementById('whatsappActive').checked = isActive;
                console.log('Setting WhatsApp checkbox to:', isActive, 'based on is_active:', settings.whatsapp_business.is_active);
                
                // Populate test status
                populateTestStatus('whatsapp', settings.whatsapp_business);
            }

            // Facebook Messenger settings
            if (settings.facebook_messenger) {
                document.getElementById('facebookPageId').value = settings.facebook_messenger.facebook_page_id || '';
                document.getElementById('facebookPageAccessToken').value = settings.facebook_messenger.facebook_page_access_token || '';
                document.getElementById('facebookAppId').value = settings.facebook_messenger.facebook_app_id || '';
                document.getElementById('facebookAppSecret').value = settings.facebook_messenger.facebook_app_secret || '';
                // Webhook values are hardcoded and readonly - no need to populate from database
                document.getElementById('facebookActive').checked = settings.facebook_messenger.is_active == 1;
                
                // Populate test status
                populateTestStatus('facebook', settings.facebook_messenger);
            }
        }

        function showTestLoading(platform) {
            const testStatusDiv = document.getElementById(platform + 'TestStatus');
            const testMessageSpan = document.getElementById(platform + 'TestMessage');
            const testStatusBadge = document.getElementById(platform + 'TestStatusBadge');
            const testBtn = document.getElementById(platform + 'TestBtn');
            
            // Show loading state
            testStatusDiv.style.display = 'flex';
            testMessageSpan.textContent = 'Testing connection...';
            testMessageSpan.title = 'Testing in progress';
            testStatusBadge.textContent = 'Testing';
            testStatusBadge.className = 'badge bg-warning';
            testStatusBadge.style.backgroundColor = '#ffc107';
            
            // Disable button and show loading
            testBtn.disabled = true;
            testBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
        }

        function resetTestButton(platform) {
            const testBtn = document.getElementById(platform + 'TestBtn');
            testBtn.disabled = false;
            testBtn.innerHTML = '<i class="fas fa-plug"></i> Test Connection';
        }

        // Polling mechanism to check status every 3 seconds
        let statusPollingInterval = null;

        function startStatusPolling() {
            // Clear any existing interval
            if (statusPollingInterval) {
                clearInterval(statusPollingInterval);
            }
            
            // Start polling every 3 seconds - only update test status, not form fields
            statusPollingInterval = setInterval(() => {
                updateTestStatusOnly();
            }, 3000);
        }

        function stopStatusPolling() {
            if (statusPollingInterval) {
                clearInterval(statusPollingInterval);
                statusPollingInterval = null;
            }
        }

        function updateTestStatusOnly() {
            const workpointId = '<?= $_SESSION['workpoint_id'] ?>';
            
            fetch(`admin/get_communication_settings.php?workpoint_id=${workpointId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.settings) {
                        // Only update test status, don't repopulate form fields
                        if (data.settings.whatsapp_business) {
                            populateTestStatus('whatsapp', data.settings.whatsapp_business);
                        }
                        if (data.settings.facebook_messenger) {
                            populateTestStatus('facebook', data.settings.facebook_messenger);
                        }
                    }
                })
                .catch(error => {
                    // Silent error handling for polling
                });
        }

        function populateTestStatus(platform, settings) {
            const testStatusDiv = document.getElementById(platform + 'TestStatus');
            const testMessageSpan = document.getElementById(platform + 'TestMessage');
            const testStatusBadge = document.getElementById(platform + 'TestStatusBadge');
            
            // Always show status if there's any test data
            if (settings.last_test_status) {
                testStatusDiv.style.display = 'flex';
                
                // Set test message with tooltip showing last test time
                testMessageSpan.textContent = settings.last_test_message || 'No message available';
                testMessageSpan.title = 'Last tested: ' + (settings.last_test_at || 'Never');
                
                // Set status badge with appropriate color
                if (settings.last_test_status === 'success') {
                    testStatusBadge.textContent = 'Success';
                    testStatusBadge.className = 'badge bg-success';
                    testStatusBadge.style.backgroundColor = '#28a745';
                    // Reset button when status is success
                    resetTestButton(platform);
                } else if (settings.last_test_status === 'failed') {
                    testStatusBadge.textContent = 'Failed';
                    testStatusBadge.className = 'badge bg-danger';
                    testStatusBadge.style.backgroundColor = '#dc3545';
                    // Reset button when status is failed
                    resetTestButton(platform);
                } else if (settings.last_test_status === 'not_tested') {
                    testStatusBadge.textContent = 'Not Tested';
                    testStatusBadge.className = 'badge bg-secondary';
                    testStatusBadge.style.backgroundColor = '#6c757d';
                } else {
                    testStatusBadge.textContent = 'Unknown';
                    testStatusBadge.className = 'badge bg-secondary';
                    testStatusBadge.style.backgroundColor = '#6c757d';
                }
            } else {
                testStatusDiv.style.display = 'none';
            }
        }

        function saveCommunicationSettings() {
            const workpointId = '<?= $_SESSION['workpoint_id'] ?>';
            const form = document.getElementById('communicationSetupForm');
            
            // Debug: Check checkbox state before submission
            const whatsappCheckbox = document.getElementById('whatsappActive');
            const facebookCheckbox = document.getElementById('facebookActive');
            console.log('WhatsApp checkbox checked:', whatsappCheckbox.checked);
            console.log('Facebook checkbox checked:', facebookCheckbox.checked);
            
            const formData = new FormData(form);
            formData.append('workpoint_id', workpointId);

            // Debug: Log what's being submitted
            console.log('Form data being submitted:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ': ' + value);
            }

            fetch('admin/save_communication_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Save response:', data);
                if (data.success) {
                    alert('Communication settings saved successfully!');
                    document.getElementById('communicationSetupModal').style.display = 'none';
                } else {
                    alert('Error saving settings: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving communication settings');
            });
        }

        function testWhatsAppConnection() {
            const workpointId = '<?= $_SESSION['workpoint_id'] ?>';
            const formData = new FormData(document.getElementById('communicationSetupForm'));
            formData.append('workpoint_id', workpointId);
            formData.append('test_platform', 'whatsapp_business');

            // Show loading indicator
            showTestLoading('whatsapp');

            fetch('admin/test_communication_connection.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Just show testing state, polling will update status automatically
            })
            .catch(error => {
                console.error('Error:', error);
                // Reset button on error
                setTimeout(() => {
                    resetTestButton('whatsapp');
                }, 1000);
            });
        }

        function testFacebookConnection() {
            const workpointId = '<?= $_SESSION['workpoint_id'] ?>';
            const formData = new FormData(document.getElementById('communicationSetupForm'));
            formData.append('workpoint_id', workpointId);
            formData.append('test_platform', 'facebook_messenger');

            // Show loading indicator
            showTestLoading('facebook');

            fetch('admin/test_communication_connection.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Just show testing state, polling will update status automatically
            })
            .catch(error => {
                console.error('Error:', error);
                // Reset button on error
                setTimeout(() => {
                    resetTestButton('facebook');
                }, 1000);
            });
        }

        const CREDENTIALS_REFRESH_BASE_URL = '<?= defined('CREDENTIALS_REFRESH_BASE_URL') ? CREDENTIALS_REFRESH_BASE_URL : 'https://voice.rom2.co.uk/api/refresh-credentials' ?>';

        function refreshWhatsAppCredentials() {
            const phoneId = (document.getElementById('whatsappPhoneNumberId').value || '').trim();
            if (!phoneId) { openResponseModal({ status: 'N/A', url: '-', body: 'Please enter WhatsApp Phone Number ID first.' }); return; }
            const url = `${CREDENTIALS_REFRESH_BASE_URL}?platform=whatsapp&whatsapp_phone_id=${encodeURIComponent(phoneId)}`;
            fetch(url)
                .then(async r => {
                    const status = r.status + (r.ok ? ' OK' : ' ERROR');
                    const text = await r.text();
                    let json; try { json = JSON.parse(text); } catch (e) { json = null; }
                    return { status, url, body: json ? JSON.stringify(json, null, 2) : text };
                })
                .then(payload => openResponseModal(payload))
                .catch(err => openResponseModal({ status: 'FAILED', url, body: String(err) }));
        }

        function refreshFacebookCredentials() {
            const pageId = (document.getElementById('facebookPageId').value || '').trim();
            if (!pageId) { openResponseModal({ status: 'N/A', url: '-', body: 'Please enter Facebook Page ID first.' }); return; }
            const url = `${CREDENTIALS_REFRESH_BASE_URL}?platform=facebook_messenger&facebook_page_id=${encodeURIComponent(pageId)}`;
            fetch(url)
                .then(async r => {
                    const status = r.status + (r.ok ? ' OK' : ' ERROR');
                    const text = await r.text();
                    let json; try { json = JSON.parse(text); } catch (e) { json = null; }
                    return { status, url, body: json ? JSON.stringify(json, null, 2) : text };
                })
                .then(payload => openResponseModal(payload))
                .catch(err => openResponseModal({ status: 'FAILED', url, body: String(err) }));
        }

        function openResponseModal({ status, url, body }) {
            document.getElementById('respStatus').textContent = status;
            document.getElementById('respUrl').textContent = url;
            document.getElementById('respBody').value = body;
            document.getElementById('responseModal').style.display = 'block';
        }
        function closeResponseModal() {
            document.getElementById('responseModal').style.display = 'none';
        }
        function copyResponseBody() {
            const ta = document.getElementById('respBody');
            ta.select();
            ta.setSelectionRange(0, ta.value.length);
            try { document.execCommand('copy'); } catch (e) {}
        }
        
        function manageSMSTemplate() {
            const workpointId = <?= $workpoint['unic_id'] ?>;
            const workpointName = '<?= addslashes($workpoint['name_of_the_place']) ?>';
            
            // Create modal for SMS template management
            const modalHtml = `
                <div class="modal fade show" id="smsTemplateModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5); overflow-y: auto; z-index: 10000;">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">SMS Templates for ${workpointName}</h5>
                                <button type="button" class="btn-close" onclick="closeSMSTemplateModal()"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <strong>Available Variables:</strong><br>
                                    <div class="row">
                                        <div class="col-md-6">
                                            • <code>{booking_id}</code> - Booking ID<br>
                                            • <code>{organisation_alias}</code> - Organisation name<br>
                                            • <code>{workpoint_name}</code> - Working point name<br>
                                            • <code>{workpoint_address}</code> - Address<br>
                                            • <code>{workpoint_phone}</code> - Phone number<br>
                                            • <code>{service_name}</code> - Service name
                                        </div>
                                        <div class="col-md-6">
                                            • <code>{start_time}</code> - Start time (HH:mm)<br>
                                            • <code>{end_time}</code> - End time (HH:mm)<br>
                                            • <code>{booking_date}</code> - Full date<br>
                                            • <code>{client_name}</code> - Client name<br>
                                            • <code>{specialist_name}</code> - Specialist name
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <h6 class="mb-2">Exclude SMS notifications when booking action comes from:</h6>
                                    <small class="text-muted mb-2 d-block">If a booking is cancelled/created/updated via these channels, NO SMS will be sent (to avoid duplicate notifications)</small>
                                    <div class="d-flex gap-3 flex-wrap">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="channel_PHONE" value="PHONE">
                                            <label class="form-check-label" for="channel_PHONE">Phone Call</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="channel_SMS" value="SMS" checked>
                                            <label class="form-check-label" for="channel_SMS">SMS</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="channel_WEB" value="WEB">
                                            <label class="form-check-label" for="channel_WEB">Web Portal</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="channel_WHATSAPP" value="WHATSAPP">
                                            <label class="form-check-label" for="channel_WHATSAPP">WhatsApp</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="channel_MESSENGER" value="MESSENGER">
                                            <label class="form-check-label" for="channel_MESSENGER">Messenger</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <form id="smsTemplateForm">
                                    <input type="hidden" id="sms_workpoint_id" value="${workpointId}">
                                    
                                    <!-- Cancellation Template -->
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <label for="sms_cancellation_template" class="form-label fw-bold">Cancellation Template:</label>
                                            <textarea class="form-control" id="sms_cancellation_template" rows="3" placeholder="Loading..."></textarea>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <small class="text-muted">This template will be used when sending SMS notifications for cancelled bookings.</small>
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="resetToDefaultTemplate('cancellation')">Reset to Default</button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Creation Template -->
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <label for="sms_creation_template" class="form-label fw-bold">Creation Template:</label>
                                            <textarea class="form-control" id="sms_creation_template" rows="3" placeholder="Loading..."></textarea>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <small class="text-muted">This template will be used when sending SMS notifications for new bookings.</small>
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="resetToDefaultTemplate('creation')">Reset to Default</button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Update Template -->
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <label for="sms_update_template" class="form-label fw-bold">Update Template:</label>
                                            <textarea class="form-control" id="sms_update_template" rows="3" placeholder="Loading..."></textarea>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <small class="text-muted">This template will be used when sending SMS notifications for booking updates.</small>
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="resetToDefaultTemplate('update')">Reset to Default</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeSMSTemplateModal()">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="saveSMSTemplate()">Save All Templates</button>
                            </div>
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

        // ========== WORKPOINT HOLIDAYS MANAGEMENT ==========
        let workpointTimeOffData = new Set();
        let workpointTimeOffDetails = {}; // Stores { date: { type, workStart, workEnd, isRecurring, description } }
        let workpointHolidaysWorkpointId = null;

        function openWorkpointHolidaysModal() {
            const workpointId = <?= $_SESSION['workpoint_id'] ?? 0 ?>;
            if (!workpointId) {
                alert('No workpoint selected');
                return;
            }

            workpointHolidaysWorkpointId = workpointId;

            // Load existing holidays
            loadWorkpointHolidays(workpointId);

            // Create modal
            const modal = document.createElement('div');
            modal.id = 'workpointHolidaysModal';
            modal.className = 'modal';
            modal.style.cssText = 'display:block; position:fixed; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; overflow-y:auto;';
            modal.innerHTML = `
                <div style="background:#fff; width:90%; max-width:1200px; height:90vh; margin:2% auto; overflow-y:auto; border-radius:8px; box-shadow:0 6px 24px rgba(0,0,0,0.2);">
                    <div style="background:#ffc107; color:#000; padding:16px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:1;">
                        <h3 style="margin:0;"><i class="fas fa-calendar-times"></i> Workpoint Holidays & Closures</h3>
                        <span style="cursor:pointer; font-size:32px; font-weight:bold; color:#000; line-height:1;" onclick="closeWorkpointHolidaysModal()">&times;</span>
                    </div>
                    <div style="padding:20px;">
                        <!-- Table layout matching specialist holidays -->
                        <table style="width:100%; margin:0; padding:0; border:0; border-spacing:0;">
                            <tr>
                                <td style="vertical-align:top; border:0; margin:0; padding:0;">
                                    <div id="workpointInfo" style="margin-bottom:10px; font-size:14px;">
                                        <!-- Workpoint info will be displayed here -->
                                    </div>
                                </td>
                                <td rowspan="3" style="vertical-align:top; border:0; margin:0; padding:0; width:200px;">
                                    <!-- Selected dates summary -->
                                    <div style="padding:10px; background:#f8f9fa; border-radius:5px; margin-left:10px; height:600px; box-sizing:border-box; overflow-y:auto;">
                                        <h5 style="margin-bottom:10px; text-align:center; font-size:0.9em;">Selected Holidays</h5>
                                        <div id="selectedWorkpointHolidaysList" style="text-align:left;">
                                            <!-- Selected dates will be listed here -->
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="vertical-align:top; border:0; margin:0; padding:0;">
                                    <!-- 12 month calendar grid -->
                                    <div id="workpointHolidaysCalendar" style="display:grid; grid-template-columns:repeat(4, minmax(200px, 1fr)); gap:0px;">
                                        <!-- Months will be generated here -->
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="vertical-align:top; border:0; margin:0; padding:0;">
                                    <!-- Empty row to ensure proper height -->
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            renderWorkpointHolidaysCalendar();
        }

        function closeWorkpointHolidaysModal() {
            const modal = document.getElementById('workpointHolidaysModal');
            if (modal) modal.remove();
        }

        function loadWorkpointHolidays(workpointId) {
            const formData = new FormData();
            formData.append('action', 'get_time_off_details');
            formData.append('workingpoint_id', workpointId);

            fetch('admin/workpoint_time_off_auto_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    workpointTimeOffData = new Set(data.dates || []);
                    workpointTimeOffDetails = data.details || {};
                    renderWorkpointHolidaysCalendar();
                    updateSelectedWorkpointHolidaysList();
                }
            })
            .catch(error => console.error('Error loading holidays:', error));
        }

        function renderWorkpointHolidaysCalendar() {
            const container = document.getElementById('workpointHolidaysCalendar');
            if (!container) return;

            container.innerHTML = '';

            const today = new Date();
            const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                               'July', 'August', 'September', 'October', 'November', 'December'];

            // Start with current month
            const currentMonth = today.getMonth();
            const currentYear = today.getFullYear();

            for (let i = 0; i < 12; i++) {
                const monthIndex = (currentMonth + i) % 12;
                const year = currentYear + Math.floor((currentMonth + i) / 12);
                const monthDiv = document.createElement('div');
                monthDiv.style.cssText = 'background:white; border:1px solid #ddd; border-radius:3px; padding:6px; transform:scale(0.75); transform-origin:top left; margin-bottom:-50px; margin-right:-70px;';

                // Month header with year and month
                const monthHeader = document.createElement('div');
                monthHeader.style.cssText = 'text-align:center; font-weight:bold; margin-bottom:5px; color:#333; font-size:13px;';
                monthHeader.textContent = `${year} ${monthNames[monthIndex]}`;
                monthDiv.appendChild(monthHeader);

                // Days grid
                const daysGrid = document.createElement('div');
                daysGrid.style.cssText = 'display:grid; grid-template-columns:repeat(7, 1fr); gap:0px; font-size:11px;';

                // Day headers - Monday first, weekends in red
                const dayHeaders = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
                dayHeaders.forEach((day, index) => {
                    const dayHeader = document.createElement('div');
                    const isWeekend = index >= 5;
                    dayHeader.style.cssText = `text-align:center; font-weight:bold; color:${isWeekend ? '#dc3545' : '#666'}; padding:2px; font-size:10px;`;
                    dayHeader.textContent = day;
                    daysGrid.appendChild(dayHeader);
                });

                // Get first day of month and number of days
                let firstDay = new Date(year, monthIndex, 1).getDay();
                // Adjust for Monday as first day (0 = Sunday, so convert to Monday = 0)
                firstDay = firstDay === 0 ? 6 : firstDay - 1;
                const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();

                // Empty cells for days before month starts
                for (let j = 0; j < firstDay; j++) {
                    const emptyCell = document.createElement('div');
                    daysGrid.appendChild(emptyCell);
                }

                // Days of month
                for (let day = 1; day <= daysInMonth; day++) {
                    const dayCell = document.createElement('div');
                    const dateStr = `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

                    // Check if this date is today
                    const isToday = dateStr === todayStr;

                    // Check if weekend
                    const dayOfWeek = new Date(year, monthIndex, day).getDay();
                    const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;

                    // Base styling
                    let bgColor = '#fff';
                    let textColor = isWeekend ? '#dc3545' : '#333';
                    let cursor = 'pointer';

                    const isSelected = workpointTimeOffData.has(dateStr);
                    if (isSelected) {
                        const dayOffData = workpointTimeOffDetails[dateStr] || { type: 'full' };
                        bgColor = dayOffData.type === 'partial' ? '#f59e0b' : '#dc3545';
                        textColor = '#fff';
                    } else if (isToday) {
                        bgColor = '#007bff';
                        textColor = '#fff';
                    }

                    dayCell.style.cssText = `
                        text-align:center;
                        padding:6px 4px;
                        cursor:${cursor};
                        border:none;
                        background:${bgColor};
                        color:${textColor};
                        transition:all 0.2s;
                        font-size:12px;
                        width:28px;
                        height:28px;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        border-radius:50%;
                        margin:2px auto;
                    `;

                    dayCell.textContent = day;
                    dayCell.dataset.date = dateStr;

                    // Set tooltip
                    if (isSelected) {
                        const dayOffData = workpointTimeOffDetails[dateStr] || { type: 'full' };
                        dayCell.title = dayOffData.type === 'partial' ? 'Partial Day Closure' : 'Full Day Closure';
                    } else if (isToday) {
                        dayCell.title = 'Today';
                    } else {
                        dayCell.title = '';
                    }

                    // Click handler
                    dayCell.onclick = function() {
                        toggleWorkpointHoliday(dateStr);
                    };

                    // Hover effect
                    dayCell.onmouseover = function() {
                        if (!isSelected && !isToday) {
                            this.style.background = '#f0f0f0';
                        }
                    };

                    dayCell.onmouseout = function() {
                        if (!isSelected && !isToday) {
                            this.style.background = '#fff';
                        }
                    };

                    daysGrid.appendChild(dayCell);
                }

                monthDiv.appendChild(daysGrid);
                container.appendChild(monthDiv);
            }
        }

        function toggleWorkpointHoliday(dateStr) {
            if (workpointTimeOffData.has(dateStr)) {
                // Remove
                workpointTimeOffData.delete(dateStr);
                delete workpointTimeOffDetails[dateStr];
                autoSaveRemoveWorkpointHoliday(dateStr);
            } else {
                // Add
                workpointTimeOffData.add(dateStr);
                workpointTimeOffDetails[dateStr] = { type: 'full', isRecurring: false, description: '' };
                autoSaveAddWorkpointHoliday(dateStr);
            }
            renderWorkpointHolidaysCalendar();
            updateSelectedWorkpointHolidaysList();
        }

        function updateSelectedWorkpointHolidaysList() {
            const listDiv = document.getElementById('selectedWorkpointHolidaysList');
            if (!listDiv) return;

            const datesArray = Array.from(workpointTimeOffData).sort();

            if (datesArray.length === 0) {
                listDiv.innerHTML = '<em style="color:#999;">No holidays selected</em>';
            } else {
                listDiv.innerHTML = datesArray.map(date => {
                    const d = new Date(date + 'T12:00:00');
                    const dayOffData = workpointTimeOffDetails[date] || { type: 'full', isRecurring: false, description: '' };
                    const isPartial = dayOffData.type === 'partial';
                    const isRecurring = dayOffData.isRecurring || false;
                    const description = dayOffData.description || '';
                    const buttonBgColor = isPartial ? '#f59e0b' : '#dc3545';
                    const buttonIcon = isPartial ? '◐' : '⊗';

                    return `<div style="margin:6px 0;">
                        <div onclick="toggleWorkpointHolidayDropdown('${date}')"
                             style="display:flex; align-items:center; justify-content:space-between; padding:8px; background:${buttonBgColor}; color:white; border-radius:4px; cursor:pointer;">
                            <span style="font-size:11px; font-weight:500;">
                                <span style="font-size:1.1em; margin-right:4px;">${buttonIcon}</span>${d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })}
                            </span>
                            <span onclick="event.stopPropagation(); removeWorkpointHoliday('${date}')"
                                  style="color:white; cursor:pointer; font-size:18px; font-weight:bold; padding:0 4px;">×</span>
                        </div>
                        <div id="wp-dropdown-${date}" style="display:none; background:#f8f9fa; border:1px solid #ddd; border-radius:4px; padding:10px; margin-top:2px; font-size:11px;">
                            <div style="margin-bottom:8px;">
                                <label style="display:block; margin-bottom:4px; font-weight:600;">Description:</label>
                                <input type="text" id="wp-desc-${date}" value="${description}" onchange="updateWorkpointDescription('${date}')"
                                       placeholder="e.g., Christmas Day, Emergency Closure" style="width:100%; padding:5px; border:1px solid #ddd; border-radius:3px; font-size:11px;">
                            </div>
                            <div style="margin-bottom:8px;">
                                <label style="cursor:pointer;">
                                    <input type="checkbox" id="wp-recurring-${date}" ${isRecurring ? 'checked' : ''} onchange="updateWorkpointRecurring('${date}', this.checked)">
                                    <span style="font-size:11px;">Recurring yearly holiday</span>
                                </label>
                            </div>
                            <div style="margin-bottom:8px;">
                                <label style="display:block; margin-bottom:4px; font-weight:600;">Type:</label>
                                <select id="wp-type-${date}" onchange="updateWorkpointDayOffType('${date}', this.value)" style="width:100%; padding:5px; border:1px solid #ddd; border-radius:3px; font-size:11px;">
                                    <option value="full" ${!isPartial ? 'selected' : ''}>Fully Closed</option>
                                    <option value="partial" ${isPartial ? 'selected' : ''}>Partially Open</option>
                                </select>
                            </div>
                            <div id="wp-partial-${date}" style="display:${isPartial ? 'block' : 'none'};">
                                <label style="display:block; margin-bottom:4px; font-weight:600;">Open Hours:</label>
                                <div style="display:flex; gap:5px; align-items:center;">
                                    <input type="time" id="wp-start-${date}" value="${dayOffData.workStart || ''}" onchange="updateWorkpointWorkingHours('${date}')"
                                           style="flex:1; padding:5px; border:1px solid #ddd; border-radius:3px; font-size:11px;">
                                    <span style="font-size:11px;">to</span>
                                    <input type="time" id="wp-end-${date}" value="${dayOffData.workEnd || ''}" onchange="updateWorkpointWorkingHours('${date}')"
                                           style="flex:1; padding:5px; border:1px solid #ddd; border-radius:3px; font-size:11px;">
                                </div>
                            </div>
                        </div>
                    </div>`;
                }).join('');
            }
        }

        function toggleWorkpointHolidayDropdown(date) {
            const dropdown = document.getElementById(`wp-dropdown-${date}`);
            if (dropdown) {
                dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            }
        }

        function removeWorkpointHoliday(date) {
            workpointTimeOffData.delete(date);
            delete workpointTimeOffDetails[date];
            autoSaveRemoveWorkpointHoliday(date);
            renderWorkpointHolidaysCalendar();
            updateSelectedWorkpointHolidaysList();
        }

        function updateWorkpointDayOffType(date, type) {
            if (type === 'partial' && workpointTimeOffDetails[date].type === 'full') {
                autoSaveConvertWorkpointToPartial(date);
            } else if (type === 'full' && workpointTimeOffDetails[date].type === 'partial') {
                autoSaveConvertWorkpointToFull(date);
            }
            workpointTimeOffDetails[date].type = type;
            document.getElementById(`wp-partial-${date}`).style.display = type === 'partial' ? 'block' : 'none';
            updateSelectedWorkpointHolidaysList();
            renderWorkpointHolidaysCalendar();
        }

        function updateWorkpointWorkingHours(date) {
            const workStart = document.getElementById(`wp-start-${date}`).value;
            const workEnd = document.getElementById(`wp-end-${date}`).value;

            if (!workStart || !workEnd) return;

            workpointTimeOffDetails[date].workStart = workStart;
            workpointTimeOffDetails[date].workEnd = workEnd;
            autoSaveUpdateWorkpointWorkingHours(date, workStart, workEnd);
        }

        function updateWorkpointRecurring(date, isRecurring) {
            workpointTimeOffDetails[date].isRecurring = isRecurring;
            autoSaveUpdateWorkpointRecurring(date, isRecurring);
        }

        function updateWorkpointDescription(date) {
            const description = document.getElementById(`wp-desc-${date}`).value;
            workpointTimeOffDetails[date].description = description;
            autoSaveUpdateWorkpointDescription(date, description);
        }

        // Auto-save functions
        function autoSaveAddWorkpointHoliday(date) {
            const formData = new FormData();
            formData.append('action', 'add_full_day');
            formData.append('workingpoint_id', workpointHolidaysWorkpointId);
            formData.append('date', date);
            formData.append('is_recurring', 0);
            formData.append('description', '');

            fetch('admin/workpoint_time_off_auto_ajax.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .catch(error => console.error('Error:', error));
        }

        function autoSaveRemoveWorkpointHoliday(date) {
            const formData = new FormData();
            formData.append('action', 'remove_day');
            formData.append('workingpoint_id', workpointHolidaysWorkpointId);
            formData.append('date', date);

            fetch('admin/workpoint_time_off_auto_ajax.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .catch(error => console.error('Error:', error));
        }

        function autoSaveConvertWorkpointToPartial(date) {
            const formData = new FormData();
            formData.append('action', 'convert_to_partial');
            formData.append('workingpoint_id', workpointHolidaysWorkpointId);
            formData.append('date', date);

            fetch('admin/workpoint_time_off_auto_ajax.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .catch(error => console.error('Error:', error));
        }

        function autoSaveConvertWorkpointToFull(date) {
            const formData = new FormData();
            formData.append('action', 'convert_to_full');
            formData.append('workingpoint_id', workpointHolidaysWorkpointId);
            formData.append('date', date);

            fetch('admin/workpoint_time_off_auto_ajax.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .catch(error => console.error('Error:', error));
        }

        function autoSaveUpdateWorkpointWorkingHours(date, workStart, workEnd) {
            const formData = new FormData();
            formData.append('action', 'update_working_hours');
            formData.append('workingpoint_id', workpointHolidaysWorkpointId);
            formData.append('date', date);
            formData.append('work_start', workStart);
            formData.append('work_end', workEnd);

            fetch('admin/workpoint_time_off_auto_ajax.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .catch(error => console.error('Error:', error));
        }

        function autoSaveUpdateWorkpointRecurring(date, isRecurring) {
            const formData = new FormData();
            formData.append('action', 'update_recurring');
            formData.append('workingpoint_id', workpointHolidaysWorkpointId);
            formData.append('date', date);
            formData.append('is_recurring', isRecurring ? 1 : 0);

            fetch('admin/workpoint_time_off_auto_ajax.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .catch(error => console.error('Error:', error));
        }

        function autoSaveUpdateWorkpointDescription(date, description) {
            const formData = new FormData();
            formData.append('action', 'update_description');
            formData.append('workingpoint_id', workpointHolidaysWorkpointId);
            formData.append('date', date);
            formData.append('description', description);

            fetch('admin/workpoint_time_off_auto_ajax.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .catch(error => console.error('Error:', error));
        }
    </script>
    <div id="responseModal" class="modal" style="display:none; position:fixed; left:0; top:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index: 10000;">
        <div class="modal-content" style="background:#fff; max-width:700px; margin:5% auto; padding:16px; border-radius:8px; box-shadow:0 6px 24px rgba(0,0,0,0.2);">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Webhook Response</h5>
                <span style="cursor:pointer; font-size:22px; color:#6c757d; font-weight:bold;" onclick="closeResponseModal()">&times;</span>
            </div>
            <div class="mb-2" style="font-size: 0.9rem; color:#555;">
                <div><strong>Status:</strong> <span id="respStatus">-</span></div>
                <div style="word-break: break-all;"><strong>URL:</strong> <span id="respUrl">-</span></div>
            </div>
            <div class="mb-2">
                <textarea id="respBody" class="form-control" rows="8" style="font-family: monospace;" readonly></textarea>
            </div>
            <div class="text-end">
                <button type="button" class="btn btn-sm btn-secondary me-2" onclick="copyResponseBody()">Copy</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="closeResponseModal()">Close</button>
            </div>
        </div>
    </div>
</body>
</html> 
