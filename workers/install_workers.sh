#!/bin/bash

# Install and enable PHP Workers as systemd services
# Run with sudo

echo "Installing PHP Workers as systemd services..."

# Copy service files to systemd directory
echo "1. Copying service files..."
sudo cp /srv/project_1/calendar/workers/booking-event-worker.service /etc/systemd/system/
sudo cp /srv/project_1/calendar/workers/google-calendar-worker.service /etc/systemd/system/
sudo cp /srv/project_1/calendar/workers/sms-worker.service /etc/systemd/system/

# Reload systemd to recognize new services
echo "2. Reloading systemd daemon..."
sudo systemctl daemon-reload

# Enable services to start on boot
echo "3. Enabling services..."
sudo systemctl enable booking-event-worker.service
sudo systemctl enable google-calendar-worker.service
sudo systemctl enable sms-worker.service

# Stop any existing PHP worker processes
echo "4. Stopping existing worker processes..."
pkill -f "booking_event_worker.php" || true
pkill -f "process_google_calendar_queue_enhanced.php" || true
pkill -f "sms_worker.php" || true
sleep 2

# Start the services
echo "5. Starting services..."
sudo systemctl start booking-event-worker.service
sudo systemctl start google-calendar-worker.service
sudo systemctl start sms-worker.service

# Show status
echo ""
echo "Service Status:"
echo "==============="
sudo systemctl status booking-event-worker.service --no-pager | head -10
echo ""
sudo systemctl status google-calendar-worker.service --no-pager | head -10
echo ""
sudo systemctl status sms-worker.service --no-pager | head -10

echo ""
echo "Installation complete!"
echo ""
echo "Useful commands:"
echo "- sudo systemctl status booking-event-worker"
echo "- sudo systemctl status google-calendar-worker"
echo "- sudo systemctl status sms-worker"
echo "- sudo journalctl -u booking-event-worker -f"
echo "- sudo journalctl -u google-calendar-worker -f"
echo "- sudo journalctl -u sms-worker -f"
echo "- sudo systemctl restart booking-event-worker"
echo "- sudo systemctl restart google-calendar-worker"
echo "- sudo systemctl restart sms-worker"