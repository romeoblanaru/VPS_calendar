# PHP Workers Systemd Notes

## Important: Systemd Service Control

When workers are running as systemd services with `Restart=always`, they will automatically restart when killed. This is by design to ensure service reliability.

### Options for Web Control:

1. **Setup sudo permissions (Recommended)**
   ```bash
   sudo /srv/project_1/calendar/workers/setup_sudo_permissions.sh
   ```
   This allows the web interface to properly control systemd services.

2. **Disable auto-restart temporarily**
   ```bash
   # Edit service files and change:
   # Restart=always → Restart=on-failure
   sudo systemctl edit booking-event-worker
   sudo systemctl edit google-calendar-worker
   ```

3. **Manual control**
   Use SSH to manually control services:
   ```bash
   sudo systemctl stop booking-event-worker
   sudo systemctl start booking-event-worker
   sudo systemctl restart booking-event-worker
   ```

## Current Behavior

- **Start**: Works - creates new process if not running
- **Stop**: Process is killed but systemd restarts it immediately
- **Restart**: Works - kills and restarts process
- **Status**: Shows (systemd) when running as systemd service

## Solution

For proper web control of systemd services, run:
```bash
sudo /srv/project_1/calendar/workers/setup_sudo_permissions.sh
```

This grants www-data user permission to control the worker services without a password.