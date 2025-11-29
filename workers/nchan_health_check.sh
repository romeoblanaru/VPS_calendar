#!/bin/bash
# Nchan Health Check Script
# Tests nchan publishing endpoint and worker service health
# Run via cron every 15 minutes

LOG_FILE="/srv/project_1/calendar/logs/nchan-health.log"
ERROR_LOG="/srv/project_1/calendar/logs/nchan-health-errors.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

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

# Main health check
nchan_ok=0
worker_ok=0

test_nchan && nchan_ok=1
check_worker && worker_ok=1

# Summary
if [ $nchan_ok -eq 1 ] && [ $worker_ok -eq 1 ]; then
    echo "[$TIMESTAMP] Health check: ALL OK" >> "$LOG_FILE"
    exit 0
else
    echo "[$TIMESTAMP] Health check: FAILURE DETECTED" >> "$ERROR_LOG"
    # Optional: Send email/notification here
    exit 1
fi
