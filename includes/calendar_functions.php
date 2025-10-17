<?php
/**
 * Calendar Functions for Booking System
 * Handles calendar logic, period calculations, and design selection
 */

/**
 * Calculate date range based on period selection
 */
function calculateDateRange($period, $start_date = null, $end_date = null) {
    $today = new DateTime();
    
    switch ($period) {
        case 'today':
            return [
                'start' => $today->format('Y-m-d'),
                'end' => $today->format('Y-m-d'),
                'design' => 'daily'
            ];
            
        case 'tomorrow':
            $tomorrow = clone $today;
            $tomorrow->add(new DateInterval('P1D'));
            return [
                'start' => $tomorrow->format('Y-m-d'),
                'end' => $tomorrow->format('Y-m-d'),
                'design' => 'daily'
            ];
            
        case 'this_week':
            $monday = clone $today;
            $monday->modify('monday this week');
            $sunday = clone $monday;
            $sunday->add(new DateInterval('P6D'));
            return [
                'start' => $monday->format('Y-m-d'),
                'end' => $sunday->format('Y-m-d'),
                'design' => 'weekly'
            ];
            
        case 'next_week':
            $monday = clone $today;
            $monday->modify('monday next week');
            $sunday = clone $monday;
            $sunday->add(new DateInterval('P6D'));
            return [
                'start' => $monday->format('Y-m-d'),
                'end' => $sunday->format('Y-m-d'),
                'design' => 'weekly'
            ];
            
        case 'two_weeks':
            $monday = clone $today;
            $monday->modify('monday this week');
            $sunday = clone $monday;
            $sunday->add(new DateInterval('P13D')); // 2 weeks
            return [
                'start' => $monday->format('Y-m-d'),
                'end' => $sunday->format('Y-m-d'),
                'design' => 'weekly'
            ];
            
        case 'this_month':
            $first_day = clone $today;
            $first_day->modify('first day of this month');
            $last_day = clone $today;
            $last_day->modify('last day of this month');
            return [
                'start' => $first_day->format('Y-m-d'),
                'end' => $last_day->format('Y-m-d'),
                'design' => 'monthly'
            ];
            
        case 'next_month':
            $first_day = clone $today;
            $first_day->modify('first day of next month');
            $last_day = clone $first_day;
            $last_day->modify('last day of next month');
            return [
                'start' => $first_day->format('Y-m-d'),
                'end' => $last_day->format('Y-m-d'),
                'design' => 'monthly'
            ];
            
        case 'custom':
            if ($start_date && $end_date) {
                $start = new DateTime($start_date);
                $end = new DateTime($end_date);
                $diff = $start->diff($end)->days;
                
                // Determine design based on date range
                // Less than 4 days = daily design
                // 4-14 days = weekly design  
                // 15+ days = monthly design
                $design = 'daily';
                if ($diff >= 4 && $diff <= 14) {
                    $design = 'weekly';
                } elseif ($diff >= 15) {
                    $design = 'monthly';
                }
                
                return [
                    'start' => $start->format('Y-m-d'),
                    'end' => $end->format('Y-m-d'),
                    'design' => $design
                ];
            }
            break;
    }
    
    // Default fallback
    return [
        'start' => $today->format('Y-m-d'),
        'end' => $today->format('Y-m-d'),
        'design' => 'daily'
    ];
}

/**
 * Get working hours for a specialist at a specific location
 */
function getWorkingHours($pdo, $specialist_id, $workplace_id, $date) {
    $day_of_week = strtolower(date('l', strtotime($date)));
    
    $stmt = $pdo->prepare("
        SELECT * FROM working_program 
        WHERE specialist_id = ? AND working_place_id = ? AND day_of_week = ?
    ");
    $stmt->execute([$specialist_id, $workplace_id, $day_of_week]);
    $program = $stmt->fetch();
    
    if (!$program) {
        return null;
    }
    
    $shifts = [];
    for ($i = 1; $i <= 3; $i++) {
        $start = $program["shift{$i}_start"];
        $end = $program["shift{$i}_end"];
        
        if ($start && $end && $start !== '00:00:00' && $end !== '00:00:00') {
            $shifts[] = [
                'start' => $start,
                'end' => $end
            ];
        }
    }
    
    return $shifts;
}

/**
 * Check if a time slot is within working hours
 */
function isWithinWorkingHours($time, $working_hours) {
    if (!$working_hours) {
        return false;
    }
    
    $time_obj = new DateTime($time);
    $time_str = $time_obj->format('H:i:s');
    
    foreach ($working_hours as $shift) {
        if ($time_str >= $shift['start'] && $time_str <= $shift['end']) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if a booking is in the past
 */
function isPastBooking($booking) {
    // Use current time in organization timezone for comparison
    $now = new DateTime();
    $booking_time = new DateTime($booking['booking_start_datetime']);
    return $booking_time < $now;
}

/**
 * Check if a booking is today
 */
function isTodayBooking($booking) {
    $today = date('Y-m-d');
    
    // Get the booking date from booking_start_datetime (when the client will come)
    $booking_date = date('Y-m-d', strtotime($booking['booking_start_datetime']));
    
    return $booking_date === $today;
}

/**
 * Format time for display
 */
function formatTime($time) {
    // Don't convert booking times - display them as stored
    return date('H:i', strtotime($time));
}

/**
 * Format date for display
 */
function formatDate($date) {
    return date('l, F j, Y', strtotime($date));
}

/**
 * Get day name
 */
function getDayName($date) {
    return date('l', strtotime($date));
}

/**
 * Get short day name
 */
function getShortDayName($date) {
    return date('D', strtotime($date));
}

/**
 * Generate time slots for a day (10-minute increments)
 */
function generateTimeSlots($start_time = '08:00', $end_time = '18:00') {
    $slots = [];
    $current = new DateTime($start_time);
    $end = new DateTime($end_time);
    
    while ($current < $end) {
        $slots[] = $current->format('H:i');
        $current->add(new DateInterval('PT10M'));
    }
    
    return $slots;
}

/**
 * Get bookings for a specific time slot
 */
function getBookingsForTimeSlot($bookings, $date, $time) {
    $slot_bookings = [];
    $time_obj = new DateTime($time);
    
    foreach ($bookings as $booking) {
        // Get the booking date from booking_start_datetime (when the client will come)
        $booking_date = date('Y-m-d', strtotime($booking['booking_start_datetime']));
        
        if ($booking_date === $date) {
            // Use original booking times without timezone conversion (these are appointment times)
            $booking_start = new DateTime($booking['booking_start_datetime']);
            $booking_end = new DateTime($booking['booking_end_datetime']);
            
            // Only show booking in the time slot where it starts
            // This prevents duplicate display across multiple time slots
            $booking_start_time = $booking_start->format('H:i');
            
            if ($booking_start_time === $time) {
                $slot_bookings[] = $booking;
            }
        }
    }
    
    return $slot_bookings;
}

/**
 * Get bookings for a specific date
 */
function getBookingsForDate($bookings, $date) {
    return array_filter($bookings, function($booking) use ($date) {
        // Use booking_start_datetime to get the appointment date
        $booking_date = date('Y-m-d', strtotime($booking['booking_start_datetime']));
        return $booking_date === $date;
    });
}

/**
 * Sort bookings by start time
 */
function sortBookingsByTime($bookings) {
    usort($bookings, function($a, $b) {
        return strtotime($a['booking_start_datetime']) - strtotime($b['booking_start_datetime']);
    });
    return $bookings;
}

/**
 * Get month calendar data
 */
function getMonthCalendarData($year, $month) {
    $first_day = new DateTime("$year-$month-01");
    $last_day = clone $first_day;
    $last_day->modify('last day of this month');
    
    $start_date = clone $first_day;
    $start_date->modify('monday this week');
    
    $end_date = clone $last_day;
    $end_date->modify('sunday this week');
    
    $calendar = [];
    $current = clone $start_date;
    
    while ($current <= $end_date) {
        $week = [];
        for ($i = 0; $i < 7; $i++) {
            $week[] = [
                'date' => $current->format('Y-m-d'),
                'day' => $current->format('j'),
                'is_current_month' => $current->format('Y-m') === "$year-$month",
                'is_today' => $current->format('Y-m-d') === date('Y-m-d')
            ];
            $current->add(new DateInterval('P1D'));
        }
        $calendar[] = $week;
    }
    
    return $calendar;
}

/**
 * Get week calendar data
 */
function getWeekCalendarData($start_date) {
    $start = new DateTime($start_date);
    $start->modify('monday this week');
    
    $week = [];
    for ($i = 0; $i < 7; $i++) {
        $week[] = [
            'date' => $start->format('Y-m-d'),
            'day_name' => $start->format('l'),
            'short_day' => $start->format('D'),
            'day_number' => $start->format('j'),
            'is_today' => $start->format('Y-m-d') === date('Y-m-d')
        ];
        $start->add(new DateInterval('P1D'));
    }
    
    return $week;
}

/**
 * Get CSS class for booking status
 */
function getBookingStatusClass($booking) {
    if (isPastBooking($booking)) {
        return 'booking-past';
    } elseif (isTodayBooking($booking)) {
        return 'booking-today';
    } else {
        return 'booking-future';
    }
}

/**
 * Get booking tooltip content
 */
function getBookingTooltip($booking, $is_supervisor_mode = false, $has_multiple_workpoints = false) {
    $start_time = formatTime($booking['booking_start_datetime']);
    $end_time = formatTime($booking['booking_end_datetime']);
    
    // Get the appointment date from booking_start_datetime (when the client will come)
    $appointment_date = date('Y-m-d', strtotime($booking['booking_start_datetime']));
    $date = formatDate($appointment_date);
    
    // day_of_creation shows when the booking was recorded
    $creation_time = '';
    if (isset($booking['day_of_creation']) && $booking['day_of_creation']) {
        $creation_time = date('Y-m-d H:i', strtotime($booking['day_of_creation']));
    } else {
        $creation_time = 'N/A';
    }
    
    // Start building tooltip without ID (moved below)
    $tooltip = "<strong>Client:</strong> " . htmlspecialchars($booking['client_full_name']) . "<br>";
    $tooltip .= "<strong>Phone:</strong> " . htmlspecialchars($booking['client_phone_nr'] ?? 'N/A') . "<br>";
    $tooltip .= "<strong>From:</strong> $start_time - $end_time<br>";
    
    if ($booking['name_of_service']) {
        $tooltip .= htmlspecialchars($booking['name_of_service']) . "<br>";
    }
    
    // Only show location/address if not in supervisor mode AND specialist has multiple workpoints
    if (!$is_supervisor_mode && $has_multiple_workpoints) {
        $tooltip .= "<hr style='margin: 5px 0; border: 1px solid #ccc;'>";
        $tooltip .= "<strong>Location:</strong> " . htmlspecialchars($booking['name_of_the_place']) . "<br>";
        if (isset($booking['address']) && $booking['address']) {
            $tooltip .= "(" . htmlspecialchars($booking['address']) . ")<br>";
        }
    }
    
    $tooltip .= "<hr style='margin: 5px 0; border: 1px solid #ccc;'>";
    
    // Add Booking ID here
    $tooltip .= "<small style='font-size: 0.7em;'>";
    $tooltip .= "<strong>Booking ID:</strong> #" . htmlspecialchars($booking['unic_id']) . "<br>";
    $tooltip .= "Created: $creation_time";
    
    // Display the actual received_through value from database
    if (isset($booking['received_through']) && !empty($booking['received_through'])) {
        $tooltip .= "<br>from: " . htmlspecialchars($booking['received_through']);
    }
    
    $tooltip .= "</small>";
    
    return $tooltip;
}

/**
 * Calculate rowspan for a booking based on its duration
 */
function calculateBookingRowspan($booking, $time_slots) {
    $start_time = formatTime($booking['booking_start_datetime']);
    $end_time = formatTime($booking['booking_end_datetime']);
    
    // Find the index of the start time slot
    $start_index = -1;
    $end_index = -1;
    
    foreach ($time_slots as $index => $slot_time) {
        if ($slot_time === $start_time) {
            $start_index = $index;
        }
        if ($slot_time === $end_time) {
            $end_index = $index;
            break;
        }
    }
    
    // If we found the start slot, calculate rowspan
    if ($start_index !== -1) {
        if ($end_index !== -1) {
            // Booking ends at a specific time slot
            return $end_index - $start_index + 1;
        } else {
            // Booking ends after the last time slot, calculate based on duration
            $start_minutes = (int)substr($start_time, 0, 2) * 60 + (int)substr($start_time, 3, 2);
            $end_minutes = (int)substr($end_time, 0, 2) * 60 + (int)substr($end_time, 3, 2);
            $duration_minutes = $end_minutes - $start_minutes;
            
            // Each time slot is 10 minutes, so calculate how many slots this booking spans
            $rowspan = max(1, ceil($duration_minutes / 10));
            
            // Ensure we don't exceed the remaining time slots
            $remaining_slots = count($time_slots) - $start_index;
            return min($rowspan, $remaining_slots);
        }
    }
    
    return 1; // Default to 1 if we can't calculate
}

/**
 * Get the first available time from working shifts for a given date
 */
function getFirstAvailableTime($pdo, $specialist_id, $workplace_id, $date) {
    $working_hours = getWorkingHours($pdo, $specialist_id, $workplace_id, $date);
    
    if (!$working_hours || empty($working_hours)) {
        return '09:00'; // Default time if no working hours defined
    }
    
    // Get the earliest start time from all shifts
    $earliest_time = null;
    foreach ($working_hours as $shift) {
        $start_time = substr($shift['start'], 0, 5); // Get HH:MM format
        if (!$earliest_time || $start_time < $earliest_time) {
            $earliest_time = $start_time;
        }
    }
    
    return $earliest_time ?: '09:00';
}
?> 