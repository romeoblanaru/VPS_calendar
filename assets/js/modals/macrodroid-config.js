/**
 * MacroDroid Configuration Modal
 * Manages MacroDroid SMS and WhatsApp automation settings for workpoints
 */

// Global variables to track status overrides (admin only)
let smsManualStatus = null;
let whatsappManualStatus = null;
let isAdminUser = false;

// Update status badge based on field values
function updateStatusBadge(type) {
    const phoneField = document.getElementById(`${type}PhoneNr`);
    const linkField = document.getElementById(`${type}DroidLink`);
    const badge = document.getElementById(`${type}StatusBadge`);

    if (!phoneField || !linkField || !badge) return;

    const phoneValue = phoneField.value.trim();
    const linkValue = linkField.value.trim();

    // Check if admin has manually overridden the status
    const manualStatus = type === 'sms' ? smsManualStatus : whatsappManualStatus;

    if (manualStatus !== null && isAdminUser) {
        // Admin has manually set the status
        if (manualStatus === 1) {
            badge.textContent = 'ACTIVE';
            badge.className = 'badge bg-success';
        } else {
            badge.textContent = 'INACTIVE';
            badge.className = 'badge bg-secondary';
        }
    } else {
        // Auto-detect based on field values
        if (phoneValue && linkValue) {
            badge.textContent = 'ACTIVE';
            badge.className = 'badge bg-success';
        } else {
            badge.textContent = 'INACTIVE';
            badge.className = 'badge bg-secondary';
        }
    }
}

// Toggle status (admin only)
function toggleStatus(type) {
    if (!isAdminUser) return;

    const badge = document.getElementById(`${type}StatusBadge`);
    if (!badge) return;

    // Get current status
    const currentStatus = badge.textContent === 'ACTIVE' ? 1 : 0;
    const newStatus = currentStatus === 1 ? 0 : 1;

    // Store manual override
    if (type === 'sms') {
        smsManualStatus = newStatus;
    } else {
        whatsappManualStatus = newStatus;
    }

    // Update badge
    if (newStatus === 1) {
        badge.textContent = 'ACTIVE';
        badge.className = 'badge bg-success';
    } else {
        badge.textContent = 'INACTIVE';
        badge.className = 'badge bg-secondary';
    }
}

// Open MacroDroid Configuration Modal
window.openMacroDroidConfigModalReal = function() {
    const modal = document.getElementById('macroDroidConfigModal');
    if (!modal) {
        console.error('MacroDroid Config Modal element not found');
        return;
    }

    // Detect if user is admin
    isAdminUser = window.isAdminImpersonating || false;

    // Get current workpoint ID
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    if (!workpointId) {
        alert('Please select a working point first');
        return;
    }

    const wpId = workpointId;

    // Load current configuration
    fetch('admin/get_droid_config.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'wp_id=' + encodeURIComponent(wpId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reset manual status overrides
            smsManualStatus = null;
            whatsappManualStatus = null;

            // Populate SMS fields
            document.getElementById('smsPhoneNr').value = data.sms.sms_phone_nr || '';
            document.getElementById('smsDroidLink').value = data.sms.droid_link || '';

            // Update SMS status badge
            const smsActive = data.sms.active === '1' || data.sms.active === 1;
            const smsBadge = document.getElementById('smsStatusBadge');
            if (smsActive) {
                smsBadge.textContent = 'ACTIVE';
                smsBadge.className = 'badge bg-success';
                smsManualStatus = 1;
            } else {
                smsBadge.textContent = 'INACTIVE';
                smsBadge.className = 'badge bg-secondary';
                smsManualStatus = 0;
            }

            // Populate WhatsApp fields
            document.getElementById('whatsappPhoneNr').value = data.whatsapp.whatsapp_phone_nr || '';
            document.getElementById('whatsappDroidLink').value = data.whatsapp.droid_link || '';

            // Update WhatsApp status badge
            const whatsappActive = data.whatsapp.active === '1' || data.whatsapp.active === 1;
            const whatsappBadge = document.getElementById('whatsappStatusBadge');
            if (whatsappActive) {
                whatsappBadge.textContent = 'ACTIVE';
                whatsappBadge.className = 'badge bg-success';
                whatsappManualStatus = 1;
            } else {
                whatsappBadge.textContent = 'INACTIVE';
                whatsappBadge.className = 'badge bg-secondary';
                whatsappManualStatus = 0;
            }

            // Setup admin-specific features
            setupAdminFeatures();

            // Add event listeners to update status on field change
            setupFieldListeners();

            // Show modal
            modal.style.display = 'block';
        } else {
            alert('Failed to load MacroDroid configuration: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error loading MacroDroid config:', error);
        alert('Failed to load MacroDroid configuration. Please try again.');
    });
};

// Setup admin-specific features
function setupAdminFeatures() {
    const smsBadge = document.getElementById('smsStatusBadge');
    const whatsappBadge = document.getElementById('whatsappStatusBadge');
    const adminNote = document.getElementById('adminStatusNote');

    if (isAdminUser) {
        // Make badges clickable for admin
        if (smsBadge) {
            smsBadge.style.cursor = 'pointer';
            smsBadge.title = 'Click to toggle status';
            smsBadge.onclick = () => toggleStatus('sms');
        }
        if (whatsappBadge) {
            whatsappBadge.style.cursor = 'pointer';
            whatsappBadge.title = 'Click to toggle status';
            whatsappBadge.onclick = () => toggleStatus('whatsapp');
        }
        if (adminNote) {
            adminNote.style.display = 'inline';
        }
    } else {
        // Non-admin: badges not clickable
        if (smsBadge) {
            smsBadge.style.cursor = 'default';
            smsBadge.title = 'Status can only be changed by admin';
            smsBadge.onclick = null;
        }
        if (whatsappBadge) {
            whatsappBadge.style.cursor = 'default';
            whatsappBadge.title = 'Status can only be changed by admin';
            whatsappBadge.onclick = null;
        }
        if (adminNote) {
            adminNote.style.display = 'none';
        }
    }
}

// Setup field listeners for real-time status updates (non-admin only)
function setupFieldListeners() {
    ['sms', 'whatsapp'].forEach(type => {
        const phoneField = document.getElementById(`${type}PhoneNr`);
        const linkField = document.getElementById(`${type}DroidLink`);

        if (phoneField) {
            phoneField.removeEventListener('input', () => updateStatusBadge(type));
            phoneField.addEventListener('input', () => {
                // Only auto-update if not admin or no manual override
                if (!isAdminUser) {
                    updateStatusBadge(type);
                }
            });
        }
        if (linkField) {
            linkField.removeEventListener('input', () => updateStatusBadge(type));
            linkField.addEventListener('input', () => {
                // Only auto-update if not admin or no manual override
                if (!isAdminUser) {
                    updateStatusBadge(type);
                }
            });
        }
    });
}

// Close MacroDroid Configuration Modal
window.closeMacroDroidConfigModal = function() {
    const modal = document.getElementById('macroDroidConfigModal');
    if (modal) {
        modal.style.display = 'none';
    }
    // Reset manual overrides
    smsManualStatus = null;
    whatsappManualStatus = null;
};

// Save MacroDroid Configuration
window.saveMacroDroidSettings = function() {
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    if (!workpointId) {
        alert('Please select a working point first');
        return;
    }

    const wpId = workpointId;

    // Get form values
    const formData = new URLSearchParams();
    formData.append('wp_id', wpId);

    // SMS fields
    const smsPhoneNr = document.getElementById('smsPhoneNr').value.trim();
    const smsDroidLink = document.getElementById('smsDroidLink').value.trim();

    // Determine active status
    let smsActive;
    if (isAdminUser && smsManualStatus !== null) {
        // Admin has manually set the status
        smsActive = smsManualStatus;
    } else {
        // Auto-determine based on fields
        smsActive = (smsPhoneNr && smsDroidLink) ? 1 : 0;
    }

    formData.append('sms_active', smsActive);
    formData.append('sms_phone_nr', smsPhoneNr);
    formData.append('sms_droid_link', smsDroidLink);

    // WhatsApp fields
    const whatsappPhoneNr = document.getElementById('whatsappPhoneNr').value.trim();
    const whatsappDroidLink = document.getElementById('whatsappDroidLink').value.trim();

    // Determine active status
    let whatsappActive;
    if (isAdminUser && whatsappManualStatus !== null) {
        // Admin has manually set the status
        whatsappActive = whatsappManualStatus;
    } else {
        // Auto-determine based on fields
        whatsappActive = (whatsappPhoneNr && whatsappDroidLink) ? 1 : 0;
    }

    formData.append('whatsapp_active', whatsappActive);
    formData.append('whatsapp_phone_nr', whatsappPhoneNr);
    formData.append('whatsapp_droid_link', whatsappDroidLink);

    // Save to server
    fetch('admin/update_droid_config.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('MacroDroid configuration saved successfully!');
            closeMacroDroidConfigModal();
        } else {
            alert('Failed to save configuration: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error saving MacroDroid config:', error);
        alert('Failed to save configuration. Please try again.');
    });
};

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('macroDroidConfigModal');
    if (event.target === modal) {
        closeMacroDroidConfigModal();
    }
});
