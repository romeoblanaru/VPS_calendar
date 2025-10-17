<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../includes/db.php';
require_once '../includes/webhook_logger.php';

// Handle AJAX request for checking new records
if (isset($_GET['check_new']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $since = $_GET['since'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));
    $webhook_name = $_GET['webhook_name'] ?? '';
    $status = $_GET['status'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    // Build WHERE clause for new records check
    $whereConditions = ['created_at > ?'];
    $params = [$since];
    
    if ($webhook_name) {
        $whereConditions[] = "webhook_name = ?";
        $params[] = $webhook_name;
    }
    
    if ($status !== '') {
        $whereConditions[] = "is_successful = ?";
        $params[] = (int)$status;
    }
    
    if ($date_from) {
        $whereConditions[] = "created_at >= ?";
        $params[] = $date_from . ' 00:00:00';
    }
    
    if ($date_to) {
        $whereConditions[] = "created_at <= ?";
        $params[] = $date_to . ' 23:59:59';
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Count new records and get latest timestamp
    $countSql = "SELECT COUNT(*) as new_count, MAX(created_at) as latest_created_at FROM webhook_logs $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $newCount = $row['new_count'];
    $latestCreatedAt = $row['latest_created_at'];
    
    echo json_encode([
        'new_records' => (int)$newCount,
        'latest_created_at' => $latestCreatedAt,
        'since' => $since,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Get filter parameters
$webhook_name = $_GET['webhook_name'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereConditions = [];
$params = [];

if ($webhook_name) {
    $whereConditions[] = "webhook_name = ?";
    $params[] = $webhook_name;
}

if ($status !== '') {
    $whereConditions[] = "is_successful = ?";
    $params[] = (int)$status;
}

if ($date_from) {
    $whereConditions[] = "created_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if ($date_to) {
    $whereConditions[] = "created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countSql = "SELECT COUNT(*) as total FROM webhook_logs $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $limit);

// Get webhook logs
$limit = (int)$limit;
$offset = (int)$offset;
$sql = "SELECT * FROM webhook_logs $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = WebhookLogger::getStatistics($pdo, $webhook_name, 30);

// Get unique webhook names for filter
$stmt = $pdo->query("SELECT DISTINCT webhook_name FROM webhook_logs ORDER BY webhook_name");
$webhookNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

/**
 * Format request parameters for display
 */
function formatParameters($requestParams) {
    if (empty($requestParams)) {
        return '<em>No parameters</em>';
    }
    
    $params = json_decode($requestParams, true);
    if (!$params || !is_array($params)) {
        return '<em>Invalid parameters</em>';
    }
    
    $output = '';
    foreach ($params as $key => $value) {
        $displayValue = is_string($value) ? htmlspecialchars($value) : htmlspecialchars(json_encode($value));
        $output .= '<div class="parameter-item">';
        $output .= '<span class="parameter-key">' . htmlspecialchars($key) . ':</span> ';
        $output .= '<span class="parameter-value">' . $displayValue . '</span>';
        $output .= '</div>';
    }
    
    return $output;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook Logs - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        .webhook-logs {
            padding: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #333;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        .filters {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .filters input, .filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
        }
        .filters input[type="date"] {
            width: 33.33%;
        }
        .filters button {
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .filters button:hover {
            background: #0056b3;
        }
        .logs-table {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .logs-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .logs-table th, .logs-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .logs-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .status-success {
            color: #28a745;
            font-weight: bold;
        }
        .status-error {
            color: #dc3545;
            font-weight: bold;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        .pagination a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
        }
        .pagination a:hover {
            background: #f8f9fa;
        }
        .pagination .current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .log-details {
            max-width: 300px;
            word-wrap: break-word;
        }
        .view-details {
            color: #007bff;
            text-decoration: none;
        }
        .view-details:hover {
            text-decoration: underline;
        }
        .parameters-display {
            max-width: 200px;
            word-wrap: break-word;
            font-size: 12px;
            color: #666;
        }
        .parameter-item {
            margin-bottom: 2px;
        }
        .parameter-key {
            font-weight: bold;
            color: #333;
        }
        .parameter-value {
            color: #666;
        }
        .latest-record {
            background-color: #e3f2fd !important; /* Light blue for latest record */
        }
        .second-latest-record {
            background-color: #f3f8ff !important; /* Lighter blue for second latest record */
        }
        
        /* Page header with inline controls */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .page-header h1 {
            margin: 0;
            color: #333;
        }
        
        /* Auto-refresh controls inline */
        .auto-refresh-controls-inline {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .btn-auto-refresh-enabled {
            background: #20c997;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .btn-auto-refresh-disabled {
            background: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .btn-auto-refresh-enabled:hover {
            background: #1ba085;
        }
        
        .btn-auto-refresh-disabled:hover {
            background: #218838;
        }
        
        .auto-refresh-status-on {
            color: #28a745;
            font-weight: 600;
            font-size: 0.85em;
        }
        
        .auto-refresh-status-off {
            color: #6c757d;
            font-weight: 600;
            font-size: 0.85em;
        }
        
        /* Auto-refresh description removed for inline layout */
        
        /* New records notification */
        .new-records-notification {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            animation: slideInDown 0.3s ease-out;
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .notification-content strong {
            font-size: 1.1em;
        }
        
        .notification-content span {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        @keyframes slideInDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        .log-detail-section {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        .log-detail-header {
            background: #f8f9fa;
            padding: 10px 15px;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
        }
        .log-detail-content {
            padding: 15px;
            background: white;
        }
        .log-detail-content pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            margin: 0;
            font-size: 12px;
        }
        .log-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .log-detail-full {
            grid-column: 1 / -1;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        .related-ids {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .related-id {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="webhook-logs">
        <div class="page-header">
            <h1>Webhook Logs</h1>
            <div class="auto-refresh-controls-inline">
                <button id="autoRefreshBtn" class="btn-auto-refresh-disabled" onclick="toggleAutoRefresh()">
                    ▶️ Enable Auto-Refresh
                </button>
                <span id="autoRefreshStatus" class="auto-refresh-status-off">Auto-refresh: OFF</span>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_calls'] ?? 0 ?></div>
                <div class="stat-label">Total Calls (30 days)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['successful_calls'] ?? 0 ?></div>
                <div class="stat-label">Successful Calls</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['failed_calls'] ?? 0 ?></div>
                <div class="stat-label">Failed Calls</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= round($stats['avg_processing_time'] ?? 0) ?>ms</div>
                <div class="stat-label">Avg Processing Time</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <form method="GET">
                <div>
                    <label for="webhook_name">Webhook Name:</label>
                    <select name="webhook_name" id="webhook_name" onchange="autoSubmit()">
                        <option value="">All Webhooks</option>
                        <?php foreach ($webhookNames as $name): ?>
                            <option value="<?= htmlspecialchars($name) ?>" <?= $webhook_name === $name ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status">Status:</label>
                    <select name="status" id="status" onchange="autoSubmit()">
                        <option value="">All Status</option>
                        <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Successful</option>
                        <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>
                <div>
                    <label for="date_from">Date From:</label>
                    <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div>
                    <label for="date_to">Date To:</label>
                    <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <button type="submit">Filter</button>
                        <a href="?">Clear</a>
                    </div>
                    <button type="button" onclick="clearFilteredRecords()" style="background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">Delete Filtered Rec.</button>
                </div>
            </form>
        </div>
        

        
        <!-- Logs Table -->
        <div class="logs-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Webhook</th>
                        <th>Method</th>
                        <th>Parameters</th>
                        <th>Status</th>
                        <th>Response Code</th>
                        <th>Processing Time</th>
                        <th>Client IP</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $firstLog = true; foreach ($logs as $log): ?>
                        <?php 
                        $rowClass = '';
                        if ($firstLog) {
                            $rowClass = 'latest-record';
                        } elseif ($firstLog) {
                            $rowClass = 'second-latest-record';
                        }
                        ?>
                        <tr<?= $firstLog ? ' data-latest-created-at="' . htmlspecialchars($log['created_at']) . '"' : '' ?>>
                            <td><?= $log['id'] ?></td>
                            <td><?= htmlspecialchars($log['webhook_name']) ?></td>
                            <td><?= htmlspecialchars($log['request_method']) ?></td>
                            <td class="parameters-display">
                                <?= formatParameters($log['request_params']) ?>
                            </td>
                            <td>
                                <span class="status-<?= $log['is_successful'] ? 'success' : 'error' ?>">
                                    <?= $log['is_successful'] ? 'Success' : 'Failed' ?>
                                </span>
                            </td>
                            <td><?= $log['response_status_code'] ?></td>
                            <td><?= $log['processing_time_ms'] ?>ms</td>
                            <td><?= htmlspecialchars($log['client_ip']) ?></td>
                            <td><?= $log['created_at'] ?></td>
                            <td>
                                <a href="#" onclick="showLogDetails(<?= $log['id'] ?>)" class="view-details">View Details</a>
                            </td>
                        </tr>
                    <?php $firstLog = false; endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                       class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal for log details -->
    <div id="logModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; max-width: 90vw; max-height: 90vh; overflow-y: auto; min-width: 600px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Webhook Log Details</h3>
                <button onclick="closeLogModal()" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Close</button>
            </div>
            <div id="logDetails"></div>
        </div>
    </div>
    
    <script>
function isNewer(ts1, ts2) {
    if (!ts1 || !ts2) return false;
    return new Date(ts1).getTime() > new Date(ts2).getTime();
}
        function showLogDetails(logId) {
            // Track modal state for auto-refresh pause
            isModalOpen = true;
            
            // Show loading state
            document.getElementById('logDetails').innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <p>Loading log details...</p>
                </div>
            `;
            document.getElementById('logModal').style.display = 'block';
            
            // Fetch log details via AJAX
            fetch(`get_webhook_log_details.php?log_id=${logId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayLogDetails(data.log);
                    } else {
                        document.getElementById('logDetails').innerHTML = `
                            <div style="text-align: center; padding: 40px; color: #dc3545;">
                                <p>Error: ${data.message}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('logDetails').innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #dc3545;">
                            <p>Error loading log details</p>
                        </div>
                    `;
                });
        }
        
        function displayLogDetails(log) {
            const statusClass = log.is_successful ? 'status-success' : 'status-error';
            const statusText = log.is_successful ? 'Success' : 'Failed';
            
            let relatedIdsHtml = '';
            if (log.related_booking_id || log.related_specialist_id || log.related_organisation_id || log.related_working_point_id) {
                relatedIdsHtml = '<div class="related-ids">';
                if (log.related_booking_id) relatedIdsHtml += `<span class="related-id">Booking: ${log.related_booking_id}</span>`;
                if (log.related_specialist_id) relatedIdsHtml += `<span class="related-id">Specialist: ${log.related_specialist_id}</span>`;
                if (log.related_organisation_id) relatedIdsHtml += `<span class="related-id">Organisation: ${log.related_organisation_id}</span>`;
                if (log.related_working_point_id) relatedIdsHtml += `<span class="related-id">Working Point: ${log.related_working_point_id}</span>`;
                relatedIdsHtml += '</div>';
            }
            
            let additionalDataHtml = '';
            if (log.additional_data) {
                additionalDataHtml = `
                    <div class="log-detail-section">
                        <div class="log-detail-header">Additional Data</div>
                        <div class="log-detail-content">
                            <pre>${JSON.stringify(log.additional_data, null, 2)}</pre>
                        </div>
                    </div>
                `;
            }
            
            let errorDetailsHtml = '';
            if (!log.is_successful && (log.error_message || log.error_trace)) {
                errorDetailsHtml = `
                    <div class="log-detail-section">
                        <div class="log-detail-header">Error Details</div>
                        <div class="log-detail-content">
                            ${log.error_message ? `<p><strong>Error Message:</strong> ${log.error_message}</p>` : ''}
                            ${log.error_trace ? `<p><strong>Error Trace:</strong></p><pre>${log.error_trace}</pre>` : ''}
                        </div>
                    </div>
                `;
            }
            
            // Format response body for better readability
            let formattedResponseBody = '';
            if (log.response_body) {
                try {
                    // Try to parse as JSON and format it
                    const parsedJson = JSON.parse(log.response_body);
                    formattedResponseBody = `<pre style="white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto;">${JSON.stringify(parsedJson, null, 2)}</pre>`;
                } catch (e) {
                    // If not valid JSON, display as is
                    formattedResponseBody = `<pre style="white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto;">${log.response_body}</pre>`;
                }
            } else {
                formattedResponseBody = '<p>No response body recorded</p>';
            }
            
            document.getElementById('logDetails').innerHTML = `
                <div class="log-detail-grid">
                    <!-- Basic Info -->
                    <div class="log-detail-section">
                        <div class="log-detail-header">Basic Information</div>
                        <div class="log-detail-content">
                            <p><strong>ID:</strong> ${log.id}</p>
                            <p><strong>Webhook:</strong> ${log.webhook_name}</p>
                            <p><strong>Status:</strong> <span class="status-badge ${statusClass}">${statusText}</span></p>
                            <p><strong>Method:</strong> ${log.request_method}</p>
                            <p><strong>Response Code:</strong> ${log.response_status_code}</p>
                            <p><strong>Processing Time:</strong> ${log.processing_time_ms}ms</p>
                            <p><strong>Created:</strong> ${log.created_at}</p>
                            <p><strong>Processed:</strong> ${log.processed_at}</p>
                        </div>
                    </div>
                    
                    <!-- Client Info -->
                    <div class="log-detail-section">
                        <div class="log-detail-header">Client Information</div>
                        <div class="log-detail-content">
                            <p><strong>Client IP:</strong> ${log.client_ip}</p>
                            <p><strong>User Agent:</strong> ${log.user_agent || 'N/A'}</p>
                            ${relatedIdsHtml}
                        </div>
                    </div>
                    
                    <!-- Request URL -->
                    <div class="log-detail-section log-detail-full">
                        <div class="log-detail-header">Request URL</div>
                        <div class="log-detail-content">
                            <pre>${log.request_url}</pre>
                        </div>
                    </div>
                    
                    <!-- Request Headers -->
                    <div class="log-detail-section">
                        <div class="log-detail-header">Request Headers</div>
                        <div class="log-detail-content">
                            ${log.request_headers ? `<pre>${JSON.stringify(log.request_headers, null, 2)}</pre>` : '<p>No headers recorded</p>'}
                        </div>
                    </div>
                    
                    <!-- Request Body -->
                    <div class="log-detail-section">
                        <div class="log-detail-header">Request Body</div>
                        <div class="log-detail-content">
                            ${log.request_body ? `<pre>${log.request_body}</pre>` : '<p>No body recorded</p>'}
                        </div>
                    </div>
                    
                    <!-- Request Parameters -->
                    <div class="log-detail-section">
                        <div class="log-detail-header">Request Parameters</div>
                        <div class="log-detail-content">
                            ${log.request_params ? `<pre>${JSON.stringify(log.request_params, null, 2)}</pre>` : '<p>No parameters recorded</p>'}
                        </div>
                    </div>
                    
                    <!-- Response Headers -->
                    <div class="log-detail-section">
                        <div class="log-detail-header">Response Headers</div>
                        <div class="log-detail-content">
                            ${log.response_headers ? `<pre>${JSON.stringify(log.response_headers, null, 2)}</pre>` : '<p>No response headers recorded</p>'}
                        </div>
                    </div>
                    
                    ${errorDetailsHtml}
                    ${additionalDataHtml}
                    
                    <!-- Response Body - Moved to the end -->
                    <div class="log-detail-section log-detail-full">
                        <div class="log-detail-header">Response Body</div>
                        <div class="log-detail-content">
                            ${formattedResponseBody}
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Modal close functionality is handled later in the auto-refresh section
        
        function autoSubmit() {
            // Get current form values
            const webhookName = document.getElementById('webhook_name').value;
            const status = document.getElementById('status').value;
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            
            // Build query parameters
            const params = new URLSearchParams();
            if (webhookName) params.append('webhook_name', webhookName);
            if (status) params.append('status', status);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            
            // Redirect with new parameters
            window.location.href = 'webhook_logs.php?' + params.toString();
        }
        
        // Auto-refresh functionality
        let autoRefreshInterval = null;
        let autoRefreshEnabled = false;
        let lastRefreshTime = new Date().toISOString();
        let isModalOpen = false;

        // LocalStorage key for auto-refresh state
        const AUTO_REFRESH_KEY = 'webhookLogsAutoRefresh';

        function saveAutoRefreshState(enabled) {
            localStorage.setItem(AUTO_REFRESH_KEY, enabled ? 'on' : 'off');
        }

        function loadAutoRefreshState() {
            return localStorage.getItem(AUTO_REFRESH_KEY) === 'on';
        }

        function toggleAutoRefresh(forceState = null) {
            const button = document.getElementById('autoRefreshBtn');
            const status = document.getElementById('autoRefreshStatus');
            let newState;
            if (forceState !== null) {
                newState = forceState;
            } else {
                newState = !autoRefreshEnabled;
            }
            if (!newState) {
                // Disable auto-refresh
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
                autoRefreshEnabled = false;
                button.textContent = '▶️ Enable Auto-Refresh';
                button.className = 'btn-auto-refresh-disabled';
                status.textContent = 'Auto-refresh: OFF';
                status.className = 'auto-refresh-status-off';
            } else {
                // Enable auto-refresh
                autoRefreshEnabled = true;
                button.textContent = '⏸️ Disable Auto-Refresh';
                button.className = 'btn-auto-refresh-enabled';
                status.textContent = 'Auto-refresh: ON (5s)';
                status.className = 'auto-refresh-status-on';
                autoRefreshInterval = setInterval(checkForNewRecords, 5000);
                lastRefreshTime = getLatestLogTimestamp();
            }
            saveAutoRefreshState(newState);
        }

        function getLatestLogTimestamp() {
            var latestRow = document.querySelector('.logs-table tbody tr[data-latest-created-at]');
            if (latestRow) {
                return latestRow.getAttribute('data-latest-created-at');
            } else {
                return new Date().toISOString();
            }
        }
        
        function checkForNewRecords() {
            // Don't refresh if modal is open
            if (isModalOpen) {
                return;
            }
            // Get current filter parameters
            const urlParams = new URLSearchParams(window.location.search);
            const checkParams = new URLSearchParams(urlParams);
            checkParams.append('check_new', '1');
            checkParams.append('since', lastRefreshTime);
            const ajaxUrl = `webhook_logs.php?${checkParams.toString()}`;
            fetch(ajaxUrl, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.new_records && data.latest_created_at && isNewer(data.latest_created_at, lastRefreshTime)) {
                    showNewRecordsNotification(data.new_records);
                    // Update lastRefreshTime to the new latest_created_at
                    lastRefreshTime = data.latest_created_at;
                    // Auto-reload to show new records (preserve current page/filters)
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else if (data.latest_created_at && isNewer(data.latest_created_at, lastRefreshTime)) {
                    // No new records, but update lastRefreshTime if needed
                    lastRefreshTime = data.latest_created_at;
                }
            })
            .catch(error => {
                console.error('Auto-refresh check failed:', error);
                // Don't show error to user unless it's persistent
            });
        }
        
        function showNewRecordsNotification(count) {
            // Remove existing notification
            const existing = document.querySelector('.new-records-notification');
            if (existing) {
                existing.remove();
            }
            
            // Create notification
            const notification = document.createElement('div');
            notification.className = 'new-records-notification';
            notification.innerHTML = `
                <div class="notification-content">
                    <strong>🔔 ${count} new webhook log${count > 1 ? 's' : ''} found!</strong>
                    <span>Page will refresh automatically...</span>
                </div>
            `;
            
            // Insert at top of logs table
            const logsTable = document.querySelector('.logs-table');
            logsTable.parentNode.insertBefore(notification, logsTable);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }
        
        // Track modal state for auto-refresh pause
        
        function closeLogModal() {
            isModalOpen = false;
            document.getElementById('logModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('logModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLogModal();
            }
        });
        
        // Initialize auto-refresh on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set lastRefreshTime to the latest log's created_at
            lastRefreshTime = getLatestLogTimestamp();
            // Restore auto-refresh state from localStorage
            const savedState = loadAutoRefreshState();
            toggleAutoRefresh(savedState);
        });

        function clearFilteredRecords() {
            // Get current filter parameters
            const urlParams = new URLSearchParams(window.location.search);
            const webhookName = urlParams.get('webhook_name') || '';
            const status = urlParams.get('status') || '';
            const dateFrom = urlParams.get('date_from') || '';
            const dateTo = urlParams.get('date_to') || '';
            
            // Build confirmation message
            let message = 'Are you sure you want to delete all webhook logs';
            if (webhookName || status !== '' || dateFrom || dateTo) {
                message += ' matching the current filters?';
                if (webhookName) message += `\n- Webhook: ${webhookName}`;
                if (status !== '') message += `\n- Status: ${status === '1' ? 'Successful' : 'Failed'}`;
                if (dateFrom) message += `\n- From: ${dateFrom}`;
                if (dateTo) message += `\n- To: ${dateTo}`;
            } else {
                message += '? (This will delete ALL webhook logs!)';
            }
            
            if (!confirm(message)) {
                return;
            }
            
            // Show loading state
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = 'Deleting...';
            button.disabled = true;
            
            // Send delete request
            fetch('clear_webhook_logs.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    webhook_name: webhookName,
                    status: status,
                    date_from: dateFrom,
                    date_to: dateTo
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Successfully deleted ${data.deleted_count} webhook log(s).`);
                    // Redirect to initial state (no filters)
                    window.location.href = 'webhook_logs.php';
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete records.'));
                    button.textContent = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting records.');
                button.textContent = originalText;
                button.disabled = false;
            });
        }
    </script>
</body>
</html> 