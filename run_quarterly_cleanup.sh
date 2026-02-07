#!/bin/bash
# Wrapper script to run quarterly cleanup every 100 days

SCRIPT_DIR="/srv/project_1/calendar"
STATE_FILE="$SCRIPT_DIR/.last_quarterly_cleanup"
PHP_SCRIPT="$SCRIPT_DIR/cleanup_quarterly_records.php"
LOG_FILE="$SCRIPT_DIR/logs/cleanup_quarterly.log"
INTERVAL_DAYS=100

# Create state file if it doesn't exist (first run)
if [ ! -f "$STATE_FILE" ]; then
    echo "0" > "$STATE_FILE"
fi

# Get last run timestamp
LAST_RUN=$(cat "$STATE_FILE")
CURRENT_TIME=$(date +%s)
DAYS_SINCE_LAST_RUN=$(( ($CURRENT_TIME - $LAST_RUN) / 86400 ))

# Check if 100 days have passed
if [ $DAYS_SINCE_LAST_RUN -ge $INTERVAL_DAYS ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Running quarterly cleanup (last run: $DAYS_SINCE_LAST_RUN days ago)" >> "$LOG_FILE"

    # Run the cleanup script
    /usr/bin/php "$PHP_SCRIPT" >> "$LOG_FILE" 2>&1

    # Update last run timestamp
    echo "$CURRENT_TIME" > "$STATE_FILE"

    echo "$(date '+%Y-%m-%d %H:%M:%S') - Quarterly cleanup completed" >> "$LOG_FILE"
else
    # Uncomment the next line if you want to log skipped runs
    # echo "$(date '+%Y-%m-%d %H:%M:%S') - Skipping cleanup (only $DAYS_SINCE_LAST_RUN days since last run)" >> "$LOG_FILE"
    :
fi
