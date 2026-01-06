<?php
/**
 * Weekly Calendar Template
 * Shows bookings in a grid format with days as columns and time slots as rows
 * Uses layered approach: background table + floating booking boxes
 */

require_once 'includes/calendar_functions.php';

// Check if this is supervisor mode
$supervisor_mode = isset($_GET['supervisor_mode']) && $_GET['supervisor_mode'] === 'true';

// Get specialists for supervisor mode
$specialists_for_tabs = [];
if ($supervisor_mode) {
    // Get workpoint ID from the URL parameter
    $workpoint_id = $_GET['working_point_user_id'] ?? null;
    
    // Ensure workpoint_id is available globally for supervisor mode
    if ($workpoint_id && $workpoint_id !== 'null' && $workpoint_id !== '') {
        // Get all specialists for this workpoint with unique entries
        $stmt = $pdo->prepare("
            SELECT s.unic_id, s.name, s.speciality, s.email, s.phone_nr, 
                   COALESCE(ss.back_color, '#667eea') as back_color, 
                   COALESCE(ss.foreground_color, '#ffffff') as foreground_color
            FROM specialists s
            INNER JOIN (
                SELECT DISTINCT specialist_id 
                FROM working_program 
                WHERE working_place_id = ?
            ) wpr ON s.unic_id = wpr.specialist_id 
            LEFT JOIN (
                SELECT specialist_id, 
                       MAX(back_color) as back_color, 
                       MAX(foreground_color) as foreground_color
                FROM specialists_setting_and_attr 
                GROUP BY specialist_id
            ) ss ON s.unic_id = ss.specialist_id
            ORDER BY s.name
        ");
        $stmt->execute([$workpoint_id]);
        $raw_specialists = $stmt->fetchAll();
        
        // Deduplicate by specialist ID using associative array approach
        $specialists_for_tabs = [];
        $unique_specialists = [];
        
        foreach ($raw_specialists as $spec) {
            $spec_id = (int)$spec['unic_id']; // Ensure it's an integer
            
            if (!isset($unique_specialists[$spec_id])) {
                $unique_specialists[$spec_id] = $spec;
            }
        }
        
        // Convert back to indexed array
        $specialists_for_tabs = array_values($unique_specialists);
        
        // Set default colors if not set
        foreach ($specialists_for_tabs as &$spec) {
            if (!$spec['back_color']) $spec['back_color'] = '#667eea';
            if (!$spec['foreground_color']) $spec['foreground_color'] = '#ffffff';
        }
        unset($spec); // Unset the reference to prevent array corruption
        

        

    } else {
        $specialists_for_tabs = [];
    }
    
    // Ensure workpoint_id is always set for supervisor mode
    if (!$workpoint_id || $workpoint_id === 'null' || $workpoint_id === '') {
        $workpoint_id = $_GET['working_point_user_id'] ?? 1; // Fallback to workpoint 1 if none specified
    }
}



// Get week data
$week_data = getWeekCalendarData($start_date);

// Calculate dynamic start and end times based on working hours
$earliest_start = '08:00';
$latest_end = '17:00';

// Check working hours for each day to find earliest start and latest end
foreach ($week_data as $day) {
    if ($supervisor_mode && !empty($specialists_for_tabs)) {
        // In supervisor mode, check all specialists' working hours
        foreach ($specialists_for_tabs as $spec) {
            // For supervisor mode, we need to get the workpoint_id from the URL
            $workpoint_id = $_GET['working_point_user_id'] ?? null;
            $working_hours = $workpoint_id ? getWorkingHours($pdo, $spec['unic_id'], $workpoint_id, $day['date']) : null;
            if ($working_hours) {
                foreach ($working_hours as $shift) {
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
        }
    } else {
        // Regular mode - use current specialist
        // Get the workpoint_id from the working_points array
        $workpoint_id = !empty($working_points) ? $working_points[0]['unic_id'] : null;
        $working_hours = $workpoint_id ? getWorkingHours($pdo, $specialist_id, $workpoint_id, $day['date']) : null;
    if ($working_hours) {
        foreach ($working_hours as $shift) {
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
    }
}

// Also check all bookings to ensure we include them in the time range
foreach ($bookings as $booking) {
    $booking_start = date('H:i', strtotime($booking['booking_start_datetime']));
    $booking_end = date('H:i', strtotime($booking['booking_end_datetime']));
    
    if ($booking_start < $earliest_start) {
        $earliest_start = $booking_start;
    }
    if ($booking_end > $latest_end) {
        $latest_end = $booking_end;
    }
}

// Ensure minimum range of 8 hours
$start_hour = (int)substr($earliest_start, 0, 2);
$end_hour = (int)substr($latest_end, 0, 2);
if ($end_hour - $start_hour < 8) {
    $latest_end = sprintf('%02d:00', $start_hour + 8);
}

// Get workpoint holidays (both recurring and non-recurring)
$workpoint_holidays = [];
if ($supervisor_mode) {
    // In supervisor mode, use workpoint_id from URL
    if ($workpoint_id && $workpoint_id !== 'null' && $workpoint_id !== '') {
        $stmt = $pdo->prepare("
            SELECT date_off, start_time, end_time, is_recurring, description
            FROM workingpoint_time_off
            WHERE workingpoint_id = ?
            ORDER BY date_off
        ");
        $stmt->execute([$workpoint_id]);
        $workpoint_holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // In specialist mode, get workpoint from working_points
    if (!empty($working_points)) {
        $wp = $working_points[0]; // Get the first (and only) working point
        $stmt = $pdo->prepare("
            SELECT date_off, start_time, end_time, is_recurring, description
            FROM workingpoint_time_off
            WHERE workingpoint_id = ?
            ORDER BY date_off
        ");
        $stmt->execute([$wp['unic_id']]);
        $workpoint_holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

$time_slots = generateTimeSlots($earliest_start, $latest_end);

// Calculate positions for all bookings
$booking_positions = [];
foreach ($bookings as $booking) {
    $booking_date = date('Y-m-d', strtotime($booking['booking_start_datetime']));
    $start_time = formatTime($booking['booking_start_datetime']);
    $end_time = formatTime($booking['booking_end_datetime']);
    
    // Find day index
    $day_index = -1;
    foreach ($week_data as $index => $day) {
        if ($day['date'] === $booking_date) {
            $day_index = $index;
            break;
        }
    }
    
    if ($day_index !== -1) {
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
                'day_index' => $day_index,
                'start_slot_index' => $start_slot_index,
                'end_slot_index' => $end_slot_index,
                'start_time' => $start_time,
                'end_time' => $end_time
            ];
        }
    }
}
?>

<style>
<?php
// Set specialist colors as CSS variables if available (only for specialist mode, not supervisor mode)
if (!$supervisor_mode && isset($specialist_permissions['back_color']) && isset($specialist_permissions['foreground_color'])) {
    echo ":root {\n";
    echo "    --specialist-bg-color: " . $specialist_permissions['back_color'] . ";\n";
    echo "    --specialist-fg-color: " . $specialist_permissions['foreground_color'] . ";\n";
    echo "}\n";
}
?>

.weekly-calendar {
    overflow-x: auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
}

.weekly-grid-container {
    display: flex;
    justify-content: center;
    width: 100%;
    position: relative;
    overflow-x: auto;
    overflow-y: visible;
    padding: 0 10px;
}

.weekly-grid {
    width: 100%;
    min-width: 800px;
    max-width: 1200px;
    border-collapse: collapse;
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: relative;
    z-index: 1;
    border-spacing: 0;
    border: 1px solid #e9ecef;
}

<?php if (count($specialists_for_tabs) > 5): ?>
/* Expanded layout for more than 5 specialists */
.weekly-grid {
    width: 96% !important;
    min-width: 96% !important;
    max-width: 96% !important;
}

.specialist-tabs {
    width: calc(96% - 24px) !important;
    min-width: calc(96% - 24px) !important;
    max-width: calc(96% - 24px) !important;
}
<?php endif; ?>

.weekly-grid th,
.weekly-grid td {
    border: 1px solid #e9ecef;
    padding: 0;
    vertical-align: top;
    min-height: 30px;
    height: 30px;
}

/* Specialist Tabs Styles */
.specialist-tabs-container {
    margin-bottom: -2px;
    display: flex;
    justify-content: center;
    width: 100%;
    max-width: 100%;
    padding-top: 5px;
    padding-left: 12px;
    padding-right: 12px;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #999 #fff;
}

/* Custom scrollbar for specialist tabs - slim white with darker arrows */
.specialist-tabs-container::-webkit-scrollbar {
    height: 6px;
    background: #fff;
    border-radius: 3px;
}

.specialist-tabs-container::-webkit-scrollbar-track {
    background: #fff;
    border-radius: 3px;
}

.specialist-tabs-container::-webkit-scrollbar-thumb {
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 3px;
}

.specialist-tabs-container::-webkit-scrollbar-thumb:hover {
    background: #f0f0f0;
    border-color: #999;
}

/* Scrollbar buttons (arrows) */
.specialist-tabs-container::-webkit-scrollbar-button {
    width: 12px;
    height: 6px;
    background: #fff;
    border: 1px solid #999;
}

.specialist-tabs-container::-webkit-scrollbar-button:hover {
    background: #f0f0f0;
}

/* Arrow styles */
.specialist-tabs-container::-webkit-scrollbar-button:horizontal:start:decrement {
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="6" viewBox="0 0 12 6"><path d="M8 1 L4 3 L8 5" fill="none" stroke="%23666" stroke-width="1.5"/></svg>');
    background-repeat: no-repeat;
    background-position: center;
}

.specialist-tabs-container::-webkit-scrollbar-button:horizontal:end:increment {
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="6" viewBox="0 0 12 6"><path d="M4 1 L8 3 L4 5" fill="none" stroke="%23666" stroke-width="1.5"/></svg>');
    background-repeat: no-repeat;
    background-position: center;
}

.specialist-tabs {
    display: flex;
    gap: 0;
    background: #f8f9fa;
    border-radius: 20px 20px 0 0;
    padding: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    width: 98%;
    min-width: 784px; /* 98% of 800px */
    max-width: 1176px; /* 98% of 1200px */
    overflow-x: auto;
    overflow-y: visible;
}

/* Hide scrollbar when content doesn't overflow */
.specialist-tabs-container {
    overflow-x: hidden;
}

.specialist-tabs-container.scrollable {
    overflow-x: auto;
}

.specialist-tab {
    padding: 10px 15px;
    border: none;
    border-radius: 20px 20px 0 0;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
    min-width: 100px;
    max-width: 150px;
    text-align: center;
    position: relative;
    margin-right: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.specialist-tab:last-child {
    margin-right: 0;
}

.specialist-tab:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
}

.specialist-tab.active {
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    transform: translateY(-2px);
    border-bottom: 2px solid white;
    margin-bottom: -2px;
}

.specialist-tab:not(.active) {
    opacity: 0.8;
}

.specialist-tab:not(.active):hover {
    opacity: 1;
}

.weekly-grid tbody td {
    min-height: 20px;
    height: 20px;
}

.weekly-grid th {
    background: #1e3a8a;
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

/* Specialist color overrides for weekly grid headers */
.weekly-grid.specialist-colored th {
    background: var(--specialist-bg-color) !important;
    color: var(--specialist-fg-color) !important;
}

.week-info-row th {
    background: #1e3a8a;
    color: white;
    text-align: center;
    padding: 8px 0;
    font-weight: 700;
    font-size: 1.1rem;
    border: 1px solid #ddd;
    min-height: 47px;
    max-height: 47px;
    height: 47px;
    vertical-align: middle;
}

/* Specialist color overrides for week info headers */
.weekly-grid.specialist-colored .week-info-row th {
    background: var(--specialist-bg-color) !important;
    color: var(--specialist-fg-color) !important;
}

.week-nav-btn {
    background: rgba(255,255,255,0.2);
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    font-size: 0.8rem;
}

.week-nav-btn:hover {
    background: rgba(255,255,255,0.3);
}

.week-nav-buttons {
    display: flex;
    gap: 10px;
}

.week-info-cell {
    background: #1e3a8a !important;
    color: white !important;
}

/* Specialist color overrides for week info cells */
.weekly-grid.specialist-colored .week-info-cell {
    background: var(--specialist-bg-color) !important;
    color: var(--specialist-fg-color) !important;
}

.week-info-header {
    font-size: 0.9rem;
    font-weight: 600;
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    line-height: 1.2;
}

.week-info-header i {
    color: var(--primary-color);
    font-size: 0.9rem;
}

.day-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    line-height: 1.2;
}

.day-compact {
    font-size: 0.8rem;
    font-weight: 600;
    text-align: center;
    line-height: 1.2;
}

.day-name {
    font-size: 0.8rem;
    font-weight: 600;
}

.day-number {
    font-size: 1rem;
    font-weight: 700;
}

.day-header.today {
    background: rgba(255,255,255,0.2);
    border-radius: 8px;
    padding: 3px;
}

/* Clickable date headers in specialist mode */
.day-header[onclick] {
    transition: all 0.3s ease;
}

.day-header[onclick]:hover {
    background: rgba(255,255,255,0.3);
    border-radius: 8px;
    transform: scale(1.05);
}

.time-column {
    background: #f8f9fa;
    font-weight: 600;
    color: var(--dark-color);
    text-align: center;
    vertical-align: middle;
    font-size: 0.7rem;
    min-width: 80px;
    max-width: 80px;
    width: 80px;
    padding: 0;
    border: 1px solid #e9ecef;
    line-height: 1;
    min-height: 20px;
    height: 20px;
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
}

.day-cell[onclick] {
    cursor: pointer;
}

.day-cell.non-working {
    background: #f8f9fa;
}

/* Working hours cells - white background */
.day-cell.working {
    background: white;
    cursor: pointer;
}

/* Primary working point - white background */
.day-cell.working-primary {
    background: white;
    cursor: pointer;
}

/* Secondary working point - light red background */
.day-cell.working-secondary {
    background: #ffebee;
    cursor: pointer;
}

/* Tertiary working point - light red background */
.day-cell.working-tertiary {
    background: #ffcdd2;
    cursor: pointer;
}

/* Non-working hours cells - gray background */
.day-cell.non-working {
    background: #f8f9fa;
    cursor: default;
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

/* Past time cells within working hours - white background but not clickable */
.day-cell.non-working[data-is-working="true"] {
    background: white;
    opacity: 0.7;
    cursor: default;
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
    width: 120px; /* Will be set dynamically by JavaScript */
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
    font-weight: 700;
    font-size: 0.7rem;
    color: #fff;
    background: rgba(0,0,0,0.2);
    padding: 1px 3px;
    border-radius: 2px;
    margin-bottom: 1px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
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





.empty-slot {
    color: #6c757d;
    font-style: italic;
    font-size: 0.7rem;
    text-align: center;
    padding: 8px 4px;
}

.week-info-box {
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

.week-info {
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

.weekly-grid th:first-child {
    background: #1e3a8a !important;
    color: white !important;
    min-width: 80px;
    max-width: 80px;
    width: 80px;
    text-align: center;
    vertical-align: middle;
    padding: 0;
    line-height: 1;
}

.week-info-row th:first-child {
    background: #1e3a8a !important;
    color: white !important;
    min-width: 80px;
    max-width: 80px;
    width: 80px;
    text-align: center;
    vertical-align: middle;
    padding: 0;
    line-height: 1;
}

/* Responsive adjustments for narrower devices */
@media (max-width: 1200px) {
    .weekly-grid-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
    }
    
    .specialist-tabs-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    /* Show scroll hint */
    .weekly-grid-container::-webkit-scrollbar {
        height: 8px;
    }
    
    .weekly-grid-container::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 4px;
    }
}

@media (max-width: 768px) {
    .weekly-grid {
        min-width: 700px;
        font-size: 0.85rem;
    }
    
    .specialist-tabs {
        min-width: 700px;
    }
    
    .weekly-grid th,
    .weekly-grid td {
        padding: 2px;
        font-size: 0.7rem;
        min-height: 25px;
        max-height: 25px;
        height: 25px;
    }
    
    .day-name {
        font-size: 0.7rem;
    }
    
    .booking-box {
        font-size: 0.65rem;
    }
    
    .booking-time {
        font-size: 0.6rem;
    }
    
    .booking-client {
        font-size: 0.7rem;
    }
    
    .booking-service {
        font-size: 0.65rem;
    }
    
    .day-number {
        font-size: 0.9rem;
    }
    
    .booking-box {
        padding: 1px 3px;
        font-size: 0.6rem;
    }
    
    .calendar-legend {
        gap: 15px;
        padding: 8px 15px;
    }
    
    .legend-item {
        font-size: 0.8rem;
    }
    
    .week-nav-btn {
        font-size: 0.7rem;
        padding: 4px 8px;
    }
    
    .week-info-row th {
        padding: 4px 0;
        min-height: 40px;
        max-height: 40px;
        height: 40px;
    }
}
</style>

<div class="weekly-calendar">
    <?php if ($supervisor_mode): ?>
        <!-- Specialist Tabs for Supervisor Mode -->
        <div class="specialist-tabs-container">
            <div class="specialist-tabs">
                <?php 
                // Get the selected specialist from URL parameter or from parent scope or default to first
                $selected_specialist_id = $_GET['selected_specialist'] ?? $selected_specialist ?? (!empty($specialists_for_tabs) ? $specialists_for_tabs[0]['unic_id'] : null);
                foreach ($specialists_for_tabs as $index => $spec): 
                    $is_active = ($spec['unic_id'] == $selected_specialist_id);
                ?>
                    <button class="specialist-tab <?= $is_active ? 'active' : '' ?>" 
                            data-specialist-id="<?= $spec['unic_id'] ?>"
                            style="background-color: <?= $spec['back_color'] ?>; color: <?= $spec['foreground_color'] ?>;"
                            onclick="switchSpecialist('<?= $spec['unic_id'] ?>')"
                            title="<?= htmlspecialchars($spec['name']) ?>">
                        <?= htmlspecialchars($spec['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php 
    // Create a lookup array of specialist colors for supervisor mode
    $specialist_colors = [];
    if ($supervisor_mode && !empty($specialists_for_tabs)) {
        foreach ($specialists_for_tabs as $spec) {
            $specialist_colors[$spec['unic_id']] = [
                'back_color' => $spec['back_color'],
                'foreground_color' => $spec['foreground_color']
            ];
        }
    }
    ?>
    <?php else: ?>
        <!-- Calendar Legend removed to save space -->
    <?php endif; ?>

    <!-- Weekly Grid -->
    <div class="weekly-grid-container">
        <table class="weekly-grid <?= (!$supervisor_mode && isset($specialist_permissions['back_color'])) ? 'specialist-colored' : '' ?>" cellspacing="0" cellpadding="0">
            <thead>
                <tr class="week-info-row" id="week-info-row">
                    <th class="week-info-cell" colspan="<?= count($week_data) + 1 ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                            <div style="min-width: 120px;"></div>
                            <h2 class="week-title" style="font-size: 1.4rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px; color: white;">
                                <i class="fas fa-calendar-week"></i>
                                Week of <?= date('M j', strtotime($start_date)) ?>
                                <?php if ($supervisor_mode && !empty($specialists_for_tabs)): ?>
                                    - <span id="selected-specialist-name"><?= htmlspecialchars($specialists_for_tabs[array_search($selected_specialist_id, array_column($specialists_for_tabs, 'unic_id'))]['name'] ?? $specialists_for_tabs[0]['name']) ?></span>
                                <?php endif; ?>
                            </h2>
                            <div class="week-nav-buttons" style="display: flex; gap: 10px; margin-right: 20px;">
                                <button class="week-nav-btn" onclick="navigateWeek('prev')" 
                                        title="Navigate to previous week" 
                                        onmouseover="showWeekTargetDates('prev')" 
                                        onmouseout="hideWeekTargetDates()">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </button>
                                <button class="week-nav-btn" onclick="navigateWeek('next')" 
                                        title="Navigate to next week" 
                                        onmouseover="showWeekTargetDates('next')" 
                                        onmouseout="hideWeekTargetDates()">
                                    Next <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </th>
                </tr>
                <tr>
                    <th>
                        <div class="time-header">Time</div>
                    </th>
                    <?php foreach ($week_data as $day): ?>
                        <th>
                            <div class="day-header <?= $day['is_today'] ? 'today' : '' ?>" 
                                 <?php if (!$supervisor_mode): ?>
                                 style="cursor: pointer;"
                                 onclick="goToDayView('<?= $day['date'] ?>')"
                                 title="Click to view this day"
                                 <?php endif; ?>>
                                <div class="day-compact">
                                    <?= $day['short_day'] ?> <?= $day['day_number'] ?> -<?= date('M', strtotime($day['date'])) ?>
                                </div>
                            </div>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($time_slots as $time_index => $time): ?>
                    <tr>
                        <td class="time-column <?= (substr($time, -2) === '00') ? 'time-hour' : '' ?>"><?= $time ?></td>
                        <?php foreach ($week_data as $day_index => $day): ?>
                            <?php 
                            // Check if this date/time is a specialist's day off
                            $is_day_off = false;
                            $day_off_info = null;
                            
                            if (($supervisor_mode && $selected_specialist && isset($time_off_dates[$day['date']])) || 
                                (!$supervisor_mode && isset($specialist_id) && isset($time_off_dates[$day['date']]))) {
                                $day_off_info = $time_off_dates[$day['date']];
                                
                                // Check if current time slot is within the day off period
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
                            
                            if ($supervisor_mode) {
                                // In supervisor mode, check working hours for the selected specialist
                                $is_working = false;
                                $workpoint_class = '';
                                $workpoint_name = '';

                                // Use the global workpoint_id for supervisor mode
                                if ($workpoint_id) {
                                    // Get workpoint name from database
                                    $stmt = $pdo->prepare("SELECT name_of_the_place FROM working_points WHERE unic_id = ?");
                                    $stmt->execute([$workpoint_id]);
                                    $workpoint_name = $stmt->fetchColumn() ?: 'Workpoint ' . $workpoint_id;

                                    // Check if the selected specialist has working hours at this time
                                    // Use the first specialist from tabs if selected_specialist_id is not set
                                    $check_specialist_id = $selected_specialist_id ?? (!empty($specialists_for_tabs) ? $specialists_for_tabs[0]['unic_id'] : null);

                                    if ($check_specialist_id) {
                                        $working_hours = getWorkingHours($pdo, $check_specialist_id, $workpoint_id, $day['date']);
                                        $is_working = isWithinWorkingHours($time . ':00', $working_hours);
                                        if ($is_working) {
                                            $workpoint_class = 'working-primary';
                                        }
                                    }
                                }
                            } else {
                                // Check all working points for this specialist
                                $is_working = false;
                                $workpoint_class = '';
                                
                                if (isset($has_multiple_workpoints) && $has_multiple_workpoints) {
                                    // Check each working point
                                    $workpoint_id = null;
                                    $workpoint_name = null;
                                    foreach ($working_points as $wp_index => $wp) {
                                        $working_hours = getWorkingHours($pdo, $specialist_id, $wp['unic_id'], $day['date']);
                                        if (isWithinWorkingHours($time . ':00', $working_hours)) {
                                            $is_working = true;
                                            $workpoint_id = $wp['unic_id'];
                                            $workpoint_name = $wp['name_of_the_place'];
                                            if ($wp_index === 0) {
                                                // First working point - primary
                                                $workpoint_class = 'working-primary';
                                            } elseif ($wp_index === 1) {
                                                // Second working point
                                                $workpoint_class = 'working-secondary';
                                            } elseif ($wp_index === 2) {
                                                // Third working point
                                                $workpoint_class = 'working-tertiary';
                                            }
                                            break; // Found a working time slot, no need to check others
                                        }
                                    }
                                } else {
                                    // Single working point - get the actual workpoint from working_points array
                                    if (!empty($working_points)) {
                                        $wp = $working_points[0]; // Get the first (and only) working point
                                        $working_hours = getWorkingHours($pdo, $specialist_id, $wp['unic_id'], $day['date']);
                                        $is_working = isWithinWorkingHours($time . ':00', $working_hours);
                                        if ($is_working) {
                                            $workpoint_class = 'working-primary';
                                            $workpoint_id = $wp['unic_id'];
                                            $workpoint_name = $wp['name_of_the_place'];
                                        }
                                    } else {
                                        // Fallback if no working points found
                                        $is_working = false;
                                        $workpoint_id = null;
                                        $workpoint_name = null;
                                    }
                                }
                            }
                            ?>
                            <?php
                            // Check if this time slot is in the past using working point timezone if available, otherwise organization timezone
                            $org_timezone = ($supervisor_mode && isset($workpoint)) ? getTimezoneForWorkingPoint($workpoint) :
                                           ((!$supervisor_mode && !empty($working_points)) ? getTimezoneForWorkingPoint($working_points[0]) :
                                           getTimezoneForOrganisation($organisation));
                            $current_org = new DateTime('now', new DateTimeZone($org_timezone));
                            $slot_datetime = new DateTime($day['date'] . ' ' . $time . ':00', new DateTimeZone($org_timezone));
                            $is_past = $slot_datetime < $current_org;

                            // Check if this date/time is a workpoint holiday (both recurring and non-recurring)
                            // In supervisor mode, show holidays on ALL time slots (regardless of working hours)
                            // In specialist mode, only show holidays during working hours
                            $holiday_check = isWorkpointHoliday($day['date'], $time . ':00', $workpoint_holidays);
                            $is_workpoint_holiday = $supervisor_mode ? $holiday_check['is_holiday'] : ($holiday_check['is_holiday'] && $is_working);
                            $holiday_name = $holiday_check['description'];
                            ?>
                            <td class="day-cell <?= $is_workpoint_holiday ? 'workpoint-holiday' : ($is_day_off ? 'day-off' : (!$is_working ? 'non-working' : '')) ?> <?= $workpoint_class ?>"
                                data-day="<?= $day['date'] ?>"
                                data-time="<?= $time ?>"
                                data-day-index="<?= $day_index ?>"
                                data-time-index="<?= $time_index ?>"
                                data-is-past="<?= $is_past ? 'true' : 'false' ?>"
                                data-is-working="<?= $is_working ? 'true' : 'false' ?>"
                                data-is-day-off="<?= $is_day_off ? 'true' : 'false' ?>"
                                <?php if ($is_working || $supervisor_mode): ?>
                                data-workpoint-id="<?= $workpoint_id ?>"
                                data-workpoint-name="<?= htmlspecialchars($workpoint_name) ?>"
                                <?php endif; ?>
                                <?= $is_workpoint_holiday ? 'data-holiday-name="'.htmlspecialchars($holiday_name).'"' : '' ?>
                                <?php if ($is_workpoint_holiday): ?>
                                title="<?= htmlspecialchars($holiday_name) ?>"
                                <?php elseif ($is_day_off): ?>
                                title="Specialist day off"
                                <?php endif; ?>
                                <?php if (!$is_past && !$is_day_off && !$is_workpoint_holiday && ($is_working || $supervisor_mode)): ?>
                                <?php if ($supervisor_mode): ?>
                                onclick="openAddBookingModalWithWorkpoint('<?= $day['date'] ?>', '<?= $time ?>', currentSpecialistId, '<?= $workpoint_id ?? '' ?>', '<?= htmlspecialchars($workpoint_name ?? '') ?>')"
                                <?php else: ?>
                                onclick="openAddBookingModalWithWorkpoint('<?= $day['date'] ?>', '<?= $time ?>', '<?= $specialist_id ?>', '<?= $workpoint_id ?>', '<?= htmlspecialchars($workpoint_name) ?>')"
                                <?php endif; ?>
                                <?php endif; ?>>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Floating booking boxes -->
        <?php foreach ($booking_positions as $pos): ?>
            <?php 
            $booking = $pos['booking'];
            $status_class = getBookingStatusClass($booking);
            $tooltip = getBookingTooltip($booking, $supervisor_mode, $has_multiple_workpoints);
            
            // Calculate position (base values, will be adjusted by JavaScript)
            $time_slot_height = 20; // Height per time slot (reduced from 30)
            $left_base = $pos['day_index']; // Day index, will be multiplied by actual cell width
            $top_base = $pos['start_slot_index'] * $time_slot_height; // Base top position
            // Reduce height by half-row (10px) to allow clicking the ending slot below
            $height = ($pos['end_slot_index'] - $pos['start_slot_index'] + 1) * $time_slot_height - 10;
            
            // Apply specialist colors for future bookings
            $inline_styles = "height: {$height}px;";
            
            // In supervisor mode, use the specialist's color from the lookup array
            if ($supervisor_mode && $status_class === 'booking-future' && isset($specialist_colors[$booking['id_specialist']])) {
                $spec_color = $specialist_colors[$booking['id_specialist']];
                $bg_color = $spec_color['back_color'];
                
                // Convert hex to RGB to mix with white for lighter color
                if (preg_match('/^#([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/', $bg_color, $matches)) {
                    $r = hexdec($matches[1]);
                    $g = hexdec($matches[2]);
                    $b = hexdec($matches[3]);
                    
                    // Mix with white to create lighter version (85% white, 15% color)
                    $r_light = round($r * 0.15 + 255 * 0.85);
                    $g_light = round($g * 0.15 + 255 * 0.85);
                    $b_light = round($b * 0.15 + 255 * 0.85);
                    
                    $light_bg_color = "rgb($r_light, $g_light, $b_light)";
                    $inline_styles .= " background-color: {$light_bg_color} !important;";
                    
                    // Create darker version of the specialist color for text and border (70% of original)
                    $r_dark = round($r * 0.7);
                    $g_dark = round($g * 0.7);
                    $b_dark = round($b * 0.7);
                    
                    $dark_color = "rgb($r_dark, $g_dark, $b_dark)";
                    $inline_styles .= " color: {$dark_color} !important; border-color: {$dark_color} !important;";
                }
            }
            // In specialist mode, use the specialist's own color
            else if (!$supervisor_mode && $status_class === 'booking-future' && isset($specialist_permissions['back_color'])) {
                $bg_color = $specialist_permissions['back_color'];
                
                // Convert hex to RGB to mix with white for lighter color
                if (preg_match('/^#([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/', $bg_color, $matches)) {
                    $r = hexdec($matches[1]);
                    $g = hexdec($matches[2]);
                    $b = hexdec($matches[3]);
                    
                    // Mix with white to create lighter version (85% white, 15% color)
                    $r_light = round($r * 0.15 + 255 * 0.85);
                    $g_light = round($g * 0.15 + 255 * 0.85);
                    $b_light = round($b * 0.15 + 255 * 0.85);
                    
                    $light_bg_color = "rgb($r_light, $g_light, $b_light)";
                    $inline_styles .= " background-color: {$light_bg_color} !important;";
                    
                    // Create darker version of the specialist color for text and border (70% of original)
                    $r_dark = round($r * 0.7);
                    $g_dark = round($g * 0.7);
                    $b_dark = round($b * 0.7);
                    
                    $dark_color = "rgb($r_dark, $g_dark, $b_dark)";
                    $inline_styles .= " color: {$dark_color} !important; border-color: {$dark_color} !important;";
                }
            }
            ?>
                         <div class="booking-box <?= $status_class ?>" 
                  style="<?= $inline_styles ?>"
                  data-day-index="<?= $pos['day_index'] ?>"
                  data-top-base="<?= $top_base ?>"
                  <?php if ($supervisor_mode): ?>
                  data-specialist-id="<?= $booking['id_specialist'] ?>"
                  <?php endif; ?>
                 data-bs-toggle="tooltip" 
                 data-bs-html="true" 
                 title="<?= $tooltip ?>"
                 onclick="openBookingModal(
    '<?= htmlspecialchars($booking['client_full_name']) ?>',
    '<?= $pos['start_time'] ?> - <?= $pos['end_time'] ?>',
    '<?= htmlspecialchars($supervisor_mode ? $booking['specialist_name'] : $specialist['name']) ?>',
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
</div>

<script>
// Timezone for JavaScript (working point if available, otherwise organization)
if (typeof organizationTimezone === 'undefined') {
    organizationTimezone = '<?= ($supervisor_mode && isset($workpoint)) ? getTimezoneForWorkingPoint($workpoint) : 
                               ((!$supervisor_mode && !empty($working_points)) ? getTimezoneForWorkingPoint($working_points[0]) : 
                               getTimezoneForOrganisation($organisation)) ?>';
}

// Initialize tooltips and dynamic positioning
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Dynamically position booking boxes using table cells as reference points
    function positionBookingBoxes() {
        var table = document.querySelector('.weekly-grid');
        var container = document.querySelector('.weekly-grid-container');
        if (!table || !container) {
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

        // Get all cells in the first row (time column + day columns)
        var cells = firstRow.children;
        if (cells.length < 2) return;

        // Calculate the width of the time column (first cell)
        var timeColumnWidth = cells[0].offsetWidth;
        
        // Calculate the width of each day column (all other cells should be equal)
        var dayColumnWidth = cells[1].offsetWidth;

        // Get header height (accounting for padding)
        var thead = table.querySelector('thead');
        var headerHeight = thead ? thead.offsetHeight : 0;
        
        // Account for the padding in the header rows
        var weekInfoRow = table.querySelector('.week-info-row');
        var dayHeaderRow = table.querySelector('thead tr:last-child');
        var weekInfoHeight = weekInfoRow ? weekInfoRow.offsetHeight : 0;
        var dayHeaderHeight = dayHeaderRow ? dayHeaderRow.offsetHeight : 0;

        // Position each booking box
        document.querySelectorAll('.booking-box').forEach(function(box) {
            var dayIndex = parseInt(box.getAttribute('data-day-index'), 10);
            var topBase = parseInt(box.getAttribute('data-top-base'), 10);

            if (!isNaN(dayIndex) && !isNaN(topBase)) {
                // Get the actual cell position for more accurate positioning
                var targetCell = table.querySelector('tbody tr:first-child td:nth-child(' + (dayIndex + 2) + ')');
                if (targetCell) {
                    var cellRect = targetCell.getBoundingClientRect();
                    var left = cellRect.left - containerRect.left;
                    var top = tableOffsetTop + headerHeight + topBase;
                    var width = cellRect.width;
                } else {
                    // Fallback to calculated positioning
                    var left = tableOffsetLeft + timeColumnWidth + (dayIndex * dayColumnWidth);
                    var top = tableOffsetTop + headerHeight + topBase;
                    var width = dayColumnWidth;
                }

                // Add small margin for better visual alignment
                var margin = 1;
                box.style.left = (left + margin) + 'px';
                box.style.top = (top + margin) + 'px';
                box.style.width = (width - (margin * 2)) + 'px';
                

            }
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

// Navigate to day view
function goToDayView(date) {
    const url = new URL(window.location);
    url.searchParams.set('start_date', date);
    url.searchParams.set('end_date', date);
    url.searchParams.set('period', 'custom');
    url.searchParams.set('view', 'daily');
    
    window.location.href = url.toString();
}

// Week navigation
function navigateWeek(direction) {
    const currentStart = new Date('<?= $start_date ?>');
    const daysToAdd = direction === 'next' ? 7 : -7;
    currentStart.setDate(currentStart.getDate() + daysToAdd);
    
    const newStartDate = currentStart.toISOString().split('T')[0];
    const newEndDate = new Date(currentStart);
    newEndDate.setDate(newEndDate.getDate() + 6);
    const newEndDateStr = newEndDate.toISOString().split('T')[0];
    
    const url = new URL(window.location);
    url.searchParams.set('start_date', newStartDate);
    url.searchParams.set('end_date', newEndDateStr);
    url.searchParams.set('period', 'custom');
    
    // Preserve selected specialist in supervisor mode
    <?php if ($supervisor_mode): ?>
    if (currentSpecialistId) {
        url.searchParams.set('selected_specialist', currentSpecialistId);
    }
    <?php endif; ?>
    
    window.location.href = url.toString();
}

// Show week target dates on hover
function showWeekTargetDates(direction) {
    const currentStart = new Date('<?= $start_date ?>');
    const daysToAdd = direction === 'next' ? 7 : -7;
    currentStart.setDate(currentStart.getDate() + daysToAdd);
    
    const newStartDate = currentStart.toISOString().split('T')[0];
    const newEndDate = new Date(currentStart);
    newEndDate.setDate(newEndDate.getDate() + 6);
    const newEndDateStr = newEndDate.toISOString().split('T')[0];
    
    // Format the dates for display
    const startFormatted = new Date(newStartDate).toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
    const endFormatted = new Date(newEndDateStr).toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
    
    // Create or update tooltip
    let tooltip = document.getElementById('week-nav-tooltip');
    if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.id = 'week-nav-tooltip';
        tooltip.style.cssText = `
            position: fixed;
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            z-index: 10000;
            pointer-events: none;
            white-space: nowrap;
        `;
        document.body.appendChild(tooltip);
    }
    
    tooltip.innerHTML = `Target: ${startFormatted} to ${endFormatted}`;
    tooltip.style.display = 'block';
    
    // Position tooltip near mouse
    document.addEventListener('mousemove', function moveTooltip(e) {
        tooltip.style.left = (e.clientX + 10) + 'px';
        tooltip.style.top = (e.clientY - 30) + 'px';
    });
}

// Hide week target dates
function hideWeekTargetDates() {
    const tooltip = document.getElementById('week-nav-tooltip');
    if (tooltip) {
        tooltip.style.display = 'none';
    }
}

// Make table header sticky on scroll
window.addEventListener('scroll', function() {
    const table = document.querySelector('.weekly-grid');
    const header = table.querySelector('thead');
    
    if (window.scrollY > 100) {
        header.style.position = 'sticky';
        header.style.top = '0';
    } else {
        header.style.position = 'static';
    }
});

<?php if ($supervisor_mode): ?>
// Function to open booking modal with workpoint information
function openAddBookingModalWithWorkpoint(date, time, specialistId, workpointId, workpointName) {
    // Store workpoint information in sessionStorage for the booking modal to use
    sessionStorage.setItem('selectedWorkpointId', workpointId);
    sessionStorage.setItem('selectedWorkpointName', workpointName);
    
    // Update the workpoint_id field in the modal BEFORE opening it
    const workpointIdField = document.querySelector('input[name="workpoint_id"]');
    if (workpointIdField) {
        workpointIdField.value = workpointId;
    }
    
    // Call the original booking modal function
    openAddBookingModal(date, time, specialistId);
}

// Specialist switching functionality for supervisor mode
let currentSpecialistId = '<?= $specialists_for_tabs[0]['unic_id'] ?? '' ?>';
const specialistsData = <?= json_encode($specialists_for_tabs) ?>;

// Pre-load working hours data for all specialists
const workingHoursData = <?= json_encode(array_reduce($specialists_for_tabs, function($carry, $spec) use ($pdo, $week_data) {
    $spec_working_hours = [];
    // For supervisor mode, get workpoint_id from URL
    $workpoint_id = $_GET['working_point_user_id'] ?? null;
    foreach ($week_data as $day) {
        $day_working_hours = $workpoint_id ? getWorkingHours($pdo, $spec['unic_id'], $workpoint_id, $day['date']) : null;
        $spec_working_hours[$day['date']] = $day_working_hours;
    }
    $carry[$spec['unic_id']] = $spec_working_hours;
    return $carry;
}, [])) ?>;

// Pre-load time off data for all specialists
const timeOffData = <?= json_encode(array_reduce($specialists_for_tabs, function($carry, $spec) use ($pdo, $start_date, $end_date) {
    $stmt = $pdo->prepare("
        SELECT date_off, start_time, end_time 
        FROM specialist_time_off 
        WHERE specialist_id = ? 
        AND date_off BETWEEN ? AND ?
        ORDER BY date_off
    ");
    $stmt->execute([$spec['unic_id'], $start_date, $end_date]);
    $time_off_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to date-keyed array
    $time_off_by_date = [];
    foreach ($time_off_raw as $off) {
        $time_off_by_date[$off['date_off']] = [
            'start_time' => $off['start_time'],
            'end_time' => $off['end_time']
        ];
    }
    
    $carry[$spec['unic_id']] = $time_off_by_date;
    return $carry;
}, [])) ?>;



function switchSpecialist(specialistId) {
    
    // Update active tab
    document.querySelectorAll('.specialist-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelector(`[data-specialist-id="${specialistId}"]`).classList.add('active');
    
    // Update current specialist
    currentSpecialistId = specialistId;
    
    // Update URL parameter to preserve selected specialist
    const url = new URL(window.location);
    url.searchParams.set('selected_specialist', specialistId);
    window.history.replaceState({}, '', url);
    
    // Update header background color for all th in thead
    const specialist = specialistsData.find(s => s.unic_id == specialistId); // Use == for type coercion
    
    if (specialist) {
        // Week info row background
        const weekInfoRow = document.getElementById('week-info-row');
        if (weekInfoRow) {
            weekInfoRow.style.background = `linear-gradient(135deg, ${specialist.back_color} 0%, ${specialist.back_color}dd 100%)`;
        }
        
        // All th in thead
        const thElements = document.querySelectorAll('.weekly-grid thead th');
        
        thElements.forEach((th, index) => {
            th.style.setProperty('background', specialist.back_color, 'important');
            th.style.setProperty('color', specialist.foreground_color, 'important');
        });
        
        // Update specialist name in header
        const specialistNameSpan = document.getElementById('selected-specialist-name');
        if (specialistNameSpan) {
            specialistNameSpan.textContent = specialist.name;
        }
    }
    
    // Filter bookings to show only for this specialist
    filterBookingsBySpecialist(specialistId);
    
    // Update working hours display for the selected specialist
    updateWorkingHoursForSpecialist(specialistId);
}

function filterBookingsBySpecialist(specialistId) {
    // Hide all booking boxes first
    document.querySelectorAll('.booking-box').forEach(box => {
        box.style.display = 'none';
    });
    
    // Show only bookings for the selected specialist
    document.querySelectorAll('.booking-box').forEach(box => {
        const bookingSpecialistId = box.getAttribute('data-specialist-id');
        if (bookingSpecialistId === specialistId) {
            box.style.display = 'block';
        }
    });
}

function updateWorkingHoursForSpecialist(specialistId) {
    // Get working hours data for this specialist
    const specialistWorkingHours = workingHoursData[specialistId];
    if (!specialistWorkingHours) {
        return;
    }

    // Get time off dates for this specialist
    const specialistTimeOff = timeOffData[specialistId] || [];

    // Update each day cell's working hours status and click functionality
    document.querySelectorAll('.day-cell').forEach(cell => {
        const dayDate = cell.getAttribute('data-day');
        const timeSlot = cell.getAttribute('data-time');
        const dayWorkingHours = specialistWorkingHours[dayDate];

        // Check if this cell is a workpoint holiday (these should be preserved!)
        const isWorkpointHoliday = cell.classList.contains('workpoint-holiday');
        const holidayName = cell.getAttribute('data-holiday-name');

        // Remove existing working/non-working/day-off classes (but NOT workpoint-holiday!)
        cell.classList.remove('working', 'non-working', 'day-off');

        // Check if this is a day off
        if (specialistTimeOff[dayDate]) {
            const timeOffInfo = specialistTimeOff[dayDate];

            // Check if it's a partial or full day off
            if (timeOffInfo.start_time && timeOffInfo.end_time && timeSlot) {
                // Partial day off - check if this time slot is within the range
                const slotTime = timeSlot + ':00';
                if (slotTime >= timeOffInfo.start_time && slotTime <= timeOffInfo.end_time) {
                    cell.classList.add('day-off');
                    cell.style.cursor = 'not-allowed';
                    cell.style.backgroundColor = '#f5f5f5';
                    cell.style.opacity = '0.9';
                    cell.onclick = null;
                    cell.title = 'Specialist day off';
                    return; // Skip further processing for this cell
                }
            } else {
                // Full day off
                cell.classList.add('day-off');
                cell.style.cursor = 'not-allowed';
                cell.style.backgroundColor = '#f5f5f5';
                cell.style.opacity = '0.9';
                cell.onclick = null;
                cell.title = 'Specialist day off';
                return; // Skip further processing for this cell
            }
        }

        // Check if this time slot is within working hours
        if (timeSlot && dayWorkingHours && dayWorkingHours.length > 0) {
            // Check if current time in organization timezone is past this slot
            const now = new Date();
            const currentDate = now.toLocaleDateString('en-CA', { timeZone: organizationTimezone }); // YYYY-MM-DD format
            const currentTime = now.toLocaleTimeString('en-US', {
                timeZone: organizationTimezone,
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });

            // Compare date and time
            let isPast = false;
            if (dayDate < currentDate) {
                isPast = true; // Past date
            } else if (dayDate === currentDate) {
                isPast = timeSlot < currentTime.substring(0, 5); // Same date, compare time
            }
            // If dayDate > currentDate, isPast remains false (future date)

            // Check if this time slot is within any working shift
            let isWithinWorkingHours = false;
            for (const shift of dayWorkingHours) {
                const shiftStart = shift.start.substring(0, 5); // Get HH:MM format
                const shiftEnd = shift.end.substring(0, 5);

                if (timeSlot >= shiftStart && timeSlot <= shiftEnd) {
                    isWithinWorkingHours = true;
                    break;
                }
            }

            // If this is a workpoint holiday, ALWAYS keep it as a holiday (supervisor mode)
            if (isWorkpointHoliday) {
                cell.classList.add('workpoint-holiday');
                cell.style.cursor = 'not-allowed';
                cell.style.backgroundColor = '#f0f0f0';
                cell.style.opacity = '1';
                cell.onclick = null;
                cell.title = holidayName || 'Workpoint holiday';
                return; // Skip further processing for this cell
            }

            if (isWithinWorkingHours) {
                if (!isPast) {
                    // Future time slot within working hours - make it clickable
                    cell.classList.add('working');
                    cell.style.cursor = 'pointer';
                    cell.style.backgroundColor = 'white';
                    cell.style.opacity = '1';
                    cell.onclick = function() {
                        const workpointId = cell.getAttribute('data-workpoint-id');
                        const workpointName = cell.getAttribute('data-workpoint-name');
                        <?php if ($supervisor_mode): ?>
                        openAddBookingModalWithWorkpoint(dayDate, timeSlot, currentSpecialistId, workpointId, workpointName);
                        <?php else: ?>
                        openAddBookingModalWithWorkpoint(dayDate, timeSlot, '<?= $specialist_id ?>', workpointId, workpointName);
                        <?php endif; ?>
                    };
                } else {
                    // Past time slot within working hours - not clickable
                    cell.classList.add('non-working');
                    cell.style.cursor = 'default';
                    cell.style.backgroundColor = 'white';
                    cell.style.opacity = '0.7';
                    cell.onclick = null;
                }
            } else {
                // Outside working hours - gray background
                cell.classList.add('non-working');
                cell.style.cursor = 'default';
                cell.style.backgroundColor = '#f8f9fa';
                cell.style.opacity = '1';
                cell.onclick = null;
            }
        } else {
            // No working hours for this day
            // If this is a workpoint holiday, ALWAYS keep it as a holiday (supervisor mode)
            if (isWorkpointHoliday) {
                cell.classList.add('workpoint-holiday');
                cell.style.cursor = 'not-allowed';
                cell.style.backgroundColor = '#f0f0f0';
                cell.style.opacity = '1';
                cell.onclick = null;
                cell.title = holidayName || 'Workpoint holiday';
                return; // Skip further processing for this cell
            }

            // Show as non-working (gray background)
            cell.classList.add('non-working');
            cell.style.cursor = 'default';
            cell.style.backgroundColor = '#f8f9fa';
            cell.style.opacity = '1';
            cell.onclick = null;
        }
    });
}

// Function to update past time status for all cells
function updatePastTimeStatus() {
    const now = new Date();
    const currentDate = now.toLocaleDateString('en-CA', { timeZone: organizationTimezone }); // YYYY-MM-DD format
    const currentTime = now.toLocaleTimeString('en-US', { 
        timeZone: organizationTimezone,
        hour12: false,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    
    document.querySelectorAll('.day-cell').forEach(cell => {
        const dayDate = cell.getAttribute('data-day');
        const timeSlot = cell.getAttribute('data-time');
        const isWorking = cell.getAttribute('data-is-working') === 'true';
        
        if (dayDate && timeSlot && isWorking) {
            // Compare date and time
            let isPast = false;
            if (dayDate < currentDate) {
                isPast = true; // Past date
            } else if (dayDate === currentDate) {
                isPast = timeSlot < currentTime.substring(0, 5); // Same date, compare time
            }
            // If dayDate > currentDate, isPast remains false (future date)
            
            // Update data attribute
            cell.setAttribute('data-is-past', isPast ? 'true' : 'false');
            
            // Update click functionality only for working hours cells
            if (isPast) {
                cell.style.cursor = 'default';
                cell.onclick = null;
                cell.style.opacity = '0.7';
            } else {
                cell.style.cursor = 'pointer';
                cell.style.opacity = '1';
                cell.onclick = function() {
                    const workpointId = cell.getAttribute('data-workpoint-id');
                    const workpointName = cell.getAttribute('data-workpoint-name');
                    <?php if ($supervisor_mode): ?>
                    openAddBookingModalWithWorkpoint(dayDate, timeSlot, currentSpecialistId, workpointId, workpointName);
                    <?php else: ?>
                    openAddBookingModalWithWorkpoint(dayDate, timeSlot, '<?= $specialist_id ?>', workpointId, workpointName);
                    <?php endif; ?>
                };
            }
        }
    });
}

// Initialize specialist switching
document.addEventListener('DOMContentLoaded', function() {
    if (specialistsData.length > 0) {
        // Get selected specialist from URL parameter or PHP variable or default to first
        const urlParams = new URLSearchParams(window.location.search);
        const selectedSpecialistId = urlParams.get('selected_specialist') || '<?= $selected_specialist_id ?>' || (specialistsData[0] ? specialistsData[0].unic_id : null);
        
        // Find the selected specialist
        const selectedSpecialist = specialistsData.find(s => s.unic_id == selectedSpecialistId) || specialistsData[0];
        
        const weekInfoRow = document.getElementById('week-info-row');
        if (weekInfoRow) {
            weekInfoRow.style.background = `linear-gradient(135deg, ${selectedSpecialist.back_color} 0%, ${selectedSpecialist.back_color}dd 100%)`;
        }
        
        // All th in thead
        const thElements = document.querySelectorAll('.weekly-grid thead th');
        
        thElements.forEach((th, index) => {
            th.style.setProperty('background', selectedSpecialist.back_color, 'important');
            th.style.setProperty('color', selectedSpecialist.foreground_color, 'important');
        });
        
        // Update specialist name in header
        const specialistNameSpan = document.getElementById('selected-specialist-name');
        if (specialistNameSpan) {
            specialistNameSpan.textContent = selectedSpecialist.name;
        }
        
        // Booking boxes already have data-specialist-id set in PHP
        document.querySelectorAll('.booking-box').forEach(box => {
            const specialistId = box.getAttribute('data-specialist-id');
        });
        
        // Set current specialist ID to the selected one
        currentSpecialistId = selectedSpecialistId;
        
        // Show only selected specialist's bookings initially
        filterBookingsBySpecialist(currentSpecialistId);
        
        // Set working hours for selected specialist
        updateWorkingHoursForSpecialist(currentSpecialistId);
        
        // Update past time status every minute
        setInterval(updatePastTimeStatus, 60000);
    }
});
<?php endif; ?>

<?php if (!$supervisor_mode): ?>
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
    } else {
        console.error('timeslotWorkpointId field not found in modal');
    }
    
    // Call the original booking modal function
    openAddBookingModal(date, time, specialistId);
}

// Specialist mode functions
function updatePastTimeStatusForSpecialist() {
    const now = new Date();
    const currentDate = now.toLocaleDateString('en-CA', { timeZone: organizationTimezone }); // YYYY-MM-DD format
    const currentTime = now.toLocaleTimeString('en-US', { 
        timeZone: organizationTimezone,
        hour12: false,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    
    document.querySelectorAll('.day-cell').forEach(cell => {
        const dayDate = cell.getAttribute('data-day');
        const timeSlot = cell.getAttribute('data-time');
        const isWorking = cell.getAttribute('data-is-working') === 'true';
        
        if (dayDate && timeSlot && isWorking) {
            // Compare date and time
            let isPast = false;
            if (dayDate < currentDate) {
                isPast = true; // Past date
            } else if (dayDate === currentDate) {
                isPast = timeSlot < currentTime.substring(0, 5); // Same date, compare time
            }
            // If dayDate > currentDate, isPast remains false (future date)
            
            // Update data attribute
            cell.setAttribute('data-is-past', isPast ? 'true' : 'false');
            
            // Update click functionality only for working hours cells
            if (isPast) {
                cell.style.cursor = 'default';
                cell.onclick = null;
                cell.style.opacity = '0.7';
            } else {
                cell.style.cursor = 'pointer';
                cell.style.opacity = '1';
                cell.onclick = function() {
                    const workpointId = cell.getAttribute('data-workpoint-id');
                    const workpointName = cell.getAttribute('data-workpoint-name');
                    openAddBookingModalWithWorkpoint(dayDate, timeSlot, '<?= $specialist_id ?>', workpointId, workpointName);
                };
            }
        }
    });
}

// Initialize specialist mode
document.addEventListener('DOMContentLoaded', function() {
    // Update past time status initially
    updatePastTimeStatusForSpecialist();
    
    // Update past time status every minute
    setInterval(updatePastTimeStatusForSpecialist, 60000);
});
<?php endif; ?>

// Synchronize scrolling between specialist tabs and table for supervisor mode
<?php if ($supervisor_mode): ?>
document.addEventListener('DOMContentLoaded', function() {
    const tabsContainer = document.querySelector('.specialist-tabs-container');
    const gridContainer = document.querySelector('.weekly-grid-container');
    const specialistTabs = document.querySelector('.specialist-tabs');
    const weeklyGrid = document.querySelector('.weekly-grid');
    
    let isScrolling = false;
    
    // Sync table scroll to tabs
    gridContainer.addEventListener('scroll', function() {
        if (!isScrolling) {
            isScrolling = true;
            const scrollRatio = this.scrollLeft / (this.scrollWidth - this.clientWidth);
            const tabsMaxScroll = tabsContainer.scrollWidth - tabsContainer.clientWidth;
            tabsContainer.scrollLeft = scrollRatio * tabsMaxScroll;
            setTimeout(() => { isScrolling = false; }, 50);
        }
    });
    
    // Sync tabs scroll to table
    tabsContainer.addEventListener('scroll', function() {
        if (!isScrolling) {
            isScrolling = true;
            const scrollRatio = this.scrollLeft / (this.scrollWidth - this.clientWidth);
            const gridMaxScroll = gridContainer.scrollWidth - gridContainer.clientWidth;
            gridContainer.scrollLeft = scrollRatio * gridMaxScroll;
            setTimeout(() => { isScrolling = false; }, 50);
        }
    });
    
    // Ensure tabs are 98% of table width
    function alignTabsWithTable() {
        if (weeklyGrid && specialistTabs) {
            const tableWidth = weeklyGrid.offsetWidth;
            const tabsWidth = Math.floor(tableWidth * 0.98);
            specialistTabs.style.width = tabsWidth + 'px';
            specialistTabs.style.minWidth = tabsWidth + 'px';
            specialistTabs.style.maxWidth = tabsWidth + 'px';
            
            // Center the tabs by adjusting container
            const widthDiff = tableWidth - tabsWidth;
            tabsContainer.style.paddingLeft = (widthDiff / 2) + 'px';
            tabsContainer.style.paddingRight = (widthDiff / 2) + 'px';
            
            // Check if scrolling is needed
            checkScrollbarVisibility();
        }
    }
    
    // Check if tabs overflow and show/hide scrollbar
    function checkScrollbarVisibility() {
        if (tabsContainer && specialistTabs) {
            const containerWidth = tabsContainer.clientWidth;
            const contentWidth = specialistTabs.scrollWidth;
            
            if (contentWidth > containerWidth) {
                tabsContainer.classList.add('scrollable');
            } else {
                tabsContainer.classList.remove('scrollable');
            }
        }
    }
    
    // Initial alignment
    alignTabsWithTable();
    
    // Re-align on window resize
    window.addEventListener('resize', () => {
        alignTabsWithTable();
        checkScrollbarVisibility();
    });
    
    // Re-align after table is positioned
    setTimeout(() => {
        alignTabsWithTable();
        checkScrollbarVisibility();
    }, 100);
    
    // Check scrollbar visibility on specialist switch
    window.switchSpecialistOriginal = window.switchSpecialist;
    window.switchSpecialist = function(specialistId) {
        if (window.switchSpecialistOriginal) {
            window.switchSpecialistOriginal(specialistId);
        }
        setTimeout(checkScrollbarVisibility, 100);
    };
});
<?php endif; ?>
</script> 