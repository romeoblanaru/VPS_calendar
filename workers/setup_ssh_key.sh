#!/bin/bash
# Setup SSH key for www-data to access rom user

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root (with sudo)"
    exit 1
fi

# Create .ssh directory for www-data if it doesn't exist
mkdir -p /var/www/.ssh
chown www-data:www-data /var/www/.ssh
chmod 700 /var/www/.ssh

# Generate SSH key for www-data if it doesn't exist
if [ ! -f /var/www/.ssh/id_rsa ]; then
    echo "Generating SSH key for www-data..."
    sudo -u www-data ssh-keygen -t rsa -b 2048 -f /var/www/.ssh/id_rsa -N "" -C "www-data@calendar-workers"
fi

# Display the public key
echo ""
echo "Add this public key to /home/rom/.ssh/authorized_keys:"
echo ""
cat /var/www/.ssh/id_rsa.pub
echo ""

# Optionally add it automatically
read -p "Do you want to add it automatically to rom's authorized_keys? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    # Ensure rom's .ssh directory exists
    sudo -u rom mkdir -p /home/rom/.ssh
    sudo -u rom touch /home/rom/.ssh/authorized_keys
    sudo -u rom chmod 700 /home/rom/.ssh
    sudo -u rom chmod 600 /home/rom/.ssh/authorized_keys
    
    # Add the key if not already present
    if ! grep -q "$(cat /var/www/.ssh/id_rsa.pub)" /home/rom/.ssh/authorized_keys; then
        cat /var/www/.ssh/id_rsa.pub >> /home/rom/.ssh/authorized_keys
        echo "SSH key added successfully!"
    else
        echo "SSH key already exists in authorized_keys"
    fi
fi

echo ""
echo "Setup complete! The web interface can now SSH as rom without a password."
echo "You'll still need to provide your sudo password for systemctl commands."