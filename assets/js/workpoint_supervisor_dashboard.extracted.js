(function(){
	function wpId(){ return (window.SUPERVISOR_CTX && window.SUPERVISOR_CTX.workpointId) || ''; }

	window.openServicesManagementModal = function openServicesManagementModal() {
		const modal = new bootstrap.Modal(document.getElementById('servicesManagementModal'));
		document.getElementById('servicesManagementContent').innerHTML = '<div class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading services...</div>';
		modal.show();
		loadServicesForWorkpoint();
	};

	window.loadServicesForWorkpoint = function loadServicesForWorkpoint() {
		const workpointId = wpId();
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

	window.displayServicesManagement = function displayServicesManagement(groupedServices, specialists, workpointId) {
		let html = `
			<div class="row mb-3">
				<div class="col-md-4">
					<button class="btn btn-info" onclick="downloadServicesCsv()">
						<i class="fas fa-download"></i> Download CSV
					</button>
				</div>
				<div class="col-md-4 text-start">
					<button class="btn btn-info" onclick="openCsvUploadModal()">
						<i class="fas fa-upload"></i> Upload CSV
					</button>
				</div>
				<div class="col-md-4 text-end">
					<button class="btn btn-success" onclick="openAddServiceModal()">
						<i class="fas fa-plus"></i> Add New Service
					</button>
				</div>
			</div>
		`;

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
					<div style=\"min-width: 300px; max-width: 400px; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; background-color: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 15px;\">
						<h6 class=\"text-primary mb-3\" style=\"border-bottom: 2px solid #007bff; padding-bottom: 5px;\">
							<i class=\"fas fa-user-md\"></i> ${group.specialist_name} 
							<small class=\"text-muted\">(${group.specialist_speciality})</small>
						</h6>
						<div style=\"display: flex; flex-wrap: wrap; gap: 8px;\">
				`;
				group.services.forEach(service => {
					html += `
						<div style=\"width: 150px; min-height: 80px; border: 1px solid #dee2e6; border-radius: 6px; padding: 6px; background-color: #f8f9fa; box-shadow: 0 1px 2px rgba(0,0,0,0.1);\">
							<div style=\"font-weight: bold; font-size: 13px; margin-bottom: 4px; color: #495057; line-height: 1.2;\">${service.name_of_service}</div>
							<div style=\"font-size: 11px; color: #6c757d; line-height: 1.2;\">
								<div><i class=\"fas fa-clock\"></i> ${service.duration} min</div>
								<div><i class=\"fas fa-dollar-sign\"></i> ${service.price_of_service} + VAT</div>
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
		loadAllServices();
	};

	window.loadAllServices = function loadAllServices() {
		const workpointId = wpId();
		Promise.all([
			fetch(`admin/get_all_services_for_workpoint.php?workpoint_id=${workpointId}`).then(r => r.json()),
			fetch(`admin/get_specialists_with_settings.php?workpoint_id=${workpointId}`).then(r => r.json())
		]).then(([servicesData, specialistsData]) => {
			if (servicesData.success && specialistsData.success) {
				displayServicesList(servicesData.services, specialistsData.specialists);
			} else {
				document.getElementById('servicesList').innerHTML = `<div class=\"text-center text-danger\"><i class=\"fas fa-exclamation-triangle\"></i> Error loading services or specialist colors</div>`;
			}
		}).catch(error => {
			console.error('Error loading services or specialist colors:', error);
			document.getElementById('servicesList').innerHTML = '<div class=\"text-center text-danger\"><i class=\"fas fa-exclamation-triangle\"></i> Error loading services or specialist colors</div>';
		});
	};

	window.displayServicesList = function displayServicesList(services, specialistsWithColors) {
		let html = '';
		if (services.length === 0) {
			html = `
				<div class=\"text-center text-muted\">
					<i class=\"fas fa-info-circle\"></i> No services found for this workpoint.
					<br><small>Add services or upload a CSV file to get started.</small>
				</div>
			`;
		} else {
			const colorMap = {};
			specialistsWithColors.forEach(sp => { colorMap[sp.unic_id] = { back: sp.back_color, fore: sp.foreground_color }; });
			const assigned = [], unassigned = [];
			services.forEach(service => { (service.specialist_name ? assigned : unassigned).push(service); });
			assigned.sort((a,b)=> a.specialist_name===b.specialist_name ? a.name_of_service.localeCompare(b.name_of_service) : a.specialist_name.localeCompare(b.specialist_name));
			unassigned.sort((a,b)=> a.name_of_service.localeCompare(b.name_of_service));
			const ordered = assigned.concat(unassigned);
			html = `
				<div class=\"table-responsive\">
					<table class=\"table table-hover\">
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
				let assignedTo = '<span class=\"text-muted\">Unassigned</span>';
				if (service.specialist_name && colorMap[service.id_specialist]) {
					const color = colorMap[service.id_specialist];
					assignedTo = `<span style=\"background:${color.back};color:${color.fore};padding:2px 8px;border-radius:6px;display:inline-block;min-width:80px;\">${service.specialist_name} (${service.specialist_speciality})</span>`;
				} else if (service.specialist_name) {
					assignedTo = `${service.specialist_name} (${service.specialist_speciality})`;
				}
				const isDeleted = service.deleted == 1;
				const deletedStyle = isDeleted ? 'text-decoration: line-through; opacity: 0.6;' : '';
				html += `
					<tr style=\"${deletedStyle}\">
						<td>
							<div class=\"btn-group btn-group-sm\" role=\"group\">
								<button class=\"btn btn-outline-primary\" style=\"padding: 1px 4px;\" onclick=\"editService('${service.service_id}', '${service.name_of_service}', ${service.duration}, ${service.price_of_service}, ${service.procent_vat || 0})\" title=\"Edit Service\">
									<i class=\"fas fa-edit\" style=\"font-size: 80%;\"></i>
								</button>&nbsp;&nbsp;
								<button class=\"btn btn-outline-info\" style=\"padding: 1px 4px;\" onclick=\"assignService('${service.service_id}', '${service.name_of_service}', '${service.specialist_id || ''}')\" title=\"Assign to Specialist\">
									<i class=\"fas fa-user-plus\" style=\"font-size: 80%;\"></i>
								</button>&nbsp;&nbsp;
								<button class=\"btn btn-outline-danger\" style=\"padding: 1px 4px;\" onclick=\"deleteService('${service.service_id}', '${service.name_of_service}', ${service.id_specialist ? 'true' : 'false'})\" title=\"${service.id_specialist ? 'Unassign from Specialist' : 'Delete Service'}\">
									<i class=\"fas fa-trash\" style=\"font-size: 80%;\"></i>
								</button>
							</div>
						</td>
						<td><strong>[${service.service_id}] ${service.name_of_service}</strong></td>
						<td>${service.duration} min</td>
						<td>$${service.price_of_service}</td>
						<td>${service.procent_vat || '0.00'}%</td>
						<td>${assignedTo}</td>
						<td>
							<div class=\"d-flex align-items-center justify-content-center\">
								<span class=\"badge me-1\" style=\"background-color: #e9ecef; color: #6c757d;\" title=\"Past bookings\">${service.past_booking_count || 0}</span>
								<span class=\"badge bg-info\" title=\"Future bookings\">${service.future_booking_count || 0}</span>
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

	window.assignService = function assignService(serviceId, serviceName, currentSpecialistId) {
		document.getElementById('assignServiceId').value = serviceId;
		document.getElementById('assignServiceName').textContent = serviceName;
		loadSpecialistsForAssignment();
		const modal = new bootstrap.Modal(document.getElementById('assignServiceModal'));
		modal.show();
	};

	window.loadSpecialistsForAssignment = function loadSpecialistsForAssignment() {
		const workpointId = wpId();
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
			.catch(error => { console.error('Error loading specialists:', error); });
	};

	window.openCsvUploadModal = function openCsvUploadModal() {
		const modal = new bootstrap.Modal(document.getElementById('csvUploadModal'));
		modal.show();
	};

	window.submitAddService = function submitAddService() {
		const formData = new FormData(document.getElementById('addServiceForm'));
		formData.append('action', 'add_service');
		fetch('admin/process_add_service.php', { method: 'POST', body: formData })
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					bootstrap.Modal.getInstance(document.getElementById('addServiceModal')).hide();
					document.getElementById('addServiceForm').reset();
					loadServicesForWorkpoint();
					alert(data.message);
				} else {
					alert('Error: ' + data.message);
				}
			})
			.catch(error => { console.error('Error adding service:', error); alert('Error adding service'); });
	};

	window.submitEditService = function submitEditService() {
		const formData = new FormData(document.getElementById('editServiceForm'));
		formData.append('action', 'edit_service');
		fetch('admin/process_add_service.php', { method: 'POST', body: formData })
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					bootstrap.Modal.getInstance(document.getElementById('editServiceModal')).hide();
					loadServicesForWorkpoint();
					alert(data.message);
				} else {
					alert('Error: ' + data.message);
				}
			})
			.catch(error => { console.error('Error editing service:', error); alert('Error editing service'); });
	};

	window.submitAssignService = function submitAssignService() {
		const serviceId = document.getElementById('assignServiceId').value;
		const targetSpecialistId = document.getElementById('assignTargetSpecialist').value;
		if (!targetSpecialistId) { alert('Please select a target specialist'); return; }
		const formData = new FormData();
		formData.append('action', 'assign_service');
		formData.append('service_id', serviceId);
		formData.append('target_specialist_id', targetSpecialistId);
		fetch('admin/process_add_service.php', { method: 'POST', body: formData })
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					bootstrap.Modal.getInstance(document.getElementById('assignServiceModal')).hide();
					loadAllServices();
					alert(data.message);
				} else {
					alert('Error: ' + data.message);
				}
			})
			.catch(error => { console.error('Error assigning service:', error); alert('Error assigning service'); });
	};

	window.submitRedistributeService = function submitRedistributeService() {
		const serviceId = document.getElementById('redistributeServiceId').value;
		const targetSpecialistId = document.getElementById('redistributeTargetSpecialist').value;
		if (!targetSpecialistId) { alert('Please select a target specialist'); return; }
		const workpointId = wpId();
		fetch(`admin/get_services_for_workpoint.php?workpoint_id=${workpointId}`)
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					let serviceDetails = null;
					for (const group of data.grouped_services) {
						for (const service of group.services) { if (service.service_id == serviceId) { serviceDetails = service; break; } }
						if (serviceDetails) break;
					}
					if (!serviceDetails) { alert('Service not found'); return; }
					const formData = new FormData();
					formData.append('action', 'add_service');
					formData.append('specialist_id', targetSpecialistId);
					formData.append('workpoint_id', workpointId);
					formData.append('name_of_service', serviceDetails.name_of_service);
					formData.append('duration', serviceDetails.duration);
					formData.append('price_of_service', serviceDetails.price_of_service);
					return fetch('admin/process_add_service.php', { method: 'POST', body: formData });
				}
			})
			.then(response => response && response.json ? response.json() : null)
			.then(data => {
				if (data && data.success) {
					const deleteFormData = new FormData();
					deleteFormData.append('action', 'delete_service');
					deleteFormData.append('service_id', serviceId);
					return fetch('admin/process_add_service.php', { method: 'POST', body: deleteFormData });
				} else if (data) {
					alert('Error: ' + data.message);
				}
			})
			.then(response => response && response.json ? response.json() : null)
			.then(data => {
				if (data && data.success) {
					bootstrap.Modal.getInstance(document.getElementById('redistributeServiceModal')).hide();
					loadServicesForWorkpoint();
					alert('Service redistributed successfully');
				} else if (data) {
					alert('Error: ' + data.message);
				}
			})
			.catch(error => { console.error('Error redistributing service:', error); alert('Error redistributing service'); });
	};

	window.deleteService = function deleteService(serviceId, serviceName, isAssigned) {
		const message = isAssigned ? 
			`Are you sure you want to unassign the service "${serviceName}" from the specialist?` :
			`Are you sure you want to delete the service "${serviceName}"?`;
		if (!confirm(message)) return;
		const formData = new FormData();
		formData.append('action', 'delete_service');
		formData.append('service_id', serviceId);
		fetch('admin/process_add_service.php', { method: 'POST', body: formData })
			.then(response => response.json())
			.then(data => { if (data.success) { alert(data.message); loadAllServices(); } else { alert('Error: ' + data.message); } })
			.catch(error => { console.error('Error deleting service:', error); alert('Error deleting service'); });
	};

	window.openAddServiceModal = function openAddServiceModal() {
		const modal = new bootstrap.Modal(document.getElementById('addServiceModal'));
		document.getElementById('addServiceForm').reset();
		populateSpecialistsForAdd();
		modal.show();
	};

	window.editService = function editService(serviceId, serviceName, duration, price, vat) {
		document.getElementById('editServiceId').value = serviceId;
		document.getElementById('editServiceName').value = serviceName;
		document.getElementById('editServiceDuration').value = duration;
		document.getElementById('editServicePrice').value = price;
		document.getElementById('editServiceVat').value = vat;
		const modal = new bootstrap.Modal(document.getElementById('editServiceModal'));
		modal.show();
	};

	window.uploadCsv = function uploadCsv() {
		const fileInput = document.getElementById('csvFileInput');
		const file = fileInput.files[0];
		if (!file) { alert('Please select a CSV file'); return; }
		const formData = new FormData();
		formData.append('csv_file', file);
		formData.append('workpoint_id', wpId());
		fetch('admin/upload_services_csv.php', { method: 'POST', body: formData })
			.then(response => response.json())
			.then(data => { if (data.success) { bootstrap.Modal.getInstance(document.getElementById('csvUploadModal')).hide(); loadAllServices(); alert(data.message); } else { alert('Error: ' + data.message); } })
			.catch(error => { console.error('Error uploading CSV:', error); alert('Error uploading CSV file'); });
	};

	window.populateSpecialistsForAdd = function populateSpecialistsForAdd() {
		const workpointId = wpId();
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
			.catch(error => { console.error('Error loading specialists:', error); });
	};

	window.downloadServicesCsv = function downloadServicesCsv() {
		const workpointId = wpId();
		fetch(`admin/download_services_csv.php?workpoint_id=${workpointId}`)
			.then(response => { if (response.ok) return response.blob(); throw new Error('Failed to download CSV'); })
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
			.catch(error => { console.error('Error downloading CSV:', error); alert('Error downloading CSV file'); });
	};
})();
