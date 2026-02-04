// Modify Specialist Modal Functions
// This file contains all Modify Specialist modal functionality

// Store the real implementation
window.openModifySpecialistModalReal = function(specialistId, specialistName, workpointId) {
    const modal = document.getElementById('modifySpecialistModal');
    if (!modal) {
        console.error('Modify specialist modal not found!');
        return;
    }

    // Update modal title with specialist ID
    const modalTitle = modal.querySelector('.modify-modal-header h3');
    if (modalTitle) {
        modalTitle.innerHTML = `👥 Modify Specialist Details [${specialistId}]`;
    }

    // Set modal IDs
    const specialistIdField = document.getElementById('modifySpecialistId');
    const workpointIdField = document.getElementById('modifyWorkpointId');

    if (specialistIdField) specialistIdField.value = specialistId;
    if (workpointIdField) workpointIdField.value = workpointId;

    // Clear previous error messages
    const errorElement = document.getElementById('modifySpecialistError');
    if (errorElement) errorElement.style.display = 'none';

    // Load specialist data
    window.loadSpecialistDataForModal(specialistId);

    // Show modal
    modal.style.display = 'flex';
};

window.closeModifySpecialistModal = function() {
    document.getElementById('modifySpecialistModal').style.display = 'none';
    document.getElementById('modifySpecialistForm').reset();
    document.getElementById('modifySpecialistError').style.display = 'none';
};

window.loadSpecialistDataForModal = function(specialistId) {
    const formData = new FormData();
    formData.append('action', 'get_specialist_data');
    formData.append('specialist_id', specialistId);

    fetch('admin/modify_specialist_details.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const specialist = data.specialist;

            // Populate form fields with proper field mapping
            const nameField = document.getElementById('modifySpecialistNameField');
            const specialityField = document.getElementById('modifySpecialistSpeciality');
            const emailField = document.getElementById('modifySpecialistEmail');
            const phoneField = document.getElementById('modifySpecialistPhone');
            const userField = document.getElementById('modifySpecialistUser');
            const passwordField = document.getElementById('modifySpecialistPassword');
            const hourField = document.getElementById('modifySpecialistEmailHour');
            const minuteField = document.getElementById('modifySpecialistEmailMinute');

            if (nameField) nameField.value = specialist.name || '';
            if (specialityField) specialityField.value = specialist.speciality || '';
            if (emailField) emailField.value = specialist.email || '';
            if (phoneField) phoneField.value = specialist.phone_nr || '';
            if (userField) userField.value = specialist.user || '';
            if (passwordField) passwordField.value = ''; // Don't populate password for security
            if (hourField) hourField.value = specialist.h_of_email_schedule || '9';
            if (minuteField) minuteField.value = specialist.m_of_email_schedule || '0';

            // Show/hide email schedule fields based on daily_email_enabled
            const emailScheduleContainer = document.getElementById('emailScheduleContainer');
            if (emailScheduleContainer) {
                if (specialist.daily_email_enabled && specialist.daily_email_enabled != 0 && specialist.daily_email_enabled != null) {
                    emailScheduleContainer.style.display = 'flex';
                } else {
                    emailScheduleContainer.style.display = 'none';
                }
            }

            // Load services for this specialist
            window.loadSpecialistServicesForModal(specialistId);
        } else {
            console.error('Failed to load specialist data:', data.message);
            document.getElementById('modifySpecialistError').textContent = 'Failed to load specialist data: ' + data.message;
            document.getElementById('modifySpecialistError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error loading specialist data:', error);
        document.getElementById('modifySpecialistError').textContent = 'An error occurred while loading specialist data.';
        document.getElementById('modifySpecialistError').style.display = 'block';
    });
};

window.loadSpecialistServicesForModal = function(specialistId) {
    const servicesDisplay = document.getElementById('modifySpecialistServicesDisplay');
    if (!servicesDisplay) return;

    // Show loading
    servicesDisplay.innerHTML = '<div style="text-align: center; color: #6c757d;"><i class="fas fa-spinner fa-spin"></i> Loading services...</div>';

    // Fetch services from database with cache buster
    const timestamp = new Date().getTime();
    fetch(`admin/get_specialist_services.php?specialist_id=${specialistId}&t=${timestamp}`)
        .then(response => response.json())
        .then(data => {

            if (data.success && data.services.length > 0) {
                let servicesHTML = '<div style="max-height: 300px; overflow-y: auto;">';

                data.services.forEach(service => {
                    const priceWithVat = service.price_of_service * (1 + service.procent_vat / 100);
                    const isSuspended = service.suspended == 1;
                    const serviceColor = isSuspended ? '#6c757d' : '#495057';

                    servicesHTML += `
                        <div class="service-item" style="padding: 8px 12px; margin-bottom: 4px; background: #f8f9fa; border-radius: 4px; font-size: 13px; transition: background-color 0.2s; cursor: pointer;"
                             onmouseover="this.style.backgroundColor='#e9ecef'"
                             onmouseout="this.style.backgroundColor='#f8f9fa'"
                             onclick="window.serviceReturnModal = 'modifySpecialist'; window.serviceReturnSpecialistId = '${specialistId}'; editSpecialistService(${service.unic_id}, '${service.name_of_service.replace(/'/g, "\\'")}', ${service.duration}, ${service.price_of_service}, ${service.procent_vat})">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="flex: 1;">
                                    <span style="color: ${serviceColor}; ${isSuspended ? 'opacity: 0.6;' : ''}">${service.name_of_service}</span>
                                    ${isSuspended ? '<span style="font-size: 11px; color: #dc3545; margin-left: 8px;">(Suspended)</span>' : ''}
                                    <div style="font-size: 11px; color: #6c757d; line-height: 1.2; margin-top: 2px;">
                                        ${service.duration} min | ${priceWithVat.toFixed(2)}€
                                        ${service.procent_vat > 0 ? `<span style="font-size: 10px;">(incl. ${service.procent_vat}% VAT)</span>` : ''}
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="border: 1px solid #868e96; color: #868e96; padding: 1px 5px; border-radius: 4px; font-size: 11px; display: inline-block; min-width: 20px; text-align: center;"
                                          title="Past bookings (last 30 days)">
                                        ${service.past_bookings || 0}
                                    </span>
                                    <span style="border: 1px solid ${service.active_bookings > 0 ? '#28a745' : '#868e96'}; color: ${service.active_bookings > 0 ? '#28a745' : '#868e96'}; padding: 1px 5px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block; min-width: 20px; text-align: center;"
                                          title="Active/Future bookings">
                                        ${service.active_bookings || 0}
                                    </span>
                                </div>
                            </div>
                        </div>
                    `;
                });

                servicesHTML += '</div>';
                servicesDisplay.innerHTML = servicesHTML;
            } else {
                servicesDisplay.innerHTML = '<div style="text-align: center; color: #6c757d; padding: 20px;"><em>No services assigned yet.</em></div>';
            }
        })
        .catch(error => {
            console.error('Error loading services:', error);
            servicesDisplay.innerHTML = '<div style="text-align: center; color: #dc3545; padding: 20px;">Error loading services</div>';
        });
};

window.addNewService = function() {
    const specialistId = document.getElementById('modifySpecialistId').value;
    if (specialistId) {
        // Mark that we're coming from Modify Specialist modal
        window.serviceReturnModal = 'modifySpecialist';
        window.serviceReturnSpecialistId = specialistId;
        window.openAddServiceModalForSpecialist(specialistId);
    }
};

window.loadSpecialistScheduleForModal = function(specialistId) {
    const workpointId = document.getElementById('modifyWorkpointId').value;

    const formData = new FormData();
    formData.append('action', 'get_schedule');
    formData.append('specialist_id', specialistId);
    formData.append('workpoint_id', workpointId);

    fetch('admin/modify_schedule_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const schedule = data.schedule;
            const scheduleDisplay = document.getElementById('modifySpecialistScheduleDisplay');

            if (schedule && schedule.length > 0) {
                // Create schedule display similar to the working schedule in the sidebar
                const dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                const dayNames = {
                    'monday': 'Mon',
                    'tuesday': 'Tue',
                    'wednesday': 'Wed',
                    'thursday': 'Thu',
                    'friday': 'Fri',
                    'saturday': 'Sat',
                    'sunday': 'Sun'
                };

                const scheduleLookup = {};
                schedule.forEach(item => {
                    const day = item.day_of_week.toLowerCase();
                    const shifts = [];

                    // Check shift 1
                    if (item.shift1_start && item.shift1_end && item.shift1_start !== '00:00:00' && item.shift1_end !== '00:00:00') {
                        const start1 = item.shift1_start.substring(0, 5);
                        const end1 = item.shift1_end.substring(0, 5);
                        shifts.push(`<span style="background-color: #ffebee; color: #d32f2f; padding: 2px 6px; border-radius: 3px; margin: 0 2px;">${start1} - ${end1}</span>`);
                    }

                    // Check shift 2
                    if (item.shift2_start && item.shift2_end && item.shift2_start !== '00:00:00' && item.shift2_end !== '00:00:00') {
                        const start2 = item.shift2_start.substring(0, 5);
                        const end2 = item.shift2_end.substring(0, 5);
                        shifts.push(`<span style="background-color: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 3px; margin: 0 2px;">${start2} - ${end2}</span>`);
                    }

                    // Check shift 3
                    if (item.shift3_start && item.shift3_end && item.shift3_start !== '00:00:00' && item.shift3_end !== '00:00:00') {
                        const start3 = item.shift3_start.substring(0, 5);
                        const end3 = item.shift3_end.substring(0, 5);
                        shifts.push(`<span style="background-color: #e8f5e8; color: #2e7d32; padding: 2px 6px; border-radius: 3px; margin: 0 2px;">${start3} - ${end3}</span>`);
                    }

                    if (shifts.length > 0) {
                        scheduleLookup[day] = shifts.join(' ');
                    }
                });

                const scheduleLines = [];
                dayOrder.forEach(day => {
                    if (scheduleLookup[day]) {
                        const dayName = dayNames[day];
                        scheduleLines.push(`<div style="margin-bottom: 5px;"><strong style="display: inline-block; width: 35px; text-align: left;">${dayName}:</strong> ${scheduleLookup[day]}</div>`);
                    }
                });

                scheduleDisplay.innerHTML = scheduleLines.join('');
            } else {
                scheduleDisplay.innerHTML = '<em style="color: #6c757d;">No schedule found for this specialist at this working point.</em>';
            }
        } else {
            document.getElementById('modifySpecialistScheduleDisplay').innerHTML = '<em style="color: #dc3545;">Failed to load schedule data.</em>';
        }
    })
    .catch(error => {
        console.error('Error loading schedule:', error);
        document.getElementById('modifySpecialistScheduleDisplay').innerHTML = '<em style="color: #dc3545;">Error loading schedule data.</em>';
    });
};

window.deleteSpecialistSchedule = function() {
    const specialistId = document.getElementById('modifySpecialistId').value;
    const workpointId = document.getElementById('modifyWorkpointId').value;

    if (!confirm('Are you sure you want to delete the schedule for this specialist at this working point? This action cannot be undone.')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_schedule');
    formData.append('specialist_id', specialistId);
    formData.append('workpoint_id', workpointId);

    fetch('admin/modify_schedule_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reflect changes in left panel and orphan dropdown
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete schedule.'));
        }
    })
    .catch(error => {
        console.error('Error deleting schedule:', error);
        alert('An error occurred while deleting the schedule.');
    });
};

window.updateSpecialistDetails = function() {
    const formData = new FormData(document.getElementById('modifySpecialistForm'));
    formData.append('action', 'update_specialist');

    // Disable submit button
    const submitBtn = event.target;
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = 'Updating...';
    submitBtn.disabled = true;

    fetch('admin/modify_specialist_details.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Specialist updated successfully!');
            window.closeModifySpecialistModal();
            // Reload the page to show updated data
            location.reload();
        } else {
            document.getElementById('modifySpecialistError').textContent = data.message || 'Failed to update specialist.';
            document.getElementById('modifySpecialistError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error updating specialist:', error);
        document.getElementById('modifySpecialistError').textContent = 'An error occurred while updating the specialist.';
        document.getElementById('modifySpecialistError').style.display = 'block';
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
};

window.deleteSpecialistFromModal = function() {
    const specialistId = document.getElementById('modifySpecialistId').value;
    const specialistName = document.getElementById('modifySpecialistNameField').value;

    // Show delete confirmation modal
    document.getElementById('deleteSpecialistConfirmName').textContent = specialistName;
    document.getElementById('deleteSpecialistConfirmModal').style.display = 'flex';
    document.getElementById('deleteSpecialistConfirmError').style.display = 'none';
    document.getElementById('deleteSpecialistConfirmPassword').value = '';
};

window.closeDeleteSpecialistConfirmModal = function() {
    document.getElementById('deleteSpecialistConfirmModal').style.display = 'none';
    document.getElementById('deleteSpecialistConfirmForm').reset();
    document.getElementById('deleteSpecialistConfirmError').style.display = 'none';
};

window.confirmDeleteSpecialistFromModal = function() {
    const specialistId = document.getElementById('modifySpecialistId').value;
    const password = document.getElementById('deleteSpecialistConfirmPassword').value;

    if (!password) {
        document.getElementById('deleteSpecialistConfirmError').textContent = 'Please enter your password to confirm deletion.';
        document.getElementById('deleteSpecialistConfirmError').style.display = 'block';
        return;
    }

    const btn = document.getElementById('confirmDeleteSpecialistBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = 'Deleting...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'delete_specialist');
    formData.append('specialist_id', specialistId);
    formData.append('password', password);

    fetch('admin/modify_specialist_details.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Specialist deleted successfully!');
            window.closeDeleteSpecialistConfirmModal();
            window.closeModifySpecialistModal();
            // Reload the page to reflect changes
            location.reload();
        } else {
            document.getElementById('deleteSpecialistConfirmError').textContent = data.message || 'Failed to delete specialist.';
            document.getElementById('deleteSpecialistConfirmError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error deleting specialist:', error);
        document.getElementById('deleteSpecialistConfirmError').textContent = 'An error occurred while deleting the specialist.';
        document.getElementById('deleteSpecialistConfirmError').style.display = 'block';
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
};

window.modifySpecialistSchedule = function() {
    const specialistId = document.getElementById('modifySpecialistId').value;
    const workpointId = document.getElementById('modifyWorkpointId').value;

    // Close the modify specialist modal first
    window.closeModifySpecialistModal();

    // Open the schedule modification modal
    window.openModifyScheduleModal(specialistId, workpointId);
};

// Replace the lazy loading wrapper with the real function after loading
window.openModifySpecialistModal = window.openModifySpecialistModalReal;

// Initialize on load
console.log('Modify Specialist Modal loaded successfully');