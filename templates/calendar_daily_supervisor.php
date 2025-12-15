<?php
/**
 * Daily Calendar Template for Supervisor Mode
 * Shows all specialists in one table with columns for each specialist
 * Similar to regular daily view but with just the central panel
 */

require_once 'includes/calendar_functions.php';

// Get date range
$current_date = new DateTime($start_date);
$end_date_obj = new DateTime($end_date);
$dates = [];

while ($current_date <= $end_date_obj) {
    $dates[] = $current_date->format('Y-m-d');
    $current_date->add(new DateInterval('P1D'));
}

// For daily view, we'll show one day at a time
$display_date = $dates[0] ?? date('Y-m-d');

// Calculate previous and next days
$display_date_obj = new DateTime($display_date);
$prev_date_obj = clone $display_date_obj;
$prev_date_obj->sub(new DateInterval('P1D'));
$next_date_obj = clone $display_date_obj;
$next_date_obj->add(new DateInterval('P1D'));

$prev_date = $prev_date_obj->format('Y-m-d');
$next_date = $next_date_obj->format('Y-m-d');

// Get specialist colors from settings
$specialist_colors = [];
foreach ($specialists as $spec) {
    $stmt = $pdo->prepare("SELECT back_color, foreground_color FROM specialists_setting_and_attr WHERE specialist_id = ?");
    $stmt->execute([$spec['unic_id']]);
    $color_settings = $stmt->fetch();
    
    if ($color_settings && $color_settings['back_color'] && $color_settings['foreground_color']) {
        $specialist_colors[$spec['unic_id']] = [
            'back_color' => $color_settings['back_color'],
            'foreground_color' => $color_settings['foreground_color']
        ];
    } else {
        // Fallback to default colors
        $specialist_colors[$spec['unic_id']] = [
            'back_color' => '#667eea',
            'foreground_color' => '#ffffff'
        ];
    }
}

// Get time off dates for all specialists
$specialist_time_off = [];
foreach ($specialists as $spec) {
    $stmt = $pdo->prepare("
        SELECT start_time, end_time 
        FROM specialist_time_off 
        WHERE specialist_id = ? 
        AND date_off = ?
    ");
    $stmt->execute([$spec['unic_id'], $display_date]);
    $time_off_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($time_off_info) {
        $specialist_time_off[$spec['unic_id']] = [
            'start_time' => $time_off_info['start_time'],
            'end_time' => $time_off_info['end_time']
        ];
    } else {
        $specialist_time_off[$spec['unic_id']] = null;
    }
}

// Get working hours for all specialists at this workpoint
$all_working_hours = [];
foreach ($specialists as $spec) {
    $spec_working_hours = getWorkingHours($pdo, $spec['unic_id'], $workpoint['unic_id'], $display_date);
    $all_working_hours[$spec['unic_id']] = $spec_working_hours;
}

// Get bookings for all specialists
$all_specialist_bookings = [];
foreach ($specialists as $spec) {
    $spec_bookings = array_filter($bookings, function($booking) use ($spec, $display_date) {
        return $booking['id_specialist'] == $spec['unic_id'] && 
               date('Y-m-d', strtotime($booking['booking_start_datetime'])) == $display_date;
    });
    $all_specialist_bookings[$spec['unic_id']] = sortBookingsByTime($spec_bookings);
}

// Calculate uniform time range across all specialists
$earliest_start = '23:59';
$latest_end = '00:00';

// Check working hours for all specialists
foreach ($all_working_hours as $spec_id => $working_hours) {
    if ($working_hours) {
        foreach ($working_hours as $shift) {
            $shift_start = substr($shift['start'], 0, 5);
            $shift_end = substr($shift['end'], 0, 5);
            
            if ($shift_start < $earliest_start) {
                $earliest_start = $shift_start;
            }
            if ($shift_end > $latest_end) {
                $latest_end = $shift_end;
            }
        }
    }
}

// Also check all bookings
foreach ($all_specialist_bookings as $spec_id => $spec_bookings) {
    foreach ($spec_bookings as $booking) {
        $booking_start = date('H:i', strtotime($booking['booking_start_datetime']));
        $booking_end = date('H:i', strtotime($booking['booking_end_datetime']));
        
        if ($booking_start < $earliest_start) {
            $earliest_start = $booking_start;
        }
        if ($booking_end > $latest_end) {
            $latest_end = $booking_end;
        }
    }
}

// If no working hours or bookings found, use default times
if ($earliest_start === '23:59' && $latest_end === '00:00') {
    $earliest_start = '08:00';
    $latest_end = '17:00';
}

// Ensure minimum range of 8 hours
$start_hour = (int)substr($earliest_start, 0, 2);
$end_hour = (int)substr($latest_end, 0, 2);
if ($end_hour - $start_hour < 8) {
    $latest_end = sprintf('%02d:00', $start_hour + 8);
}

// Generate uniform time slots
$time_slots = generateTimeSlots($earliest_start, $latest_end);

// Calculate booking positions for each specialist
$all_booking_positions = [];
foreach ($specialists as $spec) {
    $spec_bookings = $all_specialist_bookings[$spec['unic_id']] ?? [];
    $all_booking_positions[$spec['unic_id']] = calculateSupervisorBookingPositions($spec_bookings, $time_slots);
}

// Function to calculate booking positions for a given specialist (supervisor version)
function calculateSupervisorBookingPositions($day_bookings, $time_slots) {
    $booking_positions = [];
    foreach ($day_bookings as $booking) {
        $start_time = formatTime($booking['booking_start_datetime']);
        $end_time = formatTime($booking['booking_end_datetime']);
        
        // Find start and end time slot indices
        $start_slot_index = array_search($start_time, $time_slots);
        $end_slot_index = array_search($end_time, $time_slots);
        
        if ($start_slot_index !== false) {
            if ($end_slot_index === false) {
                // If end time not found, calculate based on duration
                $start_minutes = (int)substr($start_time, 0, 2) * 60 + (int)substr($start_time, 3, 2);
                $end_minutes = (int)substr($end_time, 0, 2) * 60 + (int)substr($end_time, 3, 2);
                $duration_minutes = $end_minutes - $start_minutes;
                $end_slot_index = $start_slot_index + ceil($duration_minutes / 10) - 1;
            }
        } else {
            // If start time not found, find the closest time slot
            $start_minutes = (int)substr($start_time, 0, 2) * 60 + (int)substr($start_time, 3, 2);
            $closest_slot = null;
            $min_diff = PHP_INT_MAX;
            
            foreach ($time_slots as $index => $slot) {
                $slot_minutes = (int)substr($slot, 0, 2) * 60 + (int)substr($slot, 3, 2);
                $diff = abs($start_minutes - $slot_minutes);
                
                if ($diff < $min_diff) {
                    $min_diff = $diff;
                    $closest_slot = $index;
                }
            }
            
            if ($closest_slot !== null) {
                $start_slot_index = $closest_slot;
                
                // Calculate end slot index based on duration
                $end_minutes = (int)substr($end_time, 0, 2) * 60 + (int)substr($end_time, 3, 2);
                $duration_minutes = $end_minutes - $start_minutes;
                $end_slot_index = $start_slot_index + ceil($duration_minutes / 10) - 1;
                
                // Ensure end_slot_index doesn't exceed array bounds
                if ($end_slot_index >= count($time_slots)) {
                    $end_slot_index = count($time_slots) - 1;
                }
            }
        }
        
        // Only add to positions if we have valid slot indices
        if (isset($start_slot_index) && $start_slot_index !== false && isset($end_slot_index) && $end_slot_index !== false) {
            $booking_positions[] = [
                'booking' => $booking,
                'start_slot_index' => $start_slot_index,
                'end_slot_index' => $end_slot_index,
                'start_time' => $start_time,
                'end_time' => $end_time
            ];
        }
    }
    return $booking_positions;
}
?>

<style>
.supervisor-daily-calendar {
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    min-height: 400px;
}

.supervisor-daily-grid-container {
    display: flex;
    justify-content: center;
    width: 100%;
    position: relative;
    overflow: hidden;
}

.supervisor-daily-grid {
    min-width: 300px;
    <?php if (count($specialists) > 4): ?>
    max-width: 98%;
    <?php else: ?>
    max-width: 800px;
    <?php endif; ?>
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: relative;
    z-index: 1;
    border: 2px solid var(--primary-color);
    flex: 1;
    transition: all 0.6s ease-in-out;
}

.calendar-header {
    background: white;
    padding: 6px 6px;
    border: 1px solid #e9ecef;
    border-bottom: none;
    border-radius: 10px 10px 0 0;
}

.calendar-grid {
    display: grid;
    grid-template-columns: 60px repeat(<?= count($specialists) ?>, 1fr);
    grid-template-rows: 48px repeat(<?= count($time_slots) ?>, 20px);
    border-collapse: collapse;
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 0 0 10px 10px;
}

.time-header-cell {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    text-align: center;
    padding: 20px 0;
    font-weight: 600;
    font-size: 1rem;
    border: 1px solid #ddd;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    grid-row: 1;
}

.specialist-header-cell {
    text-align: center;
    padding: 20px 0;
    font-weight: 600;
    font-size: 1rem;
    border: 1px solid #ddd;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-right: 1px solid #ddd;
    grid-row: 1;
}

.specialist-header-cell:last-child {
    border-right: none;
}

.time-cell {
    background: #f8f9fa;
    font-weight: 600;
    color: var(--dark-color);
    text-align: center;
    font-size: 0.7rem;
    padding: 0;
    border: 1px solid #e9ecef;
    border-right: 1px solid #e9ecef;
    border-bottom: 1px solid #e9ecef;
    line-height: 1;
    height: 20px;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: center;
}

.time-cell.time-hour {
    font-weight: 700;
    font-size: 0.8rem;
}

.specialist-cell {
    position: relative;
    height: 20px;
    background: white;
    overflow: visible;
    border: 1px solid #e9ecef;
    cursor: default;
    transition: background-color 0.2s ease;
    border-right: 1px solid #e9ecef;
    border-bottom: 1px solid #e9ecef;
}

.specialist-cell:not(.non-working):not(.day-off):not(.past) {
    cursor: pointer;
}

.specialist-cell:last-child {
    border-right: none;
}

/* Add horizontal lines between time slots */
.time-cell {
    border-bottom: 1px solid #e9ecef;
}

/* Remove bottom border from last row */
.time-cell:last-child,
.specialist-cell:last-child {
    border-bottom: none;
}

.specialist-cell:not(.non-working):not(.day-off):not(.past):hover {
    background-color: rgba(102, 126, 234, 0.1);
}

/* Current time slot indicator */
.specialist-cell.current-time {
    background-color: rgba(255, 193, 7, 0.2);
    border: 2px solid #ffc107;
    position: relative;
}

.specialist-cell.current-time::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 6px;
    height: 6px;
    background-color: #ffc107;
    border-radius: 50%;
}

.specialist-cell.non-working {
    background-color: #f8f9fa;
    cursor: not-allowed;
}

.specialist-cell.past {
    background-color: #f8f9fa;
    cursor: not-allowed;
    border: 1px solid #dee2e6;
    opacity: 0.7;
}

.specialist-cell.non-working {
    background-color: #f8f9fa;
    cursor: not-allowed;
    border: 1px solid #dee2e6;
    opacity: 0.8;
}

/* Booking box styles */
.booking-box {
    position: absolute;
    background: #e3f2fd;
    color: #1976d2;
    padding: 2px 4px;
    border-radius: 2px;
    font-size: 0.7rem;
    cursor: pointer;
    overflow: hidden;
    transition: all 0.3s ease;
    box-sizing: border-box;
    word-wrap: break-word;
    z-index: 10;
    border: 1px solid #1976d2;
    left: 0;
    top: 0;
}

.booking-box:hover {
    transform: scale(1.02);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    z-index: 15;
}

.booking-box.booking-past {
    background: white;
    color: #adb5bd;
    border: 1px solid #dee2e6;
}

.booking-box.booking-today {
    background: #fff3cd;
    color: #28a745;
    border: 1px solid #28a745;
}

.booking-box.booking-future {
    background: #e3f2fd;
    color: #1976d2;
    border: 1px solid #1976d2;
}

.booking-box-content {
    display: flex;
    flex-direction: column;
    gap: 1px;
    height: 100%;
    justify-content: center;
    align-items: center;
    text-align: center;
}

.booking-time {
    font-weight: 600;
    font-size: 0.7rem;
}

.booking-client {
    font-weight: 600;
    font-size: 0.8rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.booking-service {
    font-size: 0.7rem;
    opacity: 0.9;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Booking status colors */
.booking-box.completed {
    background: var(--success-color);
}

.booking-box.cancelled {
    background: var(--danger-color);
}

.booking-box.pending {
    background: var(--warning-color);
    color: #000;
}

.booking-box.confirmed {
    background: var(--info-color);
}

.supervisor-daily-grid .day-info-row th {
    background: white !important;
    color: var(--dark-color) !important;
    padding: 15px 12px;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.navigation-controls {
    display: flex;
    gap: 15px;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    background: white;
    padding: 6px 15px;
    margin: 0;
}

.nav-btn {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    min-width: 120px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    white-space: nowrap;
}

.nav-btn:hover {
    background: var(--secondary-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.nav-btn:active {
    transform: translateY(0);
}

.current-date-display {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary-color);
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex: 1;
    margin: 0 20px;
}

.supervisor-daily-grid table {
    width: 100%;
    height: 100%;
    border-collapse: collapse;
    table-layout: fixed !important;
    box-sizing: border-box;
}

.supervisor-daily-grid th,
.supervisor-daily-grid td {
    border: 1px solid #e9ecef;
    padding: 0;
    vertical-align: top;
    min-height: 30px;
    height: 30px;
}

.supervisor-daily-grid .day-info-row th {
    border: none !important;
    border-bottom: none !important;
    border-top: none !important;
    border-left: none !important;
    border-right: none !important;
    background: #1e3a8a !important;
    padding: 0 !important;
}

.supervisor-daily-grid .day-info-row th * {
    border: none !important;
    border-bottom: none !important;
}

.navigation-controls {
    border: none !important;
    border-bottom: none !important;
}

.supervisor-daily-grid .day-info-row {
    border: none !important;
}

.supervisor-daily-grid .day-info-row td {
    border: none !important;
}

.supervisor-daily-grid table {
    border-collapse: collapse;
}

.supervisor-daily-grid .day-info-cell {
    border: none !important;
}

.supervisor-daily-grid tbody td {
    min-height: 20px;
    height: 20px;
}

.supervisor-daily-grid th {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    text-align: center;
    padding: 6px 0;
    font-weight: 600;
    font-size: 1rem;
    border: 1px solid #ddd;
    min-height: 28px;
    max-height: 36px;
    height: 32px;
    vertical-align: middle;
}

/* Force time column header to be 60px */
.supervisor-daily-grid th:first-child {
    width: 60px !important;
    min-width: 60px !important;
    max-width: 60px !important;
}

/* Force specialist column headers to be equal width */
.supervisor-daily-grid th:not(:first-child) {
    width: calc((100% - 60px) / <?= count($specialists) ?>) !important;
    min-width: 120px !important;
}

/* Override any browser table layout with maximum specificity */
.supervisor-daily-grid table[style*="border-spacing"] th:first-child,
.supervisor-daily-grid table[style*="border-collapse"] th:first-child {
    width: 60px !important;
    min-width: 60px !important;
    max-width: 60px !important;
}

.supervisor-daily-grid table[style*="border-spacing"] th:not(:first-child),
.supervisor-daily-grid table[style*="border-collapse"] th:not(:first-child) {
    width: calc((100% - 60px) / <?= count($specialists) ?>) !important;
    min-width: 120px !important;
}

.supervisor-daily-grid table[style*="border-spacing"] td:first-child,
.supervisor-daily-grid table[style*="border-collapse"] td:first-child {
    width: 60px !important;
    min-width: 60px !important;
    max-width: 60px !important;
}

.supervisor-daily-grid table[style*="border-spacing"] td:not(:first-child),
.supervisor-daily-grid table[style*="border-collapse"] td:not(:first-child) {
    width: calc((100% - 60px) / <?= count($specialists) ?>) !important;
    min-width: 120px !important;
}

.day-info-row th {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    text-align: center;
    padding: 8px 0;
    font-weight: 500;
    font-size: 1rem;
    border: 1px solid #ddd;
    min-height: 47px;
    max-height: 47px;
    height: 47px;
    vertical-align: middle;
}

.day-info-cell {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
    color: white !important;
}

.day-info-header {
    font-size: 0.9rem;
    font-weight: 600;
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    line-height: 1.2;
}

.day-info-header i {
    color: white;
    font-size: 0.9rem;
}

.time-column {
    background: #f8f9fa;
    font-weight: 600;
    color: var(--dark-color);
    text-align: center;
    vertical-align: middle;
    font-size: 0.7rem;
    min-width: 60px !important;
    max-width: 60px !important;
    width: 60px !important;
    padding: 0;
    border: 1px solid #e9ecef;
    line-height: 1;
    min-height: 20px;
    height: 20px;
    box-sizing: border-box;
}

.time-column.time-hour {
    font-weight: 700;
    font-size: 0.8rem;
}

.specialist-column {
    position: relative;
    min-height: 20px;
    background: white;
    overflow: visible;
    width: calc((100% - 60px) / <?= count($specialists) ?>) !important;
    min-width: 120px !important;
    padding: 0;
    vertical-align: top;
    height: 20px;
    width: calc((100% - 60px) / <?= count($specialists) ?>);
    min-width: 120px;
}

.specialist-column.non-working {
    background: #f8f9fa;
}

.specialist-column.past {
    background: #e9ecef;
    opacity: 0.6;
}

/* Specialist day off cells - lighter gray background */
.specialist-cell.day-off {
    background: #f5f5f5 !important;
    cursor: not-allowed !important;
    opacity: 0.9;
}

.specialist-column:hover {
    background: rgba(102, 126, 234, 0.1);
}

.booking-box {
    position: absolute;
    left: 2px;
    right: 2px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 4px;
    padding: 2px 4px;
    font-size: 0.7rem;
    line-height: 1.2;
    overflow: hidden;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    z-index: 10;
    transition: all 0.2s ease;
}

.booking-box:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    z-index: 15;
}

.booking-box.completed {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.booking-box.cancelled {
    background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
}

.booking-box.overdue {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    color: #212529;
}

.booking-box-content {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.booking-location {
    font-weight: 600;
    font-size: 0.65rem;
    opacity: 0.9;
}

.booking-time {
    font-weight: 700;
    font-size: 0.7rem;
}

.booking-client {
    font-weight: 600;
    font-size: 0.7rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.booking-service {
    font-size: 0.65rem;
    opacity: 0.8;
    font-style: italic;
}

.booking-box.completed {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.booking-box.cancelled {
    background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
}

.booking-box.overdue {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    color: #212529;
}

.specialist-header {
    color: white;
    font-weight: bold;
    font-size: 0.85rem;
    text-align: center;
    padding: 4px 2px;
    line-height: 1.2;
}

.specialist-specialty {
    font-size: 0.7rem;
    color: white;
    font-style: italic;
    text-align: center;
    padding: 2px;
    line-height: 1.1;
    opacity: 0.9;
}

.navigation-controls {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 20px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.95);
    border-bottom: 1px solid #e9ecef;
}

.nav-btn {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    min-width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nav-btn:hover {
    background: var(--secondary-color);
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.nav-btn:active {
    transform: translateY(0);
}

.current-date-display {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary-color);
    text-align: center;
    min-width: 200px;
}
</style>

<div class="supervisor-daily-calendar">
    <div class="supervisor-daily-grid-container">
        <!-- Current Day (Central Panel) -->
        <div class="supervisor-daily-grid current-day" data-day="current">
            <div class="calendar-header">
                <div class="navigation-controls">
                    <button class="nav-btn" onclick="navigateDay('prev', '<?= $prev_date ?>')" title="Previous Day">
                        <i class="fas fa-chevron-left"></i> Previous Day
                    </button>
                    <div class="current-date-display">
                        <i class="fas fa-calendar-day"></i>
                        <?= formatDate($display_date) ?>
                    </div>
                    <button class="nav-btn" onclick="navigateDay('next', '<?= $next_date ?>')" title="Next Day">
                        Next Day <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="calendar-grid">
                <!-- Time column header -->
                <div class="time-header-cell">
                    <div class="time-header">
                        <i class="fas fa-clock"></i> Time
                    </div>
                </div>
                
                <!-- Specialist column headers -->
                <?php foreach ($specialists as $spec): ?>
                    <?php 
                    $spec_colors = $specialist_colors[$spec['unic_id']] ?? ['back_color' => '#667eea', 'foreground_color' => '#ffffff'];
                    ?>
                    <div class="specialist-header-cell" style="background: <?= $spec_colors['back_color'] ?>; color: <?= $spec_colors['foreground_color'] ?>;">
                        <div class="day-header">
                            <div class="specialist-header">
                                <?= htmlspecialchars($spec['name']) ?>
                            </div>
                            <div class="specialist-specialty">
                                <?= htmlspecialchars($spec['speciality']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Time slots and specialist cells -->
                <?php foreach ($time_slots as $time_index => $time): ?>
                    <!-- Time column cell -->
                    <div class="time-cell <?= (substr($time, -2) === '00') ? 'time-hour' : '' ?>">
                        <?= $time ?>
                    </div>
                    
                    <!-- Specialist cells for this time slot -->
                    <?php foreach ($specialists as $spec): ?>
                        <?php 
                        $spec_working_hours = $all_working_hours[$spec['unic_id']] ?? [];
                        $is_working = isWithinWorkingHours($time . ':00', $spec_working_hours);
                        
                        // Check if this time slot is in the past
                        $current_datetime = new DateTime();
                        $slot_datetime = new DateTime($display_date . ' ' . $time . ':00');
                        $is_past = $slot_datetime < $current_datetime;
                        
                        // Check if this is the current time slot (within 10 minutes)
                        $current_time = new DateTime();
                        $time_diff = abs($current_time->getTimestamp() - $slot_datetime->getTimestamp());
                        $is_current = $time_diff <= 600 && $current_datetime->format('Y-m-d') === $display_date; // 10 minutes = 600 seconds
                        
                        // Check if specialist has day off
                        $is_day_off = false;
                        $time_off_info = $specialist_time_off[$spec['unic_id']] ?? null;
                        
                        if ($time_off_info) {
                            if ($time_off_info['start_time'] && $time_off_info['end_time']) {
                                // Partial day off - check if this time slot is within the range
                                $slot_time = $time . ':00';
                                if ($slot_time >= $time_off_info['start_time'] && $slot_time <= $time_off_info['end_time']) {
                                    $is_day_off = true;
                                }
                            } else {
                                // Full day off
                                $is_day_off = true;
                            }
                        }
                        ?>
                        <div class="specialist-cell <?= $is_day_off ? 'day-off' : (!$is_working ? 'non-working' : '') ?> <?= $is_past ? 'past' : '' ?> <?= $is_current ? 'current-time' : '' ?>"
                             data-time="<?= $time ?>"
                             data-time-index="<?= $time_index ?>"
                             data-specialist-id="<?= $spec['unic_id'] ?>"
                             data-is-day-off="<?= $is_day_off ? 'true' : 'false' ?>"
                             <?php if ($is_day_off): ?>
                             title="Specialist day off"
                             <?php endif; ?>
                             <?php if (!$is_past && !$is_day_off && $is_working): ?>
                             onclick="openAddBookingModalWithWorkpoint('<?= $display_date ?>', '<?= $time ?>', '<?= $spec['unic_id'] ?>', '<?= $workpoint['unic_id'] ?>', '<?= htmlspecialchars($workpoint['name_of_the_place']) ?>')"
                             <?php endif; ?>>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>

            
            <!-- Floating booking boxes for all specialists -->
            <?php foreach ($specialists as $spec): ?>
                <?php 
                $spec_bookings = $all_booking_positions[$spec['unic_id']] ?? [];
                $spec_index = array_search($spec, $specialists);
                ?>
                <?php foreach ($spec_bookings as $pos): ?>
                    <?php 
                    $booking = $pos['booking'];
                    $status_class = getBookingStatusClass($booking);
                    $tooltip = getBookingTooltip($booking, true, false);
                    
                    // Get specialist colors for this booking
                    $booking_bg_color = '';
                    $booking_text_color = '';
                    $spec_color_data = $specialist_colors[$spec['unic_id']] ?? null;
                    if ($spec_color_data) {
                        $bg_color = $spec_color_data['back_color'];
                        
                        // Convert hex to RGB and create lighter version
                        $r = hexdec(substr($bg_color, 1, 2));
                        $g = hexdec(substr($bg_color, 3, 2));
                        $b = hexdec(substr($bg_color, 5, 2));
                        
                        // Mix with white to create lighter version (85% white, 15% color for supervisor mode)
                        $r_light = round($r * 0.15 + 255 * 0.85);
                        $g_light = round($g * 0.15 + 255 * 0.85);
                        $b_light = round($b * 0.15 + 255 * 0.85);
                        
                        $booking_bg_color = sprintf("#%02x%02x%02x", $r_light, $g_light, $b_light);
                        
                        // Use darker version of the color for text and border
                        $r_dark = round($r * 0.7);
                        $g_dark = round($g * 0.7);
                        $b_dark = round($b * 0.7);
                        
                        $booking_text_color = sprintf("#%02x%02x%02x", $r_dark, $g_dark, $b_dark);
                    }
                    
                    // Calculate position
                    $time_slot_height = 20;
                    $top_base = $pos['start_slot_index'] * $time_slot_height;
                    // Make visual rectangle slightly shorter (10px) to expose end slot click area
                    $height = ($pos['end_slot_index'] - $pos['start_slot_index'] + 1) * $time_slot_height - 10;
                    $left_offset = $spec_index * (100 / count($specialists)) + 1;
                    $width = (100 / count($specialists)) - 2;
                    ?>
                    <div class="booking-box <?= $status_class ?>" 
                         style="height: <?= $height ?>px; top: <?= $top_base ?>px; left: <?= $left_offset ?>%; width: <?= $width ?>%;<?php if ($booking_bg_color): ?> background-color: <?= $booking_bg_color ?> !important; color: <?= $booking_text_color ?> !important; border-color: <?= $booking_text_color ?> !important;<?php endif; ?>"
                         data-top-base="<?= $top_base ?>"
                         data-specialist-id="<?= $spec['unic_id'] ?>"
                         data-bs-toggle="tooltip" 
                         data-bs-html="true" 
                         title="<?= $tooltip ?>"
                         onclick="event.stopPropagation(); openBookingModal(
                             '<?= htmlspecialchars($booking['client_full_name']) ?>',
                             '<?= $pos['start_time'] ?> - <?= $pos['end_time'] ?>',
                             '<?= htmlspecialchars($spec['name']) ?>',
                             <?= $booking['unic_id'] ?>,
                             '<?= htmlspecialchars($booking['client_phone_nr'] ?? '') ?>',
                             '<?= htmlspecialchars($booking['name_of_service'] ?? '') ?>',
                             '<?= $pos['start_time'] ?>',
                             '<?= $pos['end_time'] ?>',
                             '<?= date('Y-m-d', strtotime($booking['booking_start_datetime'])) ?>'
                         )">
                        <div class="booking-box-content">
                            <div class="booking-client"><?= htmlspecialchars($booking['client_full_name']) ?></div>
                            <?php if ($booking['name_of_service']): ?>
                                <div class="booking-service"><?= htmlspecialchars($booking['name_of_service']) ?></div>
                            <?php endif; ?>
                            <div class="booking-time"><?= $pos['start_time'] ?> - <?= $pos['end_time'] ?></div>
                            <div class="booking-id" style="font-size: 0.65rem; color: #666; opacity: 0.9;">Booking:#<?= $booking['unic_id'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// Initialize tooltips and dynamic positioning
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Force table layout with fixed column widths
    function forceTableLayout() {
        var table = document.querySelector('.supervisor-daily-grid table');
        if (table) {
            // Force table layout
            table.style.tableLayout = 'fixed';
            table.style.width = '100%';
            
            // Force time column to be exactly 60px
            var timeCells = table.querySelectorAll('th:first-child, td:first-child');
            timeCells.forEach(function(cell) {
                cell.style.width = '60px';
                cell.style.minWidth = '60px';
                cell.style.maxWidth = '60px';
            });
            
            // Force specialist columns to be equal width
            var specialistCells = table.querySelectorAll('th:not(:first-child), td:not(:first-child)');
            var specialistWidth = (table.offsetWidth - 60) / <?= count($specialists) ?>;
            specialistCells.forEach(function(cell) {
                cell.style.width = specialistWidth + 'px';
                cell.style.minWidth = '120px';
            });
        }
    }
    
    // Force layout immediately and after a short delay
    forceTableLayout();
    setTimeout(forceTableLayout, 100);
    setTimeout(forceTableLayout, 500);
    
    // Dynamically position booking boxes using grid layout
    function positionBookingBoxes() {
        var container = document.querySelector('.supervisor-daily-grid');
        if (!container) {
            // Retry after a short delay if elements aren't ready
            setTimeout(positionBookingBoxes, 50);
            return;
        }

        var grid = container.querySelector('.calendar-grid');
        if (!grid) {
            setTimeout(positionBookingBoxes, 50);
            return;
        }

        // Get grid position relative to its container
        var gridRect = grid.getBoundingClientRect();
        var containerRect = container.getBoundingClientRect();
        
        // Calculate grid offset within container
        var gridOffsetLeft = gridRect.left - containerRect.left;
        var gridOffsetTop = gridRect.top - containerRect.top;

        // Get header height
        var header = container.querySelector('.calendar-header');
        var headerHeight = header ? header.offsetHeight : 0;
        
        // Account for the navigation controls padding
        var navControls = header ? header.querySelector('.navigation-controls') : null;
        var navPadding = navControls ? (parseInt(window.getComputedStyle(navControls).paddingTop) + parseInt(window.getComputedStyle(navControls).paddingBottom)) : 0;
        headerHeight = headerHeight - navPadding;

        // Calculate column widths based on grid
        var gridWidth = grid.offsetWidth;
        var timeColumnWidth = 60; // Fixed time column width
        var specialistColumnWidth = (gridWidth - timeColumnWidth) / <?= count($specialists) ?>;

        // Position each booking box
        var bookingBoxes = container.querySelectorAll('.booking-box');
        
        bookingBoxes.forEach(function(box) {
            var topBase = parseInt(box.getAttribute('data-top-base'), 10);
            var specialistId = box.getAttribute('data-specialist-id');

            if (!isNaN(topBase)) {
                // Calculate position based on specialist index
                var specialistIndex = 0;
                var specialistCells = grid.querySelectorAll('.specialist-cell');
                for (var i = 0; i < specialistCells.length; i++) {
                    if (specialistCells[i].getAttribute('data-specialist-id') === specialistId) {
                        specialistIndex = i;
                        break;
                    }
                }
                
                var leftPosition = gridOffsetLeft + timeColumnWidth + (specialistIndex * specialistColumnWidth);
                var topPosition = gridOffsetTop + headerHeight + topBase;
                
                box.style.position = 'absolute';
                box.style.left = leftPosition + 'px';
                box.style.top = topPosition + 'px';
                box.style.width = (specialistColumnWidth - 4) + 'px';
                box.style.zIndex = '10';
            }
        });
    }
    
    // Initial positioning
    positionBookingBoxes();
    
    // Reposition on window resize
    window.addEventListener('resize', positionBookingBoxes);
});

// Navigation function for supervisor mode
function navigateDay(direction, targetDate) {
    const url = new URL(window.location);
    url.searchParams.set('start_date', targetDate);
    url.searchParams.set('end_date', targetDate);
    url.searchParams.set('period', 'custom');
    url.searchParams.set('working_point_user_id', '<?= $working_point_user_id ?>');
    url.searchParams.set('supervisor_mode', 'true');
    
    window.location.href = url.toString();
}

// Function to open booking modal with workpoint information for supervisor mode
function openAddBookingModalWithWorkpoint(date, time, specialistId, workpointId, workpointName) {
    // Store workpoint information in sessionStorage for the booking modal to use
    sessionStorage.setItem('selectedWorkpointId', workpointId);
    sessionStorage.setItem('selectedWorkpointName', workpointName);
    
    // Update the workpoint_id field in the modal BEFORE opening it
    const workpointIdField = document.querySelector('input[name="workpoint_id"]');
    if (workpointIdField) {
        workpointIdField.value = workpointId;
    }
    
    // Set the specialist ID in the modal
    document.getElementById('modalSpecialistId').value = specialistId;
    
    // Set the date and time
    document.getElementById('bookingDate').value = date || '';
    document.getElementById('bookingTime').value = time || '';
    
    // Load services for the selected specialist
    loadServices();
    
    // Show the modal
    new bootstrap.Modal(document.getElementById('addBookingModal')).show();
}

// Modified openAddBookingModal function for supervisor mode (kept for backward compatibility)
function openAddBookingModal(date, time, specialistId) {
    // Set the specialist ID in the modal
    document.getElementById('modalSpecialistId').value = specialistId;
    
    // Set the date and time
    document.getElementById('bookingDate').value = date || '';
    document.getElementById('bookingTime').value = time || '';
    
    // Load services for the selected specialist
    loadServices();
    
    // Show the modal
    new bootstrap.Modal(document.getElementById('addBookingModal')).show();
}
</script> 