# Database Cleanup Cron Job Setup Instructions

This guide explains how to set up the automated database cleanup system that runs at midnight daily.

## Overview

The cleanup system maintains database performance by removing old records from multiple tables:

| Table | Retention Period | Description |
|-------|------------------|-------------|
| `booking_changes` | 7 days | Auto-refresh change tracking |
| `client_last_check` | 1 day | Client session tracking |
| `gcal_worker_signals` | 7 days | Google Calendar worker signals |
| `google_calendar_sync_queue` | 7 days (completed)<br>30 days (failed) | Google Calendar sync queue |
| `webhook_logs` | 7 days | Webhook request logs |
| `logs` | 30 days | System action logs |

## Automatic Setup (Recommended)

Run the automated setup script:

```bash
cd /path/to/your/calendar/admin
chmod +x setup_database_cleanup_cron.sh
./setup_database_cleanup_cron.sh
```

## Manual Setup

### Step 1: Make the Script Executable

```bash
cd /path/to/your/calendar/admin
chmod +x database_cleanup_cron.php
```

### Step 2: Find Your PHP Binary Path

```bash
which php
# or try these common locations:
# /usr/bin/php
# /usr/local/bin/php
# /opt/php/bin/php
```

### Step 3: Test the Script

Before setting up the cron job, test the script manually:

```bash
/usr/bin/php /path/to/your/calendar/admin/database_cleanup_cron.php
```

You should see output like:
```
[2025-09-14 12:00:00] [INFO] Starting database cleanup process
[2025-09-14 12:00:01] [INFO] Cleaning Booking change tracking records older than 7 days
[2025-09-14 12:00:01] [INFO] Deleted 15 records from booking_changes
...
[2025-09-14 12:00:05] [INFO] Database cleanup completed successfully
```

### Step 4: Add to Crontab

Open your crontab for editing:

```bash
crontab -e
```

Add this line (replace `/usr/bin/php` and `/path/to/your/calendar` with your actual paths):

```bash
0 0 * * * /usr/bin/php /path/to/your/calendar/admin/database_cleanup_cron.php >> /path/to/your/calendar/logs/database_cleanup.log 2>&1
```

**Cron schedule explanation:**
- `0 0 * * *` = At 00:00 (midnight) every day
- `>>` = Append output to log file
- `2>&1` = Include error messages in the log

### Step 5: Create Logs Directory

```bash
mkdir -p /path/to/your/calendar/logs
chmod 755 /path/to/your/calendar/logs
```

### Step 6: Verify Cron Job

Check that your cron job was added:

```bash
crontab -l
```

You should see your cleanup entry in the list.

## Alternative Cron Schedules

If you want to run the cleanup at a different time, modify the cron schedule:

```bash
# Every day at 2:30 AM
30 2 * * * /usr/bin/php /path/to/calendar/admin/database_cleanup_cron.php

# Every Sunday at 3:00 AM (weekly)
0 3 * * 0 /usr/bin/php /path/to/calendar/admin/database_cleanup_cron.php

# Every day at 1:00 AM with different log file
0 1 * * * /usr/bin/php /path/to/calendar/admin/database_cleanup_cron.php >> /var/log/calendar_cleanup.log 2>&1
```

## Monitoring and Maintenance

### View Cleanup Logs

```bash
# View recent cleanup activity
tail -f /path/to/your/calendar/logs/database_cleanup.log

# View last 50 lines
tail -n 50 /path/to/your/calendar/logs/database_cleanup.log

# Search for errors
grep -i error /path/to/your/calendar/logs/database_cleanup.log
```

### Manual Cleanup Execution

You can run the cleanup manually at any time:

```bash
# Run cleanup now
/usr/bin/php /path/to/your/calendar/admin/database_cleanup_cron.php

# Run via web browser (admin access required)
https://yourdomain.com/calendar/admin/database_cleanup_cron.php
```

### Check Cron Job Status

```bash
# View all cron jobs
crontab -l

# Check system cron logs (varies by system)
tail -f /var/log/cron
# or
tail -f /var/log/syslog | grep CRON
```

## Troubleshooting

### Common Issues

1. **"Permission denied" error**
   ```bash
   chmod +x /path/to/calendar/admin/database_cleanup_cron.php
   ```

2. **"PHP not found" error**
   - Find PHP path: `which php`
   - Update cron job with correct path

3. **"Database connection failed" error**
   - Check database credentials in `includes/db.php`
   - Ensure database server is running

4. **"No such file or directory" error**
   - Verify all paths in the cron job are absolute paths
   - Check that files exist at specified locations

5. **Cron job not running**
   - Check cron service is running: `systemctl status cron`
   - Verify cron job syntax: `crontab -l`
   - Check system logs for cron errors

### Log Rotation

To prevent log files from growing too large, set up log rotation:

Create `/etc/logrotate.d/calendar-cleanup`:

```
/path/to/your/calendar/logs/database_cleanup.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 644 www-data www-data
}
```

## Customization

### Modify Retention Periods

Edit `database_cleanup_cron.php` and change the `retention_days` values in the `$config` array:

```php
'booking_changes' => [
    'table' => 'booking_changes',
    'timestamp_column' => 'change_timestamp',
    'retention_days' => 14, // Changed from 7 to 14 days
    'description' => 'Booking change tracking records'
],
```

### Add Additional Tables

To clean additional tables, add them to the `$config` array:

```php
'your_table' => [
    'table' => 'your_table_name',
    'timestamp_column' => 'created_at',
    'retention_days' => 7,
    'description' => 'Your table description'
],
```

### Disable Specific Table Cleanup

Comment out or remove entries from the `$config` array to skip cleaning specific tables.

## Security Considerations

1. **File Permissions**: Ensure the script is only executable by appropriate users
2. **Database Access**: The script uses existing database credentials from `includes/db.php`
3. **Log Files**: Set appropriate permissions on log files to prevent unauthorized access
4. **Web Access**: Web access requires admin privileges

## Performance Impact

- **Runtime**: Typically 1-10 seconds depending on data volume
- **Database Load**: Minimal impact during midnight hours
- **Disk I/O**: Brief spike during cleanup and optimization
- **Memory Usage**: <10MB PHP memory usage

The cleanup is designed to run during low-traffic hours (midnight) to minimize impact on system performance. 