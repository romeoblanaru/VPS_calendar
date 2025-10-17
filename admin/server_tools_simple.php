<?php
session_start();
// Check authentication
if ((!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) || $_SESSION['role'] !== 'admin_user') {
    exit('Unauthorized - Access denied');
}

// Handle saving new tool
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tool'])) {
    $toolsFile = __DIR__ . '/custom_tools.json';
    $tools = [];
    
    if (file_exists($toolsFile)) {
        $tools = json_decode(file_get_contents($toolsFile), true) ?: [];
    }
    
    $newTool = [
        'id' => uniqid(),
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'url' => $_POST['url'] ?? '',
        'icon' => $_POST['icon'] ?? 'fas fa-tool'
    ];
    
    $tools[] = $newTool;
    file_put_contents($toolsFile, json_encode($tools, JSON_PRETTY_PRINT));
    
    // Redirect to prevent form resubmission
    header('Location: admin_dashboard.php#server_tools');
    exit;
}

// Load custom tools
$customTools = [];
$toolsFile = __DIR__ . '/custom_tools.json';
if (file_exists($toolsFile)) {
    $customTools = json_decode(file_get_contents($toolsFile), true) ?: [];
}
?>

<style>
.tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.tool-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tool-card h3 {
    margin: 0 0 10px 0;
    color: #333;
}

.tool-card p {
    color: #666;
    margin: 0 0 15px 0;
}

.tool-card button {
    background: #007bff;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
}

.add-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: #f8f9fa;
}

.add-card:hover {
    background: #e9ecef;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
}

.modal-content {
    background: white;
    margin: 50px auto;
    padding: 20px;
    width: 500px;
    border-radius: 8px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group input, .form-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
</style>

<div style="padding: 20px;">
    <h2 style="margin-bottom: 20px;">
        Server Tools & External Services
        <span style="font-weight: normal; font-size: 0.7em; color: #666;">
            (server_tools_simple.php)
        </span>
    </h2>
    
    <div class="tools-grid">
        <!-- Built-in tools -->
        <div class="tool-card">
            <h3><i class="fas fa-comments" style="color: #ff6b00;"></i> Conversation Logger</h3>
            <p>View and manage call transcripts and conversation logs</p>
            <button onclick="window.open('conversation_logger.php', '_blank')">
                <i class="fas fa-external-link-alt"></i> Open Tool
            </button>
        </div>
        
        <div class="tool-card">
            <h3><i class="fas fa-headset" style="color: #28a745;"></i> Voice System Control</h3>
            <p>Control panel for voice bot configuration and settings</p>
            <button onclick="window.open('http://voice.rom2.co.uk:8088/control-panel', '_blank')">
                <i class="fas fa-external-link-alt"></i> Open Control Panel
            </button>
        </div>
        
        <div class="tool-card">
            <h3><i class="fas fa-sms" style="color: #17a2b8;"></i> SMS Monitor</h3>
            <p>Monitor SMS messages sent through the system</p>
            <button onclick="window.open('http://voice.rom2.co.uk:8088/sms-monitor', '_blank')">
                <i class="fas fa-external-link-alt"></i> Open SMS Monitor
            </button>
        </div>
        
        <!-- Custom tools -->
        <?php foreach ($customTools as $tool): ?>
        <div class="tool-card">
            <h3><i class="<?php echo htmlspecialchars($tool['icon']); ?>" style="color: #6c757d;"></i> <?php echo htmlspecialchars($tool['title']); ?></h3>
            <p><?php echo htmlspecialchars($tool['description']); ?></p>
            <button onclick="window.open('<?php echo htmlspecialchars($tool['url']); ?>', '_blank')">
                <i class="fas fa-external-link-alt"></i> Open Tool
            </button>
        </div>
        <?php endforeach; ?>
        
        <!-- Add new tool -->
        <div class="tool-card add-card" onclick="document.getElementById('addModal').style.display='block'">
            <i class="fas fa-plus" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
            <p>Add New Tool</p>
        </div>
    </div>
</div>

<!-- Simple Modal -->
<div id="addModal" class="modal" onclick="if(event.target===this) this.style.display='none'">
    <div class="modal-content">
        <h3>Add New Server Tool</h3>
        <form method="POST">
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label>URL/Link</label>
                <input type="url" name="url" placeholder="https://..." required>
            </div>
            <div class="form-group">
                <label>Icon (Font Awesome class)</label>
                <input type="text" name="icon" placeholder="fas fa-tool" value="fas fa-tool">
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="save_tool" value="1" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                    Save Tool
                </button>
                <button type="button" onclick="document.getElementById('addModal').style.display='none'" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>