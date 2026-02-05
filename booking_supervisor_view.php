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

// This is always supervisor mode
$supervisor_mode = true; // FORCED: Always supervisor mode in this file
$working_point_user_id = $_GET['working_point_user_id'] ?? null;

if (!$working_point_user_id) {
    die("Working Point ID is required for supervisor view");
}

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

// Set timezone based on working point
if ($workpoint) {
    setTimezoneForWorkingPoint($workpoint);
} else {
    // Fallback: use organization timezone
    setTimezoneForOrganisation($organisation);
}

// Get all specialists with at least one non-zero shift at this workpoint
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
    
// Get current period selection
$period = $_GET['period'] ?? 'this_month';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Calculate date range and design based on period
$date_range = calculateDateRange($period, $start_date, $end_date);
$start_date = $date_range['start'];
$end_date = $date_range['end'];
$calendar_design = $date_range['design'];

// Debug: Log the calendar design
error_log("Calendar Design: " . $calendar_design . " for period: " . $period . " start: " . $start_date . " end: " . $end_date);

// Get bookings for the selected period - all bookings for this workpoint
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

// Get workpoint holidays (both recurring and non-recurring)
$workpoint_holidays = [];
if (isset($workpoint_id)) {
    $stmt = $pdo->prepare("
        SELECT date_off, start_time, end_time, is_recurring, description
        FROM workingpoint_time_off
        WHERE workingpoint_id = ?
        ORDER BY date_off
    ");
    $stmt->execute([$workpoint_id]);
    $workpoint_holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Calendar Booking System - <?= htmlspecialchars($workpoint['name_of_the_place']) . ' (Supervisor View)' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css?v=<?= time() ?>" rel="stylesheet">
    <link href="assets/css/booking_view_page.css?v=<?= time() ?>" rel="stylesheet">
    <style>
        /* Fix for dropdown menus appearing under other elements */
        .dropdown-menu {
            z-index: 999999 !important;
            position: absolute !important;
        }

        .dropdown {
            position: relative !important;
        }

        /* Ensure title-box doesn't interfere with dropdowns */
        .title-box {
            z-index: 100;
            position: relative;
        }

        /* Ensure dropdowns appear above calendar and other content */
        .calendar-section, .sidebar, .widget {
            z-index: 1;
            position: relative;
        }
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
                    <small style="color: var(--primary-color); font-weight: 600;"><?= date('l, F j, Y') ?></small>
                    <span class="time-badge" id="currentTimeClock" style="margin-left: 8px; padding: 3px 6px;"><?= date('H:i:s') ?></span>
                    <br>
                    <small style="color: #666;">
                        <?php
                        $timezone_str = $supervisor_mode && $workpoint ? getTimezoneForWorkingPoint($workpoint) :
                            (!$supervisor_mode && !empty($working_points) ? getTimezoneForWorkingPoint($working_points[0]) :
                            getTimezoneForOrganisation($organisation));

                        // Calculate GMT offset
                        $tz = new DateTimeZone($timezone_str);
                        $datetime = new DateTime('now', $tz);
                        $offset_seconds = $tz->getOffset($datetime);
                        $offset_hours = $offset_seconds / 3600;
                        $gmt_offset = sprintf("GMT%+d", $offset_hours);
                        ?>
                        <?= $timezone_str ?> <span style="font-size: 11px;">(<?= $gmt_offset ?>)</span>

                        <!-- Language Dropdown -->
                        <div class="dropdown" style="display: inline-block; margin-left: 8px; margin-right: 8px; position: relative;">
                            <button class="btn btn-sm dropdown-toggle" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                                    style="padding: 1px 6px; font-size: 12px; background-color: #f8f9fa; border: 1px solid #dee2e6; line-height: 1.3;">
                                <?= strtoupper($lang) ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown" style="min-width: 80px; z-index: 999999 !important;">
                                <li><a class="dropdown-item <?= $lang === 'en' ? 'active' : '' ?>" href="#" onclick="changeLanguage('en'); return false;" style="font-size: 12px;">English</a></li>
                                <li><a class="dropdown-item <?= $lang === 'ro' ? 'active' : '' ?>" href="#" onclick="changeLanguage('ro'); return false;" style="font-size: 12px;">Română</a></li>
                                <li><a class="dropdown-item <?= $lang === 'lt' ? 'active' : '' ?>" href="#" onclick="changeLanguage('lt'); return false;" style="font-size: 12px;">Lietuvių</a></li>
                            </ul>
                        </div>

                        <!-- SSE Status Indicator -->
                        <span id="realtime-status-btn" onclick="toggleRealtimeUpdates()" style="cursor: pointer; display: inline-block; margin-left: 5px; vertical-align: middle;" title="Real-time booking updates via SSE (Server-Sent Events) - Connecting...">
                            <i class="status-icon fas fa-circle" style="font-size: 12px; color: #ffc107; transition: color 0.3s ease;"></i>
                        </span>
                    </small>
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
                <div style="margin-top: 10px;">
                    <small>Usr: <?= htmlspecialchars($_SESSION['user']) ?>
                    <?php if ($supervisor_mode): ?>
                    | <a href="workpoint_supervisor_dashboard.php" style="color: var(--primary-color); text-decoration: none;">Dashboard</a>
                    <?php endif; ?>
                    | <a href="logout.php" style="color: var(--danger-color); text-decoration: none;"><?= $LANG['logout'] ?? 'Logout' ?></a>
                    </small>

                    <!-- Menu Dropdown -->
                    <div style="margin-top: 5px;">
                        <div class="dropdown" style="display: inline-block;">
                            <button class="btn btn-sm" type="button" id="menuDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                                    style="padding: 4px 8px; font-size: 14px; background-color: #f8f9fa; border: 1px solid #dee2e6; display: flex; align-items: center; gap: 5px;">
                                <i class="fas fa-bars" style="font-size: 14px;"></i>
                                <span style="font-size: 12px;">Working Point Config.Settings</span>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="menuDropdown" style="z-index: 999999 !important;">
                                <li><a class="dropdown-item" href="#" onclick="openCommunicationSetup(); return false;" style="font-size: 13px;">
                                    <i class="fab fa-whatsapp" style="width: 20px; color: #25D366;"></i> Communication Setup</a></li>
                                <li><a class="dropdown-item" href="#" onclick="openSMSConfirmationSetup(); return false;" style="font-size: 13px;">
                                    <i class="fas fa-sms" style="width: 20px; color: #1e88e5;"></i> SMS Confirmation Setup</a></li>
                                <li><a class="dropdown-item" href="#" onclick="openWorkpointHolidays(); return false;" style="font-size: 13px;">
                                    <i class="fas fa-calendar-times" style="width: 20px; color: #FFA500;"></i> Workpoint Holidays & Closures</a></li>
                                <li><a class="dropdown-item" href="#" onclick="openManageServices(); return false;" style="font-size: 13px;">
                                    <i class="fas fa-cogs" style="width: 20px;"></i> Manage Services</a></li>
                                <li><a class="dropdown-item" href="#" onclick="openStatistics(); return false;" style="font-size: 13px;">
                                    <i class="fas fa-chart-bar" style="width: 20px;"></i> Statistics</a></li>
                            </ul>
                        </div>
                    </div>
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
                                                     style="border: none; border-bottom: 1px solid #e0e0e0; border-radius: 8px; padding: 5px; margin-bottom: 10px; background-color: #fafafa; transition: all 0.2s ease;"
                                                     onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'; this.style.transform='translateY(-2px)';"
                                                     onmouseout="const tabs = this.querySelector('.specialist-tabs-container'); if (!tabs || tabs.style.display === 'none' || tabs.style.display === '') { this.style.boxShadow='none'; this.style.transform='translateY(0)'; }">
                                                    <div class="working-point-header specialist-header" style="cursor: pointer; font-weight: normal; display: block; position: relative; min-height: 24px; padding-top: 8px; margin-bottom: 0;" onclick="(function(event) {
                                                        const specialistId = '<?= $spec['unic_id'] ?>';
                                                        const specialistSection = event.currentTarget.closest('.specialist-collapsible');
                                                        const infoLine = specialistSection.querySelector('.specialist-info-line');
                                                        let tabsContainer = specialistSection.querySelector('.specialist-tabs-container');

                                                        // Switch to this specialist in the weekly view if the function exists
                                                        if (typeof window.switchSpecialist === 'function') {
                                                            window.switchSpecialist(specialistId);
                                                        }

                                                        if (!tabsContainer) {
                                                            // Initialize tabs for the first time
                                                            SpecialistTabs.initialize(specialistId);
                                                            tabsContainer = specialistSection.querySelector('.specialist-tabs-container');
                                                        }

                                                        // Toggle visibility
                                                        if (tabsContainer) {
                                                            if (tabsContainer.style.display === 'none' || tabsContainer.style.display === '') {
                                                                tabsContainer.style.display = 'block';
                                                                if (infoLine) infoLine.style.display = 'none';
                                                                // Show full border and shadow when expanded
                                                                specialistSection.style.border = '1px solid #e0e0e0';
                                                                specialistSection.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                                                                specialistSection.style.transform = 'translateY(-2px)';
                                                            } else {
                                                                tabsContainer.style.display = 'none';
                                                                if (infoLine) infoLine.style.display = 'block';
                                                                // Show only bottom border and remove shadow when collapsed
                                                                specialistSection.style.border = 'none';
                                                                specialistSection.style.borderBottom = '1px solid #e0e0e0';
                                                                specialistSection.style.boxShadow = 'none';
                                                                specialistSection.style.transform = 'translateY(0)';
                                                            }
                                                        } else {
                                                            // Fallback to old behavior
                                                            const scheduleContent = specialistSection.querySelector('.schedule-content');
                                                            if (scheduleContent) {
                                                                if (scheduleContent.style.display === 'none' || scheduleContent.style.display === '') {
                                                                    scheduleContent.style.display = 'block';
                                                                    if (infoLine) infoLine.style.display = 'none';
                                                                    // Show full border and shadow when expanded
                                                                    specialistSection.style.border = '1px solid #e0e0e0';
                                                                    specialistSection.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                                                                    specialistSection.style.transform = 'translateY(-2px)';
                                                                } else {
                                                                    scheduleContent.style.display = 'none';
                                                                    if (infoLine) infoLine.style.display = 'block';
                                                                    // Show only bottom border and remove shadow when collapsed
                                                                    specialistSection.style.border = 'none';
                                                                    specialistSection.style.borderBottom = '1px solid #e0e0e0';
                                                                    specialistSection.style.boxShadow = 'none';
                                                                    specialistSection.style.transform = 'translateY(0)';
                                                                }
                                                            }
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
                                                        <div class="specialist-info-line" style="color: #6c757d; font-size: 0.9em; margin-top: -3px; text-align: center;">
                                                            <?= ucfirst(strtolower(htmlspecialchars($spec['speciality']))) ?> • <span style="cursor: help;"
                                                                  data-bs-toggle="tooltip"
                                                                  data-bs-placement="top"
                                                                  data-bs-html="true"
                                                                  title="Phone visible to clients: <?= ($spec_visibility['specialist_nr_visible_to_client'] ?? 0) ? '<span style=\'color: green;\'>✓ On</span>' : '<span style=\'color: red;\'>✗ Off</span>' ?>">
                                                                <i class="fas fa-phone" style="font-size: 0.8em;"></i> <?= htmlspecialchars($spec['phone_nr']) ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="working-point-item schedule-content" style="display: none; width: 100%; text-align: center; cursor: pointer; transition: background-color 0.3s ease;" 
                                                         onclick="event.stopPropagation(); openModifyScheduleModal('<?= $spec['unic_id'] ?>', '<?= $workpoint['unic_id'] ?>')"
                                                         onmouseover="this.style.backgroundColor='rgba(0, 123, 255, 0.05)'"
                                                         onmouseout="this.style.backgroundColor='transparent'"
                                                         title="Click to modify schedule">
                                                        <div class="working-program" style="font-size: 0.7em; padding-top: 8px;">
                                                            <?php
                                                            if (!empty($spec_program)) {
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
                                                        <?php
                                                        // Display Days Off for this specialist
                                                        $stmt_time_off = $pdo->prepare("
                                                            SELECT date_off, start_time, end_time
                                                            FROM specialist_time_off
                                                            WHERE specialist_id = ? AND date_off >= CURDATE()
                                                            ORDER BY date_off, id
                                                        ");
                                                        $stmt_time_off->execute([$spec['unic_id']]);
                                                        $time_off_records = $stmt_time_off->fetchAll(PDO::FETCH_ASSOC);

                                                        if (!empty($time_off_records)) {
                                                            // Group by date
                                                            $time_off_by_date = [];
                                                            foreach ($time_off_records as $record) {
                                                                $date = $record['date_off'];
                                                                if (!isset($time_off_by_date[$date])) {
                                                                    $time_off_by_date[$date] = [];
                                                                }
                                                                $time_off_by_date[$date][] = $record;
                                                            }

                                                            echo '<div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed #dee2e6;">';

                                                            foreach ($time_off_by_date as $date => $records) {
                                                                $date_obj = new DateTime($date);
                                                                $formatted_date = $date_obj->format('D, j M');

                                                                // Determine if full day or partial
                                                                if (count($records) === 1 &&
                                                                    $records[0]['start_time'] === '00:01:00' &&
                                                                    $records[0]['end_time'] === '23:59:00') {
                                                                    // Full day off
                                                                    echo '<div style="font-size: 0.75em; color: #999; margin-bottom: 3px;">';
                                                                    echo '<span style="color: #dc3545;">⊗</span> ' . $formatted_date . '. <span style="font-style: italic;">Full Day OFF</span>';
                                                                    echo '</div>';
                                                                } else if (count($records) >= 2) {
                                                                    // Partial day - calculate working hours
                                                                    $record1 = $records[0];
                                                                    $record2 = $records[1];

                                                                    $workStartTime = strtotime("1970-01-01 " . $record1['end_time']) + 60;
                                                                    $workStart = date('H:i', $workStartTime);

                                                                    $workEndTime = strtotime("1970-01-01 " . $record2['start_time']) - 60;
                                                                    $workEnd = date('H:i', $workEndTime);

                                                                    echo '<div style="font-size: 0.75em; color: #999; margin-bottom: 3px;">';
                                                                    echo '<span style="color: #f59e0b; font-size: 1.2em;">◐</span> ' . $formatted_date . '. <span style="font-style: italic;">Partial Day OFF</span><br>';
                                                                    echo '<span style="font-size: 0.9em; color: #aaa; margin-left: 12px;">(working only: ' . substr($workStart, 0, 2) . '<sup>' . substr($workStart, 3, 2) . '</sup>-' . substr($workEnd, 0, 2) . '<sup>' . substr($workEnd, 3, 2) . '</sup>)</span>';
                                                                    echo '</div>';
                                                                }
                                                            }

                                                            echo '</div>';
                                                        }
                                                        ?>
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
                                                            <?php
                                                            // Display Days Off for this specialist
                                                            $stmt_time_off = $pdo->prepare("
                                                                SELECT date_off, start_time, end_time
                                                                FROM specialist_time_off
                                                                WHERE specialist_id = ? AND date_off >= CURDATE()
                                                                ORDER BY date_off, id
                                                            ");
                                                            $stmt_time_off->execute([$specialist_id]);
                                                            $time_off_records = $stmt_time_off->fetchAll(PDO::FETCH_ASSOC);

                                                            if (!empty($time_off_records)) {
                                                                // Group by date
                                                                $time_off_by_date = [];
                                                                foreach ($time_off_records as $record) {
                                                                    $date = $record['date_off'];
                                                                    if (!isset($time_off_by_date[$date])) {
                                                                        $time_off_by_date[$date] = [];
                                                                    }
                                                                    $time_off_by_date[$date][] = $record;
                                                                }

                                                                echo '<div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed #dee2e6;">';

                                                                foreach ($time_off_by_date as $date => $records) {
                                                                    $date_obj = new DateTime($date);
                                                                    $formatted_date = $date_obj->format('D, j M');

                                                                    // Determine if full day or partial
                                                                    if (count($records) === 1 &&
                                                                        $records[0]['start_time'] === '00:01:00' &&
                                                                        $records[0]['end_time'] === '23:59:00') {
                                                                        // Full day off
                                                                        echo '<div style="font-size: 0.75em; color: #999; margin-bottom: 3px;">';
                                                                        echo '<span style="color: #dc3545;">⊗</span> ' . $formatted_date . '. <span style="font-style: italic;">Full Day OFF</span>';
                                                                        echo '</div>';
                                                                    } else if (count($records) >= 2) {
                                                                        // Partial day - calculate working hours
                                                                        $record1 = $records[0];
                                                                        $record2 = $records[1];

                                                                        $workStartTime = strtotime("1970-01-01 " . $record1['end_time']) + 60;
                                                                        $workStart = date('H:i', $workStartTime);

                                                                        $workEndTime = strtotime("1970-01-01 " . $record2['start_time']) - 60;
                                                                        $workEnd = date('H:i', $workEndTime);

                                                                        echo '<div style="font-size: 0.75em; color: #999; margin-bottom: 3px;">';
                                                                        echo '<span style="color: #f59e0b; font-size: 1.2em;">◐</span> ' . $formatted_date . '. <span style="font-style: italic;">Partial Day OFF</span><br>';
                                                                        echo '<span style="font-size: 0.9em; color: #aaa; margin-left: 12px;">(working only: ' . substr($workStart, 0, 2) . '<sup>' . substr($workStart, 3, 2) . '</sup>-' . substr($workEnd, 0, 2) . '<sup>' . substr($workEnd, 3, 2) . '</sup>)</span>';
                                                                        echo '</div>';
                                                                    }
                                                                }

                                                                echo '</div>';
                                                            }
                                                            ?>
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
                                <input type="hidden" name="working_point_user_id" value="<?= $working_point_user_id ?>">
                                
                                <div class="period-option">
                                    <input type="radio" name="period" value="today" id="today" <?= $period === 'today' ? 'checked' : '' ?> onclick="changePeriod('today', event); return false;">
                                    <label for="today" onclick="changePeriod('today', event); return false;">Today</label>
                                </div>

                                <div class="period-option">
                                    <input type="radio" name="period" value="tomorrow" id="tomorrow" <?= $period === 'tomorrow' ? 'checked' : '' ?> onclick="changePeriod('tomorrow', event); return false;">
                                    <label for="tomorrow" onclick="changePeriod('tomorrow', event); return false;">Tomorrow</label>
                                </div>

                                <div class="period-option">
                                    <input type="radio" name="period" value="this_week" id="this_week" <?= $period === 'this_week' ? 'checked' : '' ?> onclick="changePeriod('this_week', event); return false;">
                                    <label for="this_week" onclick="changePeriod('this_week', event); return false;">This Week</label>
                                </div>

                                <div class="period-option">
                                    <input type="radio" name="period" value="next_week" id="next_week" <?= $period === 'next_week' ? 'checked' : '' ?> onclick="changePeriod('next_week', event); return false;">
                                    <label for="next_week" onclick="changePeriod('next_week', event); return false;">Next Week</label>
                                </div>

                                <div class="period-option">
                                    <input type="radio" name="period" value="this_month" id="this_month" <?= $period === 'this_month' ? 'checked' : '' ?> onclick="changePeriod('this_month', event); return false;">
                                    <label for="this_month" onclick="changePeriod('this_month', event); return false;">This Month</label>
                                </div>

                                <div class="period-option">
                                    <input type="radio" name="period" value="next_month" id="next_month" <?= $period === 'next_month' ? 'checked' : '' ?> onclick="changePeriod('next_month', event); return false;">
                                    <label for="next_month" onclick="changePeriod('next_month', event); return false;">Next Month</label>
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
    <script src="assets/js/modal-loader.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/modal-wrappers.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/calendar-navigation.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/specialist-tabs.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/modal-debug.js?v=<?php echo time(); ?>"></script> <!-- Temporary debug helper -->
    <script>
    // Google Calendar integration completely removed from supervisor mode
    </script>
    <script>
        // Timezone for JavaScript (working point if available, otherwise organization)
        const organizationTimezone = '<?= 
            $supervisor_mode && $workpoint ? getTimezoneForWorkingPoint($workpoint) : 
            (!$supervisor_mode && !empty($working_points) ? getTimezoneForWorkingPoint($working_points[0]) : 
            getTimezoneForOrganisation($organisation)) 
        ?>';
        
        // Prevent form submission on load
        document.addEventListener('DOMContentLoaded', function() {
            const periodForm = document.getElementById('periodForm');
            if (periodForm) {
                periodForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    return false;
                });
            }
        });

        // Period change function - must be defined before HTML that uses it
        function changePeriod(period, event) {
            // Prevent any form submission
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            // Build URL without unnecessary parameters for predefined periods
            const url = new URL(window.location);

            // Debug output



            // Make sure we stay on the supervisor view page
            if (!window.location.pathname.includes('booking_supervisor_view.php')) {
                console.error('WARNING: Not on supervisor view page! Redirecting...');
                url.pathname = '/booking_supervisor_view.php';
            }

            url.searchParams.set('period', period);

            // Handle supervisor mode
            url.searchParams.set('working_point_user_id', '<?= $working_point_user_id ?>');

            // Always include selected specialist in supervisor mode
            const currentSelectedSpecialist = url.searchParams.get('selected_specialist') || '<?= $selected_specialist ?>';

            if (currentSelectedSpecialist) {
                url.searchParams.set('selected_specialist', currentSelectedSpecialist);
            }

            // Remove any custom date parameters for predefined periods
            url.searchParams.delete('start_date');
            url.searchParams.delete('end_date');

            window.location.href = url.toString();
        }
        
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
        
        // Real-time booking updates - Lazy loaded
        let realtimeBookingsInitialized = false;
        function initializeRealtimeBookings() {
            if (!realtimeBookingsInitialized) {
                // Load utilities first, then realtime handler
                const script = document.createElement('script');
                script.src = 'assets/js/utilities.js?v=' + Date.now();
                script.onload = function() {
                    // Now load realtime handler
                    const realtimeScript = document.createElement('script');
                    realtimeScript.src = 'assets/js/realtime-booking-handler.js?v=' + Date.now();
                    realtimeScript.onload = function() {
                        realtimeBookingsInitialized = true;
                        // Initialize with configuration
                        window.RealtimeBookingHandler.initialize({
                            specialistId: null,
                            workpointId: <?= $working_point_user_id ?? 'null' ?>,
                            supervisorMode: true,
                            debug: true
                        });
                    };
                    document.head.appendChild(realtimeScript);
                };
                document.head.appendChild(script);
            }
        }

        // Stub functions until real module loads
        function toggleRealtimeUpdates() {
            if (window.RealtimeBookingHandler) {
                window.RealtimeBookingHandler.toggle();
            }
        }

        function showBookingNotification() {
            if (window.RealtimeBookingHandler) {
                window.RealtimeBookingHandler.showNotification();
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
        


        // Add Specialist Modal - Using centralized modal loader
        window.openAddSpecialistModal = function(workpointId, organisationId) {
            ModalLoader.open('add-specialist', workpointId, organisationId).catch(error => {
                console.error('Failed to open Add Specialist modal:', error);
                alert('Failed to load Add Specialist functionality. Please try again.');
            });
        }

        // Function stub for onSelectUnassignedSpecialist (needed for orphan specialists dropdown)
        function onSelectUnassignedSpecialist(specialistId) {
            if (!specialistId) return;
            // Use the current workpoint ID from window object (set by PHP)
            const currentWorkpointId = window.currentWorkpointId || <?= json_encode($workpoint_id ?? 0) ?>;
            openModifyScheduleModal(specialistId, currentWorkpointId);
            // Reset selection so it can be re-used
            const sel = document.getElementById('unassignedSpecialistsSelect');
            if (sel) sel.value = '';
        }

        // Stub functions for Add Specialist form - these use the modal loader
        function submitAddSpecialist() {
            if (ModalLoader.isLoaded('add-specialist')) {
                if (typeof window.submitAddSpecialist === 'function') {
                    window.submitAddSpecialist();
                }
            } else {
                ModalLoader.load('add-specialist').then(() => {
                    if (typeof window.submitAddSpecialist === 'function') {
                        window.submitAddSpecialist();
                    }
                });
            }
        }

        window.handleSpecialistSelection = function() {
            if (ModalLoader.isLoaded('add-specialist')) {
                if (typeof window.handleSpecialistSelectionReal === 'function') {
                    window.handleSpecialistSelectionReal();
                }
            } else {
                ModalLoader.load('add-specialist').then(() => {
                    if (typeof window.handleSpecialistSelectionReal === 'function') {
                        window.handleSpecialistSelectionReal();
                    }
                });
            }
        }

        function clearShift(button, shiftNum) {
            if (ModalLoader.isLoaded('add-specialist')) {
                if (typeof window.clearShift === 'function') {
                    window.clearShift(button, shiftNum);
                }
            } else {
                ModalLoader.load('add-specialist').then(() => {
                    if (typeof window.clearShift === 'function') {
                        window.clearShift(button, shiftNum);
                    }
                });
            }
        }

        function applyAllShifts() {
            if (ModalLoader.isLoaded('add-specialist')) {
                if (typeof window.applyAllShifts === 'function') {
                    window.applyAllShifts();
                }
            } else {
                ModalLoader.load('add-specialist').then(() => {
                    if (typeof window.applyAllShifts === 'function') {
                        window.applyAllShifts();
                    }
                });
            }
        }

        function closeAddSpecialistModal() {
            if (!window.addSpecialistModalLoaded) {
                // If module not loaded, just hide the modal directly
                const modal = document.getElementById('addSpecialistModal');
                if (modal) modal.style.display = 'none';
            } else {
                if (typeof window.closeAddSpecialistModal === 'function') {
                    window.closeAddSpecialistModal();
                }
            }
        }

        // Modify Specialist Modal - Lazy Loading
        let modifySpecialistModalLoaded = false;

        // Lazy loading wrapper for Modify Specialist Modal
        function openModifySpecialistModal(specialistId, specialistName, workpointId) {
            if (!modifySpecialistModalLoaded) {
                // Load the Modify Specialist script
                const script = document.createElement('script');
                script.src = 'assets/js/modals/modify-specialist.js?v=' + Date.now();
                script.onload = function() {
                    modifySpecialistModalLoaded = true;
                    // Now call the real function
                    if (typeof window.openModifySpecialistModal === 'function') {
                        window.openModifySpecialistModal(specialistId, specialistName, workpointId);
                    }
                };
                script.onerror = function() {
                    console.error('Failed to load Modify Specialist modal script');
                    alert('Failed to load Modify Specialist functionality. Please try again.');
                };
                document.head.appendChild(script);
            } else {
                // Already loaded, call the real function
                if (typeof window.openModifySpecialistModalReal === 'function') {
                    window.openModifySpecialistModalReal(specialistId, specialistName, workpointId);
                }
            }
        }

        // Stub functions for Modify Specialist form - these load the module if needed
        function addNewService() {
            if (!modifySpecialistModalLoaded) {
                const script = document.createElement('script');
                script.src = 'assets/js/modals/modify-specialist.js?v=' + Date.now();
                script.onload = function() {
                    modifySpecialistModalLoaded = true;
                    if (typeof window.addNewService === 'function') {
                        window.addNewService();
                    }
                };
                document.head.appendChild(script);
            } else {
                if (typeof window.addNewService === 'function') {
                    window.addNewService();
                }
            }
        }

        function updateSpecialistDetails() {
            if (!modifySpecialistModalLoaded) {
                const script = document.createElement('script');
                script.src = 'assets/js/modals/modify-specialist.js?v=' + Date.now();
                script.onload = function() {
                    modifySpecialistModalLoaded = true;
                    if (typeof window.updateSpecialistDetails === 'function') {
                        window.updateSpecialistDetails();
                    }
                };
                document.head.appendChild(script);
            } else {
                if (typeof window.updateSpecialistDetails === 'function') {
                    window.updateSpecialistDetails();
                }
            }
        }

        // Communication Setup Modal - Lazy Loading
        window.communicationSetupModalLoaded = false;
        window.communicationSetupModalHtmlLoaded = false;

        // Lazy loading wrapper for Communication Setup Modal
        window.openCommunicationSetup = function() {
            // First check if HTML is loaded
            if (!window.communicationSetupModalHtmlLoaded) {
                // Load the modal HTML from assets directory (more accessible)
                fetch('assets/modals/communication-setup-modal.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.text();
                    })
                    .then(html => {
                        // Create a temporary container
                        const temp = document.createElement('div');
                        temp.innerHTML = html;

                        // Append all child elements to body
                        while (temp.firstElementChild) {
                            document.body.appendChild(temp.firstElementChild);
                        }

                        window.communicationSetupModalHtmlLoaded = true;

                        // Now load the JavaScript if not already loaded
                        if (!window.communicationSetupModalLoaded) {
                            const script = document.createElement('script');
                            script.src = 'assets/js/modals/communication-setup.js?v=' + Date.now();
                            script.onload = function() {
                                window.communicationSetupModalLoaded = true;
                                // Call the real function
                                if (typeof window.openCommunicationSetupModalReal === 'function') {
                                    window.openCommunicationSetupModalReal();
                                }
                            };
                            script.onerror = function() {
                                console.error('Failed to load communication-setup.js module');
                                alert('Failed to load Communication Setup module. Please try again.');
                            };
                            document.head.appendChild(script);
                        } else {
                            // JavaScript already loaded, just open the modal
                            if (typeof window.openCommunicationSetupModalReal === 'function') {
                                window.openCommunicationSetupModalReal();
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Failed to load Communication Setup modal HTML:', error);
                        alert('Failed to load Communication Setup. Please try again.');
                    });
            } else {
                // HTML already loaded, check if JavaScript is loaded
                if (!window.communicationSetupModalLoaded) {
                    const script = document.createElement('script');
                    script.src = 'assets/js/modals/communication-setup.js?v=' + Date.now();
                    script.onload = function() {
                        window.communicationSetupModalLoaded = true;
                        if (typeof window.openCommunicationSetupModalReal === 'function') {
                            window.openCommunicationSetupModalReal();
                        }
                    };
                    script.onerror = function() {
                        console.error('Failed to load communication-setup.js module');
                        alert('Failed to load Communication Setup module. Please try again.');
                    };
                    document.head.appendChild(script);
                } else {
                    // Everything already loaded, just open the modal
                    if (typeof window.openCommunicationSetupModalReal === 'function') {
                        window.openCommunicationSetupModalReal();
                    }
                }
            }
        }

        // Lazy loading flags for Manage Services Modal
        let manageServicesModalHtmlLoaded = false;
        let manageServicesModalLoaded = false;

        // Lazy loading flags for Statistics Modal
        let statisticsModalHtmlLoaded = false;
        let statisticsModalLoaded = false;

        // Lazy loading wrapper for Manage Services Modal
        function openManageServices() {
            // First check if HTML is loaded
            if (!manageServicesModalHtmlLoaded) {
                // Load the modal HTML from assets directory
                fetch('assets/modals/manage-services-modal.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.text();
                    })
                    .then(html => {
                        // Create a temporary container
                        const temp = document.createElement('div');
                        temp.innerHTML = html;

                        // Append all child elements to body
                        while (temp.firstElementChild) {
                            document.body.appendChild(temp.firstElementChild);
                        }

                        manageServicesModalHtmlLoaded = true;

                        // Now load the JavaScript if not already loaded
                        if (!manageServicesModalLoaded) {
                            const script = document.createElement('script');
                            script.src = 'assets/js/modals/manage-services.js?v=' + Date.now();
                            script.onload = function() {
                                manageServicesModalLoaded = true;
                                // Call the real function
                                if (typeof window.openServicesManagementModalReal === 'function') {
                                    window.openServicesManagementModalReal();
                                }
                            };
                            script.onerror = function() {
                                console.error('Failed to load manage-services.js module');
                                alert('Failed to load Manage Services module. Please try again.');
                            };
                            document.head.appendChild(script);
                        } else {
                            // JavaScript already loaded, just open the modal
                            if (typeof window.openServicesManagementModalReal === 'function') {
                                window.openServicesManagementModalReal();
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Failed to load Manage Services modal HTML:', error);
                        alert('Failed to load Manage Services. Please try again.');
                    });
            } else {
                // HTML already loaded, check JavaScript
                if (!manageServicesModalLoaded) {
                    const script = document.createElement('script');
                    script.src = 'assets/js/modals/manage-services.js?v=' + Date.now();
                    script.onload = function() {
                        manageServicesModalLoaded = true;
                        // Call the real function
                        if (typeof window.openServicesManagementModalReal === 'function') {
                            window.openServicesManagementModalReal();
                        }
                    };
                    script.onerror = function() {
                        console.error('Failed to load manage-services.js module');
                        alert('Failed to load Manage Services module. Please try again.');
                    };
                    document.head.appendChild(script);
                } else {
                    // Everything already loaded, just open the modal
                    if (typeof window.openServicesManagementModalReal === 'function') {
                        window.openServicesManagementModalReal();
                    }
                }
            }
        }

        // Lazy loading wrapper for Statistics Modal
        function openStatistics() {
            // First check if HTML is loaded
            if (!statisticsModalHtmlLoaded) {
                // Load the modal HTML from assets directory
                fetch('assets/modals/statistics-modal.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.text();
                    })
                    .then(html => {
                        // Create a temporary container
                        const temp = document.createElement('div');
                        temp.innerHTML = html;

                        // Append all child elements to body
                        while (temp.firstElementChild) {
                            document.body.appendChild(temp.firstElementChild);
                        }

                        statisticsModalHtmlLoaded = true;

                        // Now load the JavaScript if not already loaded
                        if (!statisticsModalLoaded) {
                            const script = document.createElement('script');
                            script.src = 'assets/js/modals/statistics.js?v=' + Date.now();
                            script.onload = function() {
                                statisticsModalLoaded = true;
                                // Call the real function
                                if (typeof window.openStatisticsModalReal === 'function') {
                                    window.openStatisticsModalReal();
                                }
                            };
                            script.onerror = function() {
                                console.error('Failed to load statistics.js module');
                                alert('Failed to load Statistics module. Please try again.');
                            };
                            document.head.appendChild(script);
                        } else {
                            // JavaScript already loaded, just open the modal
                            if (typeof window.openStatisticsModalReal === 'function') {
                                window.openStatisticsModalReal();
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Failed to load Statistics modal HTML:', error);
                        alert('Failed to load Statistics. Please try again.');
                    });
            } else {
                // HTML already loaded, check JavaScript
                if (!statisticsModalLoaded) {
                    const script = document.createElement('script');
                    script.src = 'assets/js/modals/statistics.js?v=' + Date.now();
                    script.onload = function() {
                        statisticsModalLoaded = true;
                        // Call the real function
                        if (typeof window.openStatisticsModalReal === 'function') {
                            window.openStatisticsModalReal();
                        }
                    };
                    script.onerror = function() {
                        console.error('Failed to load statistics.js module');
                        alert('Failed to load Statistics module. Please try again.');
                    };
                    document.head.appendChild(script);
                } else {
                    // Everything already loaded, just open the modal
                    if (typeof window.openStatisticsModalReal === 'function') {
                        window.openStatisticsModalReal();
                    }
                }
            }
        }

        function deleteSpecialistFromModal() {
            if (!modifySpecialistModalLoaded) {
                const script = document.createElement('script');
                script.src = 'assets/js/modals/modify-specialist.js?v=' + Date.now();
                script.onload = function() {
                    modifySpecialistModalLoaded = true;
                    if (typeof window.deleteSpecialistFromModal === 'function') {
                        window.deleteSpecialistFromModal();
                    }
                };
                document.head.appendChild(script);
            } else {
                if (typeof window.deleteSpecialistFromModal === 'function') {
                    window.deleteSpecialistFromModal();
                }
            }
        }

        function modifySpecialistSchedule() {
            if (!modifySpecialistModalLoaded) {
                const script = document.createElement('script');
                script.src = 'assets/js/modals/modify-specialist.js?v=' + Date.now();
                script.onload = function() {
                    modifySpecialistModalLoaded = true;
                    if (typeof window.modifySpecialistSchedule === 'function') {
                        window.modifySpecialistSchedule();
                    }
                };
                document.head.appendChild(script);
            } else {
                if (typeof window.modifySpecialistSchedule === 'function') {
                    window.modifySpecialistSchedule();
                }
            }
        }

        function closeModifySpecialistModal() {
            if (!modifySpecialistModalLoaded) {
                // If module not loaded, just hide the modal directly
                const modal = document.getElementById('modifySpecialistModal');
                if (modal) modal.style.display = 'none';
            } else {
                if (typeof window.closeModifySpecialistModal === 'function') {
                    window.closeModifySpecialistModal();
                }
            }
        }

        function closeDeleteSpecialistConfirmModal() {
            if (!modifySpecialistModalLoaded) {
                // If module not loaded, just hide the modal directly
                const modal = document.getElementById('deleteSpecialistConfirmModal');
                if (modal) modal.style.display = 'none';
            } else {
                if (typeof window.closeDeleteSpecialistConfirmModal === 'function') {
                    window.closeDeleteSpecialistConfirmModal();
                }
            }
        }

        function confirmDeleteSpecialistFromModal() {
            if (!modifySpecialistModalLoaded) {
                const script = document.createElement('script');
                script.src = 'assets/js/modals/modify-specialist.js?v=' + Date.now();
                script.onload = function() {
                    modifySpecialistModalLoaded = true;
                    if (typeof window.confirmDeleteSpecialistFromModal === 'function') {
                        window.confirmDeleteSpecialistFromModal();
                    }
                };
                document.head.appendChild(script);
            } else {
                if (typeof window.confirmDeleteSpecialistFromModal === 'function') {
                    window.confirmDeleteSpecialistFromModal();
                }
            }
        }

        // Schedule Modification Modal Functions
        // Comprehensive Schedule Editor - Lazy Loading
        let comprehensiveScheduleModalLoaded = false;
        let comprehensiveScheduleModalHtmlLoaded = false;

        function openModifyScheduleModal(specialistId, workpointId) {
            // If the real function exists (already loaded), call it directly
            if (typeof window.openModifyScheduleModalReal === 'function') {
                window.openModifyScheduleModalReal(specialistId, workpointId);
                return;
            }

            // First, load the modal HTML if not already loaded
            if (!comprehensiveScheduleModalHtmlLoaded) {
                fetch('assets/modals/comprehensive-schedule-editor-modal.php')
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('comprehensiveScheduleModalPlaceholder').innerHTML = html;
                        comprehensiveScheduleModalHtmlLoaded = true;

                        // Now load the JavaScript if not already loaded
                        if (!comprehensiveScheduleModalLoaded) {
                            const script = document.createElement('script');
                            script.src = 'assets/js/modals/comprehensive-schedule-editor.js?v=' + Date.now();
                            script.onload = function() {
                                comprehensiveScheduleModalLoaded = true;
                                // The script should have set window.openModifyScheduleModalReal
                                if (typeof window.openModifyScheduleModalReal === 'function') {
                                    window.openModifyScheduleModalReal(specialistId, workpointId);
                                } else {
                                    console.error('openModifyScheduleModalReal not found after script load');
                                }
                            };
                            script.onerror = function() {
                                console.error('Failed to load Comprehensive Schedule Editor script');
                                alert('Failed to load Schedule Editor functionality. Please try again.');
                            };
                            document.head.appendChild(script);
                        } else {
                            // JavaScript already loaded, call the real function
                            if (typeof window.openModifyScheduleModalReal === 'function') {
                                window.openModifyScheduleModalReal(specialistId, workpointId);
                            } else {
                                console.error('openModifyScheduleModalReal not found');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Failed to load Comprehensive Schedule Editor modal HTML:', error);
                        alert('Failed to load Schedule Editor. Please try again.');
                    });
            } else {
                // HTML already loaded
                if (!comprehensiveScheduleModalLoaded) {
                    // Load JavaScript
                    const script = document.createElement('script');
                    script.src = 'assets/js/modals/comprehensive-schedule-editor.js?v=' + Date.now();
                    script.onload = function() {
                        comprehensiveScheduleModalLoaded = true;
                        if (typeof window.openModifyScheduleModalReal === 'function') {
                            window.openModifyScheduleModalReal(specialistId, workpointId);
                        } else {
                            console.error('openModifyScheduleModalReal not found after script load');
                        }
                    };
                    script.onerror = function() {
                        console.error('Failed to load Comprehensive Schedule Editor script');
                        alert('Failed to load Schedule Editor functionality. Please try again.');
                    };
                    document.head.appendChild(script);
                } else {
                    // Both loaded, call the real function
                    if (typeof window.openModifyScheduleModalReal === 'function') {
                        window.openModifyScheduleModalReal(specialistId, workpointId);
                    } else {
                        console.error('openModifyScheduleModalReal not found even though JS is marked as loaded');
                    }
                }
            }
        }

        // Stub function for close - will be replaced when the actual script loads
        function closeModifyScheduleModal() {
            const modal = document.getElementById('modifyScheduleModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Stub functions that will be replaced when the script loads
        function toggleShiftVisibility(shiftNumber, isVisible) {
            // This will be replaced by the real function when the script loads
        }

        function deleteScheduleFromModal() {
            // This will be replaced by the real function when the script loads
            alert('Please wait for the Schedule Editor to fully load before performing this action.');
        }

        function updateScheduleFromModal() {
            // This will be replaced by the real function when the script loads
            alert('Please wait for the Schedule Editor to fully load before performing this action.');
        }

        function applyModifyAllShifts() {
            // This will be replaced by the real function when the script loads
            alert('Please wait for the Schedule Editor to fully load before performing this action.');
        }

        function clearModifyShift(button, shiftNum) {
            // This will be replaced by the real function when the script loads
        }

        function loadScheduleDataForModal(specialistId, workpointId) {
            // This will be replaced by the real function when the script loads
        }

        function loadModifyScheduleEditor() {
            // This will be replaced by the real function when the script loads
        }
        
        // Time Off Modal Functions - Lazy Loading
        let timeOffModalLoaded = false;
        let timeOffSpecialistId = null; // Keep this here for autoSave functions

        // Preserve these globally for the external script
        window.bookedDates = new Set();
        window.bookingCounts = {};

        // Lazy loading wrapper for Time Off Modal
        window.openTimeOffModal = function() {
            // Set specialist ID immediately for autoSave functions
            timeOffSpecialistId = document.getElementById('modifyScheduleSpecialistId').value;
            window.timeOffSpecialistId = timeOffSpecialistId; // Make it globally available

            if (!timeOffModalLoaded) {
                // Load the Time Off script
                const script = document.createElement('script');
                script.src = 'assets/js/modals/timeoff.js?v=' + Date.now();
                script.onload = function() {
                    timeOffModalLoaded = true;
                    // The script replaces openTimeOffModal with the real function
                    // Now call it
                    if (typeof window.openTimeOffModal === 'function') {
                        window.openTimeOffModal();
                    }
                };
                script.onerror = function() {
                    console.error('Failed to load Time Off modal script');
                    alert('Failed to load Time Off functionality. Please try again.');
                };
                document.head.appendChild(script);
            } else {
                // Already loaded, the real function has replaced this wrapper
                // This shouldn't happen but handle it just in case
                if (typeof window.openTimeOffModalReal === 'function') {
                    window.openTimeOffModalReal();
                }
            }
        };

        // Stub function that will be overridden when the actual script loads
        window.closeTimeOffModal = function() {
            // This will be replaced by the real function when loaded
            const modal = document.getElementById('timeOffModal');
            if (modal) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) {
                    bsModal.hide();
                }
            }
        };

        // Workpoint Holidays & Closures - Lazy Loading
        let workpointHolidaysLoaded = false;

        // Get current workpoint ID from PHP
        window.currentWorkpointId = <?= json_encode($workpoint_id ?? 0) ?>;
        console.log('Initial workpoint ID from PHP:', window.currentWorkpointId); // Debug

        // Lazy loading wrapper for Workpoint Holidays Modal
        window.openWorkpointHolidays = function() {
            // Use the workpoint ID from PHP
            window.currentWorkpointId = <?= json_encode($workpoint_id ?? 0) ?>;
            console.log('Workpoint ID when opening modal:', window.currentWorkpointId); // Debug

            if (!workpointHolidaysLoaded) {
                // Load the Workpoint Holidays script
                const script = document.createElement('script');
                script.src = 'assets/js/modals/workpoint-holidays.js?v=' + Date.now();
                script.onload = function() {
                    workpointHolidaysLoaded = true;
                    // The script replaces openWorkpointHolidaysModal with the real function
                    // Now call it
                    if (typeof window.openWorkpointHolidaysModal === 'function') {
                        window.openWorkpointHolidaysModal();
                    }
                };
                script.onerror = function() {
                    console.error('Failed to load Workpoint Holidays modal script');
                    alert('Failed to load Workpoint Holidays functionality. Please try again.');
                };
                document.head.appendChild(script);
            } else {
                // Already loaded, the real function has replaced the wrapper
                if (typeof window.openWorkpointHolidaysModal === 'function') {
                    window.openWorkpointHolidaysModal();
                }
            }
        };

        // Alias for compatibility
        window.openWorkpointHolidaysModal = window.openWorkpointHolidays;

        // SMS Templates - Lazy Loading
        let smsTemplatesLoaded = false;

        // Lazy loading wrapper for SMS Templates Modal
        window.openSMSConfirmationSetup = function() {
            console.log('Opening SMS Templates with workpoint ID:', window.currentWorkpointId); // Debug

            if (!smsTemplatesLoaded) {
                // Load the SMS Templates script
                const script = document.createElement('script');
                script.src = 'assets/js/modals/sms-templates.js?v=' + Date.now();
                script.onload = function() {
                    smsTemplatesLoaded = true;
                    // The script replaces manageSMSTemplate with the real function
                    // Now call it
                    if (typeof window.manageSMSTemplate === 'function') {
                        window.manageSMSTemplate();
                    }
                };
                script.onerror = function() {
                    console.error('Failed to load SMS Templates modal script');
                    alert('Failed to load SMS Templates functionality. Please try again.');
                };
                document.head.appendChild(script);
            } else {
                // Already loaded, the real function has replaced the wrapper
                if (typeof window.manageSMSTemplate === 'function') {
                    window.manageSMSTemplate();
                }
            }
        };

        // Alias for compatibility
        window.manageSMSTemplate = window.openSMSConfirmationSetup;

        // Auto-save functions (window-scoped for external access)
        window.autoSaveAddFullDayOff = function(date) {
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
        };

        window.autoSaveRemoveDayOff = function(date) {
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
        };

        window.autoSaveConvertToPartial = function(date) {
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
        };

        window.autoSaveConvertToFull = function(date) {
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
        };

        window.autoSaveUpdateWorkingHours = function(date, workStart, workEnd) {
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

                }
            })
            .catch(error => console.error('Auto-save error:', error));
        };

        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modifyModal = document.getElementById('modifySpecialistModal');
            const deleteModal = document.getElementById('deleteSpecialistConfirmModal');
            const addModal = document.getElementById('addSpecialistModal');

            if (event.target === modifyModal) {
                closeModifySpecialistModal();
            }
            if (event.target === deleteModal) {
                closeDeleteSpecialistConfirmModal();
            }
            if (event.target === addModal) {
                closeAddSpecialistModal();
            }

            // Handle modifyScheduleModal if it's loaded
            const modifyScheduleModal = document.getElementById('modifyScheduleModal');
            if (modifyScheduleModal && event.target === modifyScheduleModal) {
                closeModifyScheduleModal();
            }
        };


        // Color Picker Functions - Lazy Loaded
        (function() {
            let colorPickerLoaded = false;
            
            // Keep getContrastColor available immediately (used by other parts)
            window.getContrastColor = function(hexColor) {
                const hex = hexColor.replace('#', '');
                const r = parseInt(hex.substr(0, 2), 16);
                const g = parseInt(hex.substr(2, 2), 16);
                const b = parseInt(hex.substr(4, 2), 16);
                const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                return luminance > 0.5 ? '#000000' : '#ffffff';
            };
            
            // Lazy loader for color picker modal
            window.openColorPickerModal = function(specialistId, specialistName, currentBackColor, currentForeColor) {
                if (!colorPickerLoaded) {
                    colorPickerLoaded = true;
                    const script = document.createElement('script');
                    script.src = 'assets/js/modals/color-picker.js?v=' + Date.now();
                    script.onload = function() {
                        // Now call the real function from the loaded script
                        window.openColorPickerModal(specialistId, specialistName, currentBackColor, currentForeColor);
                    };
                    document.head.appendChild(script);
                }
            };
        })();
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
                            <input type="text" id="modifySpecialistPassword" name="password" class="form-control" placeholder="Leave blank to keep current password">
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
                                    <input type="text" class="form-control" id="specialistPassword" name="password" required style="background-color: #e3f2fd;">
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

    <!-- Comprehensive Schedule Editor Modal - Will be loaded on demand -->
    <div id="comprehensiveScheduleModalPlaceholder"></div>
    
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


        if (!serviceId || !window.workpointServices || !window.workpointServices[serviceId]) {
            // Clear form if no template selected
            document.getElementById('supervisorServiceName').value = '';
            document.getElementById('supervisorServiceDuration').value = '';
            document.getElementById('supervisorServicePrice').value = '';
            document.getElementById('supervisorServiceVat').value = '0';
            return;
        }
        
        const service = window.workpointServices[serviceId];

        // Set form values
        const nameField = document.getElementById('supervisorServiceName');
        const durationField = document.getElementById('supervisorServiceDuration');
        const priceField = document.getElementById('supervisorServicePrice');
        const vatField = document.getElementById('supervisorServiceVat');
        
        // Set the name
        if (nameField) {
            nameField.value = service.name_of_service || '';

        }
        if (priceField) {
            priceField.value = service.price_of_service || '';

        }
        if (vatField) {
            vatField.value = service.procent_vat || '0';

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
        // In supervisor mode, stay in supervisor mode
        let baseUrl = 'booking_supervisor_view.php?working_point_user_id=<?= $working_point_user_id ?>';
        
        // Close the panel
        closeRightPanel();
        
        // Navigate to the specific date with the requested period
        // In supervisor mode, add the selected specialist ID to the URL
        const specialistParam = specialistId ? '&selected_specialist=' + specialistId : '';
        
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
</body>
</html>
