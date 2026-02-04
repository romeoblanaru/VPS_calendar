// Time Off Modal Functions
// This file contains all Time Off modal functionality including the calendar display

// Global variables for Time Off functionality
window.currentTimeOffYear = new Date().getFullYear();
window.selectedTimeOffDates = new Set();
window.timeOffDetails = {}; // Stores { date: { type: 'full'|'partial', workStart: 'HH:MM', workEnd: 'HH:MM' } }
// timeOffSpecialistId is already set by the lazy loader
window.timeOffWorkpointId = null;

// bookedDates and bookingCounts are already initialized by the lazy loader

// Store the real implementation
window.openTimeOffModalReal = function() {
    // timeOffSpecialistId is already set by the lazy loader, just reconfirm
    window.timeOffSpecialistId = document.getElementById('modifyScheduleSpecialistId').value;
    window.timeOffWorkpointId = document.getElementById('modifyScheduleWorkpointId').value;

    // Get specialist name from title - look for the text after "Modify Schedule: "
    const titleText = document.getElementById('modifyScheduleTitle').textContent;
    let specialistName = 'Unknown';
    let workpointName = 'Unknown';

    // Try different patterns to extract the names
    const match1 = titleText.match(/Editing: (.+?) at (.+)$/);
    const match2 = titleText.match(/Modify Schedule: (.+?) at (.+)$/);
    const match3 = titleText.match(/Schedule: (.+?) at (.+)$/);

    if (match1) {
        specialistName = match1[1];
        workpointName = match1[2];
    } else if (match2) {
        specialistName = match2[1];
        workpointName = match2[2];
    } else if (match3) {
        specialistName = match3[1];
        workpointName = match3[2];
    }

    // Update info display
    document.getElementById('specialistTimeOffInfo').innerHTML = `
        <strong>Specialist:</strong> ${specialistName} <br>
        <strong>Location:</strong> ${workpointName}
    `;

    // Set current year
    window.currentTimeOffYear = new Date().getFullYear();
    document.getElementById('timeOffYear').textContent = window.currentTimeOffYear;

    // Load booked dates first, then time off dates
    window.loadBookedDates(() => {
        window.loadTimeOffDates();
    });

    // Show modal
    document.getElementById('timeOffModal').style.display = 'flex';
};

// Load booked dates for the specialist
window.loadBookedDates = function(callback) {
    // Load dates with existing bookings for this specialist
    const formData = new FormData();
    formData.append('action', 'get_booked_dates');
    formData.append('specialist_id', window.timeOffSpecialistId);
    formData.append('year', window.currentTimeOffYear);
    formData.append('supervisor_mode', 'true');

    fetch('admin/get_specialist_bookings_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.dates) {
            window.bookedDates.clear();
            window.bookingCounts = {};
            data.dates.forEach(item => {
                if (typeof item === 'string') {
                    // Legacy format - just date
                    window.bookedDates.add(item);
                    window.bookingCounts[item] = 1;
                } else {
                    // New format with count
                    window.bookedDates.add(item.date);
                    window.bookingCounts[item.date] = item.count;
                }
            });
        }
        if (callback) callback();
    })
    .catch(error => {
        console.error('Error loading booked dates:', error);
        if (callback) callback();
    });
};

// Close Time Off Modal
window.closeTimeOffModal = function() {
    document.getElementById('timeOffModal').style.display = 'none';
    window.selectedTimeOffDates.clear();
};

// Change year in Time Off calendar
window.changeTimeOffYear = function(direction) {
    window.currentTimeOffYear += direction;
    document.getElementById('timeOffYear').textContent = window.currentTimeOffYear;
    // Reload booked dates for the new year
    window.loadBookedDates(() => {
        window.generateTimeOffCalendar();
    });
};

// Generate the Time Off calendar display
window.generateTimeOffCalendar = function() {
    const grid = document.getElementById('timeOffCalendarGrid');
    grid.innerHTML = '';

    // Get today's date
    const today = new Date();
    const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];

    // Start with current month
    const currentMonth = today.getMonth();
    const currentYear = today.getFullYear();

    for (let i = 0; i < 12; i++) {
        const monthIndex = (currentMonth + i) % 12;
        const year = currentYear + Math.floor((currentMonth + i) / 12);
        const monthDiv = document.createElement('div');
        monthDiv.style.cssText = 'background: white; border: 1px solid #ddd; border-radius: 3px; padding: 6px; transform: scale(0.75); transform-origin: top left; margin-bottom: -50px; margin-right: -70px;';

        // Month header with year and month number
        const monthHeader = document.createElement('div');
        monthHeader.style.cssText = 'text-align: center; font-weight: bold; margin-bottom: 5px; color: #333; font-size: 13px;';
        monthHeader.textContent = `${year} ${monthNames[monthIndex]}`;
        monthDiv.appendChild(monthHeader);

        // Days grid
        const daysGrid = document.createElement('div');
        daysGrid.style.cssText = 'display: grid; grid-template-columns: repeat(7, 1fr); gap: 0px; font-size: 11px;';

        // Day headers - Monday first, weekends in red
        const dayHeaders = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
        dayHeaders.forEach((day, index) => {
            const dayHeader = document.createElement('div');
            const isWeekend = index >= 5;
            dayHeader.style.cssText = `text-align: center; font-weight: bold; color: ${isWeekend ? '#dc3545' : '#666'}; padding: 2px; font-size: 10px;`;
            dayHeader.textContent = day;
            daysGrid.appendChild(dayHeader);
        });

        // Get first day of month and number of days
        let firstDay = new Date(year, monthIndex, 1).getDay();
        // Adjust for Monday as first day (0 = Sunday, so convert to Monday = 0)
        firstDay = firstDay === 0 ? 6 : firstDay - 1;
        const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();

        // Empty cells for days before month starts
        for (let j = 0; j < firstDay; j++) {
            const emptyCell = document.createElement('div');
            daysGrid.appendChild(emptyCell);
        }

        // Days of month
        for (let day = 1; day <= daysInMonth; day++) {
            const dayCell = document.createElement('div');
            const dateStr = `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

            // Check if this date is today
            const isToday = dateStr === todayStr;

            // Check if weekend
            const dayOfWeek = new Date(year, monthIndex, day).getDay();
            const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;

            // Check if this date has bookings (will be populated by loadBookedDates)
            const hasBookings = window.bookedDates && window.bookedDates.has(dateStr);
            const bookingCount = window.bookingCounts && window.bookingCounts[dateStr] || 0;

            // Base styling
            let bgColor = '#fff';
            let textColor = isWeekend ? '#dc3545' : '#333';
            let cursor = 'pointer';

            if (hasBookings) {
                bgColor = '#d6d8db';
                textColor = '#6c757d';
                cursor = 'not-allowed';
            } else if (window.selectedTimeOffDates.has(dateStr)) {
                // Check if partial or full day off
                const dayOffData = window.timeOffDetails[dateStr] || { type: 'full' };
                bgColor = dayOffData.type === 'partial' ? '#f59e0b' : '#dc3545';
                textColor = '#fff';
            } else if (isToday) {
                bgColor = '#007bff';
                textColor = '#fff';
            }

            dayCell.style.cssText = `
                text-align: center;
                padding: 6px 4px;
                cursor: ${cursor};
                border: none;
                background: ${bgColor};
                color: ${textColor};
                transition: all 0.2s;
                font-size: 12px;
                width: 28px;
                height: 28px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                margin: 2px auto;
            `;

            dayCell.textContent = day;
            dayCell.dataset.date = dateStr;

            // Set tooltip
            if (hasBookings) {
                dayCell.title = `${bookingCount} booking${bookingCount > 1 ? 's' : ''} (bookings need to be canceled before selecting this day off)`;
            } else if (window.selectedTimeOffDates.has(dateStr)) {
                const dayOffData = window.timeOffDetails[dateStr] || { type: 'full' };
                dayCell.title = dayOffData.type === 'partial' ? 'Partial Day OFF' : 'Full Day OFF';
            } else if (isToday) {
                dayCell.title = 'Today';
            } else {
                dayCell.title = '';
            }

            // Click handler - only if no bookings
            if (!hasBookings) {
                dayCell.onclick = function() {
                    window.toggleTimeOffDate(dateStr, this);
                };

                // Hover effect
                dayCell.onmouseover = function() {
                    if (!window.selectedTimeOffDates.has(dateStr) && !isToday && !hasBookings) {
                        this.style.background = '#f0f0f0';
                    }
                };
                dayCell.onmouseout = function() {
                    if (!window.selectedTimeOffDates.has(dateStr) && !isToday && !hasBookings) {
                        this.style.background = '#fff';
                    } else if (isToday && !window.selectedTimeOffDates.has(dateStr)) {
                        this.style.background = '#007bff';
                    } else if (hasBookings && !window.selectedTimeOffDates.has(dateStr)) {
                        this.style.background = '#d6d8db';
                    }
                };
            }

            daysGrid.appendChild(dayCell);
        }

        monthDiv.appendChild(daysGrid);
        grid.appendChild(monthDiv);
    }
};

// Toggle a date selection in the calendar
window.toggleTimeOffDate = function(dateStr, element) {
    const today = new Date();
    const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
    const isToday = dateStr === todayStr;

    // Check if weekend
    const dateParts = dateStr.split('-').map(Number);
    const year = dateParts[0];
    const month = dateParts[1];
    const day = dateParts[2];
    const dayOfWeek = new Date(year, month - 1, day).getDay();
    const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;

    if (window.selectedTimeOffDates.has(dateStr)) {
        window.selectedTimeOffDates.delete(dateStr);
        delete window.timeOffDetails[dateStr];
        // Call autoSave function if it exists
        if (typeof window.autoSaveRemoveDayOff === 'function') {
            window.autoSaveRemoveDayOff(dateStr);
        }
        if (isToday) {
            element.style.background = '#007bff';
            element.style.color = '#fff';
            element.title = 'Today';
        } else {
            element.style.background = '#fff';
            element.style.color = isWeekend ? '#dc3545' : '#333';
            element.title = '';
        }
    } else {
        window.selectedTimeOffDates.add(dateStr);
        window.timeOffDetails[dateStr] = { type: 'full' };
        // Call autoSave function if it exists
        if (typeof window.autoSaveAddFullDayOff === 'function') {
            window.autoSaveAddFullDayOff(dateStr);
        }
        element.style.background = '#dc3545';
        element.style.color = '#fff';
        element.title = 'Day off';
    }
    window.updateSelectedDaysList();
};

// Update the list of selected days off
window.updateSelectedDaysList = function() {
    const listDiv = document.getElementById('selectedDaysOffList');
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Filter only future dates
    const datesArray = Array.from(window.selectedTimeOffDates)
        .filter(date => new Date(date + 'T12:00:00') >= today)
        .sort();

    if (datesArray.length === 0) {
        listDiv.innerHTML = '<em style="color: #999;">No future days off selected</em>';
    } else {
        listDiv.innerHTML = datesArray.map(date => {
            const d = new Date(date + 'T12:00:00');
            const dayOffData = window.timeOffDetails[date] || { type: 'full' };
            const isPartial = dayOffData.type === 'partial';
            const dropdownId = `dropdown-${date}`;

            const buttonBgColor = isPartial ? '#f59e0b' : '#dc3545';
            const buttonIcon = isPartial ? '◐' : '⊗';

            return `<div style="margin: 4px 0;">
                <div onclick="toggleDayOffDropdown('${date}')"
                     style="display: flex; align-items: center; justify-content: space-between; padding: 6px 8px; background: ${buttonBgColor}; color: white; border-radius: 3px; cursor: pointer; white-space: nowrap;">
                    <span style="font-size: 12px; font-weight: 500;">
                        <span style="font-size: 1.1em; margin-right: 4px;">${buttonIcon}</span>${d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })}
                    </span>
                    <span onclick="event.stopPropagation(); removeDayOff('${date}')"
                          style="color: white; cursor: pointer; font-size: 18px; font-weight: bold; padding: 0 2px; margin-left: 4px;"
                          title="Remove">
                        ×
                    </span>
                </div>
                <div id="${dropdownId}" style="display: none; background: #f8f9fa; border: 1px solid #ddd; border-radius: 3px; padding: 8px; margin-top: 2px; font-size: 11px;">
                    <div style="margin-bottom: 6px;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 600;">Type:</label>
                        <select onchange="updateDayOffType('${date}', this.value)" style="width: 100%; padding: 4px; border: 1px solid #ddd; border-radius: 2px; font-size: 11px;">
                            <option value="full" ${!isPartial ? 'selected' : ''}>Fully Off</option>
                            <option value="partial" ${isPartial ? 'selected' : ''}>Partially Off</option>
                        </select>
                    </div>
                    <div id="partial-${date}" style="display: ${isPartial ? 'block' : 'none'};">
                        <label style="display: block; margin-bottom: 4px; font-weight: 600;">Working Hours:</label>
                        <div style="display: flex; gap: 4px; align-items: center;">
                            <input type="time" id="start-${date}" value="${dayOffData.workStart || ''}"
                                   onchange="updateWorkingHours('${date}')"
                                   style="flex: 1; padding: 4px; border: 1px solid #ddd; border-radius: 2px; font-size: 11px;"
                                   placeholder="From">
                            <span>to</span>
                            <input type="time" id="end-${date}" value="${dayOffData.workEnd || ''}"
                                   onchange="updateWorkingHours('${date}')"
                                   style="flex: 1; padding: 4px; border: 1px solid #ddd; border-radius: 2px; font-size: 11px;"
                                   placeholder="Until">
                        </div>
                    </div>
                </div>
            </div>`;
        }).join('');
    }
};

// Remove a day off
window.removeDayOff = function(dateStr) {
    window.selectedTimeOffDates.delete(dateStr);
    delete window.timeOffDetails[dateStr];
    window.generateTimeOffCalendar();
    window.updateSelectedDaysList();
};

// Toggle dropdown for day off options
window.toggleDayOffDropdown = function(date) {
    const dropdown = document.getElementById(`dropdown-${date}`);
    if (dropdown) {
        const isVisible = dropdown.style.display !== 'none';
        // Close all other dropdowns first
        document.querySelectorAll('[id^="dropdown-"]').forEach(dd => {
            if (dd.id !== `dropdown-${date}`) {
                dd.style.display = 'none';
            }
        });
        dropdown.style.display = isVisible ? 'none' : 'block';
    }
};

// Update day off type (full/partial)
window.updateDayOffType = function(date, type) {
    if (!window.timeOffDetails[date]) {
        window.timeOffDetails[date] = {};
    }
    window.timeOffDetails[date].type = type;

    // Show/hide partial fields
    const partialDiv = document.getElementById(`partial-${date}`);
    if (partialDiv) {
        partialDiv.style.display = type === 'partial' ? 'block' : 'none';
    }

    // Auto-save the type change if functions exist
    if (type === 'full') {
        window.timeOffDetails[date].workStart = null;
        window.timeOffDetails[date].workEnd = null;
        if (typeof window.autoSaveConvertToFull === 'function') {
            window.autoSaveConvertToFull(date);
        }
    } else if (type === 'partial') {
        if (typeof window.autoSaveConvertToPartial === 'function') {
            window.autoSaveConvertToPartial(date);
        }
    }
};

// Update working hours for partial day off
window.updateWorkingHours = function(date) {
    const startInput = document.getElementById(`start-${date}`);
    const endInput = document.getElementById(`end-${date}`);

    if (!window.timeOffDetails[date]) {
        window.timeOffDetails[date] = { type: 'partial' };
    }

    const workStart = startInput ? startInput.value : null;
    const workEnd = endInput ? endInput.value : null;

    window.timeOffDetails[date].workStart = workStart;
    window.timeOffDetails[date].workEnd = workEnd;

    // Auto-save working hours if both are filled
    if (workStart && workEnd) {
        if (typeof window.autoSaveUpdateWorkingHours === 'function') {
            window.autoSaveUpdateWorkingHours(date, workStart, workEnd);
        }
    }
};

// Clear all time off selections
window.clearAllTimeOff = function() {
    if (confirm('Are you sure you want to clear all selected days off?')) {
        window.selectedTimeOffDates.clear();
        window.timeOffDetails = {};
        window.generateTimeOffCalendar();
        window.updateSelectedDaysList();
    }
};

// Load existing time off dates from database
window.loadTimeOffDates = function() {
    // Load existing time off dates and details from database
    const formData = new FormData();
    formData.append('action', 'get_time_off_details');
    formData.append('specialist_id', window.timeOffSpecialistId);
    formData.append('supervisor_mode', 'true');

    fetch('admin/specialist_time_off_auto_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.selectedTimeOffDates.clear();
            window.timeOffDetails = {};

            if (data.dates) {
                data.dates.forEach(date => window.selectedTimeOffDates.add(date));
            }

            if (data.details) {
                window.timeOffDetails = data.details;
            }

            window.generateTimeOffCalendar();
            window.updateSelectedDaysList();
        }
    })
    .catch(error => {
        console.error('Error loading time off dates:', error);
    });
};

// Save time off selections to database
window.saveTimeOff = function() {
    const formData = new FormData();
    formData.append('action', 'save_time_off');
    formData.append('specialist_id', window.timeOffSpecialistId);
    formData.append('dates', JSON.stringify(Array.from(window.selectedTimeOffDates)));
    formData.append('details', JSON.stringify(window.timeOffDetails));
    formData.append('supervisor_mode', 'true');

    fetch('admin/specialist_time_off_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Days off saved successfully!');
            window.closeTimeOffModal();
        } else {
            alert('Failed to save days off: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error saving time off:', error);
        alert('An error occurred while saving days off.');
    });
};

// Replace the lazy loading wrapper with the real function after loading
window.openTimeOffModal = window.openTimeOffModalReal;

// Initialize on load
console.log('Time Off Modal loaded successfully');