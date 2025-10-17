<?php
// Start session to check auth
if (session_status() === PHP_SESSION_NONE) {
    // Set session lifetime to 12 hours (43200 seconds)
    ini_set('session.gc_maxlifetime', 43200);
    ini_set('session.cookie_lifetime', 43200);
    
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_httponly', true);
    
    // Set session cookie parameters for 12 hours
    session_set_cookie_params([
        'lifetime' => 43200, // 12 hours
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

// If user is not authenticated, always go to login index
if (!isset($_SESSION['user'])) {
    header('Location: /calendar/index.php');
    exit;
}

// For authenticated users, also block raw directory listing
// and send them to their role-based landing via index redirect logic
header('Location: /calendar/index.php');
exit;


