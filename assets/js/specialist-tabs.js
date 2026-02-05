// Specialist Tabs Module
// Handles tabbed interface for specialist cards (Schedule, Services, Permissions, Details)

(function(window) {
    'use strict';

    // Store active tabs for each specialist
    const activeTabs = {};

    /**
     * Initialize tabs for a specialist card
     * @param {string} specialistId - The specialist ID
     */
    function initializeSpecialistTabs(specialistId) {
        const card = document.querySelector(`[data-specialist-id="${specialistId}"]`);
        if (!card) return;

        // Check if tabs already initialized
        if (card.querySelector('.specialist-tabs')) return;

        // Get the existing schedule content div
        const scheduleContent = card.querySelector('.schedule-content');
        if (!scheduleContent) return;

        // Create tabs container
        const tabsContainer = document.createElement('div');
        tabsContainer.className = 'specialist-tabs-container';
        tabsContainer.style.display = scheduleContent.style.display;
        tabsContainer.style.paddingTop = '5px';
        tabsContainer.style.paddingLeft = '3px';
        tabsContainer.style.paddingRight = '3px';

        // Create tab navigation buttons directly
        const tabNav = document.createElement('div');
        tabNav.style.display = 'flex';
        tabNav.style.borderBottom = '1px solid #e0e0e0';
        tabNav.style.marginBottom = '6px';
        tabNav.innerHTML = `
            <button data-tab="schedule" data-specialist="${specialistId}"
                    style="flex: 1; padding: 3px 5px; background: transparent; border: none; border-bottom: 2px solid #007bff; color: #007bff; font-weight: 500; cursor: pointer; transition: all 0.3s; font-size: 0.65rem;">
                <i class="fas fa-calendar-alt" style="margin-right: 2px; font-size: 0.6rem;"></i>Schedule
            </button>
            <button data-tab="services" data-specialist="${specialistId}"
                    style="flex: 1; padding: 3px 5px; background: transparent; border: none; border-bottom: 2px solid transparent; color: #666; cursor: pointer; transition: all 0.3s; font-size: 0.65rem;">
                <i class="fas fa-list" style="margin-right: 2px; font-size: 0.6rem;"></i>Services
            </button>
            <button data-tab="permissions" data-specialist="${specialistId}"
                    style="flex: 1; padding: 3px 5px; background: transparent; border: none; border-bottom: 2px solid transparent; color: #666; cursor: pointer; transition: all 0.3s; font-size: 0.65rem;">
                <i class="fas fa-lock" style="margin-right: 2px; font-size: 0.6rem;"></i>Permissions
            </button>
            <button data-tab="details" data-specialist="${specialistId}"
                    style="flex: 1; padding: 3px 5px; background: transparent; border: none; border-bottom: 2px solid transparent; color: #666; cursor: pointer; transition: all 0.3s; font-size: 0.65rem;">
                <i class="fas fa-info-circle" style="margin-right: 2px; font-size: 0.6rem;"></i>Details
            </button>
        `;

        // Create tab content containers
        const tabContent = document.createElement('div');
        tabContent.className = 'tab-content';
        tabContent.innerHTML = `
            <!-- Schedule Tab -->
            <div class="tab-pane active" data-tab-content="schedule" data-specialist="${specialistId}">
                ${scheduleContent.innerHTML}
            </div>

            <!-- Services Tab -->
            <div class="tab-pane" data-tab-content="services" data-specialist="${specialistId}" style="display: none;">
                <div style="padding: 15px; text-align: center;">
                    <p style="color: #666; margin-bottom: 15px;">Loading specialist services...</p>
                    <button class="btn btn-sm btn-primary" onclick="SpecialistTabs.loadServices('${specialistId}')" style="padding: 5px 15px;">
                        <i class="fas fa-sync"></i> Load Services
                    </button>
                </div>
            </div>

            <!-- Permissions Tab -->
            <div class="tab-pane" data-tab-content="permissions" data-specialist="${specialistId}" style="display: none;">
                <div style="padding: 8px;">
                    <div class="permissions-list" id="permissions-${specialistId}">
                        <!-- Permissions will load here -->
                    </div>
                </div>
            </div>

            <!-- Details Tab -->
            <div class="tab-pane" data-tab-content="details" data-specialist="${specialistId}" style="display: none;">
                <div style="padding: 8px;">
                    <div class="details-info" id="details-${specialistId}">
                        <!-- Details will load here -->
                    </div>
                </div>
            </div>
        `;

        // Add click handler to Schedule tab content to open modal
        const schedulePane = tabContent.querySelector('[data-tab-content="schedule"]');
        schedulePane.style.cursor = 'pointer';
        schedulePane.onclick = function(event) {
            event.stopPropagation();
            const workpointId = window.currentWorkpointId || document.querySelector('[data-workpoint-id]')?.dataset.workpointId;
            if (window.openModifyScheduleModal) {
                window.openModifyScheduleModal(specialistId, workpointId);
            }
        };

        // Assemble the tabs container
        tabsContainer.appendChild(tabNav);
        tabsContainer.appendChild(tabContent);

        // Replace the old schedule content with tabs container
        scheduleContent.parentNode.replaceChild(tabsContainer, scheduleContent);

        // Add event listeners to tab buttons
        const tabButtons = tabsContainer.querySelectorAll('[data-tab]');
        tabButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                switchTab(specialistId, this.dataset.tab);
            });

            // Add hover effects
            button.addEventListener('mouseenter', function() {
                if (this.style.borderBottomColor !== '#007bff') {
                    this.style.backgroundColor = 'rgba(0,0,0,0.02)';
                }
            });

            button.addEventListener('mouseleave', function() {
                if (this.style.borderBottomColor !== '#007bff') {
                    this.style.backgroundColor = 'transparent';
                }
            });
        });

        // Set default active tab
        activeTabs[specialistId] = 'schedule';
    }

    /**
     * Switch to a different tab
     * @param {string} specialistId - The specialist ID
     * @param {string} tabName - The tab to switch to
     */
    function switchTab(specialistId, tabName) {
        const card = document.querySelector(`[data-specialist-id="${specialistId}"]`);
        if (!card) return;

        // Update tab buttons
        const tabButtons = card.querySelectorAll('[data-tab]');
        tabButtons.forEach(button => {
            if (button.dataset.tab === tabName) {
                button.style.borderBottomColor = '#007bff';
                button.style.color = '#007bff';
                button.style.fontWeight = '500';
                button.style.backgroundColor = 'transparent';
            } else {
                button.style.borderBottomColor = 'transparent';
                button.style.color = '#666';
                button.style.fontWeight = 'normal';
                button.style.backgroundColor = 'transparent';
            }
        });

        // Update tab content
        const tabPanes = card.querySelectorAll('.tab-pane');
        tabPanes.forEach(pane => {
            if (pane.dataset.tabContent === tabName) {
                pane.style.display = 'block';
                pane.classList.add('active');

                // Load content if not already loaded
                if (tabName === 'services' && !pane.dataset.loaded) {
                    loadServices(specialistId);
                } else if (tabName === 'permissions' && !pane.dataset.loaded) {
                    loadPermissions(specialistId);
                } else if (tabName === 'details' && !pane.dataset.loaded) {
                    loadDetails(specialistId);
                }
            } else {
                pane.style.display = 'none';
                pane.classList.remove('active');
            }
        });

        // Store active tab
        activeTabs[specialistId] = tabName;
    }

    /**
     * Load services for a specialist - using same display as modal
     * @param {string} specialistId - The specialist ID
     */
    function loadServices(specialistId) {
        const container = document.querySelector(`[data-tab-content="services"][data-specialist="${specialistId}"]`);
        if (!container) return;

        // Show loading same as modal
        container.innerHTML = '<div style="text-align: center; color: #6c757d; padding: 8px;"><i class="fas fa-spinner fa-spin"></i> Loading services...</div>';

        // Fetch services from database with cache buster (same as modal)
        const timestamp = new Date().getTime();
        fetch(`admin/get_specialist_services.php?specialist_id=${specialistId}&t=${timestamp}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.services && data.services.length > 0) {
                    let servicesHTML = '<div style="padding: 8px;">';
                    servicesHTML += '<div style="max-height: 150px; overflow-y: auto;">';

                    data.services.forEach(service => {
                        const priceWithVat = service.price_of_service * (1 + service.procent_vat / 100);
                        const isSuspended = service.suspended == 1;
                        const serviceColor = isSuspended ? '#6c757d' : '#495057';

                        servicesHTML += `
                            <div class="service-item" style="padding: 6px 8px; margin-bottom: 3px; background: #f8f9fa; border-radius: 3px; font-size: 0.7rem; transition: background-color 0.2s; cursor: pointer;"
                                 onmouseover="this.style.backgroundColor='#e9ecef'"
                                 onmouseout="this.style.backgroundColor='#f8f9fa'"
                                 onclick="window.serviceReturnModal = 'specialistTab'; window.serviceReturnSpecialistId = '${specialistId}'; if(window.editSpecialistService) { editSpecialistService(${service.unic_id}, '${service.name_of_service.replace(/'/g, "\\'")}', ${service.duration}, ${service.price_of_service}, ${service.procent_vat}); }">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="flex: 1;">
                                        <span style="color: ${serviceColor}; font-size: 0.75rem; ${isSuspended ? 'opacity: 0.6;' : ''}">${service.name_of_service}</span>
                                        ${isSuspended ? '<span style="font-size: 0.6rem; color: #dc3545; margin-left: 5px;">(Suspended)</span>' : ''}
                                        <div style="font-size: 0.65rem; color: #6c757d; line-height: 1.2; margin-top: 1px;">
                                            ${service.duration} min | ${priceWithVat.toFixed(2)}€
                                            ${service.procent_vat > 0 ? `<span style="font-size: 0.6rem;">(incl. ${service.procent_vat}% VAT)</span>` : ''}
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <span style="border: 1px solid #868e96; color: #868e96; padding: 1px 3px; border-radius: 2px; font-size: 0.6rem; display: inline-block; min-width: 15px; text-align: center;"
                                              title="Past bookings (last 30 days)">
                                            ${service.past_bookings || 0}
                                        </span>
                                        <span style="border: 1px solid ${service.active_bookings > 0 ? '#28a745' : '#868e96'}; color: ${service.active_bookings > 0 ? '#28a745' : '#868e96'}; padding: 1px 3px; border-radius: 2px; font-size: 0.6rem; font-weight: bold; display: inline-block; min-width: 15px; text-align: center;"
                                              title="Active/Future bookings">
                                            ${service.active_bookings || 0}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    servicesHTML += '</div>';

                    // Add new service button at bottom (same as modal)
                    servicesHTML += `
                        <button type="button" class="btn btn-sm"
                                onclick="window.serviceReturnModal = 'specialistTab'; window.serviceReturnSpecialistId = '${specialistId}'; if(window.addNewService) { addNewService(); } else if(window.openAddServiceModalForSpecialist) { openAddServiceModalForSpecialist('${specialistId}'); }"
                                style="font-size: 0.65rem; padding: 3px 6px; background-color: white; color: #333; border: 1px solid #ddd; transition: all 0.2s ease; cursor: pointer; margin-top: 6px; width: 100%;"
                                onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)'; this.style.transform='translateY(-1px)';"
                                onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';">
                            <i class="fas fa-plus" style="font-size: 0.6rem;"></i> Add new service
                        </button>
                    `;

                    servicesHTML += '</div>';
                    container.innerHTML = servicesHTML;
                    container.dataset.loaded = 'true';
                } else {
                    container.innerHTML = '<div style="text-align: center; color: #6c757d; padding: 15px;"><em style="font-size: 0.75rem;">No services assigned yet.</em></div>';
                }
            })
            .catch(error => {
                console.error('Error loading services:', error);
                container.innerHTML = '<div style="text-align: center; color: #dc3545; padding: 15px; font-size: 0.75rem;">Error loading services</div>';
            });
    }

    /**
     * Load permissions for a specialist
     * @param {string} specialistId - The specialist ID
     */
    function loadPermissions(specialistId) {
        const container = document.querySelector(`[data-tab-content="permissions"][data-specialist="${specialistId}"]`);
        if (!container) return;

        container.innerHTML = '<div style="padding: 15px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Loading permissions...</div>';

        // Make AJAX call to get permissions
        fetch('admin/get_specialist_permissions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `specialist_id=${specialistId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<div style="padding: 8px;">';

                // Display permissions as toggles
                const permissions = [
                    { key: 'can_modify_schedule', label: 'Modify Schedule', icon: 'fa-calendar-alt' },
                    { key: 'can_view_clients', label: 'View Clients', icon: 'fa-users' },
                    { key: 'can_manage_bookings', label: 'Manage Bookings', icon: 'fa-book' },
                    { key: 'phone_visible', label: 'Phone Visible', icon: 'fa-phone' },
                    { key: 'email_visible', label: 'Email Visible', icon: 'fa-envelope' }
                ];

                html += '<div class="permissions-grid" style="display: grid; gap: 6px;">';
                permissions.forEach(perm => {
                    const isEnabled = data.permissions[perm.key] || false;
                    html += `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 5px; background: #f8f9fa; border-radius: 3px; font-size: 0.75rem;">
                            <span style="color: #333;">
                                <i class="fas ${perm.icon}" style="margin-right: 5px; font-size: 0.65rem; width: 12px;"></i>
                                ${perm.label}
                            </span>
                            <span style="color: ${isEnabled ? '#28a745' : '#dc3545'}; font-weight: 500; font-size: 0.7rem;">
                                ${isEnabled ? '✓ Yes' : '✗ No'}
                            </span>
                        </div>
                    `;
                });
                html += '</div>';

                html += `
                    <button class="btn btn-sm btn-outline-primary" onclick="openModifySpecialistModal('${specialistId}')" style="margin-top: 8px; width: 100%; font-size: 0.7rem; padding: 4px;">
                        <i class="fas fa-edit" style="font-size: 0.65rem;"></i> Edit Permissions
                    </button>
                `;
                html += '</div>';

                container.innerHTML = html;
                container.dataset.loaded = 'true';
            } else {
                container.innerHTML = '<div style="padding: 8px; text-align: center; color: #dc3545; font-size: 0.75rem;">Failed to load permissions</div>';
            }
        })
        .catch(error => {
            console.error('Error loading permissions:', error);
            // Show mock data for now
            let html = '<div style="padding: 8px;">';
            html += `
                <div class="permissions-grid" style="display: grid; gap: 6px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 5px; background: #f8f9fa; border-radius: 3px; font-size: 0.75rem;">
                        <span style="color: #333;">
                            <i class="fas fa-calendar-alt" style="margin-right: 5px; font-size: 0.65rem;"></i>
                            Modify Schedule
                        </span>
                        <span style="color: #28a745; font-weight: 500; font-size: 0.7rem;">✓ Yes</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 5px; background: #f8f9fa; border-radius: 3px; font-size: 0.75rem;">
                        <span style="color: #333;">
                            <i class="fas fa-users" style="margin-right: 5px; font-size: 0.65rem;"></i>
                            View Clients
                        </span>
                        <span style="color: #28a745; font-weight: 500; font-size: 0.7rem;">✓ Yes</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 5px; background: #f8f9fa; border-radius: 3px; font-size: 0.75rem;">
                        <span style="color: #333;">
                            <i class="fas fa-phone" style="margin-right: 5px; font-size: 0.65rem;"></i>
                            Phone Visible
                        </span>
                        <span style="color: #dc3545; font-weight: 500; font-size: 0.7rem;">✗ No</span>
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-primary" onclick="openModifySpecialistModal('${specialistId}')" style="margin-top: 8px; width: 100%; font-size: 0.7rem; padding: 4px;">
                    <i class="fas fa-edit" style="font-size: 0.65rem;"></i> Edit Permissions
                </button>
            `;
            html += '</div>';
            container.innerHTML = html;
            container.dataset.loaded = 'true';
        });
    }

    /**
     * Load details for a specialist
     * @param {string} specialistId - The specialist ID
     */
    function loadDetails(specialistId) {
        const container = document.querySelector(`[data-tab-content="details"][data-specialist="${specialistId}"]`);
        if (!container) return;

        // Get specialist info from the card - CORRECT selectors based on actual HTML structure
        const card = document.querySelector(`[data-specialist-id="${specialistId}"]`);
        const headerDiv = card.querySelector('.specialist-header');

        // Get name from the span with data-bs-toggle="tooltip"
        const nameElement = headerDiv?.querySelector('span[data-bs-toggle="tooltip"]');
        const name = nameElement?.textContent?.trim() || 'Unknown';

        // Get specialty from the first text node before the bullet
        const specialtyDiv = headerDiv?.querySelector('div[style*="color: #6c757d"]');
        const specialty = specialtyDiv?.textContent?.split('•')[0]?.trim() || 'Unknown';

        // Get phone number from the phone icon parent span
        const phoneSpan = specialtyDiv?.querySelector('.fa-phone')?.parentElement;
        const phone = phoneSpan?.textContent?.trim() || 'N/A';

        // Get email from the envelope icon tooltip
        const emailSpan = specialtyDiv?.querySelector('.fa-envelope')?.parentElement;
        const emailTooltip = emailSpan?.getAttribute('title') || '';
        const email = emailTooltip.match(/Email:<\/strong>\s*([^<]+)/)?.[1]?.trim() || 'N/A';

        // Username would need to be fetched from database - for now show N/A
        const username = 'N/A';

        let html = '<div style="padding: 6px;">';

        html += `
            <div style="display: grid; gap: 2px;">
                <!-- ID First -->
                <div style="padding: 3px 5px; background: #f8f9fa; border-radius: 2px;">
                    <span style="color: #666; font-size: 0.7rem;">ID:</span> <strong style="color: #333; font-size: 0.7rem; font-family: monospace;">#${specialistId}</strong>
                </div>

                <!-- Name -->
                <div style="padding: 3px 5px; background: #f8f9fa; border-radius: 2px;">
                    <span style="color: #666; font-size: 0.7rem;">Name:</span> <strong style="color: #333; font-size: 0.7rem;">${name}</strong>
                </div>

                <!-- Speciality -->
                <div style="padding: 3px 5px; background: #f8f9fa; border-radius: 2px;">
                    <span style="color: #666; font-size: 0.7rem;">Speciality:</span> <strong style="color: #333; font-size: 0.7rem;">${specialty}</strong>
                </div>

                <!-- Phone -->
                <div style="padding: 3px 5px; background: #f8f9fa; border-radius: 2px;">
                    <span style="color: #666; font-size: 0.7rem;">
                        <i class="fas fa-phone" style="margin-right: 2px; font-size: 0.6rem;"></i>Phone:
                    </span>
                    <strong style="color: #333; font-size: 0.7rem;">${phone}</strong>
                </div>

                <!-- Email -->
                <div style="padding: 3px 5px; background: #f8f9fa; border-radius: 2px;">
                    <span style="color: #666; font-size: 0.7rem;">
                        <i class="fas fa-envelope" style="margin-right: 2px; font-size: 0.6rem;"></i>Email:
                    </span>
                    <strong style="color: #333; font-size: 0.7rem;">${email}</strong>
                </div>

                <!-- Username/Password -->
                <div style="padding: 3px 5px; background: #f8f9fa; border-radius: 2px;">
                    <span style="color: #666; font-size: 0.7rem;">
                        <i class="fas fa-user" style="margin-right: 2px; font-size: 0.6rem;"></i>Username:
                    </span>
                    <strong style="color: #333; font-size: 0.7rem;">${username} / ****</strong>
                </div>
            </div>

            <!-- Single Edit Details button in the middle -->
            <div style="display: flex; justify-content: center; margin-top: 6px;">
                <button onclick="openModifySpecialistModal('${specialistId}')"
                        style="font-size: 0.65rem; padding: 3px 10px; background: white; color: #007bff; border: 1px solid #007bff; border-radius: 3px; cursor: pointer;"
                        onmouseover="this.style.background='#007bff'; this.style.color='white';"
                        onmouseout="this.style.background='white'; this.style.color='#007bff';">
                    <i class="fas fa-edit" style="font-size: 0.6rem;"></i> Edit Details
                </button>
            </div>
        `;

        html += '</div>';
        container.innerHTML = html;
        container.dataset.loaded = 'true';
    }

    /**
     * Toggle specialist card expansion
     * @param {string} specialistId - The specialist ID
     */
    function toggleSpecialistCard(specialistId) {
        const card = document.querySelector(`[data-specialist-id="${specialistId}"]`);
        if (!card) return;

        const tabsContainer = card.querySelector('.specialist-tabs-container');
        if (tabsContainer) {
            if (tabsContainer.style.display === 'none' || tabsContainer.style.display === '') {
                tabsContainer.style.display = 'block';
                // Initialize tabs if not already done
                if (!card.querySelector('.specialist-tabs')) {
                    initializeSpecialistTabs(specialistId);
                }
            } else {
                tabsContainer.style.display = 'none';
            }
        } else {
            // Initialize tabs for the first time
            initializeSpecialistTabs(specialistId);
        }
    }

    // Export functions
    window.SpecialistTabs = {
        initialize: initializeSpecialistTabs,
        switchTab: switchTab,
        loadServices: loadServices,
        loadPermissions: loadPermissions,
        loadDetails: loadDetails,
        toggle: toggleSpecialistCard
    };

})(window);