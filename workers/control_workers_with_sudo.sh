#!/bin/bash
# Worker control script with sudo password support

WORKER=$1
COMMAND=$2
SUDO_PASS=$3

# Validate inputs
if [ -z "$WORKER" ] || [ -z "$COMMAND" ]; then
    echo "Usage: $0 {booking-event|google-calendar} {start|stop|restart|status} [sudo_password]"
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

# Function to run systemctl with sudo
run_systemctl() {
    local CMD=$1
    
    if [ -n "$SUDO_PASS" ]; then
        # Try with provided password
        echo "$SUDO_PASS" | sudo -S systemctl $CMD "$SERVICE" 2>/dev/null
        return $?
    else
        # Try without password (in case sudoers is configured)
        sudo -n systemctl $CMD "$SERVICE" 2>/dev/null
        return $?
    fi
}

# Execute command
case "$COMMAND" in
    start)
        if [ "$CAN_USE_SYSTEMCTL" = true ] && run_systemctl "start"; then
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
        if [ "$CAN_USE_SYSTEMCTL" = true ] && run_systemctl "stop"; then
            echo "Stopped $SERVICE via systemd"
            # Also ensure process is killed
            pkill -f "$SCRIPT" 2>/dev/null
        else
            # Kill the process
            pkill -f "$SCRIPT" 2>/dev/null
            sleep 1
            
            # Force kill if needed
            if pgrep -f "$SCRIPT" > /dev/null; then
                pkill -9 -f "$SCRIPT" 2>/dev/null
            fi
            
            echo "Stopped $WORKER process"
        fi
        ;;
    
    restart)
        if [ "$CAN_USE_SYSTEMCTL" = true ] && run_systemctl "restart"; then
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