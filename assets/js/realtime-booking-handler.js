// Real-time Booking Handler Module
// Handles Server-Sent Events (SSE) for live booking updates

(function(window) {
    'use strict';

    // Configuration
    const RELOAD_COOLDOWN = 10000; // 10 seconds between reloads
    let realtimeBookings = null;
    let reloadTimeout = null;
    let lastReloadTime = parseInt(sessionStorage.getItem('lastReloadTime') || '0');

    // Initialize real-time booking updates
    function initializeRealtimeBookings(config) {
        const defaultConfig = {
            specialistId: null,
            workpointId: null,
            supervisorMode: false,
            onUpdate: handleBookingUpdate,
            onStatusChange: updateRealtimeStatus,
            debug: false
        };

        const settings = Object.assign({}, defaultConfig, config);

        realtimeBookings = new RealtimeBookings({
            specialistId: settings.specialistId,
            workpointId: settings.workpointId,
            supervisorMode: settings.supervisorMode,
            onUpdate: settings.onUpdate,
            onStatusChange: settings.onStatusChange,
            debug: settings.debug
        });

        realtimeBookings.start();
        return realtimeBookings;
    }

    // Handle booking update events
    function handleBookingUpdate(data) {
        // Create a unique event ID
        const eventId = data.timestamp + '_' + data.type + '_' + (data.data.booking_id || '');

        // Check if we've already processed this event
        const processedEvents = JSON.parse(sessionStorage.getItem('processedBookingEvents') || '{}');
        const now = Math.floor(Date.now() / 1000);

        // Clean up old entries (older than 60 seconds)
        for (const key in processedEvents) {
            if (now - processedEvents[key] > 60) {
                delete processedEvents[key];
            }
        }

        // Check if this event was already processed
        if (processedEvents[eventId]) {
            return;
        }

        // Only reload for recent events (within last 30 seconds)
        const eventTime = data.timestamp || 0;
        const age = now - eventTime;

        if (age < 30) {
            // Mark this event as processed
            processedEvents[eventId] = now;
            sessionStorage.setItem('processedBookingEvents', JSON.stringify(processedEvents));

            // Store event details for showing after reload
            const eventDetails = {
                type: data.type,
                clientName: data.data.client_full_name || 'Unknown',
                bookingId: data.data.booking_id,
                specialistId: data.data.specialist_id,
                timestamp: now
            };
            sessionStorage.setItem('lastBookingUpdate', JSON.stringify(eventDetails));

            // Clear any existing reload timeout
            if (reloadTimeout) {
                clearTimeout(reloadTimeout);
            }

            // Check if we recently reloaded
            const currentTime = Date.now();
            const timeSinceLastReload = currentTime - lastReloadTime;

            if (timeSinceLastReload < RELOAD_COOLDOWN) {
                return;
            }

            // Check if Google Calendar import is in progress
            if (window.gcalImportInProgress) {
                return;
            }

            // Schedule reload with a small delay to batch multiple updates
            reloadTimeout = setTimeout(() => {
                lastReloadTime = Date.now();
                sessionStorage.setItem('lastReloadTime', lastReloadTime.toString());
                window.location.reload();
            }, 1000); // Wait 1 second to batch multiple updates
        }
    }

    // Update real-time status indicator
    function updateRealtimeStatus(status, message, mode) {
        const statusBtn = document.getElementById('realtime-status-btn');
        if (!statusBtn) return;

        const statusIcon = statusBtn.querySelector('.status-icon');

        // Detailed tooltip descriptions
        let tooltip = '';

        // Update icon and color based on status
        switch(status) {
            case 'connected':
                statusIcon.style.color = '#28a745'; // Green
                tooltip = `Real-time booking updates: ACTIVE\nMode: ${message}\nClick to disable automatic updates`;
                break;
            case 'reconnecting':
                statusIcon.style.color = '#ffc107'; // Yellow/Orange
                tooltip = `Real-time booking updates: RECONNECTING\nStatus: ${message}\nClick to disable`;
                break;
            case 'error':
                statusIcon.style.color = '#dc3545'; // Red
                tooltip = `Real-time booking updates: ERROR\nStatus: ${message}\nClick to retry`;
                break;
            case 'stopped':
                statusIcon.style.color = '#dc3545'; // Red
                tooltip = `Real-time booking updates: DISABLED\nClick to enable automatic updates`;
                break;
        }

        statusBtn.title = tooltip;
    }

    // Toggle real-time updates on/off
    function toggleRealtimeUpdates() {
        if (realtimeBookings) {
            const isEnabled = realtimeBookings.toggle();
            const statusBtn = document.getElementById('realtime-status-btn');

            if (!isEnabled) {
                updateRealtimeStatus('stopped', 'Disabled', 'none');
            }
        }
    }

    // Show booking update notification if page was reloaded due to update
    function showBookingNotification() {
        const lastUpdate = sessionStorage.getItem('lastBookingUpdate');
        if (lastUpdate) {
            sessionStorage.removeItem('lastBookingUpdate');
            const update = JSON.parse(lastUpdate);

            // Create notification element
            const notification = document.createElement('div');
            notification.className = `booking-notification ${update.type}`;

            const icons = {
                create: 'fa-plus-circle',
                update: 'fa-edit',
                delete: 'fa-trash'
            };

            const titles = {
                create: 'New Booking Added',
                update: 'Booking Updated',
                delete: 'Booking Cancelled'
            };

            notification.innerHTML = `
                <button class="notification-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="notification-header">
                    <div class="notification-icon ${update.type}">
                        <i class="fas ${icons[update.type] || 'fa-info-circle'}"></i>
                    </div>
                    <div>
                        <div>${titles[update.type] || 'Booking Changed'}</div>
                        <small style="color: #999; font-weight: normal;">Just now</small>
                    </div>
                </div>
                <div class="notification-body">
                    <strong>Client:</strong> ${update.clientName}<br>
                    <strong>Booking ID:</strong> #${update.bookingId}<br>
                    ${update.type === 'update' ? '<em>The page has been refreshed to show the latest changes.</em>' : ''}
                </div>
            `;

            const container = document.getElementById('notification-container');
            if (container) {
                container.appendChild(notification);

                // Remove notification after animation completes
                setTimeout(() => {
                    notification.remove();
                }, 5000);
            }
        }
    }

    // Export functions to global scope
    window.RealtimeBookingHandler = {
        initialize: initializeRealtimeBookings,
        toggle: toggleRealtimeUpdates,
        showNotification: showBookingNotification,
        updateStatus: updateRealtimeStatus
    };

})(window);