// Communication Setup Modal Functions
// This file contains all Communication Setup modal functionality

let statusPollingInterval = null;

// Store the real implementation
window.openCommunicationSetupModalReal = function() {
    // Get workpoint_id from the page context
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    if (!workpointId) {
        alert('Please select a working point first');
        return;
    }

    // Check if modal element exists
    const modal = document.getElementById('communicationSetupModal');
    if (!modal) {
        console.error('Communication Setup modal element not found');
        alert('Error: Modal not found. Please refresh the page and try again.');
        return;
    }

    loadCommunicationSettings(workpointId);
    modal.style.display = 'block';

    // Start continuous polling when modal opens
    startStatusPolling();
};

window.closeCommunicationSetupModal = function() {
    const modal = document.getElementById('communicationSetupModal');
    if (modal) {
        modal.style.display = 'none';
    }

    // Stop polling when modal closes
    stopStatusPolling();
};

window.loadCommunicationSettings = function(workpointId) {
    fetch(`admin/get_communication_settings.php?workpoint_id=${workpointId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateCommunicationForm(data.settings);
            } else {
                console.error('Error loading communication settings:', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
};

function populateCommunicationForm(settings) {
    // WhatsApp settings
    if (settings.whatsapp_business) {
        document.getElementById('whatsappPhoneNumber').value = settings.whatsapp_business.whatsapp_phone_number || '';
        document.getElementById('whatsappPhoneNumberId').value = settings.whatsapp_business.whatsapp_phone_number_id || '';
        document.getElementById('whatsappBusinessAccountId').value = settings.whatsapp_business.whatsapp_business_account_id || '';
        document.getElementById('whatsappAccessToken').value = settings.whatsapp_business.whatsapp_access_token || '';
        document.getElementById('whatsappActive').checked = settings.whatsapp_business.is_active == 1;

        // Populate test status
        populateTestStatus('whatsapp', settings.whatsapp_business);
    }

    // Facebook Messenger settings
    if (settings.facebook_messenger) {
        document.getElementById('facebookPageId').value = settings.facebook_messenger.facebook_page_id || '';
        document.getElementById('facebookPageAccessToken').value = settings.facebook_messenger.facebook_page_access_token || '';
        document.getElementById('facebookAppId').value = settings.facebook_messenger.facebook_app_id || '';
        document.getElementById('facebookAppSecret').value = settings.facebook_messenger.facebook_app_secret || '';
        document.getElementById('facebookActive').checked = settings.facebook_messenger.is_active == 1;

        // Populate test status
        populateTestStatus('facebook', settings.facebook_messenger);
    }
}

function populateTestStatus(platform, settings) {
    const statusDiv = document.getElementById(`${platform}TestStatus`);
    const messageEl = document.getElementById(`${platform}TestMessage`);
    const badgeEl = document.getElementById(`${platform}TestStatusBadge`);

    if (settings.test_status === 'testing') {
        showTestLoading(platform);
        return;
    }

    if (settings.test_status && settings.test_message) {
        statusDiv.style.display = 'block';

        // Truncate message if too long
        const fullMessage = settings.test_message;
        const displayMessage = fullMessage.length > 50 ? fullMessage.substring(0, 47) + '...' : fullMessage;

        messageEl.textContent = displayMessage;
        messageEl.title = fullMessage; // Show full message on hover

        // Set badge based on status
        if (settings.test_status === 'success') {
            badgeEl.className = 'badge bg-success';
            badgeEl.textContent = 'Connected';
        } else if (settings.test_status === 'failed') {
            badgeEl.className = 'badge bg-danger';
            badgeEl.textContent = 'Failed';
        } else {
            badgeEl.className = 'badge bg-secondary';
            badgeEl.textContent = 'Unknown';
        }
    } else {
        statusDiv.style.display = 'none';
    }
}

function showTestLoading(platform) {
    const btn = document.getElementById(`${platform}TestBtn`);
    const statusDiv = document.getElementById(`${platform}TestStatus`);
    const messageEl = document.getElementById(`${platform}TestMessage`);
    const badgeEl = document.getElementById(`${platform}TestStatusBadge`);

    // Update button
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';

    // Show loading status
    statusDiv.style.display = 'block';
    messageEl.textContent = 'Testing connection...';
    messageEl.title = 'Testing connection...';
    badgeEl.className = 'badge bg-warning';
    badgeEl.textContent = 'Testing';
}

function resetTestButton(platform) {
    const btn = document.getElementById(`${platform}TestBtn`);
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-plug"></i> Test Connection';
}

function updateTestStatus(platform, status, message) {
    const statusDiv = document.getElementById(`${platform}TestStatus`);
    const messageEl = document.getElementById(`${platform}TestMessage`);
    const badgeEl = document.getElementById(`${platform}TestStatusBadge`);

    statusDiv.style.display = 'block';

    // Truncate message if too long
    const displayMessage = message.length > 50 ? message.substring(0, 47) + '...' : message;
    messageEl.textContent = displayMessage;
    messageEl.title = message; // Show full message on hover

    if (status === 'success') {
        badgeEl.className = 'badge bg-success';
        badgeEl.textContent = 'Connected';
        resetTestButton(platform);
    } else if (status === 'failed') {
        badgeEl.className = 'badge bg-danger';
        badgeEl.textContent = 'Failed';
        resetTestButton(platform);
    } else if (status === 'testing') {
        badgeEl.className = 'badge bg-warning';
        badgeEl.textContent = 'Testing';
    }
}

function startStatusPolling() {
    // Poll every 3 seconds (less aggressive)
    statusPollingInterval = setInterval(() => {
        const workpointId = window.currentWorkpointId ||
                           document.getElementById('workpoint_id')?.value ||
                           (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

        if (!workpointId) {
            console.log('No workpoint ID for polling');
            return;
        }

        fetch(`admin/get_communication_test_status.php?workpoint_id=${workpointId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (data.whatsapp_status) {
                        updateTestStatus('whatsapp', data.whatsapp_status.status, data.whatsapp_status.message);
                    }
                    if (data.facebook_status) {
                        updateTestStatus('facebook', data.facebook_status.status, data.facebook_status.message);
                    }
                } else {
                    console.log('Polling returned success:false', data.message);
                }
            })
            .catch(error => {
                console.log('Polling skipped:', error.message);
                // Don't spam console with errors, just skip this poll
            });
    }, 3000); // Poll every 3 seconds instead of 2
}

function stopStatusPolling() {
    if (statusPollingInterval) {
        clearInterval(statusPollingInterval);
        statusPollingInterval = null;
    }
}

window.saveCommunicationSettings = function() {
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    if (!workpointId) {
        alert('Working point not found');
        return;
    }

    const form = document.getElementById('communicationSetupForm');
    const formData = new FormData(form);
    formData.append('workpoint_id', workpointId);

    fetch('admin/save_communication_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Communication settings saved successfully!');
            closeCommunicationSetupModal();
        } else {
            alert('Error saving settings: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving communication settings');
    });
};

window.testWhatsAppConnection = function() {
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    if (!workpointId) {
        alert('Working point not found');
        return;
    }

    const formData = new FormData(document.getElementById('communicationSetupForm'));
    formData.append('workpoint_id', workpointId);
    formData.append('test_platform', 'whatsapp_business');

    // Show loading indicator
    showTestLoading('whatsapp');

    fetch('admin/test_communication_connection.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (!data.success) {
            // If immediate failure, show it
            updateTestStatus('whatsapp', 'failed', data.message || 'Test failed');
        }
        // Otherwise polling will update status
    })
    .catch(error => {
        console.error('Test error:', error);
        updateTestStatus('whatsapp', 'failed', 'Connection test failed: ' + error.message);
    });
};

window.testFacebookConnection = function() {
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    if (!workpointId) {
        alert('Working point not found');
        return;
    }

    const formData = new FormData(document.getElementById('communicationSetupForm'));
    formData.append('workpoint_id', workpointId);
    formData.append('test_platform', 'facebook_messenger');

    // Show loading indicator
    showTestLoading('facebook');

    fetch('admin/test_communication_connection.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (!data.success) {
            // If immediate failure, show it
            updateTestStatus('facebook', 'failed', data.message || 'Test failed');
        }
        // Otherwise polling will update status
    })
    .catch(error => {
        console.error('Test error:', error);
        updateTestStatus('facebook', 'failed', 'Connection test failed: ' + error.message);
    });
};

const CREDENTIALS_REFRESH_BASE_URL = 'https://voice.rom2.co.uk/api/refresh-credentials';

window.refreshWhatsAppCredentials = function() {
    const phoneId = (document.getElementById('whatsappPhoneNumberId').value || '').trim();
    if (!phoneId) {
        openResponseModal({ status: 'N/A', url: '-', body: 'Please enter WhatsApp Phone Number ID first.' });
        return;
    }
    const url = `${CREDENTIALS_REFRESH_BASE_URL}?platform=whatsapp&whatsapp_phone_id=${encodeURIComponent(phoneId)}`;
    fetch(url)
        .then(async r => {
            const status = r.status + (r.ok ? ' OK' : ' ERROR');
            const text = await r.text();
            let json;
            try {
                json = JSON.parse(text);
            } catch (e) {
                json = null;
            }
            return { status, url, body: json ? JSON.stringify(json, null, 2) : text };
        })
        .then(payload => openResponseModal(payload))
        .catch(err => openResponseModal({ status: 'FAILED', url, body: String(err) }));
};

window.refreshFacebookCredentials = function() {
    const pageId = (document.getElementById('facebookPageId').value || '').trim();
    if (!pageId) {
        openResponseModal({ status: 'N/A', url: '-', body: 'Please enter Facebook Page ID first.' });
        return;
    }
    const url = `${CREDENTIALS_REFRESH_BASE_URL}?platform=facebook_messenger&facebook_page_id=${encodeURIComponent(pageId)}`;
    fetch(url)
        .then(async r => {
            const status = r.status + (r.ok ? ' OK' : ' ERROR');
            const text = await r.text();
            let json;
            try {
                json = JSON.parse(text);
            } catch (e) {
                json = null;
            }
            return { status, url, body: json ? JSON.stringify(json, null, 2) : text };
        })
        .then(payload => openResponseModal(payload))
        .catch(err => openResponseModal({ status: 'FAILED', url, body: String(err) }));
};

function openResponseModal({ status, url, body }) {
    document.getElementById('respStatus').textContent = status;
    document.getElementById('respUrl').textContent = url;
    document.getElementById('respBody').value = body;
    document.getElementById('responseModal').style.display = 'block';
}

window.closeResponseModal = function() {
    document.getElementById('responseModal').style.display = 'none';
};

window.copyResponseBody = function() {
    const ta = document.getElementById('respBody');
    ta.select();
    ta.setSelectionRange(0, ta.value.length);
    try {
        document.execCommand('copy');
    } catch (e) {}
};


// Replace the lazy loading wrapper with the real function after loading
window.openCommunicationSetupModal = window.openCommunicationSetupModalReal;

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('communicationSetupModal');
    if (event.target === modal) {
        closeCommunicationSetupModal();
    }
});