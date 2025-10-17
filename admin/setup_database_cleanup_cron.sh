#!/bin/bash

# Database Cleanup Cron Job Setup Script
# This script sets up a daily cron job to clean up old database records at midnight

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== Database Cleanup Cron Job Setup ===${NC}"
echo ""

# Get the absolute path to the calendar directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CALENDAR_DIR="$(dirname "$SCRIPT_DIR")"
CLEANUP_SCRIPT="$CALENDAR_DIR/admin/database_cleanup_cron.php"

echo -e "${YELLOW}Calendar directory: ${NC}$CALENDAR_DIR"
echo -e "${YELLOW}Cleanup script: ${NC}$CLEANUP_SCRIPT"
echo ""

# Check if the cleanup script exists
if [ ! -f "$CLEANUP_SCRIPT" ]; then
    echo -e "${RED}Error: Cleanup script not found at $CLEANUP_SCRIPT${NC}"
    exit 1
fi

# Make the script executable
chmod +x "$CLEANUP_SCRIPT"
echo -e "${GREEN}Made cleanup script executable${NC}"

# Find PHP binary
PHP_BINARY=""
for php_path in /usr/bin/php /usr/local/bin/php /opt/php/bin/php $(which php 2>/dev/null); do
    if [ -x "$php_path" ]; then
        PHP_BINARY="$php_path"
        break
    fi
done

if [ -z "$PHP_BINARY" ]; then
    echo -e "${RED}Error: PHP binary not found. Please install PHP or specify the correct path.${NC}"
    exit 1
fi

echo -e "${GREEN}Found PHP binary: ${NC}$PHP_BINARY"

# Create the cron job entry
CRON_ENTRY="0 0 * * * $PHP_BINARY $CLEANUP_SCRIPT >> $CALENDAR_DIR/logs/database_cleanup.log 2>&1"

echo ""
echo -e "${YELLOW}Cron job entry to be added:${NC}"
echo "$CRON_ENTRY"
echo ""

# Check if cron job already exists
if crontab -l 2>/dev/null | grep -q "$CLEANUP_SCRIPT"; then
    echo -e "${YELLOW}Warning: A cron job for this script already exists.${NC}"
    echo -e "${YELLOW}Current crontab entries for this script:${NC}"
    crontab -l 2>/dev/null | grep "$CLEANUP_SCRIPT"
    echo ""
    read -p "Do you want to replace the existing entry? (y/n): " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Setup cancelled.${NC}"
        exit 0
    fi
    
    # Remove existing entries
    crontab -l 2>/dev/null | grep -v "$CLEANUP_SCRIPT" | crontab -
    echo -e "${GREEN}Removed existing cron job entries${NC}"
fi

# Add the new cron job
(crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Cron job successfully added!${NC}"
else
    echo -e "${RED}✗ Failed to add cron job${NC}"
    exit 1
fi

# Create logs directory if it doesn't exist
LOGS_DIR="$CALENDAR_DIR/logs"
if [ ! -d "$LOGS_DIR" ]; then
    mkdir -p "$LOGS_DIR"
    echo -e "${GREEN}Created logs directory: $LOGS_DIR${NC}"
fi

# Set appropriate permissions for logs directory
chmod 755 "$LOGS_DIR"

echo ""
echo -e "${GREEN}=== Setup Complete! ===${NC}"
echo ""
echo -e "${BLUE}Cron job details:${NC}"
echo "• Runs daily at midnight (00:00)"
echo "• Cleans records older than 7 days from most tables"
echo "• Cleans client_last_check records older than 1 day"
echo "• Keeps failed sync queue items for 30 days"
echo "• Keeps system logs for 30 days"
echo "• Logs output to: $CALENDAR_DIR/logs/database_cleanup.log"
echo ""
echo -e "${BLUE}Tables that will be cleaned:${NC}"
echo "• booking_changes (7 days)"
echo "• client_last_check (1 day)"
echo "• gcal_worker_signals (7 days)"
echo "• google_calendar_sync_queue (7 days for completed, 30 days for failed)"
echo "• webhook_logs (7 days)"
echo "• logs (30 days)"
echo ""
echo -e "${YELLOW}To view current cron jobs:${NC} crontab -l"
echo -e "${YELLOW}To remove this cron job:${NC} crontab -e (then delete the line)"
echo -e "${YELLOW}To test the cleanup script manually:${NC} $PHP_BINARY $CLEANUP_SCRIPT"
echo -e "${YELLOW}To view cleanup logs:${NC} tail -f $CALENDAR_DIR/logs/database_cleanup.log"
echo ""
echo -e "${GREEN}Database cleanup is now scheduled to run automatically!${NC}" 