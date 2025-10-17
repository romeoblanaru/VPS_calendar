#!/bin/bash
# Simple worker starter script

cd /srv/project_1/calendar

# Ensure log files are writable
touch logs/booking-event-worker.log logs/google-calendar-worker.log workers/logs/sms_worker.log 2>/dev/null
chmod 666 logs/*.log workers/logs/*.log 2>/dev/null
mkdir -p workers/logs 2>/dev/null

case "$1" in
    booking-event)
        echo "Starting Booking Event Worker..."
        # Try to write to log, if fails, use /dev/null
        if [ -w logs/booking-event-worker.log ]; then
            nohup php workers/booking_event_worker.php >> logs/booking-event-worker.log 2>&1 &
        else
            nohup php workers/booking_event_worker.php > /dev/null 2>&1 &
        fi
        echo $!
        ;;
    google-calendar)
        echo "Starting Google Calendar Worker..."
        if [ -w logs/google-calendar-worker.log ]; then
            nohup php process_google_calendar_queue_enhanced.php --signal-loop >> logs/google-calendar-worker.log 2>&1 &
        else
            nohup php process_google_calendar_queue_enhanced.php --signal-loop > /dev/null 2>&1 &
        fi
        echo $!
        ;;
    sms)
        echo "Starting SMS Worker..."
        # Ensure log directory exists and is writable
        mkdir -p workers/logs
        touch workers/logs/sms_worker.log
        chmod 666 workers/logs/sms_worker.log
        
        # Check if we should use sudo to start as www-data
        if [ "$(whoami)" != "www-data" ] && groups | grep -q "www-data"; then
            # If current user is in www-data group, start normally
            nohup php workers/sms_worker.php >> workers/logs/sms_worker.log 2>&1 &
        else
            # Otherwise just start as current user
            nohup php workers/sms_worker.php >> workers/logs/sms_worker.log 2>&1 &
        fi
        echo $!
        ;;
    *)
        echo "Usage: $0 {booking-event|google-calendar|sms}"
        exit 1
        ;;
esac