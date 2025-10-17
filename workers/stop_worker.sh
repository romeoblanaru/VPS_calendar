#!/bin/bash
# Stop worker script that handles permission issues

PROCESS_NAME=$1

if [ -z "$PROCESS_NAME" ]; then
    echo "Usage: $0 <process_name>"
    exit 1
fi

# Get all PIDs for the process
PIDS=$(pgrep -f "$PROCESS_NAME")

if [ -z "$PIDS" ]; then
    echo "No process found matching: $PROCESS_NAME"
    exit 0
fi

# Try to kill each PID
for PID in $PIDS; do
    # First try normal kill (SIGTERM)
    if kill $PID 2>/dev/null; then
        echo "Sent SIGTERM to PID $PID"
    else
        # If that fails, check if process exists
        if ps -p $PID > /dev/null 2>&1; then
            echo "Cannot stop PID $PID (permission denied)"
            # Try using sudo if available (will fail without password)
            sudo kill $PID 2>/dev/null || echo "Need elevated permissions to stop PID $PID"
        fi
    fi
done

# Wait a bit
sleep 2

# Check if any processes are still running
REMAINING=$(pgrep -f "$PROCESS_NAME")
if [ -z "$REMAINING" ]; then
    echo "All processes stopped successfully"
    exit 0
else
    echo "Warning: Some processes may still be running: $REMAINING"
    exit 1
fi