// Calendar Navigation Module
// Handles period changes, time updates, and specialist selection

(function(window) {
    'use strict';

    // Period change function
    window.changePeriod = function(period, event) {
        // Prevent any form submission
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        // Build URL without unnecessary parameters for predefined periods
        const url = new URL(window.location);

        // Make sure we stay on the supervisor view page
        if (!window.location.pathname.includes('booking_supervisor_view.php')) {
            console.error('WARNING: Not on supervisor view page! Redirecting...');
            url.pathname = '/booking_supervisor_view.php';
        }

        url.searchParams.set('period', period);

        // Handle supervisor mode - get from PHP variable if available
        const workingPointUserId = url.searchParams.get('working_point_user_id') || '';
        if (workingPointUserId) {
            url.searchParams.set('working_point_user_id', workingPointUserId);
        }

        // Always include selected specialist in supervisor mode
        const currentSelectedSpecialist = url.searchParams.get('selected_specialist') || '';
        if (currentSelectedSpecialist) {
            url.searchParams.set('selected_specialist', currentSelectedSpecialist);
        }

        // Remove any custom date parameters for predefined periods
        url.searchParams.delete('start_date');
        url.searchParams.delete('end_date');

        window.location.href = url.toString();
    };

    // Update current time display
    window.updateTime = function() {
        const timeElement = document.getElementById('currentTime');
        if (!timeElement) return;

        const now = new Date();

        // Get timezone from global variable (set by PHP)
        const timezone = window.organizationTimezone || 'UTC';

        const timeString = now.toLocaleTimeString('en-US', {
            timeZone: timezone,
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });

        const dateString = now.toLocaleDateString('en-US', {
            timeZone: timezone,
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        timeElement.innerHTML = `${dateString} - ${timeString}`;
    };

    // Show period selector dropdown
    window.showPeriodSelector = function(event) {
        event.preventDefault();
        event.stopPropagation();

        const dropdown = document.getElementById('periodDropdown');
        if (!dropdown) return;

        if (dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
        } else {
            dropdown.style.display = 'block';
            // Position the dropdown below the button
            const button = event.target.closest('button');
            if (button) {
                const rect = button.getBoundingClientRect();
                dropdown.style.left = rect.left + 'px';
                dropdown.style.top = (rect.bottom + 5) + 'px';
            }
        }
    };

    // Select a specialist (for supervisor mode)
    window.selectSpecialist = function(specialistId) {
        const url = new URL(window.location);

        if (specialistId) {
            url.searchParams.set('selected_specialist', specialistId);
        } else {
            url.searchParams.delete('selected_specialist');
        }

        // Preserve other parameters
        window.location.href = url.toString();
    };

    // Setup period selectors on page load
    window.setupPeriodSelectors = function() {
        // Add event listeners for period buttons
        const periodButtons = document.querySelectorAll('[data-period]');
        periodButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const period = this.getAttribute('data-period');
                if (period) {
                    changePeriod(period, e);
                }
            });
        });

        // Setup custom date form if present
        const customDateForm = document.getElementById('customDateForm');
        if (customDateForm) {
            customDateForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;

                if (!startDate || !endDate) {
                    alert('Please select both start and end dates');
                    return;
                }

                if (startDate > endDate) {
                    alert('Start date must be before end date');
                    return;
                }

                const url = new URL(window.location);
                url.searchParams.set('period', 'custom');
                url.searchParams.set('start_date', startDate);
                url.searchParams.set('end_date', endDate);

                window.location.href = url.toString();
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('periodDropdown');
            const button = document.querySelector('[onclick*="showPeriodSelector"]');

            if (dropdown && !dropdown.contains(e.target) && !button.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    };

    // Initialize time update interval
    function initializeTimeUpdates() {
        // Update time immediately
        updateTime();

        // Update every second
        setInterval(updateTime, 1000);
    }

    // Navigate to a specific date in the calendar
    window.navigateToDate = function(date) {
        const url = new URL(window.location);

        // Set period to custom
        url.searchParams.set('period', 'custom');

        // Set the date range to show the week containing this date
        const targetDate = new Date(date);
        const dayOfWeek = targetDate.getDay();
        const monday = new Date(targetDate);
        monday.setDate(targetDate.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1));
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);

        // Format dates as YYYY-MM-DD
        const formatDate = (d) => {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        url.searchParams.set('start_date', formatDate(monday));
        url.searchParams.set('end_date', formatDate(sunday));

        window.location.href = url.toString();
    };

    // Export functions for external use
    window.CalendarNavigation = {
        changePeriod: changePeriod,
        updateTime: updateTime,
        showPeriodSelector: showPeriodSelector,
        selectSpecialist: selectSpecialist,
        setupPeriodSelectors: setupPeriodSelectors,
        navigateToDate: navigateToDate,
        initialize: function() {
            setupPeriodSelectors();
            initializeTimeUpdates();
        }
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            CalendarNavigation.initialize();
        });
    } else {
        // DOM is already loaded
        CalendarNavigation.initialize();
    }

})(window);