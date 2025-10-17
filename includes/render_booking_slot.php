<?php
include 'includes/booking_actions_template.php';

function renderBookingCell($booking) {
    $html = "<div class='booking-slot bg-light border p-2 mb-2'>";
    $html .= "<strong>{$booking['service']}</strong><br>";
    $html .= "<small>{$booking['start_time']} - {$booking['end_time']}</small><br>";
    $html .= "<div>{$booking['client_name']}</div>";

    // Check if booking is in the future
    $now = new DateTime();
    $start = new DateTime($booking['start_datetime']);
    if ($start > $now && $booking['can_edit']) {
        $html .= getBookingActionsHtml($booking['id']);
    }

    $html .= "</div>";
    return $html;
}
?>