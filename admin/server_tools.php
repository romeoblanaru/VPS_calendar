<?php
session_start();
// Check authentication
if ((!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) || $_SESSION['role'] !== 'admin_user') {
    exit('Unauthorized - Access denied');
}
?>

<style>
.server-tools-container {
    padding: 20px;
}

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
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
}

.tool-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.tool-card h3 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 1.2em;
}

.tool-card p {
    color: #666;
    margin: 0 0 15px 0;
    font-size: 0.9em;
}

.tool-card .tool-url {
    font-size: 0.8em;
    color: #999;
    margin-bottom: 15px;
    word-break: break-all;
}

.tool-card button {
    background: #00adb5;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9em;
    transition: background 0.3s;
    display: block;
    margin: 0 auto;
}

.tool-card button:hover {
    background: #008c94;
}

.tool-card h3 {
    display: flex;
    align-items: center;
    gap: 10px;
}

.tool-card h3 i {
    font-size: 1.1em;
    opacity: 0.8;
}

.add-card {
    background: #f8f9fa;
    border: 2px dashed #ccc;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
}

.add-card:hover {
    border-color: #999;
    background: #f0f0f0;
}

.add-card i {
    font-size: 2em;
    color: #999;
    margin-bottom: 10px;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 400px;
    border-radius: 8px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-header h3 {
    margin: 0;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: black;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    resize: vertical;
    min-height: 60px;
}

.delete-x {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 20px;
    height: 20px;
    background: transparent !important;
    background-color: transparent !important;
    color: #ccc;
    border: none;
    font-size: 20px;
    font-weight: normal;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.3s;
    padding: 0;
    line-height: 1;
    outline: none;
}

.delete-x:hover {
    color: #999;
    background: transparent !important;
}
</style>

<div class="server-tools-container">
    <h2 style="margin-bottom: 20px;">
        Server Tools & External Services
        <span style="font-weight: normal; font-size: 0.7em; color: #666;">
            (server_tools.php)
        </span>
    </h2>
    
    <div class="tools-grid" id="toolsGrid">
        <!-- Tool Card 1: Conversation Logger -->
        <div class="tool-card">
            <h3><i class="fas fa-comments" style="color: #ff6b00;"></i> Conversation Logger</h3>
            <p>Real-time voice conversation logging and transcript viewer</p>
            <div class="tool-url">https://voice.rom2.co.uk/static/conversations.html</div>
            <button onclick="window.open('https://voice.rom2.co.uk/static/conversations.html', '_blank')">
                <i class="fas fa-external-link-alt"></i> Open Tool
            </button>
        </div>
        
        <!-- Tool Card 2: Date Parser -->
        <div class="tool-card">
            <h3><i class="fas fa-clock" style="color: #28a745;"></i> Advanced Date Parser Tester</h3>
            <p>Tests relative date expressions (like "next week", "tomorrow", "in 3 days") and converts them to exact dates</p>
            <div class="tool-url">https://voice.rom2.co.uk/date-parser</div>
            <button onclick="window.open('https://voice.rom2.co.uk/date-parser', '_blank')">
                <i class="fas fa-external-link-alt"></i> Open Tool
            </button>
        </div>
        
        <!-- Tool Card 3: SMS Monitor -->
        <div class="tool-card">
            <h3><i class="fas fa-mobile-alt" style="color: #4285f4;"></i> SMS Monitor - Multi VPN Gateway</h3>
            <p>SMS gateway monitoring across VPN connections</p>
            <div class="tool-url">https://voice.rom2.co.uk/sms-monitor</div>
            <button onclick="window.open('https://voice.rom2.co.uk/sms-monitor', '_blank')">
                <i class="fas fa-external-link-alt"></i> Open Tool
            </button>
        </div>
        
        <!-- Tool Card 4: System Messages -->
        <div class="tool-card">
            <h3><i class="fas fa-envelope-open-text" style="color: #6c757d;"></i> System Messages</h3>
            <p>System-wide message monitoring and management</p>
            <div class="tool-url">https://voice.rom2.co.uk/message</div>
            <button onclick="window.open('https://voice.rom2.co.uk/message', '_blank')">
                <i class="fas fa-external-link-alt"></i> Open Tool
            </button>
        </div>
        
        <!-- Tool Card 5: Text to Voice -->
        <div class="tool-card">
            <h3><i class="fas fa-volume-up" style="color: #9b59b6;"></i> Text to Voice (TTS)</h3>
            <p>Test TTS and create voice files</p>
            <div class="tool-url">https://voice.rom2.co.uk/text_to_voice</div>
            <button onclick="window.open('https://voice.rom2.co.uk/text_to_voice', '_blank')">
                <i class="fas fa-external-link-alt"></i> Open Tool
            </button>
        </div>

        <!-- Tool Card 6: phpMyAdmin -->
        <div class="tool-card">
            <h3><i class="fas fa-database" style="color: #f39c12;"></i> phpMyAdmin</h3>
            <p>MySQL database management</p>
            <div class="tool-url">https://voice.rom2.co.uk/phpmyadmin</div>
            <button onclick="window.open('https://voice.rom2.co.uk/phpmyadmin', '_blank')">
                <i class="fas fa-external-link-alt"></i> Open Tool
            </button>
        </div>

        <!-- Tool Card 7: Check Availability Tester -->
        <div class="tool-card">
            <h3><i class="fas fa-calendar-check" style="color: #ff6b35;"></i> Check Availability Tester</h3>
            <p>Test and debug the check_availability function with custom parameters. View detailed performance breakdowns, timing analysis, and webhook calls made during execution.</p>
            <div class="tool-url">https://voice.rom2.co.uk/check-availability</div>
            <button onclick="window.open('https://voice.rom2.co.uk/check-availability', '_blank')">
                <i class="fas fa-external-link-alt"></i> Open Tool
            </button>
        </div>

        <!-- Add New Card -->
        <div class="tool-card add-card" onclick="document.getElementById('addToolModal').style.display='block'">
            <i class="fas fa-plus"></i>
            <p>Add New Tool</p>
        </div>
    </div>
</div>

<!-- Add New Tool Modal -->
<div id="addToolModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Server Tool</h3>
            <span class="close" onclick="document.getElementById('addToolModal').style.display='none'">&times;</span>
        </div>
        <form id="addToolForm" onsubmit="return saveNewTool()">
            <div class="form-group">
                <label for="toolName">Tool Name</label>
                <input type="text" id="toolName" name="name" required>
            </div>
            <div class="form-group">
                <label for="toolDescription">Description</label>
                <textarea id="toolDescription" name="description" required></textarea>
            </div>
            <div class="form-group">
                <label for="toolUrl">URL</label>
                <input type="url" id="toolUrl" name="url" required>
            </div>
            <div class="form-group">
                <label for="toolIcon">Icon (Font Awesome class)</label>
                <input type="text" id="toolIcon" name="icon" placeholder="fas fa-tool" value="fas fa-tool">
            </div>
            <button type="button" onclick="(function() {
                try {
                    const nameInput = document.getElementById('toolName');
                    const descInput = document.getElementById('toolDescription');
                    const urlInput = document.getElementById('toolUrl');
                    const iconInput = document.getElementById('toolIcon');
                    
                    if (!nameInput.value || !descInput.value || !urlInput.value) {
                        alert('Please fill in all required fields');
                        return false;
                    }
                    
                    const tool = {
                        id: Date.now().toString(),
                        name: nameInput.value,
                        description: descInput.value,
                        url: urlInput.value,
                        icon: iconInput.value || 'fas fa-tool'
                    };
                    
                    const existingTools = localStorage.getItem('serverTools');
                    const tools = existingTools ? JSON.parse(existingTools) : [];
                    tools.push(tool);
                    localStorage.setItem('serverTools', JSON.stringify(tools));
                    saveToolsToServer(tools);
                    
                    document.getElementById('addToolModal').style.display = 'none';
                    
                    const gridElement = document.getElementById('toolsGrid');
                    const addCard = gridElement.querySelector('.add-card');
                    
                    const card = document.createElement('div');
                    card.className = 'tool-card';
                    const toolId = tool.id.replace(/'/g, '');
                    card.innerHTML = '<button class=\'delete-x\' onclick=\'(function() { var answer = prompt(\\\'The name of your first pet?\\\'); if (answer && answer.toLowerCase() === \\\'foxu\\\') { if(confirm(\\\'Are you sure you want to remove this tool?\\\')) { var tools = JSON.parse(localStorage.getItem(\\\'serverTools\\\') || \\\'[]\\\'); var newTools = tools.filter(function(t) { return t.id !== \\\'' + toolId + '\\\'; }); localStorage.setItem(\\\'serverTools\\\', JSON.stringify(newTools)); saveToolsToServer(newTools); location.reload(); } } else if (answer !== null) { alert(\\\'Incorrect answer!\\\'); } })()\'>×</button>' +
                        '<h3><i class=\'' + tool.icon + '\' style=\'color: #17a2b8;\'></i> ' + tool.name + '</h3>' +
                        '<p>' + tool.description + '</p>' +
                        '<div class=\'tool-url\'>' + tool.url + '</div>' +
                        '<button onclick=\'window.open(\\\'' + tool.url + '\\\', \\\'_blank\\\')\'>' +
                            '<i class=\'fas fa-external-link-alt\'></i> Open Tool' +
                        '</button>';
                    gridElement.insertBefore(card, addCard);
                    
                    nameInput.value = '';
                    descInput.value = '';
                    urlInput.value = '';
                    iconInput.value = 'fas fa-tool';
                    
                    alert('Tool added successfully!');
                    
                } catch(err) {
                    alert('Error saving tool: ' + err.message);
                }
                return false;
            })()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                <i class="fas fa-save"></i> Save Tool
            </button>
        </form>
    </div>
</div>

<script>
// Ensure function is in global scope
if (typeof window.saveNewTool === 'undefined') {
    window.saveNewTool = function() {
    console.log('saveNewTool called');
    
    try {
        const nameInput = document.getElementById('toolName');
        const descInput = document.getElementById('toolDescription');
        const urlInput = document.getElementById('toolUrl');
        const iconInput = document.getElementById('toolIcon');
        
        if (!nameInput || !descInput || !urlInput || !iconInput) {
            alert('Form elements not found. Please try again.');
            return false;
        }
        
        if (!nameInput.value || !descInput.value || !urlInput.value) {
            alert('Please fill in all required fields');
            return false;
        }
        
        const tool = {
            id: Date.now().toString(),
            name: nameInput.value,
            description: descInput.value,
            url: urlInput.value,
            icon: iconInput.value || 'fas fa-tool'
        };
        
        console.log('Creating tool:', tool);
        
        // Get existing tools
        const existingTools = localStorage.getItem('serverTools');
        const tools = existingTools ? JSON.parse(existingTools) : [];
        
        // Add new tool
        tools.push(tool);
        
        // Save to localStorage
        localStorage.setItem('serverTools', JSON.stringify(tools));
        
        console.log('Tool saved, total tools:', tools.length);
        
        // Close modal
        document.getElementById('addToolModal').style.display = 'none';
        
        // Add the new tool card immediately
        const gridElement = document.getElementById('toolsGrid');
        const addCard = gridElement.querySelector('.add-card');
        
        const card = document.createElement('div');
        card.className = 'tool-card';
        card.innerHTML = '<h3><i class="' + tool.icon + '" style="color: #17a2b8;"></i> ' + tool.name + '</h3>' +
            '<p>' + tool.description + '</p>' +
            '<div class="tool-url">' + tool.url + '</div>' +
            '<button onclick="window.open(\'' + tool.url + '\', \'_blank\')">' +
                '<i class="fas fa-external-link-alt"></i> Open Tool' +
            '</button>' +
            '<button onclick="if(confirm(\'Remove this tool?\')) { var tools = JSON.parse(localStorage.getItem(\'serverTools\') || \'[]\'); localStorage.setItem(\'serverTools\', JSON.stringify(tools.filter(function(t) { return t.id !== \'' + tool.id + '\'; }))); location.reload(); }" style="background: #dc3545; margin-left: 10px; margin-top: 10px;">' +
                '<i class="fas fa-trash"></i> Remove' +
            '</button>';
        gridElement.insertBefore(card, addCard);
        
        // Clear form
        nameInput.value = '';
        descInput.value = '';
        urlInput.value = '';
        iconInput.value = 'fas fa-tool';
        
        alert('Tool added successfully!');
        
    } catch(err) {
        console.error('Error:', err);
        alert('Error saving tool: ' + err.message);
    }
    
    return false;
    };
}

// Function to save tools to server - make it global
window.saveToolsToServer = function(tools) {
    fetch('save_custom_tools.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ tools: tools })
    });
}

// Load custom tools immediately
(function() {
    // First try to load from server
    fetch('save_custom_tools.php')
        .then(response => response.json())
        .then(serverTools => {
            // If we have server tools, use them and update localStorage
            if (serverTools && serverTools.length > 0) {
                localStorage.setItem('serverTools', JSON.stringify(serverTools));
                displayTools(serverTools);
            } else {
                // Otherwise use localStorage
                const localTools = JSON.parse(localStorage.getItem('serverTools') || '[]');
                if (localTools.length > 0) {
                    saveToolsToServer(localTools); // Save to server
                }
                displayTools(localTools);
            }
        })
        .catch(error => {
            // Fallback to localStorage
            const localTools = JSON.parse(localStorage.getItem('serverTools') || '[]');
            displayTools(localTools);
        });
    
    function displayTools(customTools) {
        const gridElement = document.getElementById('toolsGrid');
        if (!gridElement) return;
        
        const addCard = gridElement.querySelector('.add-card');
    
    customTools.forEach(tool => {
        const card = document.createElement('div');
        card.className = 'tool-card';
        const toolId = tool.id.replace(/'/g, '');
        card.innerHTML = '<button class="delete-x" onclick="(function() { var answer = prompt(\'The name of your first pet?\'); if (answer && answer.toLowerCase() === \'foxu\') { if(confirm(\'Are you sure you want to remove this tool?\')) { var tools = JSON.parse(localStorage.getItem(\'serverTools\') || \'[]\'); var newTools = tools.filter(function(t) { return t.id !== \'' + toolId + '\'; }); localStorage.setItem(\'serverTools\', JSON.stringify(newTools)); saveToolsToServer(newTools); location.reload(); } } else if (answer !== null) { alert(\'Incorrect answer!\'); } })()">×</button>' +
            '<h3><i class="' + (tool.icon || 'fas fa-tool') + '" style="color: #17a2b8;"></i> ' + tool.name + '</h3>' +
            '<p>' + tool.description + '</p>' +
            '<div class="tool-url">' + tool.url + '</div>' +
            '<button onclick="window.open(\'' + tool.url + '\', \'_blank\')">' +
                '<i class="fas fa-external-link-alt"></i> Open Tool' +
            '</button>';
        gridElement.insertBefore(card, addCard);
    });
    }
})();

// Handle modal clicks on background
window.onclick = function(event) {
    const modal = document.getElementById('addToolModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>