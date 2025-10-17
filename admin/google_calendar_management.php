<?php
// Suppress any warnings for clean display
error_reporting(E_ERROR | E_PARSE);

// Don't start session - it's already started by the parent dashboard
// Don't include session.php - it's already included
// Don't include db.php - it's already included
// This file is included by load_bottom_panel.php which already has all necessary includes
?>

<!-- Bootstrap CSS for proper styling -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<style>
.gcal-management-container {
    margin: 0;
    padding: 20px;
    background: #fff;
}
.nav-tabs .nav-link {
    color: #495057;
}
.nav-tabs .nav-link.active {
    color: #495057;
    background-color: #fff;
    border-color: #dee2e6 #dee2e6 #fff;
}
.card {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}
.card-header {
    padding: 0.75rem 1.25rem;
    margin-bottom: 0;
    background-color: #4285f4;
    border-bottom: 1px solid #dee2e6;
}
.alert {
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.375rem;
}
.alert-warning {
    color: #856404;
    background-color: #fff3cd;
    border-color: #ffeaa7;
}
.alert-info {
    color: #0c5460;
    background-color: #d1ecf1;
    border-color: #bee5eb;
}
.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}
.table-responsive {
    overflow-x: auto;
}
.badge {
    display: inline-block;
    padding: 0.25em 0.4em;
    font-size: 0.75em;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.375rem;
}
.bg-warning { background-color: #ffc107 !important; }
.bg-success { background-color: #198754 !important; }
.bg-danger { background-color: #dc3545 !important; }
.bg-info { background-color: #0dcaf0 !important; }
.bg-secondary { background-color: #6c757d !important; }
.text-white { color: #fff !important; }
</style>

<div class="gcal-management-container">
<div class="card">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">
            <i class="fab fa-google"></i> Google Calendar Sync Management
            <span style="font-weight: normal; font-size: 0.7em; color: #666;">
                (google_calendar_management.php)
            </span>
        </h4>
    </div>
    <div class="card-body">
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="gcalTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="overview-tab" 
                    onclick="document.querySelectorAll('.nav-link').forEach(el=>el.classList.remove('active')); this.classList.add('active'); document.querySelectorAll('.tab-pane').forEach(el=>el.classList.remove('show','active')); document.getElementById('overview').classList.add('show','active');">
                    <i class="fas fa-tachometer-alt"></i> Overview
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="queue-tab" 
                    onclick="document.querySelectorAll('.nav-link').forEach(el=>el.classList.remove('active')); this.classList.add('active'); document.querySelectorAll('.tab-pane').forEach(el=>el.classList.remove('show','active')); document.getElementById('queue').classList.add('show','active');">
                    <i class="fas fa-list"></i> Live Queue Monitor
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="logs-tab" 
                    onclick="document.querySelectorAll('.nav-link').forEach(el=>el.classList.remove('active')); this.classList.add('active'); document.querySelectorAll('.tab-pane').forEach(el=>el.classList.remove('show','active')); document.getElementById('logs').classList.add('show','active');">
                    <i class="fas fa-file-alt"></i> Sync Logs
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="database-tab" 
                    onclick="document.querySelectorAll('.nav-link').forEach(el=>el.classList.remove('active')); this.classList.add('active'); document.querySelectorAll('.tab-pane').forEach(el=>el.classList.remove('show','active')); document.getElementById('database').classList.add('show','active');">
                    <i class="fas fa-database"></i> Database Records
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="settings-tab" 
                    onclick="document.querySelectorAll('.nav-link').forEach(el=>el.classList.remove('active')); this.classList.add('active'); document.querySelectorAll('.tab-pane').forEach(el=>el.classList.remove('show','active')); document.getElementById('settings').classList.add('show','active');">
                    <i class="fas fa-cog"></i> Settings
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="gcalTabContent">
            
            <!-- Overview Tab -->
            <div class="tab-pane fade show active" id="overview" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-info-circle text-info"></i> How Google Calendar Sync Works</h5>
                                <p class="card-text">
                                    <strong>Current System:</strong> Bookings are queued and processed by a background worker.<br>
                                    <strong>Sync Delay:</strong> Configurable (currently set for optimal performance)<br>
                                    <strong>Reliability:</strong> Failed syncs are automatically retried
                                </p>
                                <div class="alert alert-warning">
                                    <strong>Important:</strong> The current 2-minute delay may cause booking conflicts. 
                                    We're working on immediate sync solutions.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-chart-line text-success"></i> Quick Stats</h5>
                                <div id="quick-stats">Loading statistics...</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-tools text-primary"></i> Quick Actions</h5>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-outline-primary" onclick="document.getElementById('queue-monitor').innerHTML='<p>Loading queue data...</p>'; fetch('google_calendar_queue_data.php?action=queue_monitor').then(r=>r.text()).then(data=>document.getElementById('queue-monitor').innerHTML=data).catch(err=>document.getElementById('queue-monitor').innerHTML='<p style=\'color:red\'>Error loading queue: '+err+'</p>')">
                                        <i class="fas fa-sync"></i> Refresh Queue
                                    </button>
                                    <button class="btn btn-outline-warning" onclick="if(confirm('Process queue manually now?')){fetch('google_calendar_queue_data.php?action=process_manually').then(r=>r.json()).then(data=>alert(data.message||'Queue processed')).catch(err=>alert('Error: '+err))}">
                                        <i class="fas fa-play"></i> Process Queue Now
                                    </button>
                                    <button class="btn btn-outline-info" onclick="window.open('google_calendar_queue_monitor.php','_blank')">
                                        <i class="fas fa-external-link-alt"></i> Full Queue Monitor
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Queue Monitor Tab -->
            <div class="tab-pane fade" id="queue" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="fas fa-list"></i> Live Sync Queue</h5>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary" onclick="document.getElementById('queue-monitor').innerHTML='<p>Loading queue data...</p>'; fetch('google_calendar_queue_data.php?action=queue_monitor').then(r=>r.text()).then(data=>document.getElementById('queue-monitor').innerHTML=data).catch(err=>document.getElementById('queue-monitor').innerHTML='<p style=\'color:red\'>Error loading queue: '+err+'</p>')">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <div class="form-check form-switch ms-2">
                            <input class="form-check-input" type="checkbox" id="autoRefreshQueue" checked>
                            <label class="form-check-label" for="autoRefreshQueue">Auto-refresh</label>
                        </div>
                    </div>
                </div>
                <div id="queue-monitor">Loading queue data...</div>
            </div>

            <!-- Sync Logs Tab -->
            <div class="tab-pane fade" id="logs" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="fas fa-file-alt"></i> Google Calendar Sync Logs</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="document.getElementById('sync-logs').innerHTML='<p>Loading sync logs...</p>'; fetch('google_calendar_queue_data.php?action=sync_logs').then(r=>r.text()).then(data=>document.getElementById('sync-logs').innerHTML=data).catch(err=>document.getElementById('sync-logs').innerHTML='<p style=\'color:red\'>Error loading logs: '+err+'</p>')">
                        <i class="fas fa-sync"></i> Refresh Logs
                    </button>
                </div>
                <div id="sync-logs">Loading sync logs...</div>
            </div>

            <!-- Database Records Tab -->
            <div class="tab-pane fade" id="database" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="fas fa-database"></i> Database Records (Last 50)</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="document.getElementById('database-records').innerHTML='<p>Loading database records...</p>'; fetch('google_calendar_queue_data.php?action=database_records').then(r=>r.text()).then(data=>document.getElementById('database-records').innerHTML=data).catch(err=>document.getElementById('database-records').innerHTML='<p style=\'color:red\'>Error loading records: '+err+'</p>')">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
                <div id="database-records">Loading database records...</div>
            </div>

            <!-- Settings Tab -->
            <div class="tab-pane fade" id="settings" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-cog"></i> Sync Configuration</h5>
                                <div class="alert alert-info">
                                    <strong>Current Issue:</strong> 2-minute delay causing potential booking conflicts.<br>
                                    <strong>Solution Options:</strong>
                                    <ul class="mb-0">
                                        <li><strong>Immediate Sync:</strong> 3-5 second delay, more server load</li>
                                        <li><strong>30-second Queue:</strong> Faster than current, still reliable</li>
                                        <li><strong>Hybrid Approach:</strong> Immediate for CREATE, queue for UPDATE/DELETE</li>
                                    </ul>
                                </div>
                                <p>Settings and timing adjustments will be configured here once we decide on the approach.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6><i class="fas fa-server"></i> System Status</h6>
                                <div id="system-status">Checking system status...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript functions are now defined in the main admin dashboard -->

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Initialize content on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load initial stats
    fetch('google_calendar_queue_data.php?action=quick_stats')
        .then(r => r.text())
        .then(data => {
            const statsDiv = document.getElementById('quick-stats');
            if (statsDiv) statsDiv.innerHTML = data;
        })
        .catch(err => console.error('Error loading stats:', err));
    
    // Load initial queue monitor
    fetch('google_calendar_queue_data.php?action=queue_monitor')
        .then(r => r.text())
        .then(data => {
            const queueDiv = document.getElementById('queue-monitor');
            if (queueDiv) queueDiv.innerHTML = data;
        })
        .catch(err => console.error('Error loading queue:', err));
    
    // Load initial logs
    fetch('google_calendar_queue_data.php?action=sync_logs')
        .then(r => r.text())
        .then(data => {
            const logsDiv = document.getElementById('sync-logs');
            if (logsDiv) logsDiv.innerHTML = data;
        })
        .catch(err => console.error('Error loading logs:', err));
    
    // Load initial database records
    fetch('google_calendar_queue_data.php?action=database_records')
        .then(r => r.text())
        .then(data => {
            const recordsDiv = document.getElementById('database-records');
            if (recordsDiv) recordsDiv.innerHTML = data;
        })
        .catch(err => console.error('Error loading records:', err));
    
    // Load system status
    fetch('google_calendar_queue_data.php?action=system_status')
        .then(r => r.text())
        .then(data => {
            const statusDiv = document.getElementById('system-status');
            if (statusDiv) statusDiv.innerHTML = data;
        })
        .catch(err => console.error('Error loading status:', err));
});

// Auto-refresh queue monitor if checkbox is checked
setInterval(function() {
    const autoRefresh = document.getElementById('autoRefreshQueue');
    const activeTab = document.querySelector('.nav-link.active');
    
    if (autoRefresh && autoRefresh.checked && activeTab && activeTab.id === 'queue-tab') {
        fetch('google_calendar_queue_data.php?action=queue_monitor')
            .then(r => r.text())
            .then(data => {
                const queueDiv = document.getElementById('queue-monitor');
                if (queueDiv) queueDiv.innerHTML = data;
            })
            .catch(err => console.error('Auto-refresh error:', err));
    }
}, 5000); // Refresh every 5 seconds
</script>

</div> <!-- Close gcal-management-container --> 