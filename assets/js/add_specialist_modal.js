// Add Specialist Modal Functions
function openAddSpecialistModal(workpointId, organisationId) {
    const modal = document.getElementById('addSpecialistModal');
    if (!modal) return;
    modal.style.display = 'flex';
    document.getElementById('workpointId').value = workpointId || '';
    document.getElementById('organisationId').value = organisationId || '';
    if (workpointId) {
        document.getElementById('workpointSelect').style.display = 'none';
        document.getElementById('workpointLabel').style.display = 'none';
        fetch('admin/get_working_point_details.php?workpoint_id=' + workpointId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('workingScheduleTitle').textContent = '📋 Working Schedule at ' + data.workpoint.name_of_the_place + ' (' + data.workpoint.address + ')';
                }
            });
        loadAvailableSpecialists(organisationId, workpointId);
    } else {
        document.getElementById('workpointSelect').style.display = 'block';
        document.getElementById('workpointLabel').style.display = 'block';
        document.getElementById('workpointLabel').textContent = 'Assign to Working Point *';
        loadWorkingPointsForOrganisation(organisationId);
    }
    loadScheduleEditor();
}

function closeAddSpecialistModal() {
    document.getElementById('addSpecialistModal').style.display = 'none';
    document.getElementById('addSpecialistForm').reset();
    document.getElementById('scheduleEditorTableBody').innerHTML = '';
}

window.onclick = function(event) {
    const modal = document.getElementById('addSpecialistModal');
    if (event.target === modal) {
        closeAddSpecialistModal();
    }
}

function loadWorkingPointsForOrganisation(organisationId) {
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
        });
}

function loadScheduleEditor() {
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
}

function loadAvailableSpecialists(organisationId, workpointId) {
    fetch(`admin/get_available_specialists.php?organisation_id=${organisationId}&workpoint_id=${workpointId}`)
        .then(response => response.json())
        .then(data => {
            const specialistSelect = document.getElementById('specialistSelection');
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
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No available specialists found';
                option.disabled = true;
                specialistSelect.appendChild(option);
            }
        });
}

function handleSpecialistSelection() {
    const specialistSelect = document.getElementById('specialistSelection');
    const selectedValue = specialistSelect.value;
    if (selectedValue === 'new') {
        enableFormFields();
        clearFormFields();
    } else if (selectedValue && selectedValue !== '') {
        loadSpecialistData(selectedValue);
        disableFormFields();
    }
}

function enableFormFields() {
    const fields = ['specialistName', 'specialistSpeciality', 'specialistEmail', 'specialistPhone', 'specialistUser', 'specialistPassword', 'emailScheduleHour', 'emailScheduleMinute'];
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.readOnly = false;
            field.disabled = false;
            field.style.backgroundColor = '#fff';
        }
    });
}

function disableFormFields() {
    const fields = ['specialistName', 'specialistSpeciality', 'specialistEmail', 'specialistPhone', 'specialistUser', 'specialistPassword', 'emailScheduleHour', 'emailScheduleMinute'];
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.readOnly = true;
            field.disabled = true;
            field.style.backgroundColor = '#f8f9fa';
        }
    });
}

function clearFormFields() {
    const fields = ['specialistName', 'specialistSpeciality', 'specialistEmail', 'specialistPhone', 'specialistUser', 'specialistPassword', 'emailScheduleHour', 'emailScheduleMinute'];
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.value = '';
        }
    });
    document.getElementById('emailScheduleHour').value = '9';
    document.getElementById('emailScheduleMinute').value = '0';
}

function loadSpecialistData(specialistId) {
    fetch(`admin/get_specialist_data.php?specialist_id=${specialistId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const specialist = data.specialist;
                document.getElementById('specialistName').value = specialist.name || '';
                document.getElementById('specialistSpeciality').value = specialist.speciality || '';
                document.getElementById('specialistEmail').value = specialist.email || '';
                document.getElementById('specialistPhone').value = specialist.phone_nr || '';
                document.getElementById('specialistUser').value = specialist.user || '';
                document.getElementById('specialistPassword').value = '';
                document.getElementById('emailScheduleHour').value = specialist.h_of_email_schedule || '9';
                document.getElementById('emailScheduleMinute').value = specialist.m_of_email_schedule || '0';
            }
        });
}

function clearShift(button, shiftNum) {
    const row = button.closest('tr');
    const startInput = row.querySelector(`.shift${shiftNum}-start-time`);
    const endInput = row.querySelector(`.shift${shiftNum}-end-time`);
    startInput.value = '';
    endInput.value = '';
}

function applyAllShifts() {
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
    const shift1Start = document.getElementById('shift1Start').value;
    const shift1End = document.getElementById('shift1End').value;
    const shift2Start = document.getElementById('shift2Start').value;
    const shift2End = document.getElementById('shift2End').value;
    const shift3Start = document.getElementById('shift3Start').value;
    const shift3End = document.getElementById('shift3End').value;
    days.forEach(day => {
        const row = document.querySelector(`input[name="shift1_start_${day}"]`).closest('tr');
        if (shift1Start && shift1End) {
            row.querySelector(`input[name="shift1_start_${day}"]`).value = shift1Start;
            row.querySelector(`input[name="shift1_end_${day}"]`).value = shift1End;
        }
        if (shift2Start && shift2End) {
            row.querySelector(`input[name="shift2_start_${day}"]`).value = shift2Start;
            row.querySelector(`input[name="shift2_end_${day}"]`).value = shift2End;
        }
        if (shift3Start && shift3End) {
            row.querySelector(`input[name="shift3_start_${day}"]`).value = shift3Start;
            row.querySelector(`input[name="shift3_end_${day}"]`).value = shift3End;
        }
    });
} 