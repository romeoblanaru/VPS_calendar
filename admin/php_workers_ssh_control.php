<?php
// SSH-based sudo control helper
function controlServiceWithSSH($service, $action, $username, $password, $sudo_password) {
    // Use SSH key authentication to connect as the specified user
    $host = 'localhost';
    $port = 22;
    
    // Build the command to run via SSH
    // We'll echo the sudo password and pipe it to sudo -S
    $remote_command = sprintf(
        'echo %s | sudo -S systemctl %s %s 2>&1',
        escapeshellarg($sudo_password),
        escapeshellarg($action),
        escapeshellarg($service)
    );
    
    // SSH with key authentication (www-data's key should be in rom's authorized_keys)
    $ssh_command = sprintf(
        'ssh -o StrictHostKeyChecking=no -o PasswordAuthentication=no -i /var/www/.ssh/id_rsa -p %d %s@%s %s 2>&1',
        $port,
        escapeshellarg($username),
        $host,
        escapeshellarg($remote_command)
    );
    
    $output = shell_exec($ssh_command);
    
    // If SSH key auth failed, show helpful message
    if (strpos($output, 'Permission denied') !== false) {
        $output = "SSH key authentication failed. Please run: sudo /srv/project_1/calendar/workers/setup_ssh_key.sh";
    }
    
    // Check if the command succeeded
    $success = false;
    if ($output) {
        // Check for common error patterns (but ignore sudo password prompt)
        if ((strpos($output, 'Failed') !== false || 
             strpos($output, 'error') !== false ||
             strpos($output, 'incorrect password') !== false ||
             strpos($output, 'Permission denied') !== false ||
             strpos($output, 'authentication failed') !== false) &&
            strpos($output, '[sudo] password for') === false) {
            $success = false;
        } else if (strpos($output, 'Active: active') !== false || 
                   strpos($output, 'Started') !== false ||
                   strpos($output, '[sudo] password for') !== false ||
                   empty(trim($output))) {
            // systemctl often returns nothing on success, or just shows sudo prompt
            $success = true;
        }
    }
    
    return array(
        'success' => $success,
        'output' => $output ?: 'No output from SSH command',
        'error' => $success ? '' : $output
    );
}

// Alternative using PHP SSH2 extension if available
function controlServiceWithSSH2($service, $action, $username, $password, $sudo_password) {
    if (!function_exists('ssh2_connect')) {
        return array(
            'success' => false,
            'error' => 'PHP SSH2 extension not installed'
        );
    }
    
    $connection = ssh2_connect('localhost', 22);
    if (!$connection) {
        return array('success' => false, 'error' => 'Could not connect to SSH');
    }
    
    if (!ssh2_auth_password($connection, $username, $password)) {
        return array('success' => false, 'error' => 'SSH authentication failed');
    }
    
    // Execute sudo command
    $command = sprintf('echo %s | sudo -S systemctl %s %s 2>&1',
        escapeshellarg($sudo_password),
        $action,
        $service
    );
    
    $stream = ssh2_exec($connection, $command);
    stream_set_blocking($stream, true);
    
    $output = stream_get_contents($stream);
    fclose($stream);
    
    $success = (strpos($output, 'Failed') === false && strpos($output, 'error') === false);
    
    return array(
        'success' => $success,
        'output' => $output,
        'error' => $success ? '' : $output
    );
}
?>