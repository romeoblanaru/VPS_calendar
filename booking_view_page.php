<?php
// session_start() is already called in lang_loader.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/lang_loader.php';
require_once __DIR__ . '/includes/calendar_functions.php';
require_once __DIR__ . '/includes/timezone_config.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// Check if this is supervisor mode
$supervisor_mode = isset($_GET['supervisor_mode']) && $_GET['supervisor_mode'] === 'true';
$working_point_user_id = $_GET['working_point_user_id'] ?? null;

if ($supervisor_mode && $working_point_user_id) {
    // Supervisor mode - get workpoint information directly
    // In this system, working_point_user_id is actually the workpoint_id
    $stmt = $pdo->prepare("SELECT * FROM working_points WHERE unic_id = ?");
    $stmt->execute([$working_point_user_id]);
    $workpoint = $stmt->fetch();
    
    if (!$workpoint) {
        die("Workpoint not found");
    }
    
    // Get organization information
    $stmt = $pdo->prepare("SELECT * FROM organisations WHERE unic_id = ?");
    $stmt->execute([$workpoint['organisation_id']]);
    $organisation = $stmt->fetch();
    
    // For supervisor mode, we'll show all specialists at this workpoint
    $specialist_id = null; // Will be used to get all specialists
    $specialist = null; // No single specialist in supervisor mode
    $workpoint_id = $workpoint['unic_id']; // <-- Ensure this is set for the modal
    
} else {
    // Regular specialist mode
    $specialist_id = $_GET['specialist_id'] ?? null;
    
    if (!$specialist_id) {
        die("Specialist ID is required");
    }
    
    // Get specialist information
    $stmt = $pdo->prepare("SELECT * FROM specialists WHERE unic_id = ?");
    $stmt->execute([$specialist_id]);
    $specialist = $stmt->fetch();
    
    if (!$specialist) {
        die("Specialist not found");
    }
    
    // Check if the logged-in user has permission to view this specialist's calendar
    $user_role = $_SESSION['role'] ?? '';
    $user_specialist_id = $_SESSION['specialist_id'] ?? 0;
    
    // Specialists can only view their own calendar
    if ($user_role === 'specialist_user' && $user_specialist_id != $specialist_id) {
        $_SESSION['error_message'] = "Access denied: You can only view your own calendar. Redirecting to your calendar...";
                $period_redirect = $_GET['period'] ?? 'this_month';
        $redirectUrl = "booking_view_page.php?specialist_id=" . $user_specialist_id . "&period=" . $period_redirect;
        if (isset($_GET['start_date'])) { $redirectUrl .= "&start_date=" . urlencode($_GET['start_date']); }
        if (isset($_GET['end_date'])) { $redirectUrl .= "&end_date=" . urlencode($_GET['end_date']); }
        header("Location: " . $redirectUrl);
        exit;
    }
    
    // Workpoint supervisors can view calendars for specialists at their workpoint
    if ($user_role === 'workpoint_user') {
        $user_workpoint_id = $_SESSION['workpoint_id'] ?? 0;
        if ($user_workpoint_id > 0) {
            // Check if the specialist works at this supervisor's workpoint
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM working_program wp 
                WHERE wp.specialist_id = ? AND wp.working_place_id = ?
            ");
            $stmt->execute([$specialist_id, $user_workpoint_id]);
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                $_SESSION['error_message'] = "Access denied: You can only view calendars for specialists at your workpoint.";
                header("Location: workpoint_supervisor_dashboard.php?workpoint_id=" . $user_workpoint_id);
                exit;
            }
        }
    }
    
    // Organisation users can view calendars for specialists in their organisation
    if ($user_role === 'organisation_user') {
        if ($specialist['organisation_id'] != $_SESSION['organisation_id']) {
            $_SESSION['error_message'] = "Access denied: You can only view calendars for specialists in your organisation.";
            header("Location: organisation_dashboard.php");
            exit;
        }
    }
    
    // Admin users can view any calendar (no additional check needed)
    
    // Get specialist permissions and colors
    $stmt = $pdo->prepare("
        SELECT specialist_can_delete_booking, specialist_can_modify_booking, 
               specialist_can_add_services, specialist_can_modify_services, specialist_can_delete_services,
               back_color, foreground_color
        FROM specialists_setting_and_attr 
        WHERE specialist_id = ?
    ");
    $stmt->execute([$specialist_id]);
    $specialist_permissions = $stmt->fetch();
    
    // Set default permissions and colors if not found
    if (!$specialist_permissions) {
        $specialist_permissions = [
            'specialist_can_delete_booking' => 0,
            'specialist_can_modify_booking' => 0,
            'specialist_can_add_services' => 0,
            'specialist_can_modify_services' => 0,
            'specialist_can_delete_services' => 0,
            'back_color' => '#667eea',
            'foreground_color' => '#ffffff'
        ];
    }
    
    // Get organization information
    $stmt = $pdo->prepare("SELECT * FROM organisations WHERE unic_id = ?");
    $stmt->execute([$specialist['organisation_id']]);
    $organisation = $stmt->fetch();
}

// Set timezone based on working point if available, otherwise fall back to organization
if ($supervisor_mode && $workpoint) {
    // Supervisor mode: use the specific workpoint
    setTimezoneForWorkingPoint($workpoint);
} elseif (!$supervisor_mode && !empty($working_points)) {
    // Specialist mode: use the first working point (or primary one)
    setTimezoneForWorkingPoint($working_points[0]);
} else {
    // Fallback: use organization timezone
setTimezoneForOrganisation($organisation);
}

if ($supervisor_mode) {
    // Supervisor mode - get all specialists with at least one non-zero shift at this workpoint
    $stmt = $pdo->prepare("\n        SELECT DISTINCT s.* \n        FROM specialists s\n        INNER JOIN working_program wpr ON s.unic_id = wpr.specialist_id \n        WHERE wpr.working_place_id = ?\n          AND ((wpr.shift1_start <> '00:00:00' AND wpr.shift1_end <> '00:00:00')\n            OR (wpr.shift2_start <> '00:00:00' AND wpr.shift2_end <> '00:00:00')\n            OR (wpr.shift3_start <> '00:00:00' AND wpr.shift3_end <> '00:00:00'))\n        ORDER BY s.name\n    ");
    $stmt->execute([$workpoint['unic_id']]);
    $specialists = $stmt->fetchAll();
    
    // Handle selected specialist in supervisor mode
    $selected_specialist = $_GET['selected_specialist'] ?? null;
    
    // If no specialist is selected but we have specialists, select the first one
    if (!$selected_specialist && !empty($specialists)) {
        $selected_specialist = $specialists[0]['unic_id'];
    }
    
    // Get working points (just the current workpoint)
    $working_points = [$workpoint];
    
    // Get working program for all specialists at this workpoint
    $stmt = $pdo->prepare("\n        SELECT * FROM working_program \n        WHERE working_place_id = ?\n        ORDER BY specialist_id, day_of_week\n    ");
    $stmt->execute([$workpoint['unic_id']]);
    $working_program = $stmt->fetchAll();
    
    // For supervisor mode, set has_multiple_workpoints to false (supervisors don't need this feature)
    $has_multiple_workpoints = false;
    
} else {
    // Regular specialist mode
    // Get working points for this specialist from working_program table
    $stmt = $pdo->prepare("
        SELECT DISTINCT wp.* 
        FROM working_points wp 
        INNER JOIN working_program wpr ON wp.unic_id = wpr.working_place_id 
        WHERE wpr.specialist_id = ?
        ORDER BY wp.name_of_the_place
    ");
    $stmt->execute([$specialist_id]);
    $working_points = $stmt->fetchAll();
    
    // Check if specialist has multiple working points
    $has_multiple_workpoints = count($working_points) > 1;
    
    // Set workpoint_id for specialist mode (use first working point if not specified)
    if (!isset($workpoint_id) && !empty($working_points)) {
        $workpoint_id = $working_points[0]['unic_id'];
    }
    
    // Create a lookup array to get working point index by ID
    $workpoint_index_lookup = [];
    foreach ($working_points as $index => $wp) {
        $workpoint_index_lookup[$wp['unic_id']] = $index + 1; // 1-based index
    }

    // Get working program for this specialist
    $stmt = $pdo->prepare("SELECT * FROM working_program WHERE specialist_id = ?");
    $stmt->execute([$specialist_id]);
    $working_program = $stmt->fetchAll();
}

// Get current period selection (default to this month for both specialist and supervisor)
if ($supervisor_mode) {
$period = $_GET['period'] ?? 'this_month';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
} else {
$period = $_GET['period'] ?? 'this_month';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
}

// Calculate date range and design based on period
$date_range = calculateDateRange($period, $start_date, $end_date);
$start_date = $date_range['start'];
$end_date = $date_range['end'];
$calendar_design = $date_range['design'];

// Debug: Log the calendar design
error_log("Calendar Design: " . $calendar_design . " for period: " . $period . " start: " . $start_date . " end: " . $end_date);

// Get bookings for the selected period
if ($supervisor_mode) {
    // Supervisor mode - get all bookings for this workpoint
    $stmt = $pdo->prepare("
        SELECT b.*, wp.name_of_the_place, wp.address, s.name_of_service, sp.name as specialist_name
        FROM booking b
        LEFT JOIN working_points wp ON b.id_work_place = wp.unic_id
        LEFT JOIN services s ON b.service_id = s.unic_id
        LEFT JOIN specialists sp ON b.id_specialist = sp.unic_id
        WHERE b.id_work_place = ? 
        AND DATE(b.booking_start_datetime) BETWEEN ? AND ?
        ORDER BY b.booking_start_datetime
    ");
    $stmt->execute([$workpoint['unic_id'], $start_date, $end_date]);
    $bookings = $stmt->fetchAll();
} else {
    // Regular specialist mode
    $stmt = $pdo->prepare("
        SELECT b.*, wp.name_of_the_place, wp.address, s.name_of_service 
        FROM booking b
        LEFT JOIN working_points wp ON b.id_work_place = wp.unic_id
        LEFT JOIN services s ON b.service_id = s.unic_id
        WHERE b.id_specialist = ? 
        AND DATE(b.booking_start_datetime) BETWEEN ? AND ?
        ORDER BY b.booking_start_datetime
    ");
    $stmt->execute([$specialist_id, $start_date, $end_date]);
    $bookings = $stmt->fetchAll();
}

// Get specialist time off dates for the selected period
$time_off_dates = [];
if ($supervisor_mode && $selected_specialist) {
    $stmt = $pdo->prepare("
        SELECT date_off, start_time, end_time 
        FROM specialist_time_off 
        WHERE specialist_id = ? 
        AND date_off BETWEEN ? AND ?
        ORDER BY date_off
    ");
    $stmt->execute([$selected_specialist, $start_date, $end_date]);
    $time_off_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create lookup array by date
    foreach ($time_off_data as $off) {
        $time_off_dates[$off['date_off']] = [
            'start_time' => $off['start_time'],
            'end_time' => $off['end_time']
        ];
    }
} elseif (!$supervisor_mode && $specialist_id) {
    $stmt = $pdo->prepare("
        SELECT date_off, start_time, end_time 
        FROM specialist_time_off 
        WHERE specialist_id = ? 
        AND date_off BETWEEN ? AND ?
        ORDER BY date_off
    ");
    $stmt->execute([$specialist_id, $start_date, $end_date]);
    $time_off_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create lookup array by date
    foreach ($time_off_data as $off) {
        $time_off_dates[$off['date_off']] = [
            'start_time' => $off['start_time'],
            'end_time' => $off['end_time']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Calendar Booking System - <?= $supervisor_mode ? htmlspecialchars($workpoint['name_of_the_place']) . ' (Supervisor View)' : htmlspecialchars($specialist['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css?v=<?= time() ?>" rel="stylesheet">
    <link href="assets/css/booking_view_page.css?v=<?= time() ?>" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-color: #dee2e6;
            --shadow: 0 2px 10px rgba(0,0,0,0.1);
            <?php if (!$supervisor_mode && isset($specialist_permissions['back_color']) && isset($specialist_permissions['foreground_color'])): ?>
            --specialist-bg-color: <?= $specialist_permissions['back_color'] ?>;
            --specialist-fg-color: <?= $specialist_permissions['foreground_color'] ?>;
            <?php endif; ?>
        }
        
        /* Discrete mandatory field indicators */
        .required-indicator {
            color: #dc3545aa;
            font-weight: normal;
        }
        
        /* Light yellow background for required fields */
        input:required,
        select:required,
        textarea:required {
            background-color: #fffef0 !important; /* Very light yellow */
            border-color: #ced4da !important; /* Default border color */
            box-shadow: none !important;
        }
        
        input:required:focus,
        select:required:focus,
        textarea:required:focus {
            background-color: #fffef0 !important; /* Keep light yellow on focus */
            border-color: #80bdff !important; /* Default Bootstrap focus color */
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25) !important; /* Default Bootstrap focus shadow */
        }
        
        /* Notification popup styles */
        .booking-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 350px;
            max-width: 450px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            padding: 20px;
            z-index: 10000;
            animation: slideIn 0.4s ease-out, fadeOut 0.5s ease-in 4.5s forwards;
            border-left: 5px solid #28a745;
        }
        
        .booking-notification.update {
            border-left-color: #17a2b8;
        }
        
        .booking-notification.create {
            border-left-color: #28a745;
        }
        
        .booking-notification.delete {
            border-left-color: #dc3545;
        }
        
        .notification-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 20px;
        }
        
        .notification-icon.create {
            background: #d4edda;
            color: #28a745;
        }
        
        .notification-icon.update {
            background: #d1ecf1;
            color: #17a2b8;
        }
        
        .notification-icon.delete {
            background: #f8d7da;
            color: #dc3545;
        }
        
        .notification-body {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .notification-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 20px;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }
        
        .notification-close:hover {
            background: #f5f5f5;
            color: #333;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
        
        /* Custom Menu Tooltips */
        .menu-tooltip .tooltip-inner {
            background-color: #fffacd; /* Light yellow */
            color: #333333; /* Dark gray for contrast */
            font-weight: 500;
            border: 1px solid #f0e68c;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .menu-tooltip .tooltip-arrow::before {
            border-top-color: #fffacd !important;
            border-bottom-color: #fffacd !important;
            border-left-color: #fffacd !important;
            border-right-color: #fffacd !important;
        }
        
        /* Ensure blue text in tooltip remains blue */
        .menu-tooltip .tooltip-inner strong[style*="color: #4285f4"] {
            color: #4285f4 !important;
        }
        
        /* Override styles for Modify Schedule Modal to match COMPREHENSIVE SCHEDULE EDITOR */
        #modifyScheduleModal .modify-modal {
            width: 70% !important;
            max-width: 1000px !important;
            height: auto !important;
            max-height: 90vh !important;
            margin: 5vh auto !important;
            background: #fff !important;
            border-radius: 15px !important;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2) !important;
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
        }
        
        #modifyScheduleModal .modify-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            padding: 12px 25px !important;
            border-radius: 15px 15px 0 0 !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            border-bottom: none !important;
        }
        
        #modifyScheduleModal .modify-modal-header h3 {
            margin: 0 !important;
            font-size: 1.3rem !important;
            font-weight: 600 !important;
            color: white !important;
            padding: 0 !important;
        }
        
        #modifyScheduleModal .modify-modal-body {
            flex: 1 !important;
            padding: 10px !important;
            padding-bottom: 10px !important;
            background-color: #f8f9fa !important;
            overflow-y: auto !important;
            height: auto !important;
            max-height: calc(90vh - 120px) !important;
        }
        
        #modifyScheduleModal .individual-edit-section {
            background: white !important;
            padding: 20px !important;
            padding-bottom: 2px !important;
            border-radius: 10px !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08) !important;
            margin-bottom: 20px !important;
        }
        
        #modifyScheduleModal .individual-edit-section:last-child {
            margin-bottom: 0 !important;
        }
        
        #modifyScheduleModal .individual-edit-section h4 {
            font-size: 16px !important;
            margin-bottom: 3px !important;
            color: #333 !important;
            font-weight: 600 !important;
        }
        
        #modifyScheduleModal .schedule-editor-table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 10px !important;
        }
        
        #modifyScheduleModal .schedule-editor-table th,
        #modifyScheduleModal .schedule-editor-table td {
            border: 1px solid #dee2e6 !important;
            padding: 8px 6px !important;
            text-align: center !important;
            font-size: 11px !important;
        }
        
        #modifyScheduleModal .schedule-editor-table th {
            background-color: #f8f9fa !important;
            font-weight: 600 !important;
        }
        
        
        /* Style separator columns */
        #modifyScheduleModal .schedule-editor-table .separator-col {
            width: 12px !important;
            padding: 0 !important;
            background-color: transparent !important;
            border: none !important;
            border-left: none !important;
            border-right: none !important;
        }
        
        #modifyScheduleModal .schedule-editor-table th.separator-col {
            background-color: transparent !important;
        }
        
        /* Remove all styling from schedule-editor-table-container */
        #modifyScheduleModal .schedule-editor-table-container {
            border: 0 !important;
            background: transparent !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            padding: 0 !important;
        }
        
        #modifyScheduleModal .time-inputs {
            display: flex !important;
            gap: 2px !important;
            align-items: center !important;
        }
        
        #modifyScheduleModal .time-inputs input[type="time"],
        #modifyScheduleModal input[type="time"] {
            width: 70px !important;
            padding: 3px 0px !important;
            border: 1px solid #ced4da !important;
            border-radius: 3px !important;
            font-size: 12px !important;
            min-width: 70px !important;
            max-width: 70px !important;
        }
        
        #modifyScheduleModal .btn-clear-shift {
            background: #dc3545 !important;
            color: white !important;
            border: none !important;
            padding: 3px 6px !important;
            border-radius: 3px !important;
            font-size: 9px !important;
            cursor: pointer !important;
        }
        
        #modifyScheduleModal .btn-clear-shift:hover {
            background: #c82333 !important;
        }
        
        #modifyScheduleModal .quick-options-compact {
            background: #f8f9fa !important;
            padding: 10px !important;
            border-radius: 5px !important;
            margin-bottom: 0 !important;
            border: 1px solid #dee2e6 !important;
        }
        
        #modifyScheduleModal .quick-options-row {
            display: flex !important;
            gap: 12px !important;
            align-items: center !important;
            flex-wrap: nowrap !important;
            justify-content: space-between !important;
        }
        
        #modifyScheduleModal .quick-option-group {
            display: flex !important;
            align-items: center !important;
            gap: 4px !important;
            flex-shrink: 0 !important;
        }
        
        #modifyScheduleModal .quick-option-group label {
            font-size: 12px !important;
            margin: 0 !important;
            margin-right: 2px !important;
            text-align: right !important;
            min-width: 40px !important;
            max-width: 40px !important;
            white-space: nowrap !important;
            padding-right: 3px !important;
        }
        
        #modifyScheduleModal .quick-option-group select {
            width: 100px !important;
            padding: 3px 6px !important;
            border: 1px solid #ced4da !important;
            border-radius: 3px !important;
            font-size: 12px !important;
        }
        
        #modifyScheduleModal .quick-option-group button {
            background: #007bff !important;
            color: white !important;
            border: none !important;
            padding: 1px 5px !important;
            border-radius: 3px !important;
            font-size: 12px !important;
            cursor: pointer !important;
            min-width: auto !important;
            max-width: none !important;
        }
        
        #modifyScheduleModal .quick-option-group button:hover {
            background: #0056b3 !important;
        }
        
        #modifyScheduleModal .modify-modal-buttons {
            text-align: center !important;
            margin-top: 10px !important;
            padding-top: 10px !important;
            padding-bottom: 15px !important;
            border-top: 1px solid #dee2e6 !important;
            background: white !important;
            display: block !important;
            flex-shrink: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            justify-content: center !important;
            position: sticky !important;
            bottom: 0 !important;
            z-index: 10 !important;
        }
        
        /* Shift coloring - match specialist schedule display colors */
        #modifyScheduleModal input[type="time"].shift1-start-time,
        #modifyScheduleModal input[type="time"].shift1-end-time {
            background-color: #ffebee !important;
            color: #d32f2f !important;
            border-color: #ffcdd2 !important;
        }
        
        #modifyScheduleModal input[type="time"].shift2-start-time,
        #modifyScheduleModal input[type="time"].shift2-end-time {
            background-color: #e3f2fd !important;
            color: #1976d2 !important;
            border-color: #bbdefb !important;
        }
        
        #modifyScheduleModal input[type="time"].shift3-start-time,
        #modifyScheduleModal input[type="time"].shift3-end-time {
            background-color: #e8f5e8 !important;
            color: #2e7d32 !important;
            border-color: #c8e6c9 !important;
        }
        
        /* Apply background colors to shift cells in tbody only */
        /* Shift 1 cells - light red */
        #modifyScheduleModal .schedule-editor-table tbody td:nth-child(2),
        #modifyScheduleModal .schedule-editor-table tbody td:nth-child(3),
        #modifyScheduleModal .schedule-editor-table tbody td:nth-child(4) {
            background-color: #ffebee !important;
        }
        
        /* Shift 2 cells - light blue */
        #modifyScheduleModal .schedule-editor-table tbody td:nth-child(6),
        #modifyScheduleModal .schedule-editor-table tbody td:nth-child(7),
        #modifyScheduleModal .schedule-editor-table tbody td:nth-child(8) {
            background-color: #e3f2fd !important;
        }
        
        /* Shift 3 cells - light green */
        #modifyScheduleModal .schedule-editor-table tbody td:nth-child(10),
        #modifyScheduleModal .schedule-editor-table tbody td:nth-child(11),
        #modifyScheduleModal .schedule-editor-table tbody td:nth-child(12) {
            background-color: #e8f5e8 !important;
        }
        
        /* Remove internal borders within shifts */
        /* Shift 1 internal borders */
        #modifyScheduleModal .schedule-editor-table tbody td:nth-child(2) {
            border-right: 1px solid #ffebee !important;
            text-align: right !important;
        }
        #modifyScheduleModal .schedule-editor-table tbody td:nth-child(3) {
            border-left: 1px solid #ffebee !important;
            border-right: 1px solid #ffebee !important;
        }
        
        /* Shift 2 internal borders */
        #modifyScheduleModal .schedule-editor-table tbody td:nth-child(6) {
            border-right: 1px solid #e3f2fd !important;
            text-align: right !important;
        }
        #modifyScheduleModal .schedule-editor-table tbody td:nth-child(7) {
            border-left: 1px solid #e3f2fd !important;
            border-right: 1px solid #e3f2fd !important;
        }
        
        /* Shift 3 internal borders */
        #modifyScheduleModal .schedule-editor-table tbody td:nth-child(10) {
            border-right: 1px solid #e8f5e8 !important;
            text-align: right !important;
        }
        #modifyScheduleModal .schedule-editor-table tbody td:nth-child(11) {
            border-left: 1px solid #e8f5e8 !important;
            border-right: 1px solid #e8f5e8 !important;
        }
        
        /* Style the day column with lighter background */
        #modifyScheduleModal .schedule-editor-table .day-name {
            font-weight: 600 !important;
            background-color: #f8f9fa !important;
            padding: 4px 6px !important; /* Reduced top/bottom padding by 50% */
        }
        
        /* Make the second header row entirely white */
        #modifyScheduleModal .schedule-editor-table thead tr:nth-child(2) th {
            background-color: white !important;
        }
        
        /* Make the first cell of first row same background as day cells */
        #modifyScheduleModal .schedule-editor-table thead tr:nth-child(1) th:first-child {
            background-color: #f8f9fa !important;
        }
        
        /* Override btn-delete style for square shape in modify schedule modal */
        #modifyScheduleModal .btn-delete {
            border-radius: 0 !important;
            padding: 5px 12px !important;
            font-size: 11px !important;
            min-width: auto !important;
        }
        
        /* Time Off Modal Styles */
        #timeOffModal .modify-modal {
            max-height: 90vh !important;
            display: flex !important;
            flex-direction: column !important;
        }
        
        #timeOffModal .modify-modal-body {
            flex: 1 !important;
            overflow-y: auto !important;
        }
        
        /* Modify Specialist Modal - Variable Height */
        #modifySpecialistModal .modify-modal {
            height: auto !important;
            max-height: 90vh !important;
            display: flex !important;
            flex-direction: column !important;
            margin: 5vh auto auto auto !important;
            position: relative !important;
            top: 0 !important;
            transform: none !important;
        }
        
        #modifySpecialistModal .modify-modal-body {
            flex: 0 1 auto !important;
            overflow-y: auto !important;
            height: auto !important;
            max-height: calc(90vh - 180px) !important;
            min-height: unset !important;
            padding-bottom: 20px !important;
        }
        
        #modifySpecialistModal .modify-modal-buttons {
            flex-shrink: 0 !important;
            margin-top: 0 !important;
            padding: 15px 20px !important;
            border-top: 1px solid #dee2e6 !important;
            background: #f8f9fa !important;
        }
        
        /* Ensure colors work on focus too */
        #modifyScheduleModal input[type="time"].shift1-start-time:focus,
        #modifyScheduleModal input[type="time"].shift1-end-time:focus {
            background-color: #ffebee !important;
            color: #d32f2f !important;
            border-color: #d32f2f !important;
            box-shadow: 0 0 0 0.2rem rgba(211, 47, 47, 0.25) !important;
        }
        
        #modifyScheduleModal input[type="time"].shift2-start-time:focus,
        #modifyScheduleModal input[type="time"].shift2-end-time:focus {
            background-color: #e3f2fd !important;
            color: #1976d2 !important;
            border-color: #1976d2 !important;
            box-shadow: 0 0 0 0.2rem rgba(25, 118, 210, 0.25) !important;
        }
        
        #modifyScheduleModal input[type="time"].shift3-start-time:focus,
        #modifyScheduleModal input[type="time"].shift3-end-time:focus {
            background-color: #e8f5e8 !important;
            color: #2e7d32 !important;
            border-color: #2e7d32 !important;
            box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.25) !important;
        }
        
        #modifyScheduleModal .form-control {
            display: block !important;
            width: 100% !important;
            padding: 0.375rem 0.75rem !important;
            font-size: 1rem !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #495057 !important;
            background-color: #fff !important;
            background-clip: padding-box !important;
            border: 1px solid #ced4da !important;
            border-radius: 0.25rem !important;
            transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out !important;
        }
        
        #modifyScheduleModal input[type="time"].form-control {
            width: 70px !important;
            padding: 3px 6px !important;
            font-size: 12px !important;
        }
        
        #modifyScheduleModal .org-name-row {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            margin-bottom: 10px !important;
        }
        
        #modifyScheduleModal .modify-icon-inline {
            font-size: 24px !important;
        }
        
        #modifyScheduleModal .org-name-large {
            font-size: 18px !important;
            font-weight: 600 !important;
            color: #333 !important;
        }
        
        /* Disabled button styles */
        .btn:disabled,
        .btn[disabled],
        button:disabled,
        button[disabled] {
            opacity: 0.5 !important;
            cursor: not-allowed !important;
        }
        
        /* Add Specialist Modal username/password fields light blue background */
        #addSpecialistModal #specialistUser,
        #addSpecialistModal #specialistPassword,
        #addSpecialistModal #specialistUser:focus,
        #addSpecialistModal #specialistPassword:focus {
            background-color: #e3f2fd !important;
        }
        
        /* Schedule editor table time inputs */
        .schedule-editor-table input[type="time"] {
            width: 75% !important;
            padding: 2px 0px !important;
            border: 1px solid #ccc;
            border-radius: 3px !important;
            font-size: 11px !important;
            min-width: 30px !important;
        }
        
        /* Schedule editor table padding */
        .schedule-editor-table th,
        .schedule-editor-table td {
            padding: 6px 6px !important;
        }
        
        /* Add Specialist Modal - Apply shift colors to inputs */
        #addSpecialistModal .shift1-start-time,
        #addSpecialistModal .shift1-end-time {
            background-color: #ffebee !important;
            color: #d32f2f !important;
            border-color: #ffcdd2 !important;
        }
        
        #addSpecialistModal .shift2-start-time,
        #addSpecialistModal .shift2-end-time {
            background-color: #e3f2fd !important;
            color: #1976d2 !important;
            border-color: #bbdefb !important;
        }
        
        #addSpecialistModal .shift3-start-time,
        #addSpecialistModal .shift3-end-time {
            background-color: #e8f5e8 !important;
            color: #2e7d32 !important;
            border-color: #c8e6c9 !important;
        }
        
        /* Ensure colors work on focus too */
        #addSpecialistModal .shift1-start-time:focus,
        #addSpecialistModal .shift1-end-time:focus {
            background-color: #ffebee !important;
            color: #d32f2f !important;
            border-color: #d32f2f !important;
            box-shadow: 0 0 0 0.2rem rgba(211, 47, 47, 0.25) !important;
        }
        
        #addSpecialistModal .shift2-start-time:focus,
        #addSpecialistModal .shift2-end-time:focus {
            background-color: #e3f2fd !important;
            color: #1976d2 !important;
            border-color: #1976d2 !important;
            box-shadow: 0 0 0 0.2rem rgba(25, 118, 210, 0.25) !important;
        }
        
        #addSpecialistModal .shift3-start-time:focus,
        #addSpecialistModal .shift3-end-time:focus {
            background-color: #e8f5e8 !important;
            color: #2e7d32 !important;
            border-color: #2e7d32 !important;
            box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.25) !important;
        }
    </style>
</head>
<body>
    <!-- Notification Container -->
    <div id="notification-container"></div>
    
    <div class="main-container">
        <!-- Title Box -->
        <div class="title-box">
            <div class="title-left">
                <div class="current-time" id="currentTime">
                    <?= date('l, F j, Y') ?> 
                    <span class="time-badge" id="currentTimeClock"><?= date('H:i:s') ?></span>
                    <br><small style="color: #666;"><?= 
                        $supervisor_mode && $workpoint ? getTimezoneForWorkingPoint($workpoint) : 
                        (!$supervisor_mode && !empty($working_points) ? getTimezoneForWorkingPoint($working_points[0]) : 
                        getTimezoneForOrganisation($organisation)) 
                    ?></small>
                </div>
            </div>
            <div class="title-center">
                <h1 class="page-title">
                    <span class="logo-text">
                        <span class="logo-letter">B</span>
                        <span class="logo-letter beauty-eye">
                            <span class="eye-outer">o</span>
                            <span class="eye-inner">
                                <span class="iris"></span>
                            </span>
                        </span>
                        <span class="logo-letter beauty-eye">
                            <span class="eye-outer">o</span>
                            <span class="eye-inner">
                                <span class="iris"></span>
                            </span>
                        </span>
                        <span class="logo-letter">k</span>
                        <span class="logo-letter">i</span>
                        <span class="logo-letter">n</span>
                        <span class="logo-letter">g</span>
                        <span class="logo-letter space"></span>
                        <span class="logo-letter">P</span>
                        <span class="logo-letter">a</span>
                        <span class="logo-letter">g</span>
                        <span class="logo-letter">e</span>
                    </span>
                </h1>

            </div>
            <div class="title-right">
                <div class="language-selector">
                    <button class="language-btn <?= $lang === 'en' ? 'active' : '' ?>" onclick="changeLanguage('en')">EN</button>
                    <button class="language-btn <?= $lang === 'ro' ? 'active' : '' ?>" onclick="changeLanguage('ro')">RO</button>
                    <button class="language-btn <?= $lang === 'lt' ? 'active' : '' ?>" onclick="changeLanguage('lt')">LT</button>
                </div>
                <div style="margin-top: 10px;">
                    <small><?= htmlspecialchars($_SESSION['user']) ?>
                    <?php if ($supervisor_mode): ?>
                    | <a href="workpoint_supervisor_dashboard.php" style="color: var(--primary-color); text-decoration: none;">Dashboard</a>
                    <?php endif; ?>
                    | <a href="logout.php" style="color: var(--danger-color); text-decoration: none;"><?= $LANG['logout'] ?? 'Logout' ?></a>
                    | <span id="realtime-status-btn" onclick="toggleRealtimeUpdates()" style="cursor: pointer; display: inline-block; margin: 0 8px; vertical-align: middle;" title="Real-time booking updates via SSE (Server-Sent Events) - Connecting...">
                        <i class="status-icon fas fa-circle" style="font-size: 14px; color: #ffc107; transition: color 0.3s ease;"></i>
                    </span></small>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-wrapper">
            <!-- Left Sidebar -->
            <div class="sidebar">
                <?php if ($supervisor_mode): ?>
                <!-- Workpoint Details Widget (Supervisor Mode) -->
                <div class="widget specialist-widget">
                    <div class="widget-title" style="font-size: 1.1rem; color: var(--primary-color); text-align: center;">
                        <span style="margin-right: 8px; font-size: 1.3rem;">🏢</span><?= htmlspecialchars($organisation['alias_name']) ?><br>
                        (<?= htmlspecialchars($organisation['oficial_company_name']) ?>)
                    </div>
                    <div class="specialist-info">
                        <div class="specialist-name-large" style="font-size: 1rem; color: var(--dark-color); font-style: italic; margin-bottom: 15px; text-align: center;"><?= htmlspecialchars($workpoint['name_of_the_place']) ?></div>
                        <div class="contact-info">
                            <span style="margin-right: 8px;">📍</span>
                            <span><?= htmlspecialchars($workpoint['address']) ?></span>
                        </div>
                        <?php if ($workpoint['workplace_phone_nr'] || $workpoint['booking_phone_nr']): ?>
                        <div class="contact-info">
                            <?php if ($workpoint['workplace_phone_nr']): ?>
                            <span style="margin-right: 8px;">☎️</span>
                            <span><?= htmlspecialchars($workpoint['workplace_phone_nr']) ?></span>
                            <?php endif; ?>
                            <?php if ($workpoint['booking_phone_nr']): ?>
                            <span style="margin-left: 20px;">
                                <span style="margin-right: 5px;">📆</span><?= htmlspecialchars($workpoint['booking_phone_nr']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div class="contact-info">
                            <span style="margin-right: 8px;">👥</span>
                            <span><?= count($specialists) ?> Specialist(s) at this location</span>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Specialist Details Widget -->
                <div class="widget specialist-widget">
                    <div class="widget-title <?= (!$supervisor_mode && isset($specialist_permissions['back_color'])) ? 'specialist-colored' : '' ?>">
                        <i class="fas fa-user-md"></i> <?= htmlspecialchars($specialist['name']) ?>
                    </div>
                    <div class="specialist-info">
                        <div class="specialist-specialty"><?= htmlspecialchars($specialist['speciality']) ?></div>
                        <?php
                        // Get specialist visibility settings
                        $stmt = $pdo->prepare("SELECT specialist_nr_visible_to_client, specialist_email_visible_to_client FROM specialists_setting_and_attr WHERE specialist_id = ?");
                        $stmt->execute([$specialist_id]);
                        $visibility_settings = $stmt->fetch();
                        ?>
                        <div class="contact-info">
                            <i class="fas fa-phone"></i>
                            <span><?= htmlspecialchars($specialist['phone_nr']) ?></span>
                            <span class="visibility-indicator <?= ($visibility_settings['specialist_nr_visible_to_client'] ?? 0) ? 'visible' : 'hidden' ?>" 
                                  title="<?= ($visibility_settings['specialist_nr_visible_to_client'] ?? 0) ? 'Visible to clients' : 'Hidden from clients' ?>">
                                <i class="fas fa-<?= ($visibility_settings['specialist_nr_visible_to_client'] ?? 0) ? 'eye' : 'eye-slash' ?>"></i>
                            </span>
                        </div>
                        <div class="contact-info">
                            <i class="fas fa-envelope"></i>
                            <span><?= htmlspecialchars($specialist['email']) ?></span>
                            <span class="visibility-indicator <?= ($visibility_settings['specialist_email_visible_to_client'] ?? 0) ? 'visible' : 'hidden' ?>" 
                                  title="<?= ($visibility_settings['specialist_email_visible_to_client'] ?? 0) ? 'Visible to clients' : 'Hidden from clients' ?>">
                                <i class="fas fa-<?= ($visibility_settings['specialist_email_visible_to_client'] ?? 0) ? 'eye' : 'eye-slash' ?>"></i>
                            </span>
                        </div>
                        <div class="contact-info">
                            <i class="fas fa-clock"></i>
                            <span>Daily booking email: <?= isset($specialist['h_of_email_schedule']) ? $specialist['h_of_email_schedule'] : '0' ?>:<?= isset($specialist['m_of_email_schedule']) ? str_pad($specialist['m_of_email_schedule'], 2, '0', STR_PAD_LEFT) : '00' ?></span>
                        </div>
                        <!-- DEBUG: After Daily booking email -->
                        <?php echo "<!-- DEBUG: PHP is executing here -->"; ?>
                        <?php
                        // Initialize Google Calendar variables with defaults
                        $gc_conn = null;
                        $gc_status = null;
                        $gc_calendar_id = null;
                        $gc_calendar_name = null;
                        $gc_specialist_name = 'Unknown';
                        $services_options = '';
                        
                        // Prepare services options for specialist view only
                        // Google Calendar functionality is only for specialist view
                        if (!$supervisor_mode && $specialist_id) {
                            try {
                                $stmt = $pdo->prepare("SELECT unic_id, name_of_service FROM services WHERE id_specialist = ? AND (deleted IS NULL OR deleted != 1) AND (suspended IS NULL OR suspended != 1) ORDER BY name_of_service ASC");
                                $stmt->execute([$specialist_id]);
                                $service_count = 0;
                                while ($service = $stmt->fetch()) {
                                    $services_options .= '<option value="' . $service['unic_id'] . '">' . htmlspecialchars($service['name_of_service']) . '</option>';
                                    $service_count++;
                                }
                                // Debug: log how many services were found
                                error_log("Found services for specialist " . $specialist_id . ": " . $service_count);
                                // Add debug output visible in HTML
                                echo "<!-- DEBUG: Found " . $service_count . " services for specialist " . $specialist_id . " -->";
                                echo "<!-- DEBUG: services_options length: " . strlen($services_options) . " -->";
                            } catch (Exception $e) {
                                // Silently fail
                                error_log("Error loading services: " . $e->getMessage());
                                echo "<!-- DEBUG: Error loading services: " . $e->getMessage() . " -->";
                            }
                        } else {
                            echo "<!-- DEBUG: Not loading services - supervisor_mode = " . ($supervisor_mode ? 'true' : 'false') . ", specialist_id = " . ($specialist_id ?? 'null') . " -->";
                        }
                        
                        // Skip Google Calendar for supervisor mode
                        if (!$supervisor_mode && $specialist_id) {
                            // Try to load Google Calendar connection - but don't let it break the page
                            if (file_exists(__DIR__ . '/includes/google_calendar_sync.php')) {
                                @include_once __DIR__ . '/includes/google_calendar_sync.php';
                            }
                            
                            // Only try to get connection if function exists
                            if (function_exists('get_google_calendar_connection')) {
                                try {
                                    $gc_conn = @get_google_calendar_connection($pdo, (int)$specialist_id);
                                    if ($gc_conn && is_array($gc_conn)) {
                                        $gc_status = isset($gc_conn['status']) ? $gc_conn['status'] : null;
                                        $gc_calendar_id = isset($gc_conn['calendar_id']) ? $gc_conn['calendar_id'] : null;
                                        $gc_calendar_name = isset($gc_conn['calendar_name']) ? $gc_conn['calendar_name'] : null;
                                        $gc_specialist_name = isset($gc_conn['specialist_name']) ? $gc_conn['specialist_name'] : 'Unknown';
                                    }
                                } catch (Exception $e) {
                                    // Silently fail
                                }
                            }
                        }
                        ?>
                        <?php if ($gc_conn && $gc_status === 'active'): ?>
                        <div class="contact-info" style="position: relative; left: -3px; display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center;">
                                <img src="logo/png-transparent-google-calendar-logo-icon.png" style="width: 24px; height: 24px; margin-right: 8px;" 
                                     data-bs-toggle="tooltip"
                                     data-bs-html="true"
                                     data-bs-custom-class="menu-tooltip"
                                     title="Connected to: <?= htmlspecialchars($gc_specialist_name) ?><br>Calendar: <span style='color: #4285f4;'><?= htmlspecialchars($gc_calendar_name ?: $gc_calendar_id) ?></span>" />
                                <span style="color: #4285f4; cursor: help;" 
                                      data-bs-toggle="tooltip"
                                      data-bs-html="true"
                                      data-bs-custom-class="menu-tooltip"
                                      title="Connected to: <?= htmlspecialchars($gc_calendar_name ?: $gc_calendar_id) ?>">G. Calendar:</span>&nbsp;&nbsp;
                                <span class="badge bg-success" 
                                      style="cursor: pointer; font-size: 0.75rem; padding: 0.25rem 0.5rem; font-weight: normal;" 
                                      onclick="if(confirm('Are you sure you want to disconnect from Google Calendar? This will stop syncing your bookings.')) { gcalDisconnect(<?= (int)$specialist_id ?>); }" 
                                      data-bs-toggle="tooltip"
                                      data-bs-html="true"
                                      data-bs-custom-class="menu-tooltip"
                                      title="Click to disconnect from Google Calendar">Connected</span>
                            </div>
                            <div style="margin-left: auto; display: flex; gap: 8px;">
                                <a href="#" onclick="gcalImportFromGoogle(<?= (int)$specialist_id ?>); return false;" 
                                   data-bs-toggle="tooltip"
                                   data-bs-html="true"
                                   data-bs-custom-class="menu-tooltip"
                                   title="Import from Google Calendar" 
                                   style="text-decoration: none; font-size: 18px; filter: hue-rotate(150deg) saturate(2);">
                                    🔄
                                </a>
                                <a href="#" onclick="gcalSyncAll(<?= (int)$specialist_id ?>); return false;" 
                                   data-bs-toggle="tooltip"
                                   data-bs-html="true"
                                   data-bs-custom-class="menu-tooltip"
                                   title="Sync all bookings to Google Calendar" 
                                   style="text-decoration: none; font-size: 18px;">
                                    🔄
                                </a>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="contact-info google-calendar-integration" style="width: 100%; margin-bottom: 10px;">
                            <div class="gcal-status-disconnected">
                                <div class="d-flex align-items-center mb-2">
                                    <img src="logo/png-transparent-google-calendar-logo-icon.png" style="width: 24px; height: 24px; margin-right: 8px; opacity: 0.5;" />
                                    <span style="color: #4285f4; font-weight: bold;">G. Calendar:</span> <span class="badge bg-danger">NOT CONNECTED</span>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-info-circle"></i> 
                                    <a href="#" onclick="gcalOpenConnect(<?= (int)$specialist_id ?>); return false;" 
                                       class="gcal-connect-link">Connect</a> to automatically sync your bookings with Google Calendar
                                </small>
                            </div>
                        </div>
                        <?php endif; ?>
                        <!-- Success/Error messages for Google Calendar -->
                        <?php if (isset($_GET['gcal_success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
                                <i class="fas fa-check-circle"></i> Google Calendar connected successfully! Your bookings will now sync automatically.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <script>
                            // Auto-refresh the page to update Google Calendar status after successful connection
                            <?php if (isset($_GET['refresh'])): ?>
                                // Immediate refresh if refresh=1 parameter
                                setTimeout(function() {
                                    const url = new URL(window.location);
                                    url.searchParams.delete('gcal_success');
                                    url.searchParams.delete('refresh');
                                    window.location.href = url.toString();
                                }, 1000);
                            <?php else: ?>
                                // Normal delay
                                setTimeout(function() {
                                    const url = new URL(window.location);
                                    url.searchParams.delete('gcal_success');
                                    window.location.href = url.toString();
                                }, 3000);
                            <?php endif; ?>
                            </script>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['gcal_disconnected'])): ?>
                            <div class="alert alert-warning alert-dismissible fade show mt-2" role="alert">
                                <i class="fas fa-unlink"></i> Google Calendar disconnected successfully. Booking sync has been stopped.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <script>
                            setTimeout(function() {
                                const url = new URL(window.location);
                                url.searchParams.delete('gcal_disconnected');
                                window.location.href = url.toString();
                            }, 3000);
                            </script>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['gcal_synced'])): ?>
                            <div class="alert alert-info alert-dismissible fade show mt-2" role="alert">
                                <i class="fas fa-sync"></i> Full sync queued successfully. Your bookings will be synchronized to Google Calendar shortly.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <script>
                            setTimeout(function() {
                                const url = new URL(window.location);
                                url.searchParams.delete('gcal_synced');
                                window.location.href = url.toString();
                            }, 2000);
                            </script>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['gcal_error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show mt-2" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> Google Calendar connection failed: <?= htmlspecialchars($_GET['gcal_error']) ?>
                                <?php if (strpos($_GET['gcal_error'], 'expired') !== false || strpos($_GET['gcal_error'], 'invalid_grant') !== false): ?>
                                    <br><br>
                                                                         <button class="btn btn-sm btn-primary mt-2" onclick="window.location.reload(); return false;">
                                         <i class="fas fa-redo"></i> Refresh & Try Again
                                     </button>
                                <?php endif; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Organisation Details Widget -->
                <div class="widget organisation-widget">
                    <?php if ($supervisor_mode): ?>
                    <div class="organisation-name-large" style="margin-bottom: 0; padding-bottom: 3px; position: relative;">
                        <span style="margin-right: 8px;">👥</span>
                        Specialists:
                        <button type="button" 
                                style="position: absolute; right: 0; top: 50%; transform: translateY(-50%); font-size: 11px; padding: 2px 8px; background-color: white; color: #333; border: 1px solid #ddd; transition: box-shadow 0.2s ease; cursor: pointer;"
                                onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)';"
                                onmouseout="this.style.boxShadow='none';"
                                onclick="openAddSpecialistModal('<?= $workpoint['unic_id'] ?>', '<?= $organisation['unic_id'] ?>')">
                            <i class="fas fa-plus-circle"></i> Add New
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="organisation-name-large" 
                         style="cursor: pointer; margin-bottom: 0; padding-bottom: 3px;" 
                         data-bs-toggle="tooltip" 
                         data-bs-placement="top"
                         title="<?= htmlspecialchars($organisation['oficial_company_name']) ?>">
                        <i class="fas fa-building" style="color: black; margin-right: 8px;"></i>
                        <?= htmlspecialchars($organisation['alias_name']) ?>
                    </div>
                    <?php endif; ?>
                    

                    
                                        <?php if ($supervisor_mode): ?>
                                        <div class="working-points-container" style="margin-top: 0;">
                                            <?php 
                                            $spec_counter = 1;
                                            foreach ($specialists as $spec): 
                                                // Get working program for this specialist
                                                $spec_program = array_filter($working_program, function($p) use ($spec) {
                                                    return $p['specialist_id'] == $spec['unic_id'];
                                                });
                                                
                                                // Only show specialists that have programs
                                                if (!empty($spec_program)):
                                            ?>
                                                <?php 
                                                // Get specialist colors from settings
                                                $stmt = $pdo->prepare("SELECT back_color, foreground_color FROM specialists_setting_and_attr WHERE specialist_id = ?");
                                                $stmt->execute([$spec['unic_id']]);
                                                $spec_settings = $stmt->fetch();
                                                
                                                $bg_color = $spec_settings['back_color'] ?? '#667eea';
                                                $fg_color = $spec_settings['foreground_color'] ?? '#ffffff';
                                                ?>
                                                <div class="working-point-section specialist-collapsible" data-specialist-id="<?= $spec['unic_id'] ?>" 
                                                     style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 5px; margin-bottom: 10px; background-color: #fafafa; transition: all 0.2s ease;"
                                                     onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'; this.style.transform='translateY(-2px)';"
                                                     onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';">
                                                    <div class="working-point-header specialist-header" style="cursor: pointer; font-weight: normal; display: block; position: relative; min-height: 24px; padding-top: 8px; margin-bottom: 0;" onclick="(function(event) {
                                                        const specialistSection = event.currentTarget.closest('.specialist-collapsible');
                                                        const scheduleContent = specialistSection.querySelector('.schedule-content');
                                                        
                                                        // Toggle visibility
                                                        if (scheduleContent.style.display === 'none' || scheduleContent.style.display === '') {
                                                            scheduleContent.style.display = 'block';
                                                        } else {
                                                            scheduleContent.style.display = 'none';
                                                        }
                                                    })(event)">
                                                        <div style="display: flex; align-items: center; width: 100%;">
                                                            <div style="display: flex; align-items: center; flex: 1;">
                                                                <div style="width: 16px; height: 16px; border-radius: 50%; background-color: <?= $bg_color ?>; border: 1px solid #ddd; margin-right: 8px; cursor: pointer; flex-shrink: 0;" 
                                                                     onclick="event.stopPropagation(); openColorPickerModal('<?= $spec['unic_id'] ?>', '<?= htmlspecialchars($spec['name']) ?>', '<?= $bg_color ?>', '<?= $fg_color ?>')" 
                                                                     title="Change specialist colors"></div>
                                                                <?php
                                                                // Get specialist visibility settings for tooltip
                                                                $stmt = $pdo->prepare("SELECT specialist_nr_visible_to_client, specialist_email_visible_to_client FROM specialists_setting_and_attr WHERE specialist_id = ?");
                                                                $stmt->execute([$spec['unic_id']]);
                                                                $spec_visibility = $stmt->fetch();
                                                                ?>
                                                                <span style="color: #333; font-weight: 600; display: inline-block;" 
                                                                      data-bs-toggle="tooltip" 
                                                                      data-bs-placement="top" 
                                                                      data-bs-html="true"
                                                                      data-bs-trigger="hover"
                                                                      title="<strong>Speciality:</strong> <?= htmlspecialchars($spec['speciality']) ?><br><em>Click to view schedule</em>">
                                                                    <?= htmlspecialchars($spec['name']) ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div style="color: #6c757d; font-size: 0.9em; margin-top: -5px;">
                                                            <?= ucfirst(strtolower(htmlspecialchars($spec['speciality']))) ?>  •  <span style="cursor: help;" 
                                                                  data-bs-toggle="tooltip" 
                                                                  data-bs-placement="top" 
                                                                  data-bs-html="true"
                                                                  title="Phone visible to clients: <?= ($spec_visibility['specialist_nr_visible_to_client'] ?? 0) ? '<span style=\'color: green;\'>✓ On</span>' : '<span style=\'color: red;\'>✗ Off</span>' ?>">
                                                                <i class="fas fa-phone" style="font-size: 0.8em;"></i> <?= htmlspecialchars($spec['phone_nr']) ?>
                                                            </span>  •  <span style="cursor: help;" 
                                                                  data-bs-toggle="tooltip" 
                                                                  data-bs-placement="top" 
                                                                  data-bs-html="true"
                                                                  title="<strong>Email:</strong> <?= htmlspecialchars($spec['email']) ?><br>Email visible to clients: <?= ($spec_visibility['specialist_email_visible_to_client'] ?? 0) ? '<span style=\'color: green;\'>✓ On</span>' : '<span style=\'color: red;\'>✗ Off</span>' ?>">
                                                                <i class="fas fa-envelope" style="font-size: 0.8em;"></i>
                                                            </span>
                                                        </div>
                                                        <button type="button"
                                                                style="position: absolute; right: 8px; top: 50%; transform: translateY(calc(-50% - 8px)); z-index: 10; font-size: 11px; padding: 2px 8px; background-color: white; color: #333; border: 1px solid #ddd; transition: box-shadow 0.2s ease; cursor: pointer;"
                                                                onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)';"
                                                                onmouseout="this.style.boxShadow='none';"
                                                                onclick="event.stopPropagation(); openModifySpecialistModal('<?= $spec['unic_id'] ?>', '<?= htmlspecialchars($spec['name']) ?>', '<?= $workpoint['unic_id'] ?>')" 
                                                                title="Edit details">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </div>
                                                    <div class="working-point-item schedule-content" style="display: none; width: 100%; text-align: center; cursor: pointer; transition: background-color 0.3s ease;" 
                                                         onclick="event.stopPropagation(); openModifyScheduleModal('<?= $spec['unic_id'] ?>', '<?= $workpoint['unic_id'] ?>')"
                                                         onmouseover="this.style.backgroundColor='rgba(0, 123, 255, 0.05)'"
                                                         onmouseout="this.style.backgroundColor='transparent'"
                                                         title="Click to modify schedule">
                                                        <div class="working-program">
                                                            <?php
                                                            if (!empty($spec_program)) {
                                                                echo "<strong>Working Schedule:</strong><br>";
                                                                
                                                                // Define day order
                                                                $day_order = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                                                $day_names = [
                                                                    'monday' => 'Mon',
                                                                    'tuesday' => 'Tue', 
                                                                    'wednesday' => 'Wed',
                                                                    'thursday' => 'Thu',
                                                                    'friday' => 'Fri',
                                                                    'saturday' => 'Sat',
                                                                    'sunday' => 'Sun'
                                                                ];
                                                                
                                                                // Create a lookup array for working program
                                                                $program_lookup = [];
                                                                foreach ($spec_program as $program) {
                                                                    $day = strtolower($program['day_of_week']); // Convert to lowercase
                                                                    $shifts = [];
                                                                    
                                                                    // Check shift 1
                                                                    $start1 = $program['shift1_start'];
                                                                    $end1 = $program['shift1_end'];
                                                                    if ($start1 && $end1 && $start1 !== '00:00:00' && $end1 !== '00:00:00') {
                                                                        $start1_formatted = substr($start1, 0, 2) . "<sup>" . substr($start1, 3, 2) . "</sup>";
                                                                        $end1_formatted = substr($end1, 0, 2) . "<sup>" . substr($end1, 3, 2) . "</sup>";
                                                                        $shifts[] = $start1_formatted . " - " . $end1_formatted;
                                                                    }
                                                                    
                                                                    // Check shift 2
                                                                    $start2 = $program['shift2_start'];
                                                                    $end2 = $program['shift2_end'];
                                                                    if ($start2 && $end2 && $start2 !== '00:00:00' && $end2 !== '00:00:00') {
                                                                        $start2_formatted = substr($start2, 0, 2) . "<sup>" . substr($start2, 3, 2) . "</sup>";
                                                                        $end2_formatted = substr($end2, 0, 2) . "<sup>" . substr($end2, 3, 2) . "</sup>";
                                                                        $shifts[] = $start2_formatted . " - " . $end2_formatted;
                                                                    }
                                                                    
                                                                    // Check shift 3
                                                                    $start3 = $program['shift3_start'];
                                                                    $end3 = $program['shift3_end'];
                                                                    if ($start3 && $end3 && $start3 !== '00:00:00' && $end3 !== '00:00:00') {
                                                                        $start3_formatted = substr($start3, 0, 2) . "<sup>" . substr($start3, 3, 2) . "</sup>";
                                                                        $end3_formatted = substr($end3, 0, 2) . "<sup>" . substr($end3, 3, 2) . "</sup>";
                                                                        $shifts[] = $start3_formatted . " - " . $end3_formatted;
                                                                    }
                                                                    
                                                                    if (!empty($shifts)) {
                                                                        // Create colored shift spans
                                                                        $colored_shifts = [];
                                                                        foreach ($shifts as $index => $shift) {
                                                                            $shift_number = $index + 1;
                                                                            $bg_color = '';
                                                                            switch ($shift_number) {
                                                                                case 1:
                                                                                    $bg_color = 'background-color: #ffebee; color: #d32f2f; padding: 2px 6px; border-radius: 3px; margin: 0 2px; text-align: right;';
                                                                                    // Adjust margin for specific days
                                                                                    if ($day === 'monday' || $day === 'wednesday') {
                                                                                        $bg_color = 'background-color: #ffebee; color: #d32f2f; padding: 2px 6px; border-radius: 3px; margin: 0 -2px 0 2px; text-align: right;';
                                                                                    } elseif ($day === 'friday') {
                                                                                        $bg_color = 'background-color: #ffebee; color: #d32f2f; padding: 2px 6px; border-radius: 3px; margin: 0 4px; text-align: right;';
                                                                                    }
                                                                                    break;
                                                                                case 2:
                                                                                    $bg_color = 'background-color: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 3px; margin: 0 2px;';
                                                                                    break;
                                                                                case 3:
                                                                                    $bg_color = 'background-color: #e8f5e8; color: #2e7d32; padding: 2px 6px; border-radius: 3px; margin: 0 2px;';
                                                                                    break;
                                                                            }
                                                                            $colored_shifts[] = '<span style="' . $bg_color . '">' . $shift . '</span>';
                                                                        }
                                                                        // Remove separator when there are 3 shifts
                                                                        $separator = count($shifts) == 3 ? "" : "&nbsp;";
                                                                        $program_lookup[$day] = implode($separator, $colored_shifts);
                                                                    }
                                                                }
                                                                
                                                                // Display days in order, only if they have schedules
                                                                $schedule_lines = [];
                                                                foreach ($day_order as $day) {
                                                                    if (isset($program_lookup[$day])) {
                                                                        $day_name = $day_names[$day];
                                                                        $day_style = '';
                                                                        
                                                                        // Condense Mon and Wed horizontally
                                                                        if ($day_name === 'Mon' || $day_name === 'Wed') {
                                                                            $day_style = 'style="letter-spacing: -0.5px; transform: scaleX(0.9); display: inline-block;"';
                                                                        }
                                                                        
                                                                        $schedule_lines[] = '<span ' . $day_style . '>' . $day_name . ':</span> ' . $program_lookup[$day];
                                                                    }
                                                                }
                                                                echo implode("<br>", $schedule_lines);
                                                                if (!empty($schedule_lines)) {
                                                                    echo "<br>";
                                                                }
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php 
                                                $spec_counter++;
                                                endif;
                                            endforeach; 
                                            ?>
                                            
                                            <!-- Unassigned Specialists Dropdown (visible if organisation has unassigned specialists) -->
                                            <?php
                                            // Fetch organisation-wide unassigned specialists (no working_program rows)
                                            $org_unassigned = [];
                                            if (!empty($organisation['unic_id'])) {
                                                $stmt = $pdo->prepare("SELECT s.unic_id, s.name, s.speciality FROM specialists s WHERE s.organisation_id = ? AND NOT EXISTS (SELECT 1 FROM working_program wp WHERE wp.specialist_id = s.unic_id AND ((wp.shift1_start <> '00:00:00' AND wp.shift1_end <> '00:00:00') OR (wp.shift2_start <> '00:00:00' AND wp.shift2_end <> '00:00:00') OR (wp.shift3_start <> '00:00:00' AND wp.shift3_end <> '00:00:00'))) ORDER BY s.name");
                                                $stmt->execute([$organisation['unic_id']]);
                                                $org_unassigned = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            }
                                            if (!empty($org_unassigned)):
                                            ?>
                                            <div style="margin: 16px 0; padding-top: 12px; border-top: 1px dashed #ddd;">
                                                <details>
                                                    <summary style="cursor:pointer;font-weight:600;color:#444;font-size:0.9rem;font-style:italic;">
                                                        <i class="fas fa-user-slash"></i> Specialists Withouth Program (<?= count($org_unassigned) ?>)
                                                    </summary>
                                                    <div style="margin-top:8px; font-size: 0.9rem; font-style: italic;">
                                                        <select id="unassignedSpecialistsSelect" class="form-select form-select-sm" onchange="onSelectUnassignedSpecialist(this.value)" style="max-width:100%; font-size: 0.9rem; font-style: italic;">
                                                            <option value="">Select a specialist…</option>
                                                            <?php foreach ($org_unassigned as $us): ?>
                                                                <option value="<?= (int)$us['unic_id'] ?>"><?= htmlspecialchars($us['name']) ?> — <?= htmlspecialchars($us['speciality']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </details>
                                            </div>
                                            <?php endif; ?>

                                            
                                            <!-- Extra Tools Section -->
                                            <div style="margin-top: 20px; text-align: center; padding: 15px; border-top: 2px solid #e0e0e0;">
                                                <h5 style="font-size: 1rem; margin-bottom: 15px; color: #1e3a8a;">Extra Tools</h5>
                                                <div style="display: flex; justify-content: space-around; align-items: center; gap: 10px;">
                                                    <button type="button" class="btn btn-sm" 
                                                            style="background-color: white; color: #1e3a8a; border: 1px solid #1e3a8a; padding: 8px; flex: 1; transition: all 0.3s ease;"
                                                            onmouseover="this.style.backgroundColor='#1e3a8a'; this.style.color='white';"
                                                            onmouseout="this.style.backgroundColor='white'; this.style.color='#1e3a8a';"
                                                            onclick="showSearchPanel()"
                                                            title="Search Bookings">
                                                        <i class="fas fa-search"></i>
                                                        <div style="font-size: 0.7rem; margin-top: 4px;">Search</div>
                                                    </button>
                                                    <button type="button" class="btn btn-sm" 
                                                            style="background-color: white; color: #1e3a8a; border: 1px solid #1e3a8a; padding: 8px; flex: 1; transition: all 0.3s ease;"
                                                            onmouseover="this.style.backgroundColor='#1e3a8a'; this.style.color='white';"
                                                            onmouseout="this.style.backgroundColor='white'; this.style.color='#1e3a8a';"
                                                            onclick="showArrivalsPanel()"
                                                            title="View Booking Arrivals">
                                                        <i class="fas fa-calendar-check"></i>
                                                        <div style="font-size: 0.7rem; margin-top: 4px;">Arrivals</div>
                                                    </button>
                                                    <button type="button" class="btn btn-sm" 
                                                            style="background-color: white; color: #1e3a8a; border: 1px solid #1e3a8a; padding: 8px; flex: 1; transition: all 0.3s ease;"
                                                            onmouseover="this.style.backgroundColor='#1e3a8a'; this.style.color='white';"
                                                            onmouseout="this.style.backgroundColor='white'; this.style.color='#1e3a8a';"
                                                            onclick="showCanceledPanel()"
                                                            title="View Canceled Bookings">
                                                        <i class="fas fa-ban"></i>
                                                        <div style="font-size: 0.7rem; margin-top: 4px;">Canceled</div>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php else: ?>
                                        <div class="working-points-container" style="margin-top: 0;">
                                            <?php 
                                            $wp_counter = 1;
                                            foreach ($working_points as $wp): 
                                                // Check if this working point has a program
                                                $wp_program = array_filter($working_program, function($p) use ($wp) {
                                                    return $p['working_place_id'] == $wp['unic_id'];
                                                });
                                                
                                                // Only show working points that have programs
                                                if (!empty($wp_program)):
                                            ?>
                                                <div class="working-point-section" style="padding-left: 0;">
                                                    <!-- Working Point Dropdown -->
                                                    <div class="working-point-dropdown-section">
                                                        <div class="working-point-header" onclick="toggleWorkingPoint<?= $wp['unic_id'] ?>()" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; cursor: pointer; padding: 0; padding-top: 8px;">
                                                            <h5 style="margin: 0; font-size: 14px; color: #495057; font-weight: 600;">
                                                                <i class="fas fa-map-marker-alt"></i> Point: <?= htmlspecialchars($wp['name_of_the_place']) ?>
                                                            </h5>
                                                            <button type="button" class="btn btn-sm" 
                                                                    id="workingPointToggle<?= $wp['unic_id'] ?>"
                                                                    style="font-size: 11px; padding: 2px 8px; background-color: white; color: #333; border: 1px solid #ddd; transition: all 0.2s ease; cursor: pointer;"
                                                                    onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)'; this.style.transform='translateY(-1px)';"
                                                                    onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';"
                                                                    onclick="event.stopPropagation();">
                                                                <i class="fas fa-info-circle" style="font-size: 10px;"></i> Info
                                                            </button>
                                                        </div>
                                                        <div class="working-point-item" id="workingPointDetails<?= $wp['unic_id'] ?>" style="width: 100%; text-align: left; display: none; margin-top: 10px;">
                                                            <div class="working-program">
                                                                <?php if (!empty($wp['address']) || !empty($wp['workplace_city']) || !empty($wp['country']) || !empty($wp['language'])): ?>
                                                                    <div style="margin-bottom: 5px;">
                                                                        <i class="fas fa-map-marker-alt" style="color: #dc3545; margin-right: 8px;"></i>
                                                                        <?php 
                                                                        $parts = [];
                                                                        if (!empty($wp['address'])) $parts[] = htmlspecialchars($wp['address']);
                                                                        if (!empty($wp['workplace_city'])) $parts[] = htmlspecialchars($wp['workplace_city']);
                                                                        echo implode(', ', $parts);
                                                                        ?>
                                                                        <?php if (!empty($wp['country'])): ?>
                                                                            <span data-bs-toggle="tooltip" 
                                                                                  data-bs-placement="top" 
                                                                                  title="<?= htmlspecialchars($wp['country_long_name'] ?? $wp['country']) ?><?= !empty($wp['workplace_timezone']) ? ' (GMT ' . htmlspecialchars($wp['workplace_timezone']) . ')' : '' ?>" 
                                                                                  style="cursor: help; text-transform: uppercase;">
                                                                                <?= !empty($parts) ? ', ' : '' ?><?= htmlspecialchars(strtoupper($wp['country'])) ?>
                                                                            </span>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($wp['language'])): ?>
                                                                            <span data-bs-toggle="tooltip" 
                                                                                  data-bs-placement="top" 
                                                                                  title="Default language" 
                                                                                  style="cursor: help; text-transform: lowercase;">
                                                                                , <?= htmlspecialchars(strtolower($wp['language'])) ?>
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($wp['workplace_email'])): ?>
                                                                    <div style="margin-bottom: 5px;">
                                                                        <i class="fas fa-envelope" style="color: #28a745; margin-right: 8px;"></i>
                                                                        <?= htmlspecialchars($wp['workplace_email']) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($wp['lead_person_name'])): ?>
                                                                    <div style="margin-bottom: 5px;">
                                                                        <i class="fas fa-user-tie" style="color: #6610f2; margin-right: 8px;"></i>
                                                                        <span data-bs-toggle="tooltip" 
                                                                              data-bs-placement="top" 
                                                                              title="Lead Person (Workpoint supervisor)" 
                                                                              style="cursor: help;">
                                                                            <?= htmlspecialchars($wp['lead_person_name']) ?>
                                                                        </span>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($wp['lead_person_phone_nr'])): ?>
                                                                    <div style="margin-bottom: 5px;">
                                                                        <i class="fas fa-phone-square" style="color: #e83e8c; margin-right: 8px;"></i>
                                                                        Lead: <?= htmlspecialchars($wp['lead_person_phone_nr']) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($wp['workplace_phone_nr'])): ?>
                                                                    <div style="margin-bottom: 5px;">
                                                                        <i class="fas fa-phone" style="color: #fd7e14; margin-right: 8px;"></i>
                                                                        Office: <?= htmlspecialchars($wp['workplace_phone_nr']) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($wp['booking_phone_nr'])): ?>
                                                                    <div style="margin-bottom: 5px;">
                                                                        <i class="fas fa-phone-volume" style="color: #20c997; margin-right: 8px;"></i>
                                                                        Booking: <?= htmlspecialchars($wp['booking_phone_nr']) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Working Schedule Section -->
                                                    <div class="working-schedule-section" style="margin-top: 15px;">
                                                        <div class="working-schedule-header" onclick="toggleWorkingSchedule<?= $wp['unic_id'] ?>()" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; cursor: pointer;">
                                                            <h5 style="margin: 0; font-size: 14px; color: #495057; font-weight: 600;">
                                                                <i class="fas fa-clock"></i> Working Schedule
                                                            </h5>
                                                            <button type="button" class="btn btn-sm" 
                                                                    id="workingScheduleToggle<?= $wp['unic_id'] ?>"
                                                                    style="font-size: 11px; padding: 2px 8px; background-color: white; color: #333; border: 1px solid #ddd; transition: all 0.2s ease; cursor: pointer;"
                                                                    onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)'; this.style.transform='translateY(-1px)';"
                                                                    onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';"
                                                                    onclick="event.stopPropagation();">
                                                                <i class="fas fa-list" style="font-size: 10px;"></i> List
                                                            </button>
                                                        </div>
                                                        <div class="working-point-item" id="workingScheduleContent<?= $wp['unic_id'] ?>" style="width: 100%; text-align: center; display: none; margin-top: 10px;">
                                                            <div class="working-program">
                                                                <?php
                                                                if (!empty($wp_program)) {
                                                                
                                                                // Define day order
                                                                $day_order = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                                                $day_names = [
                                                                    'monday' => 'Mon',
                                                                    'tuesday' => 'Tue', 
                                                                    'wednesday' => 'Wed',
                                                                    'thursday' => 'Thu',
                                                                    'friday' => 'Fri',
                                                                    'saturday' => 'Sat',
                                                                    'sunday' => 'Sun'
                                                                ];
                                                                
                                                                // Create a lookup array for working program
                                                                $program_lookup = [];
                                                                foreach ($wp_program as $program) {
                                                                    $day = strtolower($program['day_of_week']); // Convert to lowercase
                                                                    $shifts = [];
                                                                    
                                                                    // Check shift 1
                                                                    $start1 = $program['shift1_start'];
                                                                    $end1 = $program['shift1_end'];
                                                                    if ($start1 && $end1 && $start1 !== '00:00:00' && $end1 !== '00:00:00') {
                                                                        $start1_formatted = substr($start1, 0, 2) . "<sup>" . substr($start1, 3, 2) . "</sup>";
                                                                        $end1_formatted = substr($end1, 0, 2) . "<sup>" . substr($end1, 3, 2) . "</sup>";
                                                                        $shifts[] = $start1_formatted . " - " . $end1_formatted;
                                                    }
                                                                    
                                                                    // Check shift 2
                                                                    $start2 = $program['shift2_start'];
                                                                    $end2 = $program['shift2_end'];
                                                                    if ($start2 && $end2 && $start2 !== '00:00:00' && $end2 !== '00:00:00') {
                                                                        $start2_formatted = substr($start2, 0, 2) . "<sup>" . substr($start2, 3, 2) . "</sup>";
                                                                        $end2_formatted = substr($end2, 0, 2) . "<sup>" . substr($end2, 3, 2) . "</sup>";
                                                                        $shifts[] = $start2_formatted . " - " . $end2_formatted;
                                                                    }
                                                                    
                                                                    // Check shift 3
                                                                    $start3 = $program['shift3_start'];
                                                                    $end3 = $program['shift3_end'];
                                                                    if ($start3 && $end3 && $start3 !== '00:00:00' && $end3 !== '00:00:00') {
                                                                        $start3_formatted = substr($start3, 0, 2) . "<sup>" . substr($start3, 3, 2) . "</sup>";
                                                                        $end3_formatted = substr($end3, 0, 2) . "<sup>" . substr($end3, 3, 2) . "</sup>";
                                                                        $shifts[] = $start3_formatted . " - " . $end3_formatted;
                                                                    }
                                                                    
                                                                    if (!empty($shifts)) {
                                                                        // Create colored shift spans
                                                                        $colored_shifts = [];
                                                                        foreach ($shifts as $index => $shift) {
                                                                            $shift_number = $index + 1;
                                                                            $bg_color = '';
                                                                            switch ($shift_number) {
                                                                                case 1:
                                                                                    $bg_color = 'background-color: #ffebee; color: #d32f2f; padding: 2px 6px; border-radius: 3px; margin: 0 2px; text-align: right;';
                                                                                    // Adjust margin for specific days
                                                                                    if ($day === 'monday' || $day === 'wednesday') {
                                                                                        $bg_color = 'background-color: #ffebee; color: #d32f2f; padding: 2px 6px; border-radius: 3px; margin: 0 -2px 0 2px; text-align: right;';
                                                                                    } elseif ($day === 'friday') {
                                                                                        $bg_color = 'background-color: #ffebee; color: #d32f2f; padding: 2px 6px; border-radius: 3px; margin: 0 4px; text-align: right;';
                                                                                    }
                                                                                    break;
                                                                                case 2:
                                                                                    $bg_color = 'background-color: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 3px; margin: 0 2px;';
                                                                                    break;
                                                                                case 3:
                                                                                    $bg_color = 'background-color: #e8f5e8; color: #2e7d32; padding: 2px 6px; border-radius: 3px; margin: 0 2px;';
                                                                                    break;
                                                                            }
                                                                            $colored_shifts[] = '<span style="' . $bg_color . '">' . $shift . '</span>';
                                                                        }
                                                                        // Remove separator when there are 3 shifts
                                                                        $separator = count($shifts) == 3 ? "" : "&nbsp;";
                                                                        $program_lookup[$day] = implode($separator, $colored_shifts);
                                                                    }
                                                                }
                                                                
                                                                // Display days in order, only if they have schedules
                                                                $schedule_lines = [];
                                                                foreach ($day_order as $day) {
                                                                    if (isset($program_lookup[$day])) {
                                                                        $day_name = $day_names[$day];
                                                                        $day_style = '';
                                                                        
                                                                        // Condense Mon and Wed horizontally
                                                                        if ($day_name === 'Mon' || $day_name === 'Wed') {
                                                                            $day_style = 'style="letter-spacing: -0.5px; transform: scaleX(0.9); display: inline-block;"';
                                                                        }
                                                                        
                                                                        $schedule_lines[] = '<span ' . $day_style . '>' . $day_name . ':</span> ' . $program_lookup[$day];
                                                                    }
                                                                }
                                                                echo implode("<br>", $schedule_lines);
                                                                if (!empty($schedule_lines)) {
                                                                    echo "<br>";
                                                                }
                                                            }
                                                            ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if ($wp_counter < count(array_filter($working_points, function($wp) use ($working_program) {
                                                    $wp_program = array_filter($working_program, function($p) use ($wp) {
                                                        return $p['working_place_id'] == $wp['unic_id'];
                                                    });
                                                    return !empty($wp_program);
                                                }))): ?>
                                                    <div style='border-top: 1px solid #e0e0e0; margin: 15px 0;'></div>
                                                <?php endif; ?>
                                            <?php 
                                                $wp_counter++;
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Services Section -->
                                        <?php if (!$supervisor_mode && $specialist_id): ?>
                                        <div class="specialist-services-section" style="margin-top: 0; padding-top: 5px; border-top: 1px solid #e9ecef;">
                                            <div class="services-list" id="specialistServicesList">
                                                <?php
                                                // Query services for this specialist only with booking counts
                                                try {
                                                    $stmt = $pdo->prepare("
                                                        SELECT 
                                                            s.unic_id, 
                                                            s.name_of_service, 
                                                            s.duration, 
                                                            s.price_of_service, 
                                                            s.procent_vat, 
                                                            s.id_specialist, 
                                                            s.deleted,
                                                            s.suspended,
                                                            COALESCE(SUM(CASE WHEN b.booking_start_datetime < NOW() AND b.booking_start_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) as past_bookings,
                                                            COALESCE(SUM(CASE WHEN b.booking_start_datetime >= NOW() THEN 1 ELSE 0 END), 0) as active_bookings
                                                        FROM services s
                                                        LEFT JOIN booking b ON s.unic_id = b.service_id
                                                        WHERE s.id_specialist = ? AND (s.deleted IS NULL OR s.deleted != 1)
                                                        GROUP BY s.unic_id, s.name_of_service, s.duration, s.price_of_service, s.procent_vat, s.id_specialist, s.deleted, s.suspended
                                                        ORDER BY s.name_of_service ASC
                                                    ");
                                                    $stmt->execute([$specialist['unic_id']]);
                                                    $services = $stmt->fetchAll();
                                                } catch (Exception $e) {
                                                    // If error, fallback to simple query without booking counts
                                                    $stmt = $pdo->prepare("
                                                        SELECT unic_id, name_of_service, duration, price_of_service, procent_vat, id_specialist, deleted 
                                                        FROM services 
                                                        WHERE id_specialist = ? AND (deleted IS NULL OR deleted != 1) 
                                                        ORDER BY name_of_service ASC
                                                    ");
                                                    $stmt->execute([$specialist['unic_id']]);
                                                    $services = $stmt->fetchAll();
                                                    // Add default booking counts
                                                    foreach ($services as &$service) {
                                                        $service['past_bookings'] = 0;
                                                        $service['active_bookings'] = 0;
                                                    }
                                                }
                                                
                                                if (count($services) > 0):
                                                ?>
                                                <div class="services-header" onclick="toggleServicesPerformed()" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; cursor: pointer;">
                                                    <h5 style="margin: 0; font-size: 14px; color: #495057; font-weight: 600;">
                                                        <i class="fas fa-clipboard-list"></i> Services Performed
                                                    </h5>
                                                    <button type="button" class="btn btn-sm" 
                                                            onclick="event.stopPropagation(); openAddServiceModalForSpecialist(<?= $specialist['unic_id'] ?>)" 
                                                            style="font-size: 11px; padding: 2px 8px; background-color: white; color: #333; border: 1px solid #ddd; transition: all 0.2s ease; cursor: pointer;"
                                                            onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)'; this.style.transform='translateY(-1px)';"
                                                            onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';">
                                                        <i class="fas fa-plus" style="font-size: 10px;"></i> Add
                                                    </button>
                                                </div>
                                                <div class="working-point-item" id="servicesPerformedContent" style="width: 100%; display: block; margin-top: 10px;">
                                                    <div class="working-program" style="max-height: 300px; overflow-y: auto;">
                                                        <style>
                                                        .service-item:hover {
                                                            background-color: #e9ecef !important;
                                                        }
                                                        .service-clickable {
                                                            cursor: pointer;
                                                        }
                                                        </style>
                                                        <?php foreach ($services as $service): ?>
                                                    <div class="service-item" style="padding: 5px 8px; margin-bottom: 2px; background: #f8f9fa; border-radius: 4px; font-size: 13px; transition: background-color 0.2s;">
                                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                                            <div style="flex: 1;" 
                                                                 class="service-clickable"
                                                                 onclick="editSpecialistService(<?= $service['unic_id'] ?>, '<?= htmlspecialchars($service['name_of_service'], ENT_QUOTES) ?>', <?= $service['duration'] ?>, <?= $service['price_of_service'] ?>, <?= $service['procent_vat'] ?>)"
                                                                 data-bs-toggle="tooltip"
                                                                 data-bs-placement="top"
                                                                 title="<?= ($service['suspended'] == 1) ? 'Service Suspended (click to activate)' : 'Click to modify' ?>">
                                                                <?php 
                                                                $serviceColor = $specialist_permissions['back_color'] ?? '#495057';
                                                                if ($service['suspended'] == 1) {
                                                                    // Convert to RGBA with low opacity for suspended services
                                                                    if (preg_match('/^#([0-9A-F]{2})([0-9A-F]{2})([0-9A-F]{2})$/i', $serviceColor, $matches)) {
                                                                        $r = hexdec($matches[1]);
                                                                        $g = hexdec($matches[2]);
                                                                        $b = hexdec($matches[3]);
                                                                        $serviceColor = "rgba($r, $g, $b, 0.3)";
                                                                    }
                                                                }
                                                                ?>
                                                                <span style="color: <?= $serviceColor ?>;"><?= htmlspecialchars($service['name_of_service']) ?></span>
                                                                <div style="font-size: 11px; color: #6c757d; line-height: 1.2;">
                                                                    <?php 
                                                                    $price_with_vat = $service['price_of_service'] * (1 + $service['procent_vat'] / 100);
                                                                    ?>
                                                                    <?= $service['duration'] ?> min | <?= number_format($price_with_vat, 2) ?>€ 
                                                                    <?php if ($service['procent_vat'] > 0): ?>
                                                                    <span style="font-size: 10px;">(incl. <?= $service['procent_vat'] ?>% VAT)</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                                <span style="display: flex; align-items: center; gap: 5px;">
                                                                    <span data-bs-toggle="tooltip" 
                                                                          data-bs-placement="top" 
                                                                          title="Past bookings (last 30 days)"
                                                                          style="border: 1px solid #868e96; color: #868e96; padding: 1px 5px; border-radius: 4px; font-size: 11px; display: inline-block; min-width: 20px; text-align: center;">
                                                                        <?= $service['past_bookings'] ?>
                                                                    </span>
                                                                    <span data-bs-toggle="tooltip" 
                                                                          data-bs-placement="top" 
                                                                          title="Active/Future bookings"
                                                                          style="border: 1px solid <?= $service['active_bookings'] > 0 ? ($specialist_permissions['back_color'] ?? '#28a745') : '#868e96' ?>; color: <?= $service['active_bookings'] > 0 ? ($specialist_permissions['back_color'] ?? '#28a745') : '#868e96' ?>; padding: 1px 5px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block; min-width: 20px; text-align: center;">
                                                                        <?= $service['active_bookings'] ?>
                                                                    </span>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <?php else: ?>
                                                <div class="services-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                                                    <h5 style="margin: 0; font-size: 14px; color: #6c757d; font-weight: 600;">
                                                        <i class="fas fa-info-circle"></i> No services configured
                                                    </h5>
                                                    <button type="button" class="btn btn-sm" 
                                                            onclick="openAddServiceModalForSpecialist(<?= $specialist['unic_id'] ?>)" 
                                                            style="font-size: 11px; padding: 2px 8px; background-color: white; color: #333; border: 1px solid #ddd; transition: all 0.2s ease; cursor: pointer;"
                                                            onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)'; this.style.transform='translateY(-1px)';"
                                                            onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';">
                                                        <i class="fas fa-plus" style="font-size: 10px;"></i> Add
                                                    </button>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Extra Tools Section for Specialist Mode -->
                                        <?php if (!$supervisor_mode): ?>
                                        <div style="margin-top: 20px; text-align: center; padding: 15px; border-top: 2px solid #e0e0e0;">
                                            <h5 style="font-size: 1rem; margin-bottom: 15px; color: #1e3a8a;">Extra Tools</h5>
                                            <div style="display: flex; justify-content: space-around; align-items: center; gap: 10px;">
                                                <button type="button" class="btn btn-sm" 
                                                        style="background-color: white; color: #1e3a8a; border: 1px solid #1e3a8a; padding: 8px; flex: 1; transition: all 0.3s ease;"
                                                        onmouseover="this.style.backgroundColor='#1e3a8a'; this.style.color='white';"
                                                        onmouseout="this.style.backgroundColor='white'; this.style.color='#1e3a8a';"
                                                        onclick="showSearchPanel()"
                                                        title="Search Bookings">
                                                    <i class="fas fa-search"></i>
                                                    <div style="font-size: 0.7rem; margin-top: 4px;">Search</div>
                                                </button>
                                                <button type="button" class="btn btn-sm" 
                                                        style="background-color: white; color: #1e3a8a; border: 1px solid #1e3a8a; padding: 8px; flex: 1; transition: all 0.3s ease;"
                                                        onmouseover="this.style.backgroundColor='#1e3a8a'; this.style.color='white';"
                                                        onmouseout="this.style.backgroundColor='white'; this.style.color='#1e3a8a';"
                                                        onclick="showArrivalsPanel()"
                                                        title="View Booking Arrivals">
                                                    <i class="fas fa-calendar-check"></i>
                                                    <div style="font-size: 0.7rem; margin-top: 4px;">Arrivals</div>
                                                </button>
                                                <button type="button" class="btn btn-sm" 
                                                        style="background-color: white; color: #1e3a8a; border: 1px solid #1e3a8a; padding: 8px; flex: 1; transition: all 0.3s ease;"
                                                        onmouseover="this.style.backgroundColor='#1e3a8a'; this.style.color='white';"
                                                        onmouseout="this.style.backgroundColor='white'; this.style.color='#1e3a8a';"
                                                        onclick="showCanceledPanel()"
                                                        title="View Canceled Bookings">
                                                    <i class="fas fa-ban"></i>
                                                    <div style="font-size: 0.7rem; margin-top: 4px;">Canceled</div>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                </div>
            </div>

            <!-- Calendar Section -->
            <div class="calendar-section">
                <!-- Calendar Top Bar -->
                <div class="calendar-top">
                                            <div class="period-selector">
                            <form method="GET" id="periodForm">
                                <?php if ($supervisor_mode): ?>
                                <input type="hidden" name="working_point_user_id" value="<?= $working_point_user_id ?>">
                                <input type="hidden" name="supervisor_mode" value="true">
                                <?php else: ?>
                                <input type="hidden" name="specialist_id" value="<?= $specialist_id ?>">
                                <?php endif; ?>
                                
                                <div class="period-option">
                                    <input type="radio" name="period" value="today" id="today" <?= $period === 'today' ? 'checked' : '' ?> onclick="changePeriod('today')">
                                    <label for="today" onclick="changePeriod('today')">Today</label>
                                </div>
                                
                                <div class="period-option">
                                    <input type="radio" name="period" value="tomorrow" id="tomorrow" <?= $period === 'tomorrow' ? 'checked' : '' ?> onclick="changePeriod('tomorrow')">
                                    <label for="tomorrow" onclick="changePeriod('tomorrow')">Tomorrow</label>
                                </div>
                                
                                <div class="period-option">
                                    <input type="radio" name="period" value="this_week" id="this_week" <?= $period === 'this_week' ? 'checked' : '' ?> onclick="changePeriod('this_week')">
                                    <label for="this_week" onclick="changePeriod('this_week')">This Week</label>
                                </div>
                                
                                <div class="period-option">
                                    <input type="radio" name="period" value="next_week" id="next_week" <?= $period === 'next_week' ? 'checked' : '' ?> onclick="changePeriod('next_week')">
                                    <label for="next_week" onclick="changePeriod('next_week')">Next Week</label>
                                </div>
                                
                                <div class="period-option">
                                    <input type="radio" name="period" value="this_month" id="this_month" <?= $period === 'this_month' ? 'checked' : '' ?> onclick="changePeriod('this_month')">
                                    <label for="this_month" onclick="changePeriod('this_month')">This Month</label>
                                </div>
                                
                                <div class="period-option">
                                    <input type="radio" name="period" value="next_month" id="next_month" <?= $period === 'next_month' ? 'checked' : '' ?> onclick="changePeriod('next_month')">
                                    <label for="next_month" onclick="changePeriod('next_month')">Next Month</label>
                                </div>
                            </form>
                        </div>
                </div>

                <!-- Calendar View -->
                <div class="calendar-view<?= ($supervisor_mode && $calendar_design === 'monthly') ? ' compact' : '' ?>">
                    <?php
                    // Debug: Show which template is being loaded
                    echo "<!-- Loading template: " . $calendar_design . " -->";
                    
                    // Load the appropriate calendar template based on design
                    switch ($calendar_design) {
                        case 'daily':
                            include 'templates/calendar_daily.php';
                            break;
                        case 'weekly':
                            include 'templates/calendar_weekly.php';
                            break;
                        case 'monthly':
                            include 'templates/calendar_monthly.php';
                            break;
                        case 'monthly':
                            include 'templates/calendar_monthly.php';
                            break;
                        default:
                            include 'templates/calendar_daily.php';
                    }
                    ?>
                </div>
            </div>
            
            <!-- Left Side Panel for Search/Arrivals/Canceled Results -->
            <div id="rightSidePanel" class="left-side-panel" style="position: fixed; left: -472px; top: 0; width: 472px; height: 100%; background: white; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 1050; overflow-y: auto; transition: left 0.3s ease;">
                <div class="panel-header" style="padding: 20px; border-bottom: 1px solid #dee2e6; background: #f8f9fa; position: sticky; top: 0; z-index: 10;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h4 id="panelTitle" style="margin: 0; color: #1e3a8a;">Panel Title</h4>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <!-- Quick navigation buttons -->
                            <div id="quickNavButtons" style="display: flex; gap: 5px; margin-right: 10px;">
                                <!-- Buttons will be added dynamically -->
                            </div>
                            <button type="button" class="btn-close" onclick="closeRightPanel()" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
                <div id="panelContent" class="panel-content" style="padding: 20px;">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Include Templates -->
    <?php 
    // Get the workpoint_id for the modal
    if ($supervisor_mode && $working_point_user_id) {
        // In supervisor mode, use the working_point_user_id as workpoint_id
        $workpoint_id = $working_point_user_id;
    } else {
        // In specialist mode, use the first workpoint for this specialist
        $workpoint_id = $working_points[0]['unic_id'] ?? 1;
    }
    ?>
    
    <!-- Error Message Display -->
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert" style="margin: 20px; text-align: center;">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($_SESSION['error_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <?php include 'templates/navbar.php'; ?>
    <?php 
    // Get excluded channels for the current workpoint
    $excluded_channels_str = 'SMS'; // Default
    if (isset($workpoint_id)) {
        $stmt = $pdo->prepare("
            SELECT excluded_channels 
            FROM workingpoint_settings_and_attr 
            WHERE working_point_id = ? 
            AND setting_key = 'sms_creation_template'
            LIMIT 1
        ");
        $stmt->execute([$workpoint_id]);
        $result = $stmt->fetch();
        if ($result && isset($result['excluded_channels'])) {
            $excluded_channels_str = $result['excluded_channels'];
        }
    }
    $excluded_channels_array = array_map('trim', explode(',', $excluded_channels_str));
    $is_web_excluded = in_array('WEB', $excluded_channels_array);
    
    // Pass supervisor mode and specialist permissions to the modal template
    $modal_supervisor_mode = $supervisor_mode;
    $modal_specialist_permissions = $specialist_permissions ?? null;
    $has_multiple_workpoints = $has_multiple_workpoints ?? false;
    $modal_excluded_channels = $excluded_channels_array;
    $modal_is_web_excluded = $is_web_excluded;
    include 'templates/modal_booking_actions.php'; 
    ?>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/specialists_cards_bootstrap.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/realtime-bookings.js?v=<?php echo time(); ?>"></script>
    <script>
    // Ensure Google Calendar functions are always available
    function postJSON(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: new URLSearchParams(data),
            credentials: 'same-origin' // Include cookies for session
        }).then(r => {
            // First check if response is ok
            if (!r.ok) {
                throw new Error(`HTTP error! status: ${r.status}`);
            }
            // Try to get text first to debug
            return r.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    // console.error('Response text:', text);
                    // console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response: ' + text.substring(0, 200));
                }
            });
        });
    }

    window.gcalOpenConnect = function(specialistId) {
        // Show loading indicator
        const btn = event ? event.target : null;
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
        }
        
        // Add cache busting to ensure fresh request
        postJSON('admin/gcal_oauth_start.php?t=' + Date.now(), { specialist_id: specialistId }).then(resp => {
            if (resp.success && resp.oauth_url) {
                // Redirect to Google OAuth
                window.location.href = resp.oauth_url;
            } else if (resp.success) {
                alert('Connection initiated. Please complete the OAuth flow.');
                location.reload();
            } else {
                alert('Error: ' + (resp.message || 'Failed to start OAuth flow'));
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-plug"></i> Connect to Google Calendar';
                    btn.disabled = false;
                }
            }
        }).catch(err => {
            alert('Network error: ' + err.message);
            if (btn) {
                btn.innerHTML = '<i class="fas fa-plug"></i> Connect to Google Calendar';
                btn.disabled = false;
            }
        });
    };

    window.gcalDisconnect = function(specialistId) {
        if (!confirm('Are you sure you want to disconnect Google Calendar for this specialist? This will stop syncing all future bookings.')) return;
        
        postJSON('admin/gcal_disconnect.php', { specialist_id: specialistId }).then(resp => {
            if (resp.success) {
                // Redirect to show success message without popup
                window.location.href = window.location.pathname + '?specialist_id=' + specialistId + '&gcal_disconnected=1';
            } else {
                alert('Error: ' + (resp.message || 'Failed to disconnect'));
            }
        }).catch(err => {
            alert('Network error: ' + err.message);
        });
    };

    window.gcalSyncAll = function(specialistId) {
        if (!confirm('This will sync all existing and future bookings to Google Calendar immediately. Continue?')) return;
        
        console.log('Starting Google Calendar sync for specialist:', specialistId);
        
        // Show loading message
        const loadingMessage = document.createElement('div');
        loadingMessage.id = 'gcal-loading-message';
        loadingMessage.innerHTML = `
            <div class="alert alert-info" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                <i class="fas fa-spinner fa-spin"></i> Synchronizing bookings to Google Calendar...
            </div>
        `;
        document.body.appendChild(loadingMessage);
        
        postJSON('admin/gcal_sync_all.php', { specialist_id: specialistId }).then(resp => {
            console.log('Sync response received:', resp);
            
            // Remove loading message
            const loadingEl = document.getElementById('gcal-loading-message');
            if (loadingEl && loadingEl.parentNode) {
                loadingEl.parentNode.removeChild(loadingEl);
            }
            
            if (resp && resp.success !== undefined) {
                if (resp.success) {
                    console.log('Showing sync results');
                    showSyncResults(resp);
                } else {
                    console.log('Showing sync error:', resp.message);
                    showSyncError(resp.message || 'Failed to sync');
                }
            } else {
                console.error('Invalid response format:', resp);
                showSyncError('Invalid response from server');
            }
        }).catch(err => {
            console.error('Sync request failed:', err);
            
            // Remove loading message
            const loadingEl = document.getElementById('gcal-loading-message');
            if (loadingEl && loadingEl.parentNode) {
                loadingEl.parentNode.removeChild(loadingEl);
            }
            showSyncError('Network error: ' + err.message);
        });
    };

    // Confirm Google Calendar import after preview
    window.confirmGcalImport = function() {
        // console.log('Confirming Google Calendar import');
        
        // Get stored import data
        if (!window.gcalImportData) {
            alert('No import data found. Please start over.');
            return;
        }
        
        // Create a copy of the data and remove preview_only flag
        const importData = {};
        for (const key in window.gcalImportData) {
            if (key !== 'preview_only') {
                importData[key] = window.gcalImportData[key];
            }
        }
        
        // Show loading state
        const resultsModal = document.querySelector('div[style*="position: fixed"]');
        if (resultsModal) {
            const content = resultsModal.querySelector('div[style*="background: white"]');
            if (content) {
                content.innerHTML = `
                    <div style="text-align: center; padding: 50px;">
                        <div style="font-size: 48px; margin-bottom: 20px;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                        <h4>Importing events...</h4>
                        <p style="color: #666;">Please wait while we import your Google Calendar events.</p>
                    </div>
                `;
            }
        }
        
        // Make actual import call
        postJSON('admin/gcal_import_from_google.php', importData).then(resp => {
            // console.log('Actual import response:', resp);
            
            if (resp.success) {
                // Re-enable auto-refresh
                window.gcalImportInProgress = false;
                
                // Show success message
                const content = resultsModal.querySelector('div[style*="background: white"]');
                if (content) {
                    content.innerHTML = `
                        <button onclick="window.gcalImportInProgress = false; this.parentElement.parentElement.remove(); window.location.reload();" 
                                style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; color: #999; cursor: pointer;">
                            ×
                        </button>
                        <div style="text-align: center; padding: 30px;">
                            <div style="font-size: 48px; color: #28a745; margin-bottom: 20px;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4 style="margin-bottom: 20px;">${resp.message}</h4>
                            ${resp.errors && resp.errors.length > 0 ? 
                                `<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 5px; margin: 20px 0;">
                                    <strong>Some errors occurred:</strong>
                                    <ul style="text-align: left; margin: 10px 0;">
                                        ${resp.errors.map(err => `<li>${err}</li>`).join('')}
                                    </ul>
                                </div>` : ''
                            }
                            <button onclick="window.gcalImportInProgress = false; this.parentElement.parentElement.parentElement.remove(); window.location.reload();" 
                                    style="margin-top: 20px; padding: 10px 30px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
                                Close & Reload
                            </button>
                        </div>
                    `;
                }
            } else {
                alert('Import failed: ' + (resp.message || 'Unknown error'));
                window.gcalImportInProgress = false;
            }
            
            // Clear stored data
            delete window.gcalImportData;
            
        }).catch(err => {
            // console.error('Confirm import error:', err);
            alert('Error during import: ' + err.message);
            window.gcalImportInProgress = false;
            delete window.gcalImportData;
        });
    };

    function showSyncResults(data) {
        console.log('showSyncResults called with data:', data);
        
        // Remove any existing sync modals first
        const existingModals = document.querySelectorAll('.gcal-sync-modal');
        existingModals.forEach(modal => modal.remove());
        
        const modal = document.createElement('div');
        modal.className = 'gcal-sync-modal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 50000;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        `;
        
        let eventsHtml = '';
        if (data.events && data.events.length > 0) {
            data.events.forEach(event => {
                const statusIcon = event.status === 'success' ? 
                    '<i class="fas fa-check-circle text-success"></i>' : 
                    '<i class="fas fa-times-circle text-danger"></i>';
                
                const statusBadge = event.status === 'success' ? 
                    '<span class="badge bg-success">Synced</span>' : 
                    '<span class="badge bg-danger">Failed</span>';
                
                eventsHtml += `
                    <div class="card mb-2">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${statusIcon} ${event.client_name || 'Unknown'}</strong><br>
                                    <small class="text-muted">${event.service || 'Unknown'} | ${event.start_time} ${event.google_event_link ? `<a href="${event.google_event_link}" target="_blank" title="View in Google Calendar" style="text-decoration: none; margin-left: 8px; display: inline-flex; align-items: center; vertical-align: middle;"><span style="width: 20px; height: 20px; background: linear-gradient(45deg, #4285f4 25%, #34a853 25%, #34a853 50%, #fbbc05 50%, #fbbc05 75%, #ea4335 75%); border-radius: 4px; display: inline-flex; flex-shrink: 0; transform: scale(1);"></span></a>` : ''}</small>
                                </div>
                                <div>
                                    ${statusBadge}
                                </div>
                            </div>
                            ${event.error ? `<div class="text-danger small mt-1"><strong>⚠️ Error:</strong> ${event.error}</div>` : ''}
                            <details class="mt-2">
                                <summary class="small text-muted" style="cursor: pointer;">🔍 Debug: Event Data Sent to Google</summary>
                                <pre class="small mt-1 bg-light p-2" style="font-size: 10px; max-height: 200px; overflow-y: auto; border-radius: 4px;">${JSON.stringify(event.google_event_data, null, 2)}</pre>
                            </details>
                        </div>
                    </div>
                `;
            });
        }
        
        modal.innerHTML = `
            <div style="background: white; border-radius: 8px; max-width: 800px; width: 90%; max-height: 80vh; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                <div style="padding: 20px; border-bottom: 1px solid #ddd; background: #f8f9fa;">
                    <h5 style="margin: 0; color: #333;">
                        <i class="fab fa-google" style="color: #4285f4;"></i> Google Calendar Sync Results
                    </h5>
                </div>
                <div style="padding: 20px; overflow-y: auto; max-height: 60vh;">
                    <div class="alert alert-${data.summary.errors > 0 ? 'warning' : 'success'} mb-3">
                        <strong>📊 Summary:</strong> ${data.summary.success} events synchronized successfully
                        ${data.summary.errors > 0 ? `, ${data.summary.errors} failed` : ''}
                        (Total: ${data.summary.total})
                    </div>
                    <div style="max-height: 400px; overflow-y: auto;">
                        ${eventsHtml || '<p class="text-muted">No events to display.</p>'}
                    </div>
                </div>
                <div style="padding: 15px 20px; border-top: 1px solid #ddd; text-align: right;">
                    <button type="button" class="btn btn-secondary me-2" onclick="this.closest('.gcal-sync-modal').remove()">Close</button>
                    <button type="button" class="btn btn-primary" onclick="window.location.reload()">Refresh Page</button>
                </div>
            </div>
        `;
        
        // Add click-outside-to-close functionality
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
            }
        });
        
        // Prevent modal from interfering with page events
        modal.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        document.body.appendChild(modal);
        console.log('Sync results modal added to page');
    }

    function showSyncError(message) {
        console.log('showSyncError called with message:', message);
        
        // Remove any existing sync modals first
        const existingModals = document.querySelectorAll('.gcal-sync-modal');
        existingModals.forEach(modal => modal.remove());
        
        const modal = document.createElement('div');
        modal.className = 'gcal-sync-modal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 50000;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        `;
        
        modal.innerHTML = `
            <div style="background: white; border-radius: 8px; max-width: 500px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                <div style="padding: 20px; border-bottom: 1px solid #ddd; background: #f8f9fa;">
                    <h5 style="margin: 0; color: #dc3545;">
                        <i class="fas fa-exclamation-triangle"></i> Sync Error
                    </h5>
                </div>
                <div style="padding: 20px;">
                    <div class="alert alert-danger">
                        ${message}
                    </div>
                </div>
                <div style="padding: 15px 20px; border-top: 1px solid #ddd; text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.gcal-sync-modal').remove()">Close</button>
                </div>
            </div>
        `;
        
        // Add click-outside-to-close functionality
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
            }
        });
        
        // Prevent modal from interfering with page events
        modal.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        document.body.appendChild(modal);
        console.log('Sync error modal added to page');
    }
    </script>
    <script>
        // Timezone for JavaScript (working point if available, otherwise organization)
        const organizationTimezone = '<?= 
            $supervisor_mode && $workpoint ? getTimezoneForWorkingPoint($workpoint) : 
            (!$supervisor_mode && !empty($working_points) ? getTimezoneForWorkingPoint($working_points[0]) : 
            getTimezoneForOrganisation($organisation)) 
        ?>';
        
        // Period change function - must be defined before HTML that uses it
        function changePeriod(period) {
            // Build URL without unnecessary parameters for predefined periods
            const url = new URL(window.location);
            url.searchParams.set('period', period);
            
            // Handle supervisor mode vs specialist mode
            <?php if ($supervisor_mode): ?>
            url.searchParams.set('working_point_user_id', '<?= $working_point_user_id ?>');
            url.searchParams.set('supervisor_mode', 'true');
            
            // Always include selected specialist in supervisor mode
            const currentSelectedSpecialist = url.searchParams.get('selected_specialist') || '<?= $selected_specialist ?>';
            
            if (currentSelectedSpecialist) {
                url.searchParams.set('selected_specialist', currentSelectedSpecialist);
            }
            
            <?php else: ?>
            url.searchParams.set('specialist_id', '<?= $specialist_id ?>');
            <?php endif; ?>
            
            // Remove any custom date parameters for predefined periods
            url.searchParams.delete('start_date');
            url.searchParams.delete('end_date');
            
            window.location.href = url.toString();
        }
        
        // Google Calendar Import Function
        window.gcalImportFromGoogle = function(specialistId) {
            // console.log('Opening Google Calendar import dialog for specialist:', specialistId);
            
            // First, fetch the correct services for this specialist
            fetch(`admin/get_specialist_services.php?specialist_id=${specialistId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert('Error loading services: ' + (data.message || 'Unknown error'));
                        return;
                    }
                    
                    // Build services HTML from the fetched data
                    let servicesHTML = '';
                    data.services.forEach(service => {
                        servicesHTML += `<option value="${service.id}">${service.name}</option>`;
                    });
                    
                    // Continue with creating the modal
                    showImportModal(specialistId, servicesHTML);
                })
                .catch(error => {
                    console.error('Error fetching services:', error);
                    alert('Error loading services. Please try again.');
                });
        };
        
        // Separate function to show the import modal
        window.showImportModal = function(specialistId, servicesHTML) {
            // Create import modal
            const modal = document.createElement('div');
            modal.className = 'gcal-import-modal';
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
                z-index: 50000;
                display: flex;
                justify-content: center;
                align-items: center;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            `;
            
            modal.innerHTML = `
                <div style="background: white; padding: 30px; border-radius: 10px; max-width: 660px; width: 100%; box-shadow: 0 5px 25px rgba(0,0,0,0.2); position: relative;">
                    <!-- Close button -->
                    <button onclick="document.querySelector('.gcal-import-modal').remove()" 
                            style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; color: #999; cursor: pointer; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;"
                            onmouseover="this.style.color='#333'" 
                            onmouseout="this.style.color='#999'">
                        ×
                    </button>
                    
                    <h3 style="margin-top: 0; margin-right: 30px; color: #333; display: flex; align-items: center; gap: 10px;">
                        <img src="logo/png-transparent-google-calendar-logo-icon.png" style="width: 32px; height: 32px;" />
                        Import from Google Calendar
                    </h3>
                    
                    <div style="margin: 20px 0;">
                        <p style="color: #666; margin-bottom: 20px;">
                            Select the date range to import events from your Google Calendar:
                        </p>
                        
                        <!-- Date range in same line -->
                        <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 5px; color: #333; font-weight: 600;">From Date:</label>
                                <input type="date" id="gcal-import-from" class="form-control" 
                                       value="${new Date().toISOString().split('T')[0]}"
                                       style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                            </div>
                            
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 5px; color: #333; font-weight: 600;">To Date:</label>
                                <input type="date" id="gcal-import-to" class="form-control" 
                                       value="${new Date(Date.now() + 30*24*60*60*1000).toISOString().split('T')[0]}"
                                       style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                            </div>
                        </div>
                        
                        <!-- Important section with frame on white background -->
                        <div style="background: white; border: 2px solid #dee2e6; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
                            <strong style="color: #495057; font-size: 14px;">⚠️ Important - How Import Works:</strong>
                            <ul style="margin: 8px 0 0 0; padding-left: 20px; color: #495057; line-height: 1.3; font-size: 13px;">
                                <li>Only events that don't already exist will be imported</li>
                                <li><strong>Phone numbers:</strong> The system will search for phone numbers in the event title and description. It looks for:
                                    <ul style="margin-top: 3px; margin-bottom: 3px;">
                                        <li>Numbers labeled with "phone", "tel", "mobile" etc.</li>
                                        <li>Any sequence of 9 or more digits</li>
                                        <li>Numbers with dashes or dots will be cleaned automatically</li>
                                    </ul>
                                </li>
                                <li><strong>Services:</strong> Services are matched using keywords you define below. If no keyword matches, the default service will be assigned</li>
                                <li><strong>Client names:</strong> Extracted from the event title before any dash (-) character</li>
                            </ul>
                        </div>
                        
                        <!-- Service keyword mapping on yellow background -->
                        <div style="background: #fffacd; border: 1px solid #f0e68c; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 10px; color: #333; font-weight: 600;">
                                Service Keyword Mapping:
                                <button type="button" onclick="addServiceMappingRow()" 
                                        style="margin-left: 10px; padding: 2px 8px; font-size: 12px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;">
                                    + Add
                                </button>
                            </label>
                            <p style="font-size: 13px; color: #666; margin-bottom: 10px; line-height: 1.4;">
                                Map keywords found in your Google Calendar events to specific services you have inserted in your database of this app. For example, if your Google Calendar event contains "hair cut", it will be imported with the service you select here.
                            </p>
                            <div id="service-mappings">
                                <div class="service-mapping-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                                    <input type="text" placeholder="Keyword (e.g. hair cut)" 
                                           style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                                    <select style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                                        <option value="">Select Service</option>
                                        ${servicesHTML}
                                    </select>
                                    <button type="button" onclick="this.parentElement.remove()" 
                                            style="padding: 8px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                        ×
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button onclick="document.querySelector('.gcal-import-modal').remove()" 
                                style="padding: 8px 20px; border: 1px solid #6c757d; border-radius: 4px; background: white; color: #6c757d; cursor: pointer; font-weight: 500;">
                            Cancel
                        </button>
                        <button onclick="executeGcalImport(${specialistId})" 
                                style="padding: 8px 20px; border: none; border-radius: 4px; background: #dc3545; color: white; cursor: pointer; font-weight: 600;">
                            <span id="import-btn-text">Import Events</span>
                            <i class="fas fa-spinner fa-spin" style="display: none;" id="import-spinner"></i>
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        };
        
        // Add service mapping row function
        window.addServiceMappingRow = function() {
            // Get the services HTML from the existing select in the modal
            const existingSelect = document.querySelector('#service-mappings select');
            let servicesHTML = '';
            if (existingSelect) {
                // Copy options from existing select
                Array.from(existingSelect.options).forEach(option => {
                    if (option.value) { // Skip the "Select Service" option
                        servicesHTML += `<option value="${option.value}">${option.text}</option>`;
                    }
                });
            }
            const mappingDiv = document.getElementById('service-mappings');
            const newRow = document.createElement('div');
            newRow.className = 'service-mapping-row';
            newRow.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px;';
            newRow.innerHTML = `
                <input type="text" placeholder="Keyword (e.g. hair cut)" 
                       style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                <select style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                    <option value="">Select Service</option>
                    ${servicesHTML}
                </select>
                <button type="button" onclick="this.parentElement.remove()" 
                        style="padding: 8px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    ×
                </button>
            `;
            mappingDiv.appendChild(newRow);
        };
        
        // Execute import function
        window.executeGcalImport = function(specialistId) {
            const fromDate = document.getElementById('gcal-import-from').value;
            const toDate = document.getElementById('gcal-import-to').value;
            
            if (!fromDate || !toDate) {
                alert('Please select both dates');
                return;
            }
            
            if (fromDate > toDate) {
                alert('From date must be before To date');
                return;
            }
            
            // Disable real-time reload during import
            window.gcalImportInProgress = true;
            
            // Collect service mappings
            const mappings = [];
            document.querySelectorAll('.service-mapping-row').forEach(row => {
                const keyword = row.querySelector('input[type="text"]').value.trim();
                const serviceId = row.querySelector('select').value;
                if (keyword && serviceId) {
                    mappings.push({ keywords: keyword.toLowerCase(), service_id: serviceId });
                }
            });
            
            // Show loading
            document.getElementById('import-btn-text').style.display = 'none';
            document.getElementById('import-spinner').style.display = 'inline-block';
            
            // Prepare data for POST
            const postData = {
                specialist_id: specialistId,
                from_date: fromDate,
                to_date: toDate,
                preview_only: 'true' // First call is preview only
            };
            
            // Add service mappings as array elements
            mappings.forEach((mapping, index) => {
                postData[`service_mappings[${index}][keywords]`] = mapping.keywords;
                postData[`service_mappings[${index}][service_id]`] = mapping.service_id;
            });
            
            // Store data for actual import
            window.gcalImportData = postData;
            
            // Preview call
            // console.log('Calling preview with data:', postData);
            postJSON('admin/gcal_import_from_google.php', postData).then(resp => {
                // console.log('Import response:', resp);
                
                if (resp.success) {
                    document.querySelector('.gcal-import-modal').remove();
                    
                    // Show detailed results modal
                    const resultsModal = document.createElement('div');
                    resultsModal.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background-color: rgba(0,0,0,0.5);
                        z-index: 50000;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    `;
                    
                    let eventsHtml = '';
                    if (resp.debug_info && resp.debug_info.events) {
                        eventsHtml = '<div style="max-height: 400px; overflow-y: auto;">';
                        eventsHtml += '<h5>Found ' + resp.debug_info.total_events_found + ' events in Google Calendar</h5>';
                        eventsHtml += '<p>Date range: ' + resp.debug_info.date_range.from + ' to ' + resp.debug_info.date_range.to + '</p>';
                        eventsHtml += '<p>Calendar ID: ' + (resp.debug_info.calendar_id || 'primary').substring(0, 20) + '...</p>';
                        eventsHtml += '<hr>';
                        
                        if (resp.debug_info.events.length === 0) {
                            eventsHtml += '<p style="color: orange;">No events found in the selected date range. Try selecting different dates or check if you have events in your Google Calendar.</p>';
                        } else {
                            resp.debug_info.events.forEach((event, idx) => {
                                const statusColor = event.status === 'imported' ? 'green' : 
                                                  event.status === 'will_import' ? 'blue' :
                                                  event.status === 'skipped' ? 'orange' : 'red';
                                const statusText = event.status === 'will_import' ? 'Will Import' : event.status;
                                eventsHtml += `
                                    <div style="margin-bottom: 10px; padding: 8px; border: 1px solid #ddd; border-radius: 5px; line-height: 1.3;">
                                        <strong>${idx + 1}. ${event.summary}</strong> <span style="font-weight: normal; font-size: 0.9em;">(${event.start})</span><br style="margin-bottom: 2px;">
                                        <small>Status: <span style="color: ${statusColor}">${statusText}</span> 
                                        ${event.reason ? '(' + event.reason + ')' : ''}</small><br style="margin-bottom: 2px;">
                                        ${event.client_name ? '<small><span style="text-decoration: underline;">Client:</span> ' + event.client_name + '</small><br style="margin-bottom: 2px;">' : ''}
                                        ${event.phone_extracted ? '<small><span style="text-decoration: underline;">Phone:</span> ' + event.phone_extracted + '</small><br style="margin-bottom: 2px;">' : ''}
                                        ${event.service_matched ? '<small><span style="text-decoration: underline;">Service:</span> ' + event.service_matched + '</small><br style="margin-bottom: 2px;">' : ''}
                                        ${event.error ? '<small style="color: red">Error: ' + event.error + '</small>' : ''}
                                    </div>
                                `;
                            });
                        }
                        eventsHtml += '</div>';
                    }
                    
                    const buttonHtml = resp.preview_mode ? 
                        `<button onclick="window.gcalImportInProgress = false; this.parentElement.parentElement.remove();" 
                                style="margin-top: 20px; margin-right: 10px; padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            Cancel
                        </button>
                        <button onclick="window.confirmGcalImport()" 
                                style="margin-top: 20px; padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            Confirm Import (${resp.imported} events)
                        </button>` :
                        `<button onclick="window.gcalImportInProgress = false; this.parentElement.parentElement.remove(); ${resp.imported > 0 ? 'window.location.reload();' : ''}" 
                                style="margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            ${resp.imported > 0 ? 'Close & Reload' : 'Close'}
                        </button>`;
                    
                    resultsModal.innerHTML = `
                        <div style="background: white; padding: 30px; border-radius: 10px; max-width: 800px; width: 90%; max-height: 90vh; overflow-y: auto;">
                            <button onclick="window.gcalImportInProgress = false; this.parentElement.parentElement.remove();" 
                                    style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; color: #999; cursor: pointer;">
                                ×
                            </button>
                            <h4>${resp.message}</h4>
                            ${eventsHtml}
                            <div style="text-align: center;">
                                ${buttonHtml}
                            </div>
                        </div>
                    `;
                    document.body.appendChild(resultsModal);
                } else {
                    // console.error('Import failed response:', resp);
                    alert('Import failed: ' + (resp.message || 'Unknown error'));
                    document.getElementById('import-btn-text').style.display = 'inline';
                    document.getElementById('import-spinner').style.display = 'none';
                }
            }).catch(err => {
                // console.error('Import error:', err);
                alert('Error: ' + err.message);
                document.getElementById('import-btn-text').style.display = 'inline';
                document.getElementById('import-spinner').style.display = 'none';
            });
        };
        
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                timeZone: organizationTimezone,
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('currentTimeClock').textContent = timeString;
        }
        
        setInterval(updateTime, 1000);

        // Period selection handling
        function setupPeriodSelectors() {
            const periodRadios = document.querySelectorAll('input[name="period"]');
            
            periodRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    // Build URL without unnecessary parameters for predefined periods
                    const url = new URL(window.location);
                    url.searchParams.set('period', this.value);
                    
                    // Handle supervisor mode vs specialist mode
                    <?php if ($supervisor_mode): ?>
                    url.searchParams.set('working_point_user_id', '<?= $working_point_user_id ?>');
                    url.searchParams.set('supervisor_mode', 'true');
                    <?php else: ?>
                    url.searchParams.set('specialist_id', '<?= $specialist_id ?>');
                    <?php endif; ?>
                    
                    // Remove any custom date parameters for predefined periods
                    url.searchParams.delete('start_date');
                    url.searchParams.delete('end_date');
                    
                    window.location.href = url.toString();
                });
            });
        }
        
        // Setup period selectors when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            setupPeriodSelectors();
        });
        
        // Also setup immediately in case DOM is already loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupPeriodSelectors);
        } else {
            setupPeriodSelectors();
        }
        
        // Debug: Test if radio buttons are clickable
        setTimeout(() => {
            const radios = document.querySelectorAll('input[name="period"]');
            radios.forEach((radio, index) => {
                // Add a simple click test
                radio.addEventListener('click', function() {
                    // Click handler for radio buttons
                });
            });
        }, 1000);

        // Language change
        function changeLanguage(lang) {
            const url = new URL(window.location);
            url.searchParams.set('lang', lang);
            
            // Handle supervisor mode vs specialist mode
            <?php if ($supervisor_mode): ?>
            url.searchParams.set('working_point_user_id', '<?= $working_point_user_id ?>');
            url.searchParams.set('supervisor_mode', 'true');
            <?php else: ?>
            url.searchParams.set('specialist_id', '<?= $specialist_id ?>');
            <?php endif; ?>
            
            url.searchParams.set('period', '<?= $period ?>');
            
            // No custom date range to preserve
            
            window.location.href = url.toString();
        }
        

        

        // Real-time booking updates
        let realtimeBookings = null;
        let reloadTimeout = null;
        let lastReloadTime = parseInt(sessionStorage.getItem('lastReloadTime') || '0');
        const RELOAD_COOLDOWN = 10000; // 10 seconds minimum between reloads
        
        function initializeRealtimeBookings() {
            realtimeBookings = new RealtimeBookings({
                specialistId: <?= $supervisor_mode ? 'null' : $specialist_id ?>,
                workpointId: <?= $supervisor_mode ? $working_point_user_id : 'null' ?>,
                supervisorMode: <?= $supervisor_mode ? 'true' : 'false' ?>,
                onUpdate: function(data) {
                    console.log('Booking update received:', data);
                    
                    // Create a unique event ID
                    const eventId = data.timestamp + '_' + data.type + '_' + (data.data.booking_id || '');
                    
                    // Check if we've already processed this event
                    const processedEvents = JSON.parse(sessionStorage.getItem('processedBookingEvents') || '{}');
                    const now = Math.floor(Date.now() / 1000);
                    
                    // Clean up old entries (older than 60 seconds)
                    for (const key in processedEvents) {
                        if (now - processedEvents[key] > 60) {
                            delete processedEvents[key];
                        }
                    }
                    
                    // Check if this event was already processed
                    if (processedEvents[eventId]) {
                        console.log('Event already processed, ignoring');
                        return;
                    }
                    
                    // Only reload for recent events (within last 30 seconds)
                    const eventTime = data.timestamp || 0;
                    const age = now - eventTime;
                    
                    if (age < 30) {
                        // Mark this event as processed
                        processedEvents[eventId] = now;
                        sessionStorage.setItem('processedBookingEvents', JSON.stringify(processedEvents));
                        
                        // Store event details for showing after reload
                        const eventDetails = {
                            type: data.type,
                            clientName: data.data.client_full_name || 'Unknown',
                            bookingId: data.data.booking_id,
                            specialistId: data.data.specialist_id,
                            timestamp: now
                        };
                        sessionStorage.setItem('lastBookingUpdate', JSON.stringify(eventDetails));
                        
                        console.log('Recent update detected, scheduling reload...');
                        
                        // Clear any existing reload timeout
                        if (reloadTimeout) {
                            clearTimeout(reloadTimeout);
                        }
                        
                        // Check if we recently reloaded
                        const currentTime = Date.now();
                        const timeSinceLastReload = currentTime - lastReloadTime;
                        
                        if (timeSinceLastReload < RELOAD_COOLDOWN) {
                            console.log('Skipping reload - too soon since last reload (' + timeSinceLastReload + 'ms ago)');
                            return;
                        }
                        
                        // Check if Google Calendar import is in progress
                        if (window.gcalImportInProgress) {
                            console.log('Skipping reload - Google Calendar import in progress');
                            return;
                        }
                        
                        // Schedule reload with a small delay to batch multiple updates
                        reloadTimeout = setTimeout(() => {
                            lastReloadTime = Date.now();
                            sessionStorage.setItem('lastReloadTime', lastReloadTime.toString());
                            console.log('Executing scheduled reload...');
                            window.location.reload();
                        }, 1000); // Wait 1 second to batch multiple updates
                    } else {
                        console.log('Ignoring old event (age: ' + age + ' seconds)');
                    }
                },
                onStatusChange: function(status, message, mode) {
                    updateRealtimeStatus(status, message, mode);
                },
                debug: true  // Keep enabled for testing
            });
            
            realtimeBookings.start();
        }
        
        function updateRealtimeStatus(status, message, mode) {
            const statusBtn = document.getElementById('realtime-status-btn');
            if (!statusBtn) return;

            const statusIcon = statusBtn.querySelector('.status-icon');

            // Detailed tooltip descriptions
            let tooltip = '';

            // Update icon and color based on status
            switch(status) {
                case 'connected':
                    statusIcon.style.color = '#28a745'; // Green
                    tooltip = `Real-time booking updates: ACTIVE\nMode: ${message}\nClick to disable automatic updates`;
                    break;
                case 'reconnecting':
                    statusIcon.style.color = '#ffc107'; // Yellow/Orange
                    tooltip = `Real-time booking updates: RECONNECTING\nStatus: ${message}\nClick to disable`;
                    break;
                case 'error':
                    statusIcon.style.color = '#dc3545'; // Red
                    tooltip = `Real-time booking updates: ERROR\nStatus: ${message}\nClick to retry`;
                    break;
                case 'stopped':
                    statusIcon.style.color = '#dc3545'; // Red
                    tooltip = `Real-time booking updates: DISABLED\nClick to enable automatic updates`;
                    break;
            }

            statusBtn.title = tooltip;
        }
        
        function toggleRealtimeUpdates() {
            if (realtimeBookings) {
                const isEnabled = realtimeBookings.toggle();
                const statusBtn = document.getElementById('realtime-status-btn');
                
                if (!isEnabled) {
                    updateRealtimeStatus('stopped', 'Disabled', 'none');
                }
            }
        }

        // Show booking update notification if page was reloaded due to update
        function showBookingNotification() {
            const lastUpdate = sessionStorage.getItem('lastBookingUpdate');
            if (lastUpdate) {
                sessionStorage.removeItem('lastBookingUpdate');
                const update = JSON.parse(lastUpdate);
                
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `booking-notification ${update.type}`;
                
                const icons = {
                    create: 'fa-plus-circle',
                    update: 'fa-edit',
                    delete: 'fa-trash'
                };
                
                const titles = {
                    create: 'New Booking Added',
                    update: 'Booking Updated',
                    delete: 'Booking Cancelled'
                };
                
                notification.innerHTML = `
                    <button class="notification-close" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="notification-header">
                        <div class="notification-icon ${update.type}">
                            <i class="fas ${icons[update.type] || 'fa-info-circle'}"></i>
                        </div>
                        <div>
                            <div>${titles[update.type] || 'Booking Changed'}</div>
                            <small style="color: #999; font-weight: normal;">Just now</small>
                        </div>
                    </div>
                    <div class="notification-body">
                        <strong>Client:</strong> ${update.clientName}<br>
                        <strong>Booking ID:</strong> #${update.bookingId}<br>
                        ${update.type === 'update' ? '<em>The page has been refreshed to show the latest changes.</em>' : ''}
                    </div>
                `;
                
                document.getElementById('notification-container').appendChild(notification);
                
                // Remove notification after animation completes
                setTimeout(() => {
                    notification.remove();
                }, 5000);
            }
        }

        // Interactive logo animations
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Show notification if there was a booking update
            showBookingNotification();
            
            // Setup period selectors first
            setupPeriodSelectors();
            setupSpecialistCollapsible();
            
            // Initialize real-time booking updates
            initializeRealtimeBookings();
            
            
            const logoText = document.querySelector('.logo-text');
            const letters = document.querySelectorAll('.logo-letter');
            
            // Add click animation to logo
            logoText.addEventListener('click', function() {
                letters.forEach((letter, index) => {
                    setTimeout(() => {
                        letter.style.transform = 'scale(1.2) rotate(10deg)';
                        setTimeout(() => {
                            letter.style.transform = 'scale(1) rotate(0deg)';
                        }, 200);
                    }, index * 50);
                });
            });
            
            // Add random eye movements and expressions with 3-cycle limit
            const beautyEyes = document.querySelectorAll('.logo-letter.beauty-eye');
            beautyEyes.forEach((eye, index) => {
                const iris = eye.querySelector('.iris');
                const eyeOuter = eye.querySelector('.eye-outer');
                let animationCount = 0;
                let maxAnimations = 3;
                let isAnimating = false;
                
                function triggerRandomMovement() {
                    if (isAnimating || animationCount >= maxAnimations) return;
                    
                    isAnimating = true;
                    animationCount++;
                    
                    // Random eye movement
                    iris.style.transform = `translateX(${(Math.random() - 0.5) * 0.2}em)`;
                    eyeOuter.style.transform = 'scale(1.1)';
                    
                    setTimeout(() => {
                        iris.style.transform = 'translateX(0)';
                        eyeOuter.style.transform = 'scale(1)';
                        isAnimating = false;
                    }, 800);
                }
                
                // Trigger random movements every 6 seconds
                setInterval(triggerRandomMovement, 6000 + (index * 2000));
                
                // Reset animation count on mouse over
                eye.addEventListener('mouseenter', function() {
                    animationCount = 0;
                    isAnimating = false;
                });
            });
            
            // Add elegant beauty sparkle effect on hover
            logoText.addEventListener('mouseenter', function() {
                const elegantSparkles = ['🌸', '✨', '💎', '🌺', '💫'];
                const sparkle = document.createElement('div');
                sparkle.innerHTML = elegantSparkles[Math.floor(Math.random() * elegantSparkles.length)];
                sparkle.style.position = 'absolute';
                sparkle.style.fontSize = '2rem';
                sparkle.style.pointerEvents = 'none';
                sparkle.style.animation = 'beautySparkle 2s ease-out forwards';
                sparkle.style.left = Math.random() * 100 + '%';
                sparkle.style.top = Math.random() * 100 + '%';
                
                logoText.style.position = 'relative';
                logoText.appendChild(sparkle);
                
                setTimeout(() => {
                    sparkle.remove();
                }, 2000);
            });
        });

        // Add beauty salon sparkle animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes beautySparkle {
                0% {
                    opacity: 0;
                    transform: scale(0) rotate(0deg);
                }
                25% {
                    opacity: 1;
                    transform: scale(1.2) rotate(90deg);
                }
                50% {
                    opacity: 0.8;
                    transform: scale(1) rotate(180deg);
                }
                75% {
                    opacity: 0.6;
                    transform: scale(0.8) rotate(270deg);
                }
                100% {
                    opacity: 0;
                    transform: scale(0) rotate(360deg);
                }
            }
            
            /* Specialist collapsible styles */
            
            .schedule-content {
                transition: all 0.3s ease;
                overflow: hidden;
            }
            
            .toggle-icon {
                transition: transform 0.3s ease;
            }
            
                    .toggle-icon.rotated {
            transform: rotate(180deg);
        }
        

        `;
        document.head.appendChild(style);
        
        // Specialist collapsible functionality - now handled via inline onclick handlers
        // Keeping empty function to avoid breaking existing calls
        function setupSpecialistCollapsible() {
            // This functionality is now handled by inline onclick handlers
            // as per CLAUDE.md best practices for AJAX-loaded content
        }
        
        // Setup specialist collapsible when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            setupPeriodSelectors();
            setupSpecialistCollapsible();
            loadModifyScheduleEditor();
        });
        
        // Also setup immediately in case DOM is already loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupSpecialistCollapsible);
        } else {
            setupSpecialistCollapsible();
            loadModifyScheduleEditor();
        }
        

        
        // Add Specialist Modal Functions
        function openAddSpecialistModal(workpointId, organisationId) {
            const modal = document.getElementById('addSpecialistModal');
            if (!modal) {
                return;
            }
            
            modal.style.display = 'flex';
            document.getElementById('workpointId').value = workpointId;
            document.getElementById('organisationId').value = organisationId;
            
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
                loadAvailableSpecialists(organisationId, workpointId);
            } else {
                document.getElementById('workpointSelect').style.display = 'block';
                document.getElementById('workpointLabel').style.display = 'block';
                document.getElementById('workpointLabel').textContent = 'Assign to Working Point *';
                
                // Load working points for this organisation
                loadWorkingPointsForOrganisation(organisationId);
            }
            
            // Load schedule editor
            loadScheduleEditor();
        }
        
        function closeAddSpecialistModal() {
            document.getElementById('addSpecialistModal').style.display = 'none';
            document.getElementById('addSpecialistForm').reset();
            document.getElementById('scheduleEditorTableBody').innerHTML = '';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addSpecialistModal');
            if (event.target === modal) {
                closeAddSpecialistModal();
            }
        }
        
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
                    // Handle error silently
                });
        }
        
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

        function onSelectUnassignedSpecialist(specialistId) {
            if (!specialistId) return;
            // Open modify schedule modal for selected orphan specialist targeting current workpoint
            const currentWorkpointId = '<?= isset($workpoint['unic_id']) ? (int)$workpoint['unic_id'] : (int)($_GET['working_point_user_id'] ?? 0) ?>';
            openModifyScheduleModal(specialistId, currentWorkpointId);
            // Reset selection so it can be re-used
            const sel = document.getElementById('unassignedSpecialistsSelect');
            if (sel) sel.value = '';
        }
        
        function handleSpecialistSelection() {
            const specialistSelect = document.getElementById('specialistSelection');
            const selectedValue = specialistSelect.value;
            
            if (selectedValue === 'new') {
                // New specialist mode - enable all fields
                enableFormFields();
                clearFormFields();
            } else if (selectedValue && selectedValue !== '') {
                // Existing specialist mode - load data and make fields read-only
                loadSpecialistData(selectedValue);
                disableFormFields();
            }
        }
        
        function enableFormFields() {
            const fields = ['specialistName', 'specialistSpeciality', 'specialistEmail', 'specialistPhone', 'specialistUser', 'specialistPassword', 'emailScheduleHour', 'emailScheduleMinute'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.readOnly = false;
                    field.disabled = false;
                    field.style.backgroundColor = '#fff';
                } else {
                    console.warn('Field not found:', fieldId);
                }
            });
        }
        
        function disableFormFields() {
            const fields = ['specialistName', 'specialistSpeciality', 'specialistEmail', 'specialistPhone', 'specialistUser', 'specialistPassword', 'emailScheduleHour', 'emailScheduleMinute'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.readOnly = true;
                    field.disabled = true;
                    field.style.backgroundColor = '#f8f9fa';
                } else {
                    console.warn('Field not found:', fieldId);
                }
            });
        }
        
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
        
        function loadSpecialistData(specialistId) {
            fetch(`admin/get_specialist_data.php?specialist_id=${specialistId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const specialist = data.specialist;
                        
                        // Populate form fields
                        const nameField = document.getElementById('specialistName');
                        const specialityField = document.getElementById('specialistSpeciality');
                        const emailField = document.getElementById('specialistEmail');
                        const phoneField = document.getElementById('specialistPhone');
                        const userField = document.getElementById('specialistUser');
                        const passwordField = document.getElementById('specialistPassword');
                        const hourField = document.getElementById('emailScheduleHour');
                        const minuteField = document.getElementById('emailScheduleMinute');
                        
                        if (nameField) nameField.value = specialist.name || '';
                        if (specialityField) specialityField.value = specialist.speciality || '';
                        if (emailField) emailField.value = specialist.email || '';
                        if (phoneField) phoneField.value = specialist.phone_nr || '';
                        if (userField) userField.value = specialist.user || '';
                        if (passwordField) passwordField.value = ''; // Don't populate password
                        if (hourField) hourField.value = specialist.h_of_email_schedule || '9';
                        if (minuteField) minuteField.value = specialist.m_of_email_schedule || '0';
                    } else {
                        console.error('Failed to load specialist data:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading specialist data:', error);
                });
        }
        
        function clearShift(button, shiftNum) {
            const row = button.closest('tr');
            const startInput = row.querySelector(`.shift${shiftNum}-start-time`);
            const endInput = row.querySelector(`.shift${shiftNum}-end-time`);
            startInput.value = '';
            endInput.value = '';
        }
        
        function applyAllShifts() {
            const dayRange = document.getElementById('quickOptionsDaySelect').value;
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
            const shift1Start = document.getElementById('shift1Start').value;
            const shift1End = document.getElementById('shift1End').value;
            const shift2Start = document.getElementById('shift2Start').value;
            const shift2End = document.getElementById('shift2End').value;
            const shift3Start = document.getElementById('shift3Start').value;
            const shift3End = document.getElementById('shift3End').value;
            
            // Check if at least one shift has values
            const hasShift1 = shift1Start && shift1End;
            const hasShift2 = shift2Start && shift2End;
            const hasShift3 = shift3Start && shift3End;
            
            if (!hasShift1 && !hasShift2 && !hasShift3) {
                return;
            }
            
            // Apply shifts to all selected days
            days.forEach(day => {
                const row = document.querySelector(`tr:has(input[name="shift1_start_${day}"])`);
                if (row) {
                    // Apply Shift 1 only if values are provided
                    if (hasShift1) {
                        const startInput = row.querySelector('.shift1-start-time');
                        const endInput = row.querySelector('.shift1-end-time');
                        if (startInput && endInput) {
                            startInput.value = shift1Start;
                            endInput.value = shift1End;
                        }
                    }
                    
                    // Apply Shift 2 only if values are provided
                    if (hasShift2) {
                        const startInput = row.querySelector('.shift2-start-time');
                        const endInput = row.querySelector('.shift2-end-time');
                        if (startInput && endInput) {
                            startInput.value = shift2Start;
                            endInput.value = shift2End;
                        }
                    }
                    
                    // Apply Shift 3 only if values are provided
                    if (hasShift3) {
                        const startInput = row.querySelector('.shift3-start-time');
                        const endInput = row.querySelector('.shift3-end-time');
                        if (startInput && endInput) {
                            startInput.value = shift3Start;
                            endInput.value = shift3End;
                        }
                    }
                }
            });
        }
        
        function submitAddSpecialist() {
            const formData = new FormData(document.getElementById('addSpecialistForm'));
            const specialistSelection = document.getElementById('specialistSelection').value;
            
            // Get workpoint_id and organisation_id
            let workpointId = document.getElementById('workpointId').value;
            const organisationId = document.getElementById('organisationId').value;
            
            // If hidden field is empty, try to get from select dropdown
            if (!workpointId) {
                workpointId = document.getElementById('workpointSelect').value;
            }
            
            // Ensure both IDs are included
            if (workpointId) {
                formData.append('workpoint_id', workpointId);
                formData.append('working_points[]', workpointId);
            }
            
            if (organisationId) {
                formData.append('organisation_id', organisationId);
            }
            
            // Add schedule data
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            days.forEach(day => {
                for (let shift = 1; shift <= 3; shift++) {
                    const startInput = document.querySelector(`input[name="shift${shift}_start_${day}"]`);
                    const endInput = document.querySelector(`input[name="shift${shift}_end_${day}"]`);
                    if (startInput && endInput) {
                        formData.append(`schedule[${day}][shift${shift}_start]`, startInput.value || '');
                        formData.append(`schedule[${day}][shift${shift}_end]`, endInput.value || '');
                    }
                }
            });
            
            // Determine action based on specialist selection
            if (specialistSelection === 'new') {
                // New specialist - use existing add_specialist_ajax.php
                formData.append('action', 'add_new_specialist');
                submitToAddSpecialist(formData);
            } else {
                // Existing specialist - use new reactivate_specialist_ajax.php
                formData.append('action', 'reactivate_specialist');
                formData.append('specialist_id', specialistSelection);
                submitToReactivateSpecialist(formData);
            }
        }
        
        function submitToAddSpecialist(formData) {
            fetch('admin/add_specialist_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Specialist added successfully!');
                    closeAddSpecialistModal();
                    // Reload the page to show the new specialist
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('An error occurred while adding the specialist.');
            });
        }
        
        function submitToReactivateSpecialist(formData) {
            // Get the required IDs from hidden fields
            const workpointId = document.getElementById('workpointId').value;
            const organisationId = document.getElementById('organisationId').value;
            
            // Add the required IDs to form data
            formData.append('workpoint_id', workpointId);
            formData.append('organisation_id', organisationId);
            
            fetch('admin/reactivate_specialist_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Specialist reactivated successfully!');
                    closeAddSpecialistModal();
                    // Reload the page to show the reactivated specialist
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || data.error));
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('An error occurred while reactivating the specialist.');
            });
        }
        
        // Modify Specialist Modal Functions
        function openModifySpecialistModal(specialistId, specialistName, workpointId) {
            const modal = document.getElementById('modifySpecialistModal');
            if (!modal) {
                console.error('Modify specialist modal not found!');
                return;
            }
            
            // Update modal title with specialist ID
            const modalTitle = modal.querySelector('.modify-modal-header h3');
            if (modalTitle) {
                modalTitle.innerHTML = `👥 Modify Specialist Details [${specialistId}]`;
            }
            
            // Set modal IDs
            const specialistIdField = document.getElementById('modifySpecialistId');
            const workpointIdField = document.getElementById('modifyWorkpointId');
            
            if (specialistIdField) specialistIdField.value = specialistId;
            if (workpointIdField) workpointIdField.value = workpointId;
            
            // Clear previous error messages
            const errorElement = document.getElementById('modifySpecialistError');
            if (errorElement) errorElement.style.display = 'none';
            
            // Load specialist data
            loadSpecialistDataForModal(specialistId);
            
            // Show modal
            modal.style.display = 'flex';
        }
        
        function closeModifySpecialistModal() {
            document.getElementById('modifySpecialistModal').style.display = 'none';
            document.getElementById('modifySpecialistForm').reset();
            document.getElementById('modifySpecialistError').style.display = 'none';
        }
        
        function loadSpecialistDataForModal(specialistId) {
            const formData = new FormData();
            formData.append('action', 'get_specialist_data');
            formData.append('specialist_id', specialistId);
            
            fetch('admin/modify_specialist_details.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const specialist = data.specialist;
                    
                    // Populate form fields with proper field mapping
                    const nameField = document.getElementById('modifySpecialistNameField');
                    const specialityField = document.getElementById('modifySpecialistSpeciality');
                    const emailField = document.getElementById('modifySpecialistEmail');
                    const phoneField = document.getElementById('modifySpecialistPhone');
                    const userField = document.getElementById('modifySpecialistUser');
                    const passwordField = document.getElementById('modifySpecialistPassword');
                    const hourField = document.getElementById('modifySpecialistEmailHour');
                    const minuteField = document.getElementById('modifySpecialistEmailMinute');
                    
                    if (nameField) nameField.value = specialist.name || '';
                    if (specialityField) specialityField.value = specialist.speciality || '';
                    if (emailField) emailField.value = specialist.email || '';
                    if (phoneField) phoneField.value = specialist.phone_nr || '';
                    if (userField) userField.value = specialist.user || '';
                    if (passwordField) passwordField.value = ''; // Don't populate password for security
                    if (hourField) hourField.value = specialist.h_of_email_schedule || '9';
                    if (minuteField) minuteField.value = specialist.m_of_email_schedule || '0';
                    
                    // Show/hide email schedule fields based on daily_email_enabled
                    const emailScheduleContainer = document.getElementById('emailScheduleContainer');
                    if (emailScheduleContainer) {
                        if (specialist.daily_email_enabled && specialist.daily_email_enabled != 0 && specialist.daily_email_enabled != null) {
                            emailScheduleContainer.style.display = 'flex';
                        } else {
                            emailScheduleContainer.style.display = 'none';
                        }
                    }
                    
                    // Load services for this specialist
                    loadSpecialistServicesForModal(specialistId);
                } else {
                    console.error('Failed to load specialist data:', data.message);
                    document.getElementById('modifySpecialistError').textContent = 'Failed to load specialist data: ' + data.message;
                    document.getElementById('modifySpecialistError').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error loading specialist data:', error);
                document.getElementById('modifySpecialistError').textContent = 'An error occurred while loading specialist data.';
                document.getElementById('modifySpecialistError').style.display = 'block';
            });
        }
        
        function loadSpecialistServicesForModal(specialistId) {
            const servicesDisplay = document.getElementById('modifySpecialistServicesDisplay');
            if (!servicesDisplay) return;
            
            // Show loading
            servicesDisplay.innerHTML = '<div style="text-align: center; color: #6c757d;"><i class="fas fa-spinner fa-spin"></i> Loading services...</div>';
            
            // Fetch services from database with cache buster
            const timestamp = new Date().getTime();
            fetch(`admin/get_specialist_services.php?specialist_id=${specialistId}&t=${timestamp}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Services response:', data);
                    if (data.success && data.services.length > 0) {
                        let servicesHTML = '<div style="max-height: 300px; overflow-y: auto;">';
                        
                        data.services.forEach(service => {
                            const priceWithVat = service.price_of_service * (1 + service.procent_vat / 100);
                            const isSuspended = service.suspended == 1;
                            const serviceColor = isSuspended ? '#6c757d' : '#495057';
                            
                            servicesHTML += `
                                <div class="service-item" style="padding: 8px 12px; margin-bottom: 4px; background: #f8f9fa; border-radius: 4px; font-size: 13px; transition: background-color 0.2s; cursor: pointer;"
                                     onmouseover="this.style.backgroundColor='#e9ecef'"
                                     onmouseout="this.style.backgroundColor='#f8f9fa'"
                                     onclick="window.serviceReturnModal = 'modifySpecialist'; window.serviceReturnSpecialistId = '${specialistId}'; editSpecialistService(${service.unic_id}, '${service.name_of_service.replace(/'/g, "\\'")}', ${service.duration}, ${service.price_of_service}, ${service.procent_vat})">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div style="flex: 1;">
                                            <span style="color: ${serviceColor}; ${isSuspended ? 'opacity: 0.6;' : ''}">${service.name_of_service}</span>
                                            ${isSuspended ? '<span style="font-size: 11px; color: #dc3545; margin-left: 8px;">(Suspended)</span>' : ''}
                                            <div style="font-size: 11px; color: #6c757d; line-height: 1.2; margin-top: 2px;">
                                                ${service.duration} min | ${priceWithVat.toFixed(2)}€ 
                                                ${service.procent_vat > 0 ? `<span style="font-size: 10px;">(incl. ${service.procent_vat}% VAT)</span>` : ''}
                                            </div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="border: 1px solid #868e96; color: #868e96; padding: 1px 5px; border-radius: 4px; font-size: 11px; display: inline-block; min-width: 20px; text-align: center;" 
                                                  title="Past bookings (last 30 days)">
                                                ${service.past_bookings || 0}
                                            </span>
                                            <span style="border: 1px solid ${service.active_bookings > 0 ? '#28a745' : '#868e96'}; color: ${service.active_bookings > 0 ? '#28a745' : '#868e96'}; padding: 1px 5px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block; min-width: 20px; text-align: center;"
                                                  title="Active/Future bookings">
                                                ${service.active_bookings || 0}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        servicesHTML += '</div>';
                        servicesDisplay.innerHTML = servicesHTML;
                    } else {
                        servicesDisplay.innerHTML = '<div style="text-align: center; color: #6c757d; padding: 20px;"><em>No services assigned yet.</em></div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading services:', error);
                    servicesDisplay.innerHTML = '<div style="text-align: center; color: #dc3545; padding: 20px;">Error loading services</div>';
                });
        }
        
        function addNewService() {
            const specialistId = document.getElementById('modifySpecialistId').value;
            if (specialistId) {
                // Mark that we're coming from Modify Specialist modal
                window.serviceReturnModal = 'modifySpecialist';
                window.serviceReturnSpecialistId = specialistId;
                openAddServiceModalForSpecialist(specialistId);
            }
        }
        
        function loadSpecialistScheduleForModal(specialistId) {
            const workpointId = document.getElementById('modifyWorkpointId').value;
            
            const formData = new FormData();
            formData.append('action', 'get_schedule');
            formData.append('specialist_id', specialistId);
            formData.append('workpoint_id', workpointId);
            
            fetch('admin/modify_schedule_ajax.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const schedule = data.schedule;
                    const scheduleDisplay = document.getElementById('modifySpecialistScheduleDisplay');
                    
                    if (schedule && schedule.length > 0) {
                        // Create schedule display similar to the working schedule in the sidebar
                        const dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                        const dayNames = {
                            'monday': 'Mon',
                            'tuesday': 'Tue', 
                            'wednesday': 'Wed',
                            'thursday': 'Thu',
                            'friday': 'Fri',
                            'saturday': 'Sat',
                            'sunday': 'Sun'
                        };
                        
                        const scheduleLookup = {};
                        schedule.forEach(item => {
                            const day = item.day_of_week.toLowerCase();
                            const shifts = [];
                            
                            // Check shift 1
                            if (item.shift1_start && item.shift1_end && item.shift1_start !== '00:00:00' && item.shift1_end !== '00:00:00') {
                                const start1 = item.shift1_start.substring(0, 5);
                                const end1 = item.shift1_end.substring(0, 5);
                                shifts.push(`<span style="background-color: #ffebee; color: #d32f2f; padding: 2px 6px; border-radius: 3px; margin: 0 2px;">${start1} - ${end1}</span>`);
                            }
                            
                            // Check shift 2
                            if (item.shift2_start && item.shift2_end && item.shift2_start !== '00:00:00' && item.shift2_end !== '00:00:00') {
                                const start2 = item.shift2_start.substring(0, 5);
                                const end2 = item.shift2_end.substring(0, 5);
                                shifts.push(`<span style="background-color: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 3px; margin: 0 2px;">${start2} - ${end2}</span>`);
                            }
                            
                            // Check shift 3
                            if (item.shift3_start && item.shift3_end && item.shift3_start !== '00:00:00' && item.shift3_end !== '00:00:00') {
                                const start3 = item.shift3_start.substring(0, 5);
                                const end3 = item.shift3_end.substring(0, 5);
                                shifts.push(`<span style="background-color: #e8f5e8; color: #2e7d32; padding: 2px 6px; border-radius: 3px; margin: 0 2px;">${start3} - ${end3}</span>`);
                            }
                            
                            if (shifts.length > 0) {
                                scheduleLookup[day] = shifts.join(' ');
                            }
                        });
                        
                        const scheduleLines = [];
                        dayOrder.forEach(day => {
                            if (scheduleLookup[day]) {
                                const dayName = dayNames[day];
                                scheduleLines.push(`<div style="margin-bottom: 5px;"><strong style="display: inline-block; width: 35px; text-align: left;">${dayName}:</strong> ${scheduleLookup[day]}</div>`);
                            }
                        });
                        
                        scheduleDisplay.innerHTML = scheduleLines.join('');
                    } else {
                        scheduleDisplay.innerHTML = '<em style="color: #6c757d;">No schedule found for this specialist at this working point.</em>';
                    }
                } else {
                    document.getElementById('modifySpecialistScheduleDisplay').innerHTML = '<em style="color: #dc3545;">Failed to load schedule data.</em>';
                }
            })
            .catch(error => {
                console.error('Error loading schedule:', error);
                document.getElementById('modifySpecialistScheduleDisplay').innerHTML = '<em style="color: #dc3545;">Error loading schedule data.</em>';
            });
        }
        
        function deleteSpecialistSchedule() {
            const specialistId = document.getElementById('modifySpecialistId').value;
            const workpointId = document.getElementById('modifyWorkpointId').value;
            
            if (!confirm('Are you sure you want to delete the schedule for this specialist at this working point? This action cannot be undone.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_schedule');
            formData.append('specialist_id', specialistId);
            formData.append('workpoint_id', workpointId);
            
            fetch('admin/modify_schedule_ajax.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reflect changes in left panel and orphan dropdown
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete schedule.'));
                }
            })
            .catch(error => {
                console.error('Error deleting schedule:', error);
                alert('An error occurred while deleting the schedule.');
            });
        }
        
        function updateSpecialistDetails() {
            const formData = new FormData(document.getElementById('modifySpecialistForm'));
            formData.append('action', 'update_specialist');
            
            // Disable submit button
            const submitBtn = event.target;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Updating...';
            submitBtn.disabled = true;
            
            fetch('admin/modify_specialist_details.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Specialist updated successfully!');
                    closeModifySpecialistModal();
                    // Reload the page to show updated data
                    location.reload();
                } else {
                    document.getElementById('modifySpecialistError').textContent = data.message || 'Failed to update specialist.';
                    document.getElementById('modifySpecialistError').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error updating specialist:', error);
                document.getElementById('modifySpecialistError').textContent = 'An error occurred while updating the specialist.';
                document.getElementById('modifySpecialistError').style.display = 'block';
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }
        
        function deleteSpecialistFromModal() {
            const specialistId = document.getElementById('modifySpecialistId').value;
            const specialistName = document.getElementById('modifySpecialistNameField').value;
            
            // Show delete confirmation modal
            document.getElementById('deleteSpecialistConfirmName').textContent = specialistName;
            document.getElementById('deleteSpecialistConfirmModal').style.display = 'flex';
            document.getElementById('deleteSpecialistConfirmError').style.display = 'none';
            document.getElementById('deleteSpecialistConfirmPassword').value = '';
        }
        
        function closeDeleteSpecialistConfirmModal() {
            document.getElementById('deleteSpecialistConfirmModal').style.display = 'none';
            document.getElementById('deleteSpecialistConfirmForm').reset();
            document.getElementById('deleteSpecialistConfirmError').style.display = 'none';
        }
        
        function confirmDeleteSpecialistFromModal() {
            const specialistId = document.getElementById('modifySpecialistId').value;
            const password = document.getElementById('deleteSpecialistConfirmPassword').value;
            
            if (!password) {
                document.getElementById('deleteSpecialistConfirmError').textContent = 'Please enter your password to confirm deletion.';
                document.getElementById('deleteSpecialistConfirmError').style.display = 'block';
                return;
            }
            
            const btn = document.getElementById('confirmDeleteSpecialistBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Deleting...';
            btn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'delete_specialist');
            formData.append('specialist_id', specialistId);
            formData.append('password', password);
            
            fetch('admin/modify_specialist_details.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Specialist deleted successfully!');
                    closeDeleteSpecialistConfirmModal();
                    closeModifySpecialistModal();
                    // Reload the page to reflect changes
                    location.reload();
                } else {
                    document.getElementById('deleteSpecialistConfirmError').textContent = data.message || 'Failed to delete specialist.';
                    document.getElementById('deleteSpecialistConfirmError').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error deleting specialist:', error);
                document.getElementById('deleteSpecialistConfirmError').textContent = 'An error occurred while deleting the specialist.';
                document.getElementById('deleteSpecialistConfirmError').style.display = 'block';
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        function modifySpecialistSchedule() {
            const specialistId = document.getElementById('modifySpecialistId').value;
            const workpointId = document.getElementById('modifyWorkpointId').value;
            
            // Close the modify specialist modal first
            closeModifySpecialistModal();
            
            // Open the schedule modification modal
            openModifyScheduleModal(specialistId, workpointId);
        }
        
        // Schedule Modification Modal Functions
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
            
            // Show modal
            modal.style.display = 'flex';
        }
        
        function closeModifyScheduleModal() {
            document.getElementById('modifyScheduleModal').style.display = 'none';
            document.getElementById('modifyScheduleForm').reset();
            document.getElementById('modifyScheduleError').style.display = 'none';
        }

        function toggleShiftVisibility(shiftNumber, isVisible) {
            const table = document.querySelector('#modifyScheduleModal .schedule-editor-table');
            if (!table) return;

            const display = isVisible ? '' : 'none';
            const flexDisplay = isVisible ? 'flex' : 'none';

            // Toggle Quick Options section shifts
            if (shiftNumber === 1) {
                const quickOptionsShift1 = document.getElementById('quickOptionsShift1');
                if (quickOptionsShift1) quickOptionsShift1.style.display = flexDisplay;
            } else if (shiftNumber === 2) {
                const quickOptionsShift2 = document.getElementById('quickOptionsShift2');
                if (quickOptionsShift2) quickOptionsShift2.style.display = flexDisplay;
            } else if (shiftNumber === 3) {
                const quickOptionsShift3 = document.getElementById('quickOptionsShift3');
                if (quickOptionsShift3) quickOptionsShift3.style.display = flexDisplay;
            }

            // Toggle header columns
            const headerRows = table.querySelectorAll('thead tr');
            if (shiftNumber === 1) {
                // First header row - Shift 1 title (columns 2-4)
                if (headerRows[0]) {
                    const shift1Header = headerRows[0].cells[1]; // "Shift 1" header
                    if (shift1Header) shift1Header.style.display = display;
                }
                // Second header row - Start/End/checkbox columns (2-4)
                if (headerRows[1]) {
                    for (let i = 1; i <= 3; i++) {
                        if (headerRows[1].cells[i]) headerRows[1].cells[i].style.display = display;
                    }
                }
                // Separator column after shift 1
                if (headerRows[0].cells[2]) headerRows[0].cells[2].style.display = display;
                if (headerRows[1].cells[4]) headerRows[1].cells[4].style.display = display;
            } else if (shiftNumber === 2) {
                // First header row - Shift 2 title (columns 6-8)
                if (headerRows[0]) {
                    const shift2Header = headerRows[0].cells[3]; // "Shift 2" header
                    if (shift2Header) shift2Header.style.display = display;
                }
                // Second header row - Start/End/checkbox columns (6-8)
                if (headerRows[1]) {
                    for (let i = 5; i <= 7; i++) {
                        if (headerRows[1].cells[i]) headerRows[1].cells[i].style.display = display;
                    }
                }
                // Separator column after shift 2
                if (headerRows[0].cells[4]) headerRows[0].cells[4].style.display = display;
                if (headerRows[1].cells[8]) headerRows[1].cells[8].style.display = display;
            } else if (shiftNumber === 3) {
                // First header row - Shift 3 title (columns 10-12)
                if (headerRows[0]) {
                    const shift3Header = headerRows[0].cells[5]; // "Shift 3" header
                    if (shift3Header) shift3Header.style.display = display;
                }
                // Second header row - Start/End/checkbox columns (10-12)
                if (headerRows[1]) {
                    for (let i = 9; i <= 11; i++) {
                        if (headerRows[1].cells[i]) headerRows[1].cells[i].style.display = display;
                    }
                }
            }

            // Toggle body columns for all days
            const bodyRows = table.querySelectorAll('tbody tr');
            bodyRows.forEach(row => {
                if (shiftNumber === 1) {
                    // Shift 1 columns (2-4: start, end, clear)
                    for (let i = 1; i <= 3; i++) {
                        if (row.cells[i]) row.cells[i].style.display = display;
                    }
                    // Separator column after shift 1
                    if (row.cells[4]) row.cells[4].style.display = display;
                } else if (shiftNumber === 2) {
                    // Shift 2 columns (6-8: start, end, clear)
                    for (let i = 5; i <= 7; i++) {
                        if (row.cells[i]) row.cells[i].style.display = display;
                    }
                    // Separator column after shift 2
                    if (row.cells[8]) row.cells[8].style.display = display;
                } else if (shiftNumber === 3) {
                    // Shift 3 columns (10-12: start, end, clear)
                    for (let i = 9; i <= 11; i++) {
                        if (row.cells[i]) row.cells[i].style.display = display;
                    }
                }
            });
        }

        function deleteScheduleFromModal() {
            const specialistName = document.getElementById('modifyScheduleTitle').textContent.match(/Schedule: (.+?) at/)?.[1] || 'this specialist';
            const workpointName = document.getElementById('modifyScheduleTitle').textContent.match(/at (.+)$/)?.[1] || 'this location';
            
            if (confirm(`Are you sure you want to delete the schedule for ${specialistName} at ${workpointName}? This action cannot be undone.`)) {
                const formData = new FormData();
                formData.append('action', 'delete_schedule');
                formData.append('specialist_id', document.getElementById('modifyScheduleSpecialistId').value);
                formData.append('workpoint_id', document.getElementById('modifyScheduleWorkpointId').value);
                formData.append('supervisor_mode', 'true');
                
                fetch('admin/modify_schedule_ajax.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Schedule deleted successfully!');
                        closeModifyScheduleModal();
                        location.reload();
                    } else {
                        document.getElementById('modifyScheduleError').textContent = data.message || 'Failed to delete schedule.';
                        document.getElementById('modifyScheduleError').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error deleting schedule:', error);
                    document.getElementById('modifyScheduleError').textContent = 'An error occurred while deleting the schedule.';
                    document.getElementById('modifyScheduleError').style.display = 'block';
                });
            }
        }
        
        // Time Off Modal Functions
        let currentTimeOffYear = new Date().getFullYear();
        let selectedTimeOffDates = new Set();
        let timeOffDetails = {}; // Stores { date: { type: 'full'|'partial', workStart: 'HH:MM', workEnd: 'HH:MM' } }
        let timeOffSpecialistId = null;
        let timeOffWorkpointId = null;
        
        window.bookedDates = new Set();
        window.bookingCounts = {};
        
        function openTimeOffModal() {
            // Get specialist and workpoint IDs from the modify schedule modal
            timeOffSpecialistId = document.getElementById('modifyScheduleSpecialistId').value;
            timeOffWorkpointId = document.getElementById('modifyScheduleWorkpointId').value;
            
            // Get specialist name from title - look for the text after "Modify Schedule: "
            const titleText = document.getElementById('modifyScheduleTitle').textContent;
            let specialistName = 'Unknown';
            let workpointName = 'Unknown';
            
            // Try different patterns to extract the names
            const match1 = titleText.match(/Editing: (.+?) at (.+)$/);
            const match2 = titleText.match(/Modify Schedule: (.+?) at (.+)$/);
            const match3 = titleText.match(/Schedule: (.+?) at (.+)$/);
            
            if (match1) {
                specialistName = match1[1];
                workpointName = match1[2];
            } else if (match2) {
                specialistName = match2[1];
                workpointName = match2[2];
            } else if (match3) {
                specialistName = match3[1];
                workpointName = match3[2];
            }
            
            // Debug log
            console.log('Title text:', titleText);
            console.log('Specialist:', specialistName, 'Location:', workpointName);
            console.log('Specialist ID:', timeOffSpecialistId, 'Workpoint ID:', timeOffWorkpointId);
            
            // Update info display
            document.getElementById('specialistTimeOffInfo').innerHTML = `
                <strong>Specialist:</strong> ${specialistName} <br>
                <strong>Location:</strong> ${workpointName}
            `;
            
            // Set current year
            currentTimeOffYear = new Date().getFullYear();
            document.getElementById('timeOffYear').textContent = currentTimeOffYear;
            
            // Load booked dates first, then time off dates
            loadBookedDates(() => {
                loadTimeOffDates();
            });
            
            // Show modal
            document.getElementById('timeOffModal').style.display = 'flex';
        }
        
        function loadBookedDates(callback) {
            // Load dates with existing bookings for this specialist
            console.log('Loading booked dates for specialist:', timeOffSpecialistId, 'year:', currentTimeOffYear);
            
            const formData = new FormData();
            formData.append('action', 'get_booked_dates');
            formData.append('specialist_id', timeOffSpecialistId);
            formData.append('year', currentTimeOffYear);
            formData.append('supervisor_mode', 'true');
            
            fetch('admin/get_specialist_bookings_ajax.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                console.log('Booked dates response:', data);
                if (data.success && data.dates) {
                    window.bookedDates.clear();
                    window.bookingCounts = {};
                    data.dates.forEach(item => {
                        if (typeof item === 'string') {
                            // Legacy format - just date
                            window.bookedDates.add(item);
                            window.bookingCounts[item] = 1;
                        } else {
                            // New format with count
                            window.bookedDates.add(item.date);
                            window.bookingCounts[item.date] = item.count;
                        }
                    });
                    console.log('Booked dates loaded:', window.bookedDates.size, 'dates');
                    console.log('Booked dates Set:', Array.from(window.bookedDates));
                }
                if (callback) callback();
            })
            .catch(error => {
                console.error('Error loading booked dates:', error);
                if (callback) callback();
            });
        }
        
        function closeTimeOffModal() {
            document.getElementById('timeOffModal').style.display = 'none';
            selectedTimeOffDates.clear();
        }
        
        function changeTimeOffYear(direction) {
            currentTimeOffYear += direction;
            document.getElementById('timeOffYear').textContent = currentTimeOffYear;
            // Reload booked dates for the new year
            loadBookedDates(() => {
                generateTimeOffCalendar();
            });
        }
        
        function generateTimeOffCalendar() {
            const grid = document.getElementById('timeOffCalendarGrid');
            grid.innerHTML = '';
            
            console.log('Generating calendar. Booked dates:', window.bookedDates);
            console.log('Booking counts:', window.bookingCounts);
            
            // Get today's date
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
                monthDiv.style.cssText = 'background: white; border: 1px solid #ddd; border-radius: 3px; padding: 6px; transform: scale(0.75); transform-origin: top left; margin-bottom: -50px; margin-right: -70px;';
                
                // Month header with year and month number
                const monthHeader = document.createElement('div');
                monthHeader.style.cssText = 'text-align: center; font-weight: bold; margin-bottom: 5px; color: #333; font-size: 13px;';
                const monthNum = monthIndex + 1;
                const monthOrdinal = monthNum === 1 ? 'st' : monthNum === 2 ? 'nd' : monthNum === 3 ? 'rd' : 'th';
                monthHeader.textContent = `${year} ${monthNames[monthIndex]}`;
                monthDiv.appendChild(monthHeader);
                
                // Days grid
                const daysGrid = document.createElement('div');
                daysGrid.style.cssText = 'display: grid; grid-template-columns: repeat(7, 1fr); gap: 0px; font-size: 11px;';
                
                // Day headers - Monday first, weekends in red
                const dayHeaders = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
                dayHeaders.forEach((day, index) => {
                    const dayHeader = document.createElement('div');
                    const isWeekend = index >= 5;
                    dayHeader.style.cssText = `text-align: center; font-weight: bold; color: ${isWeekend ? '#dc3545' : '#666'}; padding: 2px; font-size: 10px;`;
                    dayHeader.textContent = day;
                    daysGrid.appendChild(dayHeader);
                });
                
                // Get first day of month and number of days
                let firstDay = new Date(year, monthIndex, 1).getDay();
                // Adjust for Monday as first day (0 = Sunday, so convert to Monday = 0)
                firstDay = firstDay === 0 ? 6 : firstDay - 1;
                const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
                
                // Empty cells for days before month starts
                for (let i = 0; i < firstDay; i++) {
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
                    
                    // Check if this date has bookings (will be populated by loadBookedDates)
                    const hasBookings = window.bookedDates && window.bookedDates.has(dateStr);
                    const bookingCount = window.bookingCounts && window.bookingCounts[dateStr] || 0;
                    
                    // Debug log for dates with bookings
                    if (hasBookings) {
                        console.log('Date has bookings:', dateStr, 'count:', bookingCount);
                    }
                    
                    // Base styling
                    let bgColor = '#fff';
                    let textColor = isWeekend ? '#dc3545' : '#333';
                    let cursor = 'pointer';
                    
                    if (hasBookings) {
                        bgColor = '#d6d8db';
                        textColor = '#6c757d';
                        cursor = 'not-allowed';
                    } else if (selectedTimeOffDates.has(dateStr)) {
                        bgColor = '#dc3545';
                        textColor = '#fff';
                    } else if (isToday) {
                        bgColor = '#007bff';
                        textColor = '#fff';
                    }
                    
                    dayCell.style.cssText = `
                        text-align: center; 
                        padding: 6px 4px; 
                        cursor: ${cursor}; 
                        border: none;
                        background: ${bgColor};
                        color: ${textColor};
                        transition: all 0.2s;
                        font-size: 12px;
                        width: 28px;
                        height: 28px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border-radius: 50%;
                        margin: 2px auto;
                    `;
                    
                    dayCell.textContent = day;
                    dayCell.dataset.date = dateStr;
                    
                    // Set tooltip
                    if (hasBookings) {
                        dayCell.title = `${bookingCount} booking${bookingCount > 1 ? 's' : ''} (bookings need to be canceled before selecting this day off)`;
                    } else if (selectedTimeOffDates.has(dateStr)) {
                        dayCell.title = 'Day off';
                    } else if (isToday) {
                        dayCell.title = 'Today';
                    } else {
                        dayCell.title = '';
                    }
                    
                    // Click handler - only if no bookings
                    if (!hasBookings) {
                        dayCell.onclick = function() {
                            toggleTimeOffDate(dateStr, this);
                        };
                        
                        // Hover effect
                        dayCell.onmouseover = function() {
                            if (!selectedTimeOffDates.has(dateStr) && !isToday && !hasBookings) {
                                this.style.background = '#f0f0f0';
                            }
                        };
                        dayCell.onmouseout = function() {
                            if (!selectedTimeOffDates.has(dateStr) && !isToday && !hasBookings) {
                                this.style.background = '#fff';
                            } else if (isToday && !selectedTimeOffDates.has(dateStr)) {
                                this.style.background = '#007bff';
                            } else if (hasBookings && !selectedTimeOffDates.has(dateStr)) {
                                this.style.background = '#d6d8db';
                            }
                        };
                    }
                    
                    daysGrid.appendChild(dayCell);
                }
                
                monthDiv.appendChild(daysGrid);
                grid.appendChild(monthDiv);
            }
        }
        
        function toggleTimeOffDate(dateStr, element) {
            const today = new Date();
            const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            const isToday = dateStr === todayStr;
            
            // Check if weekend
            const [year, month, day] = dateStr.split('-').map(Number);
            const dayOfWeek = new Date(year, month - 1, day).getDay();
            const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
            
            if (selectedTimeOffDates.has(dateStr)) {
                selectedTimeOffDates.delete(dateStr);
                delete timeOffDetails[dateStr];
                autoSaveRemoveDayOff(dateStr);
                if (isToday) {
                    element.style.background = '#007bff';
                    element.style.color = '#fff';
                    element.title = 'Today';
                } else {
                    element.style.background = '#fff';
                    element.style.color = isWeekend ? '#dc3545' : '#333';
                    element.title = '';
                }
            } else {
                selectedTimeOffDates.add(dateStr);
                timeOffDetails[dateStr] = { type: 'full' };
                autoSaveAddFullDayOff(dateStr);
                element.style.background = '#dc3545';
                element.style.color = '#fff';
                element.title = 'Day off';
            }
            updateSelectedDaysList();
        }
        
        function updateSelectedDaysList() {
            const listDiv = document.getElementById('selectedDaysOffList');
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            // Filter only future dates
            const datesArray = Array.from(selectedTimeOffDates)
                .filter(date => new Date(date + 'T12:00:00') >= today)
                .sort();

            if (datesArray.length === 0) {
                listDiv.innerHTML = '<em style="color: #999;">No future days off selected</em>';
            } else {
                listDiv.innerHTML = datesArray.map(date => {
                    const d = new Date(date + 'T12:00:00');
                    const dayOffData = timeOffDetails[date] || { type: 'full' };
                    const isPartial = dayOffData.type === 'partial';
                    const dropdownId = `dropdown-${date}`;

                    return `<div style="margin: 4px 0;">
                        <div onclick="toggleDayOffDropdown('${date}')"
                             style="display: flex; align-items: center; justify-content: space-between; padding: 6px 8px; background: #dc3545; color: white; border-radius: 3px; cursor: pointer; white-space: nowrap;">
                            <span style="font-size: 12px; font-weight: 500;">
                                ${d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })}
                            </span>
                            <span onclick="event.stopPropagation(); removeDayOff('${date}')"
                                  style="color: white; cursor: pointer; font-size: 18px; font-weight: bold; padding: 0 2px; margin-left: 4px;"
                                  title="Remove">
                                ×
                            </span>
                        </div>
                        <div id="${dropdownId}" style="display: none; background: #f8f9fa; border: 1px solid #ddd; border-radius: 3px; padding: 8px; margin-top: 2px; font-size: 11px;">
                            <div style="margin-bottom: 6px;">
                                <label style="display: block; margin-bottom: 4px; font-weight: 600;">Type:</label>
                                <select onchange="updateDayOffType('${date}', this.value)" style="width: 100%; padding: 4px; border: 1px solid #ddd; border-radius: 2px; font-size: 11px;">
                                    <option value="full" ${!isPartial ? 'selected' : ''}>Fully Off</option>
                                    <option value="partial" ${isPartial ? 'selected' : ''}>Partially Off</option>
                                </select>
                            </div>
                            <div id="partial-${date}" style="display: ${isPartial ? 'block' : 'none'};">
                                <label style="display: block; margin-bottom: 4px; font-weight: 600;">Working Hours:</label>
                                <div style="display: flex; gap: 4px; align-items: center;">
                                    <input type="time" id="start-${date}" value="${dayOffData.workStart || ''}"
                                           onchange="updateWorkingHours('${date}')"
                                           style="flex: 1; padding: 4px; border: 1px solid #ddd; border-radius: 2px; font-size: 11px;"
                                           placeholder="From">
                                    <span>to</span>
                                    <input type="time" id="end-${date}" value="${dayOffData.workEnd || ''}"
                                           onchange="updateWorkingHours('${date}')"
                                           style="flex: 1; padding: 4px; border: 1px solid #ddd; border-radius: 2px; font-size: 11px;"
                                           placeholder="Until">
                                </div>
                            </div>
                        </div>
                    </div>`;
                }).join('');
            }
        }
        
        function removeDayOff(dateStr) {
            selectedTimeOffDates.delete(dateStr);
            delete timeOffDetails[dateStr];
            generateTimeOffCalendar();
            updateSelectedDaysList();
        }

        function toggleDayOffDropdown(date) {
            const dropdown = document.getElementById(`dropdown-${date}`);
            if (dropdown) {
                const isVisible = dropdown.style.display !== 'none';
                // Close all other dropdowns first
                document.querySelectorAll('[id^="dropdown-"]').forEach(dd => {
                    if (dd.id !== `dropdown-${date}`) {
                        dd.style.display = 'none';
                    }
                });
                dropdown.style.display = isVisible ? 'none' : 'block';
            }
        }

        function updateDayOffType(date, type) {
            if (!timeOffDetails[date]) {
                timeOffDetails[date] = {};
            }
            timeOffDetails[date].type = type;

            // Show/hide partial fields
            const partialDiv = document.getElementById(`partial-${date}`);
            if (partialDiv) {
                partialDiv.style.display = type === 'partial' ? 'block' : 'none';
            }

            // Auto-save the type change
            if (type === 'full') {
                timeOffDetails[date].workStart = null;
                timeOffDetails[date].workEnd = null;
                autoSaveConvertToFull(date);
            } else if (type === 'partial') {
                autoSaveConvertToPartial(date);
            }
        }

        function updateWorkingHours(date) {
            const startInput = document.getElementById(`start-${date}`);
            const endInput = document.getElementById(`end-${date}`);

            if (!timeOffDetails[date]) {
                timeOffDetails[date] = { type: 'partial' };
            }

            const workStart = startInput ? startInput.value : null;
            const workEnd = endInput ? endInput.value : null;

            timeOffDetails[date].workStart = workStart;
            timeOffDetails[date].workEnd = workEnd;

            // Auto-save working hours if both are filled
            if (workStart && workEnd) {
                autoSaveUpdateWorkingHours(date, workStart, workEnd);
            }
        }

        function clearAllTimeOff() {
            if (confirm('Are you sure you want to clear all selected days off?')) {
                selectedTimeOffDates.clear();
                timeOffDetails = {};
                generateTimeOffCalendar();
                updateSelectedDaysList();
            }
        }
        
        function loadTimeOffDates() {
            // Load existing time off dates and details from database
            const formData = new FormData();
            formData.append('action', 'get_time_off_details');
            formData.append('specialist_id', timeOffSpecialistId);
            formData.append('supervisor_mode', 'true');

            fetch('admin/specialist_time_off_auto_ajax.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    selectedTimeOffDates.clear();
                    timeOffDetails = {};

                    if (data.dates) {
                        data.dates.forEach(date => selectedTimeOffDates.add(date));
                    }

                    if (data.details) {
                        timeOffDetails = data.details;
                    }

                    generateTimeOffCalendar();
                    updateSelectedDaysList();
                }
            })
            .catch(error => {
                console.error('Error loading time off dates:', error);
            });
        }
        
        function saveTimeOff() {
            const formData = new FormData();
            formData.append('action', 'save_time_off');
            formData.append('specialist_id', timeOffSpecialistId);
            formData.append('dates', JSON.stringify(Array.from(selectedTimeOffDates)));
            formData.append('details', JSON.stringify(timeOffDetails));
            formData.append('supervisor_mode', 'true');
            
            fetch('admin/specialist_time_off_ajax.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Days off saved successfully!');
                    closeTimeOffModal();
                } else {
                    alert('Failed to save days off: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error saving time off:', error);
                alert('An error occurred while saving days off.');
            });
        }

        // Auto-save functions
        function autoSaveAddFullDayOff(date) {
            const formData = new FormData();
            formData.append('action', 'add_full_day');
            formData.append('specialist_id', timeOffSpecialistId);
            formData.append('date', date);
            formData.append('supervisor_mode', 'true');

            fetch('admin/specialist_time_off_auto_ajax.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Auto-save add failed:', data.message);
                }
            })
            .catch(error => console.error('Auto-save error:', error));
        }

        function autoSaveRemoveDayOff(date) {
            const formData = new FormData();
            formData.append('action', 'remove_day');
            formData.append('specialist_id', timeOffSpecialistId);
            formData.append('date', date);
            formData.append('supervisor_mode', 'true');

            fetch('admin/specialist_time_off_auto_ajax.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Auto-save remove failed:', data.message);
                }
            })
            .catch(error => console.error('Auto-save error:', error));
        }

        function autoSaveConvertToPartial(date) {
            const formData = new FormData();
            formData.append('action', 'convert_to_partial');
            formData.append('specialist_id', timeOffSpecialistId);
            formData.append('date', date);
            formData.append('supervisor_mode', 'true');

            fetch('admin/specialist_time_off_auto_ajax.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Auto-save convert to partial failed:', data.message);
                }
            })
            .catch(error => console.error('Auto-save error:', error));
        }

        function autoSaveConvertToFull(date) {
            const formData = new FormData();
            formData.append('action', 'convert_to_full');
            formData.append('specialist_id', timeOffSpecialistId);
            formData.append('date', date);
            formData.append('supervisor_mode', 'true');

            fetch('admin/specialist_time_off_auto_ajax.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Auto-save convert to full failed:', data.message);
                }
            })
            .catch(error => console.error('Auto-save error:', error));
        }

        function autoSaveUpdateWorkingHours(date, workStart, workEnd) {
            const formData = new FormData();
            formData.append('action', 'update_working_hours');
            formData.append('specialist_id', timeOffSpecialistId);
            formData.append('date', date);
            formData.append('work_start', workStart);
            formData.append('work_end', workEnd);
            formData.append('supervisor_mode', 'true');

            fetch('admin/specialist_time_off_auto_ajax.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Auto-save working hours failed:', data.message);
                } else {
                    console.log('Working hours saved:', data.message);
                }
            })
            .catch(error => console.error('Auto-save error:', error));
        }

                function loadScheduleDataForModal(specialistId, workpointId) {
            const formData = new FormData();
            formData.append('action', 'get_schedule');
            formData.append('specialist_id', specialistId);
            formData.append('workpoint_id', workpointId);
            
            // Add supervisor mode flag if in supervisor mode
            <?php if ($supervisor_mode): ?>
            formData.append('supervisor_mode', 'true');
            <?php endif; ?>
            
            fetch('admin/modify_schedule_ajax.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const details = data.details;
                    const schedule = data.schedule;
                    
                    // Update modal title with specialist and workpoint info
                    const titleElement = document.getElementById('modifyScheduleTitle');
                    if (titleElement) {
                        titleElement.innerHTML = `<i class="fas fa-calendar-alt" style="margin-right: 10px;"></i>Comprehensive Schedule Editor<br><span style="font-size: 0.9rem; font-weight: 400; opacity: 0.9;">Editing: ${details.specialist_name} at ${details.workpoint_name}</span>`;
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
                body: formData,
                credentials: 'same-origin'
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
                    <td>
                        <input type="time" class="form-control shift1-start-time modify-shift1-start-time" name="modify_shift1_start_${dayLower}" value="" placeholder="--:--" data-day="${day}" data-shift="1">
                    </td>
                    <td>
                        <input type="time" class="form-control shift1-end-time modify-shift1-end-time" name="modify_shift1_end_${dayLower}" value="" placeholder="--:--" data-day="${day}" data-shift="1">
                    </td>
                    <td>
                        <button type="button" class="btn-clear-shift" onclick="clearModifyShift(this, 1)">Clear</button>
                    </td>
                    <td class="separator-col"></td>
                    <td>
                        <input type="time" class="form-control shift2-start-time modify-shift2-start-time" name="modify_shift2_start_${dayLower}" value="" placeholder="--:--" data-day="${day}" data-shift="2">
                    </td>
                    <td>
                        <input type="time" class="form-control shift2-end-time modify-shift2-end-time" name="modify_shift2_end_${dayLower}" value="" placeholder="--:--" data-day="${day}" data-shift="2">
                    </td>
                    <td>
                        <button type="button" class="btn-clear-shift" onclick="clearModifyShift(this, 2)">Clear</button>
                    </td>
                    <td class="separator-col"></td>
                    <td>
                        <input type="time" class="form-control shift3-start-time modify-shift3-start-time" name="modify_shift3_start_${dayLower}" value="" placeholder="--:--" data-day="${day}" data-shift="3">
                    </td>
                    <td>
                        <input type="time" class="form-control shift3-end-time modify-shift3-end-time" name="modify_shift3_end_${dayLower}" value="" placeholder="--:--" data-day="${day}" data-shift="3">
                    </td>
                    <td>
                        <button type="button" class="btn-clear-shift" onclick="clearModifyShift(this, 3)">Clear</button>
                    </td>
                `;
                tableBody.appendChild(row);
            });
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modifyModal = document.getElementById('modifySpecialistModal');
            const deleteModal = document.getElementById('deleteSpecialistConfirmModal');
            const addModal = document.getElementById('addSpecialistModal');
            const modifyScheduleModal = document.getElementById('modifyScheduleModal');
            
            if (event.target === modifyModal) {
                closeModifySpecialistModal();
            }
            if (event.target === deleteModal) {
                closeDeleteSpecialistConfirmModal();
            }
            if (event.target === addModal) {
                closeAddSpecialistModal();
            }
            if (event.target === modifyScheduleModal) {
                closeModifyScheduleModal();
            }
        }

        // Color Picker Functions
        function openColorPickerModal(specialistId, specialistName, currentBackColor, currentForeColor) {
            document.getElementById('colorSpecialistId').value = specialistId;
            document.getElementById('colorSpecialistName').value = specialistName;
            document.getElementById('colorSpecialistNameDisplay').textContent = specialistName;
            
            // Set current colors
            document.getElementById('backColorPicker').value = currentBackColor;
            document.getElementById('foreColorPicker').value = currentForeColor;
            
            // Update preview
            updateColorPreview(currentBackColor, currentForeColor);
            generateColorVariations(currentBackColor);
            
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
                    // Reload the page to show updated colors
                    window.location.reload();
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

        // Add event listeners for color picker inputs
        document.addEventListener('DOMContentLoaded', function() {
            const backColorPicker = document.getElementById('backColorPicker');
            const foreColorPicker = document.getElementById('foreColorPicker');
            
            if (backColorPicker) {
                backColorPicker.addEventListener('input', function() {
                    updateColorPreview(this.value, foreColorPicker.value);
                    generateColorVariations(this.value);
                });
            }
            
            if (foreColorPicker) {
                foreColorPicker.addEventListener('input', function() {
                    updateColorPreview(backColorPicker.value, this.value);
                });
            }
        });
    </script>
    
    <!-- Modify Specialist Modal -->
    <div id="modifySpecialistModal" class="modify-modal-overlay">
        <div class="modify-modal">
            <div class="modify-modal-header">
                <h3>👥 Modify Specialist Details</h3>
                <span class="modify-modal-close" onclick="closeModifySpecialistModal()">&times;</span>
            </div>
            <div class="modify-modal-body">
                <div class="org-name-row" style="height: 3px;"></div>
                
                <form id="modifySpecialistForm">
                    <input type="hidden" id="modifySpecialistId" name="specialist_id">
                    <input type="hidden" id="modifyWorkpointId" name="workpoint_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modifySpecialistNameField">Full Name *</label>
                            <input type="text" id="modifySpecialistNameField" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="modifySpecialistSpeciality">Speciality *</label>
                            <input type="text" id="modifySpecialistSpeciality" name="speciality" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modifySpecialistEmail">Email</label>
                            <input type="email" id="modifySpecialistEmail" name="email" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="modifySpecialistPhone">Phone</label>
                            <input type="text" id="modifySpecialistPhone" name="phone_nr" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modifySpecialistUser">Username *</label>
                            <input type="text" id="modifySpecialistUser" name="user" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="modifySpecialistPassword">Password</label>
                            <input type="password" id="modifySpecialistPassword" name="password" class="form-control" placeholder="Leave blank to keep current password">
                        </div>
                    </div>
                    
                    <div class="form-row" id="emailScheduleContainer" style="display: none;">
                        <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                            <label for="modifySpecialistEmailHour" style="margin-bottom: 0; white-space: nowrap;">Email Schedule Hour *</label>
                            <input type="number" id="modifySpecialistEmailHour" name="h_of_email_schedule" min="0" max="23" value="9" required class="form-control" style="width: 80px;">
                        </div>
                        <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                            <label for="modifySpecialistEmailMinute" style="margin-bottom: 0; white-space: nowrap;">Email Schedule Minute *</label>
                            <input type="number" id="modifySpecialistEmailMinute" name="m_of_email_schedule" min="0" max="59" value="0" required class="form-control" style="width: 80px;">
                        </div>
                    </div>
                    
                    <div id="modifySpecialistError" class="modify-error" style="display: none;"></div>
                    
                    <!-- Specialist Services Display -->
                    <div class="specialist-services-display" style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px; border: 1px solid #dee2e6;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h5 style="margin: 0; color: #495057; font-size: 14px;">
                                <i class="fas fa-list"></i> Services
                            </h5>
                            <button type="button" class="btn btn-sm" 
                                    onclick="addNewService()" 
                                    style="font-size: 11px; padding: 2px 8px; background-color: white; color: #333; border: 1px solid #ddd; transition: all 0.2s ease; cursor: pointer;"
                                    onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)'; this.style.transform='translateY(-1px)';"
                                    onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';">
                                <i class="fas fa-plus" style="font-size: 10px;"></i> Add new services
                            </button>
                        </div>
                        <div id="modifySpecialistServicesDisplay" style="font-size: 12px;">
                            <!-- Services will be loaded here -->
                        </div>
                    </div>
                    
                    <div class="modify-modal-buttons" style="margin-top: 20px;">
                        <div class="button-row">
                            <button type="button" class="btn-modify" onclick="deleteSpecialistFromModal()" 
                                    style="font-size: 12px; padding: 6px 12px; background: #dc3545; border-color: #dc3545;" 
                                    title="This will DELETE completely the Specialist from the Company and from any other Working points">
                                <i class="fas fa-times" style="font-size: 11px;"></i> Delete Specialist
                            </button>
                            <button type="button" class="btn-modify" onclick="updateSpecialistDetails()"
                                    style="font-size: 12px; padding: 6px 12px;">
                                Modify Details
                            </button>
                        </div>
                    </div>
                    
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Specialist Confirmation Modal -->
    <div id="deleteSpecialistConfirmModal" class="delete-modal-overlay">
        <div class="delete-modal">
            <div class="delete-modal-header">
                <h3>❌ DELETE SPECIALIST</h3>
            </div>
            <div class="delete-modal-body">
                <div class="org-name-row">
                    <span class="delete-icon-inline">❌</span>
                    <div class="org-name-large" id="deleteSpecialistConfirmName"></div>
                </div>
                
                <div class="warning-text">
                    ⚠️ WARNING: This action will permanently delete this specialist!
                </div>
                
                <div class="dependencies-list">
                    <strong>The following will be DELETED:</strong>
                    <ul>
                        <li>• All specialist details and profile</li>
                        <li>• All working point assignments for this specialist</li>
                        <li>• All working programs for this specialist</li>
                        <li>• All bookings associated with this specialist</li>
                    </ul>
                    <strong>Note:</strong> This action cannot be undone!
                </div>
                
                <div class="warning-text">
                    <span class="blinking-warning">❌ <span class="underlined">This action cannot be undone!</span></span>
                </div>
                
                <br><br>
                
                <form id="deleteSpecialistConfirmForm">
                    <div class="password-confirmation">
                        <div class="password-button-row">
                            <input type="password" id="deleteSpecialistConfirmPassword" class="password-input" placeholder="password to confirm" autocomplete="current-password">
                            <button class="btn-delete" id="confirmDeleteSpecialistBtn" onclick="confirmDeleteSpecialistFromModal()">
                                <i class="fas fa-times"></i> Delete Specialist
                            </button>
                        </div>
                        <div id="deleteSpecialistConfirmError" class="password-error" style="display: none; color: #dc3545; font-size: 0.9em; margin-top: 5px;"></div>
                    </div>
                    
                    <div class="delete-modal-buttons">
                        <button class="btn-cancel" onclick="closeDeleteSpecialistConfirmModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Specialist Modal -->
    <div id="addSpecialistModal" class="modify-modal-overlay">
        <div class="modify-modal">
            <div class="modify-modal-header">
                <h3>👨‍⚕️ ADD/REACTIVATE SPECIALIST</h3>
                <span class="modify-modal-close" onclick="closeAddSpecialistModal()">&times;</span>
            </div>
            <div class="modify-modal-body">
                <form id="addSpecialistForm">
                    <input type="hidden" id="workpointId">
                    <input type="hidden" id="organisationId">
                    
                    <!-- Specialist Selection -->
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="specialistSelection">Select Specialist *</label>
                        <select class="form-control" id="specialistSelection" required onchange="handleSpecialistSelection()">
                            <option value="new" selected style="color: #dc3545; font-weight: bold;">👨‍⚕️ New Specialist Registration</option>
                            <option value="" disabled>─────────── Existing Specialists ───────────</option>
                            <?php
                            // Populate existing specialists for organisation (already loaded lists may exist earlier)
                            // Reuse $specialists array if available for current org/workpoint
                            if (!empty($specialists)) {
                                foreach ($specialists as $spec) {
                                    echo '<option value="' . (int)$spec['unic_id'] . '">' . htmlspecialchars($spec['name']) . ' — ' . htmlspecialchars($spec['speciality']) . '</option>';
                                }
                            }
                            // Append unassigned specialists section
                            $stmt = $pdo->prepare("SELECT s.unic_id, s.name, s.speciality FROM specialists s WHERE s.organisation_id = ? AND NOT EXISTS (SELECT 1 FROM working_program wp WHERE wp.specialist_id = s.unic_id AND ((wp.shift1_start <> '00:00:00' AND wp.shift1_end <> '00:00:00') OR (wp.shift2_start <> '00:00:00' AND wp.shift2_end <> '00:00:00') OR (wp.shift3_start <> '00:00:00' AND wp.shift3_end <> '00:00:00'))) ORDER BY s.name");
                            $stmt->execute([$organisation['unic_id']]);
                            $org_unassigned_modal = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            if (!empty($org_unassigned_modal)) {
                                echo '<option value="" disabled>─────────── Specialists Without Program ───────────</option>';
                                foreach ($org_unassigned_modal as $us) {
                                    echo '<option value="' . (int)$us['unic_id'] . '">' . htmlspecialchars($us['name']) . ' — ' . htmlspecialchars($us['speciality']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <!-- Specialist Details -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="specialistName">Full Name *</label>
                            <input type="text" class="form-control" id="specialistName" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="specialistSpeciality">Speciality *</label>
                            <input type="text" class="form-control" id="specialistSpeciality" name="speciality" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" style="width: 50%;">
                            <label for="specialistEmail">Email *</label>
                            <input type="email" class="form-control" id="specialistEmail" name="email" required>
                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                <div style="flex: 1;">
                                    <label for="emailScheduleHour" style="font-size: 0.85rem;">Email Schedule Hour *</label>
                                    <input type="number" class="form-control" id="emailScheduleHour" name="h_of_email_schedule" min="0" max="23" value="9" required style="width: 100%;">
                                </div>
                                <div style="flex: 1;">
                                    <label for="emailScheduleMinute" style="font-size: 0.85rem;">Email Schedule Minute *</label>
                                    <input type="number" class="form-control" id="emailScheduleMinute" name="m_of_email_schedule" min="0" max="59" value="0" required style="width: 100%;">
                                </div>
                            </div>
                        </div>
                        <div class="form-group" style="width: 50%;">
                            <label for="specialistPhone">Phone Number</label>
                            <input type="text" class="form-control" id="specialistPhone" name="phone_nr">
                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                <div style="flex: 1;">
                                    <label for="specialistUser" style="font-size: 0.85rem;">Username *</label>
                                    <input type="text" class="form-control" id="specialistUser" name="user" required style="background-color: #e3f2fd;">
                                </div>
                                <div style="flex: 1;">
                                    <label for="specialistPassword" style="font-size: 0.85rem;">Password *</label>
                                    <input type="password" class="form-control" id="specialistPassword" name="password" required style="background-color: #e3f2fd;">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Working Point Assignment -->
                    <div class="form-group">
                        <label id="workpointLabel">Assign to Working Point *</label>
                        <select class="form-control" id="workpointSelect" required>
                            <option value="">Loading working points...</option>
                        </select>
                    </div>
                    
                    <!-- Individual Day Editor -->
                    <div class="individual-edit-section">
                        <h4 id="workingScheduleTitle">📋 Working Schedule</h4>
                        <div class="schedule-editor-table-container">
                            <table class="schedule-editor-table">
                                <thead>
                                    <tr>
                                        <th></th>
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
                                <tbody id="scheduleEditorTableBody">
                                    <!-- Days will be populated here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Quick Options Section -->
                    <div class="individual-edit-section">
                        <h4 style="font-size: 11px; margin-bottom: 10px;">⚡ Quick Options</h4>
                        <div class="quick-options-row">
                                    <div class="quick-option-group">
                                        <select id="quickOptionsDaySelect" class="form-control" style="font-size: 11px; width: 66px;">
                                            <option value="mondayToFriday">Mon-Fri</option>
                                            <option value="saturday">Saturday</option>
                                            <option value="sunday">Sunday</option>
                                        </select>
                                    </div>
                                    <div class="quick-option-group">
                                        <label style="font-size: 11px; margin-right: 2px; text-align: right; min-width: 50px; display: inline-block;">Shift 1:</label>
                                        <div class="time-inputs">
                                            <input type="time" id="shift1Start" class="form-control shift1-start-time" placeholder="S" style="background-color: #ffebee !important; color: #d32f2f !important;">
                                            <input type="time" id="shift1End" class="form-control shift1-end-time" placeholder="E" style="background-color: #ffebee !important; color: #d32f2f !important;">
                                        </div>
                                    </div>
                                    <div class="quick-option-group">
                                        <label style="font-size: 11px; margin-right: 2px; text-align: right; min-width: 50px; display: inline-block;">Shift 2:</label>
                                        <div class="time-inputs">
                                            <input type="time" id="shift2Start" class="form-control shift2-start-time" placeholder="S" style="background-color: #e3f2fd !important; color: #1976d2 !important;">
                                            <input type="time" id="shift2End" class="form-control shift2-end-time" placeholder="E" style="background-color: #e3f2fd !important; color: #1976d2 !important;">
                                        </div>
                                    </div>
                                    <div class="quick-option-group">
                                        <label style="font-size: 11px; margin-right: 2px; text-align: right; min-width: 50px; display: inline-block;">Shift 3:</label>
                                        <div class="time-inputs">
                                            <input type="time" id="shift3Start" class="form-control shift3-start-time" placeholder="S" style="background-color: #e8f5e8 !important; color: #2e7d32 !important;">
                                            <input type="time" id="shift3End" class="form-control shift3-end-time" placeholder="E" style="background-color: #e8f5e8 !important; color: #2e7d32 !important;">
                                        </div>
                                    </div>
                                    <div class="quick-option-group">
                                        <button type="button" onclick="applyAllShifts()" style="background: #007bff; color: white; border: none; padding: 4px 12px; border-radius: 4px; font-size: 11px; cursor: pointer;">Apply</button>
                                    </div>
                        </div>
                    </div>
                    
                    <div id="addSpecialistError" class="modify-error" style="display: none;"></div>
                    
                    <div class="modify-modal-buttons">
                        <button type="button" class="btn-modify" onclick="submitAddSpecialist()" style="font-size: 0.84rem; padding: 6px 20px;">Add Specialist</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Additional CSS overrides for Modify Schedule Modal -->
    <style>
        /* Force override for Modify Schedule Modal - placed right before modal HTML */
        #modifyScheduleModal .modify-modal {
            width: 70% !important;
            max-width: 1000px !important;
            height: auto !important;
            max-height: 90vh !important;
            margin: 5vh auto !important;
        }
        
        #modifyScheduleModal .modify-modal-body {
            padding: 25px !important;
            background-color: #f8f9fa !important;
            height: auto !important;
            overflow-y: auto !important;
        }
        
        /* Force shift colors right before modal - match specialist schedule display */
        #modifyScheduleModal .shift1-start-time,
        #modifyScheduleModal .shift1-end-time {
            background-color: #ffebee !important;
            color: #d32f2f !important;
        }
        
        #modifyScheduleModal .shift2-start-time,
        #modifyScheduleModal .shift2-end-time {
            background-color: #e3f2fd !important;
            color: #1976d2 !important;
        }
        
        #modifyScheduleModal .shift3-start-time,
        #modifyScheduleModal .shift3-end-time {
            background-color: #e8f5e8 !important;
            color: #2e7d32 !important;
        }
    </style>

    <!-- Modify Schedule Modal -->
    <div id="modifyScheduleModal" class="modify-modal-overlay">
        <div class="modify-modal">
            <div class="modify-modal-header">
                <h3 id="modifyScheduleTitle">
                    📅 Comprehensive Schedule Editor
                </h3>
                <span class="modify-modal-close" onclick="closeModifyScheduleModal()">&times;</span>
            </div>
            <div class="modify-modal-body">
                <form id="modifyScheduleForm">
                    <input type="hidden" id="modifyScheduleSpecialistId" name="specialist_id">
                    <input type="hidden" id="modifyScheduleWorkpointId" name="workpoint_id">
                    
                    <!-- Quick Options Section -->
                    <div class="individual-edit-section">
                        <h4 style="font-size: 14px; margin-bottom: 15px;">⚡ Quick Options</h4>
                        <div class="schedule-editor-table-container">
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 5px; border: 1px solid #e9ecef;">
                                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: nowrap;">
                                    <!-- Day Selector -->
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <label style="font-size: 12px; font-weight: 600; color: #333;">Day:</label>
                                        <select id="modifyQuickOptionsDaySelect" style="font-size: 11px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 3px; width: 80px;">
                                            <option value="mondayToFriday">Mon-Fri</option>
                                            <option value="saturday">Saturday</option>
                                            <option value="sunday">Sunday</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Shift 1 -->
                                    <div id="quickOptionsShift1" style="display: flex; align-items: center; gap: 8px;">
                                        <label style="font-size: 12px; font-weight: 600; color: #333; min-width: 50px;">Shift 1:</label>
                                        <input type="time" id="modifyShift1Start" class="form-control shift1-start-time" style="font-size: 11px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; width: 70px;" placeholder="Start">
                                        <input type="time" id="modifyShift1End" class="form-control shift1-end-time" style="font-size: 11px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; width: 70px;" placeholder="End">
                                    </div>

                                    <!-- Shift 2 -->
                                    <div id="quickOptionsShift2" style="display: flex; align-items: center; gap: 8px;">
                                        <label style="font-size: 12px; font-weight: 600; color: #333; min-width: 50px;">Shift 2:</label>
                                        <input type="time" id="modifyShift2Start" class="form-control shift2-start-time" style="font-size: 11px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; width: 70px;" placeholder="Start">
                                        <input type="time" id="modifyShift2End" class="form-control shift2-end-time" style="font-size: 11px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; width: 70px;" placeholder="End">
                                    </div>

                                    <!-- Shift 3 -->
                                    <div id="quickOptionsShift3" style="display: flex; align-items: center; gap: 8px;">
                                        <label style="font-size: 12px; font-weight: 600; color: #333; min-width: 50px;">Shift 3:</label>
                                        <input type="time" id="modifyShift3Start" class="form-control shift3-start-time" style="font-size: 11px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; width: 70px;" placeholder="Start">
                                        <input type="time" id="modifyShift3End" class="form-control shift3-end-time" style="font-size: 11px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; width: 70px;" placeholder="End">
                                    </div>
                                    
                                    <!-- Apply Button -->
                                    <button type="button" onclick="applyModifyAllShifts()" style="background: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600;">Apply</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Individual Day Editor -->
                    <div class="individual-edit-section">
                        <h4 style="display: flex; justify-content: space-between; align-items: center;">
                            <span>📋 Individual Day Editor</span>
                            <div style="font-size: 12px; font-weight: normal;">
                                <label style="margin-right: 15px; cursor: pointer;">
                                    <input type="checkbox" id="toggleShift1" checked onchange="toggleShiftVisibility(1, this.checked)" style="margin-right: 5px;">
                                    Shift 1
                                </label>
                                <label style="margin-right: 15px; cursor: pointer;">
                                    <input type="checkbox" id="toggleShift2" checked onchange="toggleShiftVisibility(2, this.checked)" style="margin-right: 5px;">
                                    Shift 2
                                </label>
                                <label style="cursor: pointer;">
                                    <input type="checkbox" id="toggleShift3" checked onchange="toggleShiftVisibility(3, this.checked)" style="margin-right: 5px;">
                                    Shift 3
                                </label>
                            </div>
                        </h4>
                        <div class="schedule-editor-table-container">
                            <table class="schedule-editor-table">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th colspan="3">Shift 1</th>
                                        <th class="separator-col"></th>
                                        <th colspan="3">Shift 2</th>
                                        <th class="separator-col"></th>
                                        <th colspan="3">Shift 3</th>
                                    </tr>
                                    <tr>
                                        <th></th>
                                        <th>Start</th>
                                        <th>End</th>
                                        <th></th>
                                        <th class="separator-col"></th>
                                        <th>Start</th>
                                        <th>End</th>
                                        <th></th>
                                        <th class="separator-col"></th>
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
                        <!-- All Buttons inside Individual Day Editor -->
                        <div style="margin-top: 10px; padding-bottom: 10px; overflow: hidden;">
                            <a href="#" onclick="openTimeOffModal(); return false;" style="float: left; font-size: 14px; color: #007bff; text-decoration: none; font-weight: 600; line-height: 27px;" title="Manage holidays and days off">
                                <i class="fas fa-calendar-times"></i> Holidays and Days off
                            </a>
                            <div style="float: right;">
                                <button type="button" class="btn-delete" onclick="deleteScheduleFromModal()" style="padding: 5px 12px !important; border-radius: 0 !important; font-size: 11px !important; margin-left: 5px;" title="Delete all schedules for this specialist at this location">
                                    <i class="fas fa-times" style="font-size: 10px;"></i> Delete
                                </button>
                            <button type="button" style="background: #6c757d; color: white; border: none; padding: 5px 12px; border-radius: 0; font-size: 11px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; margin-left: 5px;" 
                                    onmouseover="this.style.background='#5a6268';"
                                    onmouseout="this.style.background='#6c757d';"
                                    onclick="closeModifyScheduleModal()"
                                    title="Close without saving changes">
                                <i class="fas fa-times" style="margin-right: 3px; font-size: 10px;"></i>Cancel
                            </button>
                            <button type="button" style="background: linear-gradient(135deg, #007bff, #0056b3); color: white; border: none; padding: 5px 12px; border-radius: 0; font-size: 11px; font-weight: 600; cursor: pointer; box-shadow: 0 2px 6px rgba(0, 123, 255, 0.2); transition: all 0.3s ease; margin-left: 5px;"
                                    onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 3px 8px rgba(0, 123, 255, 0.3)';"
                                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 6px rgba(0, 123, 255, 0.2)';"
                                    onclick="updateScheduleFromModal()"
                                    title="Save schedule changes">
                                <i class="fas fa-save" style="margin-right: 3px; font-size: 10px;"></i>Update
                            </button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="modifyScheduleError" class="modify-error" style="display: none; margin: 15px 0; padding: 10px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px;"></div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Time Off Modal -->
    <div id="timeOffModal" class="modify-modal-overlay" style="display: none; z-index: 10000; background-color: rgba(0, 0, 0, 0.9);">
        <div class="modify-modal" style="width: 90%; max-width: 1200px; height: 90vh; overflow-y: auto;">
            <div class="modify-modal-header">
                <h3>
                    <i class="fas fa-calendar-times"></i> Holidays and Days Off Management
                </h3>
                <span class="modify-modal-close" onclick="closeTimeOffModal()">&times;</span>
            </div>
            <div class="modify-modal-body" style="padding: 20px;">
                <!-- Table layout -->
                <table style="width: 100%; margin: 0; padding: 0; border: 0; border-spacing: 0;">
                    <tr>
                        <td style="vertical-align: top; border: 0; margin: 0; padding: 0;">
                            <div id="specialistTimeOffInfo" style="margin-bottom: 10px; font-size: 14px;">
                                <!-- Specialist info will be displayed here -->
                            </div>
                        </td>
                        <td rowspan="3" style="vertical-align: top; border: 0; margin: 0; padding: 0; width: 200px;">
                            <!-- Selected dates summary -->
                            <div style="padding: 10px; background: #f8f9fa; border-radius: 5px; margin-left: 10px; height: 600px; box-sizing: border-box; overflow-y: auto;">
                                <h5 style="margin-bottom: 10px; text-align: center; font-size: 0.9em;">Days Off</h5>
                                <div id="selectedDaysOffList" style="text-align: left;">
                                    <!-- Selected dates will be listed here -->
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="vertical-align: top; border: 0; margin: 0; padding: 0;">
                            <!-- Year selector hidden as we show rolling 12 months -->
                            <div style="display: none;">
                                <span id="timeOffYear">2024</span>
                            </div>
                            
                            <!-- 12 month calendar grid -->
                            <div id="timeOffCalendarGrid" style="display: grid; grid-template-columns: repeat(4, minmax(200px, 1fr)); gap: 0px;">
                                <!-- Months will be generated here -->
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="vertical-align: top; border: 0; margin: 0; padding: 0;">
                            <!-- Empty row to ensure proper height -->
                        </td>
                    </tr>
                </table>
                
                <!-- Action buttons -->
                <div style="text-align: center; margin-top: 20px;">
                    <button type="button" onclick="clearAllTimeOff()" 
                            style="background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 3px; font-size: 12px; cursor: pointer; margin: 0 5px; transition: all 0.2s;"
                            onmouseover="this.style.boxShadow='0 2px 8px rgba(220,53,69,0.3)'; this.style.transform='translateY(-1px)';"
                            onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';"
                            title="Remove all selected days off">
                        <i class="fas fa-trash" style="font-size: 11px;"></i> Clear All
                    </button>
                    <button type="button" onclick="closeTimeOffModal()" 
                            style="background: #6c757d; color: white; border: none; padding: 6px 12px; border-radius: 3px; font-size: 12px; cursor: pointer; margin: 0 5px; transition: all 0.2s;"
                            onmouseover="this.style.boxShadow='0 2px 8px rgba(108,117,125,0.3)'; this.style.transform='translateY(-1px)';"
                            onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';"
                            title="Close without saving changes">
                        <i class="fas fa-times" style="font-size: 11px;"></i> Cancel
                    </button>
                    <button type="button" onclick="saveTimeOff()" 
                            style="background: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 3px; font-size: 12px; cursor: pointer; margin: 0 5px; transition: all 0.2s;"
                            onmouseover="this.style.boxShadow='0 2px 8px rgba(0,123,255,0.3)'; this.style.transform='translateY(-1px)';"
                            onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';"
                            title="Save all changes to database">
                        <i class="fas fa-save" style="font-size: 11px;"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Function to toggle Working Schedule visibility
    function toggleWorkingSchedule() {
        const content = document.getElementById('workingScheduleContent');
        const toggle = document.getElementById('workingScheduleToggle');
        
        if (content.style.display === 'none') {
            content.style.display = 'block';
            toggle.style.transform = 'rotate(180deg)';
        } else {
            content.style.display = 'none';
            toggle.style.transform = 'rotate(0deg)';
        }
    }
    
    
    // Function to open Add Service Modal for Specialist
    function openAddServiceModalForSpecialist(specialistUnicId) {
        // Check if we're in supervisor mode or specialist mode
        if (isSupervisorMode) {
            // Supervisor mode - use custom modal
            openSupervisorAddServiceModal(specialistUnicId);
        } else {
            // Specialist mode - use original modal from modal_booking_actions.php
            // Set the specialist_id in the service modal hidden fields
            const serviceModalSpecialistId = document.getElementById('serviceModalSpecialistId');
            if (serviceModalSpecialistId) {
                serviceModalSpecialistId.value = specialistUnicId;
            }
            
            // Set the workpoint_id if available
            const workpointId = <?= json_encode($workpoint_id ?? $working_point_user_id ?? '') ?>;
            const serviceModalWorkpointId = document.getElementById('serviceModalWorkpointId');
            if (serviceModalWorkpointId && workpointId) {
                serviceModalWorkpointId.value = workpointId;
            }
            
            // Clear the form
            document.getElementById('addServiceForm').reset();
            
            // Restore the IDs after reset
            if (serviceModalSpecialistId) {
                serviceModalSpecialistId.value = specialistUnicId;
            }
            if (serviceModalWorkpointId && workpointId) {
                serviceModalWorkpointId.value = workpointId;
            }
            
            // Open the original add service modal
            new bootstrap.Modal(document.getElementById('addServiceModal')).show();
            
            // Initialize tooltips for disabled buttons after modal is shown
            setTimeout(() => {
                const addModal = document.getElementById('addServiceModal');
                const tooltipElements = addModal.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltipElements.forEach(elem => {
                    new bootstrap.Tooltip(elem);
                });
            }, 100);
        }
    }
    
    // Create custom Add Service Modal for Supervisor Mode
    function openSupervisorAddServiceModal(specialistUnicId) {
        // Check if modal exists, if not create it
        let modal = document.getElementById('supervisorAddServiceModal');
        if (!modal) {
            createSupervisorAddServiceModal();
            modal = document.getElementById('supervisorAddServiceModal');
        }
        
        // Set the specialist ID
        const specialistIdField = modal.querySelector('input[name="specialist_id"]');
        if (specialistIdField) {
            specialistIdField.value = specialistUnicId;
        }
        
        // Set the workpoint ID
        const workpointId = <?= json_encode($workpoint_id ?? $working_point_user_id ?? '') ?>;
        const workpointIdField = modal.querySelector('input[name="workpoint_id"]');
        if (workpointIdField) {
            workpointIdField.value = workpointId;
        }
        
        // Load existing services for this workpoint
        loadWorkpointServicesForTemplate(workpointId, specialistUnicId);
        
        // Clear the form
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
            // Restore the IDs after reset
            if (specialistIdField) specialistIdField.value = specialistUnicId;
            if (workpointIdField) workpointIdField.value = workpointId;
        }
        
        // Show the modal
        new bootstrap.Modal(modal).show();
    }
    
    function createSupervisorAddServiceModal() {
        const modalHTML = `
        <div class="modal fade" id="supervisorAddServiceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="fas fa-plus"></i> Add Service (Supervisor Mode)</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="supervisorAddServiceForm">
                        <div class="modal-body">
                            <input type="hidden" name="specialist_id">
                            <input type="hidden" name="workpoint_id">
                            
                            <div class="mb-3">
                                <label class="form-label">Select from existing services</label>
                                <select class="form-select form-select-sm" id="serviceTemplate" onchange="populateFromTemplate(this.value)" style="max-width: 100%;">
                                    <option value="">** Select a service to pre-fill the form</option>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="supervisorServiceName" class="form-label">The Name of the new service <span style="color: #dc3545aa; font-weight: normal;">*</span></label>
                                    <input type="text" class="form-control" id="supervisorServiceName" name="name_of_service" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="supervisorServiceDuration" class="form-label">Duration (minutes) <span style="color: #dc3545aa; font-weight: normal;">*</span></label>
                                    <select class="form-control" id="supervisorServiceDuration" name="duration" required>
                                        <option value="">Select duration...</option>
                                        <option value="10">10 minutes</option>
                                        <option value="20">20 minutes</option>
                                        <option value="30">30 minutes</option>
                                        <option value="40">40 minutes</option>
                                        <option value="50">50 minutes</option>
                                        <option value="60">1 hour</option>
                                        <option value="70">1 hour 10 minutes</option>
                                        <option value="80">1 hour 20 minutes</option>
                                        <option value="90">1 hour 30 minutes</option>
                                        <option value="100">1 hour 40 minutes</option>
                                        <option value="110">1 hour 50 minutes</option>
                                        <option value="120">2 hours</option>
                                        <option value="150">2 hours 30 minutes</option>
                                        <option value="180">3 hours</option>
                                        <option value="210">3 hours 30 minutes</option>
                                        <option value="240">4 hours</option>
                                        <option value="300">5 hours</option>
                                        <option value="360">6 hours</option>
                                        <option value="420">7 hours</option>
                                        <option value="480">8 hours</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="supervisorServicePrice" class="form-label">Price <span style="color: #dc3545aa; font-weight: normal;">*</span></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="supervisorServicePrice" name="price_of_service" step="0.01" min="0" required>
                                        <span class="input-group-text">€</span>
                                    </div>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="supervisorServiceVat" class="form-label">VAT %</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="supervisorServiceVat" name="procent_vat" value="0" step="0.01" min="0" max="100">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success btn-sm" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                                <i class="fas fa-plus"></i> Add Service
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Add submit handler
        document.getElementById('supervisorAddServiceForm').addEventListener('submit', handleSupervisorAddService);
    }
    
    function loadWorkpointServicesForTemplate(workpointId, currentSpecialistId) {
        if (!workpointId) return;
        
        fetch(`admin/get_all_services_for_workpoint.php?workpoint_id=${workpointId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('serviceTemplate');
                    if (!select) return;
                    
                    select.innerHTML = '<option value="">** Select a service to pre-fill the form</option>';
                    
                    // Store services data globally for template population
                    window.workpointServices = {};
                    
                    data.services.forEach(service => {
                        // Skip services that are deleted
                        if (service.deleted == 1) return;
                        
                        const option = document.createElement('option');
                        option.value = service.service_id;
                        
                        let label = service.name_of_service;
                        if (service.specialist_name) {
                            label += ` (${service.specialist_name})`;
                        }
                        label += ` - ${service.duration}min, €${service.price_of_service}`;
                        if (service.procent_vat > 0) {
                            label += ` +${service.procent_vat}% VAT`;
                        }
                        
                        option.textContent = label;
                        select.appendChild(option);
                        
                        // Store COMPLETE service data for later use
                        window.workpointServices[service.service_id] = {
                            name_of_service: service.name_of_service,
                            duration: service.duration,
                            price_of_service: service.price_of_service,
                            procent_vat: service.procent_vat || 0
                        };
                    });
                }
            })
            .catch(error => {
                console.error('Error loading services:', error);
            });
    }
    
    function populateFromTemplate(serviceId) {
        console.log('Selected service ID:', serviceId);
        console.log('Available services:', window.workpointServices);
        
        if (!serviceId || !window.workpointServices || !window.workpointServices[serviceId]) {
            // Clear form if no template selected
            document.getElementById('supervisorServiceName').value = '';
            document.getElementById('supervisorServiceDuration').value = '';
            document.getElementById('supervisorServicePrice').value = '';
            document.getElementById('supervisorServiceVat').value = '0';
            return;
        }
        
        const service = window.workpointServices[serviceId];
        console.log('Selected service data:', service);
        
        // Set form values
        const nameField = document.getElementById('supervisorServiceName');
        const durationField = document.getElementById('supervisorServiceDuration');
        const priceField = document.getElementById('supervisorServicePrice');
        const vatField = document.getElementById('supervisorServiceVat');
        
        // Set the name
        if (nameField) {
            nameField.value = service.name_of_service || '';
            console.log('Set name to:', nameField.value);
        }
        if (priceField) {
            priceField.value = service.price_of_service || '';
            console.log('Set price to:', priceField.value);
        }
        if (vatField) {
            vatField.value = service.procent_vat || '0';
            console.log('Set VAT to:', vatField.value);
        }
        
        // Duration is a select, need to set it properly
        if (durationField && service.duration) {
            // Convert duration to string to match option values
            const durationValue = service.duration.toString();
            
            // Check if the option exists
            let optionExists = false;
            for (let i = 0; i < durationField.options.length; i++) {
                if (durationField.options[i].value === durationValue) {
                    durationField.value = durationValue;
                    optionExists = true;
                    break;
                }
            }
            
            if (!optionExists) {
                // Try to find closest match or add custom option
                const customOption = document.createElement('option');
                customOption.value = durationValue;
                customOption.textContent = durationValue + ' minutes';
                durationField.appendChild(customOption);
                durationField.value = durationValue;
            }
        }
    }
    
    function handleSupervisorAddService(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        formData.append('action', 'add_service');
        
        
        // Show loading state
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        submitBtn.disabled = true;
        
        fetch('admin/process_add_service.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close the modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('supervisorAddServiceModal'));
                modal.hide();
                
                // Refresh services in the Modify Specialist modal
                const specialistId = formData.get('specialist_id');
                if (specialistId) {
                    setTimeout(() => {
                        loadSpecialistServicesForModal(specialistId);
                    }, 300);
                }
                
                // Reset form
                e.target.reset();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error adding service:', error);
            alert('Error adding service');
        })
        .finally(() => {
            // Reset button
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }
    
    // Store specialist permissions in JavaScript
    const specialistCanModifyServices = <?= json_encode(($specialist_permissions['specialist_can_modify_services'] ?? 0) == 1) ?>;
    const specialistCanDeleteServices = <?= json_encode(($specialist_permissions['specialist_can_delete_services'] ?? 0) == 1) ?>;
    const isSupervisorMode = <?= json_encode($supervisor_mode) ?>;
    
    
    // Function to edit a specialist service
    function editSpecialistService(serviceId, serviceName, duration, price, vat) {
        // Check if edit service modal exists, if not create it
        let editModal = document.getElementById('editServiceModalSpecialist');
        if (!editModal) {
            // Create the edit modal
            const modalHTML = `
            <div class='modal fade' id='editServiceModalSpecialist' tabindex='-1' aria-hidden='true'>
                <div class='modal-dialog'>
                    <div class='modal-content'>
                        <div class='modal-header bg-primary text-white'>
                            <h5 class='modal-title'>Edit Service</h5>
                            <button type='button' class='btn-close btn-close-white' data-bs-dismiss='modal'></button>
                        </div>
                        <form id='editServiceFormSpecialist' onsubmit='submitEditSpecialistService(event)'>
                            <div class='modal-body'>
                                <input type='hidden' name='service_id' id='editServiceId'>
                                <input type='hidden' name='action' value='edit_service'>
                                
                                <div class='mb-3'>
                                    <label for='editServiceName' class='form-label'>Service Name</label>
                                    <input type='text' class='form-control' id='editServiceName' name='name_of_service' required
                                           ${!isSupervisorMode && !specialistCanModifyServices ? 'disabled' : ''}>
                                </div>
                                
                                <div class='mb-3'>
                                    <label for='editServiceDuration' class='form-label'>Duration (minutes)</label>
                                    <select class='form-control' id='editServiceDuration' name='duration' required
                                            ${!isSupervisorMode && !specialistCanModifyServices ? 'disabled' : ''}>
                                        <option value='10'>10 minutes</option>
                                        <option value='20'>20 minutes</option>
                                        <option value='30'>30 minutes</option>
                                        <option value='40'>40 minutes</option>
                                        <option value='50'>50 minutes</option>
                                        <option value='60'>1 hour</option>
                                        <option value='70'>1 hour 10 minutes</option>
                                        <option value='80'>1 hour 20 minutes</option>
                                        <option value='90'>1 hour 30 minutes</option>
                                        <option value='100'>1 hour 40 minutes</option>
                                        <option value='110'>1 hour 50 minutes</option>
                                        <option value='120'>2 hours</option>
                                    </select>
                                </div>
                                
                                <div class='row'>
                                    <div class='col-md-8 mb-3'>
                                        <label for='editServicePrice' class='form-label'>Price (€)</label>
                                        <input type='number' class='form-control' id='editServicePrice' name='price_of_service' step='0.01' min='0' required
                                               ${!isSupervisorMode && !specialistCanModifyServices ? 'disabled' : ''}>
                                    </div>
                                    
                                    <div class='col-md-4 mb-3'>
                                        <label for='editServiceVat' class='form-label'>VAT %</label>
                                        <input type='number' class='form-control' id='editServiceVat' name='procent_vat' min='0' max='100' value='0'
                                               ${!isSupervisorMode && !specialistCanModifyServices ? 'disabled' : ''}>
                                    </div>
                                </div>
                            </div>
                            <div class='modal-footer' style='justify-content: space-between;'>
                                ${!isSupervisorMode && !specialistCanDeleteServices ? 
                                    '<span data-bs-toggle="tooltip" data-bs-placement="top" title="Permission Disabled for this action. Ask the supervisor or Enable this permissions from Supervisor Dashboard if a sole trader.">' : ''}
                                <button type='button' 
                                        id='deleteServiceBtn' 
                                        class='btn btn-danger btn-sm' 
                                        style='float: left; padding: 0.25rem 0.5rem; font-size: 0.875rem;'
                                        ${!isSupervisorMode && !specialistCanDeleteServices ? 'disabled' : ''}>
                                    <i class='fas fa-trash'></i> Delete Service
                                </button>
                                ${!isSupervisorMode && !specialistCanDeleteServices ? '</span>' : ''}
                                <div>
                                    <button type='button' class='btn btn-secondary btn-sm' data-bs-dismiss='modal' style='padding: 0.25rem 0.5rem; font-size: 0.875rem;'>Cancel</button>
                                    ${!isSupervisorMode && !specialistCanModifyServices ? 
                                        '<span data-bs-toggle="tooltip" data-bs-placement="top" title="Permission Disabled for this action. Ask the supervisor or Enable this permissions from Supervisor Dashboard if a sole trader.">' : ''}
                                    <button type='submit' class='btn btn-primary btn-sm'
                                            style='padding: 0.25rem 0.5rem; font-size: 0.875rem;'
                                            ${!isSupervisorMode && !specialistCanModifyServices ? 'disabled' : ''}>
                                        <i class='fas fa-save'></i> Save Changes
                                    </button>
                                    ${!isSupervisorMode && !specialistCanModifyServices ? '</span>' : ''}
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>`;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }
        
        // Populate the form
        document.getElementById('editServiceId').value = serviceId;
        document.getElementById('editServiceName').value = serviceName;
        document.getElementById('editServiceDuration').value = duration;
        document.getElementById('editServicePrice').value = price;
        document.getElementById('editServiceVat').value = vat;
        
        // Store service info globally for delete function
        window.currentEditService = {
            id: serviceId,
            name: serviceName
        };
        
        // Check if service has future bookings
        checkServiceBookings(serviceId);
        
        // Show the modal
        new bootstrap.Modal(document.getElementById('editServiceModalSpecialist')).show();
        
        // Initialize tooltips for disabled buttons after modal is shown
        setTimeout(() => {
            const editModal = document.getElementById('editServiceModalSpecialist');
            const tooltipElements = editModal.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltipElements.forEach(elem => {
                new bootstrap.Tooltip(elem);
            });
        }, 100);
    }
    
    // Submit edit service form
    function submitEditSpecialistService(e) {
        e.preventDefault();
        
        const formData = new FormData(document.getElementById('editServiceFormSpecialist'));
        
        fetch('admin/process_add_service.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('editServiceModalSpecialist')).hide();
                
                // Check if we should return to Modify Specialist modal
                if (window.serviceReturnModal === 'modifySpecialist' && window.serviceReturnSpecialistId) {
                    // Refresh the services list
                    loadSpecialistServicesForModal(window.serviceReturnSpecialistId);
                    // Clear the markers
                    window.serviceReturnModal = null;
                    window.serviceReturnSpecialistId = null;
                } else {
                    // Reload the page to show updated services
                    location.reload();
                }
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating service');
        });
    }
    
    // Helper function to apply permission state to delete button
    function applyDeletePermissionState(deleteBtn) {
        if (!isSupervisorMode && !specialistCanDeleteServices) {
            deleteBtn.disabled = true;
            
            // Wrap the button in a span for tooltip to work with disabled button
            const wrapper = document.createElement('span');
            wrapper.setAttribute('data-bs-toggle', 'tooltip');
            wrapper.setAttribute('data-bs-placement', 'top');
            wrapper.setAttribute('title', 'Permission Disabled for this action. Ask the supervisor or Enable this permissions from Supervisor Dashboard if a sole trader.');
            
            deleteBtn.parentNode.insertBefore(wrapper, deleteBtn);
            wrapper.appendChild(deleteBtn);
            
            // Initialize tooltip on the wrapper
            new bootstrap.Tooltip(wrapper);
            return false; // No permission
        }
        return true; // Has permission
    }
    
    // Function to check if service has bookings
    function checkServiceBookings(serviceId) {
        fetch('admin/check_service_bookings.php?service_id=' + serviceId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text(); // Get as text first to debug
        })
        .then(text => {
            console.log('Raw response:', text); // Debug output
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', text);
                throw e;
            }
        })
        .then(data => {
            console.log('Service booking data:', data); // Debug log
            
            const deleteBtn = document.getElementById('deleteServiceBtn');
            const durationInput = document.getElementById('editServiceDuration');
            
            // Reset button styles first (but respect permissions)
            deleteBtn.style.backgroundColor = '';
            deleteBtn.style.borderColor = '';
            deleteBtn.style.color = '';
            deleteBtn.style.opacity = '1';
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Service';
            deleteBtn.onclick = function() { checkAndDeleteService(); };
            
            // Check if user has delete permission and apply it
            if (!applyDeletePermissionState(deleteBtn)) {
                // Don't continue with other modifications if no permission
                return;
            }
            
            console.log('Has past bookings:', data.hasPastBookings, 'Has future bookings:', data.hasFutureBookings, 'Is suspended:', data.isSuspended); // Debug log
            
            // Handle different cases
            if (data.hasFutureBookings && data.hasPastBookings) {
                // 1. Service has both future and past bookings - show suspend button
                deleteBtn.style.backgroundColor = '#ffc107';
                deleteBtn.style.borderColor = '#ffc107';
                deleteBtn.style.color = '#000';
                deleteBtn.style.opacity = '1';
                deleteBtn.disabled = !isSupervisorMode && !specialistCanDeleteServices;
                if (deleteBtn.disabled) {
                    applyDeletePermissionState(deleteBtn);
                } else {
                    deleteBtn.removeAttribute('title');
                    deleteBtn.removeAttribute('data-bs-toggle');
                    deleteBtn.removeAttribute('data-bs-placement');
                }
                
                if (data.isSuspended) {
                    deleteBtn.innerHTML = '<i class="fas fa-play-circle"></i> Activate Service';
                    deleteBtn.onclick = function() { suspendOrActivateService('activate'); };
                } else {
                    deleteBtn.innerHTML = '<i class="fas fa-pause-circle"></i> Suspend Service';
                    deleteBtn.onclick = function() { suspendOrActivateService('suspend'); };
                }
                
                // Handle duration field
                durationInput.readOnly = true;
                durationInput.setAttribute('title', 'The duration cannot be changed as long as this service has future bookings');
                durationInput.setAttribute('data-bs-toggle', 'tooltip');
                durationInput.setAttribute('data-bs-placement', 'top');
                durationInput.style.backgroundColor = '#f8f9fa';
                durationInput.style.cursor = 'not-allowed';
                
                // Initialize Bootstrap tooltips
                new bootstrap.Tooltip(durationInput);
            } else if (data.hasFutureBookings && !data.hasPastBookings) {
                // 3. Service has only future bookings - show suspend button
                deleteBtn.style.backgroundColor = '#ffc107';
                deleteBtn.style.borderColor = '#ffc107';
                deleteBtn.style.color = '#000';
                deleteBtn.style.opacity = '1';
                deleteBtn.disabled = !isSupervisorMode && !specialistCanDeleteServices;
                if (deleteBtn.disabled) {
                    applyDeletePermissionState(deleteBtn);
                } else {
                    deleteBtn.removeAttribute('title');
                    deleteBtn.removeAttribute('data-bs-toggle');
                    deleteBtn.removeAttribute('data-bs-placement');
                }
                
                if (data.isSuspended) {
                    deleteBtn.innerHTML = '<i class="fas fa-play-circle"></i> Activate Service';
                    deleteBtn.onclick = function() { suspendOrActivateService('activate'); };
                } else {
                    deleteBtn.innerHTML = '<i class="fas fa-pause-circle"></i> Suspend Service';
                    deleteBtn.onclick = function() { suspendOrActivateService('suspend'); };
                }
                
                // Handle duration field
                durationInput.readOnly = true;
                durationInput.setAttribute('title', 'The duration cannot be changed as long as this service has future bookings');
                durationInput.setAttribute('data-bs-toggle', 'tooltip');
                durationInput.setAttribute('data-bs-placement', 'top');
                durationInput.style.backgroundColor = '#f8f9fa';
                durationInput.style.cursor = 'not-allowed';
                
                // Initialize Bootstrap tooltips
                new bootstrap.Tooltip(durationInput);
            } else if (data.hasPastBookings && !data.hasFutureBookings) {
                // 2. Service has only past bookings - show normal delete button (soft delete)
                deleteBtn.disabled = !isSupervisorMode && !specialistCanDeleteServices;
                if (deleteBtn.disabled) {
                    applyDeletePermissionState(deleteBtn);
                } else {
                    deleteBtn.removeAttribute('title');
                    deleteBtn.removeAttribute('data-bs-toggle');
                    deleteBtn.removeAttribute('data-bs-placement');
                }
                
                // Handle duration field - enable it
                durationInput.readOnly = false;
                durationInput.removeAttribute('title');
                durationInput.removeAttribute('data-bs-toggle');
                durationInput.removeAttribute('data-bs-placement');
                durationInput.style.backgroundColor = '';
                durationInput.style.cursor = '';
                
                // Destroy tooltips if they exist
                const durationTooltip = bootstrap.Tooltip.getInstance(durationInput);
                if (durationTooltip) durationTooltip.dispose();
            } else {
                // 4. Service has no bookings - enable normal delete (hard delete)
                deleteBtn.disabled = !isSupervisorMode && !specialistCanDeleteServices;
                if (deleteBtn.disabled) {
                    applyDeletePermissionState(deleteBtn);
                } else {
                    deleteBtn.removeAttribute('title');
                    deleteBtn.removeAttribute('data-bs-toggle');
                    deleteBtn.removeAttribute('data-bs-placement');
                }
                
                // Handle duration field
                durationInput.readOnly = false;
                durationInput.removeAttribute('title');
                durationInput.removeAttribute('data-bs-toggle');
                durationInput.removeAttribute('data-bs-placement');
                durationInput.style.backgroundColor = '';
                durationInput.style.cursor = '';
                
                // Destroy tooltips if they exist
                const deleteBtnTooltip = bootstrap.Tooltip.getInstance(deleteBtn);
                if (deleteBtnTooltip) deleteBtnTooltip.dispose();
                
                const durationTooltip = bootstrap.Tooltip.getInstance(durationInput);
                if (durationTooltip) durationTooltip.dispose();
            }
            
            // Store booking info for delete logic
            window.currentEditService.hasPastBookings = data.hasPastBookings;
            window.currentEditService.hasFutureBookings = data.hasFutureBookings;
            window.currentEditService.isSuspended = data.isSuspended;
        })
        .catch(error => {
            console.error('Error checking bookings:', error);
        });
    }
    
    // Function to check and delete service
    function checkAndDeleteService() {
        if (!window.currentEditService) return;
        
        // Check permission
        if (!isSupervisorMode && !specialistCanDeleteServices) {
            alert('Permission Disabled for this action. Ask the supervisor or Enable this permissions from Supervisor Dashboard if a sole trader.');
            return;
        }
        
        const service = window.currentEditService;
        
        if (confirm(`Are you sure you want to delete the service "${service.name}"?`)) {
            const formData = new FormData();
            formData.append('service_id', service.id);
            
            // Determine delete type based on past bookings
            if (service.hasPastBookings) {
                formData.append('action', 'soft_delete_service');
            } else {
                formData.append('action', 'hard_delete_service');
            }
            
            fetch('admin/process_delete_service.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close the edit modal
                    const editModal = bootstrap.Modal.getInstance(document.getElementById('editServiceModalSpecialist'));
                    if (editModal) {
                        editModal.hide();
                    }
                    
                    // Check if we came from Modify Specialist modal
                    if (window.serviceReturnModal === 'modifySpecialist' && window.serviceReturnSpecialistId) {
                        // Refresh services in the Modify Specialist modal
                        setTimeout(() => {
                            loadSpecialistServicesForModal(window.serviceReturnSpecialistId);
                        }, 300);
                    } else {
                        // Otherwise reload the page
                        location.reload();
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting service');
            });
        }
    }
    
    // Function to suspend or activate service
    function suspendOrActivateService(action) {
        if (!window.currentEditService) return;
        
        const service = window.currentEditService;
        const actionText = action === 'suspend' ? 'suspend' : 'activate';
        
        let confirmMessage = '';
        if (action === 'suspend') {
            confirmMessage = `⚠️ IMPORTANT: Once suspended, this service cannot be booked anymore in the future.\n\nAre you sure you want to suspend the service "${service.name}"?`;
        } else {
            confirmMessage = `Are you sure you want to activate the service "${service.name}"?`;
        }
        
        if (confirm(confirmMessage)) {
            const formData = new FormData();
            formData.append('service_id', service.id);
            formData.append('action', action);
            
            fetch('admin/process_suspend_service.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close the edit modal
                    const editModal = bootstrap.Modal.getInstance(document.getElementById('editServiceModalSpecialist'));
                    if (editModal) {
                        editModal.hide();
                    }
                    
                    // Check if we came from Modify Specialist modal
                    if (window.serviceReturnModal === 'modifySpecialist' && window.serviceReturnSpecialistId) {
                        // Refresh services in the Modify Specialist modal
                        setTimeout(() => {
                            loadSpecialistServicesForModal(window.serviceReturnSpecialistId);
                        }, 300);
                    } else {
                        // Otherwise reload the page
                        location.reload();
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(`Error ${action === 'suspend' ? 'suspending' : 'activating'} service`);
            });
        }
    }
    
    // Toggle Services Performed dropdown
    function toggleServicesPerformed() {
        const content = document.getElementById('servicesPerformedContent');
        
        if (content.style.display === 'none') {
            content.style.display = 'block';
            localStorage.setItem('servicesPerformedState', 'open');
        } else {
            content.style.display = 'none';
            localStorage.setItem('servicesPerformedState', 'closed');
        }
    }
    
    // Toggle Working Schedule dropdown for each working point
    <?php if (!$supervisor_mode && isset($working_points) && !empty($working_points)): ?>
        <?php foreach ($working_points as $wp): ?>
        function toggleWorkingSchedule<?= $wp['unic_id'] ?>() {
            const content = document.getElementById('workingScheduleContent<?= $wp['unic_id'] ?>');
            const button = document.getElementById('workingScheduleToggle<?= $wp['unic_id'] ?>');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                button.innerHTML = '<i class="fas fa-times" style="font-size: 10px;"></i> Close';
                localStorage.setItem('workingScheduleState_<?= $wp['unic_id'] ?>', 'open');
            } else {
                content.style.display = 'none';
                button.innerHTML = '<i class="fas fa-list" style="font-size: 10px;"></i> List';
                localStorage.setItem('workingScheduleState_<?= $wp['unic_id'] ?>', 'closed');
            }
        }
        
        function toggleWorkingPoint<?= $wp['unic_id'] ?>() {
            const details = document.getElementById('workingPointDetails<?= $wp['unic_id'] ?>');
            const button = document.getElementById('workingPointToggle<?= $wp['unic_id'] ?>');
            
            if (details.style.display === 'none') {
                details.style.display = 'block';
                button.innerHTML = '<i class="fas fa-times" style="font-size: 10px;"></i> Close';
                localStorage.setItem('workingPointState_<?= $wp['unic_id'] ?>', 'open');
            } else {
                details.style.display = 'none';
                button.innerHTML = '<i class="fas fa-info-circle" style="font-size: 10px;"></i> Info';
                localStorage.setItem('workingPointState_<?= $wp['unic_id'] ?>', 'closed');
            }
        }
        <?php endforeach; ?>
    <?php endif; ?>
    
    // Restore dropdown states on page load
    setTimeout(function() {
        // Restore Services Performed state
        if (localStorage.getItem('servicesPerformedState') === 'open') {
            const content = document.getElementById('servicesPerformedContent');
            if (content) {
                content.style.display = 'block';
            }
        }
        
        // Restore all working point states by checking localStorage keys
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            
            // Check for Working Schedule states
            if (key.startsWith('workingScheduleState_')) {
                const wpId = key.replace('workingScheduleState_', '');
                if (localStorage.getItem(key) === 'open') {
                    const scheduleContent = document.getElementById('workingScheduleContent' + wpId);
                    const scheduleButton = document.getElementById('workingScheduleToggle' + wpId);
                    if (scheduleContent && scheduleButton) {
                        scheduleContent.style.display = 'block';
                        scheduleButton.innerHTML = '<i class="fas fa-times" style="font-size: 10px;"></i> Close';
                    }
                }
            }
            
            // Check for Working Point states
            if (key.startsWith('workingPointState_')) {
                const wpId = key.replace('workingPointState_', '');
                if (localStorage.getItem(key) === 'open') {
                    const pointDetails = document.getElementById('workingPointDetails' + wpId);
                    const pointButton = document.getElementById('workingPointToggle' + wpId);
                    if (pointDetails && pointButton) {
                        pointDetails.style.display = 'block';
                        pointButton.innerHTML = '<i class="fas fa-times" style="font-size: 10px;"></i> Close';
                    }
                }
            }
        }
    }, 100);
    
    // Right Panel Functions
    function updateQuickNavButtons(currentPanel) {
        const quickNavButtons = document.getElementById('quickNavButtons');
        quickNavButtons.innerHTML = '';
        
        const buttons = [
            { id: 'search', icon: 'fa-search', label: 'Search', action: 'showSearchPanel' },
            { id: 'arrivals', icon: 'fa-clock', label: 'Arrivals', action: 'showArrivalsPanel' },
            { id: 'canceled', icon: 'fa-ban', label: 'Canceled', action: 'showCanceledPanel' }
        ];
        
        buttons.forEach(btn => {
            if (btn.id !== currentPanel) {
                const button = document.createElement('button');
                button.className = 'btn btn-sm btn-outline-secondary';
                button.style.padding = '4px 8px';
                button.style.fontSize = '12px';
                button.innerHTML = `<i class="fas ${btn.icon}"></i> ${btn.label}`;
                button.onclick = () => window[btn.action]();
                quickNavButtons.appendChild(button);
            }
        });
    }
    
    function showSearchPanel() {
        document.getElementById('panelTitle').textContent = 'Search Bookings';
        updateQuickNavButtons('search');
        document.getElementById('panelContent').innerHTML = `
            <div class="search-container">
                <div class="mb-3">
                    <label for="searchInput" class="form-label">Search by Name or Booking ID</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Enter name or ID number..." onkeyup="performSearch()">
                </div>
                <div id="searchResults">
                    <p class="text-muted">Enter a search term to find bookings...</p>
                </div>
            </div>
        `;
        openRightPanel();
    }
    
    function showArrivalsPanel() {
        document.getElementById('panelTitle').textContent = 'Recent Arrivals';
        updateQuickNavButtons('arrivals');
        document.getElementById('panelContent').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading arrivals...</div>';
        openRightPanel();
        
        // Fetch arrivals data
        <?php if ($supervisor_mode): ?>
        const url = 'ajax/get_arrivals.php?mode=supervisor&workpoint_id=<?= $workpoint_id ?>';
        <?php else: ?>
        const url = 'ajax/get_arrivals.php?mode=specialist&specialist_id=<?= $specialist_id ?>';
        <?php endif; ?>
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                displayArrivals(data);
            })
            .catch(error => {
                console.error('Error fetching arrivals:', error);
                document.getElementById('panelContent').innerHTML = '<div class="alert alert-danger">Failed to load arrivals</div>';
            });
    }
    
    function showCanceledPanel() {
        document.getElementById('panelTitle').textContent = 'Canceled Bookings';
        updateQuickNavButtons('canceled');
        document.getElementById('panelContent').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading canceled bookings...</div>';
        openRightPanel();
        
        // Fetch canceled bookings
        <?php if ($supervisor_mode): ?>
        const url = 'ajax/get_canceled.php?mode=supervisor&workpoint_id=<?= $workpoint_id ?>';
        <?php else: ?>
        const url = 'ajax/get_canceled.php?mode=specialist&specialist_id=<?= $specialist_id ?>';
        <?php endif; ?>
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                displayCanceled(data);
            })
            .catch(error => {
                console.error('Error fetching canceled bookings:', error);
                document.getElementById('panelContent').innerHTML = '<div class="alert alert-danger">Failed to load canceled bookings</div>';
            });
    }
    
    function openRightPanel() {
        document.getElementById('rightSidePanel').style.left = '0';
    }
    
    function closeRightPanel() {
        document.getElementById('rightSidePanel').style.left = '-472px';
    }
    
    function performSearch() {
        const searchTerm = document.getElementById('searchInput').value.trim();
        if (searchTerm.length < 2) {
            document.getElementById('searchResults').innerHTML = '<p class="text-muted">Enter at least 2 characters to search...</p>';
            return;
        }
        
        <?php if ($supervisor_mode): ?>
        const url = `ajax/search_bookings.php?mode=supervisor&workpoint_id=<?= $workpoint_id ?>&search=${encodeURIComponent(searchTerm)}`;
        <?php else: ?>
        const url = `ajax/search_bookings.php?mode=specialist&specialist_id=<?= $specialist_id ?>&search=${encodeURIComponent(searchTerm)}`;
        <?php endif; ?>
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                displaySearchResults(data);
            })
            .catch(error => {
                console.error('Error searching:', error);
                document.getElementById('searchResults').innerHTML = '<div class="alert alert-danger">Search failed</div>';
            });
    }
    
    function displayArrivals(data) {
        if (!data.bookings || data.bookings.length === 0) {
            document.getElementById('panelContent').innerHTML = '<div class="alert alert-info">No arrivals found</div>';
            return;
        }
        
        let html = '<div class="arrivals-list">';
        
        // Group bookings by time categories
        const hot = data.bookings.filter(b => b.category === 'hot');
        const mild = data.bookings.filter(b => b.category === 'mild');
        const recent = data.bookings.filter(b => b.category === 'recent');
        const older = data.bookings.filter(b => b.category === 'older');
        
        // Display sections with neutral gray background
        if (hot.length > 0) {
            html += '<div class="arrival-section" style="background-color: #f8f9fa; padding: 10px; margin-bottom: 15px; border-radius: 8px;">';
            html += '<h6 style="color: #495057; margin-bottom: 10px;"><i class="fas fa-fire"></i> Last 2 Hours</h6>';
            hot.forEach(booking => {
                html += formatBookingCard(booking, '#f5f5f5');
            });
            html += '</div>';
        }
        
        if (mild.length > 0) {
            html += '<div class="arrival-section" style="background-color: #f8f9fa; padding: 10px; margin-bottom: 15px; border-radius: 8px;">';
            html += '<h6 style="color: #495057; margin-bottom: 10px;"><i class="fas fa-clock"></i> Last 6 Hours</h6>';
            mild.forEach(booking => {
                html += formatBookingCard(booking, '#f5f5f5');
            });
            html += '</div>';
        }
        
        if (recent.length > 0) {
            html += '<div class="arrival-section" style="background-color: #f8f9fa; padding: 10px; margin-bottom: 15px; border-radius: 8px;">';
            html += '<h6 style="color: #495057; margin-bottom: 10px;"><i class="fas fa-calendar-day"></i> Last 24 Hours</h6>';
            recent.forEach(booking => {
                html += formatBookingCard(booking, '#f5f5f5');
            });
            html += '</div>';
        }
        
        if (older.length > 0) {
            html += '<div class="arrival-section" style="background-color: #f8f9fa; padding: 10px; margin-bottom: 15px; border-radius: 8px;">';
            html += '<h6 style="color: #495057; margin-bottom: 10px;"><i class="fas fa-history"></i> Older</h6>';
            older.forEach(booking => {
                html += formatBookingCard(booking, '#f5f5f5');
            });
            html += '</div>';
        }
        
        html += '</div>';
        document.getElementById('panelContent').innerHTML = html;
        
        // Initialize tooltips for canceled bookings
        setTimeout(() => {
            const tooltipTriggerList = document.querySelectorAll('#panelContent [data-bs-toggle="tooltip"]');
            [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
        }, 50);
    }
    
    function displayCanceled(data) {
        if (!data.bookings || data.bookings.length === 0) {
            document.getElementById('panelContent').innerHTML = '<div class="alert alert-info">No canceled bookings found</div>';
            return;
        }
        
        let html = '<div class="canceled-list">';
        data.bookings.forEach(booking => {
            html += formatBookingCard(booking, '#f5f5f5', true);
        });
        html += '</div>';
        
        document.getElementById('panelContent').innerHTML = html;
        
        // Initialize tooltips for canceled bookings
        setTimeout(() => {
            const tooltipTriggerList = document.querySelectorAll('#panelContent [data-bs-toggle="tooltip"]');
            [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
        }, 50);
    }
    
    function displaySearchResults(data) {
        if (!data.bookings || data.bookings.length === 0) {
            document.getElementById('searchResults').innerHTML = '<div class="alert alert-info">No bookings found</div>';
            return;
        }
        
        // Get search term for highlighting
        const searchTerm = document.getElementById('searchInput').value.trim();
        const isIdSearch = /^\d+$/.test(searchTerm);
        
        let html = '<div class="search-results-list">';
        html += `<p class="text-muted mb-3">Found ${data.bookings.length} booking(s)</p>`;
        data.bookings.forEach(booking => {
            // Add match score indicator if available
            if (booking.match_score) {
                let scoreLabel = '';
                let scoreColor = '';
                if (booking.match_score === 10) {
                    scoreLabel = 'Exact Match';
                    scoreColor = '#28a745';
                } else if (booking.match_score >= 6) {
                    scoreLabel = 'Good Match';
                    scoreColor = '#17a2b8';
                } else {
                    scoreLabel = 'Partial Match';
                    scoreColor = '#ffc107';
                }
                html += `<div style="margin-bottom: 5px;">
                    <span style="font-size: 11px; color: ${scoreColor}; font-weight: 500;">
                        <i class="fas fa-check-circle"></i> ${scoreLabel}
                    </span>
                </div>`;
            }
            // Check if this is a canceled booking or past booking
            const isCanceled = booking.booking_status === 'canceled';
            const isPast = booking.time_status === 'past' && !isCanceled;
            // Use gray background for past bookings
            const bgColor = isPast ? '#e9ecef' : '#f5f5f5';
            html += formatBookingCard(booking, bgColor, isCanceled, searchTerm, isIdSearch);
        });
        html += '</div>';
        
        document.getElementById('searchResults').innerHTML = html;
        
        // Initialize tooltips for canceled bookings
        setTimeout(() => {
            const tooltipTriggerList = document.querySelectorAll('#searchResults [data-bs-toggle="tooltip"]');
            [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
        }, 50);
    }
    
    function formatBookingCard(booking, bgColor, isCanceled = false, searchTerm = '', isIdSearch = false) {
        const bookingDate = new Date(booking.booking_start_datetime);
        const currentYear = new Date().getFullYear();
        
        // Format date as "10:30 Monday 01.Dec 25" or without year if current year
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        const time = bookingDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
        const dayName = days[bookingDate.getDay()];
        const day = String(bookingDate.getDate()).padStart(2, '0');
        const month = months[bookingDate.getMonth()];
        const year = bookingDate.getFullYear();
        
        let dateStr = `${time} ${dayName} ${day}.${month}`;
        if (year !== currentYear) {
            dateStr += ` ${String(year).slice(-2)}`;
        }
        
        // Calculate time since creation for all panels (search, arrivals, etc)
        let timeSinceText = '';
        let timeColor = '#666'; // Default color
        
        // For search results, calculate time from day_of_creation
        if ((booking.hours_since_creation !== undefined && booking.hours_since_creation !== null) || 
            (searchTerm && booking.day_of_creation)) {
            
            let hours;
            if (booking.hours_since_creation !== undefined && booking.hours_since_creation !== null) {
                hours = parseFloat(booking.hours_since_creation);
            } else if (booking.day_of_creation) {
                // Calculate hours from day_of_creation for search results
                const creationDate = new Date(booking.day_of_creation);
                const now = new Date();
                hours = (now - creationDate) / (1000 * 60 * 60);
            }
            
            if (hours < 1) {
                const minutes = Math.round(hours * 60);
                timeSinceText = `${minutes} minute${minutes !== 1 ? 's' : ''} ago`;
                timeColor = '#d32f2f'; // Red for hot
            } else if (hours < 7) {
                // Show minutes with hours for less than 7 hours
                const wholeHours = Math.floor(hours);
                const minutes = Math.round((hours - wholeHours) * 60);
                if (minutes === 0) {
                    timeSinceText = `${wholeHours}h ago`;
                } else {
                    timeSinceText = `${wholeHours}h${minutes} ago`;
                }
                if (hours <= 2) {
                    timeColor = '#d32f2f'; // Red for hot
                } else if (hours <= 6) {
                    timeColor = '#f57c00'; // Orange for mild
                }
            } else if (hours < 24) {
                // Show only hours for 7-24 hours
                const wholeHours = Math.round(hours);
                timeSinceText = `${wholeHours}h ago`;
                timeColor = '#7b1fa2'; // Purple for recent
            } else {
                const days = Math.round(hours / 24);
                timeSinceText = `${days} day${days !== 1 ? 's' : ''} ago`;
                timeColor = '#616161'; // Grey for older
            }
        }
        
        // Format service name with first letter capital
        const serviceName = booking.service_name ? 
            booking.service_name.charAt(0).toUpperCase() + booking.service_name.slice(1).toLowerCase() : 
            'No Service';
        
        <?php if ($supervisor_mode): ?>
        // Supervisor mode - include specialist color
        const specialistColor = booking.specialist_color || '#667eea';
        <?php else: ?>
        // Specialist mode - use specialist's own color
        const specialistColor = '<?= $specialist_permissions['back_color'] ?? '#667eea' ?>';
        <?php endif; ?>
        
        // Generate unique ID for this card
        const cardId = 'booking-' + booking.unic_id + '-' + Math.random().toString(36).substr(2, 9);
        
        // Highlight search term in name if searching by name
        let displayName = booking.client_full_name || 'No Name';
        let displayId = booking.unic_id;
        
        if (searchTerm && !isIdSearch && booking.client_full_name) {
            // Highlight matching parts in name
            const searchWords = searchTerm.split(' ').filter(w => w.length > 0);
            searchWords.forEach(word => {
                const regex = new RegExp(`(${word})`, 'gi');
                displayName = displayName.replace(regex, '<strong>$1</strong>');
            });
        } else if (searchTerm && isIdSearch && booking.unic_id.toString() === searchTerm) {
            // Highlight ID if searching by ID
            displayId = `<strong>${displayId}</strong>`;
        }
        
        // Check if this is a past booking (completed)
        const isPast = booking.time_status === 'past' && !isCanceled;
        
        // Apply darker background and no border for past bookings in search
        const cardOuterStyle = (isPast && searchTerm) ? 
            `background-color: #ced4da; padding: 3px; margin-bottom: 8px; border-radius: 6px;` :
            (isPast ? 
                `background-color: ${bgColor}; padding: 3px; margin-bottom: 8px; border-radius: 6px; border: 1px solid #6c757d;` :
                `background-color: ${bgColor}; padding: 3px; margin-bottom: 8px; border-radius: 6px;`);
        
        // Determine tooltip based on booking status
        let tooltipText = '';
        if (isCanceled) {
            tooltipText = 'CANCELED Booking - Click to view';
        } else if (isPast) {
            tooltipText = 'PAST Booking - Click to view';
        } else {
            tooltipText = 'ACTIVE Booking - Click to view';
        }
        
        const cardInnerStyle = isPast ?
            `background-color: #f8f9fa; padding: 10px; border-radius: 4px; border-left: 4px solid #6c757d; cursor: pointer; position: relative; opacity: 0.85;` :
            `background-color: white; padding: 10px; border-radius: 4px; border-left: 4px solid ${specialistColor}; cursor: pointer; position: relative;`;
        
        let html = `
            <div class="booking-card-outer" style="${cardOuterStyle}">
                <div class="booking-card" style="${cardInnerStyle}" 
                     onclick="toggleBookingDetails('${cardId}')"
                     data-bs-toggle="tooltip" 
                     data-bs-placement="top" 
                     title="${tooltipText}">
                <!-- Two-line summary view -->
                <div class="booking-summary">
                    <!-- Line 1: ID Name • phone and received_through -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                        <div style="font-size: 14px; font-weight: 600; color: #333; ${isCanceled ? 'text-decoration: line-through; cursor: help;' : ''}"
                             ${isCanceled && booking.cancellation_time ? `
                             data-bs-toggle="tooltip" 
                             data-bs-placement="top" 
                             title="Canceled on ${new Date(booking.cancellation_time).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}${booking.canceled_by ? ' by ' + booking.canceled_by : ''}"
                             ` : ''}>
                            <span style="color: #999; font-weight: normal;">#${displayId}</span> ${displayName} ${booking.client_phone_nr ? `• <i class="fas fa-phone" style="color: ${specialistColor}; font-size: 12px;"></i> ${booking.client_phone_nr}` : ''}
                        </div>
                        <div style="font-size: 12px; color: #666;">
                            ${isPast ? '<i class="fas fa-check-circle" style="color: #6c757d;" title="Completed"></i> ' : ''}${booking.source || booking.received_through || 'Direct'}
                        </div>
                    </div>
                    
                    <!-- Line 2: Date, Service and Time since arrival -->
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="font-size: 12px; color: #666;">
                            ${dateStr} • ${serviceName}
                        </div>
                        <div style="font-size: 12px; color: ${timeColor}; font-weight: 600;">
                            ${isCanceled && booking.hours_since_cancellation !== undefined ? 'Canceled ' : ''}${timeSinceText}
                        </div>
                    </div>
                </div>
                
                <!-- Expandable details section -->
                <div id="${cardId}" class="booking-details" style="display: none; margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ddd;">
                    
                    <!-- Specialist info with calendar links -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <div style="font-size: 12px;">
                            <?php if ($supervisor_mode): ?>
                            <span style="display: inline-block; padding: 2px 8px; background-color: ${specialistColor}; color: ${booking.specialist_fg_color || '#fff'}; border-radius: 3px; cursor: help;"
                                  data-bs-toggle="tooltip" 
                                  data-bs-placement="top" 
                                  title="${booking.specialist_speciality ? booking.specialist_speciality.charAt(0).toUpperCase() + booking.specialist_speciality.slice(1).toLowerCase() : 'Specialist'}">
                                <i class="fas fa-user-md"></i> ${booking.specialist_name}
                            </span>
                            <?php else: ?>
                            <span style="display: inline-block; padding: 2px 8px; background-color: <?= $specialist_permissions['back_color'] ?? '#667eea' ?>; color: <?= $specialist_permissions['foreground_color'] ?? '#ffffff' ?>; border-radius: 3px; cursor: help;"
                                  data-bs-toggle="tooltip" 
                                  data-bs-placement="top" 
                                  title="<?= htmlspecialchars(ucfirst(strtolower($specialist['speciality']))) ?>">
                                <i class="fas fa-user-md"></i> <?= htmlspecialchars($specialist['name']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <a href="#" onclick="event.stopPropagation(); navigateToBookingDate('${booking.booking_date}', 'today', '${booking.id_specialist}')" 
                               style="text-decoration: none; margin-right: 10px; font-size: 20px;"
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="View in Daily Calendar">
                                📋
                            </a>
                            <a href="#" onclick="event.stopPropagation(); navigateToBookingDate('${booking.booking_date}', 'this_week', '${booking.id_specialist}')" 
                               style="text-decoration: none; font-size: 20px;"
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="View in Weekly Calendar">
                                📆
                            </a>
                        </div>
                    </div>
                    
                    ${isCanceled && booking.booking_status_text ? `
                    <div style="font-size: 11px; color: #dc3545; margin-bottom: 5px;">
                        <i class="fas fa-ban"></i> ${booking.booking_status_text}
                    </div>
                    ` : ''}
                </div>
                
                <!-- Dropdown indicator -->
                <div style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); color: #999; font-size: 10px;">
                    <i class="fas fa-chevron-down" id="chevron-${cardId}" style="transition: transform 0.2s;"></i>
                </div>
            </div>
            </div>
        `;
        
        return html;
    }
    
    function toggleBookingDetails(cardId) {
        const details = document.getElementById(cardId);
        const chevron = document.getElementById('chevron-' + cardId);
        
        if (details.style.display === 'none') {
            details.style.display = 'block';
            chevron.style.transform = 'rotate(180deg)';
            // Initialize tooltips for the newly revealed content
            setTimeout(() => {
                const tooltipTriggerList = details.querySelectorAll('[data-bs-toggle="tooltip"]');
                [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
            }, 50);
        } else {
            details.style.display = 'none';
            chevron.style.transform = 'rotate(0deg)';
            // Dispose tooltips when hiding
            const tooltipTriggerList = details.querySelectorAll('[data-bs-toggle="tooltip"]');
            [...tooltipTriggerList].forEach(tooltipTriggerEl => {
                const tooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                if (tooltip) tooltip.dispose();
            });
        }
    }
    
    function navigateToBookingDate(bookingDate, period = 'custom', specialistId = null) {
        let baseUrl;
        <?php if ($supervisor_mode): ?>
        // In supervisor mode, stay in supervisor mode
        baseUrl = 'booking_view_page.php?supervisor_mode=true&working_point_user_id=<?= $working_point_user_id ?>';
        <?php else: ?>
        // In specialist mode, use the current specialist ID
        baseUrl = 'booking_view_page.php?specialist_id=<?= $specialist_id ?>';
        <?php endif; ?>
        
        // Close the panel
        closeRightPanel();
        
        // Navigate to the specific date with the requested period
        <?php if ($supervisor_mode): ?>
        // In supervisor mode, add the selected specialist ID to the URL
        const specialistParam = specialistId ? '&selected_specialist=' + specialistId : '';
        <?php else: ?>
        const specialistParam = '';
        <?php endif; ?>
        
        if (period === 'today') {
            window.location.href = baseUrl + '&period=custom&start_date=' + bookingDate + '&end_date=' + bookingDate + specialistParam;
        } else if (period === 'this_week') {
            // Calculate the week that contains the booking date
            const date = new Date(bookingDate);
            const day = date.getDay();
            const diff = date.getDate() - day + (day === 0 ? -6 : 1); // Adjust for Sunday
            const monday = new Date(date.setDate(diff));
            const sunday = new Date(date.setDate(monday.getDate() + 6));
            
            const startDate = monday.toISOString().split('T')[0];
            const endDate = sunday.toISOString().split('T')[0];
            
            window.location.href = baseUrl + '&period=custom&start_date=' + startDate + '&end_date=' + endDate + specialistParam;
        } else {
            window.location.href = baseUrl + '&period=custom&start_date=' + bookingDate + '&end_date=' + bookingDate + specialistParam;
        }
    }
    
    </script>
    <script src="assets/js/add_specialist_modal.js"></script>
</body>
</html>
