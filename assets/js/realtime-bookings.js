/**
 * Real-time Booking Updates System
 * 
 * Handles SSE (Server-Sent Events) for real-time updates with polling fallback
 * Supports ~500 concurrent clients with minimal server load
 */

class RealtimeBookings {
    constructor(options = {}) {
        // Configuration
        this.options = {
            specialistId: options.specialistId || null,
            workpointId: options.workpointId || null,
            supervisorMode: options.supervisorMode || false,
            sseEndpoint: options.sseEndpoint || 'api/bookings_sse.php',
            versionEndpoint: options.versionEndpoint || 'api/bookings_version.php',
            pollingInterval: options.pollingInterval || 7000, // 7 seconds
            onUpdate: options.onUpdate || (() => window.location.reload()),
            onStatusChange: options.onStatusChange || (() => {}),
            debug: options.debug || false
        };
        
        // State
        this.eventSource = null;
        this.pollingTimer = null;
        this.connectionMode = 'none';
        this.isEnabled = true;
        this.lastVersion = 0;
        this.lastSpecialistVersion = 0;
        this.lastWorkpointVersion = 0;
        this.reconnectDelay = 1000;
        this.reconnectAttempts = 0;
        this.maxReconnectDelay = 30000;
        
        // Bind methods
        this.start = this.start.bind(this);
        this.stop = this.stop.bind(this);
        this.toggle = this.toggle.bind(this);
        this.handleSSEMessage = this.handleSSEMessage.bind(this);
        this.handleSSEError = this.handleSSEError.bind(this);
        this.pollVersion = this.pollVersion.bind(this);
    }
    
    /**
     * Start the real-time update system
     */
    start() {
        if (!this.isEnabled) return;
        
        this.log('Starting real-time booking updates...');
        
        // Try SSE first
        this.startSSE();
    }
    
    /**
     * Stop all real-time updates
     */
    stop() {
        this.log('Stopping real-time booking updates...');
        
        // Close SSE connection
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        
        // Clear polling timer
        if (this.pollingTimer) {
            clearInterval(this.pollingTimer);
            this.pollingTimer = null;
        }
        
        this.connectionMode = 'none';
        this.updateStatus('stopped', 'Real-time updates stopped');
    }
    
    /**
     * Toggle real-time updates on/off
     */
    toggle() {
        this.isEnabled = !this.isEnabled;
        
        if (this.isEnabled) {
            this.start();
        } else {
            this.stop();
        }
        
        return this.isEnabled;
    }
    
    /**
     * Start Server-Sent Events connection
     */
    startSSE() {
        if (!window.EventSource) {
            this.log('SSE not supported, falling back to polling');
            this.startPolling();
            return;
        }
        
        // Build nchan SSE URL based on user type
        let sseUrl;
        if (this.options.specialistId && !this.options.supervisorMode) {
            // Specialist viewing their own bookings
            sseUrl = `/realtime/events/specialist/${this.options.specialistId}`;
        } else if (this.options.supervisorMode && this.options.workpointId) {
            // Supervisor viewing workpoint bookings
            sseUrl = `/realtime/events/workpoint/${this.options.workpointId}`;
        } else {
            // Admin viewing all bookings
            sseUrl = `/realtime/events/admin/all`;
        }
        
        this.log('Connecting to nchan SSE endpoint:', sseUrl);
        
        try {
            this.eventSource = new EventSource(sseUrl, {
                withCredentials: true
            });
            
            // Connection opened
            this.eventSource.addEventListener('open', () => {
                this.log('SSE connection established');
                this.connectionMode = 'sse';
                this.reconnectDelay = 1000; // Reset reconnect delay
                this.reconnectAttempts = 0;
                this.updateStatus('connected', 'Real-time (SSE)');
                
                // Cancel any polling
                if (this.pollingTimer) {
                    clearInterval(this.pollingTimer);
                    this.pollingTimer = null;
                }
            });
            
            // Listen for booking updates (nchan sends as 'message' event)
            this.eventSource.addEventListener('message', this.handleSSEMessage.bind(this));
            
            // Listen for heartbeat
            this.eventSource.addEventListener('heartbeat', (event) => {
                this.log('Heartbeat received');
            });
            
            // Connection info
            this.eventSource.addEventListener('connected', (event) => {
                const data = JSON.parse(event.data);
                this.log('Connected:', data);
            });
            
            // Handle errors
            this.eventSource.addEventListener('error', this.handleSSEError.bind(this));
            
        } catch (error) {
            this.log('Failed to create EventSource:', error);
            this.startPolling();
        }
    }
    
    /**
     * Handle SSE messages
     */
    handleSSEMessage(event) {
        try {
            const data = JSON.parse(event.data);
            this.log('Booking update received:', data);
            
            // Trigger update callback
            this.options.onUpdate(data);
            
        } catch (error) {
            this.log('Error parsing SSE message:', error);
        }
    }
    
    /**
     * Handle SSE errors
     */
    handleSSEError(event) {
        this.log('SSE error occurred');
        
        if (this.eventSource.readyState === EventSource.CLOSED) {
            this.log('SSE connection closed');
            
            // Implement exponential backoff for reconnection
            this.reconnectAttempts++;
            this.reconnectDelay = Math.min(
                this.reconnectDelay * 2,
                this.maxReconnectDelay
            );
            
            if (this.reconnectAttempts > 5) {
                // Too many failures, fall back to polling
                this.log('Too many SSE failures, switching to polling');
                this.eventSource = null;
                this.startPolling();
            } else {
                // Try to reconnect
                this.updateStatus('reconnecting', `Reconnecting in ${this.reconnectDelay/1000}s...`);
                setTimeout(() => {
                    if (this.isEnabled && this.connectionMode === 'sse') {
                        this.startSSE();
                    }
                }, this.reconnectDelay);
            }
        }
    }
    
    /**
     * Start polling fallback
     */
    startPolling() {
        if (this.pollingTimer) return;
        
        this.log('Starting polling fallback');
        this.connectionMode = 'polling';
        this.updateStatus('connected', `Polling (${this.options.pollingInterval/1000}s)`);
        
        // Initial poll
        this.pollVersion();
        
        // Set up interval
        this.pollingTimer = setInterval(this.pollVersion, this.options.pollingInterval);
    }
    
    /**
     * Poll for version changes
     */
    async pollVersion() {
        if (!this.isEnabled) return;
        
        try {
            // Build version check URL
            const params = new URLSearchParams();
            if (this.options.specialistId) params.append('specialist_id', this.options.specialistId);
            if (this.options.workpointId) params.append('workpoint_id', this.options.workpointId);
            if (this.options.supervisorMode) params.append('supervisor_mode', 'true');
            
            const response = await fetch(`${this.options.versionEndpoint}?${params.toString()}`, {
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            this.log('Version check:', data);
            
            // Check if any version has changed
            let hasChanges = false;
            
            if (this.lastVersion > 0 && data.version !== this.lastVersion) {
                hasChanges = true;
            }
            
            if (this.options.specialistId && 
                this.lastSpecialistVersion > 0 && 
                data.specialist_version !== this.lastSpecialistVersion) {
                hasChanges = true;
            }
            
            if (this.options.supervisorMode && 
                this.options.workpointId &&
                this.lastWorkpointVersion > 0 && 
                data.workpoint_version !== this.lastWorkpointVersion) {
                hasChanges = true;
            }
            
            // Update stored versions
            this.lastVersion = data.version;
            this.lastSpecialistVersion = data.specialist_version;
            this.lastWorkpointVersion = data.workpoint_version;
            
            // Trigger update if changes detected
            if (hasChanges) {
                this.log('Version change detected, triggering update');
                this.options.onUpdate({
                    type: 'version_change',
                    versions: data
                });
            }
            
        } catch (error) {
            this.log('Polling error:', error);
            this.updateStatus('error', 'Connection error');
        }
    }
    
    /**
     * Update connection status
     */
    updateStatus(status, message) {
        this.options.onStatusChange(status, message, this.connectionMode);
    }
    
    /**
     * Debug logging
     */
    log(...args) {
        if (this.options.debug) {
            console.log('[RealtimeBookings]', ...args);
        }
    }
    
    /**
     * Get current status
     */
    getStatus() {
        return {
            enabled: this.isEnabled,
            mode: this.connectionMode,
            connected: this.connectionMode !== 'none'
        };
    }
}