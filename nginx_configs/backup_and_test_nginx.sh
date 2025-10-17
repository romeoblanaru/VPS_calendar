#!/bin/bash
# Safe nginx configuration update script

echo "=== Safe Nginx Configuration Update ==="

# 1. Check if nginx-extras is installed
if ! nginx -V 2>&1 | grep -q ngx_nchan_module; then
    echo "❌ nchan module not found. Please install nginx-extras first:"
    echo "   sudo apt-get install nginx-extras"
    exit 1
fi
echo "✅ nchan module found"

# 2. Find current nginx config
NGINX_CONF=$(nginx -t 2>&1 | grep "configuration file" | awk '{print $4}')
echo "Main config: $NGINX_CONF"

# 3. Find site config for my-bookings.co.uk
SITE_CONFIG=""
for conf in /etc/nginx/sites-enabled/*; do
    if grep -q "my-bookings.co.uk" "$conf" 2>/dev/null; then
        SITE_CONFIG="$conf"
        break
    fi
done

if [ -z "$SITE_CONFIG" ]; then
    echo "❌ Could not find site config for my-bookings.co.uk"
    exit 1
fi
echo "Site config: $SITE_CONFIG"

# 4. Backup current configuration
BACKUP_DIR="/etc/nginx/backups/$(date +%Y%m%d_%H%M%S)"
echo "Creating backup at: $BACKUP_DIR"
sudo mkdir -p "$BACKUP_DIR"
sudo cp -r /etc/nginx/* "$BACKUP_DIR/"
echo "✅ Backup created"

# 5. Test if we can add include without breaking anything
echo ""
echo "=== Current Site Configuration ==="
echo "First 20 lines of $SITE_CONFIG:"
head -20 "$SITE_CONFIG"
echo "..."
echo ""

# 6. Show where to add the include
echo "=== How to Add Nchan Configuration ==="
echo ""
echo "Add this line inside your 'server {' block in $SITE_CONFIG:"
echo ""
echo "    # Include nchan configuration for real-time updates"
echo "    include /srv/project_1/calendar/nginx_configs/nchan_booking_events.conf;"
echo ""
echo "It should go AFTER your existing 'location /' blocks but BEFORE the closing '}'"
echo ""
echo "=== Test Command ==="
echo "After adding the include, test with:"
echo "    sudo nginx -t"
echo ""
echo "=== Rollback Command ==="
echo "If something breaks, restore with:"
echo "    sudo cp -r $BACKUP_DIR/* /etc/nginx/"
echo "    sudo nginx -t && sudo systemctl reload nginx"