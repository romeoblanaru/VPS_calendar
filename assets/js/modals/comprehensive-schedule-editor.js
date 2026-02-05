// Comprehensive Schedule Editor Modal Functions
// This file contains all functionality for the schedule editor modal

// Store the real implementation
window.openModifyScheduleModalReal = function(specialistId, workpointId) {
    const modal = document.getElementById('modifyScheduleModal');
    if (!modal) {
        console.error('Modify schedule modal not found!');
        return;
    }

    console.log('Opening modal with params:', { specialistId, workpointId });

    // Store parameters globally for other functions to access
    window.currentScheduleSpecialistId = specialistId;
    window.currentScheduleWorkpointId = workpointId;

    // Set modal IDs
    document.getElementById('modifyScheduleSpecialistId').value = specialistId;
    document.getElementById('modifyScheduleWorkpointId').value = workpointId;

    // Clear previous error messages
    const errorElement = document.getElementById('modifyScheduleError');
    if (errorElement) errorElement.style.display = 'none';

    // Always reinitialize the table to ensure clean state
    loadModifyScheduleEditor();

    // Load schedule data after a small delay to ensure table is ready
    setTimeout(() => {
        loadScheduleDataForModal(specialistId, workpointId);
    }, 100);

    // Show modal
    modal.style.display = 'flex';
};

// Close modal function
window.closeModifyScheduleModal = function() {
    document.getElementById('modifyScheduleModal').style.display = 'none';
    document.getElementById('modifyScheduleForm').reset();
    const errorElement = document.getElementById('modifyScheduleError');
    if (errorElement) errorElement.style.display = 'none';
};

// Toggle shift visibility function
window.toggleShiftVisibility = function(shiftNumber, isVisible) {
    const table = document.querySelector('#modifyScheduleModal .schedule-editor-table');
    if (!table) return;

    const display = isVisible ? '' : 'none';
    const flexDisplay = isVisible ? 'flex' : 'none';

    // Toggle Quick Options section shifts
    if (shiftNumber === 1) {
        const quickOptionsShift1 = document.getElementById('quickOptionsShift1');
        if (quickOptionsShift1) quickOptionsShift1.style.display = flexDisplay;
    } else if (shiftNumber === 2) {
        const quickOptionsShift2 = document.getElementById('quickOptionsShift2');
        if (quickOptionsShift2) quickOptionsShift2.style.display = flexDisplay;
    } else if (shiftNumber === 3) {
        const quickOptionsShift3 = document.getElementById('quickOptionsShift3');
        if (quickOptionsShift3) quickOptionsShift3.style.display = flexDisplay;
    }

    // Toggle header columns
    const headerRows = table.querySelectorAll('thead tr');
    if (shiftNumber === 1) {
        // First header row - Shift 1 title (columns 2-4)
        if (headerRows[0]) {
            const shift1Header = headerRows[0].cells[1];
            if (shift1Header) shift1Header.style.display = display;
        }
        // Second header row - Start/End/checkbox columns (2-4)
        if (headerRows[1]) {
            for (let i = 1; i <= 3; i++) {
                if (headerRows[1].cells[i]) headerRows[1].cells[i].style.display = display;
            }
        }
        // Separator column after shift 1
        if (headerRows[0].cells[2]) headerRows[0].cells[2].style.display = display;
        if (headerRows[1].cells[4]) headerRows[1].cells[4].style.display = display;
    } else if (shiftNumber === 2) {
        // First header row - Shift 2 title (columns 6-8)
        if (headerRows[0]) {
            const shift2Header = headerRows[0].cells[3];
            if (shift2Header) shift2Header.style.display = display;
        }
        // Second header row - Start/End/checkbox columns (6-8)
        if (headerRows[1]) {
            for (let i = 5; i <= 7; i++) {
                if (headerRows[1].cells[i]) headerRows[1].cells[i].style.display = display;
            }
        }
        // Separator column after shift 2
        if (headerRows[0].cells[4]) headerRows[0].cells[4].style.display = display;
        if (headerRows[1].cells[8]) headerRows[1].cells[8].style.display = display;
    } else if (shiftNumber === 3) {
        // First header row - Shift 3 title (columns 10-12)
        if (headerRows[0]) {
            const shift3Header = headerRows[0].cells[5];
            if (shift3Header) shift3Header.style.display = display;
        }
        // Second header row - Start/End/checkbox columns (10-12)
        if (headerRows[1]) {
            for (let i = 9; i <= 11; i++) {
                if (headerRows[1].cells[i]) headerRows[1].cells[i].style.display = display;
            }
        }
    }

    // Toggle body columns for all days
    const bodyRows = table.querySelectorAll('tbody tr');
    bodyRows.forEach(row => {
        if (shiftNumber === 1) {
            // Shift 1 columns (2-4: start, end, clear)
            for (let i = 1; i <= 3; i++) {
                if (row.cells[i]) row.cells[i].style.display = display;
            }
            // Separator column after shift 1
            if (row.cells[4]) row.cells[4].style.display = display;
        } else if (shiftNumber === 2) {
            // Shift 2 columns (6-8: start, end, clear)
            for (let i = 5; i <= 7; i++) {
                if (row.cells[i]) row.cells[i].style.display = display;
            }
            // Separator column after shift 2
            if (row.cells[8]) row.cells[8].style.display = display;
        } else if (shiftNumber === 3) {
            // Shift 3 columns (10-12: start, end, clear)
            for (let i = 9; i <= 11; i++) {
                if (row.cells[i]) row.cells[i].style.display = display;
            }
        }
    });
};

// Delete schedule from modal
window.deleteScheduleFromModal = function() {
    const titleElement = document.getElementById('modifyScheduleTitle');
    let specialistName = 'this specialist';
    let workpointName = 'this location';

    // Try to extract names from the title
    if (titleElement) {
        const titleText = titleElement.textContent || titleElement.innerText;
        const editingMatch = titleText.match(/Editing: (.+?) at (.+)/);
        if (editingMatch) {
            specialistName = editingMatch[1];
            workpointName = editingMatch[2];
        }
    }

    if (confirm(`Are you sure you want to delete the schedule for ${specialistName} at ${workpointName}? This action cannot be undone.`)) {
        const formData = new FormData();
        formData.append('action', 'delete_schedule');
        formData.append('specialist_id', document.getElementById('modifyScheduleSpecialistId').value);
        formData.append('workpoint_id', document.getElementById('modifyScheduleWorkpointId').value);
        formData.append('supervisor_mode', 'true');

        fetch('admin/modify_schedule_ajax.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Schedule deleted successfully!');
                closeModifyScheduleModal();
                location.reload();
            } else {
                document.getElementById('modifyScheduleError').textContent = data.message || 'Failed to delete schedule.';
                document.getElementById('modifyScheduleError').style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error deleting schedule:', error);
            document.getElementById('modifyScheduleError').textContent = 'An error occurred while deleting the schedule.';
            document.getElementById('modifyScheduleError').style.display = 'block';
        });
    }
};

// Load schedule data for modal
window.loadScheduleDataForModal = function(specialistId, workpointId) {
    console.log('Loading schedule data for:', { specialistId, workpointId });

    const formData = new FormData();
    formData.append('action', 'get_schedule');
    formData.append('specialist_id', specialistId);
    formData.append('workpoint_id', workpointId);
    formData.append('supervisor_mode', 'true');

    fetch('admin/modify_schedule_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Schedule data received:', data);

        if (data.success) {
            const details = data.details;
            const schedule = data.schedule || [];

            // Update modal title with specialist and workpoint info
            const titleElement = document.getElementById('modifyScheduleTitle');
            if (titleElement) {
                titleElement.innerHTML = `<i class="fas fa-calendar-alt" style="margin-right: 10px;"></i>Comprehensive Schedule Editor<br><span style="font-size: 0.9rem; font-weight: 400; opacity: 0.9;">Editing: ${details.specialist_name} at ${details.workpoint_name}</span>`;
            }

            // Populate schedule form with current data
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

            // Create a lookup for existing schedule data
            const scheduleLookup = {};
            schedule.forEach(item => {
                const day = item.day_of_week.toLowerCase();
                scheduleLookup[day] = {
                    shift1_start: item.shift1_start,
                    shift1_end: item.shift1_end,
                    shift2_start: item.shift2_start,
                    shift2_end: item.shift2_end,
                    shift3_start: item.shift3_start,
                    shift3_end: item.shift3_end
                };
            });

            console.log('Schedule lookup:', scheduleLookup);

            // Populate form fields
            days.forEach(day => {
                const dayData = scheduleLookup[day] || {};

                // Format time values - convert from HH:MM:SS to HH:MM if needed
                const formatTime = (time) => {
                    if (!time || time === '00:00:00') return '';
                    // If it's already in HH:MM format, return as is
                    if (time.length === 5) return time;
                    // Convert HH:MM:SS to HH:MM
                    return time.substring(0, 5);
                };

                // Set shift 1 times
                const shift1Start = document.querySelector(`input[name="modify_shift1_start_${day}"]`);
                const shift1End = document.querySelector(`input[name="modify_shift1_end_${day}"]`);
                if (shift1Start) {
                    shift1Start.value = formatTime(dayData.shift1_start);
                    console.log(`Set ${day} shift1 start to: ${shift1Start.value}`);
                }
                if (shift1End) {
                    shift1End.value = formatTime(dayData.shift1_end);
                }

                // Set shift 2 times
                const shift2Start = document.querySelector(`input[name="modify_shift2_start_${day}"]`);
                const shift2End = document.querySelector(`input[name="modify_shift2_end_${day}"]`);
                if (shift2Start) {
                    shift2Start.value = formatTime(dayData.shift2_start);
                }
                if (shift2End) {
                    shift2End.value = formatTime(dayData.shift2_end);
                }

                // Set shift 3 times
                const shift3Start = document.querySelector(`input[name="modify_shift3_start_${day}"]`);
                const shift3End = document.querySelector(`input[name="modify_shift3_end_${day}"]`);
                if (shift3Start) {
                    shift3Start.value = formatTime(dayData.shift3_start);
                }
                if (shift3End) {
                    shift3End.value = formatTime(dayData.shift3_end);
                }
            });
        } else {
            console.error('Failed to load schedule data:', data.message);
            document.getElementById('modifyScheduleError').textContent = 'Failed to load schedule data: ' + data.message;
            document.getElementById('modifyScheduleError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error loading schedule data:', error);
        document.getElementById('modifyScheduleError').textContent = 'An error occurred while loading schedule data.';
        document.getElementById('modifyScheduleError').style.display = 'block';
    });
};

// Update schedule from modal
window.updateScheduleFromModal = function() {
    const formData = new FormData();
    formData.append('action', 'update_schedule');
    formData.append('specialist_id', document.getElementById('modifyScheduleSpecialistId').value);
    formData.append('workpoint_id', document.getElementById('modifyScheduleWorkpointId').value);
    formData.append('supervisor_mode', 'true');

    // Build schedule data structure
    const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    const scheduleData = {};

    days.forEach(day => {
        scheduleData[day] = {
            shift1_start: document.querySelector(`input[name="modify_shift1_start_${day}"]`)?.value || '',
            shift1_end: document.querySelector(`input[name="modify_shift1_end_${day}"]`)?.value || '',
            shift2_start: document.querySelector(`input[name="modify_shift2_start_${day}"]`)?.value || '',
            shift2_end: document.querySelector(`input[name="modify_shift2_end_${day}"]`)?.value || '',
            shift3_start: document.querySelector(`input[name="modify_shift3_start_${day}"]`)?.value || '',
            shift3_end: document.querySelector(`input[name="modify_shift3_end_${day}"]`)?.value || ''
        };
    });

    // Add schedule data as JSON string
    formData.append('schedule', JSON.stringify(scheduleData));

    // Disable submit button (get button directly from onclick context)
    const submitBtn = event.target;
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 3px; font-size: 10px;"></i>Updating...';
    submitBtn.disabled = true;

    fetch('admin/modify_schedule_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Schedule updated successfully!');
            closeModifyScheduleModal();
            // Reload the page to show updated data
            location.reload();
        } else {
            document.getElementById('modifyScheduleError').textContent = data.message || 'Failed to update schedule.';
            document.getElementById('modifyScheduleError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error updating schedule:', error);
        document.getElementById('modifyScheduleError').textContent = 'An error occurred while updating the schedule.';
        document.getElementById('modifyScheduleError').style.display = 'block';
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
};

// Clear shift function
window.clearModifyShift = function(button, shiftNum) {
    const row = button.closest('tr');
    const startInput = row.querySelector(`.modify-shift${shiftNum}-start-time`);
    const endInput = row.querySelector(`.modify-shift${shiftNum}-end-time`);
    if (startInput) startInput.value = '';
    if (endInput) endInput.value = '';
};

// Apply all shifts function
window.applyModifyAllShifts = function() {
    const dayRange = document.getElementById('modifyQuickOptionsDaySelect').value;
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
    const shift1Start = document.getElementById('modifyShift1Start').value;
    const shift1End = document.getElementById('modifyShift1End').value;
    const shift2Start = document.getElementById('modifyShift2Start').value;
    const shift2End = document.getElementById('modifyShift2End').value;
    const shift3Start = document.getElementById('modifyShift3Start').value;
    const shift3End = document.getElementById('modifyShift3End').value;

    // Check if at least one shift has values
    const hasShift1 = shift1Start && shift1End;
    const hasShift2 = shift2Start && shift2End;
    const hasShift3 = shift3Start && shift3End;

    if (!hasShift1 && !hasShift2 && !hasShift3) {
        return;
    }

    // Apply shifts to all selected days
    days.forEach(day => {
        // Apply Shift 1 only if values are provided
        if (hasShift1) {
            const startInput = document.querySelector(`input[name="modify_shift1_start_${day}"]`);
            const endInput = document.querySelector(`input[name="modify_shift1_end_${day}"]`);
            if (startInput && endInput) {
                startInput.value = shift1Start;
                endInput.value = shift1End;
            }
        }

        // Apply Shift 2 only if values are provided
        if (hasShift2) {
            const startInput = document.querySelector(`input[name="modify_shift2_start_${day}"]`);
            const endInput = document.querySelector(`input[name="modify_shift2_end_${day}"]`);
            if (startInput && endInput) {
                startInput.value = shift2Start;
                endInput.value = shift2End;
            }
        }

        // Apply Shift 3 only if values are provided
        if (hasShift3) {
            const startInput = document.querySelector(`input[name="modify_shift3_start_${day}"]`);
            const endInput = document.querySelector(`input[name="modify_shift3_end_${day}"]`);
            if (startInput && endInput) {
                startInput.value = shift3Start;
                endInput.value = shift3End;
            }
        }
    });
};

// Load modify schedule editor table
window.loadModifyScheduleEditor = function() {
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const tableBody = document.getElementById('modifyScheduleEditorTableBody');
    tableBody.innerHTML = '';

    days.forEach(day => {
        const dayLower = day.toLowerCase();
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="day-name">${day}</td>
            <td>
                <input type="time" class="form-control shift1-start-time modify-shift1-start-time" name="modify_shift1_start_${dayLower}" value="" placeholder="--:--" data-day="${day}" data-shift="1">
            </td>
            <td>
                <input type="time" class="form-control shift1-end-time modify-shift1-end-time" name="modify_shift1_end_${dayLower}" value="" placeholder="--:--" data-day="${day}" data-shift="1">
            </td>
            <td>
                <button type="button" class="btn-clear-shift" onclick="clearModifyShift(this, 1)">Clear</button>
            </td>
            <td class="separator-col"></td>
            <td>
                <input type="time" class="form-control shift2-start-time modify-shift2-start-time" name="modify_shift2_start_${dayLower}" value="" placeholder="--:--" data-day="${day}" data-shift="2">
            </td>
            <td>
                <input type="time" class="form-control shift2-end-time modify-shift2-end-time" name="modify_shift2_end_${dayLower}" value="" placeholder="--:--" data-day="${day}" data-shift="2">
            </td>
            <td>
                <button type="button" class="btn-clear-shift" onclick="clearModifyShift(this, 2)">Clear</button>
            </td>
            <td class="separator-col"></td>
            <td>
                <input type="time" class="form-control shift3-start-time modify-shift3-start-time" name="modify_shift3_start_${dayLower}" value="" placeholder="--:--" data-day="${day}" data-shift="3">
            </td>
            <td>
                <input type="time" class="form-control shift3-end-time modify-shift3-end-time" name="modify_shift3_end_${dayLower}" value="" placeholder="--:--" data-day="${day}" data-shift="3">
            </td>
            <td>
                <button type="button" class="btn-clear-shift" onclick="clearModifyShift(this, 3)">Clear</button>
            </td>
        `;
        tableBody.appendChild(row);
    });
};

// Handle modal close on background click
document.addEventListener('click', function(event) {
    const modifyScheduleModal = document.getElementById('modifyScheduleModal');
    if (event.target === modifyScheduleModal) {
        closeModifyScheduleModal();
    }
});

// Replace the lazy loading wrapper with the real function after loading
window.openModifyScheduleModal = window.openModifyScheduleModalReal;