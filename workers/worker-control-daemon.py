#!/usr/bin/env python3
"""
Worker Control Daemon - Secure service control without giving www-data sudo access
This daemon runs as a regular user and provides a socket-based API for controlling services
"""

import os
import sys
import socket
import json
import subprocess
import threading
import signal
import pwd
import grp
import stat

SOCKET_PATH = '/srv/project_1/calendar/workers/control.sock'
ALLOWED_SERVICES = ['booking-event-worker', 'google-calendar-worker']
ALLOWED_ACTIONS = ['start', 'stop', 'restart', 'status']

def is_safe_service(service):
    """Validate service name to prevent injection"""
    return service in ALLOWED_SERVICES

def is_safe_action(action):
    """Validate action to prevent injection"""
    return action in ALLOWED_ACTIONS

def control_service(service, action):
    """Control a systemd service"""
    if not is_safe_service(service) or not is_safe_action(action):
        return {'success': False, 'error': 'Invalid service or action'}
    
    try:
        # Use systemctl without sudo - daemon should run as a user with permissions
        cmd = ['systemctl', '--user', action, service]
        result = subprocess.run(cmd, capture_output=True, text=True)
        
        if result.returncode == 0:
            return {'success': True, 'output': result.stdout}
        else:
            return {'success': False, 'error': result.stderr}
    except Exception as e:
        return {'success': False, 'error': str(e)}

def handle_client(client_socket):
    """Handle a client connection"""
    try:
        data = client_socket.recv(1024).decode('utf-8')
        request = json.loads(data)
        
        service = request.get('service', '')
        action = request.get('action', '')
        
        response = control_service(service, action)
        client_socket.send(json.dumps(response).encode('utf-8'))
    except Exception as e:
        error_response = {'success': False, 'error': str(e)}
        client_socket.send(json.dumps(error_response).encode('utf-8'))
    finally:
        client_socket.close()

def cleanup_socket():
    """Remove socket file if it exists"""
    if os.path.exists(SOCKET_PATH):
        os.unlink(SOCKET_PATH)

def signal_handler(sig, frame):
    """Handle shutdown signals"""
    print("Shutting down worker control daemon...")
    cleanup_socket()
    sys.exit(0)

def main():
    # Set up signal handlers
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)
    
    # Clean up any existing socket
    cleanup_socket()
    
    # Create socket
    server_socket = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    server_socket.bind(SOCKET_PATH)
    
    # Set permissions so www-data can access the socket
    os.chmod(SOCKET_PATH, stat.S_IRUSR | stat.S_IWUSR | stat.S_IRGRP | stat.S_IWGRP)
    
    # Try to set group to www-data
    try:
        www_data_gid = grp.getgrnam('www-data').gr_gid
        os.chown(SOCKET_PATH, os.getuid(), www_data_gid)
    except:
        print("Warning: Could not set socket group to www-data")
    
    server_socket.listen(5)
    print(f"Worker control daemon listening on {SOCKET_PATH}")
    
    while True:
        client_socket, address = server_socket.accept()
        client_thread = threading.Thread(target=handle_client, args=(client_socket,))
        client_thread.start()

if __name__ == '__main__':
    main()