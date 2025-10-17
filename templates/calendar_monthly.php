<?php
/**
 * Monthly Calendar Template v2
 * Shows booking counts in day cells with hover dropdown for booking details
 */

require_once 'includes/calendar_functions.php';

// Check if this is supervisor mode
$supervisor_mode = isset($_GET['supervisor_mode']) && $_GET['supervisor_mode'] === 'true';

if ($supervisor_mode) {
    // Supervisor mode - show all specialists in one monthly view
    include 'calendar_monthly_supervisor.php';
    return;
}

// Load time off data for the specialist
$time_off_dates = [];
if (!$supervisor_mode && isset($specialist_id)) {
    $stmt = $pdo->prepare("
        SELECT date_off, start_time, end_time 
        FROM specialist_time_off 
        WHERE specialist_id = ? 
        AND date_off BETWEEN ? AND ?
        ORDER BY date_off
    ");
    $stmt->execute([$specialist_id, $start_date, $end_date]);
    $time_off_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to date-keyed array and calculate if it's a full day off
    foreach ($time_off_raw as $off) {
        $is_full_day_off = false;
        
        if ($off['start_time'] && $off['end_time']) {
            // Calculate duration in hours
            $start = new DateTime($off['start_time']);
            $end = new DateTime($off['end_time']);
            $interval = $start->diff($end);
            $hours = ($interval->h + ($interval->i / 60));
            
            // If duration is greater than 10 hours, consider it a full day off
            if ($hours > 10) {
                $is_full_day_off = true;
            }
        }
        
        $time_off_dates[$off['date_off']] = [
            'start_time' => $off['start_time'],
            'end_time' => $off['end_time'],
            'is_full_day' => $is_full_day_off
        ];
    }
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
?>

<style>

.monthly-calendar-v2 {
    max-width: 960px;
    margin: 0 auto;
}

.month-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    overflow: hidden;
}

.month-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    padding: 16px;
    text-align: center;
    display: flex;
    justify-content: space-between;
    align-items: center;
}



.month-title {
    font-size: 1.4rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.month-title i {
    font-size: 1.2rem;
}

.month-year {
    font-size: 1rem;
    opacity: 0.9;
    margin-top: 4px;
}

.month-nav-buttons {
    display: flex;
    gap: 10px;
}

.month-nav-btn {
    background: rgba(255,255,255,0.2);
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    font-size: 0.8rem;
}

.month-nav-btn:hover {
    background: rgba(255,255,255,0.3);
}

.monthly-grid {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.monthly-grid th,
.monthly-grid td {
    border: 1px solid #e9ecef;
    padding: 6px;
    vertical-align: top;
    width: calc(100% / 7);
    box-sizing: border-box;
}

.monthly-grid th {
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

.monthly-grid td {
    height: calc((100vw - 40px) / 7 * 0.5018);
    max-height: 71px;
    min-height: 60px;
}



.day-cell {
    position: relative;
    min-height: 51px;
    background: white;
    transition: background-color 0.2s ease;
    cursor: pointer;
}

.day-cell:hover {
    background: rgba(102, 126, 234, 0.05);
}

.day-cell.other-month {
    background: #f8f9fa;
    color: #6c757d;
}

.day-cell.other-month:hover {
    background: #f8f9fa;
    cursor: default;
}

.day-cell.today {
    background: rgba(102, 126, 234, 0.1);
    border: 2px solid var(--primary-color);
}

.day-cell.full-day-off {
    background: #e9ecef !important;
    cursor: default !important;
}

.day-cell.full-day-off:hover {
    background: #e9ecef !important;
}

.day-number {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 6px;
    color: var(--primary-color);
    text-align: center;
}

.day-cell.other-month .day-number {
    color: #6c757d;
}

.day-cell.today .day-number {
    color: white;
    font-weight: 700;
    background: var(--primary-color);
    border: 2px solid var(--primary-color);
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 6px auto;
}

/* Weekend styling */
.day-cell:nth-child(6) .day-number,
.day-cell:nth-child(7) .day-number {
    color: #dc3545;
}

.booking-count-container {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin-top: 16%;
}

.booking-count {
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    color: #1e3a8a;
    border-radius: 6px;
    width: 72px;
    height: 28px;
    font-weight: 600;
    font-size: 0.7rem;
    font-style: italic;
    margin: 0;
    transition: all 0.3s ease;
    cursor: pointer;
    padding: 4px;
    gap: 4px;
}

.booking-count:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    background: #1e3a8a;
    color: white;
}

.booking-count.booking-past {
    background: transparent;
    color: #adb5bd;
    font-style: italic;
    font-weight: normal !important;
}

.booking-count.booking-today {
    background: white;
    color: var(--success-color);
}

.booking-count.booking-future {
    background: white;
    color: #1e3a8a;
}

.booking-count.booking-count-today {
    background: #fff9c4 !important; /* Light yellow */
    color: #856404 !important;
}

.day-load-progress {
    width: 72px;
    height: 6px;
    background-color: #e9ecef;
    border-radius: 3px;
    margin-top: 4px;
    overflow: hidden;
    position: relative;
    border: 1px solid #dee2e6;
    box-sizing: border-box;
}

.day-load-progress-fill {
    height: 100%;
    background-color: #1e3a8a;
    border-radius: 2px;
    transition: width 0.3s ease;
}

.day-load-progress-fill.booking-past {
    background-color: #adb5bd;
}

.day-load-progress-fill.booking-today {
    background-color: var(--success-color);
}

.booking-dropdown {
    position: absolute;
    top: calc(100% + 10px);
    left: 16px;
    width: 224px;
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    max-height: 240px;
    overflow-y: auto;
    display: none;
    padding: 8px;
    margin-top: 0;
}

.booking-count-container:hover .booking-dropdown {
    display: block !important;
}

.booking-dropdown-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.booking-dropdown-item {
    background: var(--primary-color);
    color: white;
    padding: 6px 8px;
    border-radius: 3px;
    margin-bottom: 4px;
    font-size: 0.7rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    line-height: 1.2;
}

.booking-dropdown-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.booking-dropdown-item.booking-past {
    background: #f8f9fa;
    color: #adb5bd;
    border: 1px solid #dee2e6;
}

.booking-dropdown-item.booking-today {
    background: var(--success-color);
}

.booking-dropdown-item.booking-future {
    background: var(--primary-color);
}

.booking-dropdown-time {
    font-weight: 600;
    font-size: 0.6rem;
    display: inline-block;
    margin-right: 3px;
}

.booking-dropdown-client {
    font-weight: 600;
    font-size: 0.7rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: inline-block;
    max-width: calc(100% - 28px);
}

.booking-dropdown-service {
    font-size: 0.6rem;
    opacity: 0.9;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    margin-top: 1px;
}

.empty-day {
    color: #adb5bd;
    font-style: italic;
    font-size: 0.7rem;
    text-align: center;
    padding: 16px 8px;
}



@media (max-width: 768px) {
    .monthly-grid td {
        height: calc((100vw - 20px) / 7 * 0.5018);
        max-height: 51px;
        min-height: 41px;
        padding: 3px;
    }
    
    .monthly-grid th {
        padding: 3px;
        font-size: 0.7rem;
    }
    
    .day-number {
        font-size: 0.8rem;
    }
    
    .booking-count {
        width: 52px;
        height: 24px;
        font-size: 0.65rem;
    }
    
    .day-load-progress {
        width: 52px;
        height: 3px;
    }
    
    .booking-dropdown {
        max-height: 160px;
    }
}
</style>

<div class="monthly-calendar-v2">

    <?php foreach ($months as $month_data): ?>
        <div class="month-container">
            <div class="month-header">
                <div style="min-width: 120px;"></div>
                <h2 class="month-title">
                    <i class="fas fa-calendar"></i>
                    <?= date('F', mktime(0, 0, 0, $month_data['month'], 1, $month_data['year'])) ?> <?= $month_data['year'] ?>
                </h2>
                <div class="month-nav-buttons" style="margin-right: 20px;">
                    <button class="month-nav-btn" onclick="navigateMonth('prev')" 
                            title="Navigate to previous month" 
                            onmouseover="showTargetDates('prev')" 
                            onmouseout="hideTargetDates()">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <button class="month-nav-btn" onclick="navigateMonth('next')" 
                            title="Navigate to next month" 
                            onmouseover="showTargetDates('next')" 
                            onmouseout="hideTargetDates()">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <table class="monthly-grid">
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
                                $day_bookings = getBookingsForDate($bookings, $day['date']);
                                $day_bookings = sortBookingsByTime($day_bookings);
                                $is_today = $day['is_today'];
                                $booking_count = count($day_bookings);
                                
                                // Check if the day is in the past
                                $day_date = new DateTime($day['date']);
                                $today_date = new DateTime();
                                $today_date->setTime(0, 0, 0); // Reset time to start of day
                                $is_past = $day_date < $today_date;
                                
                                // Check if this is a full day off for the specialist
                                $is_full_day_off = false;
                                if (isset($time_off_dates[$day['date']]) && $time_off_dates[$day['date']]['is_full_day']) {
                                    $is_full_day_off = true;
                                }
                                ?>
                                
                                <td class="day-cell <?= !$day['is_current_month'] ? 'other-month' : '' ?> <?= $is_today ? 'today' : '' ?> <?= $is_full_day_off ? 'full-day-off' : '' ?>"
                                    <?php if ($day['is_current_month'] && !$is_full_day_off): ?>
                                        onclick="goToDayView('<?= $day['date'] ?>')"
                                    <?php endif; ?>
                                    <?php if ($is_full_day_off): ?>
                                        title="Specialist day off"
                                    <?php endif; ?>
                                >
                                    <div class="day-number"><?= $day['day'] ?></div>
                                    
                                    <?php if ($is_full_day_off): ?>
                                        <!-- Full day off - no bookings shown -->
                                        <div style="text-align: center; margin-top: 10px;">
                                            <i class="fas fa-calendar-times" style="color: #6c757d; font-size: 1.2rem;"></i>
                                        </div>
                                    <?php elseif ($booking_count > 0): ?>
                                        <?php 
                                        // Group bookings by workplace
                                        $bookings_by_workplace = [];
                                        foreach ($day_bookings as $booking) {
                                            $workplace_id = $booking['id_work_place'] ?? 'unknown';
                                            if (!isset($bookings_by_workplace[$workplace_id])) {
                                                $bookings_by_workplace[$workplace_id] = [];
                                            }
                                            $bookings_by_workplace[$workplace_id][] = $booking;
                                        }
                                        
                                        // Get workplace names
                                        $workplace_names = [];
                                        foreach (array_keys($bookings_by_workplace) as $wp_id) {
                                            if ($wp_id !== 'unknown') {
                                                $wp_stmt = $pdo->prepare("SELECT name_of_the_place FROM working_points WHERE unic_id = ?");
                                                $wp_stmt->execute([$wp_id]);
                                                $wp_result = $wp_stmt->fetch();
                                                $workplace_names[$wp_id] = $wp_result ? $wp_result['name_of_the_place'] : 'Unknown';
                                            } else {
                                                $workplace_names[$wp_id] = 'Unknown';
                                            }
                                        }
                                        
                                        // Get the specialist ID for working hours lookup
                                        $specialist_id_for_hours = $specialist['unic_id'];
                                        
                                        // Generate colors for each workplace
                                        $workplace_colors = [];
                                        $color_index = 0;
                                        $base_colors = [
                                            ['bg' => '#e3f2fd', 'border' => '#90caf9'], // Light blue
                                            ['bg' => '#f3e5f5', 'border' => '#ce93d8'], // Light purple
                                            ['bg' => '#e8f5e9', 'border' => '#a5d6a7'], // Light green
                                            ['bg' => '#fff3e0', 'border' => '#ffcc80'], // Light orange
                                            ['bg' => '#fce4ec', 'border' => '#f48fb1'], // Light pink
                                        ];
                                        
                                        foreach (array_keys($bookings_by_workplace) as $wp_id) {
                                            $workplace_colors[$wp_id] = $base_colors[$color_index % count($base_colors)];
                                            $color_index++;
                                        }
                                        
                                        // Check if we have multiple workplaces
                                        $has_multiple_workplaces = count($bookings_by_workplace) > 1;
                                        ?>
                                        
                                        <?php foreach ($bookings_by_workplace as $workplace_id => $workplace_bookings): ?>
                                            <?php
                                            // Calculate booking stats for this workplace
                                            $workplace_booking_count = count($workplace_bookings);
                                            $first_booking = $workplace_bookings[0];
                                            $status_class = getBookingStatusClass($first_booking);
                                            $extra_today_class = $is_today ? ' booking-count-today' : '';
                                            
                                            // Calculate total booking minutes for this workplace
                                            $total_booking_minutes = 0;
                                            foreach ($workplace_bookings as $booking) {
                                                $start = new DateTime($booking['booking_start_datetime']);
                                                $end = new DateTime($booking['booking_end_datetime']);
                                                $interval = $start->diff($end);
                                                $total_booking_minutes += ($interval->h * 60) + $interval->i;
                                            }
                                            
                                            // Get working hours for this specialist at this workplace
                                            $day_of_week = strtolower(date('l', strtotime($day['date'])));
                                            $working_hours = getWorkingHours($pdo, $specialist_id_for_hours, $workplace_id, $day['date']);
                                            $total_working_minutes = 0;
                                            
                                            if ($working_hours && is_array($working_hours)) {
                                                foreach ($working_hours as $shift) {
                                                    if (isset($shift['start']) && isset($shift['end'])) {
                                                        $start = new DateTime($shift['start']);
                                                        $end = new DateTime($shift['end']);
                                                        $interval = $start->diff($end);
                                                        $total_working_minutes += ($interval->h * 60) + $interval->i;
                                                    }
                                                }
                                            }
                                            
                                            // Calculate load percentage
                                            $load_percentage = 0;
                                            if ($total_working_minutes > 0) {
                                                $load_percentage = min(100, round(($total_booking_minutes / $total_working_minutes) * 100));
                                            }
                                            
                                            $workplace_name = $workplace_names[$workplace_id] ?? 'Unknown';
                                            $workplace_color = $workplace_colors[$workplace_id] ?? ['bg' => '#f5f5f5', 'border' => '#e0e0e0'];
                                            ?>
                                            
                                            <div class="booking-count-container" style="margin-bottom: 8px; <?= $has_multiple_workplaces ? 'background-color: ' . $workplace_color['bg'] . '; padding: 4px; border-radius: 6px; border: 1px solid ' . $workplace_color['border'] . ';' : '' ?>">
                                                <?php if ($has_multiple_workplaces): ?>
                                                <div style="font-size: 0.55rem; color: #6c757d; text-align: center; margin-bottom: 2px; font-weight: 600;">
                                                    <?= htmlspecialchars(substr($workplace_name, 0, 15)) ?><?= strlen($workplace_name) > 15 ? '...' : '' ?>
                                                </div>
                                                <?php endif; ?>
                                                <div class="booking-count <?= $status_class ?><?= $extra_today_class ?>">
                                                    <?php if ($is_past && !$is_today): ?>
                                                        <span style="font-size: 0.75rem; line-height: 1; white-space: nowrap;">completed</span>
                                                    <?php else: ?>
                                                        <span style="font-size: 0.75rem; line-height: 1; font-weight: 700;"><?= str_pad($workplace_booking_count, 2, '0', STR_PAD_LEFT) ?></span>
                                                        <span style="font-size: 0.75rem; line-height: 1; white-space: nowrap;">booked</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if ($total_working_minutes > 0): ?>
                                                    <?php if ($is_past && !$is_today): ?>
                                                        <!-- Show percentage text for past days -->
                                                        <div style="text-align: center; margin-top: 4px; font-size: 0.7rem; color: #6c757d; font-weight: 600;">
                                                            <?= $load_percentage ?>% booked
                                                        </div>
                                                    <?php else: ?>
                                                        <!-- Show progress bar for today and future days -->
                                                        <div class="day-load-progress" title="<?= $workplace_name ?>: <?= $load_percentage ?>% booked (<?= round($total_booking_minutes/60, 1) ?>h of <?= round($total_working_minutes/60, 1) ?>h working time)">
                                                            <div class="day-load-progress-fill <?= $status_class ?>" style="width: <?= $load_percentage ?>%;"></div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <div class="booking-dropdown">
                                                <ul class="booking-dropdown-list">
                                                    <?php foreach ($workplace_bookings as $booking): ?>
                                                        <?php 
                                                        $start_time = formatTime($booking['booking_start_datetime']);
                                                        $status_class = getBookingStatusClass($booking);
                                                        $tooltip = getBookingTooltip($booking, $supervisor_mode, $has_multiple_workpoints);
                                                        ?>
                                                        
                                                        <li class="booking-dropdown-item <?= $status_class ?>" 
                                                            data-bs-toggle="tooltip" 
                                                            data-bs-html="true" 
                                                            title="<?= $tooltip ?>"
                                                            onclick="openBookingModal(
    '<?= htmlspecialchars($booking['client_full_name']) ?>',
    '<?= $start_time ?>',
    '<?= htmlspecialchars($specialist['name']) ?>',
    <?= $booking['unic_id'] ?>,
    '<?= htmlspecialchars($booking['client_phone_nr'] ?? '') ?>',
    '<?= htmlspecialchars($booking['name_of_service'] ?? '') ?>',
    '<?= $start_time ?>',
    '<?= date('H:i', strtotime($booking['booking_end_datetime'])) ?>',
    '<?= date('Y-m-d', strtotime($booking['booking_start_datetime'])) ?>'
); event.stopPropagation();">
                                                            
                                                            <span class="booking-dropdown-time"><?= $start_time ?></span>
                                                            <span class="booking-dropdown-client"><?= htmlspecialchars($booking['client_full_name']) ?></span>
                                                            <?php if ($booking['name_of_service']): ?>
                                                                <div class="booking-dropdown-service"><?= htmlspecialchars($booking['name_of_service']) ?></div>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                        <?php endforeach; // End workplace loop ?>
                                        
                                    <?php else: ?>
                                        <?php if ($day['is_current_month']): ?>
                                            <!-- Empty day - no content -->
                                        <?php endif; ?>
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
    
    // Parse dates to ensure we have valid date objects
    const currentStartDateObj = new Date(currentStartDate);
    const currentEndDateObj = new Date(currentEndDate);
    
    // Determine which month we're actually viewing
    // If we're viewing a full month (1st to last day), use that month
    // Otherwise, determine the "primary" month based on which month has more days
    let currentYear, currentMonth;
    
    if (currentStartDateObj.getDate() === 1 && currentEndDateObj.getDate() === new Date(currentEndDateObj.getFullYear(), currentEndDateObj.getMonth() + 1, 0).getDate()) {
        // We're viewing a complete month
        currentYear = currentStartDateObj.getFullYear();
        currentMonth = currentStartDateObj.getMonth();
    } else {
        // We're viewing a partial month or spanning months
        // Use the month that contains more days in our range
        const startMonth = currentStartDateObj.getMonth();
        const endMonth = currentEndDateObj.getMonth();
        
        if (startMonth === endMonth) {
            // Same month
            currentYear = currentStartDateObj.getFullYear();
            currentMonth = startMonth;
        } else {
            // Spanning months - use the month with more days
            const lastDayOfStartMonth = new Date(currentStartDateObj.getFullYear(), startMonth + 1, 0).getDate();
            const daysInStartMonth = lastDayOfStartMonth - currentStartDateObj.getDate() + 1;
            const daysInEndMonth = currentEndDateObj.getDate();
            
            if (daysInStartMonth >= daysInEndMonth) {
                currentYear = currentStartDateObj.getFullYear();
                currentMonth = startMonth;
            } else {
                currentYear = currentEndDateObj.getFullYear();
                currentMonth = endMonth;
            }
        }
    }
    
    console.log('Current date info:', {
        currentYear: currentYear,
        currentMonth: currentMonth,
        startDate: currentStartDateObj,
        endDate: currentEndDateObj
    });
    
    // Calculate the target month
    let targetStartYear = currentYear;
    let targetStartMonth = currentMonth;
    let targetEndYear = currentYear;
    let targetEndMonth = currentMonth;
    
    if (direction === 'next') {
        // Next: Show next two months
        // First, determine the starting point - if we're viewing multiple months,
        // start from the last month in the current view
        if (currentStartDateObj.getMonth() !== currentEndDateObj.getMonth() || 
            currentStartDateObj.getFullYear() !== currentEndDateObj.getFullYear()) {
            // We're viewing multiple months - use the end month as the base
            currentYear = currentEndDateObj.getFullYear();
            currentMonth = currentEndDateObj.getMonth();
        }
        
        targetStartMonth = currentMonth + 1;
        targetEndMonth = currentMonth + 2;
        targetStartYear = currentYear;
        targetEndYear = currentYear;
        
        // Handle year rollover for start month
        if (targetStartMonth > 11) {
            targetStartMonth = targetStartMonth - 12;
            targetStartYear = currentYear + 1;
            targetEndYear = currentYear + 1;
        }
        
        // Handle year rollover for end month
        if (targetEndMonth > 11) {
            targetEndMonth = targetEndMonth - 12;
            if (targetStartMonth === 0) {
                // Both months rolled over
                targetEndYear = targetStartYear;
            } else {
                // Only end month rolled over
                targetEndYear = targetStartYear + 1;
            }
        }
    } else {
        // Previous: Show only one month back
        // If viewing multiple months, go back from the first month
        if (currentStartDateObj.getMonth() !== currentEndDateObj.getMonth() || 
            currentStartDateObj.getFullYear() !== currentEndDateObj.getFullYear()) {
            // We're viewing multiple months - use the start month as the base
            currentYear = currentStartDateObj.getFullYear();
            currentMonth = currentStartDateObj.getMonth();
        }
        
        targetStartMonth = currentMonth - 1;
        targetEndMonth = targetStartMonth;
        targetStartYear = currentYear;
        targetEndYear = currentYear;
        
        // Handle year rollover
        if (targetStartMonth < 0) {
            targetStartMonth = 11;
            targetStartYear = currentYear - 1;
            targetEndYear = currentYear - 1;
        }
    }
    
    console.log('Target month info:', {
        direction: direction,
        targetStartYear: targetStartYear,
        targetStartMonth: targetStartMonth,
        targetEndYear: targetEndYear,
        targetEndMonth: targetEndMonth
    });
    
    // Build the start date (always 01 of the target start month)
    const newStartDateObj = new Date(targetStartYear, targetStartMonth, 1);
    // Format date manually to avoid timezone issues
    const newStartDate = targetStartYear + '-' + 
                        String(targetStartMonth + 1).padStart(2, '0') + '-01';
    
    // Build the end date (last day of the target end month)
    const newEndDateObj = new Date(targetEndYear, targetEndMonth + 1, 0);
    const lastDay = newEndDateObj.getDate();
    const newEndDate = targetEndYear + '-' + 
                      String(targetEndMonth + 1).padStart(2, '0') + '-' + 
                      String(lastDay).padStart(2, '0');
    
    console.log('Final dates:', {
        startDate: newStartDateObj,
        newStartDate: newStartDate,
        endDate: newEndDateObj,
        newEndDate: newEndDate
    });
    
    const url = new URL(window.location);
    url.searchParams.set('start_date', newStartDate);
    url.searchParams.set('end_date', newEndDate);
    url.searchParams.set('period', 'custom');
    
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
    
    window.location.href = url.toString();
}

// Smooth scrolling for multiple months
document.querySelectorAll('.month-container').forEach((container, index) => {
    if (index > 0) {
        container.style.marginTop = '20px';
    }
});
</script> 