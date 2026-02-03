<?php
/**
 * Monthly Calendar Template for Supervisor Mode
 * Shows all specialists in one monthly view with bookings organized by specialist
 */

require_once 'includes/calendar_functions.php';

// Check if this is supervisor mode
// Supervisor mode should already be set by the parent file
// If not set, check the GET parameter (fallback)
if (!isset($supervisor_mode)) {
    $supervisor_mode = isset($_GET['supervisor_mode']) && $_GET['supervisor_mode'] === 'true';
}

if (!$supervisor_mode) {
    // If not supervisor mode, redirect to regular monthly view
    include 'calendar_monthly.php';
    return;
}

// Get month data
$start_date_obj = new DateTime($start_date);
$end_date_obj = new DateTime($end_date);
$current_month = clone $start_date_obj;

$months = [];
while ($current_month <= $end_date_obj) {
    $year = $current_month->format('Y');
    $month = $current_month->format('m');
    $months[] = [
        'year' => $year,
        'month' => $month,
        'calendar_data' => getMonthCalendarData($year, $month)
    ];
    $current_month->add(new DateInterval('P1M'));
}

// Get all specialists for this workpoint
$specialists = [];
if (isset($workpoint) && isset($workpoint['unic_id'])) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.* 
        FROM specialists s 
        INNER JOIN working_program wp ON s.unic_id = wp.specialist_id 
        WHERE wp.working_place_id = ? 
        ORDER BY s.name
    ");
    $stmt->execute([$workpoint['unic_id']]);
    $specialists = $stmt->fetchAll();
}

// Group bookings by specialist and date
$specialist_bookings = [];
foreach ($specialists as $spec) {
    $specialist_bookings[$spec['unic_id']] = [];
}

foreach ($bookings as $booking) {
    $booking_date = date('Y-m-d', strtotime($booking['booking_start_datetime']));
    $specialist_id = $booking['id_specialist'];
    
    if (isset($specialist_bookings[$specialist_id])) {
        if (!isset($specialist_bookings[$specialist_id][$booking_date])) {
            $specialist_bookings[$specialist_id][$booking_date] = [];
        }
        $specialist_bookings[$specialist_id][$booking_date][] = $booking;
    }
}

// Sort bookings by time for each specialist and date
foreach ($specialist_bookings as $spec_id => $dates) {
    foreach ($dates as $date => $bookings_for_date) {
        $specialist_bookings[$spec_id][$date] = sortBookingsByTime($bookings_for_date);
    }
}

// Function to calculate specialist's working hours for a specific date
function getSpecialistWorkingHoursForDate($pdo, $specialist_id, $workpoint_id, $date) {
    $day_of_week = strtolower(date('l', strtotime($date)));
    
    $stmt = $pdo->prepare("
        SELECT * FROM working_program 
        WHERE specialist_id = ? AND working_place_id = ? AND day_of_week = ?
    ");
    $stmt->execute([$specialist_id, $workpoint_id, $day_of_week]);
    $program = $stmt->fetch();
    
    if (!$program) {
        return 0; // No working hours
    }
    
    $total_hours = 0;
    for ($i = 1; $i <= 3; $i++) {
        $start = $program["shift{$i}_start"];
        $end = $program["shift{$i}_end"];
        
        if ($start && $end && $start !== '00:00:00' && $end !== '00:00:00') {
            $start_time = new DateTime($start);
            $end_time = new DateTime($end);
            $interval = $start_time->diff($end_time);
            $total_hours += $interval->h + ($interval->i / 60);
        }
    }
    
    return $total_hours;
}

// Function to calculate booking duration in hours
function getBookingDurationHours($booking) {
    $start = new DateTime($booking['booking_start_datetime']);
    $end = new DateTime($booking['booking_end_datetime']);
    $interval = $start->diff($end);
    return $interval->h + ($interval->i / 60);
}

// Function to calculate specialist's booking load percentage
function calculateSpecialistLoadPercentage($pdo, $specialist_id, $workpoint_id, $date, $bookings) {
    $working_hours = getSpecialistWorkingHoursForDate($pdo, $specialist_id, $workpoint_id, $date);
    
    if ($working_hours == 0) {
        return 0; // No working hours = 0% load
    }
    
    $booking_hours = 0;
    foreach ($bookings as $booking) {
        $booking_hours += getBookingDurationHours($booking);
    }
    
    $percentage = ($booking_hours / $working_hours) * 100;
    return round($percentage, 0); // Round to 0 decimals
}
?>

<style>
.supervisor-monthly-calendar {
    max-width: 1200px;
    margin: 0 auto;
}

.supervisor-month-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    overflow: hidden;
    max-width: 90%;
    margin-left: auto;
    margin-right: auto;
}

.supervisor-month-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    padding: 16px;
    text-align: center;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.supervisor-month-title {
    font-size: 1.4rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.supervisor-month-title i {
    font-size: 1.2rem;
}

.supervisor-month-nav-buttons {
    display: flex;
    gap: 10px;
}

.supervisor-month-nav-btn {
    background: rgba(255,255,255,0.2);
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    font-size: 0.8rem;
}

.supervisor-month-nav-btn:hover {
    background: rgba(255,255,255,0.3);
}

.supervisor-monthly-grid {
    width: 100%;
    border-collapse: collapse;
}

.supervisor-monthly-grid {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.supervisor-monthly-grid th,
.supervisor-monthly-grid td {
    border: 1px solid #d1d5db;
    padding: 6px;
    vertical-align: top;
    width: calc(100% / 7);
    box-sizing: border-box;
}

.supervisor-monthly-grid th {
    background: #f8f9fa;
    color: var(--dark-color);
    font-weight: 600;
    text-align: center;
    padding: 12px 8px;
    font-size: 0.8rem;
    height: auto;
    min-height: auto;
    max-height: none;
}

.supervisor-monthly-grid td {
    height: calc((100vw - 40px) / 7 * 0.5904);
    max-height: 83px;
    min-height: 70px;
}

.supervisor-day-cell {
    position: relative;
    min-height: 80px;
    background: white;
    transition: background-color 0.2s ease;
    cursor: pointer;
}

.supervisor-day-cell:hover {
    background: rgba(102, 126, 234, 0.05);
}

.supervisor-day-cell.other-month {
    background: #f8f9fa;
    color: #6c757d;
}

.supervisor-day-cell.other-month:hover {
    background: #f8f9fa;
    cursor: default;
}

.supervisor-day-cell.today {
    background: rgba(224, 255, 255, 0.4);
    border: 2px solid var(--primary-color);
}

.supervisor-day-number {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 6px;
    color: var(--primary-color);
    text-align: center;
    padding-bottom: 6px;
    border-bottom: 1px solid #e9ecef;
    width: fit-content;
    margin-left: auto;
    margin-right: auto;
}

.supervisor-day-cell.other-month .supervisor-day-number {
    color: #6c757d;
}

.supervisor-day-cell.today .supervisor-day-number {
    color: var(--primary-color);
    font-weight: 700;
    background: none;
    border: none;
    border-radius: 0;
    width: auto;
    height: auto;
    display: block;
    align-items: normal;
    justify-content: normal;
    margin: 0 0 6px 0;
}

/* Weekend styling */
.supervisor-day-cell:nth-child(6) .supervisor-day-number,
.supervisor-day-cell:nth-child(7) .supervisor-day-number {
    color: #dc3545;
}

.supervisor-specialist-bookings {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-top: 8px;
}

.supervisor-specialist-section {
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 4px;
    background: #f8f9fa;
    margin-bottom: 2px;
}

.supervisor-specialist-header {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 2px;
    text-align: center;
    background: white;
    padding: 2px 4px;
    border-radius: 2px;
}

.supervisor-booking-item {
    background: var(--primary-color);
    color: white;
    padding: 2px 4px;
    border-radius: 2px;
    margin-bottom: 1px;
    font-size: 0.6rem;
    cursor: pointer;
    transition: all 0.3s ease;
    line-height: 1.2;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.supervisor-booking-item:hover {
    transform: scale(1.02);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.supervisor-booking-item.booking-past {
    background: #f8f9fa;
    color: #adb5bd;
    border: 1px solid #dee2e6;
    opacity: 0.4;
}

.supervisor-booking-item.booking-today {
    background: var(--success-color);
}

.supervisor-booking-item.booking-future {
    background: var(--primary-color);
}

.supervisor-booking-time {
    font-weight: 600;
    font-size: 0.6rem;
    display: inline-block;
    margin-right: 3px;
}

.supervisor-booking-client {
    font-weight: 600;
    font-size: 0.6rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: inline-block;
    max-width: calc(100% - 28px);
}

.supervisor-empty-day {
    color: #adb5bd;
    font-style: italic;
    font-size: 0.7rem;
    text-align: center;
    padding: 16px 8px;
}

.supervisor-booking-count {
    position: absolute;
    top: 4px;
    right: 4px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.6rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 5;
}

.supervisor-booking-count.booking-past {
    background: #adb5bd;
    opacity: 0.4;
}

.supervisor-booking-count.booking-today {
    background: var(--success-color);
}

.supervisor-booking-count.booking-future {
    background: var(--primary-color);
}

/* Specialist Statistics Circles */
.supervisor-specialist-stats {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 8px;
    justify-content: center;
    align-items: center;
}

.supervisor-specialist-stat-circle {
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    margin: 0;
    flex-shrink: 0;
}

.supervisor-specialist-stat-circle.booking-past {
    opacity: 0.4;
}

/* Global rule to ensure ALL dropdowns are always fully opaque */
.supervisor-specialist-dropdown {
    opacity: 1 !important;
    filter: none !important;
}

.supervisor-specialist-dropdown * {
    opacity: 1 !important;
    filter: none !important;
}

.supervisor-specialist-stat-circle:hover {
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    z-index: 10;
}

.supervisor-specialist-stat-circle .booking-count {
    font-size: 0.6rem;
    font-weight: 700;
    line-height: 1;
}

.supervisor-specialist-stat-circle .load-percentage {
    font-size: 0.5rem;
    font-weight: 600;
    line-height: 1;
    margin-top: 1px;
}

.supervisor-specialist-stat-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    line-height: 1;
}

/* Specialist Hover Dropdown - Now positioned outside the circle */
.supervisor-specialist-dropdown-outer {
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 99999;
    display: none;
    margin-top: 4px;
}

.supervisor-specialist-dropdown {
    background: white !important;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: 200px;
    max-width: 250px;
    padding: 8px;
    opacity: 1 !important;
    transform: translateZ(0);
    will-change: opacity;
    isolation: isolate;
    filter: none !important;
}

/* Adjust dropdown position for circles near left edge */
.supervisor-specialist-stat-circle:nth-child(1) + .supervisor-specialist-dropdown-outer .supervisor-specialist-dropdown,
.supervisor-specialist-stat-circle:nth-child(2) + .supervisor-specialist-dropdown-outer .supervisor-specialist-dropdown {
    left: 0;
    transform: none;
}

/* Adjust dropdown position for circles near right edge */
.supervisor-specialist-stat-circle:nth-last-child(1) + .supervisor-specialist-dropdown-outer .supervisor-specialist-dropdown,
.supervisor-specialist-stat-circle:nth-last-child(2) + .supervisor-specialist-dropdown-outer .supervisor-specialist-dropdown {
    left: auto;
    right: 0;
    transform: none;
}

.supervisor-specialist-stat-circle:hover + .supervisor-specialist-dropdown-outer {
    display: block;
}

.supervisor-dropdown-header {
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--primary-color) !important;
    margin-bottom: 6px;
    text-align: center;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 4px;
    opacity: 1 !important;
    background: white !important;
    transform: translateZ(0);
}

.supervisor-dropdown-booking {
    background: #f8f9fa !important;
    padding: 4px 6px;
    border-radius: 3px;
    margin-bottom: 3px;
    font-size: 0.7rem;
    line-height: 1.2;
    opacity: 1 !important;
    transform: translateZ(0);
}

.supervisor-dropdown-booking:last-child {
    margin-bottom: 0;
}

.supervisor-dropdown-time {
    font-weight: 600;
    color: var(--primary-color);
    margin-right: 4px;
}

.supervisor-dropdown-client {
    color: var(--dark-color);
}

.supervisor-dropdown-empty {
    text-align: center;
    color: #6c757d !important;
    font-style: italic;
    font-size: 0.7rem;
    padding: 8px;
    opacity: 1 !important;
    background: white !important;
    transform: translateZ(0);
}

@media (max-width: 768px) {
    .supervisor-monthly-grid th,
    .supervisor-monthly-grid td {
        padding: 3px;
        width: calc(100% / 7);
        box-sizing: border-box;
    }
    
    .supervisor-monthly-grid th {
        height: auto;
        min-height: auto;
        max-height: none;
    }
    
    .supervisor-monthly-grid td {
        height: calc((100vw - 20px) / 7 * 0.656);
        max-height: 66px;
        min-height: 53px;
    }
    
    .supervisor-day-number {
        font-size: 0.8rem;
    }
    
    .supervisor-specialist-stats {
        gap: 2px;
        margin-top: 4px;
    }
    
    .supervisor-specialist-stat-circle {
        width: 28px;
        height: 28px;
        font-size: 0.6rem;
    }
    
    .supervisor-specialist-stat-circle .booking-count {
        font-size: 0.5rem;
    }
    
    .supervisor-specialist-stat-circle .load-percentage {
        font-size: 0.4rem;
    }
}
</style>

<div class="supervisor-monthly-calendar">

    <!-- Specialist Color Legend - Individual Entity Above Calendar -->
    <div class="specialist-legend" style="margin: 8px 0 10px 0; padding: 10px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef; text-align: center;">
        <div class="legend-items" style="display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; align-items: center;">
            <?php foreach ($specialists as $spec): ?>
                <?php 
                // Get specialist colors from settings
                $stmt = $pdo->prepare("SELECT back_color, foreground_color FROM specialists_setting_and_attr WHERE specialist_id = ?");
                $stmt->execute([$spec['unic_id']]);
                $spec_settings = $stmt->fetch();
                
                $bg_color = $spec_settings['back_color'] ?? '#667eea';
                $fg_color = $spec_settings['foreground_color'] ?? '#ffffff';
                ?>
                <div class="legend-item" style="display: flex; align-items: center; gap: 8px; font-size: 0.8rem;">
                    <div class="legend-color" style="width: 16px; height: 16px; border-radius: 50%; background-color: <?= $bg_color ?>; border: 1px solid #ddd;"></div>
                    <span style="color: #333;"><?= htmlspecialchars($spec['name']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php foreach ($months as $month_data): ?>
        <div class="supervisor-month-container">
            <div class="supervisor-month-header">
                <div style="min-width: 120px;"></div>
                <h2 class="supervisor-month-title">
                    <i class="fas fa-calendar"></i>
                    <?= date('F', mktime(0, 0, 0, $month_data['month'], 1, $month_data['year'])) ?> <?= $month_data['year'] ?>
                    <span style="font-size: 0.9rem; opacity: 0.9; margin-left: 10px;">
                        (<?= count($specialists) ?> specialists)
                    </span>
                </h2>
                <div class="supervisor-month-nav-buttons" style="margin-right: 20px;">
                    <button class="supervisor-month-nav-btn" onclick="navigateMonth('prev')" 
                            title="Navigate to previous month" 
                            onmouseover="showTargetDates('prev')" 
                            onmouseout="hideTargetDates()">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <button class="supervisor-month-nav-btn" onclick="navigateMonth('next')" 
                            title="Navigate to next month" 
                            onmouseover="showTargetDates('next')" 
                            onmouseout="hideTargetDates()">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <table class="supervisor-monthly-grid">
                <thead>
                    <tr>
                        <th>Monday</th>
                        <th>Tuesday</th>
                        <th>Wednesday</th>
                        <th>Thursday</th>
                        <th>Friday</th>
                        <th>Saturday</th>
                        <th>Sunday</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($month_data['calendar_data'] as $week): ?>
                        <tr>
                            <?php foreach ($week as $day): ?>
                                <?php 
                                $is_today = $day['is_today'];
                                $day_specialist_stats = [];
                                
                                // Calculate statistics for each specialist for this day
                                foreach ($specialists as $spec) {
                                    $spec_bookings = $specialist_bookings[$spec['unic_id']][$day['date']] ?? [];
                                    $booking_count = count($spec_bookings);
                                    $load_percentage = calculateSpecialistLoadPercentage($pdo, $spec['unic_id'], $workpoint['unic_id'], $day['date'], $spec_bookings);
                                    
                                    if ($booking_count > 0 || $load_percentage > 0) {
                                        $day_specialist_stats[$spec['unic_id']] = [
                                            'specialist' => $spec,
                                            'bookings' => $spec_bookings,
                                            'booking_count' => $booking_count,
                                            'load_percentage' => $load_percentage
                                        ];
                                    }
                                }
                                ?>
                                
                                <td class="supervisor-day-cell <?= !$day['is_current_month'] ? 'other-month' : '' ?> <?= $is_today ? 'today' : '' ?>"
                                    <?php if ($day['is_current_month']): ?>
                                        onclick="goToDayView('<?= $day['date'] ?>')"
                                    <?php endif; ?>
                                >
                                    <div class="supervisor-day-number"><?= $day['day'] ?></div>
                                    
                                    <?php if (!empty($day_specialist_stats)): ?>
                                        <div class="supervisor-specialist-stats">
                                            <?php foreach ($day_specialist_stats as $spec_id => $spec_data): ?>
                                                <?php 
                                                $spec = $spec_data['specialist'];
                                                $spec_bookings = $spec_data['bookings'];
                                                $booking_count = $spec_data['booking_count'];
                                                $load_percentage = $spec_data['load_percentage'];
                                                
                                                // Get specialist colors from settings
                                                $stmt = $pdo->prepare("SELECT back_color, foreground_color FROM specialists_setting_and_attr WHERE specialist_id = ?");
                                                $stmt->execute([$spec_id]);
                                                $spec_settings = $stmt->fetch();
                                                
                                                $bg_color = $spec_settings['back_color'] ?? '#667eea';
                                                $fg_color = $spec_settings['foreground_color'] ?? '#ffffff';
                                                ?>
                                                
                                                <?php 
                                                // Check if any bookings are in the past
                                                $has_past_bookings = false;
                                                foreach ($spec_bookings as $booking) {
                                                    if (isPastBooking($booking)) {
                                                        $has_past_bookings = true;
                                                        break;
                                                    }
                                                }
                                                $past_class = $has_past_bookings ? ' booking-past' : '';
                                                ?>
                                                <div class="supervisor-specialist-stat-circle<?= $past_class ?>" 
                                                     style="background-color: <?= $bg_color ?>; color: <?= $fg_color ?>;"
                                                     data-specialist-id="<?= $spec_id ?>"
                                                     data-past-bookings="<?= $has_past_bookings ? 'true' : 'false' ?>">
                                                    <div class="supervisor-specialist-stat-content">
                                                        <div class="booking-count"><?= $booking_count ?></div>
                                                        <div class="load-percentage"><?= $load_percentage ?>%</div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Dropdown positioned outside the circle to avoid opacity inheritance -->
                                                <div class="supervisor-specialist-dropdown-outer" 
                                                     data-specialist-id="<?= $spec_id ?>"
                                                     data-past-bookings="<?= $has_past_bookings ? 'true' : 'false' ?>">
                                                    <div class="supervisor-specialist-dropdown">
                                                        <div class="supervisor-dropdown-header">
                                                            <?= htmlspecialchars($spec['name']) ?>
                                                        </div>
                                                        
                                                        <?php if (!empty($spec_bookings)): ?>
                                                            <?php foreach ($spec_bookings as $booking): ?>
                                                                <?php 
                                                                $start_time = formatTime($booking['booking_start_datetime']);
                                                                $end_time = formatTime($booking['booking_end_datetime']);
                                                                ?>
                                                                <div class="supervisor-dropdown-booking">
                                                                    <span class="supervisor-dropdown-time"><?= $start_time ?></span>
                                                                    <span class="supervisor-dropdown-client"><?= htmlspecialchars($booking['client_full_name']) ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <div class="supervisor-dropdown-empty">
                                                                No bookings
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
</div>

<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Get workpoint country for timezone handling
const workpointCountry = '<?= isset($workpoint['country']) ? $workpoint['country'] : 'GB' ?>';

// Month navigation
function navigateMonth(direction) {
    // Get the current month being displayed from the URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const currentStartDate = urlParams.get('start_date') || '<?= $start_date ?>';
    const currentEndDate = urlParams.get('end_date') || '<?= $end_date ?>';
    
    console.log('Debug navigateMonth:', {
        direction: direction,
        currentStartDate: currentStartDate,
        currentEndDate: currentEndDate,
        urlParams: Object.fromEntries(urlParams.entries())
    });
    
    // Determine the actual month being displayed by using the end_date
    const currentDate = new Date(currentEndDate);
    const currentYear = currentDate.getFullYear();
    const currentMonth = currentDate.getMonth(); // 0-based (0-11)
    
    console.log('Current date info:', {
        currentDate: currentDate,
        currentYear: currentYear,
        currentMonth: currentMonth
    });
    
    // Calculate the target month
    const monthsToAdd = direction === 'next' ? 1 : -1;
    let targetYear = currentYear;
    let targetMonth = currentMonth + monthsToAdd;
    
    // Handle year rollover
    if (targetMonth > 11) {
        targetMonth = 0;
        targetYear = currentYear + 1;
    } else if (targetMonth < 0) {
        targetMonth = 11;
        targetYear = currentYear - 1;
    }
    
    console.log('Target month info:', {
        monthsToAdd: monthsToAdd,
        targetYear: targetYear,
        targetMonth: targetMonth
    });
    
    // Build the start date (always 01 of the target month)
    const startDate = new Date(targetYear, targetMonth, 1);
    // Format date manually to avoid timezone issues
    const newStartDate = targetYear + '-' + 
                        String(targetMonth + 1).padStart(2, '0') + '-01';
    
    // Build the end date (last day of the target month)
    const endDate = new Date(targetYear, targetMonth + 1, 0);
    const lastDay = endDate.getDate();
    const newEndDate = targetYear + '-' + 
                      String(targetMonth + 1).padStart(2, '0') + '-' + 
                      String(lastDay).padStart(2, '0');
    
    console.log('Final dates:', {
        startDate: startDate,
        newStartDate: newStartDate,
        endDate: endDate,
        newEndDate: newEndDate
    });
    
    const url = new URL(window.location);
    url.searchParams.set('start_date', newStartDate);
    url.searchParams.set('end_date', newEndDate);
    url.searchParams.set('period', 'custom');
    
    // Preserve supervisor mode parameters
    if (urlParams.get('supervisor_mode')) {
        url.searchParams.set('supervisor_mode', urlParams.get('supervisor_mode'));
    }
    if (urlParams.get('working_point_user_id')) {
        url.searchParams.set('working_point_user_id', urlParams.get('working_point_user_id'));
    }
    
    console.log('Final URL:', url.toString());
    
    window.location.href = url.toString();
}

// Show target dates on hover
function showTargetDates(direction) {
    // Get the current month being displayed from the URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const currentStartDate = urlParams.get('start_date') || '<?= $start_date ?>';
    const currentEndDate = urlParams.get('end_date') || '<?= $end_date ?>';
    
    // Use the end_date to determine the current month being displayed
    const currentDate = new Date(currentEndDate);
    const currentYear = currentDate.getFullYear();
    const currentMonth = currentDate.getMonth(); // 0-based (0-11)
    
    // Calculate the target month
    const monthsToAdd = direction === 'next' ? 1 : -1;
    let targetYear = currentYear;
    let targetMonth = currentMonth + monthsToAdd;
    
    // Handle year rollover
    if (targetMonth > 11) {
        targetMonth = 0;
        targetYear = currentYear + 1;
    } else if (targetMonth < 0) {
        targetMonth = 11;
        targetYear = currentYear - 1;
    }
    
    // Build the start date (always 01 of the target month)
    const startDate = new Date(targetYear, targetMonth, 1);
    // Format date manually to avoid timezone issues
    const newStartDate = targetYear + '-' + 
                        String(targetMonth + 1).padStart(2, '0') + '-01';
    
    // Build the end date (last day of the target month)
    const endDate = new Date(targetYear, targetMonth + 1, 0);
    const lastDay = endDate.getDate();
    const newEndDate = targetYear + '-' + 
                      String(targetMonth + 1).padStart(2, '0') + '-' + 
                      String(lastDay).padStart(2, '0');
    
    // Format the dates for display
    const startFormatted = new Date(newStartDate).toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    const endFormatted = new Date(newEndDate).toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    // Create or update tooltip
    let tooltip = document.getElementById('month-nav-tooltip');
    if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.id = 'month-nav-tooltip';
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

// Hide target dates
function hideTargetDates() {
    const tooltip = document.getElementById('month-nav-tooltip');
    if (tooltip) {
        tooltip.style.display = 'none';
    }
}

// Go to day view for a specific date
function goToDayView(date) {
    const url = new URL(window.location);
    url.searchParams.set('start_date', date);
    url.searchParams.set('end_date', date);
    url.searchParams.set('period', 'custom');
    
    // Preserve supervisor mode parameters
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('supervisor_mode')) {
        url.searchParams.set('supervisor_mode', urlParams.get('supervisor_mode'));
    }
    if (urlParams.get('working_point_user_id')) {
        url.searchParams.set('working_point_user_id', urlParams.get('working_point_user_id'));
    }
    
    window.location.href = url.toString();
}

// Smooth scrolling for multiple months
document.querySelectorAll('.supervisor-month-container').forEach((container, index) => {
    if (index > 0) {
        container.style.marginTop = '20px';
    }
});

// Dynamic dropdown positioning
document.addEventListener('DOMContentLoaded', function() {
    const specialistCircles = document.querySelectorAll('.supervisor-specialist-stat-circle');
    
    specialistCircles.forEach(circle => {
        let hideTimeout = null;
        const specialistId = circle.getAttribute('data-specialist-id');
        const dropdownOuter = circle.parentNode.querySelector(`.supervisor-specialist-dropdown-outer[data-specialist-id="${specialistId}"]`);
        if (!dropdownOuter) return;

        function showDropdown() {
            const dropdown = dropdownOuter.querySelector('.supervisor-specialist-dropdown');
            
            // Get the circle's position relative to its parent cell
            const circleRect = circle.getBoundingClientRect();
            const parentCell = circle.closest('.supervisor-day-cell');
            const parentCellRect = parentCell.getBoundingClientRect();
            
            // Calculate position relative to the parent cell
            const circleLeftInCell = circleRect.left - parentCellRect.left;
            const circleTopInCell = circleRect.top - parentCellRect.top;
            
            // Position dropdown centered under the circle
            const left = circleLeftInCell + (circle.offsetWidth / 2) - (dropdown.offsetWidth / 2);
            const top = circleTopInCell + circle.offsetHeight + 4; // 4px gap below circle
            
            dropdownOuter.style.position = 'absolute';
            dropdownOuter.style.left = left + 'px';
            dropdownOuter.style.top = top + 'px';
            dropdownOuter.style.zIndex = '99999';
            dropdownOuter.style.display = 'block';
            
            // Reset dropdown inner positioning
            dropdown.style.left = '0';
            dropdown.style.right = 'auto';
            dropdown.style.transform = 'none';
            
            // Force dropdown to be fully opaque
            dropdown.style.opacity = '1';
            dropdown.style.background = 'white';
            dropdown.style.filter = 'none';
            
            const allElements = dropdown.querySelectorAll('*');
            allElements.forEach(el => {
                el.style.opacity = '1';
                el.style.filter = 'none';
                if (el.classList.contains('supervisor-dropdown-header')) {
                    el.style.background = 'white';
                    el.style.color = 'var(--primary-color)';
                } else if (el.classList.contains('supervisor-dropdown-booking')) {
                    el.style.background = '#f8f9fa';
                    el.style.color = 'var(--dark-color)';
                } else if (el.classList.contains('supervisor-dropdown-empty')) {
                    el.style.background = 'white';
                    el.style.color = '#6c757d';
                }
            });
        }

        function hideDropdown() {
            hideTimeout = setTimeout(() => {
                dropdownOuter.style.display = 'none';
            }, 100);
        }

        function cancelHide() {
            if (hideTimeout) {
                clearTimeout(hideTimeout);
                hideTimeout = null;
            }
        }

        circle.addEventListener('mouseenter', function() {
            cancelHide();
            showDropdown();
        });
        circle.addEventListener('mouseleave', function() {
            hideDropdown();
        });
        dropdownOuter.addEventListener('mouseenter', function() {
            cancelHide();
            showDropdown();
        });
        dropdownOuter.addEventListener('mouseleave', function() {
            hideDropdown();
        });
    });
});
</script> 