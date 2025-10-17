<?php
// Include SSH control helper
require_once 'php_workers_ssh_control.php';

// Sudo control helper - now uses SSH with key authentication
function controlServiceWithSudo($service, $action, $password) {
    // Password is just the sudo password now
    // Username is hardcoded as 'rom'
    // SSH uses key authentication
    $username = 'rom';
    $ssh_password = ''; // Not needed with key auth
    $sudo_password = $password;
    
    // Use SSH with key authentication
    $result = controlServiceWithSSH($service, $action, $username, $ssh_password, $sudo_password);
    
    // If sshpass is not available, try PHP SSH2 extension
    if (strpos($result['error'], 'sshpass: command not found') !== false) {
        $result = controlServiceWithSSH2($service, $action, $username, $ssh_password, $sudo_password);
    }
    
    return $result;
}
?>