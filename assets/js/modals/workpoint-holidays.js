// Workpoint Holidays & Closures Modal Functions
// This file contains all Workpoint Holidays modal functionality including the calendar display

// Global variables for Workpoint Holidays functionality
window.workpointTimeOffData = new Set();
window.workpointTimeOffDetails = {}; // Stores { date: { type, workStart, workEnd, isRecurring, description } }
window.workpointHolidaysWorkpointId = null;

// Store the real implementation
window.openWorkpointHolidaysModalReal = function() {
    // Get workpoint ID from window.currentWorkpointId (set by PHP in main page)
    const workpointId = window.currentWorkpointId || 0;

    console.log('Workpoint ID:', workpointId); // Debug output

    if (!workpointId || workpointId === 0) {
        alert('No workpoint selected. Please refresh the page and try again.');
        return;
    }

    window.workpointHolidaysWorkpointId = workpointId;

    // Load workpoint data first
    window.loadWorkpointHolidays(workpointId);

    // Create modal
    let modal = document.getElementById('workpointHolidaysModal');
    if (modal) modal.remove();

    modal = document.createElement('div');
    modal.id = 'workpointHolidaysModal';
    modal.style.cssText = 'display:block; position:fixed; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; overflow-y:auto;';
    modal.innerHTML = `
        <div style="background:#fff; width:90%; max-width:1200px; height:90vh; margin:2% auto; overflow-y:auto; border-radius:8px; box-shadow:0 6px 24px rgba(0,0,0,0.2);">
            <div style="background:#ffc107; color:#000; padding:16px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:1;">
                <h3 style="margin:0;"><i class="fas fa-calendar-times"></i> Workpoint Holidays & Closures</h3>
                <span style="cursor:pointer; font-size:32px; font-weight:bold; color:#000; line-height:1;" onclick="closeWorkpointHolidaysModal()">&times;</span>
            </div>
            <div style="padding:20px;">
                <!-- Table layout matching specialist holidays -->
                <table style="width:100%; margin:0; padding:0; border:0; border-spacing:0;">
                    <tr>
                        <td style="vertical-align:top; border:0; margin:0; padding:0;">
                            <div id="workpointInfo" style="margin-bottom:10px; font-size:14px;">
                                <!-- Workpoint info will be displayed here -->
                            </div>
                        </td>
                        <td rowspan="3" style="vertical-align:top; border:0; margin:0; padding:0; width:200px;">
                            <!-- Selected dates summary -->
                            <div style="padding:10px; background:#f8f9fa; border-radius:5px; margin-left:10px; height:600px; box-sizing:border-box; overflow-y:auto;">
                                <h5 style="margin-bottom:10px; text-align:center; font-size:0.9em;">Selected Holidays</h5>
                                <div id="selectedWorkpointHolidaysList" style="text-align:left;">
                                    <!-- Selected dates will be listed here -->
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="vertical-align:top; border:0; margin:0; padding:0;">
                            <!-- 12 month calendar grid -->
                            <div id="workpointHolidaysCalendar" style="display:grid; grid-template-columns:repeat(4, minmax(200px, 1fr)); gap:0px;">
                                <!-- Months will be generated here -->
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="vertical-align:top; border:0; margin:0; padding:0;">
                            <!-- Empty row to ensure proper height -->
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    window.renderWorkpointHolidaysCalendar();
};

window.closeWorkpointHolidaysModal = function() {
    const modal = document.getElementById('workpointHolidaysModal');
    if (modal) modal.remove();
};

window.loadWorkpointHolidays = function(workpointId) {
    const formData = new FormData();
    formData.append('action', 'get_time_off_details');
    formData.append('workingpoint_id', workpointId);

    fetch('admin/workpoint_time_off_auto_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.workpointTimeOffData = new Set(data.dates || []);
            window.workpointTimeOffDetails = data.details || {};
            window.renderWorkpointHolidaysCalendar();
            window.updateSelectedWorkpointHolidaysList();
        }
    })
    .catch(error => console.error('Error loading holidays:', error));
};

window.renderWorkpointHolidaysCalendar = function() {
    const container = document.getElementById('workpointHolidaysCalendar');
    if (!container) return;

    container.innerHTML = '';

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
        monthDiv.style.cssText = 'background:white; border:1px solid #ddd; border-radius:3px; padding:6px; transform:scale(0.75); transform-origin:top left; margin-bottom:-50px; margin-right:-70px;';

        // Month header with year and month
        const monthHeader = document.createElement('div');
        monthHeader.style.cssText = 'text-align:center; font-weight:bold; margin-bottom:5px; color:#333; font-size:13px;';
        monthHeader.textContent = `${year} ${monthNames[monthIndex]}`;
        monthDiv.appendChild(monthHeader);

        // Days grid
        const daysGrid = document.createElement('div');
        daysGrid.style.cssText = 'display:grid; grid-template-columns:repeat(7, 1fr); gap:0px; font-size:11px;';

        // Day headers - Monday first, weekends in red
        const dayHeaders = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
        dayHeaders.forEach((day, index) => {
            const dayHeader = document.createElement('div');
            const isWeekend = index >= 5;
            dayHeader.style.cssText = `text-align:center; font-weight:bold; color:${isWeekend ? '#dc3545' : '#666'}; padding:2px; font-size:10px;`;
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

            // Check if this date is a holiday
            const isHoliday = window.workpointTimeOffData.has(dateStr);
            const holidayDetails = window.workpointTimeOffDetails[dateStr] || {};
            const isPartial = holidayDetails.type === 'partial';
            const isRecurring = holidayDetails.isRecurring;

            // Base styling
            let bgColor = '#fff';
            let textColor = isWeekend ? '#dc3545' : '#333';
            let borderStyle = 'none';

            if (isHoliday) {
                bgColor = isPartial ? '#ffc107' : '#dc3545';
                textColor = '#fff';
                if (isRecurring) {
                    borderStyle = '2px dashed #000';
                }
            } else if (isToday) {
                bgColor = '#007bff';
                textColor = '#fff';
            }

            dayCell.style.cssText = `
                text-align: center;
                padding: 6px 4px;
                cursor: pointer;
                border: ${borderStyle};
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
                position: relative;
            `;

            dayCell.textContent = day;
            dayCell.dataset.date = dateStr;

            // Set tooltip
            if (isHoliday) {
                let tooltipText = isPartial ? 'Partial Closure' : 'Full Closure';
                if (isRecurring) tooltipText += ' (Recurring)';
                if (holidayDetails.description) tooltipText += `: ${holidayDetails.description}`;
                dayCell.title = tooltipText;
            } else if (isToday) {
                dayCell.title = 'Today';
            }

            // Click handler
            dayCell.onclick = function() {
                window.toggleWorkpointHoliday(dateStr);
            };

            // Hover effect
            dayCell.onmouseover = function() {
                if (!isHoliday && !isToday) {
                    this.style.background = '#f0f0f0';
                }
            };
            dayCell.onmouseout = function() {
                if (!isHoliday && !isToday) {
                    this.style.background = '#fff';
                } else if (isToday && !isHoliday) {
                    this.style.background = '#007bff';
                }
            };

            daysGrid.appendChild(dayCell);
        }

        monthDiv.appendChild(daysGrid);
        container.appendChild(monthDiv);
    }
};

window.toggleWorkpointHoliday = function(dateStr) {
    if (window.workpointTimeOffData.has(dateStr)) {
        window.workpointTimeOffData.delete(dateStr);
        delete window.workpointTimeOffDetails[dateStr];
        window.autoSaveRemoveWorkpointHoliday(dateStr);
    } else {
        window.workpointTimeOffData.add(dateStr);
        window.workpointTimeOffDetails[dateStr] = { type: 'full' };
        window.autoSaveAddWorkpointHoliday(dateStr);
    }
    window.renderWorkpointHolidaysCalendar();
    window.updateSelectedWorkpointHolidaysList();
};

window.updateSelectedWorkpointHolidaysList = function() {
    const listDiv = document.getElementById('selectedWorkpointHolidaysList');
    if (!listDiv) return;

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Filter only future dates and sort them
    const datesArray = Array.from(window.workpointTimeOffData)
        .filter(date => new Date(date + 'T12:00:00') >= today)
        .sort();

    if (datesArray.length === 0) {
        listDiv.innerHTML = '<em style="color:#999;">No holidays selected</em>';
    } else {
        listDiv.innerHTML = datesArray.map(date => {
            const d = new Date(date + 'T12:00:00');
            const details = window.workpointTimeOffDetails[date] || {};
            const isPartial = details.type === 'partial';
            const isRecurring = details.isRecurring;
            const dropdownId = `wp-dropdown-${date}`;

            const buttonBgColor = isPartial ? '#ffc107' : '#dc3545';
            const buttonIcon = isPartial ? '◐' : '⊗';
            const recurringIcon = isRecurring ? '🔄' : '';

            return `<div style="margin:4px 0;">
                <div onclick="toggleWorkpointHolidayDropdown('${date}')"
                     style="display:flex; align-items:center; justify-content:space-between; padding:6px 8px; background:${buttonBgColor}; color:white; border-radius:3px; cursor:pointer; white-space:nowrap;">
                    <span style="font-size:12px; font-weight:500;">
                        <span style="font-size:1.1em; margin-right:4px;">${buttonIcon}</span>${recurringIcon} ${d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })}
                    </span>
                    <span onclick="event.stopPropagation(); removeWorkpointHoliday('${date}')"
                          style="color:white; cursor:pointer; font-size:18px; font-weight:bold; padding:0 2px; margin-left:4px;"
                          title="Remove">
                        ×
                    </span>
                </div>
                <div id="${dropdownId}" style="display:none; background:#f8f9fa; border:1px solid #ddd; border-radius:3px; padding:8px; margin-top:2px; font-size:11px;">
                    <div style="margin-bottom:6px;">
                        <label style="display:block; margin-bottom:4px; font-weight:600;">Type:</label>
                        <select onchange="updateWorkpointDayOffType('${date}', this.value)" style="width:100%; padding:4px; border:1px solid #ddd; border-radius:2px; font-size:11px;">
                            <option value="full" ${!isPartial ? 'selected' : ''}>Fully Closed</option>
                            <option value="partial" ${isPartial ? 'selected' : ''}>Partially Open</option>
                        </select>
                    </div>
                    <div id="wp-partial-${date}" style="display:${isPartial ? 'block' : 'none'};">
                        <label style="display:block; margin-bottom:4px; font-weight:600;">Open Hours:</label>
                        <div style="display:flex; gap:4px; align-items:center;">
                            <input type="time" id="wp-start-${date}" value="${details.workStart || ''}"
                                   onchange="updateWorkpointWorkingHours('${date}')"
                                   style="flex:1; padding:4px; border:1px solid #ddd; border-radius:2px; font-size:11px;">
                            <span>to</span>
                            <input type="time" id="wp-end-${date}" value="${details.workEnd || ''}"
                                   onchange="updateWorkpointWorkingHours('${date}')"
                                   style="flex:1; padding:4px; border:1px solid #ddd; border-radius:2px; font-size:11px;">
                        </div>
                    </div>
                    <div style="margin:6px 0;">
                        <label style="display:flex; align-items:center; gap:4px;">
                            <input type="checkbox" ${isRecurring ? 'checked' : ''}
                                   onchange="updateWorkpointRecurring('${date}', this.checked)"
                                   style="margin:0;">
                            <span style="font-weight:600;">Recurring Annually</span>
                        </label>
                    </div>
                    <div style="margin-top:6px;">
                        <label style="display:block; margin-bottom:4px; font-weight:600;">Description:</label>
                        <input type="text" id="wp-desc-${date}" value="${details.description || ''}"
                               onchange="updateWorkpointDescription('${date}')"
                               style="width:100%; padding:4px; border:1px solid #ddd; border-radius:2px; font-size:11px;"
                               placeholder="e.g., Christmas Day">
                    </div>
                </div>
            </div>`;
        }).join('');
    }
};

window.toggleWorkpointHolidayDropdown = function(date) {
    const dropdown = document.getElementById(`wp-dropdown-${date}`);
    if (dropdown) {
        const isVisible = dropdown.style.display !== 'none';
        // Close all other dropdowns first
        document.querySelectorAll('[id^="wp-dropdown-"]').forEach(dd => {
            if (dd.id !== `wp-dropdown-${date}`) {
                dd.style.display = 'none';
            }
        });
        dropdown.style.display = isVisible ? 'none' : 'block';
    }
};

window.removeWorkpointHoliday = function(date) {
    window.workpointTimeOffData.delete(date);
    delete window.workpointTimeOffDetails[date];
    window.autoSaveRemoveWorkpointHoliday(date);
    window.renderWorkpointHolidaysCalendar();
    window.updateSelectedWorkpointHolidaysList();
};

window.updateWorkpointDayOffType = function(date, type) {
    if (!window.workpointTimeOffDetails[date]) {
        window.workpointTimeOffDetails[date] = {};
    }
    window.workpointTimeOffDetails[date].type = type;

    // Show/hide partial fields
    const partialDiv = document.getElementById(`wp-partial-${date}`);
    if (partialDiv) {
        partialDiv.style.display = type === 'partial' ? 'block' : 'none';
    }

    // Auto-save is handled in the working hours update
    window.renderWorkpointHolidaysCalendar();
};

window.updateWorkpointWorkingHours = function(date) {
    const startInput = document.getElementById(`wp-start-${date}`);
    const endInput = document.getElementById(`wp-end-${date}`);

    if (!window.workpointTimeOffDetails[date]) {
        window.workpointTimeOffDetails[date] = { type: 'partial' };
    }

    const workStart = startInput ? startInput.value : null;
    const workEnd = endInput ? endInput.value : null;

    window.workpointTimeOffDetails[date].workStart = workStart;
    window.workpointTimeOffDetails[date].workEnd = workEnd;

    if (workStart && workEnd) {
        window.autoSaveUpdateWorkpointWorkingHours(date, workStart, workEnd);
    }
};

window.updateWorkpointRecurring = function(date, isRecurring) {
    if (!window.workpointTimeOffDetails[date]) {
        window.workpointTimeOffDetails[date] = {};
    }
    window.workpointTimeOffDetails[date].isRecurring = isRecurring;
    window.autoSaveUpdateWorkpointRecurring(date, isRecurring);
    window.renderWorkpointHolidaysCalendar();
};

window.updateWorkpointDescription = function(date) {
    const descInput = document.getElementById(`wp-desc-${date}`);
    const description = descInput ? descInput.value : '';

    if (!window.workpointTimeOffDetails[date]) {
        window.workpointTimeOffDetails[date] = {};
    }
    window.workpointTimeOffDetails[date].description = description;
    window.autoSaveUpdateWorkpointDescription(date, description);
};

// Auto-save functions
window.autoSaveAddWorkpointHoliday = function(date) {
    const formData = new FormData();
    formData.append('action', 'add_full_day');
    formData.append('workingpoint_id', window.workpointHolidaysWorkpointId);
    formData.append('date', date);

    fetch('admin/workpoint_time_off_auto_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .catch(error => console.error('Error:', error));
};

window.autoSaveRemoveWorkpointHoliday = function(date) {
    const formData = new FormData();
    formData.append('action', 'remove_day');
    formData.append('workingpoint_id', window.workpointHolidaysWorkpointId);
    formData.append('date', date);

    fetch('admin/workpoint_time_off_auto_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .catch(error => console.error('Error:', error));
};

window.autoSaveUpdateWorkpointWorkingHours = function(date, workStart, workEnd) {
    const formData = new FormData();
    formData.append('action', 'update_working_hours');
    formData.append('workingpoint_id', window.workpointHolidaysWorkpointId);
    formData.append('date', date);
    formData.append('work_start', workStart);
    formData.append('work_end', workEnd);

    fetch('admin/workpoint_time_off_auto_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .catch(error => console.error('Error:', error));
};

window.autoSaveUpdateWorkpointRecurring = function(date, isRecurring) {
    const formData = new FormData();
    formData.append('action', 'update_recurring');
    formData.append('workingpoint_id', window.workpointHolidaysWorkpointId);
    formData.append('date', date);
    formData.append('is_recurring', isRecurring ? '1' : '0');

    fetch('admin/workpoint_time_off_auto_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .catch(error => console.error('Error:', error));
};

window.autoSaveUpdateWorkpointDescription = function(date, description) {
    const formData = new FormData();
    formData.append('action', 'update_description');
    formData.append('workingpoint_id', window.workpointHolidaysWorkpointId);
    formData.append('date', date);
    formData.append('description', description);

    fetch('admin/workpoint_time_off_auto_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .catch(error => console.error('Error:', error));
};

// Replace the lazy loading wrapper with the real function after loading
window.openWorkpointHolidaysModal = window.openWorkpointHolidaysModalReal;

// Initialize on load
console.log('Workpoint Holidays Modal loaded successfully');