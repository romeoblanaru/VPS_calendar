// Statistics Modal Functions
// This file contains all Statistics modal functionality

// Store current date range
window.currentDateRange = {
    start: null,
    end: null,
    period: 'last30'
};

// Calculate date ranges based on period
window.calculateDateRange = function(period) {
    const today = new Date();
    let start = new Date();
    let end = new Date();

    switch(period) {
        case 'last30':
            start.setDate(today.getDate() - 30);
            end = new Date(today);
            break;
        case 'last90':
            start.setDate(today.getDate() - 90);
            end = new Date(today);
            break;
        case 'lastMonth':
            start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            end = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
        case 'last3Months':
            start = new Date(today.getFullYear(), today.getMonth() - 3, 1);
            end = new Date(today);
            break;
        case 'last12Months':
            start = new Date(today.getFullYear(), today.getMonth() - 12, 1);
            end = new Date(today);
            break;
        case 'next30':
            start = new Date(today);
            end.setDate(today.getDate() + 30);
            break;
        case 'next90':
            start = new Date(today);
            end.setDate(today.getDate() + 90);
            break;
        case 'nextMonth':
            start = new Date(today.getFullYear(), today.getMonth() + 1, 1);
            end = new Date(today.getFullYear(), today.getMonth() + 2, 0);
            break;
        case 'next3Months':
            start = new Date(today);
            end = new Date(today.getFullYear(), today.getMonth() + 3, today.getDate());
            break;
        case 'next12Months':
            start = new Date(today);
            end = new Date(today.getFullYear(), today.getMonth() + 12, today.getDate());
            break;
        default:
            start.setDate(today.getDate() - 30);
            end = new Date(today);
    }

    return {
        start: start.toISOString().split('T')[0],
        end: end.toISOString().split('T')[0]
    };
};

// Store the real implementation
window.openStatisticsModalReal = function() {
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    if (!workpointId) {
        // Try to get it from session if available
        const sessionWorkpointId = document.querySelector('input[name="workpoint_id"]')?.value;
        if (sessionWorkpointId) {
            window.currentWorkpointId = sessionWorkpointId;
        } else {
            alert('Please select a working point first');
            return;
        }
    }

    const modal = new bootstrap.Modal(document.getElementById('statisticsModal'));
    modal.show();

    // Initialize date range to last 30 days
    const dateRange = calculateDateRange('last30');
    window.currentDateRange = {
        start: dateRange.start,
        end: dateRange.end,
        period: 'last30'
    };

    // Update date display
    updateDateRangeDisplay();

    // Set up event listeners for period buttons
    setupPeriodButtons();

    // Load statistics
    loadStatistics();
};

// Set up period dropdown event listeners
window.setupPeriodButtons = function() {
    // Past period dropdown
    const pastSelect = document.getElementById('pastPeriodSelect');
    if (pastSelect) {
        pastSelect.addEventListener('change', function() {
            if (this.value) {
                // Clear future selection and custom dates
                const futureSelect = document.getElementById('futurePeriodSelect');
                if (futureSelect) futureSelect.value = '';
                document.getElementById('customDateFrom').value = '';
                document.getElementById('customDateTo').value = '';

                // Calculate new date range
                const dateRange = calculateDateRange(this.value);
                window.currentDateRange = {
                    start: dateRange.start,
                    end: dateRange.end,
                    period: this.value
                };

                // Update display
                updateDateRangeDisplay();

                // Reload statistics
                loadStatistics();
            }
        });
    }

    // Future period dropdown
    const futureSelect = document.getElementById('futurePeriodSelect');
    if (futureSelect) {
        futureSelect.addEventListener('change', function() {
            if (this.value) {
                // Clear past selection and custom dates
                const pastSelect = document.getElementById('pastPeriodSelect');
                if (pastSelect) pastSelect.value = '';
                document.getElementById('customDateFrom').value = '';
                document.getElementById('customDateTo').value = '';

                // Calculate new date range
                const dateRange = calculateDateRange(this.value);
                window.currentDateRange = {
                    start: dateRange.start,
                    end: dateRange.end,
                    period: this.value
                };

                // Update display
                updateDateRangeDisplay();

                // Reload statistics
                loadStatistics();
            }
        });
    }
};

// Apply custom date range
window.applyCustomDateRange = function() {
    const fromDate = document.getElementById('customDateFrom').value;
    const toDate = document.getElementById('customDateTo').value;

    if (!fromDate || !toDate) {
        alert('Please select both start and end dates');
        return;
    }

    if (new Date(fromDate) > new Date(toDate)) {
        alert('Start date must be before end date');
        return;
    }

    // Clear both dropdown selections
    const pastSelect = document.getElementById('pastPeriodSelect');
    const futureSelect = document.getElementById('futurePeriodSelect');
    if (pastSelect) pastSelect.value = '';
    if (futureSelect) futureSelect.value = '';

    window.currentDateRange = {
        start: fromDate,
        end: toDate,
        period: 'custom'
    };

    // Update display
    updateDateRangeDisplay();

    // Reload statistics
    loadStatistics();
};

// Update date range display
window.updateDateRangeDisplay = function() {
    const periodDisplay = document.getElementById('currentPeriodDisplay');
    const dateRangeDisplay = document.getElementById('dateRangeDisplay');

    const periodNames = {
        'last30': 'Last 30 Days',
        'last90': 'Last 90 Days',
        'lastMonth': 'Last Month',
        'last3Months': 'Last 3 Months',
        'last12Months': 'Last 12 Months',
        'next30': 'Next 30 Days',
        'next90': 'Next 90 Days',
        'nextMonth': 'Next Month',
        'next3Months': 'Next 3 Months',
        'next12Months': 'Next 12 Months',
        'custom': 'Custom Range'
    };

    periodDisplay.textContent = periodNames[window.currentDateRange.period] || 'Custom Range';

    // Format dates for display
    const startDate = new Date(window.currentDateRange.start);
    const endDate = new Date(window.currentDateRange.end);
    const options = { year: 'numeric', month: 'short', day: 'numeric' };

    dateRangeDisplay.textContent = `(${startDate.toLocaleDateString('en-US', options)} - ${endDate.toLocaleDateString('en-US', options)})`;
};

// Load statistics for the current workpoint and date range
window.loadStatistics = function() {
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    // Load booking statistics first (it's the default tab now)
    loadBookingStatistics(workpointId);

    // Load specialist statistics
    loadSpecialistStatistics(workpointId);
};

// Load specialist statistics
window.loadSpecialistStatistics = function(workpointId) {
    const container = document.getElementById('specialistStats');
    container.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading statistics...</div>';

    const params = new URLSearchParams({
        workpoint_id: workpointId,
        start_date: window.currentDateRange.start,
        end_date: window.currentDateRange.end
    });

    fetch(`admin/get_specialist_statistics.php?${params}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displaySpecialistStatistics(data);
            } else {
                container.innerHTML = `<div class="text-danger"><i class="fas fa-exclamation-triangle"></i> Error: ${data.message || 'Failed to load statistics'}</div>`;
            }
        })
        .catch(error => {
            container.innerHTML = `<div class="text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading statistics</div>`;
        });
};

// Display specialist statistics
window.displaySpecialistStatistics = function(data) {
    let html = `
        <div class="row g-3">
            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-label">Total Specialists</div>
                    <div class="stat-value">${data.total_specialists || 0}</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-label">Active in Period</div>
                    <div class="stat-value">${data.active_in_period || 0}</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-label">Total Services</div>
                    <div class="stat-value">${data.total_services || 0}</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-label">Avg Services/Specialist</div>
                    <div class="stat-value">${data.avg_services_per_specialist || 0}</div>
                </div>
            </div>
        </div>
    `;

    // Add specialist breakdown if available
    if (data.specialists && data.specialists.length > 0) {
        html += `
            <div class="stat-card mt-4">
                <h6 class="mb-3"><i class="fas fa-users"></i> Specialist Performance</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Specialist</th>
                                <th>Speciality</th>
                                <th class="text-center">Services</th>
                                <th class="text-center">Bookings</th>
                                <th class="text-center">Future</th>
                            </tr>
                        </thead>
                        <tbody>`;

        data.specialists.forEach(spec => {
            html += `
                <tr>
                    <td><strong>${spec.name}</strong></td>
                    <td class="text-muted">${spec.speciality}</td>
                    <td class="text-center">${spec.service_count || 0}</td>
                    <td class="text-center">${spec.booking_count || 0}</td>
                    <td class="text-center">${spec.future_bookings || 0}</td>
                </tr>`;
        });

        html += `
                        </tbody>
                    </table>
                </div>
            </div>`;
    }

    document.getElementById('specialistStats').innerHTML = html;
};

// Load booking statistics
window.loadBookingStatistics = function(workpointId) {
    const container = document.getElementById('bookingStats');
    container.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading statistics...</div>';

    const params = new URLSearchParams({
        workpoint_id: workpointId,
        start_date: window.currentDateRange.start,
        end_date: window.currentDateRange.end
    });

    fetch(`admin/get_booking_statistics.php?${params}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayBookingStatistics(data);
            } else {
                container.innerHTML = `<div class="text-danger"><i class="fas fa-exclamation-triangle"></i> Error: ${data.message || 'Failed to load statistics'}</div>`;
            }
        })
        .catch(error => {
            container.innerHTML = `<div class="text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading statistics</div>`;
        });
};

// Display booking statistics
window.displayBookingStatistics = function(data) {
    let html = `
        <div class="row g-3">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Total Bookings</div>
                    <div class="stat-value">${data.total_bookings || 0}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Average Per Day</div>
                    <div class="stat-value">${data.avg_per_day || 0}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Period Revenue</div>
                    <div class="stat-value">€${data.revenue?.period || '0.00'}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Avg Booking Value</div>
                    <div class="stat-value">€${data.avg_booking_value || '0.00'}</div>
                </div>
            </div>
        </div>
    `;

    // Add popular services if available
    if (data.popular_services && data.popular_services.length > 0) {
        html += `
            <div class="stat-card mt-4">
                <h6 class="mb-3"><i class="fas fa-star"></i> Top Services</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Service Name</th>
                                <th class="text-center">Bookings</th>
                                <th class="text-center">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>`;

        data.popular_services.forEach(service => {
            html += `
                <tr>
                    <td><strong>${service.name}</strong></td>
                    <td class="text-center">${service.booking_count || 0}</td>
                    <td class="text-center">€${service.revenue || '0.00'}</td>
                </tr>`;
        });

        html += `
                        </tbody>
                    </table>
                </div>
            </div>`;
    }

    // Add booking trends if available
    if (data.recent_trends && data.recent_trends.length > 0) {
        html += `
            <div class="stat-card mt-4">
                <h6 class="mb-3"><i class="fas fa-chart-line"></i> Daily Trends</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th class="text-center">Bookings</th>
                            </tr>
                        </thead>
                        <tbody>`;

        data.recent_trends.forEach(trend => {
            const date = new Date(trend.date);
            const formattedDate = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            html += `
                <tr>
                    <td>${formattedDate}</td>
                    <td class="text-center">${trend.count || 0}</td>
                </tr>`;
        });

        html += `
                        </tbody>
                    </table>
                </div>
            </div>`;
    }

    // Add peak hours if available
    if (data.peak_hours && data.peak_hours.length > 0) {
        html += `
            <div class="stat-card mt-4">
                <h6 class="mb-3"><i class="fas fa-clock"></i> Peak Hours</h6>
                <div class="row">`;

        data.peak_hours.forEach(hour => {
            html += `
                <div class="col-4 text-center mb-2">
                    <div class="fw-bold">${hour.hour}</div>
                    <div class="text-muted small">${hour.count} bookings</div>
                </div>`;
        });

        html += `
                </div>
            </div>`;
    }

    document.getElementById('bookingStats').innerHTML = html;
};

// Export statistics to CSV/PDF
window.exportStatistics = function() {
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    if (!workpointId) {
        alert('Please select a working point first');
        return;
    }

    // For now, export as CSV
    const format = confirm('Export as PDF? (Cancel for CSV)') ? 'pdf' : 'csv';

    fetch(`admin/export_statistics.php?workpoint_id=${workpointId}&format=${format}`)
        .then(response => {
            if (response.ok) {
                return response.blob();
            } else {
                throw new Error('Failed to export statistics');
            }
        })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            const extension = format === 'pdf' ? 'pdf' : 'csv';
            a.download = `statistics_workpoint_${workpointId}_${new Date().toISOString().split('T')[0]}.${extension}`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        })
        .catch(error => {
            console.error('Error exporting statistics:', error);
            alert('Error exporting statistics. The export functionality may not be implemented yet.');
        });
};

// Replace the lazy loading wrapper with the real function after loading
window.openStatisticsModal = window.openStatisticsModalReal;