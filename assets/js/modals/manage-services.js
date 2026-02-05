// Manage Services Modal Functions
// This file contains all Services Management modal functionality

// Store the real implementation
window.openServicesManagementModalReal = function() {
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

    const modal = new bootstrap.Modal(document.getElementById('servicesManagementModal'));
    document.getElementById('servicesManagementContent').innerHTML = '<div class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading services...</div>';
    modal.show();

    // Load services for this workpoint
    loadServicesForWorkpoint();
};

// Load services for the current workpoint
window.loadServicesForWorkpoint = function() {
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    fetch(`admin/get_services_for_workpoint.php?workpoint_id=${workpointId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayServicesManagement(data.grouped_services, data.specialists, data.workpoint_id);
            } else {
                document.getElementById('servicesManagementContent').innerHTML =
                    `<div class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Error: ${data.message}</div>`;
            }
        })
        .catch(error => {
            console.error('Error loading services:', error);
            document.getElementById('servicesManagementContent').innerHTML =
                '<div class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading services</div>';
        });
};

// Display services management interface
window.displayServicesManagement = function(groupedServices, specialists, workpointId) {
    let html = `
        <div class="row mb-3">
            <div class="col-md-6 text-start">
                <button class="btn btn-success" onclick="openAddServiceModal()">
                    <i class="fas fa-plus"></i> Add New Service
                </button>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-info me-2" onclick="openCsvUploadModal()">
                    <i class="fas fa-upload"></i> Upload CSV
                </button>
                <button class="btn btn-info" onclick="downloadServicesCsv()">
                    <i class="fas fa-download"></i> Download CSV
                </button>
            </div>
        </div>
    `;

    // First, display all services (assigned and unassigned)
    html += `
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-list"></i> All Services</h6>
            </div>
            <div class="card-body">
                <div id="servicesList">
                    <div class="text-center text-muted">
                        <i class="fas fa-spinner fa-spin"></i> Loading services...
                    </div>
                </div>
            </div>
        </div>
    `;

    // Then, display services grouped by specialist (for reference)
    if (groupedServices.length > 0) {
        html += `
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-user-md"></i> Services by Specialist</h6>
                </div>
                <div class="card-body">
                    <div style="display: flex; flex-wrap: wrap; gap: 15px;">
        `;

        groupedServices.forEach(group => {
            html += `
                <div style="min-width: 300px; max-width: 400px; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; background-color: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 15px;">
                    <h6 class="text-primary mb-3" style="border-bottom: 2px solid #007bff; padding-bottom: 5px;">
                        <i class="fas fa-user-md"></i> ${group.specialist_name}
                        <small class="text-muted">(${group.specialist_speciality})</small>
                    </h6>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            `;

            group.services.forEach(service => {
                html += `
                    <div style="width: 150px; min-height: 80px; border: 1px solid #dee2e6; border-radius: 6px; padding: 6px; background-color: #f8f9fa; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                        <div style="font-weight: bold; font-size: 13px; margin-bottom: 4px; color: #495057; line-height: 1.2;">${service.name_of_service}</div>
                        <div style="font-size: 11px; color: #6c757d; line-height: 1.2;">
                            <div><i class="fas fa-clock"></i> ${service.duration} min</div>
                            <div><i class="fas fa-dollar-sign"></i> ${service.price_of_service} + VAT</div>
                        </div>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        });

        html += `
                    </div>
                </div>
            </div>
        `;
    }

    document.getElementById('servicesManagementContent').innerHTML = html;

    // Load all services (including unassigned ones)
    loadAllServices();
};

// Load all services (including unassigned)
window.loadAllServices = function() {
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    // Fetch both services and specialist color settings
    Promise.all([
        fetch(`admin/get_all_services_for_workpoint.php?workpoint_id=${workpointId}`).then(r => r.json()),
        fetch(`admin/get_specialists_with_settings.php?workpoint_id=${workpointId}`).then(r => r.json())
    ]).then(([servicesData, specialistsData]) => {
        if (servicesData.success && specialistsData.success) {
            displayServicesList(servicesData.services, specialistsData.specialists);
        } else {
            document.getElementById('servicesList').innerHTML = `<div class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading services or specialist colors</div>`;
        }
    }).catch(error => {
        console.error('Error loading services or specialist colors:', error);
        document.getElementById('servicesList').innerHTML = '<div class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading services or specialist colors</div>';
    });
};

// Display services list (main focus)
window.displayServicesList = function(services, specialistsWithColors) {
    let html = '';
    if (services.length === 0) {
        html = `
            <div class="text-center text-muted">
                <i class="fas fa-info-circle"></i> No services found for this workpoint.
                <br><small>Add services or upload a CSV file to get started.</small>
            </div>
        `;
    } else {
        // Build a map of specialist_id to color info
        const colorMap = {};
        specialistsWithColors.forEach(sp => {
            colorMap[sp.unic_id] = {
                back: sp.back_color,
                fore: sp.foreground_color
            };
        });
        // Group services by specialist, keep unassigned separate
        const assigned = [];
        const unassigned = [];
        services.forEach(service => {
            if (service.specialist_name) {
                assigned.push(service);
            } else {
                unassigned.push(service);
            }
        });
        // Sort assigned by specialist name, then service name
        assigned.sort((a, b) => {
            if (a.specialist_name === b.specialist_name) {
                return a.name_of_service.localeCompare(b.name_of_service);
            }
            return a.specialist_name.localeCompare(b.specialist_name);
        });
        // Sort unassigned by service name
        unassigned.sort((a, b) => a.name_of_service.localeCompare(b.name_of_service));
        // Concatenate: assigned first, then unassigned
        const ordered = assigned.concat(unassigned);
        html = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Actions</th>
                            <th>Service Name</th>
                            <th>Duration</th>
                            <th>Price</th>
                            <th>VAT %</th>
                            <th>Assigned To</th>
                            <th>Booking Count</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        ordered.forEach(service => {
            let assignedTo = '<span class="text-muted">Unassigned</span>';
            if (service.specialist_name && colorMap[service.id_specialist]) {
                const color = colorMap[service.id_specialist];
                assignedTo = `<span style="background:${color.back};color:${color.fore};padding:2px 8px;border-radius:6px;display:inline-block;min-width:80px;">${service.specialist_name} (${service.specialist_speciality})</span>`;
            } else if (service.specialist_name) {
                assignedTo = `${service.specialist_name} (${service.specialist_speciality})`;
            }

            // Add strikethrough style for deleted services
            const isDeleted = service.deleted == 1;
            const deletedStyle = isDeleted ? 'text-decoration: line-through; opacity: 0.6;' : '';

            html += `
                <tr style="${deletedStyle}">
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-primary" style="padding: 1px 4px;" onclick="editService('${service.service_id}', '${service.name_of_service}', ${service.duration}, ${service.price_of_service}, ${service.procent_vat || 0})" title="Edit Service">
                                <i class="fas fa-edit" style="font-size: 80%;"></i>
                            </button>&nbsp;&nbsp;
                            <button class="btn btn-outline-info" style="padding: 1px 4px;" onclick="assignService('${service.service_id}', '${service.name_of_service}', '${service.specialist_id || ''}')" title="Assign to Specialist">
                                <i class="fas fa-user-plus" style="font-size: 80%;"></i>
                            </button>&nbsp;&nbsp;
                            <button class="btn btn-outline-danger" style="padding: 1px 4px;" onclick="deleteService('${service.service_id}', '${service.name_of_service}', ${service.id_specialist ? 'true' : 'false'})" title="${service.id_specialist ? 'Unassign from Specialist' : 'Delete Service'}">
                                <i class="fas fa-trash" style="font-size: 80%;"></i>
                            </button>
                        </div>
                    </td>
                    <td><strong>[${service.service_id}] ${service.name_of_service}</strong></td>
                    <td>${service.duration} min</td>
                    <td>$${service.price_of_service}</td>
                    <td>${service.procent_vat || '0.00'}%</td>
                    <td>${assignedTo}</td>
                    <td>
                        <div class="d-flex align-items-center justify-content-center">
                            <span class="badge me-1" style="background-color: #e9ecef; color: #6c757d;" title="Past bookings">${service.past_booking_count || 0}</span>
                            <span class="badge bg-info" title="Future bookings">${service.future_booking_count || 0}</span>
                        </div>
                    </td>
                </tr>
            `;
        });
        html += `
                    </tbody>
                </table>
            </div>
        `;
    }
    document.getElementById('servicesList').innerHTML = html;
};

// Assign Service to Specialist
window.assignService = function(serviceId, serviceName, currentSpecialistId) {
    document.getElementById('assignServiceId').value = serviceId;
    document.getElementById('assignServiceName').textContent = serviceName;

    // Load specialists for assignment
    loadSpecialistsForAssignment();

    const modal = new bootstrap.Modal(document.getElementById('assignServiceModal'));
    modal.show();
};

// Load specialists for assignment
window.loadSpecialistsForAssignment = function() {
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    fetch(`admin/get_specialists_with_settings.php?workpoint_id=${workpointId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('assignTargetSpecialist');
                select.innerHTML = '<option value="">Unassigned</option>';

                data.specialists.forEach(specialist => {
                    const option = document.createElement('option');
                    option.value = specialist.unic_id;
                    option.textContent = `${specialist.name} (${specialist.speciality})`;
                    option.style.color = specialist.back_color || '#000000';
                    option.style.backgroundColor = '#ffffff';
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading specialists:', error);
        });
};

// Open CSV Upload Modal
window.openCsvUploadModal = function() {
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    document.getElementById('csvUploadWorkpointId').value = workpointId;
    const modal = new bootstrap.Modal(document.getElementById('csvUploadModal'));
    modal.show();
};

// Submit Edit Service
window.submitEditService = function() {
    const formData = new FormData(document.getElementById('editServiceForm'));
    formData.append('action', 'edit_service');

    fetch('admin/process_add_service.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editServiceModal')).hide();
            loadServicesForWorkpoint(); // Reload the list
            alert(data.message);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error editing service:', error);
        alert('Error editing service');
    });
};

// Submit Assign Service
window.submitAssignService = function() {
    const serviceId = document.getElementById('assignServiceId').value;
    const targetSpecialistId = document.getElementById('assignTargetSpecialist').value;

    if (!targetSpecialistId) {
        alert('Please select a target specialist');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'assign_service');
    formData.append('service_id', serviceId);
    formData.append('target_specialist_id', targetSpecialistId);

    fetch('admin/process_add_service.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('assignServiceModal')).hide();
            loadAllServices(); // Reload the list
            alert(data.message);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error assigning service:', error);
        alert('Error assigning service');
    });
};

// Delete Service
window.deleteService = function(serviceId, serviceName, isAssigned) {
    const action = isAssigned ? 'unassign' : 'delete';
    const message = isAssigned ?
        `Are you sure you want to unassign the service "${serviceName}" from the specialist?` :
        `Are you sure you want to delete the service "${serviceName}"?`;

    if (!confirm(message)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_service');
    formData.append('service_id', serviceId);

    fetch('admin/process_add_service.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            loadAllServices(); // Reload the list
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error deleting service:', error);
        alert('Error deleting service');
    });
};

// Open Add Service Modal
window.openAddServiceModal = function() {
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    const modalElement = document.getElementById('addServiceModal');
    const modal = new bootstrap.Modal(modalElement);
    const form = document.getElementById('addServiceForm');

    // Reset form
    form.reset();

    // Set workpoint ID
    document.getElementById('addServiceWorkpointId').value = workpointId;

    // Enable specialist dropdown and populate it
    const specialistSelect = document.getElementById('addServiceSpecialist');
    if (specialistSelect) {
        specialistSelect.disabled = false;
        specialistSelect.style.backgroundColor = '';
    }

    // Remove any hidden specialist_id input that may have been added
    const hiddenInput = modalElement.querySelector('input[name="specialist_id"][type="hidden"]');
    if (hiddenInput) {
        hiddenInput.remove();
    }

    populateSpecialistsForAdd();
    modal.show();
};

// Edit Service
window.editService = function(serviceId, serviceName, duration, price, vat) {
    document.getElementById('editServiceId').value = serviceId;
    document.getElementById('editServiceName').value = serviceName;
    document.getElementById('editServiceDuration').value = duration;
    document.getElementById('editServicePrice').value = price;
    document.getElementById('editServiceVat').value = vat;

    const modal = new bootstrap.Modal(document.getElementById('editServiceModal'));
    modal.show();
};

// Populate specialists for add service modal
window.populateSpecialistsForAdd = function() {
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    fetch(`admin/get_services_for_workpoint.php?workpoint_id=${workpointId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('addServiceSpecialist');
                select.innerHTML = '<option value="">Unassigned</option>';

                data.specialists.forEach(specialist => {
                    const option = document.createElement('option');
                    option.value = specialist.unic_id;
                    option.textContent = `${specialist.name} (${specialist.speciality})`;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading specialists:', error);
        });
};

// Download Services CSV
window.downloadServicesCsv = function() {
    const workpointId = window.currentWorkpointId ||
                       document.getElementById('workpoint_id')?.value ||
                       (typeof getSelectedWorkpointId === 'function' ? getSelectedWorkpointId() : null);

    fetch(`admin/download_services_csv.php?workpoint_id=${workpointId}`)
        .then(response => {
            if (response.ok) {
                return response.blob();
            } else {
                throw new Error('Failed to download CSV');
            }
        })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = `services_workpoint_${workpointId}_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        })
        .catch(error => {
            console.error('Error downloading CSV:', error);
            alert('Error downloading CSV file');
        });
};

// Setup form handlers when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Add Service Form Handler
    const addServiceForm = document.getElementById('addServiceForm');
    if (addServiceForm) {
        addServiceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add_service');

            fetch('admin/process_add_service.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('addServiceModal')).hide();
                    this.reset();
                    loadServicesForWorkpoint(); // Refresh services list
                    alert('Service added successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding service');
            });
        });
    }

    // CSV Upload Form Handler
    const csvUploadForm = document.getElementById('csvUploadForm');
    if (csvUploadForm) {
        csvUploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('admin/upload_services_csv.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('csvUploadModal')).hide();
                    loadAllServices(); // Reload the list
                    alert(data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error uploading CSV:', error);
                alert('Error uploading CSV file');
            });
        });
    }
});

// Replace the lazy loading wrapper with the real function after loading
window.openServicesManagementModal = window.openServicesManagementModalReal;