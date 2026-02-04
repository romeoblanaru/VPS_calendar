// SMS Templates Modal Functions
// This file contains all SMS Templates modal functionality

// Store the real implementation
window.manageSMSTemplateReal = function() {
    // Get workpoint ID from window.currentWorkpointId (set by PHP in main page)
    const workpointId = window.currentWorkpointId || 0;

    // Get workpoint name from the page or use a default
    const workpointNameElement = document.querySelector('[data-workpoint-name]');
    const workpointName = workpointNameElement ? workpointNameElement.dataset.workpointName : 'This Location';

    console.log('SMS Templates - Workpoint ID:', workpointId); // Debug output

    if (!workpointId || workpointId === 0) {
        alert('No workpoint selected. Please refresh the page and try again.');
        return;
    }

    // Create modal for SMS template management
    const modalHtml = `
        <div class="modal fade show" id="smsTemplateModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5); overflow-y: auto; z-index: 10000;">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">SMS Templates Configuration</h5>
                        <button type="button" class="btn-close" onclick="closeSMSTemplateModal()"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>Available Variables:</strong><br>
                            <div class="row">
                                <div class="col-md-6">
                                    • <code>{booking_id}</code> - Booking ID<br>
                                    • <code>{organisation_alias}</code> - Organisation name<br>
                                    • <code>{workpoint_name}</code> - Working point name<br>
                                    • <code>{workpoint_address}</code> - Address<br>
                                    • <code>{workpoint_phone}</code> - Phone number<br>
                                    • <code>{service_name}</code> - Service name
                                </div>
                                <div class="col-md-6">
                                    • <code>{start_time}</code> - Start time (HH:mm)<br>
                                    • <code>{end_time}</code> - End time (HH:mm)<br>
                                    • <code>{booking_date}</code> - Full date<br>
                                    • <code>{client_name}</code> - Client name<br>
                                    • <code>{specialist_name}</code> - Specialist name
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <h6 class="mb-2">Exclude SMS notifications when booking action comes from:</h6>
                            <small class="text-muted mb-2 d-block">If a booking is cancelled/created/updated via these channels, NO SMS will be sent (to avoid duplicate notifications)</small>
                            <div class="d-flex gap-3 flex-wrap">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="channel_PHONE" value="PHONE">
                                    <label class="form-check-label" for="channel_PHONE">Phone Call</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="channel_SMS" value="SMS" checked>
                                    <label class="form-check-label" for="channel_SMS">SMS</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="channel_WEB" value="WEB">
                                    <label class="form-check-label" for="channel_WEB">Web Portal</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="channel_WHATSAPP" value="WHATSAPP">
                                    <label class="form-check-label" for="channel_WHATSAPP">WhatsApp</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="channel_MESSENGER" value="MESSENGER">
                                    <label class="form-check-label" for="channel_MESSENGER">Messenger</label>
                                </div>
                            </div>
                        </div>

                        <form id="smsTemplateForm">
                            <input type="hidden" id="sms_workpoint_id" value="${workpointId}">

                            <!-- Cancellation Template -->
                            <div class="card mb-3">
                                <div class="card-body">
                                    <label for="sms_cancellation_template" class="form-label fw-bold">Cancellation Template:</label>
                                    <textarea class="form-control" id="sms_cancellation_template" rows="3" placeholder="Loading..."></textarea>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small class="text-muted">This template will be used when sending SMS notifications for cancelled bookings.</small>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="resetToDefaultTemplate('cancellation')">Reset to Default</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Creation Template -->
                            <div class="card mb-3">
                                <div class="card-body">
                                    <label for="sms_creation_template" class="form-label fw-bold">Creation Template:</label>
                                    <textarea class="form-control" id="sms_creation_template" rows="3" placeholder="Loading..."></textarea>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small class="text-muted">This template will be used when sending SMS notifications for new bookings.</small>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="resetToDefaultTemplate('creation')">Reset to Default</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Update Template -->
                            <div class="card mb-3">
                                <div class="card-body">
                                    <label for="sms_update_template" class="form-label fw-bold">Update Template:</label>
                                    <textarea class="form-control" id="sms_update_template" rows="3" placeholder="Loading..."></textarea>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small class="text-muted">This template will be used when sending SMS notifications for booking updates.</small>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="resetToDefaultTemplate('update')">Reset to Default</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeSMSTemplateModal()">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="saveSMSTemplate()">Save All Templates</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    const existingModal = document.getElementById('smsTemplateModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Load current templates
    fetch(`admin/get_sms_template.php?workpoint_id=${workpointId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Load templates
                document.getElementById('sms_cancellation_template').value = data.cancellation_template || window.getDefaultTemplate('cancellation');
                document.getElementById('sms_creation_template').value = data.creation_template || window.getDefaultTemplate('creation');
                document.getElementById('sms_update_template').value = data.update_template || window.getDefaultTemplate('update');

                // Load excluded channels (default is SMS excluded)
                const excludedChannels = data.excluded_channels ? data.excluded_channels.split(',') : ['SMS'];
                document.querySelectorAll('input[id^="channel_"]').forEach(checkbox => {
                    checkbox.checked = excludedChannels.includes(checkbox.value);
                });
            } else {
                document.getElementById('sms_cancellation_template').value = window.getDefaultTemplate('cancellation');
                document.getElementById('sms_creation_template').value = window.getDefaultTemplate('creation');
                document.getElementById('sms_update_template').value = window.getDefaultTemplate('update');
            }
        })
        .catch(error => {
            console.error('Error loading template:', error);
            document.getElementById('sms_cancellation_template').value = window.getDefaultTemplate('cancellation');
            document.getElementById('sms_creation_template').value = window.getDefaultTemplate('creation');
            document.getElementById('sms_update_template').value = window.getDefaultTemplate('update');
        });
};

window.closeSMSTemplateModal = function() {
    const modal = document.getElementById('smsTemplateModal');
    if (modal) {
        modal.remove();
    }
};

window.getDefaultTemplate = function(type) {
    switch(type) {
        case 'cancellation':
            return 'Your Booking ID:{booking_id} at {organisation_alias} - {workpoint_name} ({workpoint_address}) for {service_name} at {start_time} - {booking_date} was canceled. Call {workpoint_phone} if needed.';
        case 'creation':
            return 'Booking confirmed! ID:{booking_id} at {organisation_alias} - {workpoint_name} for {service_name} on {booking_date} at {start_time}. Location: {workpoint_address}';
        case 'update':
            return 'Booking ID:{booking_id} updated. New time: {booking_date} at {start_time} for {service_name} at {workpoint_name}. Call {workpoint_phone} if needed.';
        default:
            return '';
    }
};

window.resetToDefaultTemplate = function(type) {
    document.getElementById(`sms_${type}_template`).value = window.getDefaultTemplate(type);
};

window.saveSMSTemplate = function() {
    const workpointId = document.getElementById('sms_workpoint_id').value;
    const cancellationTemplate = document.getElementById('sms_cancellation_template').value;
    const creationTemplate = document.getElementById('sms_creation_template').value;
    const updateTemplate = document.getElementById('sms_update_template').value;

    // Get excluded channels (checked = excluded)
    const excludedChannels = [];
    document.querySelectorAll('input[id^="channel_"]:checked').forEach(checkbox => {
        excludedChannels.push(checkbox.value);
    });

    if (!cancellationTemplate.trim() || !creationTemplate.trim() || !updateTemplate.trim()) {
        alert('Please enter all templates');
        return;
    }

    const formData = new FormData();
    formData.append('workpoint_id', workpointId);
    formData.append('cancellation_template', cancellationTemplate);
    formData.append('creation_template', creationTemplate);
    formData.append('update_template', updateTemplate);
    formData.append('excluded_channels', excludedChannels.join(','));

    fetch('admin/save_sms_template.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('SMS templates saved successfully!');
            window.closeSMSTemplateModal();
        } else {
            alert('Error saving templates: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving templates');
    });
};

// Replace the lazy loading wrapper with the real function after loading
window.manageSMSTemplate = window.manageSMSTemplateReal;
window.openSMSConfirmationSetup = window.manageSMSTemplateReal; // Alias for menu

// Initialize on load
console.log('SMS Templates Modal loaded successfully');