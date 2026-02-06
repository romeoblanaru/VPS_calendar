<?php
// ... your PHP session and authentication logic here ...
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// =========================
//     ADMIN DASHBOARD
// =========================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/main.css?v=<?=time()?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    .dropdown {
        position: relative;
        display: inline-block;
    }
    
    .dropdown-content {
        display: none;
        position: absolute;
        background-color: #f9f9f9;
        min-width: 160px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 1000;
        border-radius: 4px;
        top: calc(100% + 5px);
        right: 0;
        border: 1px solid #ddd;
    }
    
    .dropdown-content a {
        color: #333;
        padding: 12px 16px;
        text-decoration: none;
        display: block;
        cursor: pointer;
        transition: background-color 0.3s;
        border-bottom: 1px solid #eee;
    }
    
    .dropdown-content a:last-child {
        border-bottom: none;
    }
    
    .dropdown-content a:hover {
        background-color: #f1f1f1;
    }
    
    .dropdown:hover .dropdown-content,
    .dropdown.show .dropdown-content {
        display: block !important;
    }
    
    .dropdown-btn {
        padding: 7px 18px;
        background: #28a745;
        color: #fff;
        border: none;
        border-radius: 4px;
        font-size: 1em;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        position: relative;
    }
    
    .dropdown-btn:hover {
        background: #218838;
    }
    
    /* Debug styles to ensure visibility */
    .dropdown-content {
        background: white !important;
    }
    
    /* Force dropdown to show with higher specificity */
    div.dropdown .dropdown-content {
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s, visibility 0.3s;
    }
    
    div.dropdown:hover .dropdown-content,
    div.dropdown.show .dropdown-content {
        opacity: 1 !important;
        visibility: visible !important;
        display: block !important;
    }

    /* Fix for organisation card dropdown alignment */
    .org-card .dropdown-content {
        position: static !important;
        box-shadow: none !important;
        min-width: auto !important;
        width: auto !important;
        box-sizing: border-box !important;
    }

    .org-card.expanded .dropdown-content {
        padding: 12px 0 16px 24px !important;
    }
    </style>
</head>
<body>

<div class="header" style="min-height: 120px; display: flex; align-items: center; padding: 0;">
    <div style="width: 80%; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 20px 0;">
        <h2 style="margin: 0;">Admin2 Dashboard</h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <button id="list_all_org" style="padding: 7px 18px; background:#00adb5; color:#fff; border:none; border-radius:4px; font-size:1em; cursor:pointer; display: inline-block;">List.All.Org</button>
            <div class="dropdown">
                <button class="dropdown-btn">
                    <i class="fas fa-tools"></i>
                    Tools...
                </button>
                <div class="dropdown-content" style="display: none; position: absolute; background-color: white; min-width: 160px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); z-index: 9999; border-radius: 4px; top: 100%; right: 0; margin-top: 5px; border: 1px solid #ddd;">
                    <a id="btn_webhooks" style="color: #333; padding: 12px 16px; text-decoration: none; display: block; cursor: pointer; border-bottom: 1px solid #eee;"><i class="fas fa-plug" style="margin-right: 8px;"></i>Webhooks</a>
                    <a id="btn_google_calendar" style="color: #333; padding: 12px 16px; text-decoration: none; display: block; cursor: pointer; border-bottom: 1px solid #eee;"><i class="fab fa-google" style="margin-right: 8px; color: #4285f4;"></i>G.Calendar</a>
                    <a id="btn_php_workers" style="color: #333; padding: 12px 16px; text-decoration: none; display: block; cursor: pointer; border-bottom: 1px solid #eee;"><i class="fas fa-cogs" style="margin-right: 8px; color: #6c757d;"></i>PHP Workers</a>
                    <a id="btn_server_tools" style="color: #333; padding: 12px 16px; text-decoration: none; display: block; cursor: pointer;"><i class="fas fa-server" style="margin-right: 8px; color: #ff6b00;"></i>Server Tools</a>
                </div>
            </div>
            <button id="btn_csv" style="padding: 7px 18px; background:#00adb5; color:#fff; border:none; border-radius:4px; font-size:1em; cursor:pointer; display: inline-block;">CSV Files</button>
            <button id="btn_logout" style="padding: 7px 18px; background:#00adb5; color:#fff; border:none; border-radius:4px; font-size:1em; cursor:pointer; display: inline-block;">Logout</button>
        </div>
    </div>
</div>

<!-- Bottom Panel -->



<!-- =========================
     BOTTOM PANEL START
========================= -->
<div id="bottom_panel">
    <div style="width: 100%; margin: 0 auto; background-color: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e9ecef;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 id="bottom_panel_title">Bottom Panel</h3>
            <button id="add_new_org" style="padding: 8px 16px; padding-right: 20px; background: white; color: #333; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.3s ease; font-weight: 500; display: none;">Add.New.Org</button>
        </div>
        <div id="bottom_panel_content">
            <p>Welcome! Please select an action above.</p>
        </div>
    </div>
</div>
<!-- =========================
     BOTTOM PANEL END
========================= -->

<script>
// Check for success message from specialist addition
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1' && urlParams.get('specialist_id')) {
        const specialistId = urlParams.get('specialist_id');
        showNotification('✅ Specialist added successfully! Specialist ID: ' + specialistId, 'success');
        // Clean up URL
        const newUrl = new URL(window.location);
        newUrl.searchParams.delete('success');
        newUrl.searchParams.delete('specialist_id');
        window.history.replaceState({}, '', newUrl);
        
        // Reload the bottom panel to show the new specialist
        if (typeof loadBottomPanel === 'function') {
            loadBottomPanel('list_all_org');
        }
    }
    
    // Check for error message from specialist addition
    if (urlParams.get('error')) {
        const errorMessage = urlParams.get('error');
        showNotification('❌ Error adding specialist: ' + decodeURIComponent(errorMessage), 'error');
        // Clean up URL
        const newUrl = new URL(window.location);
        newUrl.searchParams.delete('error');
        window.history.replaceState({}, '', newUrl);
    }
});

// Utility: load content into bottom_panel via AJAX
function loadBottomPanel(action) {
    document.getElementById('bottom_panel_content').innerHTML = "<em>Loading...</em>";
    
    // Remove the title row entirely to save vertical space except when listing orgs (where we keep the Add button on the right)
    const titleRow = document.getElementById('bottom_panel_title').parentElement;
    const addBtnToggle = document.getElementById('add_new_org');
    if (action === 'list_all_org') {
        // Show row with contextual title and Add.New.Org button
        if (titleRow) titleRow.style.display = 'flex';
        document.getElementById('bottom_panel_title').textContent = 'Organisations List';
        if (addBtnToggle) addBtnToggle.style.display = 'inline-block';
    } else {
        // Hide the entire row (removes the empty space and any leftover title)
        if (titleRow) titleRow.style.display = 'none';
        if (addBtnToggle) addBtnToggle.style.display = 'none';
    }
    
    fetch('load_bottom_panel.php?action=' + encodeURIComponent(action))
        .then(response => response.text())
        .then(html => {
            document.getElementById('bottom_panel_content').innerHTML = html;
            // After content loads, attach CSV AJAX handler if present
            (function attachCsvAjax(){
                var container = document.getElementById('bottom_panel_content');
                if (!container) return;
                var form = container.querySelector('#csvImportForm');
                if (!form) return;
                // Avoid multiple bindings
                if (form.__csvBound) return; 
                form.__csvBound = true;
                // Override file input inline onchange (form.submit) with AJAX upload
                var fileInput = container.querySelector('#csv_file');
                if (fileInput) {
                    fileInput.onchange = function(ev){
                        if (!this.files || !this.files.length) return;
                        var fd = new FormData(form);
                        fetch('csv_files.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                            .then(function(res){
                                var ct = res.headers.get('content-type') || '';
                                if (ct.indexOf('application/json') !== -1) return res.json();
                                return res.text().then(function(t){ return { __html: t }; });
                            })
                            .then(function(payload){
                                if (!payload) return;
                                if (payload.__html !== undefined) {
                                    container.innerHTML = payload.__html;
                                    // Re-attach after re-render
                                    attachCsvAjax();
                                    window.scrollTo(0,0);
                                    return;
                                }
                                if (payload.success) {
                                    if (typeof loadBottomPanel === 'function') {
                                        loadBottomPanel('list_all_org');
                                    }
                                }
                            })
                            .catch(function(err){ console.error('CSV upload failed', err); });
                        ev.preventDefault();
                        return false;
                    };
                }
                form.addEventListener('submit', function(e){
                    e.preventDefault();
                    var fd = new FormData(form);
                    fetch('csv_files.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                        .then(function(res){
                            var ct = res.headers.get('content-type') || '';
                            if (ct.indexOf('application/json') !== -1) return res.json();
                            return res.text().then(function(t){ return { __html: t }; });
                        })
                        .then(function(payload){
                            if (!payload) return;
                            if (payload.__html !== undefined) {
                                container.innerHTML = payload.__html;
                                // Re-attach after re-render
                                attachCsvAjax();
                                window.scrollTo(0,0);
                                return;
                            }
                            if (payload.success) {
                                if (typeof loadBottomPanel === 'function') {
                                    loadBottomPanel('list_all_org');
                                }
                            }
                        })
                        .catch(function(err){ console.error('CSV upload failed', err); });
                });
            })();
            
            // Initialize Google Calendar management if loaded
            if (action === 'google_calendar') {
                initializeGoogleCalendarPanel();
            }
        });
}

// Wait for DOM to be ready before attaching event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Main button listeners
    var listAllOrg = document.getElementById('list_all_org');
    var btnCsv = document.getElementById('btn_csv');
    var btnLogout = document.getElementById('btn_logout');
    
    if (listAllOrg) listAllOrg.onclick = function() { loadBottomPanel('list_all_org'); };
    if (btnCsv) btnCsv.onclick = function() { loadBottomPanel('csv_files'); };
    if (btnLogout) btnLogout.onclick = function() { window.location.href = '../logout.php'; };
    
    // Dropdown toggle functionality
    var dropdown = document.querySelector('.dropdown');
    var dropdownBtn = document.querySelector('.dropdown-btn');
    var dropdownContent = document.querySelector('.dropdown-content');
    
    if (dropdownBtn && dropdown && dropdownContent) {
        dropdownBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            // Toggle display directly
            if (dropdownContent.style.display === 'block') {
                dropdownContent.style.display = 'none';
            } else {
                dropdownContent.style.display = 'block';
            }
            console.log('Dropdown display:', dropdownContent.style.display);
        };
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                dropdownContent.style.display = 'none';
            }
        });
        
        // Also handle hover - only on enter, not on leave
        dropdown.onmouseenter = function() {
            dropdownContent.style.display = 'block';
        };
    }
    
    // Dropdown menu items
    var btnWebhooks = document.getElementById('btn_webhooks');
    var btnGoogleCalendar = document.getElementById('btn_google_calendar');
    var btnPhpWorkers = document.getElementById('btn_php_workers');
    var btnServerTools = document.getElementById('btn_server_tools');
    
    if (btnWebhooks) btnWebhooks.onclick = function(e) { 
        e.preventDefault();
        loadBottomPanel('webhook_dashboard'); 
        dropdownContent.style.display = 'none';
    };
    if (btnGoogleCalendar) btnGoogleCalendar.onclick = function(e) { 
        e.preventDefault();
        loadBottomPanel('google_calendar'); 
        dropdownContent.style.display = 'none';
    };
    if (btnPhpWorkers) btnPhpWorkers.onclick = function(e) { 
        e.preventDefault();
        loadBottomPanel('php_workers'); 
        dropdownContent.style.display = 'none';
    };
    if (btnServerTools) btnServerTools.onclick = function(e) { 
        e.preventDefault();
        loadBottomPanel('server_tools'); 
        dropdownContent.style.display = 'none';
    };
});

// PHP Workers control function - define it here so it's available globally
window.controlWorkerService = function(worker, action, sudoPassword = null) {
    
    // For start-systemd, ask for sudo password upfront
    if (action === 'start-systemd' && !sudoPassword) {
        const password = prompt('Enter your sudo password to start systemd service:');
        if (!password) return;
        
        // Just pass the sudo password - SSH will use key authentication
        window.controlWorkerService(worker, action, password);
        return;
    }
    
    // Skip confirmation for systemd operations with password
    if (!sudoPassword && !confirm(`Are you sure you want to ${action} the ${worker} worker?`)) {
        return;
    }
    
    // Show loading state - only disable worker control buttons
    const workerButtons = document.querySelectorAll('.worker-control-btn');
    workerButtons.forEach(btn => btn.disabled = true);
    
    // Show loading message
    const statusDivs = document.querySelectorAll('span');
    statusDivs.forEach(span => {
        if (span.textContent.includes('Active') || span.textContent.includes('Inactive')) {
            span.innerHTML = '<i>Processing...</i>';
        }
    });
    
    // Make AJAX request to control worker
    let body = `action=control&worker=${worker}&command=${action}`;
    if (sudoPassword) {
        body += `&sudo_password=${encodeURIComponent(sudoPassword)}`;
    }
    
    fetch('php_workers_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        credentials: 'same-origin',
        body: body
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Refresh after 3 seconds
            setTimeout(() => {
                loadBottomPanel('php_workers');
            }, 3000);
        } else {
            // Check if it's a systemd service that needs sudo
            if (data.message && data.message.includes('sudo systemctl')) {
                // Ask for sudo password
                const password = prompt('Enter your sudo password to stop systemd service:');
                if (!password) {
                    workerButtons.forEach(btn => btn.disabled = false);
                    return;
                }
                
                // Just pass the sudo password - SSH will use key authentication
                workerButtons.forEach(btn => btn.disabled = false);
                window.controlWorkerService(worker, action, password);
                return;
            }
            
            showNotification('Error: ' + (data.message || 'Operation failed'), 'error');
            if (data.debug) {
                console.log('Debug info:', data.debug);
            }
            workerButtons.forEach(btn => btn.disabled = false);
        }
    })
    .catch(error => {
        showNotification('Error: ' + error, 'error');
        workerButtons.forEach(btn => btn.disabled = false);
    });
}

// Event listeners are defined in the DOMContentLoaded event above

// Add.New.Org button click handler (button is only visible in Organisations List tab)
var addNewOrgBtn = document.getElementById('add_new_org');
if (addNewOrgBtn) {
    addNewOrgBtn.onclick = function() { openAddNewOrgModal(); };
}

function toggleCard(event, orgId) {
    if (event.target.tagName === "A" || event.target.closest(".modify-link")) return;
    // Collapse all cards except the clicked one
    document.querySelectorAll('.org-card.expanded').forEach(function(el){
        if(el.id !== "org-card-"+orgId) {
            el.classList.remove('expanded');
        }
    });
    var card = document.getElementById('org-card-'+orgId);
    if (card) card.classList.toggle('expanded');
}

// Delete organisation functions
function deleteOrganisation(orgId, orgName) {
    document.getElementById('deleteOrgName').textContent = orgName;
    document.getElementById('deleteOrgModal').setAttribute('data-org-id', orgId);
    document.getElementById('deleteOrgModal').style.display = 'block';
    document.getElementById('deletePassword').value = '';
    document.getElementById('passwordError').style.display = 'none';
    document.getElementById('deletePassword').focus();
}

function closeDeleteModal() {
    document.getElementById('deleteOrgModal').style.display = 'none';
}

function confirmDelete() {
    const password = document.getElementById('deletePassword').value;
    const orgId = document.getElementById('deleteOrgModal').getAttribute('data-org-id');
    
    if (!password) {
        document.getElementById('passwordError').textContent = 'Please enter your password to confirm deletion.';
        document.getElementById('passwordError').style.display = 'block';
        return;
    }

    const btn = document.getElementById('confirmDeleteBtn');
    const originalText = btn.textContent;
    btn.textContent = 'Deleting...';
    btn.disabled = true;
    
    fetch('delete_organisation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'org_id=' + encodeURIComponent(orgId) + '&password=' + encodeURIComponent(password)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteModal();
            loadBottomPanel('list_all_org');
            showNotification('Organisation deleted successfully!', 'success');
        } else {
            document.getElementById('passwordError').textContent = data.error || 'Failed to delete organisation.';
            document.getElementById('passwordError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('passwordError').textContent = 'An error occurred while deleting the organisation.';
        document.getElementById('passwordError').style.display = 'block';
    })
    .finally(() => {
        btn.textContent = originalText;
        btn.disabled = false;
    });
}

// Unused function getOrgIdFromName removed

// Modify organisation functions
function modifyOrganisation(orgId, orgName) {
    // Store the current org ID globally for delete function access
    window.currentOrgId = orgId;
    window.currentOrgName = orgName;
    
    document.getElementById('modifyOrgName').textContent = orgName;
    document.getElementById('modifyOrgModal').style.display = 'block';
    document.getElementById('modifyOrgError').style.display = 'none';
    
    // Load organisation data
    loadOrganisationData(orgId);
}

function closeModifyModal() {
    document.getElementById('modifyOrgModal').style.display = 'none';
}

// Function to delete organisation from within the modify modal
function deleteOrganisationFromModal() {
    // Get the organisation ID and name from the global variables
    const orgId = window.currentOrgId;
    const orgName = window.currentOrgName;
    
    if (orgId && orgName) {
        // Close the modify modal first
        closeModifyModal();
        
        // Call the existing delete function
        deleteOrganisation(orgId, orgName);
    } else {
        alert('Error: Unable to get organisation information for deletion.');
    }
}

function loadOrganisationData(orgId) {
    fetch('get_organisation_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'org_id=' + encodeURIComponent(orgId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const org = data.organisation;
            
            // Populate form fields
            document.getElementById('alias_name').value = org.alias_name || '';
            document.getElementById('oficial_company_name').value = org.oficial_company_name || '';
            // booking_phone_nr is now managed per working point, not per organisation
            document.getElementById('contact_name').value = org.contact_name || '';
            document.getElementById('position').value = org.position || '';
            document.getElementById('email_address').value = org.email_address || '';
            document.getElementById('www_address').value = org.www_address || '';
            document.getElementById('company_head_office_address').value = org.company_head_office_address || '';
            document.getElementById('company_phone_nr').value = org.company_phone_nr || '';
            document.getElementById('country').value = org.country || '';
            document.getElementById('owner_name').value = org.owner_name || '';
            document.getElementById('owner_phone_nr').value = org.owner_phone_nr || '';
            document.getElementById('user').value = org.user || '';
            document.getElementById('pasword').value = org.pasword || '';
            
            
            // Store original values for comparison
            const form = document.getElementById('modifyOrgForm');
            const formFields = form.querySelectorAll('input');
            formFields.forEach(field => {
                field.setAttribute('data-original-value', field.value);
            });
            
            // Store org ID for form submission
            document.getElementById('modifyOrgForm').setAttribute('data-org-id', orgId);
        } else {
            console.error('Failed to load organisation data:', data.message);
            document.getElementById('modifyOrgError').textContent = data.message || 'Failed to load organisation data.';
            document.getElementById('modifyOrgError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error loading organisation data:', error);
        document.getElementById('modifyOrgError').textContent = 'An error occurred while loading organisation data.';
        document.getElementById('modifyOrgError').style.display = 'block';
    });
}

// Load organization list by default when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadBottomPanel('list_all_org');
    
    // Set up form submission event listeners after DOM is loaded
    const modifyForm = document.getElementById('modifyOrgForm');
    if (modifyForm) {
        modifyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const orgId = this.getAttribute('data-org-id');
            const formData = new FormData(this);
            formData.append('org_id', orgId);
            
            // Store original values for comparison
            const originalValues = {};
            const formFields = this.querySelectorAll('input');
            formFields.forEach(field => {
                originalValues[field.name] = field.getAttribute('data-original-value') || '';
            });
            
            // Disable submit button
            const submitBtn = document.getElementById('confirmModifyBtn');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Updating...';
            submitBtn.disabled = true;
            
            fetch('update_organisation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        throw new Error('Invalid JSON response from server');
                    }
                });
            })
            .then(data => {
                
                if (data.success) {
                    // Close modify modal
                    closeModifyModal();
                    
                    // Show success modal with updated fields
                    showSuccessModal(formData, originalValues);
                    
                    // Reload the organization list
                    loadBottomPanel('list_all_org');
                } else {
                    document.getElementById('modifyOrgError').textContent = data.message || 'Failed to update organisation.';
                    document.getElementById('modifyOrgError').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error updating organisation:', error);
                console.error('Error details:', error.message);
                document.getElementById('modifyOrgError').textContent = 'An error occurred while updating the organisation: ' + error.message;
                document.getElementById('modifyOrgError').style.display = 'block';
            })
            .finally(() => {
                // Re-enable submit button
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }
    
    // Set up working point form submission
    const modifyWpForm = document.getElementById('modifyWpForm');
    if (modifyWpForm) {
        modifyWpForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const wpId = this.getAttribute('data-wp-id');
            const formData = new FormData(this);
            formData.append('workpoint_id', wpId);
            
            // Store original values for comparison
            const originalValues = {};
            const formFields = this.querySelectorAll('input');
            formFields.forEach(field => {
                originalValues[field.name] = field.getAttribute('data-original-value') || '';
            });
            
            // Disable submit button
            const submitBtn = document.getElementById('confirmModifyWpBtn');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Updating...';
            submitBtn.disabled = true;
            
            fetch('update_working_point.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModifyWpModal();
                    showSuccessModal(formData, originalValues, 'Working Point');
                    loadBottomPanel('list_all_org');
                } else {
                    document.getElementById('modifyWpError').textContent = data.message || 'Failed to update working point.';
                    document.getElementById('modifyWpError').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error updating working point:', error);
                document.getElementById('modifyWpError').textContent = 'An error occurred while updating the working point.';
                document.getElementById('modifyWpError').style.display = 'block';
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }
    
    // Set up specialist form submission
    const modifySpecialistForm = document.getElementById('modifySpecialistForm');
    if (modifySpecialistForm) {
        modifySpecialistForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const specialistId = this.getAttribute('data-specialist-id');
            const formData = new FormData(this);
            formData.append('specialist_id', specialistId);
            
            // Store original values for comparison
            const originalValues = {};
            const formFields = this.querySelectorAll('input');
            formFields.forEach(field => {
                originalValues[field.name] = field.getAttribute('data-original-value') || '';
            });
            
            // Disable submit button
            const submitBtn = document.getElementById('confirmModifySpecialistBtn');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Updating...';
            submitBtn.disabled = true;
            
            fetch('update_specialist.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModifySpecialistModal();
                    showSuccessModal(formData, originalValues, 'Specialist');
                    loadBottomPanel('list_all_org');
                } else {
                    document.getElementById('modifySpecialistError').textContent = data.message || 'Failed to update specialist.';
                    document.getElementById('modifySpecialistError').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error updating specialist:', error);
                document.getElementById('modifySpecialistError').textContent = 'An error occurred while updating the specialist.';
                document.getElementById('modifySpecialistError').style.display = 'block';
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }
    
    // Add direct click handler for update button as backup
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'confirmModifyBtn') {
    
            const form = document.getElementById('modifyOrgForm');
            if (form) {
                // Trigger form submission
                const submitEvent = new Event('submit', {
                    bubbles: true,
                    cancelable: true
                });
                form.dispatchEvent(submitEvent);
            }
        }
    });
});

function showSuccessModal(formData, originalValues, entityType = 'Organisation') {
    
    
    const updatedFields = [];
    let fieldLabels = {};
    
    if (entityType === 'Working Point') {
        fieldLabels = {
            'name_of_the_place': 'Name of the Place',
            'address': 'Address',
            'lead_person_name': 'Lead Person Name',
            'lead_person_phone_nr': 'Lead Person Phone',
            'workplace_phone_nr': 'Workplace Phone',
            'email': 'Email',
            'user': 'Login',
            'password': 'Password'
        };
    } else if (entityType === 'Specialist') {
        fieldLabels = {
            'name': 'Name',
            'speciality': 'Speciality',
            'email': 'Email',
            'phone_nr': 'Phone',
            'user': 'Login',
            'password': 'Password',
            'working_points': 'Working Points'
        };
    } else {
        // Organization (default)
        fieldLabels = {
            'alias_name': 'Alias Name',
            'oficial_company_name': 'Company Name',
            // 'booking_phone_nr': 'Booking Phone', // Now managed per working point
            'contact_name': 'Contact Name',
            'position': 'Position',
            'email_address': 'Email',
            'www_address': 'Website',
            'company_head_office_address': 'Address',
            'company_phone_nr': 'Company Phone',
            'country': 'Country',
            'owner_name': 'Owner Name',
            'owner_phone_nr': 'Owner Phone',
            'user': 'Login',
            'pasword': 'Password',
            
        };
    }
    
    // Get the entity name for the header
    let entityName = '';
    if (entityType === 'Working Point') {
        for (let [key, value] of formData.entries()) {
            if (key === 'name_of_the_place') {
                entityName = value;
                break;
            }
        }
    } else if (entityType === 'Specialist') {
        for (let [key, value] of formData.entries()) {
            if (key === 'name') {
                entityName = value;
                break;
            }
        }
    } else {
        // Organization
        for (let [key, value] of formData.entries()) {
            if (key === 'oficial_company_name') {
                entityName = value;
                break;
            }
        }
    }
    
    // Compare current values with original values
    for (let [key, value] of formData.entries()) {
        if (key !== 'org_id' && key !== 'wp_id' && key !== 'specialist_id') {
            const originalValue = originalValues[key] || '';
            const currentValue = value || '';
            
            // Trim both values for comparison
            if (currentValue.trim() !== originalValue.trim()) {
    
                updatedFields.push({
                    name: fieldLabels[key] || key,
                    value: currentValue
                });
            }
        }
    }
    

    
    // Update the modal header with entity name
    const modalHeader = document.querySelector('.success-modal-body h4');
    if (modalHeader) {
        modalHeader.textContent = `${entityType} Updated Successfully!`;
    }
    
    // Update the "Updated Fields" text with entity name
    const updatedFieldsTitle = document.querySelector('.updated-fields h5');
    if (updatedFieldsTitle) {
        updatedFieldsTitle.textContent = `Updated Fields for: ${entityName}`;
    }
    
    // Populate the success modal
    const fieldsList = document.getElementById('updatedFieldsList');
    fieldsList.innerHTML = '';
    
    if (updatedFields.length > 0) {
        updatedFields.forEach(field => {
            const fieldItem = document.createElement('div');
            fieldItem.className = 'field-item';
            fieldItem.innerHTML = `
                <span class="field-name">
                    <span class="update-icon">✓</span>
                    ${field.name}
                </span>
                <span class="field-value">${field.value}</span>
            `;
            fieldsList.appendChild(fieldItem);
        });
    } else {
        fieldsList.innerHTML = '<p style="text-align: center; color: #6c757d; font-style: italic;">No fields were changed.</p>';
    }
    
    // Show the success modal
    document.getElementById('successModal').style.display = 'block';
}

function closeSuccessModal() {
    document.getElementById('successModal').style.display = 'none';
}

// Keyboard support for modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
        closeDeleteWpModal();
        closeModifyModal();
        closeModifyWpModal();
        closeModifySpecialistModal();
        closeAddNewWpModal();
        closeSuccessModal();
    }
});

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('delete-modal-overlay')) {
        closeDeleteModal();
        closeDeleteWpModal();
    }
    if (e.target.classList.contains('modify-modal-overlay')) {
        // Check which modify modal is currently open
        const modifyOrgModal = document.getElementById('modifyOrgModal');
        const modifyWpModal = document.getElementById('modifyWpModal');
        const modifySpecialistModal = document.getElementById('modifySpecialistModal');
        const telnyxPhoneModal = document.getElementById('telnyxPhoneModal');
        
        if (modifyOrgModal && modifyOrgModal.style.display === 'block') {
            closeModifyModal();
        } else if (modifyWpModal && modifyWpModal.style.display === 'block') {
            closeModifyWpModal();
        } else if (modifySpecialistModal && modifySpecialistModal.style.display === 'block') {
            closeModifySpecialistModal();
        } else if (telnyxPhoneModal && telnyxPhoneModal.style.display === 'block') {
            closeTelnyxPhoneModal();
        } else if (document.getElementById('addNewWpModal') && document.getElementById('addNewWpModal').style.display === 'block') {
            closeAddNewWpModal();
        }
    }
    if (e.target.classList.contains('add-wp-modal-overlay')) {
        closeAddWpModal();
    }
    if (e.target.classList.contains('success-modal-overlay')) {
        closeSuccessModal();
    }
});

// Load organization list by default when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadBottomPanel('list_all_org');
});

// Working Point Dropdown Toggle Function
function toggleWpDropdown(wpId, event) {
    // Prevent toggle if the click is on the name/address link or Telnyx button
    if (event.target.closest('span[onclick*="modifyWorkingPoint"]') || event.target.closest('button[onclick*="editTelnyxPhone"]')) {
        return;
    }
    var dropdown = document.getElementById('wp-specialists-dropdown-' + wpId);
    if (dropdown.style.display === 'none' || dropdown.style.display === '') {
        dropdown.style.display = 'block';
    } else {
        dropdown.style.display = 'none';
    }
}

// Working Point modify functions
function modifyWorkingPoint(wpId, wpName) {
    document.getElementById('modifyWpName').textContent = wpName;
    document.getElementById('modifyWpModal').style.display = 'block';
    document.getElementById('modifyWpError').style.display = 'none';
    
    // Load working point data
    loadWorkingPointData(wpId);
}

// Add Working Point functions
function openAddWorkingPointModal(organisationId) {
    document.getElementById('addWpOrganisationId').value = organisationId;
    document.getElementById('addNewWpModal').style.display = 'block';
    document.getElementById('addWpError').style.display = 'none';
    
    // Clear form fields
    document.getElementById('add_wp_name_of_the_place').value = '';
    document.getElementById('add_wp_address').value = '';
    document.getElementById('add_wp_lead_person_name').value = '';
    document.getElementById('add_wp_lead_person_phone_nr').value = '';
    document.getElementById('add_wp_workplace_phone_nr').value = '';
    document.getElementById('add_wp_booking_phone_nr').value = '';
    document.getElementById('add_wp_email').value = '';
    document.getElementById('add_wp_we_handling').value = '';
    document.getElementById('add_wp_specialist_relevance').value = '';
    document.getElementById('add_wp_user').value = '';
    document.getElementById('add_wp_password').value = '';
}

function closeAddNewWpModal() {
    document.getElementById('addNewWpModal').style.display = 'none';
}

function submitAddWorkingPoint() {
    const form = document.getElementById('addWpForm');
    const formData = new FormData(form);
    
    // Validate language field before submitting
    const languageField = formData.get('language');
    if (languageField && !languageField.match(/^[A-Z]{2}$/)) {
        document.getElementById('addWpError').textContent = 'Language must be a 2-letter code (e.g., EN, RO, LT)';
        document.getElementById('addWpError').style.display = 'block';
        return;
    }
    
    // Validate country field
    const countryField = document.getElementById('add_wp_country').value;
    if (!countryField || countryField.trim() === '') {
        document.getElementById('addWpError').textContent = 'Country is required';
        document.getElementById('addWpError').style.display = 'block';
        return;
    }
    
    // Clear any previous errors
    document.getElementById('addWpError').style.display = 'none';
    
    // Disable submit button
    const submitBtn = document.getElementById('confirmAddNewWpBtn');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Adding...';
    submitBtn.disabled = true;
    
    fetch('process_add_workpoint.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddNewWpModal();
            loadBottomPanel('list_all_org');
            
            // Show success message
            showSuccessMessage('Working point added successfully');
        } else {
            document.getElementById('addWpError').textContent = data.message || 'Failed to add working point';
            document.getElementById('addWpError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error adding working point:', error);
        document.getElementById('addWpError').textContent = 'An error occurred while adding the working point';
        document.getElementById('addWpError').style.display = 'block';
    })
    .finally(() => {
        // Re-enable submit button
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

// Telnyx Phone Number Edit Functions
function editTelnyxPhone(wpId, currentPhone) {
    document.getElementById('telnyxWpId').value = wpId;
    document.getElementById('telnyxPhoneInput').value = currentPhone || '';
    document.getElementById('telnyxPhoneModal').style.display = 'block';
    document.getElementById('telnyxPhoneError').style.display = 'none';
    
    // Load working point data to populate new fields
    fetch('get_working_point_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'wp_id=' + encodeURIComponent(wpId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.workpoint) {
            const wp = data.workpoint;
            document.getElementById('telnyxSmsInput').value = wp.booking_sms_number || '';
            document.getElementById('telnyxWeHandling').value = wp.we_handling || '';
            document.getElementById('telnyxSpecialistRelevance').value = wp.specialist_relevance || '';
        }
    })
    .catch(error => {
        console.error('Error loading working point data for Telnyx modal:', error);
    });
    
    // Focus on input field
    setTimeout(() => {
        document.getElementById('telnyxPhoneInput').focus();
        document.getElementById('telnyxPhoneInput').select();
    }, 100);
    
    // Add keyboard handling
    document.addEventListener('keydown', handleTelnyxPhoneKeydown);
}

function handleTelnyxPhoneKeydown(e) {
    if (document.getElementById('telnyxPhoneModal').style.display === 'block') {
        if (e.key === 'Escape') {
            closeTelnyxPhoneModal();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            updateTelnyxPhone();
        }
    }
}

function closeTelnyxPhoneModal() {
    document.getElementById('telnyxPhoneModal').style.display = 'none';
    
    // Remove keyboard event listener
    document.removeEventListener('keydown', handleTelnyxPhoneKeydown);
}

function updateTelnyxPhone() {
    const wpId = document.getElementById('telnyxWpId').value;
    const newPhone = document.getElementById('telnyxPhoneInput').value.trim();
    
    if (!newPhone) {
        document.getElementById('telnyxPhoneError').textContent = 'Phone number is required';
        document.getElementById('telnyxPhoneError').style.display = 'block';
        return;
    }
    
    // Disable submit button
    const submitBtn = document.getElementById('confirmTelnyxPhoneBtn');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Updating...';
    submitBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('workpoint_id', wpId);
    formData.append('booking_phone_nr', newPhone);
    formData.append('booking_sms_number', document.getElementById('telnyxSmsInput').value.trim());
    formData.append('we_handling', document.getElementById('telnyxWeHandling').value);
    formData.append('specialist_relevance', document.getElementById('telnyxSpecialistRelevance').value);
    formData.append('action', 'update_booking_phone');
    
    fetch('update_working_point.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeTelnyxPhoneModal();
            loadBottomPanel('list_all_org');
            
            // Show success message
            showSuccessMessage('Telnyx phone number updated successfully');
        } else {
            document.getElementById('telnyxPhoneError').textContent = data.message || 'Failed to update phone number';
            document.getElementById('telnyxPhoneError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error updating Telnyx phone:', error);
        document.getElementById('telnyxPhoneError').textContent = 'An error occurred while updating the phone number';
        document.getElementById('telnyxPhoneError').style.display = 'block';
    })
    .finally(() => {
        // Re-enable submit button
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

// Droid Config Functions
function openDroidConfigModal(wpId, wpName) {
    document.getElementById('droidConfigWpName').textContent = wpName;
    document.getElementById('droidConfigWpId').value = wpId;
    document.getElementById('droidConfigModal').style.display = 'block';
    document.getElementById('droidConfigError').style.display = 'none';

    // Load current droid configuration
    loadDroidConfig(wpId);
}

function closeDroidConfigModal() {
    document.getElementById('droidConfigModal').style.display = 'none';
}

function loadDroidConfig(wpId) {
    fetch('get_droid_config.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'wp_id=' + encodeURIComponent(wpId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Load SMS config
            if (data.sms) {
                document.getElementById('droid_sms_active').value = data.sms.active || '0';
                document.getElementById('droid_sms_phone_nr').value = data.sms.sms_phone_nr || '';
                document.getElementById('droid_sms_link').value = data.sms.droid_link || '';
            }

            // Load WhatsApp config
            if (data.whatsapp) {
                document.getElementById('droid_whatsapp_active').value = data.whatsapp.active || '0';
                document.getElementById('droid_whatsapp_phone_nr').value = data.whatsapp.whatsapp_phone_nr || '';
                document.getElementById('droid_whatsapp_link').value = data.whatsapp.droid_link || '';
            }
        }
    })
    .catch(error => {
        console.error('Error loading droid config:', error);
    });
}

function updateDroidConfig() {
    const wpId = document.getElementById('droidConfigWpId').value;
    const submitBtn = document.getElementById('confirmDroidConfigBtn');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Saving...';
    submitBtn.disabled = true;

    const formData = new FormData();
    formData.append('wp_id', wpId);

    // SMS config
    formData.append('sms_active', document.getElementById('droid_sms_active').value);
    formData.append('sms_phone_nr', document.getElementById('droid_sms_phone_nr').value);
    formData.append('sms_droid_link', document.getElementById('droid_sms_link').value);

    // WhatsApp config
    formData.append('whatsapp_active', document.getElementById('droid_whatsapp_active').value);
    formData.append('whatsapp_phone_nr', document.getElementById('droid_whatsapp_phone_nr').value);
    formData.append('whatsapp_droid_link', document.getElementById('droid_whatsapp_link').value);

    fetch('update_droid_config.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage('MacroDroid configuration updated successfully');
            closeDroidConfigModal();
        } else {
            document.getElementById('droidConfigError').textContent = data.message || 'Failed to update configuration';
            document.getElementById('droidConfigError').style.display = 'block';
        }
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    })
    .catch(error => {
        console.error('Error updating droid config:', error);
        document.getElementById('droidConfigError').textContent = 'Error occurred while updating configuration';
        document.getElementById('droidConfigError').style.display = 'block';
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

function showSuccessMessage(message) {
    // Create a temporary success message div
    const successDiv = document.createElement('div');
    successDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #28a745;
        color: white;
        padding: 12px 20px;
        border-radius: 4px;
        z-index: 10000;
        font-weight: bold;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    `;
    successDiv.textContent = message;
    document.body.appendChild(successDiv);
    
    // Remove after 3 seconds
    setTimeout(() => {
        if (successDiv.parentNode) {
            successDiv.parentNode.removeChild(successDiv);
        }
    }, 3000);
}

function closeModifyWpModal() {
    document.getElementById('modifyWpModal').style.display = 'none';
}

function loadWorkingPointData(wpId) {
    fetch('get_working_point_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'wp_id=' + encodeURIComponent(wpId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.workpoint) {
            const wp = data.workpoint;
            // Populate form fields
            document.getElementById('wp_name_of_the_place').value = wp.name_of_the_place || '';
            document.getElementById('wp_description_of_the_place').value = wp.description_of_the_place || '';
            document.getElementById('wp_address').value = wp.address || '';
            document.getElementById('wp_landmark').value = wp.landmark || '';
            document.getElementById('wp_directions').value = wp.directions || '';
            document.getElementById('wp_lead_person_name').value = wp.lead_person_name || '';
            document.getElementById('wp_lead_person_phone_nr').value = wp.lead_person_phone_nr || '';
            document.getElementById('wp_workplace_phone_nr').value = wp.workplace_phone_nr || '';
            document.getElementById('wp_booking_phone_nr').value = wp.booking_phone_nr || '';
            document.getElementById('wp_booking_sms_number').value = wp.booking_sms_number || '';
            document.getElementById('wp_email').value = wp.email || '';
            if (wp.country) {
                // Set both display and hidden fields
                document.getElementById('wp_country').value = wp.country;
                setCountryValue('wp_country_display', wp.country);
            }
            document.getElementById('wp_language').value = (wp.language || '').toUpperCase();
            document.getElementById('wp_we_handling').value = wp.we_handling || '';
            document.getElementById('wp_specialist_relevance').value = wp.specialist_relevance || '';
            document.getElementById('wp_user').value = wp.user || '';
            document.getElementById('wp_password').value = wp.password || '';
            // Store original values for comparison
            const form = document.getElementById('modifyWpForm');
            const formFields = form.querySelectorAll('input');
            formFields.forEach(field => {
                field.setAttribute('data-original-value', field.value);
            });
            // Store wp ID for form submission
            document.getElementById('modifyWpForm').setAttribute('data-wp-id', wpId);
        } else {
            document.getElementById('modifyWpError').textContent = (data && data.message) ? data.message : 'Failed to load working point data.';
            document.getElementById('modifyWpError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error loading working point data:', error);
        document.getElementById('modifyWpError').textContent = 'An error occurred while loading working point data.';
        document.getElementById('modifyWpError').style.display = 'block';
    });
}

// Working Point delete functions
function deleteWorkingPointFromModal() {
    // Get the working point ID and name from the form
    const wpId = document.getElementById('modifyWpForm').getAttribute('data-wp-id');
    const wpName = document.getElementById('modifyWpName').textContent;
    
    if (!wpId) {
        alert('Error: Unable to get working point information for deletion.');
        return;
    }
    
    // Store the current wp ID and name globally for delete function access
    window.currentWpId = wpId;
    window.currentWpName = wpName;
    
    // Close the modify modal first
    closeModifyWpModal();
    
    // Show the delete confirmation modal
    document.getElementById('deleteWpName').textContent = wpName;
    document.getElementById('deleteWpModal').setAttribute('data-wp-id', wpId);
    document.getElementById('deleteWpModal').style.display = 'block';
    document.getElementById('deleteWpPassword').value = '';
    document.getElementById('deleteWpPasswordError').style.display = 'none';
}

function closeDeleteWpModal() {
    document.getElementById('deleteWpModal').style.display = 'none';
}

function confirmDeleteWp() {
    const password = document.getElementById('deleteWpPassword').value;
    const wpId = document.getElementById('deleteWpModal').getAttribute('data-wp-id');
    
    if (!password) {
        document.getElementById('deleteWpPasswordError').textContent = 'Please enter your password to confirm deletion.';
        document.getElementById('deleteWpPasswordError').style.display = 'block';
        return;
    }

    const btn = document.getElementById('confirmDeleteWpBtn');
    const originalText = btn.textContent;
    btn.textContent = 'Deleting...';
    btn.disabled = true;
    
    fetch('delete_workpoint.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'workpoint_id=' + encodeURIComponent(wpId) + '&password=' + encodeURIComponent(password)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteWpModal();
            loadBottomPanel('list_all_org');
            showSuccessMessage('Working point deleted successfully');
        } else {
            document.getElementById('deleteWpPasswordError').textContent = data.message || 'Failed to delete working point.';
            document.getElementById('deleteWpPasswordError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('deleteWpPasswordError').textContent = 'An error occurred while deleting the working point.';
        document.getElementById('deleteWpPasswordError').style.display = 'block';
    })
    .finally(() => {
        btn.textContent = originalText;
        btn.disabled = false;
    });
}

// Specialist modify functions
function modifySpecialist(specialistId, specialistName) {
    document.getElementById('modifySpecialistName').textContent = specialistName;
    document.getElementById('modifySpecialistModal').style.display = 'block';
    document.getElementById('modifySpecialistError').style.display = 'none';
    
    // Load specialist data
    loadSpecialistData(specialistId);
}

function closeModifySpecialistModal() {
    document.getElementById('modifySpecialistModal').style.display = 'none';
    
    // Remove any orphaned specialist note
    const orphanedNote = document.querySelector('#modifySpecialistModal .orphaned-specialist-note');
    if (orphanedNote) {
        orphanedNote.remove();
    }
}

function loadSpecialistData(specialistId) {
    fetch('get_specialist_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'specialist_id=' + encodeURIComponent(specialistId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const sp = data.specialist;
            
            // Populate form fields
            document.getElementById('sp_name').value = sp.name || '';
            document.getElementById('sp_speciality').value = sp.speciality || '';
            document.getElementById('sp_email').value = sp.email || '';
            document.getElementById('sp_phone_nr').value = sp.phone_nr || '';
            document.getElementById('sp_user').value = sp.user || '';
            document.getElementById('sp_password').value = sp.password || '';
            
            // Load working points assignments
            loadWorkingPointsAssignments(specialistId);
            
            // Store original values for comparison
            const form = document.getElementById('modifySpecialistForm');
            const formFields = form.querySelectorAll('input');
            formFields.forEach(field => {
                field.setAttribute('data-original-value', field.value);
            });
            
            // Store specialist ID for form submission
            document.getElementById('modifySpecialistForm').setAttribute('data-specialist-id', specialistId);
        } else {
            document.getElementById('modifySpecialistError').textContent = data.message || 'Failed to load specialist data.';
            document.getElementById('modifySpecialistError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error loading specialist data:', error);
        document.getElementById('modifySpecialistError').textContent = 'An error occurred while loading specialist data.';
        document.getElementById('modifySpecialistError').style.display = 'block';
    });
}

function loadWorkingPointsAssignments(specialistId) {
            fetch('get_specialist_working_points.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'specialist_id=' + encodeURIComponent(specialistId)
        })
        .then(response => response.json())
        .then(data => {
        
        const container = document.getElementById('assignedWorkingPoints');
        container.innerHTML = '';
        
        if (data.success && data.assignments && data.assignments.length > 0) {
            // Store assignments globally for access by createScheduleTable
            window.workingPointAssignments = data.assignments;
            
            // Create table structure
            const table = document.createElement('table');
            table.className = 'assigned-working-points-table';
            table.style.width = '100%';
            table.style.borderCollapse = 'collapse';
            
            data.assignments.forEach(assignment => {
                const row = document.createElement('tr');
                row.className = 'working-point-row';
                row.setAttribute('data-wp-id', assignment.wp_id);
                
                const cell = document.createElement('td');
                cell.className = 'working-point-cell';
                cell.style.padding = '8px';
                cell.style.border = '1px solid #ddd';
                cell.style.verticalAlign = 'top';
                
                cell.innerHTML = `
                    <div class="working-point-schedule" id="schedule-${assignment.wp_id}">
                        <div class="schedule-loading">Loading schedule...</div>
                    </div>
                `;
                
                row.appendChild(cell);
                table.appendChild(row);
            });
            
            container.appendChild(table);
            
            // Load detailed schedule for each working point
            data.assignments.forEach(assignment => {
                loadDetailedSchedule(assignment.wp_id, specialistId);
            });
        } else {
            window.workingPointAssignments = [];
            container.innerHTML = '<div class="no-working-points">No working points assigned yet.</div>';
        }
    })
    .catch(error => {
        console.error('Error loading working points assignments:', error);
        const container = document.getElementById('assignedWorkingPoints');
        container.innerHTML = '<div class="no-working-points">Error loading assignments.</div>';
    });
}

function loadDetailedSchedule(workingPointId, specialistId) {
    fetch('get_working_program_details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'specialist_id=' + encodeURIComponent(specialistId) + '&working_point_id=' + encodeURIComponent(workingPointId)
    })
    .then(response => response.json())
    .then(data => {
        
        const container = document.getElementById(`schedule-${workingPointId}`);
        
        if (data.success && data.schedule) {
            container.innerHTML = createScheduleTable(data.schedule, workingPointId, specialistId);
        } else {
            container.innerHTML = '<div class="schedule-error">Error loading schedule.</div>';
        }
    })
    .catch(error => {
        console.error('Error loading detailed schedule:', error);
        const container = document.getElementById(`schedule-${workingPointId}`);
        container.innerHTML = '<div class="schedule-error">Error loading schedule.</div>';
    });
}

function createScheduleTable(schedule, workingPointId, specialistId) {
    // Get working point details for the header
    let wpName = '';
    let wpAddress = '';
    
    // Find the working point details from the parent container
    const wpContainer = document.querySelector(`[data-wp-id="${workingPointId}"]`);
    if (wpContainer) {
        // Try to get from the assignment data that was passed
        const assignment = window.workingPointAssignments ? window.workingPointAssignments.find(a => a.wp_id == workingPointId) : null;
        if (assignment) {
            wpName = assignment.wp_name;
            wpAddress = assignment.wp_address;
        }
    }
    
    let tableHTML = `
        <div class="schedule-table-container">
            <div class="working-point-schedule-header">
                <div class="working-point-schedule-info" style="counter-reset: working-point-counter;">
                    <div class="working-point-schedule-name">${wpName} <span class="working-point-schedule-address">(${wpAddress})</span></div>
                </div>
                <div class="working-point-schedule-actions">
                    <button type="button" class="btn-edit-schedule" onclick="showComprehensiveScheduleEditor(${workingPointId}, ${specialistId}, '${wpName}')">Edit Schedule</button>
                    <button type="button" class="btn-remove-wp" onclick="removeWorkingPointAssignment(${workingPointId}, ${specialistId})">Remove</button>
                </div>
            </div>
            <table class="schedule-table-compact">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th colspan="2">Shift 1</th>
                        <th colspan="2">Shift 2</th>
                        <th colspan="2">Shift 3</th>
                    </tr>
                    <tr>
                        <th></th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Start</th>
                        <th>End</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    schedule.forEach(day => {
        const formatTime = (time) => {
            if (!time || time === '00:00' || time === '00:00:00') return '--:--';
            // Ensure we have hh:mm format
            const timeStr = time.toString();
            if (timeStr.length >= 5) {
                return timeStr.substring(0, 5); // Return hh:mm
            }
            return timeStr;
        };
        
        tableHTML += `
            <tr class="schedule-row" data-day="${day.day_of_week}" data-wp-id="${workingPointId}" data-specialist-id="${specialistId}">
                <td class="day-cell">${day.day_of_week}</td>
                <td class="shift-cell shift1-start" onclick="editShift('${day.day_of_week}', ${workingPointId}, ${specialistId}, 1, '${day.shift1_start}', '${day.shift1_end}')">
                    ${formatTime(day.shift1_start)}
                </td>
                <td class="shift-cell shift1-end" onclick="editShift('${day.day_of_week}', ${workingPointId}, ${specialistId}, 1, '${day.shift1_start}', '${day.shift1_end}')">
                    ${formatTime(day.shift1_end)}
                </td>
                <td class="shift-cell shift2-start" onclick="editShift('${day.day_of_week}', ${workingPointId}, ${specialistId}, 2, '${day.shift2_start}', '${day.shift2_end}')">
                    ${formatTime(day.shift2_start)}
                </td>
                <td class="shift-cell shift2-end" onclick="editShift('${day.day_of_week}', ${workingPointId}, ${specialistId}, 2, '${day.shift2_start}', '${day.shift2_end}')">
                    ${formatTime(day.shift2_end)}
                </td>
                <td class="shift-cell shift3-start" onclick="editShift('${day.day_of_week}', ${workingPointId}, ${specialistId}, 3, '${day.shift3_start}', '${day.shift3_end}')">
                    ${formatTime(day.shift3_start)}
                </td>
                <td class="shift-cell shift3-end" onclick="editShift('${day.day_of_week}', ${workingPointId}, ${specialistId}, 3, '${day.shift3_start}', '${day.shift3_end}')">
                    ${formatTime(day.shift3_end)}
                </td>
            </tr>
        `;
    });
    
    tableHTML += `
                </tbody>
            </table>
        </div>
    `;
    
    return tableHTML;
}

function showAddWorkingPointModal() {
    const specialistId = document.getElementById('modifySpecialistForm').getAttribute('data-specialist-id');
    if (!specialistId) {
        alert('Please save the specialist first before adding working points.');
        return;
    }
    
    document.getElementById('addWpModal').style.display = 'flex';
    document.getElementById('addWpError').style.display = 'none';
    
    // Reset form fields
    document.querySelectorAll('.day-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('shift1_start').value = '09:00';
    document.getElementById('shift1_end').value = '17:00';
    document.getElementById('shift2_start').value = '';
    document.getElementById('shift2_end').value = '';
    document.getElementById('shift3_start').value = '';
    document.getElementById('shift3_end').value = '';
    
    // Clear selected days display
    const displayDiv = document.getElementById('selected-days-display');
    if (displayDiv) {
        displayDiv.textContent = '';
    }
    
    // Add event listeners to day checkboxes for updating display
    document.querySelectorAll('.day-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedDaysDisplay);
    });
    
    // Load available working points
    loadAvailableWorkingPoints(specialistId);
}

function closeAddWpModal() {
    document.getElementById('addWpModal').style.display = 'none';
    
    // Clear orphaned specialist data if this was for an orphaned specialist
    const orphanedSpecialistId = document.getElementById('addWpModal').getAttribute('data-orphaned-specialist-id');
    if (orphanedSpecialistId) {
        document.getElementById('addWpModal').removeAttribute('data-orphaned-specialist-id');
        document.getElementById('addWpModal').removeAttribute('data-orphaned-specialist-name');
        
        // Remove orphaned specialist note
        const modalBody = document.querySelector('#addWpModal .add-wp-modal-body');
        if (modalBody) {
            const orphanedNote = modalBody.querySelector('.orphaned-specialist-note');
            if (orphanedNote) {
                orphanedNote.remove();
            }
        }
        
        // Reset modal title
        const modalTitle = document.querySelector('#addWpModal .add-wp-modal-header h3');
        if (modalTitle) {
            modalTitle.textContent = '🏢 Add Working Point Assignment';
        }
    }
}

function loadAvailableWorkingPoints(specialistId) {
    
    fetch('get_available_working_points.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'specialist_id=' + encodeURIComponent(specialistId)
    })
    .then(response => response.json())
            .then(data => {
        
        const container = document.getElementById('availableWorkingPoints');
        container.innerHTML = '';
        
        if (data.success && data.working_points && data.working_points.length > 0) {
            data.working_points.forEach(wp => {
                const wpItem = document.createElement('div');
                wpItem.className = 'available-wp-item';
                wpItem.setAttribute('data-wp-id', wp.unic_id);
                wpItem.onclick = function() {
                    // Remove selection from all items
                    document.querySelectorAll('.available-wp-item').forEach(item => {
                        item.classList.remove('selected');
                    });
                    // Add selection to clicked item
                    this.classList.add('selected');
                };
                wpItem.innerHTML = `
                    <div class="available-wp-info" style="flex: 1; display: flex; align-items: center;">
                        <span style="font-weight: 600; color: #333;">${wp.name_of_the_place}</span>
                        <span style="margin: 0 8px; color: #666;">--</span>
                        <span style="color: #666;">${wp.address}</span>
                    </div>
                    <div class="available-wp-check" style="color: #28a745; font-weight: bold;">✓</div>
                `;
                container.appendChild(wpItem);
            });
        } else {
            container.innerHTML = '<div class="no-working-points-available">No available working points found.</div>';
        }
    })
    .catch(error => {
        console.error('Error loading available working points:', error);
        const container = document.getElementById('availableWorkingPoints');
        container.innerHTML = '<div class="no-working-points-available">Error loading working points.</div>';
    });
}

// Function to update selected days display
function updateSelectedDaysDisplay() {
    const dayCheckboxes = document.querySelectorAll('.day-checkbox');
    const displayDiv = document.getElementById('selected-days-display');
    
    if (dayCheckboxes && displayDiv) {
        const selectedDays = [];
        dayCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                selectedDays.push(checkbox.value);
            }
        });
        
        if (selectedDays.length > 0) {
            displayDiv.textContent = `Selected: ${selectedDays.join(', ')}`;
        } else {
            displayDiv.textContent = '';
        }
    }
}

function addWorkingPointAssignment() {
    const selectedWp = document.querySelector('.available-wp-item.selected');
    if (!selectedWp) {
        document.getElementById('addWpError').textContent = 'Please select a working point.';
        document.getElementById('addWpError').style.display = 'block';
        return;
    }
    
    const wpId = selectedWp.getAttribute('data-wp-id');
    const dayCheckboxes = document.querySelectorAll('.day-checkbox');
    const selectedDays = [];
    dayCheckboxes.forEach(checkbox => {
        if (checkbox.checked) {
            selectedDays.push(checkbox.value);
        }
    });
    const dayOfWeek = selectedDays.join(',');
    const shift1Start = document.getElementById('shift1_start').value;
    const shift1End = document.getElementById('shift1_end').value;
    const shift2Start = document.getElementById('shift2_start').value;
    const shift2End = document.getElementById('shift2_end').value;
    const shift3Start = document.getElementById('shift3_start').value;
    const shift3End = document.getElementById('shift3_end').value;
    
    // Check if this is for an orphaned specialist
    const orphanedSpecialistId = document.getElementById('addWpModal').getAttribute('data-orphaned-specialist-id');
    let specialistId;
    
    if (orphanedSpecialistId) {
        // This is for an orphaned specialist
        specialistId = orphanedSpecialistId;
    } else {
        // This is for a regular specialist in the modify modal
        specialistId = document.getElementById('modifySpecialistForm').getAttribute('data-specialist-id');
    }
    
    if (selectedDays.length === 0) {
        document.getElementById('addWpError').textContent = 'Please select at least one day of the week.';
        document.getElementById('addWpError').style.display = 'block';
        return;
    }
    
    if (!shift1Start || !shift1End) {
        document.getElementById('addWpError').textContent = 'Please enter shift 1 start and end times.';
        document.getElementById('addWpError').style.display = 'block';
        return;
    }
    
    // Disable button
    const btn = document.getElementById('confirmAddWpBtn');
    const originalText = btn.textContent;
    btn.textContent = 'Adding...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('specialist_id', specialistId);
    formData.append('wp_id', wpId);
    // Append each day separately to create an array
    selectedDays.forEach(day => {
        formData.append('day_of_week[]', day);
    });
    formData.append('shift1_start', shift1Start);
    formData.append('shift1_end', shift1End);
    formData.append('shift2_start', shift2Start);
    formData.append('shift2_end', shift2End);
    formData.append('shift3_start', shift3Start);
    formData.append('shift3_end', shift3End);
    
    // Debug logging
    console.log('Selected days:', selectedDays);
    console.log('FormData entries:');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    fetch('add_specialist_working_point.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddWpModal();
            
            if (orphanedSpecialistId) {
                // This was an orphaned specialist assignment - refresh the organisation list
                if (typeof loadBottomPanel === 'function') {
                    loadBottomPanel('list_all_org');
                } else {
                    location.reload();
                }
            } else {
                // This was a regular specialist assignment - reload working points assignments
                loadWorkingPointsAssignments(specialistId);
            }
        } else {
            document.getElementById('addWpError').textContent = data.message || 'Failed to add working point assignment.';
            document.getElementById('addWpError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error adding working point assignment:', error);
        document.getElementById('addWpError').textContent = 'An error occurred while adding the assignment.';
        document.getElementById('addWpError').style.display = 'block';
    })
    .finally(() => {
        btn.textContent = originalText;
        btn.disabled = false;
    });
}

function removeWorkingPointAssignment(wpId, specialistId) {
    if (!confirm('Are you sure you want to remove this working point assignment?')) {
        return;
    }
    
    fetch('remove_specialist_working_point.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'wp_id=' + encodeURIComponent(wpId) + '&specialist_id=' + encodeURIComponent(specialistId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload working points assignments
            loadWorkingPointsAssignments(specialistId);
        } else {
            alert(data.message || 'Failed to remove working point assignment.');
        }
    })
    .catch(error => {
        console.error('Error removing working point assignment:', error);
        alert('An error occurred while removing the assignment.');
    });
}

// Duplicate functions removed - keeping first occurrences

function editShift(dayOfWeek, workingPointId, specialistId, shiftNumber, startTime, endTime) {
    // Show the shift editor modal
    document.getElementById('shiftEditorModal').style.display = 'flex';
    document.getElementById('shiftEditorError').style.display = 'none';
    
    // Populate the modal with current data
    document.getElementById('shiftEditorDay').textContent = dayOfWeek;
    document.getElementById('shiftEditorShift').textContent = `Shift ${shiftNumber}`;
    document.getElementById('shiftEditorStart').value = startTime;
    document.getElementById('shiftEditorEnd').value = endTime;
    
    // Store the context data
    document.getElementById('shiftEditorForm').setAttribute('data-day', dayOfWeek);
    document.getElementById('shiftEditorForm').setAttribute('data-wp-id', workingPointId);
    document.getElementById('shiftEditorForm').setAttribute('data-specialist-id', specialistId);
    document.getElementById('shiftEditorForm').setAttribute('data-shift-number', shiftNumber);
}

function closeShiftEditorModal() {
    document.getElementById('shiftEditorModal').style.display = 'none';
}

function saveShift() {
    const dayOfWeek = document.getElementById('shiftEditorForm').getAttribute('data-day');
    const workingPointId = document.getElementById('shiftEditorForm').getAttribute('data-wp-id');
    const specialistId = document.getElementById('shiftEditorForm').getAttribute('data-specialist-id');
    const shiftNumber = document.getElementById('shiftEditorForm').getAttribute('data-shift-number');
    const startTime = document.getElementById('shiftEditorStart').value;
    const endTime = document.getElementById('shiftEditorEnd').value;
    
    if (!startTime || !endTime) {
        document.getElementById('shiftEditorError').textContent = 'Please enter both start and end times.';
        document.getElementById('shiftEditorError').style.display = 'block';
        return;
    }
    
    // Disable button
    const btn = document.getElementById('saveShiftBtn');
    const originalText = btn.textContent;
    btn.textContent = 'Saving...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('specialist_id', specialistId);
    formData.append('working_point_id', workingPointId);
    formData.append('day_of_week', dayOfWeek);
    
    // Set the appropriate shift times based on shift number
    if (shiftNumber === '1') {
        formData.append('shift1_start', startTime);
        formData.append('shift1_end', endTime);
        formData.append('shift2_start', '00:00');
        formData.append('shift2_end', '00:00');
        formData.append('shift3_start', '00:00');
        formData.append('shift3_end', '00:00');
    } else if (shiftNumber === '2') {
        formData.append('shift1_start', '00:00');
        formData.append('shift1_end', '00:00');
        formData.append('shift2_start', startTime);
        formData.append('shift2_end', endTime);
        formData.append('shift3_start', '00:00');
        formData.append('shift3_end', '00:00');
    } else if (shiftNumber === '3') {
        formData.append('shift1_start', '00:00');
        formData.append('shift1_end', '00:00');
        formData.append('shift2_start', '00:00');
        formData.append('shift2_end', '00:00');
        formData.append('shift3_start', startTime);
        formData.append('shift3_end', endTime);
    }
    
    fetch('update_working_program.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeShiftEditorModal();
            // Reload the schedule for this working point
            loadDetailedSchedule(workingPointId, specialistId);
        } else {
            document.getElementById('shiftEditorError').textContent = data.message || 'Failed to update shift.';
            document.getElementById('shiftEditorError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error updating shift:', error);
        document.getElementById('shiftEditorError').textContent = 'An error occurred while updating the shift.';
        document.getElementById('shiftEditorError').style.display = 'block';
    })
    .finally(() => {
        btn.textContent = originalText;
        btn.disabled = false;
    });
}

function clearShift(dayOfWeek, workingPointId, specialistId, shiftNumber) {
    if (!confirm(`Are you sure you want to clear Shift ${shiftNumber} for ${dayOfWeek}?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('specialist_id', specialistId);
    formData.append('working_point_id', workingPointId);
    formData.append('day_of_week', dayOfWeek);
    
    // Set all shifts to 00:00
    formData.append('shift1_start', '00:00');
    formData.append('shift1_end', '00:00');
    formData.append('shift2_start', '00:00');
    formData.append('shift2_end', '00:00');
    formData.append('shift3_start', '00:00');
    formData.append('shift3_end', '00:00');
    
    fetch('update_working_program.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload the schedule for this working point
            loadDetailedSchedule(workingPointId, specialistId);
        } else {
            alert(data.message || 'Failed to clear shift.');
        }
    })
    .catch(error => {
        console.error('Error clearing shift:', error);
        alert('An error occurred while clearing the shift.');
    });
}

// Comprehensive Schedule Editor Modal
function showComprehensiveScheduleEditor(workingPointId, specialistId, workingPointName) {
    document.getElementById('comprehensiveScheduleWpId').value = workingPointId;
    document.getElementById('comprehensiveScheduleSpecialistId').value = specialistId;
    document.getElementById('comprehensiveScheduleWorkingPointName').textContent = workingPointName;
    
    // Load specialist name and display it
    loadSpecialistNameForDisplay(specialistId);
    
    // Load current schedule data
    loadComprehensiveScheduleData(workingPointId, specialistId);
    
    // Show modal
    document.getElementById('comprehensiveScheduleModal').style.display = 'flex';
}

function loadSpecialistNameForDisplay(specialistId) {
    fetch('get_specialist_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'specialist_id=' + encodeURIComponent(specialistId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.specialist) {
            const specialistName = data.specialist.name || 'Unknown Specialist';
            document.getElementById('comprehensiveScheduleSpecialistName').textContent = specialistName;
        } else {
            document.getElementById('comprehensiveScheduleSpecialistName').textContent = 'Unknown Specialist';
        }
    })
    .catch(error => {
        console.error('Error loading specialist name:', error);
        document.getElementById('comprehensiveScheduleSpecialistName').textContent = 'Unknown Specialist';
    });
}

function closeComprehensiveScheduleModal() {
    document.getElementById('comprehensiveScheduleModal').style.display = 'none';
    document.getElementById('comprehensiveScheduleError').style.display = 'none';
    
    // Clear specialist name
    document.getElementById('comprehensiveScheduleSpecialistName').textContent = '';
}



function loadComprehensiveScheduleData(workingPointId, specialistId) {
    fetch('get_working_program_details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'specialist_id=' + encodeURIComponent(specialistId) + '&working_point_id=' + encodeURIComponent(workingPointId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.schedule) {
            populateScheduleEditorTable(data.schedule);
        } else {
            document.getElementById('comprehensiveScheduleError').textContent = 'Error loading schedule data.';
            document.getElementById('comprehensiveScheduleError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error loading comprehensive schedule:', error);
        document.getElementById('comprehensiveScheduleError').textContent = 'Error loading schedule data.';
        document.getElementById('comprehensiveScheduleError').style.display = 'block';
    });
}

function populateScheduleEditorTable(schedule) {
    const tbody = document.getElementById('comprehensiveScheduleTableBody');
    tbody.innerHTML = '';
    
    schedule.forEach(day => {
        const formatTime = (time) => {
            if (!time || time === '00:00' || time === '00:00:00') return '--:--';
            // Ensure we have hh:mm format
            const timeStr = time.toString();
            if (timeStr.length >= 5) {
                return timeStr.substring(0, 5); // Return hh:mm
            }
            return timeStr;
        };
        
        const getInputValue = (time) => {
            if (!time || time === '00:00' || time === '00:00:00') return '';
            // Return hh:mm format for input value
            const timeStr = time.toString();
            if (timeStr.length >= 5) {
                return timeStr.substring(0, 5);
            }
            return timeStr;
        };
        
        const row = document.createElement('tr');
        const dayLower = day.day_of_week.toLowerCase();
        row.innerHTML = `
            <td class="day-name">${day.day_of_week}</td>
            <td>
                <input type="time" class="form-control shift1-start-time" 
                       name="shift1_start_${dayLower}"
                       value="${getInputValue(day.shift1_start)}" 
                       placeholder="--:--"
                       data-day="${day.day_of_week}" data-shift="1">
            </td>
            <td>
                <input type="time" class="form-control shift1-end-time" 
                       name="shift1_end_${dayLower}"
                       value="${getInputValue(day.shift1_end)}" 
                       placeholder="--:--"
                       data-day="${day.day_of_week}" data-shift="1">
            </td>
            <td>
                <button type="button" class="btn-clear-shift" onclick="clearShiftSchedule('${day.day_of_week}', 1)">Clear</button>
            </td>
            <td>
                <input type="time" class="form-control shift2-start-time" 
                       name="shift2_start_${dayLower}"
                       value="${getInputValue(day.shift2_start)}" 
                       placeholder="--:--"
                       data-day="${day.day_of_week}" data-shift="2">
            </td>
            <td>
                <input type="time" class="form-control shift2-end-time" 
                       name="shift2_end_${dayLower}"
                       value="${getInputValue(day.shift2_end)}" 
                       placeholder="--:--"
                       data-day="${day.day_of_week}" data-shift="2">
            </td>
            <td>
                <button type="button" class="btn-clear-shift" onclick="clearShiftSchedule('${day.day_of_week}', 2)">Clear</button>
            </td>
            <td>
                <input type="time" class="form-control shift3-start-time" 
                       name="shift3_start_${dayLower}"
                       value="${getInputValue(day.shift3_start)}" 
                       placeholder="--:--"
                       data-day="${day.day_of_week}" data-shift="3">
            </td>
            <td>
                <input type="time" class="form-control shift3-end-time" 
                       name="shift3_end_${dayLower}"
                       value="${getInputValue(day.shift3_end)}" 
                       placeholder="--:--"
                       data-day="${day.day_of_week}" data-shift="3">
            </td>
            <td>
                <button type="button" class="btn-clear-shift" onclick="clearShiftSchedule('${day.day_of_week}', 3)">Clear</button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function clearShiftSchedule(day, shift) {
    const dayLower = day.toLowerCase();
    const startInput = document.querySelector(`input[name="shift${shift}_start_${dayLower}"]`);
    const endInput = document.querySelector(`input[name="shift${shift}_end_${dayLower}"]`);
    if (startInput && endInput) {
        startInput.value = '';
        endInput.value = '';
        showNotification(`Shift ${shift} cleared for ${day}`, 'success');
    } else {
        // Fallback: find by data attributes
        const startInput = document.querySelector(`input[data-day="${day}"][data-shift="${shift}"].shift${shift}-start-time`);
        const endInput = document.querySelector(`input[data-day="${day}"][data-shift="${shift}"].shift${shift}-end-time`);
    if (startInput && endInput) {
        startInput.value = '';
        endInput.value = '';
        showNotification(`Shift ${shift} cleared for ${day}`, 'success');
        }
    }
}

function initializeComprehensiveScheduleTable() {
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const tableBody = document.getElementById('comprehensiveScheduleTableBody');
    if (!tableBody) {
        console.error('Comprehensive schedule table body not found');
        return;
    }
    
    tableBody.innerHTML = '';
    days.forEach(day => {
        const dayLower = day.toLowerCase();
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="day-name">${day}</td>
            <td>
                <input type="time" class="form-control shift1-start-time" 
                       name="shift1_start_${dayLower}" 
                       placeholder="--:--"
                       data-day="${day}" data-shift="1">
            </td>
            <td>
                <input type="time" class="form-control shift1-end-time" 
                       name="shift1_end_${dayLower}" 
                       placeholder="--:--"
                       data-day="${day}" data-shift="1">
            </td>
            <td>
                <button type="button" class="btn-clear-shift" onclick="clearShiftSchedule('${day}', 1)">Clear</button>
            </td>
            <td>
                <input type="time" class="form-control shift2-start-time" 
                       name="shift2_start_${dayLower}" 
                       placeholder="--:--"
                       data-day="${day}" data-shift="2">
            </td>
            <td>
                <input type="time" class="form-control shift2-end-time" 
                       name="shift2_end_${dayLower}" 
                       placeholder="--:--"
                       data-day="${day}" data-shift="2">
            </td>
            <td>
                <button type="button" class="btn-clear-shift" onclick="clearShiftSchedule('${day}', 2)">Clear</button>
            </td>
            <td>
                <input type="time" class="form-control shift3-start-time" 
                       name="shift3_start_${dayLower}" 
                       placeholder="--:--"
                       data-day="${day}" data-shift="3">
            </td>
            <td>
                <input type="time" class="form-control shift3-end-time" 
                       name="shift3_end_${dayLower}" 
                       placeholder="--:--"
                       data-day="${day}" data-shift="3">
            </td>
            <td>
                <button type="button" class="btn-clear-shift" onclick="clearShiftSchedule('${day}', 3)">Clear</button>
            </td>
        `;
        tableBody.appendChild(row);
    });
}

function clearDaySchedule(day) {
    // Clear all shifts for the selected day
    for (let shift = 1; shift <= 3; shift++) {
        clearShiftSchedule(day, shift);
    }
}

function applyBulkSchedule(type) {
    let days, shift1Start, shift1End, shift2Start, shift2End, shift3Start, shift3End;
    
    switch(type) {
        case 'mondayToFriday':
            shift1Start = document.getElementById('mondayToFridayShift1Start').value;
            shift1End = document.getElementById('mondayToFridayShift1End').value;
            shift2Start = document.getElementById('mondayToFridayShift2Start').value;
            shift2End = document.getElementById('mondayToFridayShift2End').value;
            shift3Start = document.getElementById('mondayToFridayShift3Start').value;
            shift3End = document.getElementById('mondayToFridayShift3End').value;
            days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
            break;
        case 'saturday':
            shift1Start = document.getElementById('saturdayShift1Start').value;
            shift1End = document.getElementById('saturdayShift1End').value;
            shift2Start = document.getElementById('saturdayShift2Start').value;
            shift2End = document.getElementById('saturdayShift2End').value;
            shift3Start = document.getElementById('saturdayShift3Start').value;
            shift3End = document.getElementById('saturdayShift3End').value;
            days = ['Saturday'];
            break;
        case 'sunday':
            shift1Start = document.getElementById('sundayShift1Start').value;
            shift1End = document.getElementById('sundayShift1End').value;
            shift2Start = document.getElementById('sundayShift2Start').value;
            shift2End = document.getElementById('sundayShift2End').value;
            shift3Start = document.getElementById('sundayShift3Start').value;
            shift3End = document.getElementById('sundayShift3End').value;
            days = ['Sunday'];
            break;
    }
    
    // Check if at least one shift has times entered
    const hasShift1 = shift1Start && shift1End;
    const hasShift2 = shift2Start && shift2End;
    const hasShift3 = shift3Start && shift3End;
    
    if (!hasShift1 && !hasShift2 && !hasShift3) {
        alert('Please enter times for at least one shift.');
        return;
    }
    
    let appliedShifts = [];
    if (hasShift1) appliedShifts.push('Shift 1');
    if (hasShift2) appliedShifts.push('Shift 2');
    if (hasShift3) appliedShifts.push('Shift 3');
    
    days.forEach(day => {
        // Apply Shift 1 if times are provided
        if (hasShift1) {
            const startInput = document.querySelector(`input[data-day="${day}"][data-shift="1"].shift1-start-time`);
            const endInput = document.querySelector(`input[data-day="${day}"][data-shift="1"].shift1-end-time`);
            if (startInput && endInput) {
                startInput.value = shift1Start;
                endInput.value = shift1End;
            }
        }
        
        // Apply Shift 2 if times are provided
        if (hasShift2) {
            const startInput = document.querySelector(`input[data-day="${day}"][data-shift="2"].shift2-start-time`);
            const endInput = document.querySelector(`input[data-day="${day}"][data-shift="2"].shift2-end-time`);
            if (startInput && endInput) {
                startInput.value = shift2Start;
                endInput.value = shift2End;
            }
        }
        
        // Apply Shift 3 if times are provided
        if (hasShift3) {
            const startInput = document.querySelector(`input[data-day="${day}"][data-shift="3"].shift3-start-time`);
            const endInput = document.querySelector(`input[data-day="${day}"][data-shift="3"].shift3-end-time`);
            if (startInput && endInput) {
                startInput.value = shift3Start;
                endInput.value = shift3End;
            }
        }
    });
    
    // Show success message
    const successMessage = `✅ Applied ${appliedShifts.join(', ')} for ${days.join(', ')}`;
    showNotification(successMessage, 'success');
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 6px;
        color: white;
        font-weight: 600;
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
        max-width: 300px;
        word-wrap: break-word;
    `;
    
    // Set background color based on type
    switch(type) {
        case 'success':
            notification.style.backgroundColor = '#28a745';
            break;
        case 'error':
            notification.style.backgroundColor = '#dc3545';
            break;
        default:
            notification.style.backgroundColor = '#17a2b8';
    }
    
    notification.textContent = message;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

function saveComprehensiveSchedule() {
    const workingPointId = document.getElementById('comprehensiveScheduleWpId').value;
    const specialistId = document.getElementById('comprehensiveScheduleSpecialistId').value;
    const errorDiv = document.getElementById('comprehensiveScheduleError');
    
    // Helper function to convert time to database format
    const convertTimeForDB = (timeValue) => {
        if (!timeValue || timeValue === '' || timeValue === '--:--') {
            return null; // Return null for empty values
        }
        // Ensure we have hh:mm format
        const timeStr = timeValue.toString();
        if (timeStr.length >= 5) {
            return timeStr.substring(0, 5) + ':00'; // Add seconds for database
        }
        return timeStr + ':00';
    };
    
    // Collect all schedule data for all shifts
    const scheduleData = [];
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    
    days.forEach(day => {
        const dayLower = day.toLowerCase();
        // Collect data for all 3 shifts
        const shift1StartInput = document.querySelector(`input[name="shift1_start_${dayLower}"]`);
        const shift1EndInput = document.querySelector(`input[name="shift1_end_${dayLower}"]`);
        const shift2StartInput = document.querySelector(`input[name="shift2_start_${dayLower}"]`);
        const shift2EndInput = document.querySelector(`input[name="shift2_end_${dayLower}"]`);
        const shift3StartInput = document.querySelector(`input[name="shift3_start_${dayLower}"]`);
        const shift3EndInput = document.querySelector(`input[name="shift3_end_${dayLower}"]`);
        
        const shift1Start = convertTimeForDB(shift1StartInput ? shift1StartInput.value : '');
        const shift1End = convertTimeForDB(shift1EndInput ? shift1EndInput.value : '');
        const shift2Start = convertTimeForDB(shift2StartInput ? shift2StartInput.value : '');
        const shift2End = convertTimeForDB(shift2EndInput ? shift2EndInput.value : '');
        const shift3Start = convertTimeForDB(shift3StartInput ? shift3StartInput.value : '');
        const shift3End = convertTimeForDB(shift3EndInput ? shift3EndInput.value : '');
        
        scheduleData.push({
            day: day,
            shift1_start: shift1Start,
            shift1_end: shift1End,
            shift2_start: shift2Start,
            shift2_end: shift2End,
            shift3_start: shift3Start,
            shift3_end: shift3End
        });
    });
    
    // Save each day's schedule
    const savePromises = scheduleData.map(dayData => {
        const formData = new FormData();
        formData.append('specialist_id', specialistId);
        formData.append('working_point_id', workingPointId);
        formData.append('day_of_week', dayData.day);
        formData.append('shift1_start', dayData.shift1_start || '00:00:00');
        formData.append('shift1_end', dayData.shift1_end || '00:00:00');
        formData.append('shift2_start', dayData.shift2_start || '00:00:00');
        formData.append('shift2_end', dayData.shift2_end || '00:00:00');
        formData.append('shift3_start', dayData.shift3_start || '00:00:00');
        formData.append('shift3_end', dayData.shift3_end || '00:00:00');
        
        return fetch('update_working_program.php', {
            method: 'POST',
            body: formData
        }).then(response => response.json());
    });
    
    // Disable save button
    const saveBtn = document.getElementById('saveComprehensiveScheduleBtn');
    const originalText = saveBtn.textContent;
    saveBtn.textContent = 'Saving...';
    saveBtn.disabled = true;
    
    Promise.all(savePromises)
        .then(results => {
            const hasError = results.some(result => !result.success);
            if (hasError) {
                errorDiv.textContent = 'Some days failed to save. Please try again.';
                errorDiv.style.display = 'block';
            } else {
                // Close modal and refresh the parent page (organisation list)
                closeComprehensiveScheduleModal();
                
                // Check if we're in the MODIFY SPECIALIST context
                const modifySpecialistModal = document.getElementById('modifySpecialistModal');
                if (modifySpecialistModal && modifySpecialistModal.style.display !== 'none') {
                    // We're in MODIFY SPECIALIST modal, refresh the schedule there
                    if (typeof loadDetailedSchedule === 'function') {
                        loadDetailedSchedule(workingPointId, specialistId);
                    }
                } else {
                    // We're in the main organisation list, refresh the bottom panel
                    const listAllOrgBtn = document.getElementById('list_all_org');
                    if (listAllOrgBtn) {
                        listAllOrgBtn.click();
                    } else {
                        // Fallback: reload the entire page
                        location.reload();
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error saving comprehensive schedule:', error);
            errorDiv.textContent = 'Error saving schedule. Please try again.';
            errorDiv.style.display = 'block';
        })
        .finally(() => {
            saveBtn.textContent = originalText;
            saveBtn.disabled = false;
        });
}

function toggleWebhook(webhookName) {
    const content = document.getElementById('content-' + webhookName);
    const caret = document.getElementById('caret-' + webhookName);

    if (content.classList.contains('active')) {
        content.classList.remove('active');
        caret.classList.remove('rotated');
        caret.textContent = '▶';
    } else {
        // Close all other webhook contents
        document.querySelectorAll('.webhook-content').forEach(el => {
            el.classList.remove('active');
        });
        document.querySelectorAll('.caret').forEach(el => {
            el.classList.remove('rotated');
            el.textContent = '▶';
        });

        // Open this one
        content.classList.add('active');
        caret.classList.add('rotated');
        caret.textContent = '▼';
    }
}

// Simple tab switcher for admin_dashboard.php
function switchTab(event, tabName) {
    // Get all elements with class="tab-content" and hide them
    var tabContents = document.getElementsByClassName('tab-content');
    for (var i = 0; i < tabContents.length; i++) {
        tabContents[i].style.display = 'none';
    }
    // Get all elements with class="tab-button" and remove 'active'
    var tabButtons = document.getElementsByClassName('tab-button');
    for (var i = 0; i < tabButtons.length; i++) {
        tabButtons[i].classList.remove('active');
    }
    // Show the current tab, and add an 'active' class to the button that opened the tab
    document.getElementById(tabName).style.display = 'block';
    event.currentTarget.classList.add('active');
}

// Add Specialist Modal Functions
function openAddSpecialistModal(workpointId, organisationId) {
    const modal = document.getElementById('addSpecialistModal');
    if (!modal) {
        console.error('Modal not found!');
        return;
    }
    
    modal.style.display = 'flex';
    document.getElementById('workpointId').value = workpointId;
    document.getElementById('organisationId').value = organisationId;
    
    // Set workpoint info if provided
    if (workpointId) {
        document.getElementById('workpointInfo').style.display = 'block';
        document.getElementById('workpointSelect').style.display = 'none';
        document.getElementById('workpointLabel').textContent = 'Assignment for Working Point:';
        
        // Get workpoint details
        fetch('get_working_point_details.php?workpoint_id=' + workpointId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('workpointName').textContent = data.workpoint.name_of_the_place;
                    document.getElementById('workpointAddress').textContent = data.workpoint.address;
                }
            })
            .catch(error => {
                // Handle error silently
            });
    } else {
        document.getElementById('workpointInfo').style.display = 'none';
        document.getElementById('workpointSelect').style.display = 'block';
        document.getElementById('workpointLabel').textContent = 'Assign to Working Point *';
        
        // Load working points for this organisation
        loadWorkingPointsForOrganisation(organisationId);
    }
    
    // Load schedule editor
    loadScheduleEditor();
}

        function closeAddSpecialistModal() {
            document.getElementById('addSpecialistModal').style.display = 'none';
            document.getElementById('addSpecialistForm').reset();
            document.getElementById('addSpecialistForm').removeAttribute('data-orphaned-specialist-id');
            document.getElementById('comprehensiveScheduleTableBody').innerHTML = '';
            
            // Remove any orphaned specialist info div
            const infoDiv = document.querySelector('#addSpecialistModal .modal-body div[style*="background: #fff3cd"]');
            if (infoDiv) {
                infoDiv.remove();
            }
            
            // Reset modal title
            const modalTitle = document.querySelector('#addSpecialistModal .modal-header h3');
            if (modalTitle) {
                modalTitle.textContent = '👥 ADD NEW SPECIALIST';
            }
        }

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('addSpecialistModal');
    if (event.target === modal) {
        closeAddSpecialistModal();
    }
}

function loadWorkingPointsForOrganisation(organisationId) {
    fetch('get_working_points.php?organisation_id=' + organisationId)
        .then(response => response.json())
        .then(data => {
            const workpointSelect = document.getElementById('workpointSelect');
            workpointSelect.innerHTML = '<option value="">Select a working point...</option>';
            
            data.forEach(wp => {
                const option = document.createElement('option');
                option.value = wp.unic_id;
                option.textContent = wp.name_of_the_place + ' - ' + wp.address;
                workpointSelect.appendChild(option);
            });
        })
        .catch(error => {
            // Handle error silently
        });
}

function loadScheduleEditor() {
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const tableBody = document.getElementById('comprehensiveScheduleTableBody');
    
    if (!tableBody) {
        console.error('comprehensiveScheduleTableBody not found!');
        return;
    }
    
    tableBody.innerHTML = '';
    
    days.forEach(day => {
        const row = document.createElement('tr');
        row.style.cssText = 'border: 1px solid #ddd; background: white;';
        row.innerHTML = `
            <td class="day-name" style="border: 1px solid #ddd; padding: 6px 8px; font-weight: 600; color: #333; text-align: left;">${day}</td>
            <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-start-time" name="shift1_start_${day.toLowerCase()}" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
            <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-end-time" name="shift1_end_${day.toLowerCase()}" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
            <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 1)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
            <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-start-time" name="shift2_start_${day.toLowerCase()}" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
            <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-end-time" name="shift2_end_${day.toLowerCase()}" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
            <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 2)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
            <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-start-time" name="shift3_start_${day.toLowerCase()}" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
            <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-end-time" name="shift3_end_${day.toLowerCase()}" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
            <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 3)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
        `;
        tableBody.appendChild(row);
    });
}

function clearShift(button, shiftNum) {
    const row = button.closest('tr');
    const startInput = row.querySelector(`.shift${shiftNum}-start-time`);
    const endInput = row.querySelector(`.shift${shiftNum}-end-time`);
    startInput.value = '';
    endInput.value = '';
}

function applyBulkSchedule(type) {
    let startTime, endTime, days;
    
    switch(type) {
        case 'mondayToFriday':
            startTime = document.getElementById('mondayToFridayStart').value;
            endTime = document.getElementById('mondayToFridayEnd').value;
            days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
            break;
        case 'saturday':
            startTime = document.getElementById('saturdayStart').value;
            endTime = document.getElementById('saturdayEnd').value;
            days = ['saturday'];
            break;
        case 'sunday':
            startTime = document.getElementById('sundayStart').value;
            endTime = document.getElementById('sundayEnd').value;
            days = ['sunday'];
            break;
    }
    
    if (!startTime || !endTime) {
        alert('Please enter both start and end times.');
        return;
    }
    
    const shiftNum = document.getElementById('quickOptionsShiftSelect').value;
    days.forEach(day => {
        const row = document.querySelector(`tr:has(input[name="shift${shiftNum}_start_${day}"])`);
        if (row) {
            const startInput = row.querySelector(`.shift${shiftNum}-start-time`);
            const endInput = row.querySelector(`.shift${shiftNum}-end-time`);
            startInput.value = startTime;
            endInput.value = endTime;
        }
    });
}

        function submitAddSpecialist() {
            const formData = new FormData(document.getElementById('addSpecialistForm'));
            
            // Check if this is for an orphaned specialist
            const orphanedSpecialistId = document.getElementById('addSpecialistForm').getAttribute('data-orphaned-specialist-id');
            
            if (orphanedSpecialistId) {
                // Handle orphaned specialist assignment
                const workpointId = document.getElementById('workpointSelect').value;
                if (!workpointId) {
                    alert('Please select a working point for the orphaned specialist.');
                    return;
                }
                
                formData.append('specialist_id', orphanedSpecialistId);
                formData.append('workpoint_id', workpointId);
                
                // Add schedule data
                const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                days.forEach(day => {
                    for (let shift = 1; shift <= 3; shift++) {
                        const startInput = document.querySelector(`input[name="shift${shift}_start_${day}"]`);
                        const endInput = document.querySelector(`input[name="shift${shift}_end_${day}"]`);
                        if (startInput && endInput) {
                            formData.append(`schedule[${day}][shift${shift}_start]`, startInput.value || '');
                            formData.append(`schedule[${day}][shift${shift}_end]`, endInput.value || '');
                        }
                    }
                });
                
                fetch('assign_orphaned_specialist.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccessMessage('Orphaned specialist assigned successfully!');
                        closeAddSpecialistModal();
                        // Reload the bottom panel to show the updated specialist
                        loadBottomPanel('list_all_org');
                    } else {
                        alert('Error: ' + (data.message || 'Failed to assign orphaned specialist'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while assigning the orphaned specialist');
                });
            } else {
                // Handle new specialist creation
                const workpointId = document.getElementById('workpointId').value;
                if (workpointId) {
                    formData.append('working_points[]', workpointId);
                }
                
                // Add schedule data
                const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                let scheduleDataCount = 0;
                days.forEach(day => {
                    for (let shift = 1; shift <= 3; shift++) {
                        const startInput = document.querySelector(`input[name="shift${shift}_start_${day}"]`);
                        const endInput = document.querySelector(`input[name="shift${shift}_end_${day}"]`);
                        if (startInput && endInput) {
                            formData.append(`schedule[${day}][shift${shift}_start]`, startInput.value || '');
                            formData.append(`schedule[${day}][shift${shift}_end]`, endInput.value || '');
                            scheduleDataCount++;
                        }
                    }
                });
                
                fetch('add_specialist_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Specialist added successfully!', 'success');
                        closeAddSpecialistModal();
                        // Reload the bottom panel to show the new specialist
                        loadBottomPanel('list_all_org');
                    } else {
                        showNotification('Error: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    showNotification('An error occurred while adding the specialist.', 'error');
                });
            }
        }

// Orphaned Specialist Functions
function assignOrphanedSpecialist(specialistId, specialistName, organisationId) {
    // Open the Add Working Point Assignment modal directly
    document.getElementById('addWpModal').style.display = 'flex';
    document.getElementById('addWpError').style.display = 'none';
    
    // Set the specialist ID in the form for the assignment
    document.getElementById('addWpModal').setAttribute('data-orphaned-specialist-id', specialistId);
    document.getElementById('addWpModal').setAttribute('data-orphaned-specialist-name', specialistName);
    
    // Update modal title
    const modalTitle = document.querySelector('#addWpModal .add-wp-modal-header h3');
    if (modalTitle) {
        modalTitle.textContent = '📅 Assign Schedule to Orphaned Specialist';
    }
    
    // Show a message about the orphaned specialist
    const modalBody = document.querySelector('#addWpModal .add-wp-modal-body');
    if (modalBody) {
        // Remove any existing orphaned specialist note
        const existingNote = modalBody.querySelector('.orphaned-specialist-note');
        if (existingNote) {
            existingNote.remove();
        }
        
        const infoDiv = document.createElement('div');
        infoDiv.className = 'orphaned-specialist-note';
        infoDiv.style.cssText = 'background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 10px; margin-bottom: 15px; color: #856404;';
        infoDiv.innerHTML = `<strong>⚠️ Orphaned Specialist:</strong> ${specialistName} - This specialist will be assigned to a working point with the schedule you set.`;
        modalBody.insertBefore(infoDiv, modalBody.firstChild);
    }
    
    // Reset form fields
    document.querySelectorAll('.day-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('shift1_start').value = '09:00';
    document.getElementById('shift1_end').value = '17:00';
    document.getElementById('shift2_start').value = '';
    document.getElementById('shift2_end').value = '';
    document.getElementById('shift3_start').value = '';
    document.getElementById('shift3_end').value = '';
    
    // Clear selected days display
    const displayDiv = document.getElementById('selected-days-display');
    if (displayDiv) {
        displayDiv.textContent = '';
    }
    
    // Add event listeners to day checkboxes for updating display
    document.querySelectorAll('.day-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedDaysDisplay);
    });
    
    // Load available working points for this organisation
    loadAvailableWorkingPointsForOrphaned(specialistId, organisationId);
}

function loadAvailableWorkingPointsForOrphaned(specialistId, organisationId) {
    const container = document.getElementById('availableWorkingPoints');
    container.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;">Loading working points...</div>';
    
    fetch('get_available_working_points.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'specialist_id=' + encodeURIComponent(specialistId) + '&is_orphaned=true'
    })
    .then(response => response.json())
    .then(data => {
        console.log('Working points data:', data);
        container.innerHTML = '';
        
        if (data.success && data.working_points && data.working_points.length > 0) {
            data.working_points.forEach(wp => {
                const wpItem = document.createElement('div');
                wpItem.className = 'available-wp-item';
                wpItem.setAttribute('data-wp-id', wp.unic_id);
                wpItem.onclick = function() {
                    // Remove selection from all items
                    document.querySelectorAll('.available-wp-item').forEach(item => {
                        item.classList.remove('selected');
                    });
                    // Add selection to clicked item
                    this.classList.add('selected');
                };
                wpItem.innerHTML = `
                    <div class="available-wp-info" style="flex: 1; display: flex; align-items: center;">
                        <span style="font-weight: 600; color: #333;">${wp.name_of_the_place}</span>
                        <span style="margin: 0 8px; color: #666;">--</span>
                        <span style="color: #666;">${wp.address}</span>
                    </div>
                    <div class="available-wp-check" style="color: #28a745; font-weight: bold;">✓</div>
                `;
                container.appendChild(wpItem);
            });
        } else {
            container.innerHTML = '<div class="no-working-points-available">No available working points found.</div>';
        }
    })
    .catch(error => {
        console.error('Error loading available working points:', error);
        const container = document.getElementById('availableWorkingPoints');
        container.innerHTML = '<div class="no-working-points-available">Error loading working points.</div>';
    });
}

        function deleteOrphanedSpecialist(specialistId, specialistName) {
            if (!confirm(`Are you sure you want to delete the specialist "${specialistName}"?\n\nThis action cannot be undone and will permanently remove the specialist from the system.`)) {
                return;
            }
            
            // Show password confirmation modal
            const password = prompt('Please enter your password to confirm deletion:');
            if (!password) {
                return;
            }
            
            const formData = new FormData();
            formData.append('specialist_id', specialistId);
            formData.append('password', password);
            formData.append('action', 'delete_specialist');
            
            fetch('modify_specialist_details.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessMessage('Specialist deleted successfully');
                    loadBottomPanel('list_all_org');
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete specialist'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the specialist');
            });
        }
        
        function removeSpecialistFromModal() {
            const specialistId = document.getElementById('modifySpecialistForm').getAttribute('data-specialist-id');
            const specialistName = document.getElementById('modifySpecialistName').textContent;
            
            if (!specialistId) {
                alert('Error: Unable to get specialist information for removal.');
                return;
            }
            
            // Get the current working point ID from the assigned working points
            const assignedWorkingPoints = document.querySelectorAll('#assignedWorkingPoints .working-point-row');
            if (assignedWorkingPoints.length === 0) {
                alert('Error: No working points found for this specialist.');
                return;
            }
            
            // For now, we'll use the first working point. In a more complex implementation,
            // you might want to show a dropdown to select which working point to remove from
            const workpointId = assignedWorkingPoints[0].getAttribute('data-wp-id');
            
            // Store the specialist ID and workpoint ID for the delete modal
            window.currentSpecialistId = specialistId;
            window.currentWorkpointId = workpointId;
            
            // Close the modify modal first
            closeModifySpecialistModal();
            
            // Show the delete specialist confirmation modal
            document.getElementById('deleteSpecialistName').textContent = specialistName;
            document.getElementById('deleteSpecialistForm').setAttribute('data-specialist-id', specialistId);
            document.getElementById('deleteSpecialistForm').setAttribute('data-working-point-id', workpointId);
            document.getElementById('deleteSpecialistModal').style.display = 'block';
            document.getElementById('deleteSpecialistError').style.display = 'none';
        }
        

</script>


<!-- Add Organisation Modal -->
<div id="addOrgModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:10000;background:rgba(0,0,0,0.35);">
  <div style="background:#fff;width:400px;max-width:98vw;padding:24px 18px 18px 18px;margin:60px auto 0 auto;border-radius:7px;position:relative;">
    <h5>Add New Organisation</h5>
    <form id="addOrgForm">
      <div style="margin-bottom:6px;"><input name="alias_name" type="text" class="form-control" placeholder="Alias Name *" required></div>
      <div style="margin-bottom:6px;"><input name="oficial_company_name" type="text" class="form-control" placeholder="Official Company Name *" required></div>
      <!-- Booking phone is now managed per working point -->
      <div style="margin-bottom:6px;"><input name="contact_name" type="text" class="form-control" placeholder="Contact Name"></div>
      <div style="margin-bottom:6px;"><input name="position" type="text" class="form-control" placeholder="Position"></div>
      <div style="margin-bottom:6px;"><input name="email_address" type="email" class="form-control" placeholder="Email"></div>
      <div style="margin-bottom:6px;"><input name="www_address" type="text" class="form-control" placeholder="Website"></div>
      <div style="margin-bottom:6px;"><input name="company_head_office_address" type="text" class="form-control" placeholder="Head Office Address"></div>
      <div style="margin-bottom:6px;"><input name="company_phone_nr" type="text" class="form-control" placeholder="Company Phone"></div>
      <div style="margin-bottom:6px;"><input name="country" type="text" class="form-control" placeholder="Country"></div>
      <div style="margin-bottom:6px;"><input name="owner_name" type="text" class="form-control" placeholder="Owner Name"></div>
      <div style="margin-bottom:6px;"><input name="owner_phone_nr" type="text" class="form-control" placeholder="Owner Phone"></div>
      <div style="margin-bottom:6px;"><input name="user" type="text" class="form-control" placeholder="Login"></div>
      <div style="margin-bottom:6px;"><input name="pasword" type="text" class="form-control" placeholder="Password"></div>
      
      <div id="addOrgError" style="color:#c00;font-size:0.96em;margin-bottom:6px;display:none;"></div>
      <button type="submit" class="btn btn-success btn-sm">Add</button>
      <button type="button" onclick="closeAddOrgModal()" class="btn btn-secondary btn-sm">Cancel</button>
    </form>
    <span onclick="closeAddOrgModal()" style="position:absolute;top:10px;right:16px;cursor:pointer;font-size:1.2em;">&times;</span>
  </div>
</div>

<!-- Delete Organisation Confirmation Modal -->
<div id="deleteOrgModal" class="delete-modal-overlay">
    <div class="delete-modal">
        <div class="delete-modal-header">
            <h3>⚠️ DELETE ORGANISATION</h3>
        </div>
        <div class="delete-modal-body">
            <div class="org-name-row">
                <span class="delete-icon-inline">❌</span>
                <div class="org-name-large" id="deleteOrgName"></div>
            </div>
            
            <div class="warning-text">
                ⚠️ WARNING: This action will permanently delete this organisation and ALL its dependencies!
            </div>
            
            <div class="dependencies-list">
                <strong>All of the following will be REMOVED:</strong>
                <ul>
                    <li>• The organisation itself</li>
                    <li>• All working points</li>
                    <li>• All specialists</li>
                    <li>• All services</li>
                    <li>• All bookings</li>
                    <li>• All working programs</li>
                </ul>
            </div>
            
            <div class="warning-text">
                <span class="blinking-warning">❌ <span class="underlined">This action cannot be undone!</span></span>
            </div>
            
            <br><br>
            
            <div class="password-confirmation">
                <div class="password-button-row">
                    <input type="password" id="deletePassword" class="password-input" placeholder="password to confirm" autocomplete="current-password">
                    <button class="btn-delete" id="confirmDeleteBtn" onclick="confirmDelete()">Delete Organisation</button>
                </div>
                <div id="passwordError" class="password-error" style="display: none; color: #dc3545; font-size: 0.9em; margin-top: 5px;"></div>
            </div>
            
            <div class="delete-modal-buttons">
                <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Modify Organisation Modal -->
<div id="modifyOrgModal" class="modify-modal-overlay">
    <div class="modify-modal">
        <div class="modify-modal-header">
            <h3>✏️ MODIFY ORGANISATION</h3>
            <span class="modify-modal-close" onclick="closeModifyModal()">&times;</span>
        </div>
        <div class="modify-modal-body">
            <div class="org-name-row">
                <span class="modify-icon-inline">✏️</span>
                <div class="org-name-large" id="modifyOrgName"></div>
            </div>
            
            <form id="modifyOrgForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="alias_name">Alias Name *</label>
                        <input type="text" id="alias_name" name="alias_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="oficial_company_name">Official Company Name *</label>
                        <input type="text" id="oficial_company_name" name="oficial_company_name" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="booking_phone_nr">Booking Phone</label>
                        <input type="text" id="booking_phone_nr" name="booking_phone_nr" class="form-control booking-phone" disabled>
                        <small class="form-text text-muted">Booking phone is now managed per working point in the admin panel.</small>
                    </div>
                    <div class="form-group">
                        <label for="contact_name">Contact Name</label>
                        <input type="text" id="contact_name" name="contact_name" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="position">Position</label>
                        <input type="text" id="position" name="position" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="email_address">Email</label>
                        <input type="email" id="email_address" name="email_address" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="www_address">Website</label>
                        <input type="text" id="www_address" name="www_address" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="company_head_office_address">Head Office Address</label>
                        <input type="text" id="company_head_office_address" name="company_head_office_address" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="company_phone_nr">Company Phone</label>
                        <input type="text" id="company_phone_nr" name="company_phone_nr" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="country">Country</label>
                        <input type="text" id="country" name="country" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="owner_name">Owner Name</label>
                        <input type="text" id="owner_name" name="owner_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="owner_phone_nr">Owner Phone</label>
                        <input type="text" id="owner_phone_nr" name="owner_phone_nr" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="user">Login</label>
                        <input type="text" id="user" name="user" class="form-control login-password">
                    </div>
                    <div class="form-group">
                        <label for="pasword">Password</label>
                        <input type="text" id="pasword" name="pasword" class="form-control login-password">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        
                        
                    </div>
                </div>
                
                <div id="modifyOrgError" class="modify-error" style="display: none; color: #dc3545; font-size: 0.9em; margin: 10px 0;"></div>
                
                <div class="modify-modal-buttons" style="display: flex; justify-content: space-between; align-items: center;">
                    <!-- Delete button on the left -->
                    <button type="button" class="btn-delete" onclick="deleteOrganisationFromModal()" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                        🗑️ Delete Organisation
                    </button>
                    
                    <!-- Update and Cancel buttons on the right -->
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn-modify" id="confirmModifyBtn">Update Organisation</button>
                        <button type="button" class="btn-cancel" onclick="closeModifyModal()">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="success-modal-overlay">
    <div class="success-modal">
        <div class="success-modal-header">
            <h3>✅ UPDATE SUCCESSFUL</h3>
            <span class="success-modal-close" onclick="closeSuccessModal()">&times;</span>
        </div>
        <div class="success-modal-body">
            <div class="success-icon">🎉</div>
            <h4>Organisation Updated Successfully!</h4>
            <div class="updated-fields">
                <h5>Updated Fields:</h5>
                <div id="updatedFieldsList"></div>
            </div>
            <div class="success-modal-buttons">
                <button class="btn-success" onclick="closeSuccessModal()">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Modify Working Point Modal -->
<div id="modifyWpModal" class="modify-modal-overlay">
    <div class="modify-modal">
        <div class="modify-modal-header">
            <h3>🏢 MODIFY WORKING POINT</h3>
            <span class="modify-modal-close" onclick="closeModifyWpModal()">&times;</span>
        </div>
        <div class="modify-modal-body">
            <div class="org-name-row">
                <span class="modify-icon-inline">🏢</span>
                <div class="org-name-large" id="modifyWpName"></div>
            </div>
            
            <form id="modifyWpForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="wp_name_of_the_place">Name of the Place *</label>
                        <input type="text" id="wp_name_of_the_place" name="name_of_the_place" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="wp_description_of_the_place">Description of the Place</label>
                        <input type="text" id="wp_description_of_the_place" name="description_of_the_place" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="wp_address">Address *</label>
                        <input type="text" id="wp_address" name="address" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="wp_landmark">Landmark</label>
                        <input type="text" id="wp_landmark" name="landmark" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="width: 100%;">
                        <label for="wp_directions">Directions</label>
                        <textarea id="wp_directions" name="directions" class="form-control" rows="2" style="resize: vertical;"></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="wp_lead_person_name">Lead Person Name</label>
                        <input type="text" id="wp_lead_person_name" name="lead_person_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="wp_lead_person_phone_nr">Lead Person Phone</label>
                        <input type="text" id="wp_lead_person_phone_nr" name="lead_person_phone_nr" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="wp_workplace_phone_nr">Workplace Phone</label>
                        <input type="text" id="wp_workplace_phone_nr" name="workplace_phone_nr" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="wp_booking_phone_nr">Booking Phone *</label>
                        <input type="text" id="wp_booking_phone_nr" name="booking_phone_nr" class="form-control" required style="background-color: #e3f2fd; border: 1px solid #90caf9;">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="wp_email">Email</label>
                        <input type="email" id="wp_email" name="email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="wp_booking_sms_number">Booking SMS Number</label>
                        <input type="text" id="wp_booking_sms_number" name="booking_sms_number" class="form-control" style="background-color: #ffedd5; border: 1px solid #ffb380;">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="wp_country_display">Country *</label>
                        <input type="text" id="wp_country_display" placeholder="Type to search countries..." autocomplete="off" class="form-control">
                        <input type="hidden" id="wp_country" name="country" required>
                    </div>
                    <div class="form-group">
                        <label for="wp_language">Language *</label>
                        <input type="text" id="wp_language" name="language" class="form-control" maxlength="2" pattern="[a-zA-Z]{2}" placeholder="e.g., EN, RO, LT" title="Enter 2-letter language code" required style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="display: flex; flex-direction: row; gap: 8px; align-items: flex-end; margin-bottom: 0;">
                        <div style="flex: 1; display: flex; flex-direction: column;">
                            <label for="wp_user" style="font-size: 13px;">Login</label>
                            <input type="text" id="wp_user" name="user" class="form-control" style="font-size: 13px; padding: 4px 6px; background: #f3e5f5; border: 1px solid #ce93d8;">
                        </div>
                        <div style="flex: 1; display: flex; flex-direction: column;">
                            <label for="wp_password" style="font-size: 13px;">Password</label>
                            <input type="text" id="wp_password" name="password" class="form-control" style="font-size: 13px; padding: 4px 6px; background: #f3e5f5; border: 1px solid #ce93d8;">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="wp_we_handling">We Handling</label>
                        <input type="text" id="wp_we_handling" name="we_handling" class="form-control" placeholder="What do we handle here? Ex: Specialist, Table, Ramp">
                    </div>
                    <div class="form-group">
                        <label for="wp_specialist_relevance">Specialist Relevance</label>
                        <select id="wp_specialist_relevance" name="specialist_relevance" class="form-control">
                            <option value="">Select relevance...</option>
                            <option value="strong">Strong</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </div>
                <div id="modifyWpError" class="modify-error" style="display: none; color: #dc3545; font-size: 0.9em; margin: 10px 0;"></div>
                <div class="modify-modal-buttons" style="display: flex; justify-content: space-between; align-items: center;">
                    <!-- Delete button on the left -->
                    <button type="button" class="btn-delete" onclick="deleteWorkingPointFromModal()" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                        🗑️ Delete Working Point
                    </button>

                    <!-- Update button on the right -->
                    <button type="submit" class="btn-modify" id="confirmModifyWpBtn">Update Working Point</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modify Specialist Modal -->
<div id="modifySpecialistModal" class="modify-modal-overlay">
    <div class="modify-modal">
        <div class="modify-modal-header">
            <h3>👥 MODIFY SPECIALIST</h3>
            <span class="modify-modal-close" onclick="closeModifySpecialistModal()">&times;</span>
        </div>
        <div class="modify-modal-body">
            <div class="org-name-row">
                <span class="modify-icon-inline">👥</span>
                <div class="org-name-large" id="modifySpecialistName"></div>
            </div>
            
            <form id="modifySpecialistForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="sp_name">Name *</label>
                        <input type="text" id="sp_name" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="sp_speciality">Speciality *</label>
                        <input type="text" id="sp_speciality" name="speciality" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="sp_email">Email</label>
                        <input type="email" id="sp_email" name="email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="sp_phone_nr">Phone</label>
                        <input type="text" id="sp_phone_nr" name="phone_nr" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="sp_user">Login</label>
                        <input type="text" id="sp_user" name="user" class="form-control login-password">
                    </div>
                    <div class="form-group">
                        <label for="sp_password">Password</label>
                        <input type="text" id="sp_password" name="password" class="form-control login-password">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group full-width">
                        <label>Assigned Working Points</label>
                        <div id="assignedWorkingPoints" class="working-points-list">
                            <!-- Working points will be loaded here dynamically -->
                        </div>
                    </div>
                </div>
                
                <div id="modifySpecialistError" class="modify-error" style="display: none; color: #dc3545; font-size: 0.9em; margin: 10px 0;"></div>
                
                <div class="modify-modal-buttons" style="display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" class="btn-delete" id="removeSpecialistBtn" onclick="removeSpecialistFromModal()" style="white-space: nowrap;">❌ Remove Specialist</button>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn-add-wp" onclick="showAddWorkingPointModal()" style="white-space: nowrap;">📅 Work Point Assignment</button>
                        <button type="submit" class="btn-modify" id="confirmModifySpecialistBtn" style="white-space: nowrap;">Update Specialist</button>
                        <button type="button" class="btn-modify" onclick="closeModifySpecialistModal(); if (typeof loadBottomPanel === 'function') { loadBottomPanel('list_all_org'); }" style="white-space: nowrap;">OK</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Working Point Assignment Modal -->
<div id="addWpModal" class="add-wp-modal-overlay">
    <div class="add-wp-modal">
        <div class="add-wp-modal-header">
            <h3>🏢 Add Working Point Assignment</h3>
            <button class="add-wp-modal-close" onclick="closeAddWpModal()">&times;</button>
        </div>
        <div class="add-wp-modal-body" style="padding: 15px 20px;">
            <div class="form-group" style="margin-bottom: 15px;">
                <label>Select Working Point:</label>
                <div id="availableWorkingPoints" class="available-wp-list">
                    <!-- Available working points will be loaded here -->
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="working_program">Working Program:</label>
                <div class="working-program-form">
                    <div class="form-row">
                        <div class="form-group">
                            <div class="day-radio-container" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 5px;">
                                <label class="day-radio-label" style="display: flex; align-items: center; gap: 4px; cursor: pointer; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; background: #f8f9fa; transition: all 0.2s;">
                                    <input type="checkbox" name="day_of_week[]" value="Monday" class="day-checkbox" style="margin: 0;">
                                    <span style="font-size: 12px; font-weight: 600;">Mon</span>
                                </label>
                                <label class="day-radio-label" style="display: flex; align-items: center; gap: 4px; cursor: pointer; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; background: #f8f9fa; transition: all 0.2s;">
                                    <input type="checkbox" name="day_of_week[]" value="Tuesday" class="day-checkbox" style="margin: 0;">
                                    <span style="font-size: 12px; font-weight: 600;">Tue</span>
                                </label>
                                <label class="day-radio-label" style="display: flex; align-items: center; gap: 4px; cursor: pointer; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; background: #f8f9fa; transition: all 0.2s;">
                                    <input type="checkbox" name="day_of_week[]" value="Wednesday" class="day-checkbox" style="margin: 0;">
                                    <span style="font-size: 12px; font-weight: 600;">Wed</span>
                                </label>
                                <label class="day-radio-label" style="display: flex; align-items: center; gap: 4px; cursor: pointer; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; background: #f8f9fa; transition: all 0.2s;">
                                    <input type="checkbox" name="day_of_week[]" value="Thursday" class="day-checkbox" style="margin: 0;">
                                    <span style="font-size: 12px; font-weight: 600;">Thu</span>
                                </label>
                                <label class="day-radio-label" style="display: flex; align-items: center; gap: 4px; cursor: pointer; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; background: #f8f9fa; transition: all 0.2s;">
                                    <input type="checkbox" name="day_of_week[]" value="Friday" class="day-checkbox" style="margin: 0;">
                                    <span style="font-size: 12px; font-weight: 600;">Fri</span>
                                </label>
                                <label class="day-radio-label" style="display: flex; align-items: center; gap: 4px; cursor: pointer; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; background: #f8f9fa; transition: all 0.2s;">
                                    <input type="checkbox" name="day_of_week[]" value="Saturday" class="day-checkbox" style="margin: 0;">
                                    <span style="font-size: 12px; font-weight: 600;">Sat</span>
                                </label>
                                <label class="day-radio-label" style="display: flex; align-items: center; gap: 4px; cursor: pointer; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; background: #f8f9fa; transition: all 0.2s;">
                                    <input type="checkbox" name="day_of_week[]" value="Sunday" class="day-checkbox" style="margin: 0;">
                                    <span style="font-size: 12px; font-weight: 600;">Sun</span>
                                </label>
                            </div>
                            <div id="selected-days-display" style="margin-top: 5px; font-size: 12px; color: #007bff; min-height: 16px;"></div>
                        </div>
                    </div>
                    <div class="form-row" style="margin-bottom: 10px;">
                        <div class="form-group">
                            <label for="shift1_start">Shift 1 Start:</label>
                            <input type="time" id="shift1_start" name="shift1_start" class="form-control" value="09:00" required>
                        </div>
                        <div class="form-group">
                            <label for="shift1_end">Shift 1 End:</label>
                            <input type="time" id="shift1_end" name="shift1_end" class="form-control" value="17:00" required>
                        </div>
                    </div>
                    <div class="form-row" style="margin-bottom: 10px;">
                        <div class="form-group">
                            <label for="shift2_start">Shift 2 Start (Optional):</label>
                            <input type="time" id="shift2_start" name="shift2_start" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="shift2_end">Shift 2 End (Optional):</label>
                            <input type="time" id="shift2_end" name="shift2_end" class="form-control">
                        </div>
                    </div>
                    <div class="form-row" style="margin-bottom: 10px;">
                        <div class="form-group">
                            <label for="shift3_start">Shift 3 Start (Optional):</label>
                            <input type="time" id="shift3_start" name="shift3_start" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="shift3_end">Shift 3 End (Optional):</label>
                            <input type="time" id="shift3_end" name="shift3_end" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="addWpError" class="modify-error" style="display: none; margin: 10px 0;"></div>
            
            <div class="add-wp-modal-buttons" style="margin-top: 15px;">
                <button type="button" class="btn-cancel" onclick="closeAddWpModal()">Cancel</button>
                <button type="button" class="btn-modify" id="confirmAddWpBtn" onclick="addWorkingPointAssignment()">Add Assignment</button>
            </div>
        </div>
    </div>
</div>

<!-- Move Specialist Modal -->
<div id="moveSpecialistModal" class="modify-modal-overlay">
    <div class="modify-modal">
        <div class="modify-modal-header">
            <h3>🔄 MOVE SPECIALIST</h3>
            <span class="modify-modal-close" onclick="closeMoveSpecialistModal()">&times;</span>
        </div>
        <div class="modify-modal-body">
            <div class="org-name-row">
                <span class="modify-icon-inline">🔄</span>
                <div class="org-name-large" id="moveSpecialistName"></div>
            </div>
            
            <form id="moveSpecialistForm">
                <div class="form-group">
                    <label>Select Target Working Point:</label>
                    <div id="moveWorkingPointsList" class="move-wp-list">
                        <!-- Available working points will be loaded here -->
                    </div>
                </div>
                
                <div id="moveSpecialistError" class="modify-error" style="display: none; color: #dc3545; font-size: 0.9em; margin: 10px 0;"></div>
                
                <div class="modify-modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeMoveSpecialistModal()">Cancel</button>
                    <button type="button" class="btn-modify" id="confirmMoveSpecialistBtn" onclick="confirmMoveSpecialist()">Move Specialist</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add New Working Point Modal -->
<div id="addNewWpModal" class="modify-modal-overlay">
    <div class="modify-modal">
        <div class="modify-modal-header">
            <h3>🏢 ADD NEW WORKING POINT</h3>
            <span class="modify-modal-close" onclick="closeAddNewWpModal()">&times;</span>
        </div>
        <div class="modify-modal-body">
            <form id="addWpForm">
                <input type="hidden" id="addWpOrganisationId" name="organisation_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_wp_name_of_the_place">Name of the Place *</label>
                        <input type="text" id="add_wp_name_of_the_place" name="name_of_the_place" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="add_wp_address">Address *</label>
                        <input type="text" id="add_wp_address" name="address" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_wp_lead_person_name">Lead Person Name</label>
                        <input type="text" id="add_wp_lead_person_name" name="lead_person_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="add_wp_lead_person_phone_nr">Lead Person Phone</label>
                        <input type="text" id="add_wp_lead_person_phone_nr" name="lead_person_phone_nr" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_wp_workplace_phone_nr">Workplace Phone</label>
                        <input type="text" id="add_wp_workplace_phone_nr" name="workplace_phone_nr" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="add_wp_booking_phone_nr">Booking Phone</label>
                        <input type="text" id="add_wp_booking_phone_nr" name="booking_phone_nr" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_wp_email">Email</label>
                        <input type="email" id="add_wp_email" name="email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="add_wp_country_display">Country *</label>
                        <input type="text" id="add_wp_country_display" placeholder="Type to search countries..." autocomplete="off" class="form-control">
                        <input type="hidden" id="add_wp_country" name="country" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_wp_language">Language *</label>
                        <input type="text" id="add_wp_language" name="language" class="form-control" maxlength="2" pattern="[a-zA-Z]{2}" placeholder="e.g., EN, RO, LT" title="Enter 2-letter language code" required style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')">
                    </div>
                    <div class="form-group" style="display: flex; flex-direction: row; gap: 8px; align-items: flex-end; margin-bottom: 0;">
                        <div style="flex: 1; display: flex; flex-direction: column;">
                            <label for="add_wp_user" style="font-size: 13px;">Login</label>
                            <input type="text" id="add_wp_user" name="user" class="form-control" style="font-size: 13px; padding: 4px 6px; background: #e6f2ff; border: 1px solid #b3d8ff;">
                        </div>
                        <div style="flex: 1; display: flex; flex-direction: column;">
                            <label for="add_wp_password" style="font-size: 13px;">Password</label>
                            <input type="text" id="add_wp_password" name="password" class="form-control" style="font-size: 13px; padding: 4px 6px; background: #e6f2ff; border: 1px solid #b3d8ff;">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_wp_we_handling">We Handling</label>
                        <input type="text" id="add_wp_we_handling" name="we_handling" class="form-control" placeholder="What do we handle here? Ex: Specialist, Table, Ramp">
                    </div>
                    <div class="form-group">
                        <label for="add_wp_specialist_relevance">Specialist Relevance</label>
                        <select id="add_wp_specialist_relevance" name="specialist_relevance" class="form-control">
                            <option value="">Select relevance...</option>
                            <option value="strong">Strong</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </div>
                <div id="addWpError" class="modify-error" style="display: none; color: #dc3545; font-size: 0.9em; margin: 10px 0;"></div>
                <div class="modify-modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeAddNewWpModal()">Cancel</button>
                    <button type="button" class="btn-modify" id="confirmAddNewWpBtn" onclick="submitAddWorkingPoint()">Add Working Point</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Specialist Modal -->
<div id="deleteSpecialistModal" class="delete-modal-overlay">
    <div class="delete-modal">
        <div class="delete-modal-header">
            <h3>❌ REMOVE SPECIALIST FROM WORKING POINT</h3>
        </div>
        <div class="delete-modal-body">
            <div class="org-name-row">
                <span class="delete-icon-inline">❌</span>
                <div class="org-name-large" id="deleteSpecialistName"></div>
            </div>
            
            <div class="warning-text">
                ⚠️ This will unassign the specialist from the current working point.
            </div>
            
            <div class="dependencies-list">
                <strong>This will do:</strong>
                <ul>
                    <li>• Unassign the specialist from this working point</li>
                    <li>• Remove schedule entries at this working point</li>
                </ul>
                <strong>It will NOT do:</strong>
                <ul>
                    <li>• Delete the specialist profile</li>
                    <li>• Delete bookings</li>
                </ul>
                <strong>After unassign:</strong> If the specialist has no other assignments, they will appear under Orphaned Specialists where you can permanently delete them.
            </div>
            
            <br><br>
            
            <form id="deleteSpecialistForm">
                <div id="deleteSpecialistError" class="password-error" style="display: none; color: #dc3545; font-size: 0.9em; margin-top: 5px;"></div>
                <div class="delete-modal-buttons">
                    <button class="btn-cancel" onclick="closeDeleteSpecialistModal()">Cancel</button>
                    <button class="btn-delete" id="confirmDeleteSpecialistBtn" onclick="confirmDeleteSpecialist()">Unassign from Working Point</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Working Point Confirmation Modal -->
<div id="deleteWpModal" class="delete-modal-overlay">
    <div class="delete-modal">
        <div class="delete-modal-header">
            <h3>⚠️ DELETE WORKING POINT</h3>
        </div>
        <div class="delete-modal-body">
            <div class="org-name-row">
                <span class="delete-icon-inline">🏢</span>
                <div class="org-name-large" id="deleteWpName"></div>
            </div>
            
            <div class="warning-text">
                ⚠️ WARNING: This action will permanently delete this working point and ALL its dependencies!
            </div>
            
            <div class="dependencies-list">
                <strong>All of the following will be REMOVED:</strong>
                <ul>
                    <li>• The working point itself</li>
                    <li>• All working programs for this location</li>
                    <li>• All bookings for this location</li>
                    <li>• All services for this location</li>
                </ul>
            </div>
            
            <div class="warning-text">
                <span class="blinking-warning">❌ <span class="underlined">This action cannot be undone!</span></span>
            </div>
            
            <br><br>
            
            <div class="password-confirmation">
                <div class="password-button-row">
                    <input type="password" id="deleteWpPassword" class="password-input" placeholder="password to confirm" autocomplete="current-password">
                    <button class="btn-delete" id="confirmDeleteWpBtn" onclick="confirmDeleteWp()">Delete Working Point</button>
                </div>
                <div id="deleteWpPasswordError" class="password-error" style="display: none; color: #dc3545; font-size: 0.9em; margin-top: 5px;"></div>
            </div>
            
            <div class="delete-modal-buttons">
                <button class="btn-cancel" onclick="closeDeleteWpModal()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Telnyx Phone Number Edit Modal -->
<div id="telnyxPhoneModal" class="modify-modal-overlay">
    <div class="modify-modal" style="max-width: 400px;">
        <div class="modify-modal-header">
            <h3>📞 EDIT TELNYX PHONE NUMBER</h3>
            <span class="modify-modal-close" onclick="closeTelnyxPhoneModal()">&times;</span>
        </div>
        <div class="modify-modal-body">
            <form onsubmit="updateTelnyxPhone(); return false;">
                <input type="hidden" id="telnyxWpId" value="">
                <div class="form-group">
                    <label for="telnyxPhoneInput">Telnyx Phone Number *</label>
                    <input type="text" id="telnyxPhoneInput" class="form-control" required
                           placeholder="Enter Telnyx phone number" style="font-size: 16px; padding: 8px; background-color: #e8f4ff;">
                </div>

                <br>

                <div class="form-group">
                    <label for="telnyxSmsInput">Booking SMS Number</label>
                    <input type="text" id="telnyxSmsInput" class="form-control"
                           placeholder="Enter booking SMS number" style="font-size: 16px; padding: 8px; background-color: #ffedd5;">
                </div>

                <div class="form-group">
                    <label for="telnyxWeHandling">We Handling</label>
                    <input type="text" id="telnyxWeHandling" class="form-control"
                           placeholder="What do we handle here? Ex: Specialist, Table, Ramp">
                </div>

                <div class="form-group">
                    <label for="telnyxSpecialistRelevance">Specialist Relevance</label>
                    <select id="telnyxSpecialistRelevance" class="form-control">
                        <option value="">Select relevance...</option>
                        <option value="strong">Strong</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                
                <div id="telnyxPhoneError" class="modify-error" style="display: none; color: #dc3545; font-size: 0.9em; margin: 10px 0;"></div>
                
                <div class="modify-modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeTelnyxPhoneModal()">Cancel</button>
                    <button type="submit" class="btn-modify" id="confirmTelnyxPhoneBtn">Update Phone</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Shift Editor Modal -->
<div id="shiftEditorModal" class="modify-modal-overlay">
    <div class="modify-modal">
        <div class="modify-modal-header">
            <h3>⏰ EDIT SHIFT</h3>
            <span class="modify-modal-close" onclick="closeShiftEditorModal()">&times;</span>
        </div>
        <div class="modify-modal-body">
            <div class="org-name-row">
                <span class="modify-icon-inline">⏰</span>
                <div class="org-name-large">
                    <span id="shiftEditorDay"></span> - <span id="shiftEditorShift"></span>
                </div>
            </div>
            
            <form id="shiftEditorForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="shiftEditorStart">Start Time:</label>
                        <input type="time" id="shiftEditorStart" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="shiftEditorEnd">End Time:</label>
                        <input type="time" id="shiftEditorEnd" class="form-control" required>
                    </div>
                </div>
                
                <div id="shiftEditorError" class="modify-error" style="display: none; color: #dc3545; font-size: 0.9em; margin: 10px 0;"></div>
                
                <div class="modify-modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeShiftEditorModal()">Cancel</button>
                    <button type="button" class="btn-modify" id="saveShiftBtn" onclick="saveShift()">Save Shift</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Comprehensive Schedule Editor Modal -->
<div id="comprehensiveScheduleModal" class="modify-modal-overlay">
    <div class="modify-modal">
        <div class="modify-modal-header">
            <h3>📅 COMPREHENSIVE SCHEDULE EDITOR</h3>
            <span class="modify-modal-close" onclick="closeComprehensiveScheduleModal()">&times;</span>
        </div>
        <div class="modify-modal-body">
            <div class="org-name-row">
                <span class="modify-icon-inline">📅</span>
                <div class="org-name-large" id="comprehensiveScheduleWorkingPointName"></div>
            </div>
            <div class="org-name-row" style="margin-top: 5px;">
                <span class="modify-icon-inline">👤</span>
                <div class="org-name-large" id="comprehensiveScheduleSpecialistName"></div>
            </div>
            
            <form id="comprehensiveScheduleForm">
                <input type="hidden" id="comprehensiveScheduleWpId">
                <input type="hidden" id="comprehensiveScheduleSpecialistId">
                
                <!-- Individual Day Editor -->
                <div class="individual-edit-section">
                    <h4>📋 Individual Day Editor</h4>
                    <div class="schedule-editor-table-container">
                        <table class="schedule-editor-table">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th colspan="3">Shift 1</th>
                                    <th colspan="3">Shift 2</th>
                                    <th colspan="3">Shift 3</th>
                                </tr>
                                <tr>
                                    <th></th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th></th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th></th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="comprehensiveScheduleTableBody">
                                <!-- Days will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Quick Options Section -->
                <div class="individual-edit-section">
                    <h4 style="font-size: 14px; margin-bottom: 15px;">⚡ Quick Options</h4>
                    <div class="schedule-editor-table-container">
                        <div style="padding: 15px; background: #f8f9fa; border-radius: 5px; border: 1px solid #e9ecef;">
                            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                <!-- Day Selector -->
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <label style="font-size: 12px; font-weight: 600; color: #333;">Day:</label>
                                    <select id="quickOptionsDaySelect" style="font-size: 11px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 3px; width: 80px;">
                                        <option value="mondayToFriday">Mon-Fri</option>
                                        <option value="saturday">Saturday</option>
                                        <option value="sunday">Sunday</option>
                                    </select>
                                </div>
                                
                                <!-- Shift 1 -->
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <label style="font-size: 12px; font-weight: 600; color: #333; min-width: 50px;">Shift 1:</label>
                                    <input type="time" id="quickOptionsShift1Start" style="font-size: 11px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; width: 70px;" placeholder="Start">
                                    <input type="time" id="quickOptionsShift1End" style="font-size: 11px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; width: 70px;" placeholder="End">
                                </div>
                                
                                <!-- Shift 2 -->
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <label style="font-size: 12px; font-weight: 600; color: #333; min-width: 50px;">Shift 2:</label>
                                    <input type="time" id="quickOptionsShift2Start" style="font-size: 11px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; width: 70px;" placeholder="Start">
                                    <input type="time" id="quickOptionsShift2End" style="font-size: 11px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; width: 70px;" placeholder="End">
                                </div>
                                
                                <!-- Shift 3 -->
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <label style="font-size: 12px; font-weight: 600; color: #333; min-width: 50px;">Shift 3:</label>
                                    <input type="time" id="quickOptionsShift3Start" style="font-size: 11px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; width: 70px;" placeholder="Start">
                                    <input type="time" id="quickOptionsShift3End" style="font-size: 11px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; width: 70px;" placeholder="End">
                                </div>
                                
                                <!-- Apply Button -->
                                <button type="button" onclick="applyQuickOptionsSchedule()" style="background: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600;">Apply</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="comprehensiveScheduleError" class="modify-error" style="display: none;"></div>
                
                <div class="modify-modal-buttons">
                    <button type="button" class="btn-modify" id="saveComprehensiveScheduleBtn" onclick="saveComprehensiveSchedule()">Save All Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Droid Config Modal -->
<div id="droidConfigModal" class="modify-modal-overlay">
    <div class="modify-modal" style="max-width: 700px;">
        <div class="modify-modal-header">
            <h3>🤖 DROID CONFIGURATION</h3>
            <span class="modify-modal-close" onclick="closeDroidConfigModal()">&times;</span>
        </div>
        <div class="modify-modal-body">
            <div class="org-name-row">
                <span class="modify-icon-inline">🏢</span>
                <div class="org-name-large" id="droidConfigWpName"></div>
            </div>

            <form id="droidConfigForm" onsubmit="updateDroidConfig(); return false;">
                <input type="hidden" id="droidConfigWpId" value="">

                <!-- SMS Configuration -->
                <div style="background: #ffe0b2; border: 2px solid #ffcc80; border-radius: 6px; padding: 15px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: #e65100; font-size: 16px;">📱 SMS Droid Configuration</h4>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="droid_sms_active">Active</label>
                            <select id="droid_sms_active" class="form-control">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="droid_sms_phone_nr">SMS Phone Number</label>
                            <input type="text" id="droid_sms_phone_nr" class="form-control" placeholder="SMS phone number">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="width: 100%;">
                            <label for="droid_sms_link">Droid Link</label>
                            <input type="text" id="droid_sms_link" class="form-control" placeholder="MacroDroid link URL" style="width: 100%;">
                        </div>
                    </div>
                </div>

                <!-- WhatsApp Configuration -->
                <div style="background: #c8e6c9; border: 2px solid #81c784; border-radius: 6px; padding: 15px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: #2e7d32; font-size: 16px;">💬 WhatsApp Droid Configuration</h4>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="droid_whatsapp_active">Active</label>
                            <select id="droid_whatsapp_active" class="form-control">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="droid_whatsapp_phone_nr">WhatsApp Phone Number</label>
                            <input type="text" id="droid_whatsapp_phone_nr" class="form-control" placeholder="WhatsApp phone number">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="width: 100%;">
                            <label for="droid_whatsapp_link">Droid Link</label>
                            <input type="text" id="droid_whatsapp_link" class="form-control" placeholder="MacroDroid link URL" style="width: 100%;">
                        </div>
                    </div>
                </div>

                <div id="droidConfigError" class="modify-error" style="display: none; color: #dc3545; font-size: 0.9em; margin: 10px 0;"></div>

                <div class="modify-modal-buttons">
                    <button type="submit" class="btn-modify" id="confirmDroidConfigBtn">Save Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Google Calendar Management Functions
let autoRefreshInterval;

function startAutoRefresh() {
    stopAutoRefresh(); // Clear any existing interval
    autoRefreshInterval = setInterval(function() {
        const queueTab = document.getElementById('queue');
        if (queueTab && queueTab.classList.contains('show')) {
            refreshQueueMonitor();
        }
    }, 5000); // Refresh every 5 seconds
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

function loadQuickStats() {
    const statsEl = document.getElementById('quick-stats');
    if (!statsEl) return;
    
    fetch('gcal_get_stats.php')
        .then(response => response.json())
        .then(data => {
            statsEl.innerHTML = `
                <div class="row">
                    <div class="col-6">
                        <div class="text-center">
                            <h4 class="text-primary">${data.pending || 0}</h4>
                            <small>Pending</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <h4 class="text-success">${data.completed || 0}</h4>
                            <small>Completed Today</small>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-6">
                        <div class="text-center">
                            <h4 class="text-warning">${data.failed || 0}</h4>
                            <small>Failed</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <h4 class="text-info">${data.connected_specialists || 0}</h4>
                            <small>Connected</small>
                        </div>
                    </div>
                </div>
            `;
        })
        .catch(error => {
            statsEl.innerHTML = '<div class="text-danger">Error loading stats</div>';
        });
}

function refreshQueueMonitor() {
    loadQueueMonitor();
}

function loadQueueMonitor() {
    const queueEl = document.getElementById('queue-monitor');
    if (!queueEl) return;
    
    queueEl.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Loading queue...</div>';
    
    fetch('gcal_get_queue.php')
        .then(response => response.text())
        .then(html => {
            queueEl.innerHTML = html;
        })
        .catch(error => {
            queueEl.innerHTML = '<div class="alert alert-danger">Error loading queue data</div>';
        });
}

function refreshLogs() {
    loadSyncLogs();
}

function loadSyncLogs() {
    const logsEl = document.getElementById('sync-logs');
    if (!logsEl) return;
    
    logsEl.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Loading logs...</div>';
    
    fetch('gcal_get_logs.php')
        .then(response => response.text())
        .then(html => {
            logsEl.innerHTML = html;
        })
        .catch(error => {
            logsEl.innerHTML = '<div class="alert alert-danger">Error loading sync logs</div>';
        });
}

function refreshDatabaseRecords() {
    loadDatabaseRecords();
}

function loadDatabaseRecords() {
    const recordsEl = document.getElementById('database-records');
    if (!recordsEl) return;
    
    recordsEl.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Loading records...</div>';
    
    fetch('gcal_get_database_records.php')
        .then(response => response.text())
        .then(html => {
            recordsEl.innerHTML = html;
        })
        .catch(error => {
            recordsEl.innerHTML = '<div class="alert alert-danger">Error loading database records</div>';
        });
}

function loadSystemStatus() {
    const statusEl = document.getElementById('system-status');
    if (!statusEl) return;
    
    fetch('gcal_get_system_status.php')
        .then(response => response.json())
        .then(data => {
            let statusHtml = '';
            statusHtml += `<div class="mb-2"><small>Worker Script:</small><br><span class="badge bg-${data.worker_exists ? 'success' : 'danger'}">${data.worker_exists ? 'Exists' : 'Missing'}</span></div>`;
            statusHtml += `<div class="mb-2"><small>Queue Table:</small><br><span class="badge bg-${data.queue_table_exists ? 'success' : 'warning'}">${data.queue_table_exists ? 'Ready' : 'Not Created'}</span></div>`;
            statusHtml += `<div class="mb-2"><small>Credentials Table:</small><br><span class="badge bg-${data.credentials_table_exists ? 'success' : 'danger'}">${data.credentials_table_exists ? 'Ready' : 'Missing'}</span></div>`;
            
            statusEl.innerHTML = statusHtml;
        })
        .catch(error => {
            statusEl.innerHTML = '<div class="text-danger">Error checking status</div>';
        });
}

function processQueueManually() {
    if (confirm('Process all pending queue items now?')) {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        btn.disabled = true;
        
        fetch('gcal_process_queue_manual.php', {method: 'POST'})
            .then(response => response.json())
            .then(data => {
                alert(data.message || 'Queue processing completed');
                refreshQueueMonitor();
                loadQuickStats();
            })
            .catch(error => {
                alert('Error processing queue: ' + error);
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
    }
}

function viewFullQueuePage() {
    window.open('../check_live_queue.php', '_blank');
}

// Initialize Google Calendar functions when the management panel loads
function initializeGoogleCalendarPanel() {
    // Wait a bit for the content to load
    setTimeout(function() {
        loadQuickStats();
        loadQueueMonitor();
        loadSyncLogs();
        loadDatabaseRecords();
        loadSystemStatus();
        
        // Set up auto-refresh
        startAutoRefresh();
        
        // Handle auto-refresh toggle if it exists
        const autoRefreshToggle = document.getElementById('autoRefreshQueue');
        if (autoRefreshToggle) {
            autoRefreshToggle.addEventListener('change', function() {
                if (this.checked) {
                    startAutoRefresh();
                } else {
                    stopAutoRefresh();
                }
            });
        }
    }, 500);
}

// Specialist management functions
function moveSpecialist(specialistId, specialistName, organisationId) {
    if (!confirm('Are you sure you want to move ' + specialistName + ' to another branch?')) {
        return;
    }
    
    // Show move specialist modal
    const modal = document.getElementById('moveSpecialistModal');
    if (modal) {
        modal.style.display = 'block';
        document.getElementById('moveSpecialistName').textContent = specialistName;
        document.getElementById('moveSpecialistForm').setAttribute('data-specialist-id', specialistId);
        document.getElementById('moveSpecialistForm').setAttribute('data-organisation-id', organisationId);
        
        // Load available working points for this organisation
        loadAvailableWorkingPointsForMove(organisationId, specialistId);
    } else {
        console.error('Move modal not found!');
    }
}

function deleteSpecialist(specialistId, specialistName, workingPointId) {
    if (!confirm('Are you sure you want to remove ' + specialistName + ' from this working point? This action cannot be undone!')) {
        return;
    }
    
    // Show delete specialist modal
    const modal = document.getElementById('deleteSpecialistModal');
    if (modal) {
        modal.style.display = 'block';
        document.getElementById('deleteSpecialistName').textContent = specialistName;
        document.getElementById('deleteSpecialistForm').setAttribute('data-specialist-id', specialistId);
        document.getElementById('deleteSpecialistForm').setAttribute('data-working-point-id', workingPointId);
    } else {
        console.error('Delete modal not found!');
    }
}

function loadAvailableWorkingPointsForMove(organisationId, specialistId) {
    fetch('get_available_working_points_for_move.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'organisation_id=' + encodeURIComponent(organisationId) + '&specialist_id=' + encodeURIComponent(specialistId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const container = document.getElementById('moveWorkingPointsList');
            container.innerHTML = '';
            
            if (data.working_points.length === 0) {
                container.innerHTML = '<div class="no-options">No working points available in this organisation.</div>';
                return;
            }
            
            // Create dropdown
            const select = document.createElement('select');
            select.name = 'target_wp_id';
            select.id = 'target_wp_id';
            select.className = 'form-control';
            select.required = true;
            
            // Add default option
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Select a working point...';
            select.appendChild(defaultOption);
            
            // Add working point options
            data.working_points.forEach(wp => {
                const option = document.createElement('option');
                option.value = wp.unic_id;
                option.textContent = `${wp.name_of_the_place} - ${wp.address}`;
                if (wp.is_current_assignment == 1) {
                    option.textContent += ' (Current)';
                    option.selected = true;
                }
                select.appendChild(option);
            });
            
            container.appendChild(select);
        } else {
            document.getElementById('moveSpecialistError').textContent = data.message || 'Failed to load working points.';
            document.getElementById('moveSpecialistError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error loading working points:', error);
        document.getElementById('moveSpecialistError').textContent = 'An error occurred while loading working points.';
        document.getElementById('moveSpecialistError').style.display = 'block';
    });
}

function confirmMoveSpecialist() {
    const specialistId = document.getElementById('moveSpecialistForm').getAttribute('data-specialist-id');
    const targetWpSelect = document.getElementById('target_wp_id');
    
    if (!targetWpSelect || !targetWpSelect.value) {
        document.getElementById('moveSpecialistError').textContent = 'Please select a target working point.';
        document.getElementById('moveSpecialistError').style.display = 'block';
        return;
    }
    
    const btn = document.getElementById('confirmMoveSpecialistBtn');
    const originalText = btn.textContent;
    btn.textContent = 'Moving...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('specialist_id', specialistId);
    formData.append('target_wp_id', targetWpSelect.value);
    
    fetch('move_specialist.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeMoveSpecialistModal();
            if(typeof loadBottomPanel === 'function') loadBottomPanel('list_all_org');
        } else {
            document.getElementById('moveSpecialistError').textContent = data.message || 'Failed to move specialist.';
            document.getElementById('moveSpecialistError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error moving specialist:', error);
        document.getElementById('moveSpecialistError').textContent = 'An error occurred while moving the specialist.';
        document.getElementById('moveSpecialistError').style.display = 'block';
    })
    .finally(() => {
        btn.textContent = originalText;
        btn.disabled = false;
    });
}

function closeMoveSpecialistModal() {
    document.getElementById('moveSpecialistModal').style.display = 'none';
    document.getElementById('moveSpecialistError').style.display = 'none';
    document.getElementById('moveSpecialistForm').reset();
}

function confirmDeleteSpecialist() {
    const specialistId = document.getElementById('deleteSpecialistForm').getAttribute('data-specialist-id');
    const workingPointId = document.getElementById('deleteSpecialistForm').getAttribute('data-working-point-id');
    
    const btn = document.getElementById('confirmDeleteSpecialistBtn');
    const originalText = btn.textContent;
    btn.textContent = 'Unassigning...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('wp_id', workingPointId);
    formData.append('specialist_id', specialistId);
    
    fetch('remove_specialist_working_point.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteSpecialistModal();
            if(typeof loadBottomPanel === 'function') loadBottomPanel('list_all_org');
        } else {
            document.getElementById('deleteSpecialistError').textContent = data.message || 'Failed to unassign specialist from working point.';
            document.getElementById('deleteSpecialistError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error unassigning specialist:', error);
        document.getElementById('deleteSpecialistError').textContent = 'An error occurred while unassigning the specialist.';
        document.getElementById('deleteSpecialistError').style.display = 'block';
    })
    .finally(() => {
        btn.textContent = originalText;
        btn.disabled = false;
    });
}

function closeDeleteSpecialistModal() {
    document.getElementById('deleteSpecialistModal').style.display = 'none';
    document.getElementById('deleteSpecialistError').style.display = 'none';
    document.getElementById('deleteSpecialistForm').reset();
}

// Duplicate editShift function removed
// Duplicate closeShiftEditorModal function removed
// Duplicate saveShift function removed
// Duplicate clearShift function removed
// Duplicate showComprehensiveScheduleEditor function removed
// Duplicate closeComprehensiveScheduleModal function removed
// Duplicate loadComprehensiveScheduleData function removed
// Duplicate populateScheduleEditorTable function removed

// Duplicate applyBulkSchedule function removed
// Duplicate showNotification function removed
// Duplicate clearDaySchedule function removed
// Duplicate saveComprehensiveSchedule function removed
    </script>

    <!-- Add Specialist Modal -->
    <div id="addSpecialistModal" class="modify-modal-overlay">
        <div class="modify-modal">
            <div class="modify-modal-header">
                <h3>👨‍⚕️ ADD NEW SPECIALIST</h3>
                <span class="modify-modal-close" onclick="closeAddSpecialistModal()">&times;</span>
            </div>
            <div class="modify-modal-body">
                <div class="org-name-row">
                    <span class="modify-icon-inline">👨‍⚕️</span>
                    <div class="org-name-large">New Specialist Registration</div>
                </div>
                
                <form id="addSpecialistForm">
                    <input type="hidden" id="workpointId" name="workpoint_id">
                    <input type="hidden" id="organisationId" name="organisation_id">
                    
                    <!-- Specialist Details -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="specialistName">Name *</label>
                            <input type="text" class="form-control" id="specialistName" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="specialistSpeciality">Speciality *</label>
                            <input type="text" class="form-control" id="specialistSpeciality" name="speciality" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="specialistEmail">Email *</label>
                            <input type="email" class="form-control" id="specialistEmail" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="specialistPhone">Phone Number</label>
                            <input type="text" class="form-control" id="specialistPhone" name="phone_nr">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="specialistUser">Username *</label>
                            <input type="text" class="form-control" id="specialistUser" name="user" required>
                        </div>
                        <div class="form-group">
                            <label for="specialistPassword">Password *</label>
                            <input type="password" class="form-control" id="specialistPassword" name="password" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="emailScheduleHour">Email Schedule Hour *</label>
                            <input type="number" class="form-control" id="emailScheduleHour" name="h_of_email_schedule" min="0" max="23" value="9" required>
                        </div>
                        <div class="form-group">
                            <label for="emailScheduleMinute">Email Schedule Minute *</label>
                            <input type="number" class="form-control" id="emailScheduleMinute" name="m_of_email_schedule" min="0" max="59" value="0" required>
                        </div>
                    </div>
                    
                    <!-- Working Point Assignment -->
                    <div class="form-group">
                        <label id="workpointLabel">Assign to Working Point *</label>
                        <select class="form-control" id="workpointSelect" name="working_points[]" required>
                            <option value="">Loading working points...</option>
                        </select>
                        <div id="workpointInfo" style="display: none; margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                            <strong id="workpointName"></strong><br>
                            <small id="workpointAddress"></small>
                        </div>
                    </div>
                    
                    <!-- Individual Day Editor -->
                    <div class="individual-edit-section">
                        <h4>📋 Working Schedule</h4>
                        <div class="schedule-editor-table-container" style="border: 2px solid #dc3545; padding: 10px; background: #f9f9f9;">
                            <table class="schedule-editor-table" style="width: 100%; border-collapse: collapse; font-size: 12px; border: 2px solid #dc3545;">
                                <thead>
                                    <tr>
                                        <th style="border: 1px solid #ddd; padding: 6px 8px; background: #f8f9fa; font-weight: 600;">Day</th>
                                        <th colspan="3" style="border: 1px solid #ddd; padding: 6px 8px; background: #f8f9fa; font-weight: 600;">Shift 1</th>
                                        <th colspan="3" style="border: 1px solid #ddd; padding: 6px 8px; background: #f8f9fa; font-weight: 600;">Shift 2</th>
                                        <th colspan="3" style="border: 1px solid #ddd; padding: 6px 8px; background: #f8f9fa; font-weight: 600;">Shift 3</th>
                                    </tr>
                                    <tr>
                                        <th style="border: 1px solid #ddd; padding: 6px 8px; background: #f8f9fa; font-weight: 600;"></th>
                                        <th style="border: 1px solid #ddd; padding: 6px 8px; background: #f8f9fa; font-weight: 600;">Start</th>
                                        <th style="border: 1px solid #ddd; padding: 6px 8px; background: #f8f9fa; font-weight: 600;">End</th>
                                        <th style="border: 1px solid #ddd; padding: 6px 8px; background: #f8f9fa; font-weight: 600;"></th>
                                        <th style="border: 1px solid #ddd; padding: 6px 8px; background: #f8f9fa; font-weight: 600;">Start</th>
                                        <th style="border: 1px solid #ddd; padding: 6px 8px; background: #f8f9fa; font-weight: 600;">End</th>
                                        <th style="border: 1px solid #ddd; padding: 6px 8px; background: #f8f9fa; font-weight: 600;"></th>
                                        <th style="border: 1px solid #ddd; padding: 6px 8px; background: #f8f9fa; font-weight: 600;">Start</th>
                                        <th style="border: 1px solid #ddd; padding: 6px 8px; background: #f8f9fa; font-weight: 600;">End</th>
                                        <th style="border: 1px solid #ddd; padding: 6px 8px; background: #f8f9fa; font-weight: 600;"></th>
                                    </tr>
                                </thead>
                                <tbody id="comprehensiveScheduleTableBody" style="background: white;">
                                    <!-- Template rows - will be replaced by JavaScript -->
                                    <tr style="border: 1px solid #ddd; background: white;">
                                        <td class="day-name" style="border: 1px solid #ddd; padding: 6px 8px; font-weight: 600; color: #333; text-align: left;">Monday</td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-start-time" name="shift1_start_monday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-end-time" name="shift1_end_monday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 1)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-start-time" name="shift2_start_monday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-end-time" name="shift2_end_monday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 2)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-start-time" name="shift3_start_monday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-end-time" name="shift3_end_monday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 3)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                    </tr>
                                    <tr style="border: 1px solid #ddd; background: white;">
                                        <td class="day-name" style="border: 1px solid #ddd; padding: 6px 8px; font-weight: 600; color: #333; text-align: left;">Tuesday</td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-start-time" name="shift1_start_tuesday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-end-time" name="shift1_end_tuesday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 1)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-start-time" name="shift2_start_tuesday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-end-time" name="shift2_end_tuesday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 2)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-start-time" name="shift3_start_tuesday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-end-time" name="shift3_end_tuesday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 3)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                    </tr>
                                    <tr style="border: 1px solid #ddd; background: white;">
                                        <td class="day-name" style="border: 1px solid #ddd; padding: 6px 8px; font-weight: 600; color: #333; text-align: left;">Wednesday</td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-start-time" name="shift1_start_wednesday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-end-time" name="shift1_end_wednesday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 1)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-start-time" name="shift2_start_wednesday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-end-time" name="shift2_end_wednesday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 2)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-start-time" name="shift3_start_wednesday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-end-time" name="shift3_end_wednesday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 3)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                    </tr>
                                    <tr style="border: 1px solid #ddd; background: white;">
                                        <td class="day-name" style="border: 1px solid #ddd; padding: 6px 8px; font-weight: 600; color: #333; text-align: left;">Thursday</td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-start-time" name="shift1_start_thursday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-end-time" name="shift1_end_thursday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 1)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-start-time" name="shift2_start_thursday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-end-time" name="shift2_end_thursday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 2)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-start-time" name="shift3_start_thursday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-end-time" name="shift3_end_thursday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 3)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                    </tr>
                                    <tr style="border: 1px solid #ddd; background: white;">
                                        <td class="day-name" style="border: 1px solid #ddd; padding: 6px 8px; font-weight: 600; color: #333; text-align: left;">Friday</td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-start-time" name="shift1_start_friday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-end-time" name="shift1_end_friday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 1)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-start-time" name="shift2_start_friday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-end-time" name="shift2_end_friday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 2)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-start-time" name="shift3_start_friday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-end-time" name="shift3_end_friday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 3)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                    </tr>
                                    <tr style="border: 1px solid #ddd; background: white;">
                                        <td class="day-name" style="border: 1px solid #ddd; padding: 6px 8px; font-weight: 600; color: #333; text-align: left;">Saturday</td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-start-time" name="shift1_start_saturday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-end-time" name="shift1_end_saturday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 1)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-start-time" name="shift2_start_saturday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-end-time" name="shift2_end_saturday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 2)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-start-time" name="shift3_start_saturday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-end-time" name="shift3_end_saturday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 3)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                    </tr>
                                    <tr style="border: 1px solid #ddd; background: white;">
                                        <td class="day-name" style="border: 1px solid #ddd; padding: 6px 8px; font-weight: 600; color: #333; text-align: left;">Sunday</td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-start-time" name="shift1_start_sunday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift1-end-time" name="shift1_end_sunday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 1)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-start-time" name="shift2_start_sunday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift2-end-time" name="shift2_end_sunday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 2)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-start-time" name="shift3_start_sunday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><input type="time" class="shift3-end-time" name="shift3_end_sunday" value="" style="width: 80px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px;"></td>
                                        <td style="border: 1px solid #ddd; padding: 6px 8px; text-align: center;"><button type="button" class="btn-clear-shift" onclick="clearShift(this, 3)" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">Clear</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Quick Options Section -->
                    <div class="quick-options-section">
                        <h4>⚡ Quick Options</h4>
                        <div class="quick-options-compact">
                            <div class="quick-options-row">
                                <div class="quick-option-group">
                                    <label>Shift:</label>
                                    <select id="quickOptionsShiftSelect" class="form-control">
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                    </select>
                                </div>
                                <div class="quick-option-group">
                                    <label>Mon-Fri:</label>
                                    <div class="time-inputs">
                                        <input type="time" id="mondayToFridayStart" class="form-control" placeholder="Start">
                                        <input type="time" id="mondayToFridayEnd" class="form-control" placeholder="End">
                                        <button type="button" class="btn-apply-bulk" onclick="applyBulkSchedule('mondayToFriday')">Apply</button>
                                    </div>
                                </div>
                                <div class="quick-option-group">
                                    <label>Saturday:</label>
                                    <div class="time-inputs">
                                        <input type="time" id="saturdayStart" class="form-control" placeholder="Start">
                                        <input type="time" id="saturdayEnd" class="form-control" placeholder="End">
                                        <button type="button" class="btn-apply-bulk" onclick="applyBulkSchedule('saturday')">Apply</button>
                                    </div>
                                </div>
                                <div class="quick-option-group">
                                    <label>Sunday:</label>
                                    <div class="time-inputs">
                                        <input type="time" id="sundayStart" class="form-control" placeholder="Start">
                                        <input type="time" id="sundayEnd" class="form-control" placeholder="End">
                                        <button type="button" class="btn-apply-bulk" onclick="applyBulkSchedule('sunday')">Apply</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="addSpecialistError" class="modify-error" style="display: none;"></div>
                    
                    <div class="modify-modal-buttons">
                        <button type="button" class="btn-modify" onclick="submitAddSpecialist()">Add Specialist</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add New Organisation Modal -->
    <div id="addNewOrgModal" class="modify-modal-overlay">
        <div class="modify-modal" style="width: 75%; max-width: 900px;">
            <div class="modify-modal-header">
                <h3>🏢 ADD NEW ORGANISATION</h3>
                <span class="modify-modal-close" onclick="closeAddNewOrgModal()">&times;</span>
            </div>
            <div class="modify-modal-body">
                <div class="org-name-row">
                    <span class="modify-icon-inline">🏢</span>
                    <div class="org-name-large">New Organisation Registration</div>
                </div>
                
                <form id="addNewOrgForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_alias_name">Alias Name *</label>
                            <input type="text" id="new_alias_name" name="alias_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="new_oficial_company_name">Official Company Name *</label>
                            <input type="text" id="new_oficial_company_name" name="oficial_company_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_contact_name">Contact Name</label>
                            <input type="text" id="new_contact_name" name="contact_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="new_position">Position</label>
                            <input type="text" id="new_position" name="position" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_email_address">Email</label>
                            <input type="email" id="new_email_address" name="email_address" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="new_www_address">Website</label>
                            <input type="text" id="new_www_address" name="www_address" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_company_head_office_address">Head Office Address</label>
                            <input type="text" id="new_company_head_office_address" name="company_head_office_address" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="new_company_phone_nr">Company Phone</label>
                            <input type="text" id="new_company_phone_nr" name="company_phone_nr" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_country">Country</label>
                            <input type="text" id="new_country" name="country" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="new_owner_name">Owner Name</label>
                            <input type="text" id="new_owner_name" name="owner_name" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_owner_phone_nr">Owner Phone</label>
                            <input type="text" id="new_owner_phone_nr" name="owner_phone_nr" class="form-control">
                        </div>
                        <div class="form-group" style="display: flex; flex-direction: row; gap: 8px; align-items: flex-end; margin-bottom: 0;">
                            <div style="flex: 1; display: flex; flex-direction: column;">
                                <label for="new_user" style="font-size: 13px;">Login</label>
                                <input type="text" id="new_user" name="user" class="form-control" style="font-size: 13px; padding: 4px 6px; background: #e6f2ff; border: 1px solid #b3d8ff;">
                            </div>
                            <div style="flex: 1; display: flex; flex-direction: column;">
                                <label for="new_pasword" style="font-size: 13px;">Password</label>
                                <input type="text" id="new_pasword" name="pasword" class="form-control" style="font-size: 13px; padding: 4px 6px; background: #e6f2ff; border: 1px solid #b3d8ff;">
                            </div>
                        </div>
                    </div>
                    
                    <div id="addNewOrgError" class="modify-error" style="display: none; color: #dc3545; font-size: 0.9em; margin: 10px 0;"></div>
                    
                    <div class="modify-modal-buttons" style="display: flex; justify-content: flex-end; align-items: center; padding-right: 20px;">
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="btn-cancel" onclick="closeAddNewOrgModal()">Cancel</button>
                            <button type="submit" class="btn-modify" id="confirmAddNewOrgBtn">Add Organisation</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Orphaned Specialist Modal -->
    <div id="deleteOrphanedSpecialistModal" class="delete-modal-overlay">
        <div class="delete-modal">
            <div class="delete-modal-header">
                <h3>❌ DELETE ORPHANED SPECIALIST</h3>
            </div>
            <div class="delete-modal-body">
                <div class="org-name-row">
                    <span class="delete-icon-inline">❌</span>
                    <div class="org-name-large" id="deleteOrphanedSpecialistName"></div>
                </div>
                
                <div class="warning-text">
                    ⚠️ WARNING: This action will permanently delete this specialist and ALL their data!
                </div>
                
                <div class="dependencies-list">
                    <strong>All of the following will be PERMANENTLY REMOVED:</strong>
                    <ul>
                        <li>• The specialist's profile and all personal information</li>
                        <li>• All bookings associated with this specialist</li>
                        <li>• All login credentials and access rights</li>
                    </ul>
                </div>
                
                <div class="warning-text">
                    <span class="blinking-warning">❌ <span class="underlined">This action cannot be undone!</span></span>
                </div>
                
                <br><br>
                
                <form id="deleteOrphanedSpecialistForm">
                    <div class="password-confirmation">
                        <div class="password-button-row">
                            <input type="password" id="deleteOrphanedSpecialistPassword" class="password-input" placeholder="password to confirm" autocomplete="current-password">
                            <button class="btn-delete" id="confirmDeleteOrphanedSpecialistBtn" onclick="confirmDeleteOrphanedSpecialist()">Delete Specialist</button>
                        </div>
                        <div id="deleteOrphanedSpecialistError" class="password-error" style="display: none; color: #dc3545; font-size: 0.9em; margin-top: 5px;"></div>
                    </div>
                    
                    <div class="delete-modal-buttons">
                        <button class="btn-cancel" onclick="closeDeleteOrphanedSpecialistModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Add Specialist Modal Functions
        function openAddSpecialistModal(workpointId, organisationId) {
            const modal = document.getElementById('addSpecialistModal');
            if (!modal) {
                console.error('Modal not found!');
                return;
            }
            
            modal.style.display = 'flex';
            document.getElementById('workpointId').value = workpointId;
            document.getElementById('organisationId').value = organisationId;
            
            // Set workpoint info if provided
            if (workpointId) {
                document.getElementById('workpointInfo').style.display = 'block';
                document.getElementById('workpointSelect').style.display = 'none';
                document.getElementById('workpointLabel').textContent = 'Assignment for Working Point:';
                
                // Get workpoint details
                fetch('get_working_point_details.php?workpoint_id=' + workpointId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('workpointName').textContent = data.workpoint.name_of_the_place;
                            document.getElementById('workpointAddress').textContent = data.workpoint.address;
                        }
                    })
                    .catch(error => {
                        // Handle error silently
                    });
            } else {
                document.getElementById('workpointInfo').style.display = 'none';
                document.getElementById('workpointSelect').style.display = 'block';
                document.getElementById('workpointLabel').textContent = 'Assign to Working Point *';
                
                // Load working points for this organisation
                loadWorkingPointsForOrganisation(organisationId);
            }
            
            // Load schedule editor
            loadScheduleEditor();
        }
        
        function closeAddSpecialistModal() {
            document.getElementById('addSpecialistModal').style.display = 'none';
            document.getElementById('addSpecialistForm').reset();
            document.getElementById('comprehensiveScheduleTableBody').innerHTML = '';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addSpecialistModal');
            if (event.target === modal) {
                closeAddSpecialistModal();
            }
        }
        
        // Duplicate loadWorkingPointsForOrganisation function removed
        
        // Duplicate loadScheduleEditor function removed
        
        function clearShift(button, shiftNum) {
            const row = button.closest('tr');
            const startInput = row.querySelector(`.shift${shiftNum}-start-time`);
            const endInput = row.querySelector(`.shift${shiftNum}-end-time`);
            startInput.value = '';
            endInput.value = '';
        }
        
        function applyQuickOptionsSchedule() {
            const selectedDay = document.getElementById('quickOptionsDaySelect').value;
            const shift1Start = document.getElementById('quickOptionsShift1Start').value;
            const shift1End = document.getElementById('quickOptionsShift1End').value;
            const shift2Start = document.getElementById('quickOptionsShift2Start').value;
            const shift2End = document.getElementById('quickOptionsShift2End').value;
            const shift3Start = document.getElementById('quickOptionsShift3Start').value;
            const shift3End = document.getElementById('quickOptionsShift3End').value;
            
            let days = [];
            switch(selectedDay) {
                case 'mondayToFriday':
                    days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
                    break;
                case 'saturday':
                    days = ['saturday'];
                    break;
                case 'sunday':
                    days = ['sunday'];
                    break;
            }
            
            // Get the table body
            const tableBody = document.getElementById('comprehensiveScheduleTableBody');
            if (!tableBody) {
                console.error('Comprehensive schedule table body not found');
                return;
            }
            
            // Apply shift 1 times
            if (shift1Start && shift1End) {
                days.forEach(day => {
                    const startInput = tableBody.querySelector(`input[name="shift1_start_${day}"]`);
                    const endInput = tableBody.querySelector(`input[name="shift1_end_${day}"]`);
                    if (startInput && endInput) {
                        startInput.value = shift1Start;
                        endInput.value = shift1End;
                    }
                });
            }
            
            // Apply shift 2 times
            if (shift2Start && shift2End) {
                days.forEach(day => {
                    const startInput = tableBody.querySelector(`input[name="shift2_start_${day}"]`);
                    const endInput = tableBody.querySelector(`input[name="shift2_end_${day}"]`);
                    if (startInput && endInput) {
                        startInput.value = shift2Start;
                        endInput.value = shift2End;
                    }
                });
            }
            
            // Apply shift 3 times
            if (shift3Start && shift3End) {
                days.forEach(day => {
                    const startInput = tableBody.querySelector(`input[name="shift3_start_${day}"]`);
                    const endInput = tableBody.querySelector(`input[name="shift3_end_${day}"]`);
                    if (startInput && endInput) {
                        startInput.value = shift3Start;
                        endInput.value = shift3End;
                    }
                });
            }
            
            // Show success message
            showNotification('Schedule applied successfully!', 'success');
        }
        
        function applyBulkSchedule(type) {
            let startTime, endTime, days;
            
            switch(type) {
                case 'mondayToFriday':
                    startTime = document.getElementById('mondayToFridayStart').value;
                    endTime = document.getElementById('mondayToFridayEnd').value;
                    days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
                    break;
                case 'saturday':
                    startTime = document.getElementById('saturdayStart').value;
                    endTime = document.getElementById('saturdayEnd').value;
                    days = ['saturday'];
                    break;
                case 'sunday':
                    startTime = document.getElementById('sundayStart').value;
                    endTime = document.getElementById('sundayEnd').value;
                    days = ['sunday'];
                    break;
            }
            
            if (!startTime || !endTime) {
                alert('Please enter both start and end times.');
                return;
            }
            
            const shiftNum = document.getElementById('quickOptionsShiftSelect').value;
            days.forEach(day => {
                const row = document.querySelector(`tr:has(input[name="shift${shiftNum}_start_${day}"])`);
                if (row) {
                    const startInput = row.querySelector(`.shift${shiftNum}-start-time`);
                    const endInput = row.querySelector(`.shift${shiftNum}-end-time`);
                    startInput.value = startTime;
                    endInput.value = endTime;
                }
            });
        }
        
        function submitAddSpecialist() {
            const formData = new FormData(document.getElementById('addSpecialistForm'));
            
            // Add workpoint_id to working_points array if it's provided
            const workpointId = document.getElementById('workpointId').value;
            if (workpointId) {
                formData.append('working_points[]', workpointId);
            }
            
            // Add schedule data
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            let scheduleDataCount = 0;
            days.forEach(day => {
                for (let shift = 1; shift <= 3; shift++) {
                    const startInput = document.querySelector(`input[name="shift${shift}_start_${day}"]`);
                    const endInput = document.querySelector(`input[name="shift${shift}_end_${day}"]`);
                    if (startInput && endInput) {
                        formData.append(`schedule[${day}][shift${shift}_start]`, startInput.value || '');
                        formData.append(`schedule[${day}][shift${shift}_end]`, endInput.value || '');
                        scheduleDataCount++;
                    }
                }
            });
            
            fetch('add_specialist_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Specialist added successfully!', 'success');
                    closeAddSpecialistModal();
                    // Reload the bottom panel to show the new specialist
                    loadBottomPanel('list_all_org');
                } else {
                    showNotification('Error: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred while adding the specialist.', 'error');
            });
        }
        
        // Orphaned Specialist Functions
        // Duplicate assignOrphanedSpecialist function removed
        
        function modifyOrphanedSpecialist(specialistId, specialistName) {
            // Open the modify specialist modal with the orphaned specialist's data
            modifySpecialist(specialistId, specialistName);
            
            // Add a note about the orphaned specialist
            const modalBody = document.querySelector('#modifySpecialistModal .modify-modal-body');
            if (modalBody) {
                // Remove any existing orphaned specialist note
                const existingNote = modalBody.querySelector('.orphaned-specialist-note');
                if (existingNote) {
                    existingNote.remove();
                }
                
                // Add a new note
                const infoDiv = document.createElement('div');
                infoDiv.className = 'orphaned-specialist-note';
                infoDiv.style.cssText = 'background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 10px; margin-bottom: 15px; color: #856404; font-size: 13px;';
                infoDiv.innerHTML = `<strong>⚠️ Orphaned Specialist:</strong> ${specialistName} - This specialist is not assigned to any working point. Use the "Move Specialist" button to assign them to a working point.`;
                modalBody.insertBefore(infoDiv, modalBody.firstChild);
            }
        }
        
        function deleteOrphanedSpecialist(specialistId, specialistName) {
            // Store the specialist ID and name for the modal
            document.getElementById('deleteOrphanedSpecialistModal').setAttribute('data-specialist-id', specialistId);
            document.getElementById('deleteOrphanedSpecialistName').textContent = specialistName;
            document.getElementById('deleteOrphanedSpecialistPassword').value = '';
            document.getElementById('deleteOrphanedSpecialistError').style.display = 'none';
            
            // Show the modal
            document.getElementById('deleteOrphanedSpecialistModal').style.display = 'block';
            document.getElementById('deleteOrphanedSpecialistPassword').focus();
        }
        
        function closeDeleteOrphanedSpecialistModal() {
            document.getElementById('deleteOrphanedSpecialistModal').style.display = 'none';
        }
        
        function confirmDeleteOrphanedSpecialist() {
            const specialistId = document.getElementById('deleteOrphanedSpecialistModal').getAttribute('data-specialist-id');
            const password = document.getElementById('deleteOrphanedSpecialistPassword').value;
            
            if (!password) {
                document.getElementById('deleteOrphanedSpecialistError').textContent = 'Please enter your password to confirm deletion.';
                document.getElementById('deleteOrphanedSpecialistError').style.display = 'block';
                return;
            }
            
            const btn = document.getElementById('confirmDeleteOrphanedSpecialistBtn');
            const originalText = btn.textContent;
            btn.textContent = 'Deleting...';
            btn.disabled = true;
            
            const formData = new FormData();
            formData.append('specialist_id', specialistId);
            formData.append('password', password);
            formData.append('action', 'delete_specialist');
            
            fetch('delete_specialist.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeDeleteOrphanedSpecialistModal();
                    loadBottomPanel('list_all_org');
                    showNotification('Specialist deleted successfully', 'success');
                } else {
                    document.getElementById('deleteOrphanedSpecialistError').textContent = data.message || 'Failed to delete specialist.';
                    document.getElementById('deleteOrphanedSpecialistError').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('deleteOrphanedSpecialistError').textContent = 'An error occurred while deleting the specialist.';
                document.getElementById('deleteOrphanedSpecialistError').style.display = 'block';
            })
            .finally(() => {
                btn.textContent = originalText;
                btn.disabled = false;
            });
        }
        
        // Add New Organisation Modal
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modify-modal-overlay')) {
                e.target.style.display = 'none';
            }
        });
        
        // Add New Organisation Modal Functions
        function openAddNewOrgModal() {
            document.getElementById('addNewOrgModal').style.display = 'block';
            // Clear form fields
            document.getElementById('addNewOrgForm').reset();
            document.getElementById('addNewOrgError').style.display = 'none';
        }
        
        function closeAddNewOrgModal() {
            document.getElementById('addNewOrgModal').style.display = 'none';
        }
        
        function submitAddNewOrg() {
            const formData = new FormData(document.getElementById('addNewOrgForm'));
            
            const btn = document.getElementById('confirmAddNewOrgBtn');
            const originalText = btn.textContent;
            btn.textContent = 'Adding...';
            btn.disabled = true;
            
            fetch('process_add_org.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeAddNewOrgModal();
                    loadBottomPanel('list_all_org');
                    showNotification('Organisation added successfully!', 'success');
                } else {
                    document.getElementById('addNewOrgError').textContent = data.error || 'Failed to add organisation.';
                    document.getElementById('addNewOrgError').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('addNewOrgError').textContent = 'An error occurred while adding the organisation.';
                document.getElementById('addNewOrgError').style.display = 'block';
            })
            .finally(() => {
                btn.textContent = originalText;
                btn.disabled = false;
            });
        }
        
        // Add form submission handler
        document.getElementById('addNewOrgForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitAddNewOrg();
        });
    </script>
    
    <!-- Country Autocomplete -->
    <script src="../includes/country_autocomplete.js"></script>
    <script>
        // Initialize country autocomplete when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize for add working point modal
            createCountryAutocomplete('add_wp_country_display', 'add_wp_country');
            
            // Initialize for modify working point modal
            createCountryAutocomplete('wp_country_display', 'wp_country');
        });
    </script>

</body>
</html>

