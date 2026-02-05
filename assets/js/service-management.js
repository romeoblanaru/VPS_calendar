// Service Management Module
// Handles all service CRUD operations (create, read, update, delete)

(function(window) {
    'use strict';

    // Configuration and state
    let specialistCanModifyServices = false;
    let specialistCanDeleteServices = false;
    let isSupervisorMode = false;
    let workpointServices = {};
    let currentEditService = null;

    // Initialize module
    function initialize(config) {
        specialistCanModifyServices = config.specialistCanModifyServices || false;
        specialistCanDeleteServices = config.specialistCanDeleteServices || false;
        isSupervisorMode = config.isSupervisorMode || false;
    }

    // Open supervisor add service modal
    function openSupervisorAddServiceModal(specialistUnicId) {
        // Check if modal exists
        let modal = document.getElementById('supervisorAddServiceModal');
        if (!modal) {
            createSupervisorAddServiceModal();
        }

        // Set the specialist ID
        document.getElementById('supervisorSpecialistId').value = specialistUnicId;

        // Load workpoint services for template
        const workpointId = document.getElementById('supervisorWorkpointId').value;
        if (workpointId) {
            loadWorkpointServicesForTemplate(workpointId, specialistUnicId);
        }

        // Show modal
        new bootstrap.Modal(document.getElementById('supervisorAddServiceModal')).show();
    }

    // Create supervisor add service modal
    function createSupervisorAddServiceModal() {
        const modalHTML = `
        <div class="modal fade" id="supervisorAddServiceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Add Service for Specialist</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="supervisorAddServiceForm" onsubmit="ServiceManagement.handleSupervisorAddService(event)">
                        <div class="modal-body">
                            <input type="hidden" name="specialist_id" id="supervisorSpecialistId">
                            <input type="hidden" name="workpoint_id" id="supervisorWorkpointId">

                            <div class="mb-3">
                                <label for="serviceTemplate" class="form-label">Copy from existing service (optional)</label>
                                <select class="form-control" id="serviceTemplate" onchange="ServiceManagement.populateFromTemplate(this.value)">
                                    <option value="">-- Select a template --</option>
                                </select>
                            </div>

                            <hr>

                            <div class="mb-3">
                                <label for="supervisorServiceName" class="form-label">Service Name *</label>
                                <input type="text" class="form-control" id="supervisorServiceName" name="name_of_service" required>
                            </div>

                            <div class="mb-3">
                                <label for="supervisorServiceDuration" class="form-label">Duration (minutes) *</label>
                                <select class="form-control" id="supervisorServiceDuration" name="duration" required>
                                    <option value="10">10 minutes</option>
                                    <option value="20">20 minutes</option>
                                    <option value="30" selected>30 minutes</option>
                                    <option value="40">40 minutes</option>
                                    <option value="50">50 minutes</option>
                                    <option value="60">1 hour</option>
                                    <option value="70">1 hour 10 minutes</option>
                                    <option value="80">1 hour 20 minutes</option>
                                    <option value="90">1 hour 30 minutes</option>
                                    <option value="100">1 hour 40 minutes</option>
                                    <option value="110">1 hour 50 minutes</option>
                                    <option value="120">2 hours</option>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="supervisorServicePrice" class="form-label">Price (€) *</label>
                                    <input type="number" class="form-control" id="supervisorServicePrice" name="price_of_service" step="0.01" min="0" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="supervisorServiceVat" class="form-label">VAT %</label>
                                    <input type="number" class="form-control" id="supervisorServiceVat" name="procent_vat" min="0" max="100" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Service</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>`;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    // Load workpoint services for template
    function loadWorkpointServicesForTemplate(workpointId, currentSpecialistId) {
        fetch(`admin/get_workpoint_services.php?workpoint_id=${workpointId}`)
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('serviceTemplate');
                if (!select) return;

                // Clear existing options
                select.innerHTML = '<option value="">-- Select a template --</option>';

                if (data.success && data.services) {
                    // Clear the global services object
                    workpointServices = {};

                    data.services.forEach(service => {
                        // Skip services that are deleted
                        if (service.deleted == 1) return;

                        const option = document.createElement('option');
                        option.value = service.service_id;

                        let label = service.name_of_service;
                        if (service.specialist_name) {
                            label += ` (${service.specialist_name})`;
                        }
                        label += ` - ${service.duration}min, €${service.price_of_service}`;
                        if (service.procent_vat > 0) {
                            label += ` +${service.procent_vat}% VAT`;
                        }

                        option.textContent = label;
                        select.appendChild(option);

                        // Store COMPLETE service data for later use
                        workpointServices[service.service_id] = {
                            name_of_service: service.name_of_service,
                            duration: service.duration,
                            price_of_service: service.price_of_service,
                            procent_vat: service.procent_vat || 0
                        };
                    });
                }
            })
            .catch(error => {
                console.error('Error loading services:', error);
            });
    }

    // Populate form from template
    function populateFromTemplate(serviceId) {
        if (!serviceId || !workpointServices || !workpointServices[serviceId]) {
            // Clear form if no template selected
            document.getElementById('supervisorServiceName').value = '';
            document.getElementById('supervisorServiceDuration').value = '';
            document.getElementById('supervisorServicePrice').value = '';
            document.getElementById('supervisorServiceVat').value = '0';
            return;
        }

        const service = workpointServices[serviceId];

        // Set form values
        const nameField = document.getElementById('supervisorServiceName');
        const durationField = document.getElementById('supervisorServiceDuration');
        const priceField = document.getElementById('supervisorServicePrice');
        const vatField = document.getElementById('supervisorServiceVat');

        if (nameField) nameField.value = service.name_of_service || '';
        if (priceField) priceField.value = service.price_of_service || '';
        if (vatField) vatField.value = service.procent_vat || '0';

        // Duration is a select, need to set it properly
        if (durationField && service.duration) {
            const durationValue = service.duration.toString();

            // Check if the option exists
            let optionExists = false;
            for (let i = 0; i < durationField.options.length; i++) {
                if (durationField.options[i].value === durationValue) {
                    durationField.value = durationValue;
                    optionExists = true;
                    break;
                }
            }

            if (!optionExists) {
                // Add custom option if not exists
                const customOption = document.createElement('option');
                customOption.value = durationValue;
                customOption.textContent = durationValue + ' minutes';
                durationField.appendChild(customOption);
                durationField.value = durationValue;
            }
        }
    }

    // Handle supervisor add service
    function handleSupervisorAddService(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        formData.append('action', 'add_service');

        // Show loading state
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        submitBtn.disabled = true;

        fetch('admin/process_add_service.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close the modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('supervisorAddServiceModal'));
                modal.hide();

                // Refresh services if callback provided
                if (window.loadSpecialistServicesForModal) {
                    const specialistId = formData.get('specialist_id');
                    if (specialistId) {
                        setTimeout(() => {
                            window.loadSpecialistServicesForModal(specialistId);
                        }, 300);
                    }
                }

                // Reset form
                e.target.reset();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error adding service:', error);
            alert('Error adding service');
        })
        .finally(() => {
            // Reset button
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }

    // Edit specialist service
    function editSpecialistService(serviceId, serviceName, duration, price, vat) {
        // Check if edit service modal exists, if not create it
        let editModal = document.getElementById('editServiceModalSpecialist');
        if (!editModal) {
            createEditServiceModal();
        }

        // Populate the form
        document.getElementById('editServiceId').value = serviceId;
        document.getElementById('editServiceName').value = serviceName;
        document.getElementById('editServiceDuration').value = duration;
        document.getElementById('editServicePrice').value = price;
        document.getElementById('editServiceVat').value = vat;

        // Store service info globally for delete function
        currentEditService = {
            id: serviceId,
            name: serviceName
        };

        // Check if service has future bookings
        checkServiceBookings(serviceId);

        // Show the modal
        new bootstrap.Modal(document.getElementById('editServiceModalSpecialist')).show();

        // Initialize tooltips for disabled buttons after modal is shown
        if (window.Utils) {
            window.Utils.initializeTooltips('#editServiceModalSpecialist', 100);
        }
    }

    // Create edit service modal
    function createEditServiceModal() {
        const modalHTML = `
        <div class='modal fade' id='editServiceModalSpecialist' tabindex='-1' aria-hidden='true'>
            <div class='modal-dialog'>
                <div class='modal-content'>
                    <div class='modal-header bg-primary text-white'>
                        <h5 class='modal-title'>Edit Service</h5>
                        <button type='button' class='btn-close btn-close-white' data-bs-dismiss='modal'></button>
                    </div>
                    <form id='editServiceFormSpecialist' onsubmit='ServiceManagement.submitEditSpecialistService(event)'>
                        <div class='modal-body'>
                            <input type='hidden' name='service_id' id='editServiceId'>
                            <input type='hidden' name='action' value='edit_service'>

                            <div class='mb-3'>
                                <label for='editServiceName' class='form-label'>Service Name</label>
                                <input type='text' class='form-control' id='editServiceName' name='name_of_service' required
                                       ${!isSupervisorMode && !specialistCanModifyServices ? 'disabled' : ''}>
                            </div>

                            <div class='mb-3'>
                                <label for='editServiceDuration' class='form-label'>Duration (minutes)</label>
                                <select class='form-control' id='editServiceDuration' name='duration' required
                                        ${!isSupervisorMode && !specialistCanModifyServices ? 'disabled' : ''}>
                                    <option value='10'>10 minutes</option>
                                    <option value='20'>20 minutes</option>
                                    <option value='30'>30 minutes</option>
                                    <option value='40'>40 minutes</option>
                                    <option value='50'>50 minutes</option>
                                    <option value='60'>1 hour</option>
                                    <option value='70'>1 hour 10 minutes</option>
                                    <option value='80'>1 hour 20 minutes</option>
                                    <option value='90'>1 hour 30 minutes</option>
                                    <option value='100'>1 hour 40 minutes</option>
                                    <option value='110'>1 hour 50 minutes</option>
                                    <option value='120'>2 hours</option>
                                </select>
                            </div>

                            <div class='row'>
                                <div class='col-md-8 mb-3'>
                                    <label for='editServicePrice' class='form-label'>Price (€)</label>
                                    <input type='number' class='form-control' id='editServicePrice' name='price_of_service' step='0.01' min='0' required
                                           ${!isSupervisorMode && !specialistCanModifyServices ? 'disabled' : ''}>
                                </div>

                                <div class='col-md-4 mb-3'>
                                    <label for='editServiceVat' class='form-label'>VAT %</label>
                                    <input type='number' class='form-control' id='editServiceVat' name='procent_vat' min='0' max='100' value='0'
                                           ${!isSupervisorMode && !specialistCanModifyServices ? 'disabled' : ''}>
                                </div>
                            </div>
                        </div>
                        <div class='modal-footer' style='justify-content: space-between;'>
                            ${!isSupervisorMode && !specialistCanDeleteServices ?
                                '<span data-bs-toggle="tooltip" data-bs-placement="top" title="Permission Disabled for this action. Ask the supervisor or Enable this permissions from Supervisor Dashboard if a sole trader.">' : ''}
                            <button type='button'
                                    id='deleteServiceBtn'
                                    class='btn btn-danger btn-sm'
                                    style='float: left; padding: 0.25rem 0.5rem; font-size: 0.875rem;'
                                    ${!isSupervisorMode && !specialistCanDeleteServices ? 'disabled' : ''}>
                                <i class='fas fa-trash'></i> Delete Service
                            </button>
                            ${!isSupervisorMode && !specialistCanDeleteServices ? '</span>' : ''}
                            <div>
                                <button type='button' class='btn btn-secondary btn-sm' data-bs-dismiss='modal' style='padding: 0.25rem 0.5rem; font-size: 0.875rem;'>Cancel</button>
                                ${!isSupervisorMode && !specialistCanModifyServices ?
                                    '<span data-bs-toggle="tooltip" data-bs-placement="top" title="Permission Disabled for this action. Ask the supervisor or Enable this permissions from Supervisor Dashboard if a sole trader.">' : ''}
                                <button type='submit' class='btn btn-primary btn-sm'
                                        style='padding: 0.25rem 0.5rem; font-size: 0.875rem;'
                                        ${!isSupervisorMode && !specialistCanModifyServices ? 'disabled' : ''}>
                                    <i class='fas fa-save'></i> Save Changes
                                </button>
                                ${!isSupervisorMode && !specialistCanModifyServices ? '</span>' : ''}
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>`;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    // Submit edit specialist service
    function submitEditSpecialistService(e) {
        e.preventDefault();

        const formData = new FormData(document.getElementById('editServiceFormSpecialist'));

        fetch('admin/process_add_service.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('editServiceModalSpecialist')).hide();

                // Check if we should return to Modify Specialist modal
                if (window.serviceReturnModal === 'modifySpecialist' && window.serviceReturnSpecialistId) {
                    // Refresh the services list
                    if (window.loadSpecialistServicesForModal) {
                        window.loadSpecialistServicesForModal(window.serviceReturnSpecialistId);
                    }
                    // Clear the markers
                    window.serviceReturnModal = null;
                    window.serviceReturnSpecialistId = null;
                } else {
                    // Reload the page to show updated services
                    location.reload();
                }
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating service');
        });
    }

    // Check service bookings
    function checkServiceBookings(serviceId) {
        fetch('admin/check_service_bookings.php?service_id=' + serviceId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', text);
                throw e;
            }
        })
        .then(data => {
            const deleteBtn = document.getElementById('deleteServiceBtn');
            const durationInput = document.getElementById('editServiceDuration');

            if (!deleteBtn || !durationInput) return;

            // Reset button styles first
            deleteBtn.style.backgroundColor = '';
            deleteBtn.style.borderColor = '';
            deleteBtn.style.color = '';
            deleteBtn.style.opacity = '1';
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Service';
            deleteBtn.onclick = function() { checkAndDeleteService(); };

            // Check permissions
            if (!applyDeletePermissionState(deleteBtn)) {
                return;
            }

            // Handle different booking states
            if (data.hasFutureBookings) {
                // Has future bookings - show suspend/activate button
                deleteBtn.style.backgroundColor = '#ffc107';
                deleteBtn.style.borderColor = '#ffc107';
                deleteBtn.style.color = '#000';

                if (data.isSuspended) {
                    deleteBtn.innerHTML = '<i class="fas fa-play-circle"></i> Activate Service';
                    deleteBtn.onclick = function() { suspendOrActivateService('activate'); };
                } else {
                    deleteBtn.innerHTML = '<i class="fas fa-pause-circle"></i> Suspend Service';
                    deleteBtn.onclick = function() { suspendOrActivateService('suspend'); };
                }

                // Lock duration field
                durationInput.readOnly = true;
                durationInput.setAttribute('title', 'The duration cannot be changed as long as this service has future bookings');
                durationInput.setAttribute('data-bs-toggle', 'tooltip');
                durationInput.setAttribute('data-bs-placement', 'top');
                durationInput.style.backgroundColor = '#f8f9fa';
                durationInput.style.cursor = 'not-allowed';
                new bootstrap.Tooltip(durationInput);
            } else {
                // No future bookings - enable normal delete
                durationInput.readOnly = false;
                durationInput.removeAttribute('title');
                durationInput.removeAttribute('data-bs-toggle');
                durationInput.removeAttribute('data-bs-placement');
                durationInput.style.backgroundColor = '';
                durationInput.style.cursor = '';

                // Destroy tooltip if exists
                const durationTooltip = bootstrap.Tooltip.getInstance(durationInput);
                if (durationTooltip) durationTooltip.dispose();
            }

            // Store booking info
            if (currentEditService) {
                currentEditService.hasPastBookings = data.hasPastBookings;
                currentEditService.hasFutureBookings = data.hasFutureBookings;
                currentEditService.isSuspended = data.isSuspended;
            }
        })
        .catch(error => {
            console.error('Error checking bookings:', error);
        });
    }

    // Apply delete permission state
    function applyDeletePermissionState(deleteBtn) {
        if (!isSupervisorMode && !specialistCanDeleteServices) {
            deleteBtn.disabled = true;

            // Wrap the button in a span for tooltip
            const wrapper = document.createElement('span');
            wrapper.setAttribute('data-bs-toggle', 'tooltip');
            wrapper.setAttribute('data-bs-placement', 'top');
            wrapper.setAttribute('title', 'Permission Disabled for this action. Ask the supervisor or Enable this permissions from Supervisor Dashboard if a sole trader.');

            deleteBtn.parentNode.insertBefore(wrapper, deleteBtn);
            wrapper.appendChild(deleteBtn);

            new bootstrap.Tooltip(wrapper);
            return false;
        }
        return true;
    }

    // Check and delete service
    function checkAndDeleteService() {
        if (!currentEditService) return;

        // Check permission
        if (!isSupervisorMode && !specialistCanDeleteServices) {
            alert('Permission Disabled for this action. Ask the supervisor or Enable this permissions from Supervisor Dashboard if a sole trader.');
            return;
        }

        const service = currentEditService;

        if (confirm(`Are you sure you want to delete the service "${service.name}"?`)) {
            const formData = new FormData();
            formData.append('service_id', service.id);

            // Determine delete type based on past bookings
            if (service.hasPastBookings) {
                formData.append('action', 'soft_delete_service');
            } else {
                formData.append('action', 'hard_delete_service');
            }

            fetch('admin/process_delete_service.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close the edit modal
                    const editModal = bootstrap.Modal.getInstance(document.getElementById('editServiceModalSpecialist'));
                    if (editModal) {
                        editModal.hide();
                    }

                    // Check if we came from Modify Specialist modal
                    if (window.serviceReturnModal === 'modifySpecialist' && window.serviceReturnSpecialistId) {
                        // Refresh services
                        setTimeout(() => {
                            if (window.loadSpecialistServicesForModal) {
                                window.loadSpecialistServicesForModal(window.serviceReturnSpecialistId);
                            }
                        }, 300);
                    } else {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting service');
            });
        }
    }

    // Suspend or activate service
    function suspendOrActivateService(action) {
        if (!currentEditService) return;

        const service = currentEditService;
        const actionText = action === 'suspend' ? 'suspend' : 'activate';

        let confirmMessage = '';
        if (action === 'suspend') {
            confirmMessage = `⚠️ IMPORTANT: Once suspended, this service cannot be booked anymore in the future.\n\nAre you sure you want to suspend the service "${service.name}"?`;
        } else {
            confirmMessage = `Are you sure you want to activate the service "${service.name}"?`;
        }

        if (confirm(confirmMessage)) {
            const formData = new FormData();
            formData.append('service_id', service.id);
            formData.append('action', action);

            fetch('admin/process_suspend_service.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close the edit modal
                    const editModal = bootstrap.Modal.getInstance(document.getElementById('editServiceModalSpecialist'));
                    if (editModal) {
                        editModal.hide();
                    }

                    // Check if we came from Modify Specialist modal
                    if (window.serviceReturnModal === 'modifySpecialist' && window.serviceReturnSpecialistId) {
                        // Refresh services
                        setTimeout(() => {
                            if (window.loadSpecialistServicesForModal) {
                                window.loadSpecialistServicesForModal(window.serviceReturnSpecialistId);
                            }
                        }, 300);
                    } else {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(`Error ${action === 'suspend' ? 'suspending' : 'activating'} service`);
            });
        }
    }

    // Export functions
    window.ServiceManagement = {
        initialize,
        openSupervisorAddServiceModal,
        loadWorkpointServicesForTemplate,
        populateFromTemplate,
        handleSupervisorAddService,
        editSpecialistService,
        submitEditSpecialistService,
        checkServiceBookings,
        checkAndDeleteService,
        suspendOrActivateService
    };

    // Also expose individual functions for inline onclick handlers
    window.editSpecialistService = editSpecialistService;
    window.checkAndDeleteService = checkAndDeleteService;
    window.suspendOrActivateService = suspendOrActivateService;

})(window);