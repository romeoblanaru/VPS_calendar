<?php
// Set session cookie parameters to ensure it works across all paths
if (session_status() === PHP_SESSION_NONE) {
    // Set session lifetime to 12 hours (43200 seconds)
    ini_set('session.gc_maxlifetime', 43200);
    ini_set('session.cookie_lifetime', 43200);
    
    // Set session cookie parameters
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_domain', '');
    ini_set('session.cookie_secure', false);
    ini_set('session.cookie_httponly', true);
    
    // Start session with custom parameters
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

if (!isset($_SESSION['lang'])) {
    $default_lang = 'en';
    $geoip_lang = $_SERVER['GEOIP_COUNTRY_CODE'] ?? '';

    switch (strtoupper($geoip_lang)) {
        case 'RO':
            $_SESSION['lang'] = 'ro';
            break;
        case 'LT':
            $_SESSION['lang'] = 'lt';
            break;
        default:
            $_SESSION['lang'] = $default_lang;
    }
}

$lang = $_SESSION['lang'];

$lang_data = [];
$lang_file = __DIR__ . '/../lang/' . $lang . '.php';
if (file_exists($lang_file)) {
    $lang_data = include($lang_file);
}
?>
