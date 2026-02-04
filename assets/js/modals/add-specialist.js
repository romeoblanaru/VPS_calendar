// Add Specialist Modal Functions
// This file contains all Add Specialist modal functionality

// Store the real implementation
window.openAddSpecialistModalReal = function(workpointId, organisationId) {
    const modal = document.getElementById('addSpecialistModal');
    if (!modal) {
        return;
    }

    modal.style.display = 'flex';
    document.getElementById('workpointId').value = workpointId;
    document.getElementById('organisationId').value = organisationId;

    // Set workpoint info if provided
    if (workpointId) {
        document.getElementById('workpointSelect').style.display = 'none';
        document.getElementById('workpointLabel').style.display = 'none';

        // Get workpoint details
        fetch('admin/get_working_point_details.php?workpoint_id=' + workpointId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('workingScheduleTitle').textContent = '📋 Working Schedule at ' + data.workpoint.name_of_the_place + ' (' + data.workpoint.address + ')';
                }
            })
            .catch(error => {
                // Handle error silently
            });

        // Load available specialists for this organisation and workpoint
        window.loadAvailableSpecialists(organisationId, workpointId);
    } else {
        document.getElementById('workpointSelect').style.display = 'block';
        document.getElementById('workpointLabel').style.display = 'block';
        document.getElementById('workpointLabel').textContent = 'Assign to Working Point *';

        // Load working points for this organisation
        window.loadWorkingPointsForOrganisation(organisationId);
    }

    // Load schedule editor
    window.loadScheduleEditor();
};

window.closeAddSpecialistModal = function() {
    document.getElementById('addSpecialistModal').style.display = 'none';
    document.getElementById('addSpecialistForm').reset();
    document.getElementById('scheduleEditorTableBody').innerHTML = '';
};

// Close modal when clicking outside - need to be careful with this to not conflict
if (!window.addSpecialistModalClickHandlerSet) {
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('addSpecialistModal');
        if (event.target === modal) {
            window.closeAddSpecialistModal();
        }
    });
    window.addSpecialistModalClickHandlerSet = true;
}

window.loadWorkingPointsForOrganisation = function(organisationId) {
    fetch('admin/get_working_points.php?organisation_id=' + organisationId)
        .then(response => response.json())
        .then(data => {
            const workpointSelect = document.getElementById('workpointSelect');
            workpointSelect.innerHTML = '<option value="">Select a working point...</option>';

            data.forEach(wp => {
                const option = document.createElement('option');
                option.value = wp.unic_id;
                option.textContent = wp.name_of_the_place + ' - ' + wp.address;
                workpointSelect.appendChild(option);
            });
        })
        .catch(error => {
            // Handle error silently
        });
};

window.loadScheduleEditor = function() {
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const tableBody = document.getElementById('scheduleEditorTableBody');
    tableBody.innerHTML = '';

    days.forEach(day => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="day-name">${day}</td>
            <td><input type="time" class="shift1-start-time" name="shift1_start_${day.toLowerCase()}" value=""></td>
            <td><input type="time" class="shift1-end-time" name="shift1_end_${day.toLowerCase()}" value=""></td>
            <td><button type="button" class="btn-clear-shift" onclick="clearShift(this, 1)">Clear</button></td>
            <td><input type="time" class="shift2-start-time" name="shift2_start_${day.toLowerCase()}" value=""></td>
            <td><input type="time" class="shift2-end-time" name="shift2_end_${day.toLowerCase()}" value=""></td>
            <td><button type="button" class="btn-clear-shift" onclick="clearShift(this, 2)">Clear</button></td>
            <td><input type="time" class="shift3-start-time" name="shift3_start_${day.toLowerCase()}" value=""></td>
            <td><input type="time" class="shift3-end-time" name="shift3_end_${day.toLowerCase()}" value=""></td>
            <td><button type="button" class="btn-clear-shift" onclick="clearShift(this, 3)">Clear</button></td>
        `;
        tableBody.appendChild(row);
    });
};

window.loadAvailableSpecialists = function(organisationId, workpointId) {
    fetch(`admin/get_available_specialists.php?organisation_id=${organisationId}&workpoint_id=${workpointId}`)
        .then(response => response.json())
        .then(data => {
            const specialistSelect = document.getElementById('specialistSelection');

            // Clear existing options except the first two
            while (specialistSelect.children.length > 2) {
                specialistSelect.removeChild(specialistSelect.lastChild);
            }

            if (data.success && data.specialists.length > 0) {
                data.specialists.forEach(specialist => {
                    const option = document.createElement('option');
                    option.value = specialist.unic_id;
                    option.textContent = `${specialist.name} - ${specialist.speciality}`;
                    specialistSelect.appendChild(option);
                });
            } else {
                // Add a message if no specialists available
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No available specialists found';
                option.disabled = true;
                specialistSelect.appendChild(option);
            }
        })
        .catch(error => {
            console.error('Error loading specialists:', error);
        });
};

window.onSelectUnassignedSpecialist = function(specialistId) {
    if (!specialistId) return;
    // Open modify schedule modal for selected orphan specialist targeting current workpoint
    // Use the current workpoint ID from window object (set by PHP in main page)
    const currentWorkpointId = window.currentWorkpointId || 0;
    window.openModifyScheduleModal(specialistId, currentWorkpointId);
    // Reset selection so it can be re-used
    const sel = document.getElementById('unassignedSpecialistsSelect');
    if (sel) sel.value = '';
};

window.handleSpecialistSelectionReal = function() {
    console.log('=== handleSpecialistSelectionReal START ===');

    const specialistSelect = document.getElementById('specialistSelection');
    if (!specialistSelect) {
        console.error('specialistSelection element not found!');
        return;
    }

    const selectedValue = specialistSelect.value;
    console.log('Selected value:', selectedValue);
    console.log('Selected text:', specialistSelect.options[specialistSelect.selectedIndex]?.text);

    if (selectedValue === 'new') {
        console.log('New specialist mode selected');
        // New specialist mode - enable all fields
        window.enableFormFields();
        window.clearFormFields();
    } else if (selectedValue && selectedValue !== '') {
        console.log('Existing specialist selected, loading data for ID:', selectedValue);
        // Existing specialist mode - load data THEN make fields read-only
        // Note: Moving disableFormFields inside loadSpecialistData callback
        window.loadSpecialistData(selectedValue, true); // Pass flag to disable fields after loading
    } else {
        console.log('No selection or empty value');
    }

    console.log('=== handleSpecialistSelectionReal END ===');
};

// Also set the wrapper to use the real function
window.handleSpecialistSelection = window.handleSpecialistSelectionReal;

window.enableFormFields = function() {
    console.log('enableFormFields called');
    const fields = ['specialistName', 'specialistSpeciality', 'specialistEmail', 'specialistPhone', 'specialistUser', 'specialistPassword', 'emailScheduleHour', 'emailScheduleMinute'];
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.readOnly = false;
            // field.disabled = false; // Not using disabled
            field.style.backgroundColor = '#fff';
            console.log(`Field ${fieldId} enabled`);
        } else {
            console.warn('Field not found when enabling:', fieldId);
        }
    });
};

window.disableFormFields = function() {
    console.log('disableFormFields called');
    const fields = ['specialistName', 'specialistSpeciality', 'specialistEmail', 'specialistPhone', 'specialistUser', 'specialistPassword', 'emailScheduleHour', 'emailScheduleMinute'];
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            // Use readOnly instead of disabled to allow value changes
            field.readOnly = true;
            // Don't use disabled as it prevents value changes
            // field.disabled = true;
            field.style.backgroundColor = '#f8f9fa';
            console.log(`Field ${fieldId} set to readOnly`);
        } else {
            console.warn('Field not found when disabling:', fieldId);
        }
    });
};

window.clearFormFields = function() {
    const fields = ['specialistName', 'specialistSpeciality', 'specialistEmail', 'specialistPhone', 'specialistUser', 'specialistPassword', 'emailScheduleHour', 'emailScheduleMinute'];
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.value = '';
        }
    });
    // Reset to default values
    document.getElementById('emailScheduleHour').value = '9';
    document.getElementById('emailScheduleMinute').value = '0';
};

window.loadSpecialistData = function(specialistId, disableFieldsAfter = false) {
    console.log('=== loadSpecialistData START ===');
    console.log('Loading specialist data for ID:', specialistId);
    console.log('Disable fields after loading:', disableFieldsAfter);

    // First check if the form fields exist
    const testFields = {
        name: document.getElementById('specialistName'),
        speciality: document.getElementById('specialistSpeciality'),
        email: document.getElementById('specialistEmail'),
        phone: document.getElementById('specialistPhone'),
        user: document.getElementById('specialistUser'),
        password: document.getElementById('specialistPassword')
    };

    console.log('Form fields check before API call:', {
        name: !!testFields.name,
        speciality: !!testFields.speciality,
        email: !!testFields.email,
        phone: !!testFields.phone,
        user: !!testFields.user,
        password: !!testFields.password
    });

    const formData = new FormData();
    formData.append('specialist_id', specialistId);

    fetch('admin/get_specialist_data.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers.get('content-type'));

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return response.text(); // First get as text to debug
        })
        .then(text => {
            console.log('Raw response:', text);

            try {
                const data = JSON.parse(text);
                console.log('Parsed data:', data);

                if (data.success) {
                    const specialist = data.specialist;
                    console.log('Specialist data:', specialist);

                    // Populate form fields
                    const nameField = document.getElementById('specialistName');
                    const specialityField = document.getElementById('specialistSpeciality');
                    const emailField = document.getElementById('specialistEmail');
                    const phoneField = document.getElementById('specialistPhone');
                    const userField = document.getElementById('specialistUser');
                    const passwordField = document.getElementById('specialistPassword');
                    const hourField = document.getElementById('emailScheduleHour');
                    const minuteField = document.getElementById('emailScheduleMinute');

                    console.log('Form fields found after API response:', {
                        name: !!nameField,
                        speciality: !!specialityField,
                        email: !!emailField,
                        phone: !!phoneField,
                        user: !!userField,
                        password: !!passwordField,
                        hour: !!hourField,
                        minute: !!minuteField
                    });

                    if (nameField) {
                        console.log('Setting name from:', nameField.value, 'to:', specialist.name);
                        nameField.value = specialist.name || '';
                        console.log('Name field after setting:', nameField.value);
                    }
                    if (specialityField) {
                        console.log('Setting speciality from:', specialityField.value, 'to:', specialist.speciality);
                        specialityField.value = specialist.speciality || '';
                        console.log('Speciality field after setting:', specialityField.value);
                    }
                    if (emailField) {
                        console.log('Setting email from:', emailField.value, 'to:', specialist.email);
                        emailField.value = specialist.email || '';
                        console.log('Email field after setting:', emailField.value);
                    }
                    if (phoneField) {
                        phoneField.value = specialist.phone_nr || '';
                        console.log('Phone field set to:', phoneField.value);
                    }
                    if (userField) {
                        userField.value = specialist.user || '';
                        console.log('User field set to:', userField.value);
                    }
                    if (passwordField) {
                        passwordField.value = ''; // Don't populate password
                        console.log('Password field cleared');
                    }
                    if (hourField) hourField.value = specialist.h_of_email_schedule || '9';
                    if (minuteField) minuteField.value = specialist.m_of_email_schedule || '0';

                    console.log('=== All fields populated successfully ===');

                    // Disable fields after loading if requested
                    if (disableFieldsAfter) {
                        console.log('Disabling form fields after loading data...');
                        window.disableFormFields();
                    }
                } else {
                    console.error('API returned success:false -', data.message);
                    alert('Failed to load specialist data: ' + (data.message || 'Unknown error'));
                }
            } catch (parseError) {
                console.error('Failed to parse JSON:', parseError);
                console.error('Raw response was:', text);
                alert('Error parsing server response. Check console for details.');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Failed to load specialist data. Check console for details.');
        });

    console.log('=== loadSpecialistData END (async fetch initiated) ===');
};

window.clearShift = function(button, shiftNum) {
    const row = button.closest('tr');
    const startInput = row.querySelector(`.shift${shiftNum}-start-time`);
    const endInput = row.querySelector(`.shift${shiftNum}-end-time`);
    startInput.value = '';
    endInput.value = '';
};

window.applyAllShifts = function() {
    const dayRange = document.getElementById('quickOptionsDaySelect').value;
    let days;

    switch(dayRange) {
        case 'mondayToFriday':
            days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
            break;
        case 'saturday':
            days = ['saturday'];
            break;
        case 'sunday':
            days = ['sunday'];
            break;
        default:
            return;
    }

    // Get shift times
    const shift1Start = document.getElementById('shift1Start').value;
    const shift1End = document.getElementById('shift1End').value;
    const shift2Start = document.getElementById('shift2Start').value;
    const shift2End = document.getElementById('shift2End').value;
    const shift3Start = document.getElementById('shift3Start').value;
    const shift3End = document.getElementById('shift3End').value;

    // Check if at least one shift has values
    const hasShift1 = shift1Start && shift1End;
    const hasShift2 = shift2Start && shift2End;
    const hasShift3 = shift3Start && shift3End;

    if (!hasShift1 && !hasShift2 && !hasShift3) {
        return;
    }

    // Apply shifts to all selected days
    days.forEach(day => {
        const row = document.querySelector(`tr:has(input[name="shift1_start_${day}"])`);
        if (row) {
            // Apply Shift 1 only if values are provided
            if (hasShift1) {
                const startInput = row.querySelector('.shift1-start-time');
                const endInput = row.querySelector('.shift1-end-time');
                if (startInput && endInput) {
                    startInput.value = shift1Start;
                    endInput.value = shift1End;
                }
            }

            // Apply Shift 2 only if values are provided
            if (hasShift2) {
                const startInput = row.querySelector('.shift2-start-time');
                const endInput = row.querySelector('.shift2-end-time');
                if (startInput && endInput) {
                    startInput.value = shift2Start;
                    endInput.value = shift2End;
                }
            }

            // Apply Shift 3 only if values are provided
            if (hasShift3) {
                const startInput = row.querySelector('.shift3-start-time');
                const endInput = row.querySelector('.shift3-end-time');
                if (startInput && endInput) {
                    startInput.value = shift3Start;
                    endInput.value = shift3End;
                }
            }
        }
    });
};

window.submitAddSpecialist = function() {
    const formData = new FormData(document.getElementById('addSpecialistForm'));
    const specialistSelection = document.getElementById('specialistSelection').value;

    // Get workpoint_id and organisation_id
    let workpointId = document.getElementById('workpointId').value;
    const organisationId = document.getElementById('organisationId').value;

    // If hidden field is empty, try to get from select dropdown
    if (!workpointId) {
        workpointId = document.getElementById('workpointSelect').value;
    }

    // Ensure both IDs are included
    if (workpointId) {
        formData.append('workpoint_id', workpointId);
        formData.append('working_points[]', workpointId);
    }

    if (organisationId) {
        formData.append('organisation_id', organisationId);
    }

    // Add schedule data
    const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    days.forEach(day => {
        for (let shift = 1; shift <= 3; shift++) {
            const startInput = document.querySelector(`input[name="shift${shift}_start_${day}"]`);
            const endInput = document.querySelector(`input[name="shift${shift}_end_${day}"]`);
            if (startInput && endInput) {
                formData.append(`schedule[${day}][shift${shift}_start]`, startInput.value || '');
                formData.append(`schedule[${day}][shift${shift}_end]`, endInput.value || '');
            }
        }
    });

    // Determine action based on specialist selection
    if (specialistSelection === 'new') {
        // New specialist - use existing add_specialist_ajax.php
        formData.append('action', 'add_new_specialist');
        window.submitToAddSpecialist(formData);
    } else {
        // Existing specialist - use new reactivate_specialist_ajax.php
        formData.append('action', 'reactivate_specialist');
        formData.append('specialist_id', specialistSelection);
        window.submitToReactivateSpecialist(formData);
    }
};

window.submitToAddSpecialist = function(formData) {
    fetch('admin/add_specialist_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Specialist added successfully!');
            window.closeAddSpecialistModal();
            // Reload the page to show the new specialist
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('An error occurred while adding the specialist.');
    });
};

window.submitToReactivateSpecialist = function(formData) {
    // Get the required IDs from hidden fields
    const workpointId = document.getElementById('workpointId').value;
    const organisationId = document.getElementById('organisationId').value;

    // Add the required IDs to form data
    formData.append('workpoint_id', workpointId);
    formData.append('organisation_id', organisationId);

    fetch('admin/reactivate_specialist_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Specialist reactivated successfully!');
            window.closeAddSpecialistModal();
            // Reload the page to show the reactivated specialist
            location.reload();
        } else {
            alert('Error: ' + (data.message || data.error));
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('An error occurred while reactivating the specialist.');
    });
};

// Replace the lazy loading wrapper with the real function after loading
window.openAddSpecialistModal = window.openAddSpecialistModalReal;

// Initialize on load
console.log('Add Specialist Modal loaded successfully');