<?php
session_start();
// Check authentication
if ((!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) || $_SESSION['role'] !== 'admin_user') {
    header("Location: login.php");
    exit();
}

// Get the documentation file
$doc = $_GET['doc'] ?? '';
if (empty($doc)) {
    die("No documentation specified");
}

// Security: only allow .md files from the webhooks directory
if (!preg_match('/^[a-zA-Z0-9_\-]+\.md$/', $doc)) {
    die("Invalid documentation file");
}

$filePath = '../webhooks/' . $doc;
if (!file_exists($filePath)) {
    die("Documentation file not found");
}

// Read the markdown content
$content = file_get_contents($filePath);

// Basic markdown to HTML conversion
function convertMarkdownToHtml($markdown) {
    // Convert headers
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $markdown);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
    
    // Convert bold
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    
    // Convert italic
    $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
    
    // Convert code blocks
    $html = preg_replace('/```([^`]+)```/s', '<pre><code>$1</code></pre>', $html);
    
    // Convert inline code
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
    
    // Convert line breaks
    $html = nl2br($html);
    
    // Convert lists
    $html = preg_replace('/^\* (.+)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);
    
    return $html;
}

$htmlContent = convertMarkdownToHtml(htmlspecialchars($content));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Documentation - <?php echo htmlspecialchars(basename($doc, '.md')); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            min-height: 100vh;
        }
        h1, h2, h3 {
            color: #2c3e50;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        h1 {
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        code {
            background-color: #f4f4f4;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        pre {
            background-color: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        pre code {
            background-color: transparent;
            padding: 0;
            color: #ecf0f1;
        }
        .header {
            background-color: #3498db;
            color: white;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            padding: 0 20px;
            font-size: 24px;
            border: none;
        }
        .back-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .back-btn:hover {
            background-color: #2980b9;
        }
        ul {
            list-style-type: disc;
            padding-left: 30px;
        }
        li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <a href="#" onclick="window.close(); return false;" class="back-btn">
        <i class="fas fa-times"></i> Close
    </a>
    
    <div class="header">
        <h1><i class="fas fa-book"></i> Webhook Documentation</h1>
    </div>
    
    <div class="container">
        <h1><?php echo htmlspecialchars(str_replace('_', ' ', basename($doc, '.md'))); ?></h1>
        <div class="content">
            <?php echo $htmlContent; ?>
        </div>
    </div>
</body>
</html>