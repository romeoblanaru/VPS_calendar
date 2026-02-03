<?php
/**
 * Daily Calendar Template
 * Shows bookings in a grid format similar to weekly but for a single day
 * Uses layered approach: background table + floating booking boxes
 * Modified to show three days side by side with individual panel sliding
 */

require_once 'includes/calendar_functions.php';

// Ensure we have access to the required variables from the parent scope
global $pdo, $specialist_id, $working_points, $has_multiple_workpoints, $organisation, $start_date, $end_date, $bookings;

// Check if this is supervisor mode
// Supervisor mode should already be set by the parent file
// If not set, check the GET parameter (fallback)
if (!isset($supervisor_mode)) {
    $supervisor_mode = isset($_GET['supervisor_mode']) && $_GET['supervisor_mode'] === 'true';
}

if ($supervisor_mode) {
    // Supervisor mode - show all specialists in one table
    include 'calendar_daily_supervisor.php';
    return;
}

// Get the workpoint_id for this specialist (for specialist mode only)
$workpoint_id = null;
$workpoint_name = null;
if (!empty($working_points)) {
    $workpoint_id = $working_points[0]['unic_id'];
    $workpoint_name = $working_points[0]['name_of_the_place'];
} else {
    // Fallback if no working points found
    $workpoint_id = null;
    $workpoint_name = null;
}

// Check if specialist has multiple workpoints
$has_multiple_workpoints = count($working_points) > 1;



// Get specialist time off dates for the selected period
$time_off_dates = [];
if (isset($specialist_id)) {
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
if (isset($workpoint_id) && $workpoint_id) {
    $stmt = $pdo->prepare("
        SELECT date_off, start_time, end_time, is_recurring, description
        FROM workingpoint_time_off
        WHERE workingpoint_id = ?
        ORDER BY date_off
    ");
    $stmt->execute([$workpoint_id]);
    $workpoint_holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug output
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        echo "<!-- DEBUG: Workpoint ID = $workpoint_id -->\n";
        echo "<!-- DEBUG: Found " . count($workpoint_holidays) . " workpoint holidays -->\n";
        foreach ($workpoint_holidays as $h) {
            echo "<!-- Holiday: " . $h['date_off'] . " (" . $h['description'] . ") recurring=" . $h['is_recurring'] . " -->\n";
        }
    }
}

// Helper function to check if a date is a workpoint holiday (handles recurring)
if (!function_exists('isWorkpointHoliday')) {
    function isWorkpointHoliday($check_date, $check_time, $holidays) {
        foreach ($holidays as $holiday) {
            $holiday_date = $holiday['date_off'];
            $is_recurring = (bool)$holiday['is_recurring'];

            // For recurring holidays, only compare month-day (MM-DD)
            if ($is_recurring) {
                $check_month_day = substr($check_date, 5); // Get MM-DD from YYYY-MM-DD
                $holiday_month_day = substr($holiday_date, 5); // Get MM-DD from YYYY-MM-DD

                if ($check_month_day === $holiday_month_day) {
                    // Recurring holiday matches! Now check time
                    if ($check_time >= $holiday['start_time'] && $check_time <= $holiday['end_time']) {
                        return ['is_holiday' => true, 'description' => $holiday['description']];
                    }
                }
            } else {
                // Non-recurring: exact date match required
                if ($check_date === $holiday_date) {
                    // Date matches! Now check time
                    if ($check_time >= $holiday['start_time'] && $check_time <= $holiday['end_time']) {
                        return ['is_holiday' => true, 'description' => $holiday['description']];
                    }
                }
            }
        }

        return ['is_holiday' => false, 'description' => ''];
    }
}

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

// Get bookings for all three days
$prev_day_bookings = getBookingsForDate($bookings, $prev_date);
$prev_day_bookings = sortBookingsByTime($prev_day_bookings);

$current_day_bookings = getBookingsForDate($bookings, $display_date);
$current_day_bookings = sortBookingsByTime($current_day_bookings);

$next_day_bookings = getBookingsForDate($bookings, $next_date);
$next_day_bookings = sortBookingsByTime($next_day_bookings);

// Get working hours for all three days
// For specialists with multiple workpoints, we need to determine the correct workpoint for each day
if (isset($has_multiple_workpoints) && $has_multiple_workpoints) {
    // Find which workpoint the specialist is working at for each day
    $prev_workpoint_id = null;
    $current_workpoint_id = null;
    $next_workpoint_id = null;
    
    foreach ($working_points as $wp) {
        // Check previous day
        if (!$prev_workpoint_id) {
            $prev_hours = getWorkingHours($pdo, $specialist_id, $wp['unic_id'], $prev_date);
            if ($prev_hours && !empty($prev_hours)) {
                $prev_workpoint_id = $wp['unic_id'];
            }
        }
        
        // Check current day
        if (!$current_workpoint_id) {
            $current_hours = getWorkingHours($pdo, $specialist_id, $wp['unic_id'], $display_date);
            if ($current_hours && !empty($current_hours)) {
                $current_workpoint_id = $wp['unic_id'];
            }
        }
        
        // Check next day
        if (!$next_workpoint_id) {
            $next_hours = getWorkingHours($pdo, $specialist_id, $wp['unic_id'], $next_date);
            if ($next_hours && !empty($next_hours)) {
                $next_workpoint_id = $wp['unic_id'];
            }
        }
    }
    
    // Get working hours using the determined workpoint for each day
    $prev_working_hours = $prev_workpoint_id ? getWorkingHours($pdo, $specialist_id, $prev_workpoint_id, $prev_date) : null;
    $current_working_hours = $current_workpoint_id ? getWorkingHours($pdo, $specialist_id, $current_workpoint_id, $display_date) : null;
    $next_working_hours = $next_workpoint_id ? getWorkingHours($pdo, $specialist_id, $next_workpoint_id, $next_date) : null;
} else {
    // Single workpoint - use the existing logic
    $prev_working_hours = $workpoint_id ? getWorkingHours($pdo, $specialist_id, $workpoint_id, $prev_date) : null;
    $current_working_hours = $workpoint_id ? getWorkingHours($pdo, $specialist_id, $workpoint_id, $display_date) : null;
    $next_working_hours = $workpoint_id ? getWorkingHours($pdo, $specialist_id, $workpoint_id, $next_date) : null;
}

// Calculate uniform time range across all three days
// This ensures all three panels (previous, current, next day) have the same time range
// by finding the minimum start time and maximum end time among all three days
// This creates a uniform design where all panels start and end at the same times
$earliest_start = '23:59'; // Start with latest possible time
$latest_end = '00:00';     // Start with earliest possible time

// Check working hours for all three days to find earliest start and latest end
$all_working_hours = array_merge($prev_working_hours ?: [], $current_working_hours ?: [], $next_working_hours ?: []);
if ($all_working_hours) {
    foreach ($all_working_hours as $shift) {
        $shift_start = substr($shift['start'], 0, 5); // Get HH:MM format
        $shift_end = substr($shift['end'], 0, 5);
        
        if ($shift_start < $earliest_start) {
            $earliest_start = $shift_start;
        }
        if ($shift_end > $latest_end) {
            $latest_end = $shift_end;
        }
    }
}

// Also check all bookings to ensure we include them in the time range
$all_bookings = array_merge($prev_day_bookings, $current_day_bookings, $next_day_bookings);
foreach ($all_bookings as $booking) {
    $booking_start = date('H:i', strtotime($booking['booking_start_datetime']));
    $booking_end = date('H:i', strtotime($booking['booking_end_datetime']));
    
    if ($booking_start < $earliest_start) {
        $earliest_start = $booking_start;
    }
    if ($booking_end > $latest_end) {
        $latest_end = $booking_end;
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

// Generate uniform time slots for all three panels
$time_slots = generateTimeSlots($earliest_start, $latest_end);

// Debug information (can be removed in production)
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo "<!-- DEBUG: Uniform Time Range Calculation -->\n";
    echo "<!-- Earliest Start: $earliest_start -->\n";
    echo "<!-- Latest End: $latest_end -->\n";
    echo "<!-- Total Time Slots: " . count($time_slots) . " -->\n";
    echo "<!-- Time Range: " . ($earliest_start . " - " . $latest_end) . " -->\n";
}

// Function to calculate booking positions for a given day
function calculateBookingPositions($day_bookings, $time_slots) {
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

// Calculate positions for all three days
$prev_booking_positions = calculateBookingPositions($prev_day_bookings, $time_slots);
$current_booking_positions = calculateBookingPositions($current_day_bookings, $time_slots);
$next_booking_positions = calculateBookingPositions($next_day_bookings, $time_slots);
?>

<style>

.daily-calendar {
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    min-height: 400px;
}

.daily-grid-container {
    display: flex;
    justify-content: center;
    width: 100%;
    position: relative;
    overflow: hidden;
    gap: 20px;
}

.daily-grid {
    min-width: 300px;
    max-width: 350px;
    border-collapse: collapse;
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: relative;
    z-index: 1;
    border-spacing: 0;
    border: 1px solid #e9ecef;
    flex: 1;
    transition: all 0.6s ease-in-out;
}

.daily-grid table {
    width: 100%;
    height: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    /* Ensure consistent table layout across all panels */
    box-sizing: border-box;
}

.daily-grid.faded {
    opacity: 0.4;
    filter: grayscale(50%);
    transform: scale(0.92);
    transition: all 0.4s ease;
}

.daily-grid.faded:hover {
    opacity: 0.7;
    filter: grayscale(20%);
    transform: scale(0.95);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.daily-grid.current-day {
    opacity: 1;
    filter: none;
    transform: scale(1);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    border: 2px solid var(--primary-color);
    transition: all 0.4s ease;
}

/* Individual panel sliding animations */
.daily-grid.sliding-to-center {
    transform: translateX(0) scale(1);
    opacity: 1;
    filter: none;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    border: 2px solid var(--primary-color);
    z-index: 10;
}

.daily-grid.sliding-from-left {
    transform: translateX(calc(100% + 20px)) scale(0.92);
    opacity: 0.4;
    filter: grayscale(50%);
}

.daily-grid.sliding-from-right {
    transform: translateX(calc(-100% - 20px)) scale(0.92);
    opacity: 0.4;
    filter: grayscale(50%);
}

/* Right panel moving left to center */
.daily-grid.sliding-right-to-center {
    transform: translateX(calc(-100% - 20px)) scale(1) !important;
    opacity: 1 !important;
    filter: none !important;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important;
    border: 2px solid var(--primary-color) !important;
    z-index: 10 !important;
    transition: all 0.6s ease-in-out !important;
}

/* Left panel moving right to center */
.daily-grid.sliding-left-to-center {
    transform: translateX(calc(100% + 20px)) scale(1) !important;
    opacity: 1 !important;
    filter: none !important;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important;
    border: 2px solid var(--primary-color) !important;
    z-index: 10 !important;
    transition: all 0.6s ease-in-out !important;
}

.daily-grid th,
.daily-grid td {
    border: 1px solid #e9ecef;
    padding: 0;
    vertical-align: top;
    min-height: 30px;
    height: 30px;
}

.daily-grid tbody td {
    min-height: 20px;
    height: 20px;
}

.daily-grid th {
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



.daily-grid.faded th {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
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



.daily-grid.faded .day-info-row th {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    font-weight: 400;
    font-size: 0.95rem;
}

.day-info-cell {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
    color: white !important;
}



.daily-grid.faded .day-info-cell {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
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
    min-width: 60px;
    max-width: 60px;
    width: 60px;
    padding: 0;
    border: 1px solid #e9ecef;
    line-height: 1;
    min-height: 20px;
    height: 20px;
    /* Ensure consistent time column width across all panels */
    box-sizing: border-box;
}

.time-column.time-hour {
    font-weight: 700;
    font-size: 0.8rem;
}

.day-cell {
    position: relative;
    min-height: 20px;
    background: white;
    overflow: visible;
    padding: 0;
    vertical-align: top;
    height: 20px;
    cursor: default;
    border: 1px solid #e9ecef;
    width: 100%;
}

.day-cell[onclick] {
    cursor: pointer;
}

.day-cell.non-working {
    background: #f8f9fa;
}

/* Specialist day off cells - lighter gray background */
.day-cell.day-off {
    background: #f5f5f5 !important;
    cursor: not-allowed !important;
    opacity: 0.9;
}

/* Workpoint holiday cells - darker gray background with holiday name */
.day-cell.workpoint-holiday {
    background: #f0f0f0 !important;
    cursor: not-allowed !important;
    opacity: 1;
    position: relative;
}

.day-cell.workpoint-holiday::after {
    content: attr(data-holiday-name);
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 0.65rem;
    color: #666;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
    pointer-events: none;
    max-width: 90%;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Booking boxes positioned absolutely relative to container */
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
    width: 250px; /* Reduced width for side tables */
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

.booking-address {
    font-size: 0.6rem;
    opacity: 0.8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #666;
}
.workpoint-2 {
    color: #d32f2f !important;
}
.workpoint-3 {
    color: #1976d2 !important;
}

.booking-location {
    font-size: 0.6rem;
    opacity: 0.8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.empty-slot {
    color: #6c757d;
    font-style: italic;
    font-size: 0.7rem;
    text-align: center;
    padding: 8px 4px;
}

.day-info-box {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 20px;
    background: white;
    padding: 15px 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    width: fit-content;
}

.day-info {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--dark-color);
}

/* Calendar Legend */
.calendar-legend {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 20px;
    margin-bottom: 15px;
    background: white;
    padding: 6px 15px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: var(--dark-color);
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    border: 1px solid #ddd;
}

.legend-color.working-hours {
    background: white;
}

.legend-color.non-working-hours {
    background: #f8f9fa;
}

.legend-color.booking-future {
    background: #e3f2fd;
    border: 1px solid #1976d2;
}

.legend-color.booking-today {
    background: #fff3cd;
    border: 1px solid #28a745;
}

.legend-color.booking-past {
    background: white;
    border: 1px solid #6c757d;
}

.daily-grid th:first-child {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
    color: white !important;
    min-width: 60px;
    max-width: 60px;
    width: 60px;
    text-align: center;
    vertical-align: middle;
    padding: 0;
    line-height: 1;
}

.daily-grid.faded th:first-child {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
}

.day-info-row th:first-child {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
    color: white !important;
    min-width: 60px;
    max-width: 60px;
    width: 60px;
    text-align: center;
    vertical-align: middle;
    padding: 0;
    line-height: 1;
}



.daily-grid.faded .day-info-row th:first-child {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
}

.daily-grid th:nth-child(2) {
    width: 100%;
}

@media (max-width: 1200px) {
    .daily-grid-container {
        flex-direction: column;
        gap: 15px;
        overflow: visible;
    }
    
    .daily-grid {
        min-width: 100%;
        max-width: none;
    }
    
    .daily-grid.faded {
        opacity: 0.8;
        filter: grayscale(20%);
        transform: none;
    }
    
    .daily-grid.sliding-to-center,
    .daily-grid.sliding-from-left,
    .daily-grid.sliding-from-right,
    .daily-grid.sliding-right-to-center,
    .daily-grid.sliding-left-to-center {
        transform: none;
    }
}

@media (max-width: 768px) {
    .daily-grid {
        min-width: 400px;
    }
    
    .daily-grid th,
    .daily-grid td {
        padding: 2px;
        font-size: 0.7rem;
        min-height: 25px;
        max-height: 25px;
        height: 25px;
    }
    
    .booking-box {
        padding: 1px 3px;
        font-size: 0.6rem;
        width: 200px;
    }
}
</style>

<div class="daily-calendar">
    <!-- Uniform Time Range Indicator -->
    <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
    <div class="uniform-time-indicator" style="background: rgba(255, 255, 255, 0.95); padding: 10px; margin-bottom: 15px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <strong>Uniform Time Range:</strong> <?= $earliest_start ?> - <?= $latest_end ?> 
        (<?= count($time_slots) ?> time slots, <?= round((strtotime($latest_end) - strtotime($earliest_start)) / 3600, 1) ?> hours)
    </div>
    <?php endif; ?>
    
    <!-- Three Day Grid Container -->
    <div class="daily-grid-container" id="calendarContainer">
        <!-- Previous Day -->
        <div class="daily-grid faded" data-day="prev" onclick="slidePanelToCenter('prev', '<?= $prev_date ?>')" style="cursor: pointer;">
            <table cellspacing="0" cellpadding="0">
                <thead>
                    <tr class="day-info-row">
                        <th class="day-info-cell" colspan="2">
                        <i class="fas fa-calendar-day"></i>
                            <?= formatDate($prev_date) ?>
                        </th>
                    </tr>
                    <tr>
                        <th>
                            <div class="time-header">Time</div>
                        </th>
                        <th style="width: 100%;">
                            <div class="day-header">
                                <div class="day-compact">
                                    <?= date('l, M j', strtotime($prev_date)) ?>
                                </div>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($time_slots as $time_index => $time): ?>
                        <tr>
                            <td class="time-column <?= (substr($time, -2) === '00') ? 'time-hour' : '' ?>"><?= $time ?></td>
                            <?php 
                            $is_working = isWithinWorkingHours($time . ':00', $prev_working_hours);
                            ?>
                            <?php 
                            // Check if this time slot is in the past
                            $current_datetime = new DateTime();
                            $slot_datetime = new DateTime($prev_date . ' ' . $time . ':00');
                            $is_past = $slot_datetime < $current_datetime;
                            
                            // Check if this date/time is a specialist's day off
                            $is_day_off = false;
                            if (isset($time_off_dates[$prev_date])) {
                                $day_off_info = $time_off_dates[$prev_date];

                                if ($day_off_info['start_time'] && $day_off_info['end_time']) {
                                    // Partial day off - check if this time slot is within the range
                                    $slot_time = $time . ':00';
                                    if ($slot_time >= $day_off_info['start_time'] && $slot_time <= $day_off_info['end_time']) {
                                        $is_day_off = true;
                                    }
                                } else {
                                    // Full day off
                                    $is_day_off = true;
                                }
                            }

                            // Check if this date/time is a workpoint holiday (both recurring and non-recurring)
                            // Only show workpoint holidays during working hours
                            $holiday_check = isWorkpointHoliday($prev_date, $time . ':00', $workpoint_holidays);
                            $is_workpoint_holiday = $holiday_check['is_holiday'] && $is_working;
                            $holiday_name = $holiday_check['description'];
                            ?>
                            <td class="day-cell <?= $is_workpoint_holiday ? 'workpoint-holiday' : ($is_day_off ? 'day-off' : (!$is_working ? 'non-working' : '')) ?>"
                                data-time="<?= $time ?>"
                                data-time-index="<?= $time_index ?>"
                                data-day="prev"
                                data-is-day-off="<?= $is_day_off ? 'true' : 'false' ?>"
                                <?= $is_workpoint_holiday ? 'data-holiday-name="'.htmlspecialchars($holiday_name).'"' : '' ?>
                                <?php if ($is_workpoint_holiday): ?>
                                title="<?= htmlspecialchars($holiday_name) ?>"
                                <?php elseif ($is_day_off): ?>
                                title="Specialist day off"
                                <?php endif; ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Floating booking boxes for previous day -->
            <?php foreach ($prev_booking_positions as $pos): ?>
                <?php 
                $booking = $pos['booking'];
                $status_class = getBookingStatusClass($booking);
                $tooltip = getBookingTooltip($booking, $supervisor_mode, $has_multiple_workpoints);
                
                // Get specialist colors if not in supervisor mode
                $booking_bg_color = '';
                $booking_text_color = '';
                if (!$supervisor_mode && isset($specialist_permissions['back_color']) && isset($specialist_permissions['foreground_color'])) {
                    $bg_color = $specialist_permissions['back_color'];
                    $fg_color = $specialist_permissions['foreground_color'];
                    
                    // Convert hex to RGB and create lighter version
                    $r = hexdec(substr($bg_color, 1, 2));
                    $g = hexdec(substr($bg_color, 3, 2));
                    $b = hexdec(substr($bg_color, 5, 2));
                    
                    // Mix with white to create lighter version (80% white, 20% color)
                    $r_light = round($r * 0.2 + 255 * 0.8);
                    $g_light = round($g * 0.2 + 255 * 0.8);
                    $b_light = round($b * 0.2 + 255 * 0.8);
                    
                    $booking_bg_color = sprintf("#%02x%02x%02x", $r_light, $g_light, $b_light);
                    
                    // Use darker version of the color for text (60% original color, 40% black)
                    $r_dark = round($r * 0.6);
                    $g_dark = round($g * 0.6);
                    $b_dark = round($b * 0.6);
                    
                    $booking_text_color = sprintf("#%02x%02x%02x", $r_dark, $g_dark, $b_dark);
                }
                
                // Calculate position (base values, will be adjusted by JavaScript)
                $time_slot_height = 20; // Height per time slot
                $top_base = $pos['start_slot_index'] * $time_slot_height; // Base top position
                // Reduce height slightly (10px) so the bottom half-cell remains clickable for the ending slot
                $height = ($pos['end_slot_index'] - $pos['start_slot_index'] + 1) * $time_slot_height - 10;
                ?>
                <div class="booking-box <?= $status_class ?>" 
                     style="height: <?= $height ?>px;<?php if ($booking_bg_color): ?> background-color: <?= $booking_bg_color ?> !important; color: <?= $booking_text_color ?> !important; border-color: <?= $booking_text_color ?> !important;<?php endif; ?>"
                     data-top-base="<?= $top_base ?>"
                     data-day="prev"
                     data-bs-toggle="tooltip" 
                     data-bs-html="true" 
                     title="<?= $tooltip ?>"
                     onclick="event.stopPropagation(); openBookingModal(
                         '<?= htmlspecialchars($booking['client_full_name']) ?>',
                         '<?= $pos['start_time'] ?> - <?= $pos['end_time'] ?>',
                         '<?= htmlspecialchars($specialist['name']) ?>',
                         <?= $booking['unic_id'] ?>,
                         '<?= htmlspecialchars($booking['client_phone_nr'] ?? '') ?>',
                         '<?= htmlspecialchars($booking['name_of_service'] ?? '') ?>',
                         '<?= $pos['start_time'] ?>',
                         '<?= $pos['end_time'] ?>',
                         '<?= date('Y-m-d', strtotime($booking['booking_start_datetime'])) ?>'
                     )">
                    <div class="booking-box-content">
                        <div class="booking-location"><?= htmlspecialchars($booking['name_of_the_place']) ?></div>
                        <div class="booking-client"><?= htmlspecialchars($booking['client_full_name']) ?></div>
                        <?php if ($booking['name_of_service']): ?>
                            <div class="booking-service"><?= htmlspecialchars($booking['name_of_service']) ?></div>
                        <?php endif; ?>
                        <div class="booking-time"><?= $pos['start_time'] ?> - <?= $pos['end_time'] ?></div>
                        <div class="booking-id" style="font-size: 0.65rem; color: #666; opacity: 0.9;">Booking:#<?= $booking['unic_id'] ?></div>
                        <?php if (isset($has_multiple_workpoints) && $has_multiple_workpoints): ?>
                            <?php 
                            $workpoint_id = $booking['id_work_place'] ?? null;
                            $workpoint_index = isset($workpoint_index_lookup[$workpoint_id]) ? $workpoint_index_lookup[$workpoint_id] : 1;
                            $address_class = 'booking-address';
                            if ($workpoint_index === 2) {
                                $address_class .= ' workpoint-2';
                            } elseif ($workpoint_index === 3) {
                                $address_class .= ' workpoint-3';
                            }
                            ?>
                            <div class="<?= $address_class ?>"><em><?= htmlspecialchars($booking['address'] ?? '') ?></em></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Current Day -->
        <div class="daily-grid current-day" data-day="current">
            <table cellspacing="0" cellpadding="0">
                <thead>
                    <tr class="day-info-row">
                        <th class="day-info-cell" colspan="2">
                        <i class="fas fa-calendar-day"></i>
                            <?= formatDate($display_date) ?>
                            <?php if ($display_date === date('Y-m-d')): ?>
                            <span class="badge bg-light text-dark ms-2">Today</span>
                            <?php endif; ?>
                        </th>
                    </tr>
                    <tr>
                        <th>
                            <div class="time-header">Time</div>
                        </th>
                        <th style="width: 100%;">
                            <div class="day-header">
                                <div class="day-compact">
                                    <?= date('l, M j', strtotime($display_date)) ?>
                                </div>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($time_slots as $time_index => $time): ?>
                        <tr>
                            <td class="time-column <?= (substr($time, -2) === '00') ? 'time-hour' : '' ?>"><?= $time ?></td>
                            <?php 
                            // For specialists with multiple workpoints, determine the correct workpoint for this specific date and time
                            if (isset($has_multiple_workpoints) && $has_multiple_workpoints) {
                                $is_working = false;
                                $workpoint_id_for_slot = null;
                                $workpoint_name_for_slot = null;
                                
                                // Check each working point to see which one the specialist is working at for this date and time
                                foreach ($working_points as $wp) {
                                    $working_hours = getWorkingHours($pdo, $specialist_id, $wp['unic_id'], $display_date);
                                    
                                    if (isWithinWorkingHours($time . ':00', $working_hours)) {
                                        $is_working = true;
                                        $workpoint_id_for_slot = $wp['unic_id'];
                                        $workpoint_name_for_slot = $wp['name_of_the_place'];
                                        break; // Found the working workpoint for this slot
                                    }
                                }
                            } else {
                                // Single workpoint - use the existing logic
                                $is_working = isWithinWorkingHours($time . ':00', $current_working_hours);
                                $workpoint_id_for_slot = $workpoint_id;
                                $workpoint_name_for_slot = $workpoint_name;
                            }
                            ?>
                            <?php 
                            // Check if this time slot is in the past
                            $current_datetime = new DateTime();
                            $slot_datetime = new DateTime($display_date . ' ' . $time . ':00');
                            $is_past = $slot_datetime < $current_datetime;
                            
                            // Check if this date/time is a specialist's day off
                            $is_day_off = false;
                            if (isset($time_off_dates[$display_date])) {
                                $day_off_info = $time_off_dates[$display_date];

                                if ($day_off_info['start_time'] && $day_off_info['end_time']) {
                                    // Partial day off - check if this time slot is within the range
                                    $slot_time = $time . ':00';
                                    if ($slot_time >= $day_off_info['start_time'] && $slot_time <= $day_off_info['end_time']) {
                                        $is_day_off = true;
                                    }
                                } else {
                                    // Full day off
                                    $is_day_off = true;
                                }
                            }

                            // Check if this date/time is a workpoint holiday (both recurring and non-recurring)
                            // Only show workpoint holidays during working hours
                            $holiday_check = isWorkpointHoliday($display_date, $time . ':00', $workpoint_holidays);
                            $is_workpoint_holiday = $holiday_check['is_holiday'] && $is_working;
                            $holiday_name = $holiday_check['description'];
                            ?>
                            <td class="day-cell <?= $is_workpoint_holiday ? 'workpoint-holiday' : ($is_day_off ? 'day-off' : (!$is_working ? 'non-working' : '')) ?>"
                                data-time="<?= $time ?>"
                                data-time-index="<?= $time_index ?>"
                                data-day="current"
                                data-is-day-off="<?= $is_day_off ? 'true' : 'false' ?>"
                                data-workpoint-id="<?= $workpoint_id_for_slot ?>"
                                data-workpoint-name="<?= htmlspecialchars($workpoint_name_for_slot) ?>"
                                <?= $is_workpoint_holiday ? 'data-holiday-name="'.htmlspecialchars($holiday_name).'"' : '' ?>
                                <?php if ($is_workpoint_holiday): ?>
                                title="<?= htmlspecialchars($holiday_name) ?>"
                                <?php elseif ($is_day_off): ?>
                                title="Specialist day off"
                                <?php endif; ?>
                                <?php if (!$is_past && !$is_day_off && !$is_workpoint_holiday && $is_working): ?>
                                onclick="openAddBookingModalWithWorkpoint('<?= $display_date ?>', '<?= $time ?>', '<?= $specialist_id ?>', '<?= $workpoint_id_for_slot ?>', '<?= htmlspecialchars($workpoint_name_for_slot) ?>')"
                                <?php endif; ?>>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Floating booking boxes for current day -->
            <?php foreach ($current_booking_positions as $pos): ?>
                <?php 
                $booking = $pos['booking'];
                $status_class = getBookingStatusClass($booking);
                $tooltip = getBookingTooltip($booking, $supervisor_mode, $has_multiple_workpoints);
                
                // Get specialist colors if not in supervisor mode
                $booking_bg_color = '';
                $booking_text_color = '';
                $booking_border_color = '';
                if (!$supervisor_mode && isset($specialist_permissions['back_color']) && isset($specialist_permissions['foreground_color'])) {
                    $bg_color = $specialist_permissions['back_color'];
                    $fg_color = $specialist_permissions['foreground_color'];
                    
                    // Convert hex to RGB and create lighter version
                    $r = hexdec(substr($bg_color, 1, 2));
                    $g = hexdec(substr($bg_color, 3, 2));
                    $b = hexdec(substr($bg_color, 5, 2));
                    
                    // Mix with white to create lighter version (80% white, 20% color)
                    $r_light = round($r * 0.2 + 255 * 0.8);
                    $g_light = round($g * 0.2 + 255 * 0.8);
                    $b_light = round($b * 0.2 + 255 * 0.8);
                    
                    $booking_bg_color = sprintf("#%02x%02x%02x", $r_light, $g_light, $b_light);
                    
                    // Use darker version of the color for text (60% original color, 40% black)
                    $r_dark = round($r * 0.6);
                    $g_dark = round($g * 0.6);
                    $b_dark = round($b * 0.6);
                    
                    $booking_text_color = sprintf("#%02x%02x%02x", $r_dark, $g_dark, $b_dark);
                }
                
                // Calculate position (base values, will be adjusted by JavaScript)
                $time_slot_height = 20; // Height per time slot
                $top_base = $pos['start_slot_index'] * $time_slot_height; // Base top position
                $height = ($pos['end_slot_index'] - $pos['start_slot_index'] + 1) * $time_slot_height - 10;
                ?>
                <div class="booking-box <?= $status_class ?>" 
                     style="height: <?= $height ?>px;<?php if ($booking_bg_color): ?> background-color: <?= $booking_bg_color ?> !important; color: <?= $booking_text_color ?> !important; border-color: <?= $booking_text_color ?> !important;<?php endif; ?>"
                     data-top-base="<?= $top_base ?>"
                     data-day="current"
                     data-bs-toggle="tooltip" 
                     data-bs-html="true" 
                     title="<?= $tooltip ?>"
                     onclick="event.stopPropagation(); openBookingModal(
                         '<?= htmlspecialchars($booking['client_full_name']) ?>',
                         '<?= $pos['start_time'] ?> - <?= $pos['end_time'] ?>',
                         '<?= htmlspecialchars($specialist['name']) ?>',
                         <?= $booking['unic_id'] ?>,
                         '<?= htmlspecialchars($booking['client_phone_nr'] ?? '') ?>',
                         '<?= htmlspecialchars($booking['name_of_service'] ?? '') ?>',
                         '<?= $pos['start_time'] ?>',
                         '<?= $pos['end_time'] ?>',
                         '<?= date('Y-m-d', strtotime($booking['booking_start_datetime'])) ?>'
                     )">
                    <div class="booking-box-content">
                        <?php 
                        $workpoint_id = $booking['id_work_place'] ?? null;
                        $workpoint_index = isset($workpoint_index_lookup[$workpoint_id]) ? $workpoint_index_lookup[$workpoint_id] : 1;
                        $location_class = 'booking-location';
                        $address_class = 'booking-address';
                        
                        if ($workpoint_index === 2) {
                            $location_class .= ' workpoint-2';
                            $address_class .= ' workpoint-2';
                        } elseif ($workpoint_index === 3) {
                            $location_class .= ' workpoint-3';
                            $address_class .= ' workpoint-3';
                        }
                        ?>
                        <div class="<?= $location_class ?>"><?= htmlspecialchars($booking['name_of_the_place']) ?></div>
                        <div class="booking-client"><?= htmlspecialchars($booking['client_full_name']) ?></div>
                        <?php if ($booking['name_of_service']): ?>
                            <div class="booking-service"><?= htmlspecialchars($booking['name_of_service']) ?></div>
                        <?php endif; ?>
                        <div class="booking-time"><?= $pos['start_time'] ?> - <?= $pos['end_time'] ?></div>
                        <div class="booking-id" style="font-size: 0.65rem; color: #666; opacity: 0.9;">Booking:#<?= $booking['unic_id'] ?></div>
                        <?php if (isset($has_multiple_workpoints) && $has_multiple_workpoints): ?>
                            <div class="<?= $address_class ?>"><em><?= htmlspecialchars($booking['address'] ?? '') ?></em></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Next Day -->
        <div class="daily-grid faded" data-day="next" onclick="slidePanelToCenter('next', '<?= $next_date ?>')" style="cursor: pointer;">
            <table cellspacing="0" cellpadding="0">
                <thead>
                    <tr class="day-info-row">
                        <th class="day-info-cell" colspan="2">
                        <i class="fas fa-calendar-day"></i>
                            <?= formatDate($next_date) ?>
                        </th>
                    </tr>
                    <tr>
                        <th>
                            <div class="time-header">Time</div>
                        </th>
                        <th style="width: 100%;">
                            <div class="day-header">
                                <div class="day-compact">
                                    <?= date('l, M j', strtotime($next_date)) ?>
                                </div>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($time_slots as $time_index => $time): ?>
                        <tr>
                            <td class="time-column <?= (substr($time, -2) === '00') ? 'time-hour' : '' ?>"><?= $time ?></td>
                            <?php 
                            $is_working = isWithinWorkingHours($time . ':00', $next_working_hours);
                            ?>
                            <?php 
                            // Check if this time slot is in the past
                            $current_datetime = new DateTime();
                            $slot_datetime = new DateTime($next_date . ' ' . $time . ':00');
                            $is_past = $slot_datetime < $current_datetime;
                            
                            // Check if this date/time is a specialist's day off
                            $is_day_off = false;
                            if (isset($time_off_dates[$next_date])) {
                                $day_off_info = $time_off_dates[$next_date];

                                if ($day_off_info['start_time'] && $day_off_info['end_time']) {
                                    // Partial day off - check if this time slot is within the range
                                    $slot_time = $time . ':00';
                                    if ($slot_time >= $day_off_info['start_time'] && $slot_time <= $day_off_info['end_time']) {
                                        $is_day_off = true;
                                    }
                                } else {
                                    // Full day off
                                    $is_day_off = true;
                                }
                            }

                            // Check if this date/time is a workpoint holiday (both recurring and non-recurring)
                            // Only show workpoint holidays during working hours
                            $holiday_check = isWorkpointHoliday($next_date, $time . ':00', $workpoint_holidays);
                            $is_workpoint_holiday = $holiday_check['is_holiday'] && $is_working;
                            $holiday_name = $holiday_check['description'];
                            ?>
                            <td class="day-cell <?= $is_workpoint_holiday ? 'workpoint-holiday' : ($is_day_off ? 'day-off' : (!$is_working ? 'non-working' : '')) ?>"
                                data-time="<?= $time ?>"
                                data-time-index="<?= $time_index ?>"
                                data-day="next"
                                data-is-day-off="<?= $is_day_off ? 'true' : 'false' ?>"
                                <?= $is_workpoint_holiday ? 'data-holiday-name="'.htmlspecialchars($holiday_name).'"' : '' ?>
                                <?php if ($is_workpoint_holiday): ?>
                                title="<?= htmlspecialchars($holiday_name) ?>"
                                <?php elseif ($is_day_off): ?>
                                title="Specialist day off"
                                <?php endif; ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Floating booking boxes for next day -->
            <?php foreach ($next_booking_positions as $pos): ?>
                <?php 
                $booking = $pos['booking'];
                $status_class = getBookingStatusClass($booking);
                $tooltip = getBookingTooltip($booking, $supervisor_mode, $has_multiple_workpoints);
                
                // Get specialist colors if not in supervisor mode
                $booking_bg_color = '';
                $booking_text_color = '';
                if (!$supervisor_mode && isset($specialist_permissions['back_color']) && isset($specialist_permissions['foreground_color'])) {
                    $bg_color = $specialist_permissions['back_color'];
                    $fg_color = $specialist_permissions['foreground_color'];
                    
                    // Convert hex to RGB and create lighter version
                    $r = hexdec(substr($bg_color, 1, 2));
                    $g = hexdec(substr($bg_color, 3, 2));
                    $b = hexdec(substr($bg_color, 5, 2));
                    
                    // Mix with white to create lighter version (80% white, 20% color)
                    $r_light = round($r * 0.2 + 255 * 0.8);
                    $g_light = round($g * 0.2 + 255 * 0.8);
                    $b_light = round($b * 0.2 + 255 * 0.8);
                    
                    $booking_bg_color = sprintf("#%02x%02x%02x", $r_light, $g_light, $b_light);
                    
                    // Use darker version of the color for text (60% original color, 40% black)
                    $r_dark = round($r * 0.6);
                    $g_dark = round($g * 0.6);
                    $b_dark = round($b * 0.6);
                    
                    $booking_text_color = sprintf("#%02x%02x%02x", $r_dark, $g_dark, $b_dark);
                }
                
                // Calculate position (base values, will be adjusted by JavaScript)
                $time_slot_height = 20; // Height per time slot
                $top_base = $pos['start_slot_index'] * $time_slot_height; // Base top position
                $height = ($pos['end_slot_index'] - $pos['start_slot_index'] + 1) * $time_slot_height - 10;
                ?>
                <div class="booking-box <?= $status_class ?>" 
                     style="height: <?= $height ?>px;<?php if ($booking_bg_color): ?> background-color: <?= $booking_bg_color ?> !important; color: <?= $booking_text_color ?> !important; border-color: <?= $booking_text_color ?> !important;<?php endif; ?>"
                     data-top-base="<?= $top_base ?>"
                     data-day="next"
                     data-bs-toggle="tooltip" 
                     data-bs-html="true" 
                     title="<?= $tooltip ?>"
                     onclick="event.stopPropagation(); openBookingModal(
                         '<?= htmlspecialchars($booking['client_full_name']) ?>',
                         '<?= $pos['start_time'] ?> - <?= $pos['end_time'] ?>',
                         '<?= htmlspecialchars($specialist['name']) ?>',
                         <?= $booking['unic_id'] ?>,
                         '<?= htmlspecialchars($booking['client_phone_nr'] ?? '') ?>',
                         '<?= htmlspecialchars($booking['name_of_service'] ?? '') ?>',
                         '<?= $pos['start_time'] ?>',
                         '<?= $pos['end_time'] ?>',
                         '<?= date('Y-m-d', strtotime($booking['booking_start_datetime'])) ?>'
                     )">
                    <div class="booking-box-content">
                        <?php 
                        $workpoint_id = $booking['id_work_place'] ?? null;
                        $workpoint_index = isset($workpoint_index_lookup[$workpoint_id]) ? $workpoint_index_lookup[$workpoint_id] : 1;
                        $location_class = 'booking-location';
                        $address_class = 'booking-address';
                        
                        if ($workpoint_index === 2) {
                            $location_class .= ' workpoint-2';
                            $address_class .= ' workpoint-2';
                        } elseif ($workpoint_index === 3) {
                            $location_class .= ' workpoint-3';
                            $address_class .= ' workpoint-3';
                        }
                        ?>
                        <div class="<?= $location_class ?>"><?= htmlspecialchars($booking['name_of_the_place']) ?></div>
                        <div class="booking-client"><?= htmlspecialchars($booking['client_full_name']) ?></div>
                        <?php if ($booking['name_of_service']): ?>
                            <div class="booking-service"><?= htmlspecialchars($booking['name_of_service']) ?></div>
                        <?php endif; ?>
                        <div class="booking-time"><?= $pos['start_time'] ?> - <?= $pos['end_time'] ?></div>
                        <div class="booking-id" style="font-size: 0.65rem; color: #666; opacity: 0.9;">Booking:#<?= $booking['unic_id'] ?></div>
                        <?php if (isset($has_multiple_workpoints) && $has_multiple_workpoints): ?>
                            <div class="<?= $address_class ?>"><em><?= htmlspecialchars($booking['address'] ?? '') ?></em></div>
                        <?php endif; ?>
                    </div>
                </div>
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
    
    // Dynamically position booking boxes using table cells as reference points
    function positionBookingBoxes() {
        var containers = document.querySelectorAll('.daily-grid');
        
        containers.forEach(function(container) {
            var table = container.querySelector('table');
            if (!table) {
                // Retry after a short delay if elements aren't ready
                setTimeout(positionBookingBoxes, 50);
                return;
            }

            // Get table position relative to its container
            var tableRect = table.getBoundingClientRect();
            var containerRect = container.getBoundingClientRect();
            
            // Calculate table offset within container
            var tableOffsetLeft = tableRect.left - containerRect.left;
            var tableOffsetTop = tableRect.top - containerRect.top;

            // Get the first row of the table body to use as reference
            var firstRow = table.querySelector('tbody tr');
            if (!firstRow) return;

            // Get all cells in the first row (time column + day column)
            var cells = firstRow.children;
            if (cells.length < 2) return;

            // Calculate the width of the time column (first cell)
            var timeColumnWidth = cells[0].offsetWidth;
            
            // Calculate the width of the day column (second cell)
            var dayColumnWidth = cells[1].offsetWidth;

            // Get header height (accounting for padding)
            var thead = table.querySelector('thead');
            var headerHeight = thead ? thead.offsetHeight : 0;

            // Position each booking box for this container
            var dayType = container.getAttribute('data-day');
            var bookingBoxes = container.querySelectorAll('.booking-box[data-day="' + dayType + '"]');
            
            bookingBoxes.forEach(function(box) {
                var topBase = parseInt(box.getAttribute('data-top-base'), 10);

                if (!isNaN(topBase)) {
                    // Get the actual cell position for more accurate positioning
                    var targetCell = table.querySelector('tbody tr:first-child td:nth-child(2)');
                    if (targetCell) {
                        var cellRect = targetCell.getBoundingClientRect();
                        var left = cellRect.left - containerRect.left;
                        var top = tableOffsetTop + headerHeight + topBase;
                        var width = cellRect.width;
                    } else {
                        // Fallback to calculated positioning
                        var left = tableOffsetLeft + timeColumnWidth;
                        var top = tableOffsetTop + headerHeight + topBase;
                        var width = dayColumnWidth;
                    }

                    // Add small margin for better visual alignment
                    var margin = 1;
                    box.style.left = (left + margin) + 'px';
                    box.style.top = (top + margin) + 'px';
                    box.style.width = (width - (margin * 2)) + 'px';
                    
                    // Debug: Add a temporary border to see positioning
                    if (window.location.search.includes('debug=1')) {
                        box.style.border = '2px solid red';
                        console.log('Booking box positioned:', {
                            dayType: dayType,
                            left: left,
                            top: top,
                            width: width,
                            tableOffsetLeft: tableOffsetLeft,
                            tableOffsetTop: tableOffsetTop,
                            timeColumnWidth: timeColumnWidth,
                            dayColumnWidth: dayColumnWidth,
                            headerHeight: headerHeight,
                            usingCellPosition: !!targetCell
                        });
                    }
                }
            });
        });
    }
    
    // Position boxes on load
    positionBookingBoxes();
    
    // Reposition on window resize
    window.addEventListener('resize', positionBookingBoxes);
    
    // Also reposition after a short delay to ensure all styles are applied
    setTimeout(positionBookingBoxes, 100);
    
    // Additional positioning after images and fonts are loaded
    window.addEventListener('load', function() {
        setTimeout(positionBookingBoxes, 50);
    });
});

// Day navigation
function navigateDay(direction) {
    const currentDate = new Date('<?= $display_date ?>');
    const daysToAdd = direction === 'next' ? 1 : -1;
    currentDate.setDate(currentDate.getDate() + daysToAdd);
    
    const newDate = currentDate.toISOString().split('T')[0];
    const url = new URL(window.location);
    url.searchParams.set('start_date', newDate);
    url.searchParams.set('end_date', newDate);
    url.searchParams.set('period', 'custom');
    
    window.location.href = url.toString();
}

// Slide individual panel to center
function slidePanelToCenter(panelType, date) {
    const panels = document.querySelectorAll('.daily-grid');
    const clickedPanel = document.querySelector(`[data-day="${panelType}"]`);
    
    // Remove any existing animation classes
    panels.forEach(panel => {
        panel.classList.remove('sliding-to-center', 'sliding-from-left', 'sliding-from-right', 'sliding-right-to-center', 'sliding-left-to-center');
    });
    
    // Force a reflow to ensure the class removal is applied
    clickedPanel.offsetHeight;
    
    // Add animation classes based on which panel was clicked
    if (panelType === 'prev') {
        // Left panel clicked - it slides right to center, current and next stay in place
        clickedPanel.classList.add('sliding-left-to-center');
    } else if (panelType === 'next') {
        // Right panel clicked - it slides left to center, current and prev stay in place
        clickedPanel.classList.add('sliding-right-to-center');
    }
    
    // Navigate after animation completes
    setTimeout(() => {
        const url = new URL(window.location);
        url.searchParams.set('start_date', date);
        url.searchParams.set('end_date', date);
        url.searchParams.set('period', 'custom');
        
        window.location.href = url.toString();
    }, 600); // Match the CSS transition duration
}

// Make table header sticky on scroll
window.addEventListener('scroll', function() {
    const tables = document.querySelectorAll('.daily-grid table');
    
    tables.forEach(function(table) {
        const header = table.querySelector('thead');
        
        if (window.scrollY > 100) {
            header.style.position = 'sticky';
            header.style.top = '0';
        } else {
            header.style.position = 'static';
        }
    });
});

// Function to open booking modal with workpoint information (specialist mode)
function openAddBookingModalWithWorkpoint(date, time, specialistId, workpointId, workpointName) {
    console.log('openAddBookingModalWithWorkpoint called with:', { date, time, specialistId, workpointId, workpointName });
    
    // Store workpoint information in sessionStorage for the booking modal to use
    sessionStorage.setItem('selectedWorkpointId', workpointId);
    sessionStorage.setItem('selectedWorkpointName', workpointName);
    
    // Set the timeslot workpoint field in the modal
    const timeslotWorkpointField = document.getElementById('timeslotWorkpointId');
    if (timeslotWorkpointField) {
        timeslotWorkpointField.value = workpointId;
        console.log('Set timeslot workpoint field to:', workpointId);
    } else {
        console.error('timeslotWorkpointId field not found in modal');
    }
    
    // Call the original booking modal function
    openAddBookingModal(date, time, specialistId);
}
</script> 