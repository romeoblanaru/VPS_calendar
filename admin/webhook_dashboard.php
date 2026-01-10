<?php
/**
 * Webhook Dashboard
 * Displays available webhooks with their documentation and functionality
 */

// Function to parse markdown documentation
function parseWebhookDocumentation($filePath) {
    if (!file_exists($filePath)) return null;
    $content = file_get_contents($filePath);
    $info = [
        'title' => '', 'overview' => '', 'endpoint' => '',
        'parameters' => ['required' => [], 'optional' => []],
        'examples' => []
    ];

    if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
        $title = trim($matches[1]);
        // Clean up common suffixes
        $title = preg_replace('/\s+(Webhook\s+Documentation|Documentation|Webhook)$/i', '', $title);
        $info['title'] = $title;
    }

    $sections = preg_split('/^##\s+/m', $content, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($sections as $section) {
        if (preg_match('/^Overview/m', $section)) {
            $info['overview'] = trim(preg_replace('/^Overview\s*/m', '', $section));

        } elseif (preg_match('/^Endpoint/m', $section)) {
            if (preg_match('/```[^\n]*\n(.*?)\n```/s', $section, $code)) $info['endpoint'] = trim($code[1]);

        } elseif (preg_match('/^Parameters/m', $section)) {
            // Required Parameters
            if (preg_match('/### Required Parameters\s*\n(.*?)(?=\n###|$)/s', $section, $reqPart)) {
                preg_match_all('/-\s+\*\*(.+?)\*\*.*?:(.+?)(?=\n\s*-\s+\*\*|$)/s', $reqPart[1], $params, PREG_SET_ORDER);
                foreach ($params as $param) $info['parameters']['required'][] = ['name' => trim($param[1]), 'description' => trim($param[2])];
            }
            // Optional Parameters
            if (preg_match('/### Optional Parameters\s*\n(.*?)(?=\n###|$)/s', $section, $optPart)) {
                preg_match_all('/-\s+\*\*(.+?)\*\*.*?:(.+?)(?=\n\s*-\s+\*\*|$)/s', $optPart[1], $params, PREG_SET_ORDER);
                foreach ($params as $param) $info['parameters']['optional'][] = ['name' => trim($param[1]), 'description' => trim($param[2])];
            }

        } elseif (preg_match('/^Usage Examples/m', $section)) {
            preg_match_all('/### (.*?)\s*\n```[a-z]*\s*(.*?)\s*```/s', $section, $examples, PREG_SET_ORDER);
            foreach ($examples as $example) $info['examples'][] = ['title' => trim($example[1]), 'code' => trim($example[2])];
        }
    }
    return $info;
}


// Function to get webhook files from directory
function getWebhookFiles() {
    $webhooksDir = '../webhooks/';
    $webhooks = [];
    
    if (!is_dir($webhooksDir)) {
        return $webhooks;
    }
    
    $files = scandir($webhooksDir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $filePath = $webhooksDir . $file;
        $fileInfo = pathinfo($file);
        
        if (is_file($filePath)) {
            if ($fileInfo['extension'] === 'php') {
                // This is a webhook implementation
                $webhookName = $fileInfo['filename'];

                if (!isset($webhooks[$webhookName])) {
                    $webhooks[$webhookName] = [];
                }

                $webhooks[$webhookName]['implementation'] = $file;

                // Look for corresponding documentation
                $docFile = $webhookName . '.md';
                if (file_exists($webhooksDir . $docFile)) {
                    $webhooks[$webhookName]['documentation'] = $docFile;
                    $webhooks[$webhookName]['parsed_doc'] = parseWebhookDocumentation($webhooksDir . $docFile);
                }
            }
        }
    }
    
    return $webhooks;
}

$webhooks = getWebhookFiles();
?>

<style>
.webhook-dashboard {
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.webhook-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
}

.webhook-header {
    background: #007bff;
    color: white;
    padding: 15px 20px;
    cursor: pointer;
    user-select: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background-color 0.2s;
}

.webhook-header:hover {
    background: #0056b3;
}

.webhook-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
}

.webhook-status {
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.2);
}

.webhook-content {
    display: none;
    padding: 20px;
    background: white;
}

.webhook-content.active {
    display: block;
}

.info-section {
    margin-bottom: 20px;
}

.info-section h4 {
    color: #495057;
    margin-bottom: 10px;
    border-bottom: 2px solid #dee2e6;
    padding-bottom: 5px;
}

.parameter-list {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 10px;
}

.parameter-item {
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
}

.parameter-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.parameter-name {
    font-family: 'Courier New', monospace;
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 3px;
    font-weight: bold;
    color: #495057;
}

.parameter-description {
    margin-top: 5px;
    color: #6c757d;
    font-size: 14px;
}

.endpoint-box {
    background: #e9ecef;
    padding: 15px;
    border-radius: 5px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    border-left: 4px solid #007bff;
}

.overview-text {
    color: #495057;
    line-height: 1.6;
    margin-bottom: 15px;
}

.file-info {
    background: #e8f5e8;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 10px;
    font-size: 14px;
}

.file-info strong {
    color: #28a745;
}

.caret {
    transition: transform 0.2s;
    font-size: 12px;
}

.caret.rotated {
    transform: rotate(90deg);
}

.no-webhooks {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.no-webhooks i {
    font-size: 48px;
    margin-bottom: 15px;
    color: #dee2e6;
}

.code-box {
    background: #212529;
    color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    margin-bottom: 15px;
    white-space: pre-wrap;
    word-wrap: break-word;
}
.code-box pre {
    margin: 0;
}
</style>

<div class="webhook-dashboard">
    <h2 style="color: #495057; margin-bottom: 20px;">
        <i class="fas fa-webhook"></i> Webhook Control Panel
        <span style="font-weight: normal; font-size: 0.7em; color: #666;">
            (webhook_dashboard.php)
        </span>
    </h2>
    
    <!-- Webhook Logs Link -->
    <div style="margin-bottom: 20px; padding: 15px; background: #e3f2fd; border: 1px solid #2196f3; border-radius: 8px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h4 style="margin: 0 0 5px 0; color: #1976d2;">
                    <i class="fas fa-chart-line"></i> Webhook Monitoring
                </h4>
                <p style="margin: 0; color: #424242; font-size: 14px;">
                    Monitor webhook calls, view statistics, and analyze performance
                </p>
            </div>
            <a href="webhook_logs.php" target="_blank" 
               style="background: #2196f3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: 600; transition: background 0.2s;"
               onmouseover="this.style.background='#1976d2'" 
               onmouseout="this.style.background='#2196f3'">
                <i class="fas fa-external-link-alt"></i> View Webhook Logs
            </a>
        </div>
    </div>
    
    <?php if (empty($webhooks)): ?>
        <div class="no-webhooks">
            <i class="fas fa-globe"></i>
            <h4>No Webhooks Found</h4>
            <p>No webhook files were found in the webhooks directory.</p>
        </div>
    <?php else: ?>
        <p style="color: #6c757d; margin-bottom: 25px;">
            <i class="fas fa-info-circle"></i> 
            Found <?= count($webhooks) ?> webhook(s) in the system. Click on any webhook below to view its documentation and functionality.
        </p>
        
        <?php foreach ($webhooks as $webhookName => $webhookData): ?>
            <div class="webhook-card">
                <div class="webhook-header" onclick="toggleWebhook('<?= htmlspecialchars($webhookName) ?>')">
                    <div>
                        <span class="caret" id="caret-<?= htmlspecialchars($webhookName) ?>">▶</span>
                        <span class="webhook-title" style="margin-left: 10px;">
                            <?= isset($webhookData['parsed_doc']['title']) && !empty($webhookData['parsed_doc']['title']) 
                                ? htmlspecialchars($webhookData['parsed_doc']['title']) 
                                : ucfirst(str_replace('_', ' ', $webhookName)) ?>
                        </span>
                    </div>
                    <div class="webhook-status">
                        <?= isset($webhookData['implementation']) ? 'Active' : 'Documentation Only' ?>
                    </div>
                </div>
                
                <div class="webhook-content" id="content-<?= htmlspecialchars($webhookName) ?>">
                    <!-- File Information -->
                    <div class="info-section">
                        <h4><i class="fas fa-file"></i> Files</h4>
                        <?php if (isset($webhookData['implementation'])): ?>
                            <div class="file-info">
                                <strong>Implementation:</strong> /webhooks/<?= htmlspecialchars($webhookData['implementation']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($webhookData['documentation'])): ?>
                            <div class="file-info">
                                <strong>Documentation:</strong> /webhooks/<?= htmlspecialchars($webhookData['documentation']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($webhookData['parsed_doc'])): ?>
                        <?php $doc = $webhookData['parsed_doc']; ?>
                        
                        <!-- Overview -->
                        <?php if (!empty($doc['overview'])): ?>
                            <div class="info-section">
                                <h4><i class="fas fa-info-circle"></i> Overview</h4>
                                <div class="overview-text"><?= nl2br(htmlspecialchars($doc['overview'])) ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Endpoint -->
                        <?php if (!empty($doc['endpoint'])): ?>
                            <div class="info-section">
                                <h4><i class="fas fa-link"></i> Endpoint</h4>
                                <div class="endpoint-box"><?= htmlspecialchars($doc['endpoint']) ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Parameters -->
                        <?php if (!empty($doc['parameters'])): ?>
                            <div class="info-section">
                                <h4><i class="fas fa-cogs"></i> Parameters</h4>
                                
                                <?php if (!empty($doc['parameters']['required'])): ?>
                                    <h5 style="color: #dc3545; margin-bottom: 10px;">Required Parameters</h5>
                                    <div class="parameter-list">
                                        <?php foreach ($doc['parameters']['required'] as $param): ?>
                                            <div class="parameter-item">
                                                <span class="parameter-name"><?= htmlspecialchars($param['name']) ?></span>
                                                <div class="parameter-description"><?= nl2br(htmlspecialchars($param['description'])) ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($doc['parameters']['optional'])): ?>
                                    <h5 style="color: #28a745; margin-bottom: 10px;">Optional Parameters</h5>
                                    <div class="parameter-list">
                                        <?php foreach ($doc['parameters']['optional'] as $param): ?>
                                            <div class="parameter-item">
                                                <span class="parameter-name"><?= htmlspecialchars($param['name']) ?></span>
                                                <div class="parameter-description"><?= nl2br(htmlspecialchars($param['description'])) ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Usage Examples -->
                        <?php if (!empty($doc['examples'])): ?>
                            <div class="info-section">
                                <h4><i class="fas fa-laptop-code"></i> Usage Examples</h4>
                                <?php foreach ($doc['examples'] as $example): ?>
                                    <h5 style="color: #17a2b8; margin-bottom: 10px;"><?= htmlspecialchars($example['title']) ?></h5>
                                    <div class="code-box">
                                        <pre><code><?= htmlspecialchars($example['code']) ?></code></pre>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="info-section">
                            <p style="color: #6c757d; font-style: italic;">
                                <i class="fas fa-file-alt"></i> 
                                Documentation not available or could not be parsed for this webhook.
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Actions -->
                    <div class="info-section">
                        <h4><i class="fas fa-tools"></i> Actions</h4>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <?php if (isset($webhookData['implementation'])): ?>
                                <button type="button" class="btn btn-primary" 
                                        onclick="window.open('../webhooks/<?= htmlspecialchars($webhookData['implementation']) ?>', '_blank')"
                                        style="padding: 8px 15px; font-size: 14px;">
                                    <i class="fas fa-external-link-alt"></i> View Implementation
                                </button>
                            <?php endif; ?>
                            
                            <?php if (isset($webhookData['documentation'])): ?>
                                <button type="button" class="btn btn-info" 
                                        onclick="window.open('view_documentation.php?doc=<?= urlencode($webhookData['documentation']) ?>', '_blank')"
                                        style="padding: 8px 15px; font-size: 14px;">
                                    <i class="fas fa-book"></i> View Full Documentation
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
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
</script>