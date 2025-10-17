(function(){
	function wpId(){ return (window.SUPERVISOR_CTX && window.SUPERVISOR_CTX.workpointId) || ''; }

	function renderWidgets(specialists){
		const container = document.getElementById('specialistsWidgetsContainer');
		if (!container) return;
		if (!specialists || !specialists.length){
			container.innerHTML = '<div class="text-center text-muted col-12"><i class="fas fa-info-circle"></i> No specialists found</div>';
			return;
		}
		const unique = [];
		const seen = new Set();
		for (const s of specialists){ if (!seen.has(s.unic_id)) { unique.push(s); seen.add(s.unic_id); } }
		let html = '';
		unique.forEach(specialist => {
			const backColor = specialist.back_color || '#667eea';
			const foreColor = specialist.foreground_color || '#ffffff';
			html += `
				<div class="col-md-6 col-lg-3 mb-3">
					<div class="card specialist-widget" style="border: 2px solid ${backColor};">
						<div class="card-header" style="background-color: ${backColor}; color: ${foreColor};">
							<h6 class="mb-0">
								<i class="fas fa-user-md"></i> [ID: ${specialist.unic_id}] ${specialist.name}
							</h6>
						</div>
						<div class="card-body">
							<div class="d-flex align-items-start justify-content-between" style="gap:10px;">
								<div class="specialist-details" style="flex:1 1 auto;">
									<p class="mb-1"><strong>Specialty:</strong> ${specialist.speciality}</p>
									<p class="mb-1">
										<i class="fas fa-phone"></i> ${specialist.phone_nr}
										<span class="visibility-text ${specialist.specialist_nr_visible_to_client == 1 ? 'visible' : 'hidden'}">
											(${specialist.specialist_nr_visible_to_client == 1 ? 'visible' : 'hidden'})
										</span>
									</p>
									<div class="mt-2" style="display:inline-block;">
										<span style="font-size:1rem;"><i class="fas fa-bell"></i> Schedule Notification</span>
										<input class="form-check-input" type="checkbox" id="email_notification_${specialist.unic_id}" ${specialist.daily_email_enabled ? 'checked' : ''} onchange="toggleEmailNotification('${specialist.unic_id}', this.checked)" style="margin-left:0.25rem;">
									</div>
								</div>
								<div class="btn-group-vertical" style="flex:0 0 120px; gap:6px;">
									<button class="btn btn-sm btn-outline-primary w-100" onclick="openColorPickerModal('${specialist.unic_id}', '${backColor}', '${foreColor}', '${specialist.name}')" title="Change Color">
										<i class="fas fa-palette"></i> Color
									</button>
									<button class="btn btn-sm btn-outline-secondary w-100" onclick="openEditSpecialistModal('${specialist.unic_id}', '${specialist.name}', '${specialist.speciality}', '${specialist.phone_nr}', '${specialist.email}', '${specialist.user}', '${specialist.password}')" title="Modify Details">
										<i class="fas fa-edit"></i> Modify
									</button>
									<button class="btn btn-sm btn-outline-info w-100" onclick="openScheduleModal('${specialist.unic_id}')" title="Modify Schedule">
										<i class="fas fa-calendar"></i> Schedule
									</button>
									<button class="btn btn-sm btn-outline-warning w-100" onclick="openEmailModal('${specialist.unic_id}', '${specialist.name}', '${specialist.email}')" title="Send Email">
										<i class="fas fa-envelope"></i> Email
									</button>
									<button class="btn btn-sm btn-outline-success w-100" onclick="openSmsModal('${specialist.unic_id}', '${specialist.name}', '${specialist.phone_nr}')" title="Send SMS">
										<i class="fas fa-sms"></i> Sms
									</button>
								</div>
							</div>
							<div class="mt-2">
								<small class="text-muted"><strong>Specialist Permisions:</strong></small>
								<hr style="border-top: 2px solid #6c757d; margin-top:2px; margin-bottom:6px;">
								<table style="width:100%;">
									<tr>
										<td style="white-space:nowrap; vertical-align:middle; display:flex; align-items:center; gap:6px;">
											<input class="form-check-input" type="checkbox" id="can_delete_booking_${specialist.unic_id}" ${specialist.specialist_can_delete_booking == 1 ? 'checked' : ''} onchange="togglePermission('${specialist.unic_id}', 'specialist_can_delete_booking', this.checked)">
											<span style="font-size:1rem;"><i class="fas fa-trash"></i> Delete bookings</span>
										</td>
									</tr>
									<tr>
										<td style="white-space:nowrap; vertical-align:middle; display:flex; align-items:center; gap:6px;">
											<input class="form-check-input" type="checkbox" id="can_modify_booking_${specialist.unic_id}" ${specialist.specialist_can_modify_booking == 1 ? 'checked' : ''} onchange="togglePermission('${specialist.unic_id}', 'specialist_can_modify_booking', this.checked)">
											<span style="font-size:1rem;"><i class="fas fa-edit"></i> Modify bookings</span>
										</td>
									</tr>
									<tr>
										<td style="white-space:nowrap; vertical-align:middle; display:flex; align-items:center; gap:6px;">
											<input class="form-check-input" type="checkbox" id="nr_visible_${specialist.unic_id}" ${specialist.specialist_nr_visible_to_client == 1 ? 'checked' : ''} onchange="togglePermission('${specialist.unic_id}', 'specialist_nr_visible_to_client', this.checked)">
											<span style="font-size:1rem;"><i class="fas fa-phone"></i> Phone visible</span>
										</td>
									</tr>
									<tr>
										<td style="white-space:nowrap; vertical-align:middle; display:flex; align-items:center; gap:6px;">
											<input class="form-check-input" type="checkbox" id="email_visible_${specialist.unic_id}" ${specialist.specialist_email_visible_to_client == 1 ? 'checked' : ''} onchange="togglePermission('${specialist.unic_id}', 'specialist_email_visible_to_client', this.checked)">
											<span style="font-size:1rem;"><i class="fas fa-envelope"></i> Email visible</span>
										</td>
									</tr>
								</table>
							</div>
						</div>
					</div>
				`;
		});
		container.innerHTML = html;
	}

	function load(){
		const container = document.getElementById('specialistsWidgetsContainer');
		if (!container) return;
		fetch('admin/get_specialists_with_settings.php?workpoint_id=' + encodeURIComponent(wpId()))
			.then(r=>r.json())
			.then(data => { if (data && data.success) renderWidgets(data.specialists); })
			.catch(err => { console.error('Error loading specialists:', err); });
	}

	document.addEventListener('DOMContentLoaded', load);
})();

document.addEventListener('DOMContentLoaded', function() {
	// Note: Do not early-return; define globals regardless of modal existence
	function postJSON(url, data) {
		return fetch(url, {
			method: 'POST',
			headers: { 'Accept': 'application/json' },
			body: new URLSearchParams(data)
		}).then(r => r.json());
	}

	window.gcalOpenConnect = function(specialistId) {
		// Show loading indicator
		const btn = event ? event.target : null;
		if (btn) {
			btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
			btn.disabled = true;
		}
		
		postJSON('admin/gcal_oauth_start.php', { specialist_id: specialistId }).then(resp => {
			if (resp.success && resp.oauth_url) {
				// Redirect to Google OAuth
				window.location.href = resp.oauth_url;
			} else if (resp.success) {
				alert('Connection initiated. Please complete the OAuth flow.');
				location.reload();
			} else {
				alert('Error: ' + (resp.message || 'Failed to start OAuth flow'));
				if (btn) {
					btn.innerHTML = '<i class="fas fa-plug"></i>';
					btn.disabled = false;
				}
			}
		}).catch(err => {
			alert('Network error: ' + err.message);
			if (btn) {
				btn.innerHTML = '<i class="fas fa-plug"></i>';
				btn.disabled = false;
			}
		});
	};

	window.gcalDisconnect = function(specialistId) {
		if (!confirm('Are you sure you want to disconnect Google Calendar for this specialist? This will stop syncing all future bookings.')) return;
		
		postJSON('admin/gcal_disconnect.php', { specialist_id: specialistId }).then(resp => {
			if (resp.success) {
				alert('Google Calendar disconnected successfully.');
				location.reload();
			} else {
				alert('Error: ' + (resp.message || 'Failed to disconnect'));
			}
		}).catch(err => {
			alert('Network error: ' + err.message);
		});
	};

	// gcalSyncAll function is now defined inline in booking_view_page.php
	// to avoid conflicts and ensure proper modal display
});
