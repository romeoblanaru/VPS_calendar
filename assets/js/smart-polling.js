/**
 * Smart polling that won't overload the server
 * Uses exponential backoff and version checking
 */

class SmartPolling {
    constructor(options) {
        this.options = {
            specialistId: options.specialistId,
            workpointId: options.workpointId,
            minInterval: 5000,    // 5 seconds minimum
            maxInterval: 30000,   // 30 seconds maximum
            onUpdate: options.onUpdate || (() => window.location.reload())
        };
        
        this.currentInterval = this.options.minInterval;
        this.lastVersion = 0;
        this.timer = null;
        this.enabled = true;
        this.consecutiveNoChanges = 0;
    }
    
    start() {
        this.checkForUpdates();
    }
    
    stop() {
        if (this.timer) {
            clearTimeout(this.timer);
        }
    }
    
    async checkForUpdates() {
        if (!this.enabled) return;
        
        try {
            const params = new URLSearchParams();
            if (this.options.specialistId) {
                params.append('specialist_id', this.options.specialistId);
            }
            if (this.options.workpointId) {
                params.append('workpoint_id', this.options.workpointId);
            }
            
            const response = await fetch('api/bookings_version.php?' + params);
            const data = await response.json();
            
            if (this.lastVersion === 0) {
                // First check - just store version
                this.lastVersion = data.version || data.specialist_version || data.workpoint_version;
            } else {
                const currentVersion = data.version || data.specialist_version || data.workpoint_version;
                
                if (currentVersion > this.lastVersion) {
                    // Changes detected!
                    this.lastVersion = currentVersion;
                    this.currentInterval = this.options.minInterval; // Reset to fast polling
                    this.consecutiveNoChanges = 0;
                    this.options.onUpdate();
                    return; // Page will reload
                } else {
                    // No changes - slow down polling
                    this.consecutiveNoChanges++;
                    if (this.consecutiveNoChanges > 3) {
                        this.currentInterval = Math.min(
                            this.currentInterval * 1.5,
                            this.options.maxInterval
                        );
                    }
                }
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
        
        // Schedule next check
        this.timer = setTimeout(() => this.checkForUpdates(), this.currentInterval);
    }
}