#!/bin/bash
# Nchan Health Check Script
# Tests nchan publishing endpoint and worker service health
# Auto-reloads nginx after 3 consecutive failures
# Run via cron every 5 minutes

LOG_FILE="/srv/project_1/calendar/logs/nchan-health.log"
ERROR_LOG="/srv/project_1/calendar/logs/nchan-health-errors.log"
FAILURE_COUNTER_FILE="/tmp/nchan_failure_counter"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
MAX_FAILURES=3
SUDO_PASS="Romy_1202"

# Test nchan publish endpoint
test_nchan() {
    local response=$(curl -s -w "\n%{http_code}" -X POST \
        -H "Content-Type: application/json" \
        -d '{"type":"health_check","timestamp":'$(date +%s)'}' \
        --max-time 3 \
        http://127.0.0.1/internal/publish/booking?channel=health_check 2>&1)

    local http_code=$(echo "$response" | tail -n1)

    if [ "$http_code" = "200" ] || [ "$http_code" = "201" ] || [ "$http_code" = "202" ]; then
        echo "[$TIMESTAMP] ✓ Nchan publish OK (HTTP $http_code)" >> "$LOG_FILE"
        return 0
    else
        echo "[$TIMESTAMP] ✗ Nchan publish FAILED (HTTP $http_code)" >> "$ERROR_LOG"
        echo "$response" >> "$ERROR_LOG"
        return 1
    fi
}

# Check worker service status
check_worker() {
    if systemctl is-active --quiet booking-event-worker.service; then
        echo "[$TIMESTAMP] ✓ Worker service running" >> "$LOG_FILE"
        return 0
    else
        echo "[$TIMESTAMP] ✗ Worker service NOT running" >> "$ERROR_LOG"
        systemctl status booking-event-worker.service >> "$ERROR_LOG" 2>&1
        return 1
    fi
}

# Get current failure count
get_failure_count() {
    if [ -f "$FAILURE_COUNTER_FILE" ]; then
        cat "$FAILURE_COUNTER_FILE"
    else
        echo "0"
    fi
}

# Increment failure count
increment_failures() {
    local count=$(get_failure_count)
    count=$((count + 1))
    echo "$count" > "$FAILURE_COUNTER_FILE"
    echo "$count"
}

# Reset failure count
reset_failures() {
    echo "0" > "$FAILURE_COUNTER_FILE"
}

# Reload nginx
reload_nginx() {
    echo "[$TIMESTAMP] CRITICAL: Reloading nginx after $1 consecutive failures" >> "$ERROR_LOG"
    echo "$SUDO_PASS" | sudo -S systemctl reload nginx >> "$ERROR_LOG" 2>&1

    if [ $? -eq 0 ]; then
        echo "[$TIMESTAMP] ✓ Nginx reloaded successfully" >> "$LOG_FILE"
        echo "[$TIMESTAMP] ✓ Nginx reloaded successfully" >> "$ERROR_LOG"
        reset_failures
        return 0
    else
        echo "[$TIMESTAMP] ✗ Failed to reload nginx" >> "$ERROR_LOG"
        return 1
    fi
}

# Main health check
nchan_ok=0
worker_ok=0

test_nchan && nchan_ok=1
check_worker && worker_ok=1

# Handle results
if [ $nchan_ok -eq 1 ] && [ $worker_ok -eq 1 ]; then
    # Success - reset failure counter
    reset_failures
    echo "[$TIMESTAMP] Health check: ALL OK" >> "$LOG_FILE"
    exit 0
else
    # Failure - increment counter and check if we need to reload nginx
    failure_count=$(increment_failures)
    echo "[$TIMESTAMP] Health check: FAILURE DETECTED (Count: $failure_count/$MAX_FAILURES)" >> "$ERROR_LOG"

    if [ $failure_count -ge $MAX_FAILURES ]; then
        reload_nginx "$failure_count"
    fi

    exit 1
fi
