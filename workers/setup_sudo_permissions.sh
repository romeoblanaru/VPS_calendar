#!/bin/bash
# Setup script for allowing www-data to control worker services

echo "Setting up sudo permissions for www-data to control worker services..."

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "Please run this script as root (with sudo)"
    exit 1
fi

# Copy sudoers file
echo "Installing sudoers configuration..."
cp /srv/project_1/calendar/workers/www-data-workers.sudoers /etc/sudoers.d/www-data-workers
chmod 0440 /etc/sudoers.d/www-data-workers

# Verify sudoers syntax
if visudo -c -f /etc/sudoers.d/www-data-workers; then
    echo "✓ Sudoers file syntax is valid"
else
    echo "✗ Error in sudoers file syntax!"
    rm /etc/sudoers.d/www-data-workers
    exit 1
fi

echo ""
echo "Setup complete! The web interface can now control systemd services."
echo ""
echo "The following permissions have been granted to www-data:"
echo "- Start/stop/restart booking-event-worker service"
echo "- Start/stop/restart google-calendar-worker service"
echo "- Check status of both services"
echo ""
echo "If you need to remove these permissions later, run:"
echo "sudo rm /etc/sudoers.d/www-data-workers"