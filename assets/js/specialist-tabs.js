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
                    style="flex: 1; padding: 3px 5px; background: transparent; border: none; border-bottom: 2px solid #007bff; color: #007bff; font-weight: 500; cursor: pointer; transition: all 0.3s; font-size: 0.65rem; display: flex; flex-direction: column; align-items: center; gap: 2px;">
                <i class="fas fa-calendar-alt" style="font-size: 0.75rem;"></i>
                <span>Schedule</span>
            </button>
            <button data-tab="services" data-specialist="${specialistId}"
                    style="flex: 1; padding: 3px 5px; background: transparent; border: none; border-bottom: 2px solid transparent; color: #666; cursor: pointer; transition: all 0.3s; font-size: 0.65rem; display: flex; flex-direction: column; align-items: center; gap: 2px;">
                <i class="fas fa-list" style="font-size: 0.75rem;"></i>
                <span>Services</span>
            </button>
            <button data-tab="permissions" data-specialist="${specialistId}"
                    style="flex: 1; padding: 3px 5px; background: transparent; border: none; border-bottom: 2px solid transparent; color: #666; cursor: pointer; transition: all 0.3s; font-size: 0.65rem; display: flex; flex-direction: column; align-items: center; gap: 2px;">
                <i class="fas fa-lock" style="font-size: 0.75rem;"></i>
                <span>Permissions</span>
            </button>
            <button data-tab="details" data-specialist="${specialistId}"
                    style="flex: 1; padding: 3px 5px; background: transparent; border: none; border-bottom: 2px solid transparent; color: #666; cursor: pointer; transition: all 0.3s; font-size: 0.65rem; display: flex; flex-direction: column; align-items: center; gap: 2px;">
                <i class="fas fa-info-circle" style="font-size: 0.75rem;"></i>
                <span>Details</span>
            </button>
        `;

        // Create tab content containers
        const tabContent = document.createElement('div');
        tabContent.className = 'tab-content';
        tabContent.innerHTML = `
            <!-- Schedule Tab -->
            <div class="tab-pane active" data-tab-content="schedule" data-specialist="${specialistId}" style="text-align: center;">
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
                <!-- Details will load here directly -->
            </div>
        `;

        // Add click handler to Schedule tab content to open modal
        const schedulePane = tabContent.querySelector('[data-tab-content="schedule"]');
        schedulePane.style.cursor = 'pointer';
        schedulePane.onclick = function(event) {
            event.stopPropagation();
            // Get workpoint ID from multiple sources
            const workpointId = window.currentWorkpointId ||
                               document.querySelector('[data-workpoint-id]')?.dataset.workpointId ||
                               document.querySelector('.working-point-section')?.dataset.workpointId;

            if (window.openModifyScheduleModal) {
                window.openModifyScheduleModal(specialistId, workpointId);
            } else {
                console.error('openModifyScheduleModal function not found');
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
                // For services, ALWAYS reload to get fresh data
                if (tabName === 'services') {
                    pane.dataset.loaded = '';  // Clear the loaded flag
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
                    let servicesHTML = '<div style="padding: 1px;">';
                    servicesHTML += '<div style="max-height: 150px; overflow-y: auto; scrollbar-width: thin;">';

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
                                onclick="window.serviceReturnModal = 'specialistTab'; window.serviceReturnSpecialistId = '${specialistId}'; if(window.openAddServiceModalForSpecialist) { openAddServiceModalForSpecialist('${specialistId}'); }"
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
                    // No services - show message and Add button
                    container.innerHTML = '<div style="padding: 1px;">' +
                        '<div style="text-align: center; color: #6c757d; padding: 15px;"><em style="font-size: 0.75rem;">No services assigned yet.</em></div>' +
                        '<button type="button" class="btn btn-sm"' +
                        ' onclick="window.serviceReturnModal = \'specialistTab\'; window.serviceReturnSpecialistId = \'' + specialistId + '\'; if(window.openAddServiceModalForSpecialist) { openAddServiceModalForSpecialist(\'' + specialistId + '\'); }"' +
                        ' style="font-size: 0.65rem; padding: 3px 6px; background-color: white; color: #333; border: 1px solid #ddd; transition: all 0.2s ease; cursor: pointer; margin-top: 6px; width: 100%;"' +
                        ' onmouseover="this.style.boxShadow=\'0 2px 8px rgba(0,0,0,0.15)\'; this.style.transform=\'translateY(-1px)\';"' +
                        ' onmouseout="this.style.boxShadow=\'none\'; this.style.transform=\'translateY(0)\';">' +
                        '<i class="fas fa-plus" style="font-size: 0.6rem;"></i> Add new service' +
                        '</button>' +
                        '</div>';
                    container.dataset.loaded = 'true';
                }
            })
            .catch(error => {
                console.error('Error loading services:', error);
                container.innerHTML = '<div style="text-align: center; color: #dc3545; padding: 15px; font-size: 0.75rem;">Error loading services</div>';
            });
    }

    /**
     * Load permissions for a specialist with lazy loading
     * @param {string} specialistId - The specialist ID
     */
    function loadPermissions(specialistId) {
        const container = document.querySelector(`[data-tab-content="permissions"][data-specialist="${specialistId}"]`);
        if (!container) return;

        container.innerHTML = '<div style="padding: 8px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Loading permissions...</div>';

        // Lazy load the permissions script
        if (!window.togglePermission) {
            const script = document.createElement('script');
            script.src = 'assets/js/specialist-permissions.js?v=' + Date.now();
            script.onload = () => fetchAndDisplayPermissions(specialistId, container);
            script.onerror = () => {
                console.error('Failed to load permissions script');
                container.innerHTML = '<div style="padding: 8px; text-align: center; color: #dc3545; font-size: 0.75rem;">Failed to load permissions</div>';
            };
            document.head.appendChild(script);
        } else {
            fetchAndDisplayPermissions(specialistId, container);
        }
    }

    /**
     * Fetch and display permissions
     * @param {string} specialistId - The specialist ID
     * @param {HTMLElement} container - The container element
     */
    function fetchAndDisplayPermissions(specialistId, container) {

        // Fetch permissions from database
        const timestamp = new Date().getTime();
        fetch(`admin/get_specialist_permissions.php?specialist_id=${specialistId}&t=${timestamp}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `specialist_id=${specialistId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const perms = data.permissions;

                let html = '<div style="padding: 6px;">';
                html += '<div class="permissions-grid" style="display: grid; gap: 1.4px;">';

                // User can delete booking
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 3px 5px; background: #f8f9fa; border-radius: 2px;">
                        <label style="color: #333; font-size: 0.7rem; cursor: pointer; display: flex; align-items: center;">
                            <i class="fas fa-trash-alt" style="margin-right: 5px; font-size: 0.65rem; width: 12px;"></i>
                            User can delete booking
                        </label>
                        <div class="form-check form-switch" style="margin: 0; transform: scale(0.6);">
                            <input class="form-check-input" type="checkbox" id="can_delete_booking_${specialistId}"
                                   ${perms.can_delete_booking ? 'checked' : ''}
                                   onchange="togglePermission('${specialistId}', 'specialist_can_delete_booking', this.checked)"
                                   style="cursor: pointer;">
                        </div>
                    </div>
                `;

                // User can modify booking
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 3px 5px; background: #f8f9fa; border-radius: 2px;">
                        <label style="color: #333; font-size: 0.7rem; cursor: pointer; display: flex; align-items: center;">
                            <i class="fas fa-edit" style="margin-right: 5px; font-size: 0.65rem; width: 12px;"></i>
                            User can modify booking
                        </label>
                        <div class="form-check form-switch" style="margin: 0; transform: scale(0.6);">
                            <input class="form-check-input" type="checkbox" id="can_modify_booking_${specialistId}"
                                   ${perms.can_modify_booking ? 'checked' : ''}
                                   onchange="togglePermission('${specialistId}', 'specialist_can_modify_booking', this.checked)"
                                   style="cursor: pointer;">
                        </div>
                    </div>
                `;

                // User can add services
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 3px 5px; background: #f8f9fa; border-radius: 2px;">
                        <label style="color: #333; font-size: 0.7rem; cursor: pointer; display: flex; align-items: center;">
                            <i class="fas fa-plus-circle" style="margin-right: 5px; font-size: 0.65rem; width: 12px;"></i>
                            User can add services
                        </label>
                        <div class="form-check form-switch" style="margin: 0; transform: scale(0.6);">
                            <input class="form-check-input" type="checkbox" id="can_add_services_${specialistId}"
                                   ${perms.can_add_services ? 'checked' : ''}
                                   onchange="togglePermission('${specialistId}', 'specialist_can_add_services', this.checked)"
                                   style="cursor: pointer;">
                        </div>
                    </div>
                `;

                // User can modify services
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 3px 5px; background: #f8f9fa; border-radius: 2px;">
                        <label style="color: #333; font-size: 0.7rem; cursor: pointer; display: flex; align-items: center;">
                            <i class="fas fa-pen" style="margin-right: 5px; font-size: 0.65rem; width: 12px;"></i>
                            User can modify services
                        </label>
                        <div class="form-check form-switch" style="margin: 0; transform: scale(0.6);">
                            <input class="form-check-input" type="checkbox" id="can_modify_services_${specialistId}"
                                   ${perms.can_modify_services ? 'checked' : ''}
                                   onchange="togglePermission('${specialistId}', 'specialist_can_modify_services', this.checked)"
                                   style="cursor: pointer;">
                        </div>
                    </div>
                `;

                // User can delete services
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 3px 5px; background: #f8f9fa; border-radius: 2px;">
                        <label style="color: #333; font-size: 0.7rem; cursor: pointer; display: flex; align-items: center;">
                            <i class="fas fa-minus-circle" style="margin-right: 5px; font-size: 0.65rem; width: 12px;"></i>
                            User can delete services
                        </label>
                        <div class="form-check form-switch" style="margin: 0; transform: scale(0.6);">
                            <input class="form-check-input" type="checkbox" id="can_delete_services_${specialistId}"
                                   ${perms.can_delete_services ? 'checked' : ''}
                                   onchange="togglePermission('${specialistId}', 'specialist_can_delete_services', this.checked)"
                                   style="cursor: pointer;">
                        </div>
                    </div>
                `;

                // Phone visible to clients
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 3px 5px; background: #f8f9fa; border-radius: 2px;">
                        <label style="color: #333; font-size: 0.7rem; cursor: pointer; display: flex; align-items: center;">
                            <i class="fas fa-phone" style="margin-right: 5px; font-size: 0.65rem; width: 12px;"></i>
                            Phone visible to clients
                        </label>
                        <div class="form-check form-switch" style="margin: 0; transform: scale(0.6);">
                            <input class="form-check-input" type="checkbox" id="nr_visible_${specialistId}"
                                   ${perms.phone_visible ? 'checked' : ''}
                                   onchange="togglePermission('${specialistId}', 'specialist_nr_visible_to_client', this.checked)"
                                   style="cursor: pointer;">
                        </div>
                    </div>
                `;

                // Email visible to clients
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 3px 5px; background: #f8f9fa; border-radius: 2px;">
                        <label style="color: #333; font-size: 0.7rem; cursor: pointer; display: flex; align-items: center;">
                            <i class="fas fa-envelope" style="margin-right: 5px; font-size: 0.65rem; width: 12px;"></i>
                            Email visible to clients
                        </label>
                        <div class="form-check form-switch" style="margin: 0; transform: scale(0.6);">
                            <input class="form-check-input" type="checkbox" id="email_visible_${specialistId}"
                                   ${perms.email_visible ? 'checked' : ''}
                                   onchange="togglePermission('${specialistId}', 'specialist_email_visible_to_client', this.checked)"
                                   style="cursor: pointer;">
                        </div>
                    </div>
                `;

                // Schedule Notification (disabled)
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 3px 5px; background: #f8f9fa; border-radius: 2px; opacity: 0.6;">
                        <label style="color: #333; font-size: 0.7rem; display: flex; align-items: center;">
                            <i class="fas fa-bell" style="margin-right: 5px; font-size: 0.65rem; width: 12px;"></i>
                            Schedule Notification
                        </label>
                        <div class="form-check form-switch" style="margin: 0; transform: scale(0.6);">
                            <input class="form-check-input" type="checkbox" disabled
                                   style="cursor: not-allowed;">
                        </div>
                    </div>
                `;

                html += '</div></div>';

                container.innerHTML = html;
                container.dataset.loaded = 'true';
            } else {
                container.innerHTML = '<div style="padding: 8px; text-align: center; color: #dc3545; font-size: 0.75rem;">Failed to load permissions</div>';
            }
        })
        .catch(error => {
            console.error('Error loading permissions:', error);
            container.innerHTML = '<div style="padding: 8px; text-align: center; color: #dc3545; font-size: 0.75rem;">Error loading permissions</div>';
        });
    }

    /**
     * Load details for a specialist - fetch from database
     * @param {string} specialistId - The specialist ID
     */
    function loadDetails(specialistId) {
        const container = document.querySelector(`[data-tab-content="details"][data-specialist="${specialistId}"]`);
        if (!container) return;

        // Show loading
        container.innerHTML = '<div style="text-align: center; color: #6c757d; padding: 8px;"><i class="fas fa-spinner fa-spin"></i> Loading details...</div>';

        // Fetch specialist details from database
        const timestamp = new Date().getTime();
        fetch(`admin/get_specialist_details.php?specialist_id=${specialistId}&t=${timestamp}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.specialist) {
                    const spec = data.specialist;

                    let html = `
                        <div style="display: grid; gap: 2px; padding: 6px; max-width: 400px; margin: 0 auto;">
                            <div style="padding: 3px 5px; background: #f8f9fa; border-radius: 2px; text-align: center;">
                                <span style="color: #666; font-size: 0.7rem;">ID:</span> <strong style="color: #333; font-size: 0.7rem; font-family: monospace;">#${spec.unic_id}</strong>
                                <span style="color: #666; font-size: 0.7rem; margin-left: 15px;">Name:</span> <strong style="color: #333; font-size: 0.7rem;">${spec.name || 'N/A'}</strong>
                            </div>

                            <div style="padding: 3px 5px; background: #f8f9fa; border-radius: 2px; text-align: center;">
                                <span style="color: #666; font-size: 0.7rem;">Speciality:</span> <strong style="color: #333; font-size: 0.7rem;">${spec.speciality || 'N/A'}</strong>
                            </div>

                            <div style="padding: 3px 5px; background: #f8f9fa; border-radius: 2px; text-align: center;">
                                <span style="color: #666; font-size: 0.7rem;">
                                    <i class="fas fa-phone" style="margin-right: 2px; font-size: 0.6rem;"></i>Phone:
                                </span>
                                <strong style="color: #333; font-size: 0.7rem;">${spec.phone_nr || 'N/A'}</strong>
                            </div>

                            <div style="padding: 3px 5px; background: #f8f9fa; border-radius: 2px; text-align: center;">
                                <span style="color: #666; font-size: 0.7rem;">
                                    <i class="fas fa-envelope" style="margin-right: 2px; font-size: 0.6rem;"></i>Email:
                                </span>
                                <strong style="color: #333; font-size: 0.7rem;">${spec.email || 'N/A'}</strong>
                            </div>

                            <div style="padding: 3px 5px; background: #f8f9fa; border-radius: 2px; text-align: center;">
                                <span style="color: #666; font-size: 0.7rem;">
                                    <i class="fas fa-user" style="margin-right: 2px; font-size: 0.6rem;"></i>Username:
                                </span>
                                <strong style="color: #333; font-size: 0.7rem;">${spec.user || 'N/A'}</strong>
                            </div>

                            <div style="padding: 3px 5px; background: #f8f9fa; border-radius: 2px; text-align: center;">
                                <span style="color: #666; font-size: 0.7rem;">
                                    <i class="fas fa-key" style="margin-right: 2px; font-size: 0.6rem;"></i>Password:
                                </span>
                                <strong style="color: #333; font-size: 0.7rem;">${spec.password || 'N/A'}</strong>
                            </div>
                        </div>

                        <div style="text-align: center; margin-top: 6px;">
                            <button type="button" onclick="openModifySpecialistModal('${specialistId}')"
                                    style="font-size: 0.65rem; padding: 3px 6px; background-color: white; color: #333; border: 1px solid #ddd; transition: all 0.2s ease; cursor: pointer; border-radius: 3px;"
                                    onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)'; this.style.transform='translateY(-1px)';"
                                    onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)';">
                                <i class="fas fa-edit" style="font-size: 0.6rem;"></i> Edit Details
                            </button>
                        </div>
                    `;

                    container.innerHTML = html;
                    container.dataset.loaded = 'true';
                } else {
                    container.innerHTML = '<div style="text-align: center; color: #dc3545; padding: 15px; font-size: 0.75rem;">Failed to load details</div>';
                }
            })
            .catch(error => {
                console.error('Error loading details:', error);
                container.innerHTML = '<div style="text-align: center; color: #dc3545; padding: 15px; font-size: 0.75rem;">Error loading details</div>';
            });
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