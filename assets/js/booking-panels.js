// Booking Panels Module
// Handles search, arrivals, and canceled booking panels

(function(window) {
    'use strict';

    // Configuration
    let supervisorMode = false;
    let workpointId = null;
    let specialistId = null;
    let specialistColor = '#667eea';
    let specialistFgColor = '#ffffff';

    // Initialize the module with configuration
    function initialize(config) {
        supervisorMode = config.supervisorMode || false;
        workpointId = config.workpointId || null;
        specialistId = config.specialistId || null;
        specialistColor = config.specialistColor || '#667eea';
        specialistFgColor = config.specialistFgColor || '#ffffff';
    }

    // Update quick navigation buttons
    function updateQuickNavButtons(currentPanel) {
        const quickNavButtons = document.getElementById('quickNavButtons');
        if (!quickNavButtons) return;

        quickNavButtons.innerHTML = '';

        const buttons = [
            { id: 'search', icon: 'fa-search', label: 'Search', action: 'showSearchPanel' },
            { id: 'arrivals', icon: 'fa-clock', label: 'Arrivals', action: 'showArrivalsPanel' },
            { id: 'canceled', icon: 'fa-ban', label: 'Canceled', action: 'showCanceledPanel' }
        ];

        buttons.forEach(btn => {
            if (btn.id !== currentPanel) {
                const button = document.createElement('button');
                button.className = 'btn btn-sm btn-outline-secondary';
                button.style.padding = '4px 8px';
                button.style.fontSize = '12px';
                button.innerHTML = `<i class="fas ${btn.icon}"></i> ${btn.label}`;
                button.onclick = () => window.BookingPanels[btn.action]();
                quickNavButtons.appendChild(button);
            }
        });
    }

    // Show search panel
    function showSearchPanel() {
        document.getElementById('panelTitle').textContent = 'Search Bookings';
        updateQuickNavButtons('search');
        document.getElementById('panelContent').innerHTML = `
            <div class="search-container">
                <div class="mb-3">
                    <label for="searchInput" class="form-label">Search by Name or Booking ID</label>
                    <input type="text" class="form-control" id="searchInput"
                           placeholder="Enter name or ID number..."
                           onkeyup="BookingPanels.performSearch()">
                </div>
                <div id="searchResults">
                    <p class="text-muted">Enter a search term to find bookings...</p>
                </div>
            </div>
        `;
        openRightPanel();
    }

    // Show arrivals panel
    function showArrivalsPanel() {
        document.getElementById('panelTitle').textContent = 'Recent Arrivals';
        updateQuickNavButtons('arrivals');
        document.getElementById('panelContent').innerHTML =
            '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading arrivals...</div>';
        openRightPanel();

        // Build URL based on mode
        const url = supervisorMode ?
            `ajax/get_arrivals.php?mode=supervisor&workpoint_id=${workpointId}` :
            `ajax/get_arrivals.php?mode=specialist&specialist_id=${specialistId}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                displayArrivals(data);
            })
            .catch(error => {
                console.error('Error fetching arrivals:', error);
                document.getElementById('panelContent').innerHTML =
                    '<div class="alert alert-danger">Failed to load arrivals</div>';
            });
    }

    // Show canceled panel
    function showCanceledPanel() {
        document.getElementById('panelTitle').textContent = 'Canceled Bookings';
        updateQuickNavButtons('canceled');
        document.getElementById('panelContent').innerHTML =
            '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading canceled bookings...</div>';
        openRightPanel();

        // Build URL based on mode
        const url = supervisorMode ?
            `ajax/get_canceled.php?mode=supervisor&workpoint_id=${workpointId}` :
            `ajax/get_canceled.php?mode=specialist&specialist_id=${specialistId}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                displayCanceled(data);
            })
            .catch(error => {
                console.error('Error fetching canceled bookings:', error);
                document.getElementById('panelContent').innerHTML =
                    '<div class="alert alert-danger">Failed to load canceled bookings</div>';
            });
    }

    // Open right panel
    function openRightPanel() {
        const panel = document.getElementById('rightSidePanel');
        if (panel) {
            panel.style.left = '0';
        }
    }

    // Close right panel
    function closeRightPanel() {
        const panel = document.getElementById('rightSidePanel');
        if (panel) {
            panel.style.left = '-472px';
        }
    }

    // Perform search
    function performSearch() {
        const searchInput = document.getElementById('searchInput');
        if (!searchInput) return;

        const searchTerm = searchInput.value.trim();
        if (searchTerm.length < 2) {
            document.getElementById('searchResults').innerHTML =
                '<p class="text-muted">Enter at least 2 characters to search...</p>';
            return;
        }

        // Build URL based on mode
        const url = supervisorMode ?
            `ajax/search_bookings.php?mode=supervisor&workpoint_id=${workpointId}&search=${encodeURIComponent(searchTerm)}` :
            `ajax/search_bookings.php?mode=specialist&specialist_id=${specialistId}&search=${encodeURIComponent(searchTerm)}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                displaySearchResults(data);
            })
            .catch(error => {
                console.error('Error searching:', error);
                document.getElementById('searchResults').innerHTML =
                    '<div class="alert alert-danger">Search failed</div>';
            });
    }

    // Display arrivals
    function displayArrivals(data) {
        if (!data.bookings || data.bookings.length === 0) {
            document.getElementById('panelContent').innerHTML =
                '<div class="alert alert-info">No arrivals found</div>';
            return;
        }

        let html = '<div class="arrivals-list">';

        // Group bookings by time categories
        const hot = data.bookings.filter(b => b.category === 'hot');
        const mild = data.bookings.filter(b => b.category === 'mild');
        const recent = data.bookings.filter(b => b.category === 'recent');
        const older = data.bookings.filter(b => b.category === 'older');

        // Display sections with neutral gray background
        if (hot.length > 0) {
            html += `<div class="arrival-section" style="background-color: #f8f9fa; padding: 10px; margin-bottom: 15px; border-radius: 8px;">
                     <h6 style="color: #495057; margin-bottom: 10px;"><i class="fas fa-fire"></i> Last 2 Hours</h6>`;
            hot.forEach(booking => {
                html += formatBookingCard(booking, '#f5f5f5');
            });
            html += '</div>';
        }

        if (mild.length > 0) {
            html += `<div class="arrival-section" style="background-color: #f8f9fa; padding: 10px; margin-bottom: 15px; border-radius: 8px;">
                     <h6 style="color: #495057; margin-bottom: 10px;"><i class="fas fa-clock"></i> Last 6 Hours</h6>`;
            mild.forEach(booking => {
                html += formatBookingCard(booking, '#f5f5f5');
            });
            html += '</div>';
        }

        if (recent.length > 0) {
            html += `<div class="arrival-section" style="background-color: #f8f9fa; padding: 10px; margin-bottom: 15px; border-radius: 8px;">
                     <h6 style="color: #495057; margin-bottom: 10px;"><i class="fas fa-calendar-day"></i> Last 24 Hours</h6>`;
            recent.forEach(booking => {
                html += formatBookingCard(booking, '#f5f5f5');
            });
            html += '</div>';
        }

        if (older.length > 0) {
            html += `<div class="arrival-section" style="background-color: #f8f9fa; padding: 10px; margin-bottom: 15px; border-radius: 8px;">
                     <h6 style="color: #495057; margin-bottom: 10px;"><i class="fas fa-history"></i> Older</h6>`;
            older.forEach(booking => {
                html += formatBookingCard(booking, '#f5f5f5');
            });
            html += '</div>';
        }

        html += '</div>';
        document.getElementById('panelContent').innerHTML = html;

        // Initialize tooltips
        initializeTooltips('#panelContent');
    }

    // Display canceled bookings
    function displayCanceled(data) {
        if (!data.bookings || data.bookings.length === 0) {
            document.getElementById('panelContent').innerHTML =
                '<div class="alert alert-info">No canceled bookings found</div>';
            return;
        }

        let html = '<div class="canceled-list">';
        data.bookings.forEach(booking => {
            html += formatBookingCard(booking, '#f5f5f5', true);
        });
        html += '</div>';

        document.getElementById('panelContent').innerHTML = html;

        // Initialize tooltips
        initializeTooltips('#panelContent');
    }

    // Display search results
    function displaySearchResults(data) {
        const searchResults = document.getElementById('searchResults');
        if (!searchResults) return;

        if (!data.bookings || data.bookings.length === 0) {
            searchResults.innerHTML = '<div class="alert alert-info">No bookings found</div>';
            return;
        }

        // Get search term for highlighting
        const searchTerm = document.getElementById('searchInput').value.trim();
        const isIdSearch = /^\d+$/.test(searchTerm);

        let html = '<div class="search-results-list">';
        html += `<p class="text-muted mb-3">Found ${data.bookings.length} booking(s)</p>`;

        data.bookings.forEach(booking => {
            // Add match score indicator if available
            if (booking.match_score) {
                let scoreLabel = '';
                let scoreColor = '';
                if (booking.match_score === 10) {
                    scoreLabel = 'Exact Match';
                    scoreColor = '#28a745';
                } else if (booking.match_score >= 6) {
                    scoreLabel = 'Good Match';
                    scoreColor = '#17a2b8';
                } else {
                    scoreLabel = 'Partial Match';
                    scoreColor = '#ffc107';
                }
                html += `<div style="margin-bottom: 5px;">
                    <span style="font-size: 11px; color: ${scoreColor}; font-weight: 500;">
                        <i class="fas fa-check-circle"></i> ${scoreLabel}
                    </span>
                </div>`;
            }

            // Check if this is a canceled booking or past booking
            const isCanceled = booking.booking_status === 'canceled';
            const isPast = booking.time_status === 'past' && !isCanceled;
            // Use gray background for past bookings
            const bgColor = isPast ? '#e9ecef' : '#f5f5f5';
            html += formatBookingCard(booking, bgColor, isCanceled, searchTerm, isIdSearch);
        });
        html += '</div>';

        searchResults.innerHTML = html;

        // Initialize tooltips
        initializeTooltips('#searchResults');
    }

    // Format booking card
    function formatBookingCard(booking, bgColor, isCanceled = false, searchTerm = '', isIdSearch = false) {
        const bookingDate = new Date(booking.booking_start_datetime);
        const currentYear = new Date().getFullYear();

        // Format date
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        const time = bookingDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
        const dayName = days[bookingDate.getDay()];
        const day = String(bookingDate.getDate()).padStart(2, '0');
        const month = months[bookingDate.getMonth()];
        const year = bookingDate.getFullYear();

        let dateStr = `${time} ${dayName} ${day}.${month}`;
        if (year !== currentYear) {
            dateStr += ` ${String(year).slice(-2)}`;
        }

        // Calculate time since creation
        let timeSinceText = '';
        let timeColor = '#666';

        if ((booking.hours_since_creation !== undefined && booking.hours_since_creation !== null) ||
            (searchTerm && booking.day_of_creation)) {

            let hours;
            if (booking.hours_since_creation !== undefined && booking.hours_since_creation !== null) {
                hours = parseFloat(booking.hours_since_creation);
            } else if (booking.day_of_creation) {
                const creationDate = new Date(booking.day_of_creation);
                const now = new Date();
                hours = (now - creationDate) / (1000 * 60 * 60);
            }

            timeSinceText = formatTimeSince(hours);
            timeColor = getTimeColor(hours);
        }

        // Format service name
        const serviceName = booking.service_name ?
            booking.service_name.charAt(0).toUpperCase() + booking.service_name.slice(1).toLowerCase() :
            'No Service';

        // Get specialist color
        const specColor = supervisorMode ? (booking.specialist_color || '#667eea') : specialistColor;

        // Generate unique ID for this card
        const cardId = 'booking-' + booking.unic_id + '-' + Math.random().toString(36).substr(2, 9);

        // Highlight search term
        let displayName = booking.client_full_name || 'No Name';
        let displayId = booking.unic_id;

        if (searchTerm && !isIdSearch && booking.client_full_name) {
            const searchWords = searchTerm.split(' ').filter(w => w.length > 0);
            searchWords.forEach(word => {
                const regex = new RegExp(`(${word})`, 'gi');
                displayName = displayName.replace(regex, '<strong>$1</strong>');
            });
        } else if (searchTerm && isIdSearch && booking.unic_id.toString() === searchTerm) {
            displayId = `<strong>${displayId}</strong>`;
        }

        // Check if past booking
        const isPast = booking.time_status === 'past' && !isCanceled;

        // Build the card HTML
        return buildBookingCardHTML({
            cardId,
            cardOuterStyle: getCardOuterStyle(isPast, searchTerm, bgColor),
            cardInnerStyle: getCardInnerStyle(isPast, specColor),
            tooltipText: getTooltipText(isCanceled, isPast),
            isCanceled,
            displayId,
            displayName,
            booking,
            specColor,
            dateStr,
            serviceName,
            timeColor,
            timeSinceText
        });
    }

    // Format time since
    function formatTimeSince(hours) {
        if (hours < 1) {
            const minutes = Math.round(hours * 60);
            return `${minutes} minute${minutes !== 1 ? 's' : ''} ago`;
        } else if (hours < 7) {
            const wholeHours = Math.floor(hours);
            const minutes = Math.round((hours - wholeHours) * 60);
            if (minutes === 0) {
                return `${wholeHours}h ago`;
            } else {
                return `${wholeHours}h${minutes} ago`;
            }
        } else if (hours < 24) {
            const wholeHours = Math.round(hours);
            return `${wholeHours}h ago`;
        } else {
            const days = Math.round(hours / 24);
            return `${days} day${days !== 1 ? 's' : ''} ago`;
        }
    }

    // Get time color based on hours
    function getTimeColor(hours) {
        if (hours < 1 || hours <= 2) {
            return '#d32f2f'; // Red for hot
        } else if (hours <= 6) {
            return '#f57c00'; // Orange for mild
        } else if (hours < 24) {
            return '#7b1fa2'; // Purple for recent
        } else {
            return '#616161'; // Grey for older
        }
    }

    // Get card outer style
    function getCardOuterStyle(isPast, searchTerm, bgColor) {
        if (isPast && searchTerm) {
            return `background-color: #ced4da; padding: 3px; margin-bottom: 8px; border-radius: 6px;`;
        } else if (isPast) {
            return `background-color: ${bgColor}; padding: 3px; margin-bottom: 8px; border-radius: 6px; border: 1px solid #6c757d;`;
        } else {
            return `background-color: ${bgColor}; padding: 3px; margin-bottom: 8px; border-radius: 6px;`;
        }
    }

    // Get card inner style
    function getCardInnerStyle(isPast, specColor) {
        if (isPast) {
            return `background-color: #f8f9fa; padding: 10px; border-radius: 4px; border-left: 4px solid #6c757d; cursor: pointer; position: relative; opacity: 0.85;`;
        } else {
            return `background-color: white; padding: 10px; border-radius: 4px; border-left: 4px solid ${specColor}; cursor: pointer; position: relative;`;
        }
    }

    // Get tooltip text
    function getTooltipText(isCanceled, isPast) {
        if (isCanceled) {
            return 'CANCELED Booking - Click to view';
        } else if (isPast) {
            return 'PAST Booking - Click to view';
        } else {
            return 'ACTIVE Booking - Click to view';
        }
    }

    // Build booking card HTML
    function buildBookingCardHTML(params) {
        const { cardId, cardOuterStyle, cardInnerStyle, tooltipText, isCanceled, displayId,
                displayName, booking, specColor, dateStr, serviceName, timeColor, timeSinceText } = params;

        let canceledTooltip = '';
        if (isCanceled && booking.cancellation_time) {
            const cancelDate = new Date(booking.cancellation_time);
            canceledTooltip = `data-bs-toggle="tooltip" data-bs-placement="top"
                               title="Canceled on ${cancelDate.toLocaleDateString('en-GB', {
                                   day: '2-digit', month: 'short', year: 'numeric',
                                   hour: '2-digit', minute: '2-digit'
                               })}${booking.canceled_by ? ' by ' + booking.canceled_by : ''}"`;
        }

        const isPast = booking.time_status === 'past' && !isCanceled;

        return `
            <div class="booking-card-outer" style="${cardOuterStyle}">
                <div class="booking-card" style="${cardInnerStyle}"
                     onclick="BookingPanels.toggleBookingDetails('${cardId}')"
                     data-bs-toggle="tooltip"
                     data-bs-placement="top"
                     title="${tooltipText}">
                <!-- Two-line summary view -->
                <div class="booking-summary">
                    <!-- Line 1: ID Name • phone and received_through -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                        <div style="font-size: 14px; font-weight: 600; color: #333; ${isCanceled ? 'text-decoration: line-through; cursor: help;' : ''}"
                             ${canceledTooltip}>
                            <span style="color: #999; font-weight: normal;">#${displayId}</span> ${displayName}
                            ${booking.client_phone_nr ? `• <i class="fas fa-phone" style="color: ${specColor}; font-size: 12px;"></i> ${booking.client_phone_nr}` : ''}
                        </div>
                        <div style="font-size: 12px; color: #666;">
                            ${isPast ? '<i class="fas fa-check-circle" style="color: #6c757d;" title="Completed"></i> ' : ''}
                            ${booking.source || booking.received_through || 'Direct'}
                        </div>
                    </div>

                    <!-- Line 2: Date, Service and Time since arrival -->
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="font-size: 12px; color: #666;">
                            ${dateStr} • ${serviceName}
                        </div>
                        <div style="font-size: 12px; color: ${timeColor}; font-weight: 600;">
                            ${isCanceled && booking.hours_since_cancellation !== undefined ? 'Canceled ' : ''}${timeSinceText}
                        </div>
                    </div>
                </div>

                <!-- Expandable details section -->
                <div id="${cardId}" class="booking-details" style="display: none; margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ddd;">
                    ${buildDetailsSection(booking, specColor, isCanceled)}
                </div>

                <!-- Dropdown indicator -->
                <div style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); color: #999; font-size: 10px;">
                    <i class="fas fa-chevron-down" id="chevron-${cardId}" style="transition: transform 0.2s;"></i>
                </div>
            </div>
            </div>
        `;
    }

    // Build details section
    function buildDetailsSection(booking, specColor, isCanceled) {
        let html = '';

        // Specialist info with calendar links
        html += `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <div style="font-size: 12px;">`;

        if (supervisorMode) {
            const fgColor = booking.specialist_fg_color || '#fff';
            const speciality = booking.specialist_speciality ?
                booking.specialist_speciality.charAt(0).toUpperCase() + booking.specialist_speciality.slice(1).toLowerCase() :
                'Specialist';

            html += `<span style="display: inline-block; padding: 2px 8px; background-color: ${specColor};
                           color: ${fgColor}; border-radius: 3px; cursor: help;"
                           data-bs-toggle="tooltip" data-bs-placement="top" title="${speciality}">
                        <i class="fas fa-user-md"></i> ${booking.specialist_name}
                     </span>`;
        } else {
            html += `<span style="display: inline-block; padding: 2px 8px; background-color: ${specialistColor};
                           color: ${specialistFgColor}; border-radius: 3px;">
                        <i class="fas fa-user-md"></i> Specialist
                     </span>`;
        }

        html += `
                </div>
                <div>
                    <a href="#" onclick="event.stopPropagation(); BookingPanels.navigateToBookingDate('${booking.booking_date}', 'today', '${booking.id_specialist}')"
                       style="text-decoration: none; margin-right: 10px; font-size: 20px;"
                       data-bs-toggle="tooltip" data-bs-placement="top" title="View in Daily Calendar">
                        📋
                    </a>
                    <a href="#" onclick="event.stopPropagation(); BookingPanels.navigateToBookingDate('${booking.booking_date}', 'this_week', '${booking.id_specialist}')"
                       style="text-decoration: none; font-size: 20px;"
                       data-bs-toggle="tooltip" data-bs-placement="top" title="View in Weekly Calendar">
                        📆
                    </a>
                </div>
            </div>`;

        if (isCanceled && booking.booking_status_text) {
            html += `
                <div style="font-size: 11px; color: #dc3545; margin-bottom: 5px;">
                    <i class="fas fa-ban"></i> ${booking.booking_status_text}
                </div>`;
        }

        return html;
    }

    // Toggle booking details
    function toggleBookingDetails(cardId) {
        const details = document.getElementById(cardId);
        const chevron = document.getElementById('chevron-' + cardId);

        if (!details || !chevron) return;

        if (details.style.display === 'none') {
            details.style.display = 'block';
            chevron.style.transform = 'rotate(180deg)';
            // Initialize tooltips for the newly revealed content
            setTimeout(() => {
                const tooltipTriggerList = details.querySelectorAll('[data-bs-toggle="tooltip"]');
                [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
            }, 50);
        } else {
            details.style.display = 'none';
            chevron.style.transform = 'rotate(0deg)';
            // Dispose tooltips when hiding
            const tooltipTriggerList = details.querySelectorAll('[data-bs-toggle="tooltip"]');
            [...tooltipTriggerList].forEach(tooltipTriggerEl => {
                const tooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                if (tooltip) tooltip.dispose();
            });
        }
    }

    // Navigate to booking date
    function navigateToBookingDate(bookingDate, period = 'custom', specialistId = null) {
        // In supervisor mode, stay in supervisor mode
        let baseUrl = supervisorMode ?
            `booking_supervisor_view.php?working_point_user_id=${workpointId}` :
            `booking_view_page.php?specialist_id=${specialistId}`;

        // Close the panel
        closeRightPanel();

        // Navigate with the selected period
        if (period === 'custom') {
            window.location.href = `${baseUrl}&date=${bookingDate}`;
        } else {
            window.location.href = `${baseUrl}&period=${period}&highlight_date=${bookingDate}`;
        }
    }

    // Initialize tooltips helper
    function initializeTooltips(containerSelector) {
        setTimeout(() => {
            const container = document.querySelector(containerSelector);
            if (container) {
                const tooltipTriggerList = container.querySelectorAll('[data-bs-toggle="tooltip"]');
                [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
            }
        }, 50);
    }

    // Export functions
    window.BookingPanels = {
        initialize,
        showSearchPanel,
        showArrivalsPanel,
        showCanceledPanel,
        openRightPanel,
        closeRightPanel,
        performSearch,
        toggleBookingDetails,
        navigateToBookingDate
    };

})(window);