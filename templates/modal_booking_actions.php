<!-- Modal Template for Booking Actions -->
<div class='modal fade' id='bookingActionModal' tabindex='-1' aria-hidden='true'>
  <div class='modal-dialog'>
    <div class='modal-content'>
      <div class='modal-header bg-primary text-white'>
        <h5 class='modal-title'>Booking Actions</h5>
        <button type='button' class='btn-close btn-close-white' data-bs-dismiss='modal'></button>
      </div>
      <div class='modal-body'>
        <p><strong>Client:</strong> <span id='modalClientName'></span></p>
        <p><strong>Phone:</strong> <span id='modalClientPhone'></span></p>
        <p><strong>Time:</strong> <span id='modalTimeInfo'></span></p>
        <p><span id='modalServiceDetails'></span></p>
        <p><strong>Specialist:</strong> <span id='modalSpecialistName'></span></p>
      </div>
      <div class='modal-footer'>
        <div class='d-flex justify-content-between w-100'>
          <div class='d-flex gap-2'>
            <button class='btn btn-primary' id='modifyBookingBtn' style='font-size: 70%;'>Modify Booking</button>
            <button class='btn btn-danger fw-bold' id='deleteBookingBtn' style='font-size: 70%;'>Cancel Booking</button>
          </div>
          <button class='btn btn-secondary' data-bs-dismiss='modal' style='font-size: 70%;'>Close Window</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Booking Modal -->
<div class='modal fade' id='addBookingModal' tabindex='-1' aria-hidden='true'>
  <div class='modal-dialog'>
    <div class='modal-content'>
      <div class='modal-header bg-primary text-white py-2'>
        <h5 class='modal-title mb-0'>Add New Booking</h5>
        <button type='button' class='btn-close btn-close-white' data-bs-dismiss='modal'></button>
      </div>
      <form id='addBookingForm'>
        <div class='modal-body py-3'>
          <input type='hidden' name='specialist_id' value='<?= $specialist_id ?? "" ?>' id='modalSpecialistId'>
          <input type='hidden' name='php_workpoint_id' value='<?= $workpoint_id ?? "" ?>'>
          <!-- Dedicated field for timeslot workpoint - this will be updated when a timeslot is clicked -->
          <input type='hidden' name='workpoint_id' id='timeslotWorkpointId' value=''>
          

          
          <div class='row g-2 mb-2'>
            <div class='col-4'>
              <label for='bookingClient' class='form-label mb-1 small'>Client Name</label>
            </div>
            <div class='col-8'>
              <input type='text' class='form-control form-control-sm' id='bookingClient' name='client' required>
            </div>
          </div>
          
          <div class='row g-2 mb-2'>
            <div class='col-4'>
              <label for='bookingClientPhone' class='form-label mb-1 small'>Phone Number</label>
            </div>
            <div class='col-8'>
              <input type='tel' class='form-control form-control-sm' id='bookingClientPhone' name='client_phone_nr' required>
            </div>
          </div>
          
          <div class='row g-2 mb-2'>
            <div class='col-4'>
              <label for='bookingDate' class='form-label mb-1 small'>Date</label>
            </div>
            <div class='col-8'>
              <input type='date' class='form-control form-control-sm' id='bookingDate' name='date' required>
            </div>
          </div>
          
          <div class='row g-2 mb-2'>
            <div class='col-4'>
              <label for='bookingTime' class='form-label mb-1 small'>Time</label>
            </div>
            <div class='col-8'>
              <input type='time' class='form-control form-control-sm' id='bookingTime' name='time' required>
            </div>
          </div>
          
          <div class='row g-2 mb-2'>
            <div class='col-4'>
              <label for='bookingService' class='form-label mb-1 small'>Service</label>
            </div>
            <div class='col-8'>
              <div class='input-group input-group-sm'>
                <select class='form-control form-control-sm' id='bookingService' name='service_id' required>
                  <option value=''>Select a service...</option>
                </select>
                <button type='button' class='btn btn-outline-secondary btn-sm' id='addNewServiceBtn'>
                  <i class='fas fa-plus'></i>
                </button>
              </div>
              <small class='form-text text-muted small'>Duration auto-set from service</small>
            </div>
          </div>
          
          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" id="addBookingSendSms" name="send_sms" <?php echo !$modal_is_web_excluded ? 'checked' : ''; ?>>
            <label class="form-check-label" for="addBookingSendSms">
              Send SMS confirmation to client
            </label>
          </div>
        </div>
        <div class='modal-footer py-2'>
          <button type='button' class='btn btn-secondary btn-sm' data-bs-dismiss='modal'>Cancel</button>
          <button type='submit' class='btn btn-primary btn-sm'>Add Booking</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add New Service Modal -->
<div class='modal fade' id='addServiceModal' tabindex='-1' aria-hidden='true'>
  <div class='modal-dialog'>
    <div class='modal-content'>
      <div class='modal-header bg-success text-white'>
        <h5 class='modal-title'>Add Service <?php echo isset($modal_supervisor_mode) && $modal_supervisor_mode ? '(Supervisor Mode)' : '(Specialist Mode)'; ?></h5>
        <button type='button' class='btn-close btn-close-white' data-bs-dismiss='modal'></button>
      </div>
      <form id='addServiceForm'>
        <div class='modal-body'>
          <input type='hidden' name='specialist_id' value='<?= $specialist_id ?? "" ?>' id='serviceModalSpecialistId'>
          <input type='hidden' name='workpoint_id' value='<?= $workpoint_id ?? "" ?>' id='serviceModalWorkpointId'>
          
          <div class='mb-3'>
            <label for='serviceName' class='form-label'>Service Name</label>
            <input type='text' class='form-control' id='serviceName' name='name_of_service' required
                   <?php if (!$modal_supervisor_mode && ($modal_specialist_permissions['specialist_can_add_services'] ?? 0) == 0): ?>
                   disabled
                   <?php endif; ?>>
          </div>
          
          <div class='mb-3'>
            <label for='serviceDuration' class='form-label'>Duration (minutes)</label>
            <select class='form-control' id='serviceDuration' name='duration' required
                    <?php if (!$modal_supervisor_mode && ($modal_specialist_permissions['specialist_can_add_services'] ?? 0) == 0): ?>
                    disabled
                    <?php endif; ?>>
              <option value='10'>10 minutes</option>
              <option value='20'>20 minutes</option>
              <option value='30'>30 minutes</option>
              <option value='40'>40 minutes</option>
              <option value='50'>50 minutes</option>
              <option value='60' selected>1 hour</option>
              <option value='70'>1 hour 10 minutes</option>
              <option value='80'>1 hour 20 minutes</option>
              <option value='90'>1 hour 30 minutes</option>
              <option value='100'>1 hour 40 minutes</option>
              <option value='110'>1 hour 50 minutes</option>
              <option value='120'>2 hours</option>
              <option value='130'>2 hours 10 minutes</option>
              <option value='140'>2 hours 20 minutes</option>
              <option value='150'>2 hours 30 minutes</option>
              <option value='160'>2 hours 40 minutes</option>
              <option value='170'>2 hours 50 minutes</option>
              <option value='180'>3 hours</option>
              <option value='210'>3 hours 30 minutes</option>
              <option value='240'>4 hours</option>
              <option value='270'>4 hours 30 minutes</option>
              <option value='300'>5 hours</option>
              <option value='330'>5 hours 30 minutes</option>
              <option value='360'>6 hours</option>
            </select>
          </div>
          
          <div class='mb-3'>
            <div class='row g-2'>
              <div class='col-6'>
                <label for='servicePrice' class='form-label'>Price</label>
                <div class='input-group'>
                  <input type='number' class='form-control' id='servicePrice' name='price_of_service' step='0.01' min='0' required
                         <?php if (!$modal_supervisor_mode && ($modal_specialist_permissions['specialist_can_add_services'] ?? 0) == 0): ?>
                         disabled
                         <?php endif; ?>>
                  <span class='input-group-text'>€</span>
                </div>
              </div>
              <div class='col-6'>
                <label for='serviceVat' class='form-label'>Vat%</label>
                <div class='input-group'>
                  <input type='text' class='form-control' id='serviceVat' name='procent_vat' value='0.00' maxlength='4' placeholder='0.00'
                         <?php if (!$modal_supervisor_mode && ($modal_specialist_permissions['specialist_can_add_services'] ?? 0) == 0): ?>
                         disabled
                         <?php endif; ?>>
                  <span class='input-group-text'>%</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class='modal-footer'>
          <button type='button' class='btn btn-secondary btn-sm' data-bs-dismiss='modal' style='padding: 0.25rem 0.5rem; font-size: 0.875rem;'>Cancel</button>
          <?php if (!$modal_supervisor_mode && ($modal_specialist_permissions['specialist_can_add_services'] ?? 0) == 0): ?>
          <span data-bs-toggle="tooltip" data-bs-placement="top" title="Permission Disabled for this action. Ask the supervisor or Enable this permissions from Supervisor Dashboard if a sole trader.">
          <?php endif; ?>
          <button type='submit' class='btn btn-success btn-sm' 
                  style='padding: 0.25rem 0.5rem; font-size: 0.875rem;'
                  <?php if (!$modal_supervisor_mode && ($modal_specialist_permissions['specialist_can_add_services'] ?? 0) == 0): ?>
                  disabled
                  <?php endif; ?>>
            <i class='fas fa-plus'></i> Add Service
          </button>
          <?php if (!$modal_supervisor_mode && ($modal_specialist_permissions['specialist_can_add_services'] ?? 0) == 0): ?>
          </span>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Shift Conflict Confirmation Modal -->
<div class='modal fade' id='shiftConflictModal' tabindex='-1' aria-hidden='true'>
  <div class='modal-dialog'>
    <div class='modal-content'>
      <div class='modal-header bg-warning text-dark'>
        <h5 class='modal-title'>⚠️ Shift Conflict Warning</h5>
        <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
      </div>
      <div class='modal-body'>
        <div class='alert alert-warning'>
          <i class='fas fa-exclamation-triangle'></i>
          <strong>Warning:</strong> This booking extends beyond the scheduled shift end time.
        </div>
        <div id='conflictDetails'>
          <!-- Conflict details will be populated here -->
        </div>
        <p><strong>Are you sure you want to proceed with this booking?</strong></p>
        <p class='text-muted'><small>This booking will extend beyond the normal working hours.</small></p>
      </div>
      <div class='modal-footer'>
        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
        <button type='button' class='btn btn-warning' id='confirmShiftConflictBtn'>Confirm Booking</button>
      </div>
    </div>
  </div>
</div>

<script>
// Organization timezone for JavaScript (passed from PHP) - using global variable from main page
// const organizationTimezone = '<?= getTimezoneForOrganisation($organisation) ?>';

function openBookingModal(client, time, specialist, bookingId, clientPhone, serviceName, serviceStart, serviceEnd, bookingDate) {
  document.getElementById('modalClientName').innerText = client;
  document.getElementById('modalClientPhone').innerText = clientPhone || 'N/A';
  
  let timeInfo = '';
  let serviceDetails = '';
  if (serviceStart && serviceEnd) {
    // Parse times as HH:mm
    const [startH, startM] = serviceStart.split(':').map(Number);
    const [endH, endM] = serviceEnd.split(':').map(Number);
    let startMinutes = startH * 60 + startM;
    let endMinutes = endH * 60 + endM;
    // Handle overnight bookings (end < start)
    if (endMinutes < startMinutes) endMinutes += 24 * 60;
    const diff = endMinutes - startMinutes;
    timeInfo = `${serviceStart} - ${serviceEnd}`;
    serviceDetails = `(${diff} min) for ${serviceName || 'N/A'}`;
  } else {
    timeInfo = time || 'N/A';
    serviceDetails = '';
  }
  document.getElementById('modalTimeInfo').innerText = timeInfo;
  document.getElementById('modalServiceDetails').innerText = serviceDetails;
  document.getElementById('modalSpecialistName').innerText = specialist;
  
  // Check if booking is in the past
  const isPastBooking = checkIfPastBooking(bookingDate, serviceStart);
  
  // Get specialist permissions (passed from PHP)
  const specialistPermissions = <?= json_encode($modal_specialist_permissions ?? ['specialist_can_delete_booking' => 0, 'specialist_can_modify_booking' => 0]) ?>;
  const supervisorMode = <?= json_encode($modal_supervisor_mode ?? false) ?>;
  
  // Disable buttons for past bookings or based on specialist permissions
  const modifyBtn = document.getElementById('modifyBookingBtn');
  const cancelBtn = document.getElementById('deleteBookingBtn');
  
  if (isPastBooking) {
    modifyBtn.disabled = true;
    modifyBtn.title = 'Cannot modify past bookings';
    modifyBtn.style.opacity = '0.5';
    modifyBtn.style.cursor = 'not-allowed';
    
    cancelBtn.disabled = true;
    cancelBtn.title = 'Cannot cancel past bookings';
    cancelBtn.style.opacity = '0.5';
    cancelBtn.style.cursor = 'not-allowed';
  } else {
    // Check specialist permissions when not in supervisor mode
    if (!supervisorMode) {
      // In specialist mode, check permissions
      const canModify = specialistPermissions.specialist_can_modify_booking == 1;
      const canDelete = specialistPermissions.specialist_can_delete_booking == 1;
      
      modifyBtn.disabled = !canModify;
      modifyBtn.title = canModify ? '' : 'You do not have permission to modify bookings';
      modifyBtn.style.opacity = canModify ? '1' : '0.5';
      modifyBtn.style.cursor = canModify ? 'pointer' : 'not-allowed';
      
      cancelBtn.disabled = !canDelete;
      cancelBtn.title = canDelete ? '' : 'You do not have permission to delete bookings';
      cancelBtn.style.opacity = canDelete ? '1' : '0.5';
      cancelBtn.style.cursor = canDelete ? 'pointer' : 'not-allowed';
    } else {
      // In supervisor mode, all buttons are enabled
      modifyBtn.disabled = false;
      modifyBtn.title = '';
      modifyBtn.style.opacity = '1';
      modifyBtn.style.cursor = 'pointer';
      
      cancelBtn.disabled = false;
      cancelBtn.title = '';
      cancelBtn.style.opacity = '1';
      cancelBtn.style.cursor = 'pointer';
    }
  }
  document.getElementById('modifyBookingBtn').onclick = function() {
    // Instead of redirecting, open the modify booking modal
    openModifyBookingModal(bookingId, client, time, serviceName, specialist, '');
  };
  document.getElementById('deleteBookingBtn').onclick = function() {
    // Create confirmation dialog HTML
    const confirmDialog = `
      <div class="modal fade" id="cancelConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
              <h5 class="modal-title">Confirm Booking Cancellation</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <p><strong>Are you sure you want to cancel this booking?</strong></p>
              <p>Client: <span id="confirmClientName"></span></p>
              <p>Time: <span id="confirmBookingTime"></span></p>
              <p>Service: <span id="confirmServiceName"></span></p>
              <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" id="sendSmsCheck" <?php echo !$modal_is_web_excluded ? 'checked' : ''; ?>>
                <label class="form-check-label" for="sendSmsCheck">
                  Send SMS notification to client
                </label>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep Booking</button>
              <button type="button" class="btn btn-danger" id="confirmCancelBtn">Yes, Cancel Booking</button>
            </div>
          </div>
        </div>
      </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('cancelConfirmModal');
    if (existingModal) {
      existingModal.remove();
    }
    
    // Add new modal to body
    document.body.insertAdjacentHTML('beforeend', confirmDialog);
    
    // Set modal content
    document.getElementById('confirmClientName').innerText = client;
    document.getElementById('confirmBookingTime').innerText = time;
    document.getElementById('confirmServiceName').innerText = serviceName || 'N/A';
    
    // Show the confirmation modal
    const modal = new bootstrap.Modal(document.getElementById('cancelConfirmModal'));
    modal.show();
    
    // Handle confirmation
    document.getElementById('confirmCancelBtn').onclick = function() {
      const sendSms = document.getElementById('sendSmsCheck').checked;
      
      // Create form data for delete request
      const formData = new FormData();
      formData.append('action', 'delete_booking');
      formData.append('booking_id', bookingId);
      formData.append('send_sms', sendSms ? '1' : '0');
      
      fetch('process_booking.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Success: no alert on success per UX spec
          // Close both modals
          bootstrap.Modal.getInstance(document.getElementById('cancelConfirmModal')).hide();
          bootstrap.Modal.getInstance(document.getElementById('bookingActionModal')).hide();
          // Reload page to reflect changes
          window.location.reload();
        } else {
          alert('Error: ' + (data.message || 'Failed to cancel booking'));
        }
      })
      .catch(error => {
        console.error('Error:', error);
    alert('Error: Failed to cancel booking. Please try again.');
      });
    };
  };
  
  new bootstrap.Modal(document.getElementById('bookingActionModal')).show();
}

// Helper function to check if booking is in the past
function checkIfPastBooking(bookingDate, serviceStart) {
  if (!bookingDate || !serviceStart) return false;
  
  // Get current date and time in organization timezone
  const now = new Date();
  const currentDate = now.toLocaleDateString('en-CA', { timeZone: organizationTimezone }); // YYYY-MM-DD format
  const currentTime = now.toLocaleTimeString('en-US', { 
    timeZone: organizationTimezone,
    hour12: false,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
  
  // If booking date is in the past, it's a past booking
  if (bookingDate < currentDate) {
    return true;
  }
  
  // If booking date is today, compare times
  if (bookingDate === currentDate) {
    return serviceStart < currentTime;
  }
  
  // If booking date is in the future, it's not a past booking
  return false;
}

function openAddBookingModal(date, time, specialistId) {
  document.getElementById('bookingDate').value = date || '';
  document.getElementById('bookingTime').value = time || '';
  
  // If specialistId is provided (supervisor mode), update the hidden field
  if (specialistId) {
    document.getElementById('modalSpecialistId').value = specialistId;
  }
  

  
  // Load services for the current specialist and workpoint
  loadServices();
  
  new bootstrap.Modal(document.getElementById('addBookingModal')).show();
}

function loadServices() {
  const specialistId = document.querySelector('input[name="specialist_id"]').value;
  let workpointId = document.querySelector('input[name="workpoint_id"]').value;
  
  // Try to get workpoint_id from sessionStorage if it's missing
  if (!workpointId || workpointId === '0' || workpointId === '') {
    workpointId = sessionStorage.getItem('selectedWorkpointId');
    if (workpointId) {
      console.log('Retrieved workpoint_id from sessionStorage:', workpointId);
      // Update the hidden field
      document.querySelector('input[name="workpoint_id"]').value = workpointId;
    }
  }
  
  if (!specialistId || !workpointId) {
    console.error('Missing specialist_id or workpoint_id:', { specialistId, workpointId });
    return;
  }
  
  const formData = new FormData();
  formData.append('action', 'get_services');
  formData.append('specialist_id', specialistId);
  formData.append('workpoint_id', workpointId);
  
  fetch('process_booking.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const serviceSelect = document.getElementById('bookingService');
      serviceSelect.innerHTML = '<option value="">Select a service...</option>';
      
      // Check if we're in specialist mode (only showing specialist's own services)
      const currentSpecialistId = document.getElementById('modalSpecialistId').value;
      const isSpecialistMode = data.services.length > 0 && data.services.every(service => service.id_specialist == currentSpecialistId);
      
      if (!isSpecialistMode) {
        // Add separator for specialist's services (for admin/supervisor mode)
        let specialistServicesAdded = false;
        
        data.services.forEach(service => {
          const option = document.createElement('option');
          option.value = service.unic_id;
          
          // Check if this service belongs to the current specialist
          const isSpecialistService = service.id_specialist == currentSpecialistId;
          
          // Compute price with VAT
          const vat = parseFloat(service.procent_vat || 0) || 0;
          const price = parseFloat(service.price_of_service || 0) || 0;
          const priceWithVat = (price * (1 + (vat/100))).toFixed(2);

          if (isSpecialistService && !specialistServicesAdded) {
            // Add separator for specialist's services
            const separator = document.createElement('option');
            separator.disabled = true;
            separator.textContent = '─── Specialist Services ───';
            separator.style.fontWeight = 'bold';
            separator.style.backgroundColor = '#e3f2fd';
            serviceSelect.appendChild(separator);
            specialistServicesAdded = true;
          } else if (!isSpecialistService && specialistServicesAdded) {
            // Add separator for other services
            const separator = document.createElement('option');
            separator.disabled = true;
            separator.textContent = '─── Other Services ───';
            separator.style.fontWeight = 'bold';
            separator.style.backgroundColor = '#f5f5f5';
            serviceSelect.appendChild(separator);
            specialistServicesAdded = false; // Prevent multiple separators
          }
          
          // Style the option based on whether it's the specialist's service
          if (isSpecialistService) {
            option.style.backgroundColor = '#e3f2fd';
            option.style.fontWeight = 'bold';
            option.textContent = `★ ${service.name_of_service} (${service.duration} min - €${priceWithVat})`;
          } else {
            option.textContent = `${service.name_of_service} (${service.duration} min - €${priceWithVat})`;
            if (service.specialist_name) {
              option.textContent += ` - ${service.specialist_name}`;
            }
          }
          
          serviceSelect.appendChild(option);
        });
      } else {
        // Specialist mode - just show services without separators
        data.services.forEach(service => {
          const option = document.createElement('option');
          option.value = service.unic_id;
          
          // Compute price with VAT
          const vat = parseFloat(service.procent_vat || 0) || 0;
          const price = parseFloat(service.price_of_service || 0) || 0;
          const priceWithVat = (price * (1 + (vat/100))).toFixed(2);
          
          option.textContent = `${service.name_of_service} (${service.duration} min - €${priceWithVat})`;
          serviceSelect.appendChild(option);
        });
      }
    } else {
      console.error('Error loading services:', data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
  });
}

// Handle form submission for adding booking
document.getElementById('addBookingForm').addEventListener('submit', function(e) {
  e.preventDefault();

  // Get workpoint_id from the timeslot field (most reliable source)
  let workpointId = document.getElementById('timeslotWorkpointId').value;
  
  // If timeslot field is empty, try sessionStorage
  if (!workpointId || workpointId === '0' || workpointId === '') {
    workpointId = sessionStorage.getItem('selectedWorkpointId');
  }
  
  // For specialist mode: Don't fall back to PHP variable (it's misleading)
  // For supervisor mode: PHP variable is important and should be used
  const isSupervisorMode = <?= isset($modal_supervisor_mode) && $modal_supervisor_mode ? 'true' : 'false' ?>;
  
  if (isSupervisorMode) {
    // Supervisor mode: PHP workpoint_id is important
    if (!workpointId || workpointId === '0' || workpointId === '') {
      workpointId = document.querySelector('input[name="php_workpoint_id"]').value;
    }
  }
  


  const formData = new FormData(this);
  
  // Get specialist_id from hidden field
  const specialistId = document.querySelector('input[name="specialist_id"]').value;
  
  // The workpointId is already set correctly above from sessionStorage or PHP variable
  // No need to read from dropdown again - this was causing the issue
  
  formData.append('specialist_id', specialistId);
  formData.append('workpoint_id', workpointId);
  
  // Validate that we have a workpoint_id
  if (!workpointId || workpointId === '0' || workpointId === '') {
    alert('Error: This timeslot is not available for booking. The specialist may not be working at this time or location.');
    console.error('Missing workpoint_id:', workpointId);
    return;
  }
  
  // Check for shift conflicts first
  checkShiftConflict(formData);
});

// Function to check for shift conflicts
function checkShiftConflict(formData) {
  const conflictCheckData = new FormData();
  conflictCheckData.append('action', 'check_shift_conflict');
  conflictCheckData.append('date', formData.get('date'));
  conflictCheckData.append('time', formData.get('time'));
  conflictCheckData.append('service_id', formData.get('service_id'));
  conflictCheckData.append('specialist_id', formData.get('specialist_id'));
  conflictCheckData.append('workpoint_id', formData.get('workpoint_id'));
  
  fetch('process_booking.php', {
    method: 'POST',
    body: conflictCheckData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success && data.has_conflict) {
      // Show conflict confirmation modal
      showShiftConflictModal(data.conflicts, formData);
    } else if (data.success && !data.has_conflict) {
      // No conflict, proceed with booking
      submitBooking(formData);
    } else {
      // Error in conflict check
      alert('Error: ' + (data.message || 'Failed to check shift conflicts'));
    }
  })
  .catch(error => {
    console.error('Error checking shift conflicts:', error);
    alert('Error: Failed to check shift conflicts. Please try again.');
  });
}

// Function to show shift conflict modal
function showShiftConflictModal(conflicts, formData) {
  const conflictDetails = document.getElementById('conflictDetails');
  let detailsHtml = '<div class="mb-3"><strong>Conflict Details:</strong><ul>';
  
  conflicts.forEach(conflict => {
    if (conflict.shift === 0) {
      // Booking doesn't start in any valid shift
      detailsHtml += `<li style="color: red;"><strong>⚠️ ${conflict.message}</strong></li>`;
    } else {
      const shiftStart = conflict.shift_start.substring(0, 5); // Remove seconds
      const shiftEnd = conflict.shift_end.substring(0, 5); // Remove seconds
      const bookingStart = conflict.booking_start.substring(0, 5); // Remove seconds
      const bookingEnd = conflict.booking_end.substring(0, 5); // Remove seconds
      
      // Calculate the time difference in minutes
      const shiftEndTime = new Date(`2000-01-01 ${conflict.shift_end}`);
      const bookingEndTime = new Date(`2000-01-01 ${conflict.booking_end}`);
      const diffMinutes = Math.round((bookingEndTime - shiftEndTime) / (1000 * 60));
      
      detailsHtml += `<li><strong>Shift ${conflict.shift}</strong> (${shiftStart} - ${shiftEnd})</li>`;
      detailsHtml += `<li>Booking starts at ${bookingStart} and ends at ${bookingEnd}</li>`;
      detailsHtml += `<li style="color: red;">⚠️ Booking extends ${diffMinutes} minutes beyond shift end time (${shiftEnd})</li>`;
    }
  });
  
  detailsHtml += '</ul></div>';
  conflictDetails.innerHTML = detailsHtml;
  
  // Store form data for later submission
  document.getElementById('shiftConflictModal').setAttribute('data-form-data', JSON.stringify(Object.fromEntries(formData)));
  
  // Show the modal
  new bootstrap.Modal(document.getElementById('shiftConflictModal')).show();
}

// Handle shift conflict confirmation
document.getElementById('confirmShiftConflictBtn').addEventListener('click', function() {
  const modal = document.getElementById('shiftConflictModal');
  const formDataJson = modal.getAttribute('data-form-data');
  const formData = new FormData();
  
  // Reconstruct FormData from stored JSON
  const data = JSON.parse(formDataJson);
  Object.keys(data).forEach(key => {
    formData.append(key, data[key]);
  });
  
  // Add action for booking submission
  formData.append('action', 'add_booking');
  
  // Close the conflict modal
  bootstrap.Modal.getInstance(modal).hide();
  
  // Submit the booking
  submitBooking(formData);
});

// Function to submit booking
function submitBooking(formData) {
  // Add action parameter if not already present
  if (!formData.has('action')) {
    formData.append('action', 'add_booking');
  }
  
  // Get SMS checkbox value
  const sendSmsCheckbox = document.getElementById('addBookingSendSms');
  formData.append('send_sms', sendSmsCheckbox && sendSmsCheckbox.checked ? '1' : '0');
  
  // Show loading state
  const submitBtn = document.querySelector('#addBookingForm button[type="submit"]');
  const originalText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
  submitBtn.disabled = true;
  
  fetch('process_booking.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    console.log('Response:', data);
    if (data.success) {
      // Success: no alert, just close modal and reload for freshness
      bootstrap.Modal.getInstance(document.getElementById('addBookingModal')).hide();
      window.location.reload();
    } else {
      // Get the workpoint_id that was used for the booking attempt (for debugging)
      const workpointId = formData.get('workpoint_id');
      
      // Show error message with workpoint_id for debugging
      let workpointInfo = '';
      if (workpointId && workpointId !== '') {
        workpointInfo = ` (workpoint_id=${workpointId})`;
      }
      
      alert(`Error: ${data.message || 'Failed to add booking'}${workpointInfo}`);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error: Failed to add booking. Please try again.');
  })
  .finally(() => {
    // Reset button
    submitBtn.innerHTML = originalText;
    submitBtn.disabled = false;
  });
}

// Handle "Add New Service" button click
document.getElementById('addNewServiceBtn').addEventListener('click', function() {
  // Get the current specialist_id and workpoint_id from the booking modal
  const currentSpecialistId = document.getElementById('modalSpecialistId').value;
  const currentWorkpointId = document.querySelector('input[name="workpoint_id"]').value;
  
  // Set the specialist_id and workpoint_id in the service modal
  if (currentSpecialistId) {
    document.getElementById('serviceModalSpecialistId').value = currentSpecialistId;
  }
  if (currentWorkpointId) {
    document.getElementById('serviceModalWorkpointId').value = currentWorkpointId;
  }
  
  // Remember where to return after closing service modal
  window.serviceReturnModal = 'addBookingModal';
  
  // Close the booking modal
  bootstrap.Modal.getInstance(document.getElementById('addBookingModal')).hide();
  // Open the service modal
  new bootstrap.Modal(document.getElementById('addServiceModal')).show();
});

// Handle "Add New Service" button click in modify modal
document.addEventListener('click', function(e) {
  if (e.target && e.target.id === 'modifyAddNewServiceBtn') {
    // Get the current specialist_id and workpoint_id from the modify modal
    const specialistSelect = document.getElementById('modifySpecialistSelect');
    let currentSpecialistId;
    
    if (specialistSelect.tagName === 'SELECT') {
      // Supervisor mode - get from dropdown
      currentSpecialistId = specialistSelect.value;
    } else {
      // Specialist mode - get from hidden field
      const specialistHidden = document.getElementById('modifySpecialistSelectHidden');
      currentSpecialistId = specialistHidden ? specialistHidden.value : '';
    }
    
    const currentWorkpointId = document.querySelector('input[name="workpoint_id"]').value;
    
    // Set the specialist_id and workpoint_id in the service modal
    if (currentSpecialistId) {
      document.getElementById('serviceModalSpecialistId').value = currentSpecialistId;
    }
    if (currentWorkpointId) {
      document.getElementById('serviceModalWorkpointId').value = currentWorkpointId;
    }
    
    // Remember where to return after closing service modal
    window.serviceReturnModal = 'modifyBookingModal';
    
    // Close the modify booking modal
    bootstrap.Modal.getInstance(document.getElementById('modifyBookingModal')).hide();
    // Open the service modal
    new bootstrap.Modal(document.getElementById('addServiceModal')).show();
  }
});

// Handle form submission for adding new service
document.getElementById('addServiceForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  // Prevent double submission
  const submitBtn = this.querySelector('button[type="submit"]');
  if (submitBtn && submitBtn.disabled) {
    console.log('Form already submitting, ignoring duplicate submission');
    return;
  }
  
  const formData = new FormData(this);
  // Normalize VAT client-side: remove spaces and %
  if (formData.has('procent_vat')) {
    let vat = (formData.get('procent_vat') || '').toString().replace(/\s|%/g, '');
    formData.set('procent_vat', vat);
  } else {
    formData.set('procent_vat', '0.00');
  }
  formData.append('action', 'add_service');
  
  // Get organization ID from PHP session if available
  <?php if (isset($_SESSION['organisation_id'])): ?>
  formData.append('organisation_id', '<?= $_SESSION['organisation_id'] ?>');
  <?php endif; ?>
  
  // Debug what we're sending
  console.log('Sending service data:', {
    specialist_id: formData.get('specialist_id'),
    workpoint_id: formData.get('workpoint_id'),
    service_name: formData.get('name_of_service')
  });
  
  // Show loading state
  const originalText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
  submitBtn.disabled = true;
  
  fetch('admin/process_add_service.php', {
    method: 'POST',
    body: formData
  })
  .then(response => {
    console.log('Response status:', response.status);
    return response.json();
  })
  .then(data => {
    console.log('Response data:', data);
    if (data.success) {
      // Close service modal
      bootstrap.Modal.getInstance(document.getElementById('addServiceModal')).hide();
      // Reset form
      this.reset();
      
      // Check if we need to return to a previous modal or reload the page
      if (window.serviceReturnModal) {
        // If we came from another modal, don't reload - let the hidden.bs.modal handler take care of it
        console.log('Returning to previous modal:', window.serviceReturnModal);
        // Re-enable button for next time
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
      } else {
        // If we came from the Organisation widget, reload the page to show the new service
        location.reload();
      }
    } else {
      alert('Error: ' + (data.message || 'Failed to add service'));
      // Re-enable button on error
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error: Failed to add service. Please try again.');
    // Re-enable button on error
    submitBtn.innerHTML = originalText;
    submitBtn.disabled = false;
  });
});

// When the Add Service modal is closed, return to previous modal if any
document.getElementById('addServiceModal').addEventListener('hidden.bs.modal', function () {
  const ret = window.serviceReturnModal;
  if (!ret) return;
  if (ret === 'addBookingModal') {
    // Reopen booking modal and reload services
    new bootstrap.Modal(document.getElementById('addBookingModal')).show();
    if (typeof loadServices === 'function') loadServices();
  } else if (ret === 'modifyBookingModal') {
    const modifyModal = document.getElementById('modifyBookingModal');
    new bootstrap.Modal(modifyModal).show();
    // Reload services in modify modal
    const specialistSelect = document.getElementById('modifySpecialistSelect');
    let specialistId;
    if (specialistSelect && specialistSelect.tagName === 'SELECT') {
      specialistId = specialistSelect.value;
    } else {
      const specialistHidden = document.getElementById('modifySpecialistSelectHidden');
      specialistId = specialistHidden ? specialistHidden.value : '';
    }
    const workpointId = document.querySelector('input[name="workpoint_id"]').value;
    const currentServiceIdEl = document.getElementById('modifyBookingService');
    const currentServiceId = currentServiceIdEl ? currentServiceIdEl.value : '';
    if (typeof loadModifyServices === 'function') {
      loadModifyServices(specialistId, workpointId, currentServiceId);
    }
  }
  // Clear marker
  window.serviceReturnModal = null;
});

// Add the modify booking modal HTML to the page
function addModifyBookingModal() {
  // Check if modal already exists
  if (document.getElementById('modifyBookingModal')) {
    return;
  }
  
  const modalHTML = `
    <!-- Modify Booking Modal -->
    <div class="modal fade" id="modifyBookingModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white py-2">
            <h5 class="modal-title mb-0">
              <i class="fas fa-edit"></i> Modify Booking
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <form id="modifyBookingForm">
            <div class="modal-body py-3">
              <input type="hidden" id="modifyBookingId" name="booking_id">
              <input type="hidden" id="modifyOriginalDate" name="original_date">
              <input type="hidden" id="modifyOriginalTime" name="original_time">
              <input type="hidden" id="modifyOriginalServiceId" name="original_service_id">
              
              <div class="row g-2 mb-2">
                <div class="col-4">
                  <label for="modifyClientName" class="form-label mb-1 small">Client Name</label>
                </div>
                <div class="col-8">
                  <input type="text" class="form-control form-control-sm" id="modifyClientName" name="client" required>
                </div>
              </div>
              
              <div class="row g-2 mb-2">
                <div class="col-4">
                  <label for="modifyClientPhone" class="form-label mb-1 small">Phone Number</label>
                </div>
                <div class="col-8">
                  <input type="tel" class="form-control form-control-sm" id="modifyClientPhone" name="client_phone_nr" required>
                </div>
              </div>
              
              <div class="row g-2 mb-2">
                <div class="col-4">
                  <label for="modifyBookingDate" class="form-label mb-1 small"><span style="color: red;">Date</span></label>
                </div>
                <div class="col-8">
                  <input type="date" class="form-control form-control-sm" id="modifyBookingDate" name="date" required>
                </div>
              </div>
              
              <div class="row g-2 mb-2">
                <div class="col-4">
                  <label for="modifyBookingTime" class="form-label mb-1 small"><span style="color: red;">Time</span></label>
                </div>
                <div class="col-8">
                  <input type="time" class="form-control form-control-sm" id="modifyBookingTime" name="time" required>
                </div>
              </div>
              
              <div class="row g-2 mb-2">
                <div class="col-4">
                  <label for="modifyBookingService" class="form-label mb-1 small">Service</label>
                </div>
                <div class="col-8">
                  <div class="input-group input-group-sm">
                    <select class="form-control form-control-sm" id="modifyBookingService" name="service_id" required>
                      <option value="">Select a service...</option>
                    </select>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="modifyAddNewServiceBtn">
                      <i class="fas fa-plus"></i>
                    </button>
                  </div>
                  <small class="form-text text-muted small">Duration auto-set from service</small>
                </div>
              </div>
              
              <div class="row g-2 mb-2">
                <div class="col-4">
                  <label for="modifySpecialistSelect" class="form-label mb-1 small">Specialist</label>
                </div>
                <div class="col-8">
                  <?php if (isset($modal_supervisor_mode) && $modal_supervisor_mode): ?>
                  <select class="form-control form-control-sm" id="modifySpecialistSelect" name="specialist_id" required>
                    <option value="">Loading specialists...</option>
                  </select>
                  <?php else: ?>
                  <input type="text" class="form-control form-control-sm" id="modifySpecialistSelect" name="specialist_id" readonly style="background-color: #f8f9fa;">
                  <input type="hidden" id="modifySpecialistSelectHidden" name="specialist_id">
                  <?php endif; ?>
                </div>
              </div>
              
              <div class="row g-2 mb-2">
                <div class="col-4">
                  <label for="modifyWorkpointSelect" class="form-label mb-1 small">Workpoint</label>
                </div>
                <div class="col-8">
                  <?php if (isset($has_multiple_workpoints) && $has_multiple_workpoints && (isset($modal_supervisor_mode) ? !$modal_supervisor_mode : true)): ?>
                  <select class="form-control form-control-sm" id="modifyWorkpointSelect" name="workpoint_id" required>
                    <option value="">Loading workpoints...</option>
                  </select>
                  <?php else: ?>
                  <input type="text" class="form-control form-control-sm" id="modifyWorkpointName" readonly>
                  <input type="hidden" name="workpoint_id" value="<?= $workpoint_id ?? '' ?>">
                  <?php endif; ?>
                </div>
              </div>
              
              <!-- Conflict warning for date/time changes -->
              <div id="modifyConflictWarning" class="alert alert-danger alert-sm" style="display: none;">
                <i class="fas fa-exclamation-circle"></i>
                <strong>Warning:</strong> Changing the date or time may cause conflicts with other bookings.
              </div>
              
              <!-- Warning text at bottom -->
              <div class="mt-3 pt-2 border-top">
                <small class="text-muted">
                  <i class="fas fa-exclamation-triangle text-warning"></i>
                  <strong>Important:</strong> If you need to change the <span style="color: red; text-decoration: underline;">date</span> or <span style="color: red; text-decoration: underline;">time</span> of this booking, 
                  it's recommended to delete this booking and create a new one by clicking on the desired time slot. 
                  This prevents conflicts with other bookings.
                </small>
              </div>
              
              <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" id="modifyBookingSendSms" name="send_sms" <?php echo !$modal_is_web_excluded ? 'checked' : ''; ?>>
                <label class="form-check-label" for="modifyBookingSendSms">
                  Send SMS notification to client about changes
                </label>
              </div>
            </div>
            <div class="modal-footer py-2">
              <div class="d-flex gap-2 w-100">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveModifyBookingBtn">
                  <i class="fas fa-save"></i> Save Changes
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  `;
  
  document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Initialize the modify booking modal when the page loads
document.addEventListener('DOMContentLoaded', function() {
  addModifyBookingModal();
  
  // Define supervisor mode for JavaScript
  window.isSupervisorMode = <?php echo isset($modal_supervisor_mode) && $modal_supervisor_mode ? 'true' : 'false'; ?>;
  
  // Add event listener for save button
  document.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'saveModifyBookingBtn') {
      handleModifyBookingSubmit();
    }
  });
});



// Temporary stub function to prevent errors (can be removed later)
function updateDebugWorkpointInfo() {
  // Empty function to prevent "Cannot set properties of null" errors
  // This is a temporary fix for browser cache issues
}

// Function to open modify booking modal
function openModifyBookingModal(bookingId, clientName, bookingTime, serviceName, specialistName, workpointName) {
  // Set the booking ID
  document.getElementById('modifyBookingId').value = bookingId;
  
  // Parse the booking time - it's usually in format "HH:MM - HH:MM" or just "HH:MM"
  let bookingTimeOnly = '';
  let bookingDate = '';
  
  if (bookingTime && bookingTime.includes(' - ')) {
    // Extract start time from range like "14:30 - 15:30"
    bookingTimeOnly = bookingTime.split(' - ')[0];
  } else if (bookingTime) {
    // Just a time like "14:30"
    bookingTimeOnly = bookingTime;
  }
  
  // For now, set the date to today - it will be updated when booking details are loaded
  const today = new Date();
  bookingDate = today.toISOString().split('T')[0];
  
  // Set original values for comparison
  document.getElementById('modifyOriginalDate').value = bookingDate;
  document.getElementById('modifyOriginalTime').value = bookingTimeOnly;
  
  // Fill the form with current booking data
  document.getElementById('modifyClientName').value = clientName;
  document.getElementById('modifyClientPhone').value = ''; // Will be loaded from booking data
  document.getElementById('modifyBookingDate').value = bookingDate;
  document.getElementById('modifyBookingTime').value = bookingTimeOnly;
  
  // Handle workpoint field - check if it's a select or input
  const workpointSelect = document.getElementById('modifyWorkpointSelect');
  const workpointNameElement = document.getElementById('modifyWorkpointName');
  if (workpointSelect) {
    // It's a select dropdown (multiple workpoints)
    workpointSelect.value = ''; // Will be loaded from booking data
  } else if (workpointNameElement) {
    // It's a readonly input (single workpoint)
    workpointNameElement.value = workpointName || ''; // Handle empty workpointName
  }
  
  // Load booking details and services
  loadBookingDetails(bookingId);
  
  // Show the modal
  new bootstrap.Modal(document.getElementById('modifyBookingModal')).show();
  
  // Add event listeners for date/time changes
  document.getElementById('modifyBookingDate').addEventListener('change', checkForDateTimeChanges);
  document.getElementById('modifyBookingTime').addEventListener('change', checkForDateTimeChanges);
}

// Function to load booking details
function loadBookingDetails(bookingId) {
  const formData = new FormData();
  formData.append('action', 'get_booking_details');
  formData.append('booking_id', bookingId);
  
  fetch('process_booking.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const booking = data.booking;
      
      // Fill in the missing fields
      document.getElementById('modifyClientPhone').value = booking.client_phone_nr;
      document.getElementById('modifyOriginalServiceId').value = booking.service_id;
      
      // Set workpoint name if available - handle both select and input cases
      const workpointSelect = document.getElementById('modifyWorkpointSelect');
      const workpointName = document.getElementById('modifyWorkpointName');
      if (booking.workpoint_name) {
        if (workpointSelect) {
          // It's a select dropdown - will be populated by loadModifyWorkpoints
        } else if (workpointName) {
          // It's a readonly input
          workpointName.value = booking.workpoint_name;
        }
      }
      
      // Set the correct date and time from the booking
      if (booking.booking_start_datetime) {
        const bookingDateTime = new Date(booking.booking_start_datetime);
        const bookingDate = bookingDateTime.toISOString().split('T')[0];
        const bookingTime = bookingDateTime.toTimeString().substring(0, 5);
        
        document.getElementById('modifyBookingDate').value = bookingDate;
        document.getElementById('modifyBookingTime').value = bookingTime;
        document.getElementById('modifyOriginalDate').value = bookingDate;
        document.getElementById('modifyOriginalTime').value = bookingTime;
      }
      
      // Load specialists and set current specialist
      loadModifySpecialists(booking.id_specialist, booking.id_work_place);
      
      // Load workpoints for this specialist (if they work at multiple locations)
      loadModifyWorkpoints(booking.id_specialist, booking.id_work_place);
      
      // Load services for this specialist and workpoint
      loadModifyServices(booking.id_specialist, booking.id_work_place, booking.service_id);
      
      // Set the current service ID in a hidden field for backup
      document.getElementById('modifyOriginalServiceId').value = booking.service_id;
    } else {
      alert('Error loading booking details: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error loading booking details');
  });
}

// Function to load specialists for modify modal
function loadModifySpecialists(currentSpecialistId, workpointId) {
  const formData = new FormData();
  formData.append('action', 'get_specialists_for_workpoint');
  formData.append('workpoint_id', workpointId);
  
  fetch('process_booking.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const specialistSelect = document.getElementById('modifySpecialistSelect');
      const specialistHidden = document.getElementById('modifySpecialistSelectHidden');
      
      // Check if we're in supervisor mode (dropdown) or specialist mode (readonly input)
      if (specialistSelect.tagName === 'SELECT') {
        // Supervisor mode - populate dropdown
        specialistSelect.innerHTML = '<option value="">Select a specialist...</option>';
        
        data.specialists.forEach(specialist => {
          const option = document.createElement('option');
          option.value = specialist.unic_id;
          option.textContent = `${specialist.name} (${specialist.speciality})`;
          
          // Pre-select the current specialist
          if (specialist.unic_id == currentSpecialistId) {
            option.selected = true;
          }
          
          specialistSelect.appendChild(option);
        });
      } else {
        // Specialist mode - set readonly input with current specialist name
        const currentSpecialist = data.specialists.find(s => s.unic_id == currentSpecialistId);
        if (currentSpecialist) {
          specialistSelect.value = `${currentSpecialist.name} (${currentSpecialist.speciality})`;
          if (specialistHidden) {
            specialistHidden.value = currentSpecialistId;
          }
        }
      }
    } else {
      console.error('Error loading specialists:', data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
  });
}

// Function to load workpoints for modify modal
function loadModifyWorkpoints(specialistId, currentWorkpointId) {
  const formData = new FormData();
  formData.append('action', 'get_specialist_workpoints');
  formData.append('specialist_id', specialistId);
  
  fetch('process_booking.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const workpointSelect = document.getElementById('modifyWorkpointSelect');
      const workpointName = document.getElementById('modifyWorkpointName');
      
      // Check if we're in specialist mode with multiple workpoints
      if (workpointSelect) {
        // Specialist mode with multiple workpoints - populate dropdown
        workpointSelect.innerHTML = '<option value="">Select a workpoint...</option>';
        
        data.workpoints.forEach(workpoint => {
          const option = document.createElement('option');
          option.value = workpoint.wp_id;
          option.textContent = `${workpoint.wp_name} (${workpoint.wp_address})`;
          
          // Pre-select the current workpoint
          if (workpoint.wp_id == currentWorkpointId) {
            option.selected = true;
          }
          
          workpointSelect.appendChild(option);
        });
        
        // Add change event listener to reload services when workpoint changes
        workpointSelect.addEventListener('change', function() {
          const newWorkpointId = this.value;
          const specialistId = document.getElementById('modifySpecialistSelectHidden')?.value || 
                              document.getElementById('modifySpecialistSelect')?.value;
          const currentServiceId = document.getElementById('modifyBookingService').value;
          
          if (newWorkpointId && specialistId) {
            loadModifyServices(specialistId, newWorkpointId, currentServiceId);
          }
        });
      } else if (workpointName) {
        // Single workpoint mode - set readonly input
        const currentWorkpoint = data.workpoints.find(w => w.wp_id == currentWorkpointId);
        if (currentWorkpoint) {
          workpointName.value = `${currentWorkpoint.wp_name} (${currentWorkpoint.wp_address})`;
        }
      }
    } else {
      console.error('Error loading workpoints:', data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
  });
}

// Function to load services for modify modal
function loadModifyServices(specialistId, workpointId, selectedServiceId) {
  const formData = new FormData();
  formData.append('action', 'get_services');
  formData.append('specialist_id', specialistId);
  formData.append('workpoint_id', workpointId);
  
  fetch('process_booking.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    console.log('Services loaded:', data);
    if (data.success) {
      const serviceSelect = document.getElementById('modifyBookingService');
      serviceSelect.innerHTML = '<option value="">Select a service...</option>';
      
      // Add separator for specialist's services
      let specialistServicesAdded = false;
      
      data.services.forEach(service => {
        const option = document.createElement('option');
        option.value = service.unic_id;
        
        // Check if this service belongs to the current specialist
        const isSpecialistService = service.id_specialist == specialistId;
        
        if (isSpecialistService && !specialistServicesAdded) {
          // Add separator for specialist's services
          const separator = document.createElement('option');
          separator.disabled = true;
          separator.textContent = '─── Specialist Services ───';
          separator.style.fontWeight = 'bold';
          separator.style.backgroundColor = '#e3f2fd';
          serviceSelect.appendChild(separator);
          specialistServicesAdded = true;
        } else if (!isSpecialistService && specialistServicesAdded) {
          // Add separator for other services
          const separator = document.createElement('option');
          separator.disabled = true;
          separator.textContent = '─── Other Services ───';
          separator.style.fontWeight = 'bold';
          separator.style.backgroundColor = '#f5f5f5';
          serviceSelect.appendChild(separator);
          specialistServicesAdded = false; // Prevent multiple separators
        }
        
        // Compute price with VAT
        const vat = parseFloat(service.procent_vat || 0) || 0;
        const price = parseFloat(service.price_of_service || 0) || 0;
        const priceWithVat = (price * (1 + (vat/100))).toFixed(2);

        // Style the option based on whether it's the specialist's service
        if (isSpecialistService) {
          option.style.backgroundColor = '#e3f2fd';
          option.style.fontWeight = 'bold';
          option.textContent = `★ ${service.name_of_service} (${service.duration} min - €${priceWithVat})`;
        } else {
          option.textContent = `${service.name_of_service} (${service.duration} min - €${priceWithVat})`;
          if (service.specialist_name) {
            option.textContent += ` - ${service.specialist_name}`;
          }
        }
        
        // Pre-select the current service
        if (service.unic_id == selectedServiceId) {
          option.selected = true;
        }
        
        serviceSelect.appendChild(option);
      });
      
      // Add change listener for service duration
      serviceSelect.addEventListener('change', function() {
        const selectedService = data.services.find(s => s.unic_id == this.value);
        if (selectedService) {
          // Update duration display if needed
          const durationElement = document.getElementById('modifyServiceDuration');
          if (durationElement) {
            durationElement.textContent = selectedService.duration;
          }
        }
      });
    } else {
      console.error('Error loading services:', data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
  });
}

// Function to check for date/time changes
function checkForDateTimeChanges() {
  const originalDate = document.getElementById('modifyOriginalDate').value;
  const originalTime = document.getElementById('modifyOriginalTime').value;
  const currentDate = document.getElementById('modifyBookingDate').value;
  const currentTime = document.getElementById('modifyBookingTime').value;
  
  const warningDiv = document.getElementById('modifyConflictWarning');
  
  if (originalDate !== currentDate || originalTime !== currentTime) {
    warningDiv.style.display = 'block';
  } else {
    warningDiv.style.display = 'none';
  }
}

// Handle modify booking form submission
function handleModifyBookingSubmit() {
  const form = document.getElementById('modifyBookingForm');
  const formData = new FormData(form);
  formData.append('action', 'modify_booking');
  
  // Get specialist ID - handle both dropdown (supervisor mode) and readonly input (specialist mode)
  const specialistSelect = document.getElementById('modifySpecialistSelect');
  let specialistId;
  
  if (specialistSelect.tagName === 'SELECT') {
    // Supervisor mode - get from dropdown
    specialistId = specialistSelect.value;
  } else {
    // Specialist mode - get from hidden field
    const specialistHidden = document.getElementById('modifySpecialistSelectHidden');
    specialistId = specialistHidden ? specialistHidden.value : '';
  }
  
  // Get workpoint ID - handle both dropdown (multiple workpoints) and hidden field (single workpoint)
  let workpointId;
  const workpointSelect = document.getElementById('modifyWorkpointSelect');
  if (workpointSelect && workpointSelect.tagName === 'SELECT') {
    // Multiple workpoints mode - get from dropdown
    workpointId = workpointSelect.value;
  } else {
    // Single workpoint mode - get from hidden field
    workpointId = document.querySelector('input[name="workpoint_id"]').value;
  }
  
  // Get service ID - use the selected service or fall back to original
  const serviceSelect = document.getElementById('modifyBookingService');
  let serviceId = serviceSelect ? serviceSelect.value : '';
  
  // If no service is selected, use the original service ID
  if (!serviceId) {
    const originalServiceId = document.getElementById('modifyOriginalServiceId').value;
    if (originalServiceId) {
      serviceId = originalServiceId;
      console.log('Using original service ID:', serviceId);
    }
  }
  
  formData.append('specialist_id', specialistId);
  formData.append('workpoint_id', workpointId);
  formData.append('service_id', serviceId);
  
  // Get SMS checkbox value
  const sendSmsCheckbox = document.getElementById('modifyBookingSendSms');
  formData.append('send_sms', sendSmsCheckbox && sendSmsCheckbox.checked ? '1' : '0');
  
  // Debug: Log the form data being sent
  console.log('Form data being sent:');
  for (let [key, value] of formData.entries()) {
    console.log(key + ': ' + value);
  }
  
  // Disable button and show loading
  const btn = document.getElementById('saveModifyBookingBtn');
  const originalText = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  btn.disabled = true;
  
  fetch('process_booking.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Success: no alert on modify success
      // Close the modal
      bootstrap.Modal.getInstance(document.getElementById('modifyBookingModal')).hide();
      // Reload page to reflect changes
      window.location.reload();
    } else {
      alert('Error: ' + (data.message || 'Failed to modify booking'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error: Failed to modify booking. Please try again.');
  })
  .finally(() => {
    // Re-enable button
    btn.innerHTML = originalText;
    btn.disabled = false;
  });
}
</script>