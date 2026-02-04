let selectedTimeOffDates = new Set();
let timeOffDetails = {}; // Stores { date: { type: 'full'|'partial', workStart: 'HH:MM', workEnd: 'HH:MM' } }
let timeOffSpecialistId = null;
let timeOffWorkpointId = null;

window.bookedDates = new Set();
window.bookingCounts = {};

function openTimeOffModal() {
    // Get specialist and workpoint IDs from the modify schedule modal
    timeOffSpecialistId = document.getElementById('modifyScheduleSpecialistId').value;
    timeOffWorkpointId = document.getElementById('modifyScheduleWorkpointId').value;
    
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
    
    // Debug log



    // Update info display
    document.getElementById('specialistTimeOffInfo').innerHTML = `
        <strong>Specialist:</strong> ${specialistName} <br>
        <strong>Location:</strong> ${workpointName}
    `;
    
    // Set current year
    currentTimeOffYear = new Date().getFullYear();
    document.getElementById('timeOffYear').textContent = currentTimeOffYear;
    
    // Load booked dates first, then time off dates
    loadBookedDates(() => {
        loadTimeOffDates();
    });
    
    // Show modal
    document.getElementById('timeOffModal').style.display = 'flex';
}

function loadBookedDates(callback) {
    // Load dates with existing bookings for this specialist

    const formData = new FormData();
    formData.append('action', 'get_booked_dates');
    formData.append('specialist_id', timeOffSpecialistId);
    formData.append('year', currentTimeOffYear);
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
}

function closeTimeOffModal() {
    document.getElementById('timeOffModal').style.display = 'none';
    selectedTimeOffDates.clear();
}

function changeTimeOffYear(direction) {
    currentTimeOffYear += direction;
    document.getElementById('timeOffYear').textContent = currentTimeOffYear;
    // Reload booked dates for the new year
    loadBookedDates(() => {
        generateTimeOffCalendar();
    });
}

function generateTimeOffCalendar() {
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
        const monthNum = monthIndex + 1;
        const monthOrdinal = monthNum === 1 ? 'st' : monthNum === 2 ? 'nd' : monthNum === 3 ? 'rd' : 'th';
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
        for (let i = 0; i < firstDay; i++) {
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
            
            // Debug log for dates with bookings
            if (hasBookings) {

            }
            
            // Base styling
            let bgColor = '#fff';
            let textColor = isWeekend ? '#dc3545' : '#333';
            let cursor = 'pointer';
            
            if (hasBookings) {
                bgColor = '#d6d8db';
                textColor = '#6c757d';
                cursor = 'not-allowed';
            } else if (selectedTimeOffDates.has(dateStr)) {
                // Check if partial or full day off
                const dayOffData = timeOffDetails[dateStr] || { type: 'full' };
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
            } else if (selectedTimeOffDates.has(dateStr)) {
                const dayOffData = timeOffDetails[dateStr] || { type: 'full' };
                dayCell.title = dayOffData.type === 'partial' ? 'Partial Day OFF' : 'Full Day OFF';
            } else if (isToday) {
                dayCell.title = 'Today';
            } else {
                dayCell.title = '';
            }
            
            // Click handler - only if no bookings
            if (!hasBookings) {
                dayCell.onclick = function() {
                    toggleTimeOffDate(dateStr, this);
                };
                
                // Hover effect
                dayCell.onmouseover = function() {
                    if (!selectedTimeOffDates.has(dateStr) && !isToday && !hasBookings) {
                        this.style.background = '#f0f0f0';
                    }
                };
                dayCell.onmouseout = function() {
                    if (!selectedTimeOffDates.has(dateStr) && !isToday && !hasBookings) {
                        this.style.background = '#fff';
                    } else if (isToday && !selectedTimeOffDates.has(dateStr)) {
                        this.style.background = '#007bff';
                    } else if (hasBookings && !selectedTimeOffDates.has(dateStr)) {
                        this.style.background = '#d6d8db';
                    }
                };
            }
            
            daysGrid.appendChild(dayCell);
        }
        
        monthDiv.appendChild(daysGrid);
        grid.appendChild(monthDiv);
    }
}

function toggleTimeOffDate(dateStr, element) {
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
    
    if (selectedTimeOffDates.has(dateStr)) {
        selectedTimeOffDates.delete(dateStr);
        delete timeOffDetails[dateStr];
        autoSaveRemoveDayOff(dateStr);
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
        selectedTimeOffDates.add(dateStr);
        timeOffDetails[dateStr] = { type: 'full' };
        autoSaveAddFullDayOff(dateStr);
        element.style.background = '#dc3545';
        element.style.color = '#fff';
        element.title = 'Day off';
    }
    updateSelectedDaysList();
}

function updateSelectedDaysList() {
    const listDiv = document.getElementById('selectedDaysOffList');
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Filter only future dates
    const datesArray = Array.from(selectedTimeOffDates)
        .filter(date => new Date(date + 'T12:00:00') >= today)
        .sort();

    if (datesArray.length === 0) {
        listDiv.innerHTML = '<em style="color: #999;">No future days off selected</em>';
    } else {
        listDiv.innerHTML = datesArray.map(date => {
            const d = new Date(date + 'T12:00:00');
            const dayOffData = timeOffDetails[date] || { type: 'full' };
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
}

function removeDayOff(dateStr) {
    selectedTimeOffDates.delete(dateStr);
    delete timeOffDetails[dateStr];
    generateTimeOffCalendar();
    updateSelectedDaysList();
}

function toggleDayOffDropdown(date) {
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
}

function updateDayOffType(date, type) {
    if (!timeOffDetails[date]) {
        timeOffDetails[date] = {};
    }
    timeOffDetails[date].type = type;

    // Show/hide partial fields
    const partialDiv = document.getElementById(`partial-${date}`);
    if (partialDiv) {
        partialDiv.style.display = type === 'partial' ? 'block' : 'none';
    }

    // Auto-save the type change
    if (type === 'full') {
        timeOffDetails[date].workStart = null;
        timeOffDetails[date].workEnd = null;
        autoSaveConvertToFull(date);
    } else if (type === 'partial') {
        autoSaveConvertToPartial(date);
    }
}

function updateWorkingHours(date) {
    const startInput = document.getElementById(`start-${date}`);
    const endInput = document.getElementById(`end-${date}`);

    if (!timeOffDetails[date]) {
        timeOffDetails[date] = { type: 'partial' };
    }

    const workStart = startInput ? startInput.value : null;
    const workEnd = endInput ? endInput.value : null;

    timeOffDetails[date].workStart = workStart;
    timeOffDetails[date].workEnd = workEnd;

    // Auto-save working hours if both are filled
    if (workStart && workEnd) {
        autoSaveUpdateWorkingHours(date, workStart, workEnd);
    }
}

function clearAllTimeOff() {
    if (confirm('Are you sure you want to clear all selected days off?')) {
        selectedTimeOffDates.clear();
        timeOffDetails = {};
        generateTimeOffCalendar();
        updateSelectedDaysList();
    }
}

function loadTimeOffDates() {
    // Load existing time off dates and details from database
    const formData = new FormData();
    formData.append('action', 'get_time_off_details');
    formData.append('specialist_id', timeOffSpecialistId);
    formData.append('supervisor_mode', 'true');

    fetch('admin/specialist_time_off_auto_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            selectedTimeOffDates.clear();
            timeOffDetails = {};

            if (data.dates) {
                data.dates.forEach(date => selectedTimeOffDates.add(date));
            }

            if (data.details) {
                timeOffDetails = data.details;
            }

            generateTimeOffCalendar();
            updateSelectedDaysList();
        }
    })
    .catch(error => {
        console.error('Error loading time off dates:', error);
    });
}

function saveTimeOff() {
    const formData = new FormData();
    formData.append('action', 'save_time_off');
    formData.append('specialist_id', timeOffSpecialistId);
    formData.append('dates', JSON.stringify(Array.from(selectedTimeOffDates)));
    formData.append('details', JSON.stringify(timeOffDetails));
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
            closeTimeOffModal();
        } else {
            alert('Failed to save days off: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error saving time off:', error);
        alert('An error occurred while saving days off.');
    });
}

// Auto-save functions
function autoSaveAddFullDayOff(date) {
