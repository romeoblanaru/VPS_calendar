#!/bin/bash
# Worker control script that handles both systemd and direct process control

WORKER=$1
COMMAND=$2

# Validate inputs
if [ -z "$WORKER" ] || [ -z "$COMMAND" ]; then
    echo "Usage: $0 {booking-event|google-calendar|sms} {start|stop|restart|status}"
    exit 1
fi

# Map worker names to service names and scripts
case "$WORKER" in
    booking-event)
        SERVICE="booking-event-worker"
        SCRIPT="booking_event_worker.php"
        ;;
    google-calendar)
        SERVICE="google-calendar-worker"
        SCRIPT="process_google_calendar_queue_enhanced.php"
        ;;
    sms)
        SERVICE="sms-worker"
        SCRIPT="sms_worker.php"
        ;;
    *)
        echo "Invalid worker: $WORKER"
        exit 1
        ;;
esac

# Check if we can use systemctl
CAN_USE_SYSTEMCTL=false
if systemctl is-enabled "$SERVICE" &>/dev/null; then
    CAN_USE_SYSTEMCTL=true
fi

# Execute command
case "$COMMAND" in
    start)
        if [ "$CAN_USE_SYSTEMCTL" = true ] && sudo -n systemctl start "$SERVICE" 2>/dev/null; then
            echo "Started $SERVICE via systemd"
        else
            # Kill existing process
            pkill -f "$SCRIPT" 2>/dev/null
            sleep 1
            
            # Start using the start_workers.sh script
            cd /srv/project_1/calendar
            ./start_workers.sh "$WORKER"
            echo "Started $WORKER via direct process"
        fi
        ;;
    
    stop)
        # Try multiple methods to stop the process
        # First try graceful kill
        pkill -f "$SCRIPT" 2>/dev/null
        sleep 1
        
        # If still running, force kill
        if pgrep -f "$SCRIPT" > /dev/null; then
            pkill -9 -f "$SCRIPT" 2>/dev/null
            sleep 1
        fi
        
        # If systemd is available, note that manual stop may be needed
        if [ "$CAN_USE_SYSTEMCTL" = true ]; then
            # Try sudo stop (will fail without permissions)
            sudo -n systemctl stop "$SERVICE" 2>/dev/null || true
            
            # Wait a bit more for systemd to restart
            sleep 2
            
            # Check if actually stopped
            if pgrep -f "$SCRIPT" > /dev/null; then
                echo "Warning: Process killed but systemd may restart it. Use: sudo systemctl stop $SERVICE"
            else
                echo "Stopped $SERVICE"
            fi
        else
            echo "Stopped $WORKER process"
        fi
        ;;
    
    restart)
        if [ "$CAN_USE_SYSTEMCTL" = true ] && sudo -n systemctl restart "$SERVICE" 2>/dev/null; then
            echo "Restarted $SERVICE via systemd"
        else
            # Stop and start
            pkill -f "$SCRIPT"
            sleep 2
            cd /srv/project_1/calendar
            ./start_workers.sh "$WORKER"
            echo "Restarted $WORKER via direct process"
        fi
        ;;
    
    status)
        # Check both systemd and process
        SYSTEMD_ACTIVE=false
        PROCESS_ACTIVE=false
        
        if [ "$CAN_USE_SYSTEMCTL" = true ] && [ "$(systemctl is-active "$SERVICE" 2>/dev/null)" = "active" ]; then
            SYSTEMD_ACTIVE=true
        fi
        
        if pgrep -f "$SCRIPT" > /dev/null; then
            PROCESS_ACTIVE=true
        fi
        
        # Return status with type
        if [ "$PROCESS_ACTIVE" = true ]; then
            if [ "$SYSTEMD_ACTIVE" = true ]; then
                echo "active:systemd"
            else
                echo "active:direct"
            fi
        else
            echo "inactive"
        fi
        ;;
    
    *)
        echo "Invalid command: $COMMAND"
        exit 1
        ;;
esac