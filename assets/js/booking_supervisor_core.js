/**
 * Booking Supervisor Core JavaScript Functions
 * Pure JavaScript functions that don't depend on PHP variables
 */

// ===== Google Calendar Integration Functions =====

// Ensure Google Calendar functions are always available
function postJSON(url, data) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: new URLSearchParams(data),
        credentials: 'same-origin' // Include cookies for session
    }).then(r => {
        // First check if response is ok
        if (!r.ok) {
            throw new Error(`HTTP error! status: ${r.status}`);
        }
        // Try to get text first to debug
        return r.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid JSON response: ' + text.substring(0, 200));
            }
        });
    });
}

window.gcalOpenConnect = function(specialistId) {
    // Show loading indicator
    const btn = event ? event.target : null;
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connecting...';
    }

    postJSON('ajax/google_calendar_init.php', { specialist_id: specialistId })
        .then(result => {
            if (result.auth_url) {
                window.open(result.auth_url, '_blank');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fab fa-google"></i> Connect Google Calendar';
                }
            } else {
                throw new Error(result.error || 'Failed to get authorization URL');
            }
        })
        .catch(error => {
            console.error('Failed to initialize Google Calendar:', error);
            alert('Failed to connect to Google Calendar: ' + error.message);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fab fa-google"></i> Connect Google Calendar';
            }
        });
};

window.gcalDisconnect = function(specialistId) {
    if (!confirm('Are you sure you want to disconnect Google Calendar?')) return;

    const btn = event ? event.target : null;
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Disconnecting...';
    }

    postJSON('ajax/google_calendar_disconnect.php', { specialist_id: specialistId })
        .then(result => {
            if (result.success) {
                location.reload();
            } else {
                throw new Error(result.error || 'Failed to disconnect');
            }
        })
        .catch(error => {
            console.error('Failed to disconnect Google Calendar:', error);
            alert('Failed to disconnect Google Calendar: ' + error.message);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fab fa-google"></i> Disconnect';
            }
        });
};

window.gcalSync = function(specialistId) {
    const btn = event ? event.target : null;
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';
    }

    postJSON('ajax/google_calendar_sync.php', { specialist_id: specialistId })
        .then(result => {
            if (result.success) {
                alert(`Sync completed!\n${result.created} bookings created\n${result.updated} bookings updated\n${result.deleted} bookings deleted`);
                location.reload();
            } else {
                throw new Error(result.error || 'Sync failed');
            }
        })
        .catch(error => {
            console.error('Sync failed:', error);
            alert('Sync failed: ' + error.message);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync"></i> Sync Now';
            }
        });
};

// ===== Notification Functions =====

function showBookingNotification() {
    const urlParams = new URLSearchParams(window.location.search);
    const bookingAdded = urlParams.get('booking_added');
    const bookingUpdated = urlParams.get('booking_updated');

    if (!document.getElementById('notification-container')) {
        const container = document.createElement('div');
        container.id = 'notification-container';
        document.body.appendChild(container);
    }

    if (bookingAdded === '1' || bookingUpdated === '1') {
        const notification = document.createElement('div');
        notification.className = `booking-notification ${bookingUpdated ? 'update' : 'create'}`;

        notification.innerHTML = `
            <div class="notification-header">
                <div class="notification-icon ${bookingUpdated ? 'update' : 'create'}">
                    <i class="fas ${bookingUpdated ? 'fa-edit' : 'fa-check'}"></i>
                </div>
                <div>
                    <div style="font-size: 16px;">Booking ${bookingUpdated ? 'Updated' : 'Created'} Successfully!</div>
                    <div style="font-size: 12px; color: #666; margin-top: 4px;">
                        ${bookingUpdated ? 'The booking has been updated' : 'New booking has been added to the calendar'}
                    </div>
                </div>
            </div>
        `;

        document.getElementById('notification-container').appendChild(notification);

        // Remove notification after animation completes
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
}

// ===== Interactive Logo Animations =====

function initializeLogoAnimations() {
    const logoText = document.querySelector('.logo-text');
    const letters = document.querySelectorAll('.logo-letter');

    if (!logoText || !letters.length) return;

    // Add click animation to logo
    logoText.addEventListener('click', function() {
        letters.forEach((letter, index) => {
            setTimeout(() => {
                letter.style.transform = 'scale(1.2) rotate(10deg)';
                setTimeout(() => {
                    letter.style.transform = 'scale(1) rotate(0deg)';
                }, 200);
            }, index * 50);
        });
    });

    // Add random eye movements and expressions
    const beautyEyes = document.querySelectorAll('.logo-letter.beauty-eye');
    beautyEyes.forEach((eye, index) => {
        const iris = eye.querySelector('.iris');
        const eyelid = eye.querySelector('.eyelid');

        if (!iris || !eyelid) return;

        let cycleCount = 0;
        const maxCycles = 3;

        function moveEye() {
            if (cycleCount >= maxCycles) return;

            const randomX = (Math.random() - 0.5) * 4;
            const randomY = (Math.random() - 0.5) * 4;
            iris.style.transform = `translate(${randomX}px, ${randomY}px)`;

            const nextMove = Math.random() * 2000 + 1000;
            setTimeout(moveEye, nextMove);
        }

        function blink() {
            if (cycleCount >= maxCycles) {
                cycleCount = 0;
                setTimeout(startEyeMovement, 10000);
                return;
            }

            eyelid.style.transform = 'scaleY(1)';
            setTimeout(() => {
                eyelid.style.transform = 'scaleY(0)';
            }, 150);

            cycleCount++;
            const nextBlink = Math.random() * 3000 + 2000;
            setTimeout(blink, nextBlink);
        }

        function wink() {
            if (cycleCount >= maxCycles) return;
            if (Math.random() > 0.7 && index === 0) {
                eyelid.style.transform = 'scaleY(0.5)';
                setTimeout(() => {
                    eyelid.style.transform = 'scaleY(0)';
                }, 300);
            }
        }

        function startEyeMovement() {
            cycleCount = 0;
            setTimeout(moveEye, index * 500);
            setTimeout(blink, 1000 + index * 200);
            setInterval(wink, 5000);
        }

        startEyeMovement();
    });
}

// ===== Period Selector Functions =====

function setupPeriodSelectors() {
    const periodSelectors = document.querySelectorAll('.period-selector');
    periodSelectors.forEach(selector => {
        selector.addEventListener('change', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const selectedOption = this.options[this.selectedIndex];
            const period = selectedOption.value;

            if (period && period !== 'none') {
                changePeriod(period, e);
            }
        });
    });
}

// ===== Specialist Collapsible Functions =====

function setupSpecialistCollapsible() {
    const specialistHeader = document.querySelector('.specialist-section-header');
    const specialistCards = document.querySelector('.specialist-cards');
    const toggleIcon = document.querySelector('.toggle-icon');

    if (specialistHeader && specialistCards) {
        specialistHeader.addEventListener('click', function() {
            if (specialistCards.classList.contains('collapsed')) {
                specialistCards.classList.remove('collapsed');
                toggleIcon.style.transform = 'rotate(180deg)';
                localStorage.setItem('specialistSectionCollapsed', 'false');
            } else {
                specialistCards.classList.add('collapsed');
                toggleIcon.style.transform = 'rotate(0deg)';
                localStorage.setItem('specialistSectionCollapsed', 'true');
            }
        });

        // Restore collapsed state from localStorage
        const isCollapsed = localStorage.getItem('specialistSectionCollapsed') === 'true';
        if (isCollapsed) {
            specialistCards.classList.add('collapsed');
            toggleIcon.style.transform = 'rotate(0deg)';
        }
    }
}

// ===== Real-time Booking Updates =====

function initializeRealtimeBookings() {
    // Function body will be implemented based on requirements
    console.log('Real-time booking updates initialized');
}

// ===== Search Panel Functions =====

function showSearchPanel() {
    document.getElementById('panelTitle').textContent = 'Search Bookings';
    updateQuickNavButtons('search');

    const searchForm = `
        <div class="search-form">
            <div class="mb-3">
                <label class="form-label">Search by:</label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="searchType" id="searchByClient" value="client" checked>
                    <label class="btn btn-outline-primary" for="searchByClient">Client Name</label>

                    <input type="radio" class="btn-check" name="searchType" id="searchByPhone" value="phone">
                    <label class="btn btn-outline-primary" for="searchByPhone">Phone Number</label>

                    <input type="radio" class="btn-check" name="searchType" id="searchByService" value="service">
                    <label class="btn btn-outline-primary" for="searchByService">Service</label>
                </div>
            </div>

            <div class="mb-3">
                <input type="text" id="searchInput" class="form-control" placeholder="Enter search term..." autofocus>
            </div>

            <div class="mb-3">
                <button type="button" class="btn btn-primary w-100" onclick="performSearch()">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>

            <div id="searchResults"></div>
        </div>
    `;

    document.getElementById('panelContent').innerHTML = searchForm;
    openRightPanel();

    // Add enter key handler
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
}

function performSearch() {
    const searchType = document.querySelector('input[name="searchType"]:checked').value;
    const searchTerm = document.getElementById('searchInput').value.trim();

    if (!searchTerm) {
        alert('Please enter a search term');
        return;
    }

    document.getElementById('searchResults').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';

    // This function will need the appropriate URL based on supervisor mode
    // The URL will be set by PHP when the page loads
    const searchUrl = window.searchBookingsUrl || 'ajax/search_bookings.php';

    fetch(searchUrl + '?type=' + searchType + '&term=' + encodeURIComponent(searchTerm))
        .then(response => response.json())
        .then(data => {
            displaySearchResults(data);
        })
        .catch(error => {
            console.error('Search error:', error);
            document.getElementById('searchResults').innerHTML = '<div class="alert alert-danger">Search failed</div>';
        });
}

function displaySearchResults(results) {
    if (!results || results.length === 0) {
        document.getElementById('searchResults').innerHTML = '<div class="alert alert-info">No results found</div>';
        return;
    }

    let html = '<div class="search-results-list">';
    html += '<h6 class="mb-3">Found ' + results.length + ' booking(s)</h6>';

    results.forEach(booking => {
        const isPast = new Date(booking.booking_start_datetime) < new Date();
        const cardClass = isPast ? 'search-result-past' : 'search-result-future';

        html += formatBookingCard(booking, cardClass);
    });

    html += '</div>';
    document.getElementById('searchResults').innerHTML = html;
}

// ===== Panel Management Functions =====

function openRightPanel() {
    const panel = document.getElementById('rightSidePanel');
    if (panel) {
        panel.style.left = '0';
    }
}

function closeRightPanel() {
    const panel = document.getElementById('rightSidePanel');
    if (panel) {
        panel.style.left = '-472px';
    }
}

function updateQuickNavButtons(activeButton) {
    const buttons = ['search', 'arrivals', 'canceled'];

    buttons.forEach(buttonId => {
        const btn = document.querySelector(`[data-panel="${buttonId}"]`);
        if (btn) {
            if (buttonId === activeButton) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        }
    });
}

// ===== Utility Functions =====

function formatBookingCard(booking, backgroundColor = '#f5f5f5') {
    const startTime = new Date(booking.booking_start_datetime);
    const endTime = new Date(booking.booking_end_datetime);
    const createdTime = booking.day_of_creation ? new Date(booking.day_of_creation) : startTime;

    // Format times
    const timeStr = startTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) +
                   ' - ' +
                   endTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

    // Format date
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const bookingDate = new Date(startTime);
    bookingDate.setHours(0, 0, 0, 0);

    let dateStr = '';
    if (bookingDate.getTime() === today.getTime()) {
        dateStr = 'Today';
    } else if (bookingDate.getTime() === today.getTime() + 86400000) {
        dateStr = 'Tomorrow';
    } else {
        const dayDiff = Math.floor((bookingDate - today) / 86400000);
        if (dayDiff > 0 && dayDiff <= 7) {
            dateStr = startTime.toLocaleDateString('en-US', { weekday: 'long' });
        } else {
            dateStr = startTime.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            if (startTime.getFullYear() !== today.getFullYear()) {
                dateStr += ` ${String(startTime.getFullYear()).slice(-2)}`;
            }
        }
    }

    // Calculate time since creation
    const hoursSince = Math.abs(new Date() - createdTime) / 36e5;
    let timeSinceText = '';
    let timeColor = '#666';

    if (hoursSince < 1) {
        const minutes = Math.floor(hoursSince * 60);
        timeSinceText = `${minutes}m ago`;
        timeColor = '#d32f2f';
    } else if (hoursSince < 24) {
        timeSinceText = `${Math.floor(hoursSince)}h ago`;
        timeColor = hoursSince <= 2 ? '#d32f2f' : (hoursSince <= 6 ? '#ff6b35' : '#666');
    } else {
        const days = Math.floor(hoursSince / 24);
        timeSinceText = `${days}d ago`;
    }

    const serviceName = booking.service_name || booking.name_of_service || 'No service specified';

    return `
        <div class="booking-card" style="background: ${backgroundColor}; padding: 12px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #1976d2; cursor: pointer; transition: all 0.3s ease;"
             onclick="openBookingModal('${booking.client_full_name}', '${timeStr}', '${booking.specialist_name || ''}', ${booking.unic_id}, '${booking.client_phone_nr || ''}', '${serviceName}', '${startTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}', '${endTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}', '${startTime.toISOString().split('T')[0]}')"
             onmouseover="this.style.transform='translateX(5px)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)';"
             onmouseout="this.style.transform='translateX(0)'; this.style.boxShadow='none';">

            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; color: #333; font-size: 14px;">
                        ${booking.client_full_name}
                        ${booking.specialist_name ? `<span style="color: #666; font-weight: 400; margin-left: 8px;">(${booking.specialist_name})</span>` : ''}
                    </div>
                    ${booking.client_phone_nr ? `
                        <div style="font-size: 12px; color: #666; margin-top: 2px;">
                            <i class="fas fa-phone" style="font-size: 10px; margin-right: 4px;"></i>
                            ${booking.client_phone_nr}
                        </div>
                    ` : ''}
                </div>
                <div style="text-align: right;">
                    <span style="background: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                        #${booking.unic_id}
                    </span>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 12px; color: #666;">
                    ${dateStr} • ${serviceName}
                </div>
                <div style="font-size: 11px; color: ${timeColor}; font-weight: 600;">
                    ${timeSinceText}
                </div>
            </div>

            <div style="margin-top: 6px; padding-top: 6px; border-top: 1px solid #e0e0e0;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 13px; color: #333; font-weight: 500;">
                        <i class="fas fa-clock" style="color: #1976d2; margin-right: 4px;"></i>
                        ${timeStr}
                    </div>
                </div>
            </div>
        </div>
    `;
}

// ===== Initialize on Document Ready =====

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Show notification if there was a booking update
    showBookingNotification();

    // Setup various components
    setupPeriodSelectors();
    setupSpecialistCollapsible();
    initializeLogoAnimations();
    initializeRealtimeBookings();

    // Prevent form submission on load
    const periodForm = document.getElementById('periodForm');
    if (periodForm) {
        periodForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Form submission prevented');
            return false;
        });
    }
});