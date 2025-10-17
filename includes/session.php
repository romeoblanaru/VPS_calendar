<?php
if (!isset($_SESSION['user'])) {
    // Only redirect if we're not already on index.php to prevent redirect loops
    $current_page = basename($_SERVER['SCRIPT_NAME']);
    if ($current_page !== 'index.php') {
        // Check if this is an AJAX request
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        // Also check if this is a POST request to an admin script (likely AJAX)
        $is_admin_ajax = $is_ajax || (
            $_SERVER['REQUEST_METHOD'] === 'POST' && 
            strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false
        );
        
        if (!$is_admin_ajax) {
            header('Location: index.php');
            exit();
        }
    }
}

require_once __DIR__ . '/db.php';

if (isset($_SESSION['role']) && $_SESSION['role'] === 'organisation_user') {
    $stmt = $pdo->prepare("SELECT unic_id FROM organisations WHERE user = ?");
    $stmt->execute([$_SESSION['user']]);
    $org = $stmt->fetch();

    if ($org) {
        $org_id = $org['unic_id'];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM working_points WHERE organisation_id = ?");
        $stmt->execute([$org_id]);
        $wp_count = $stmt->fetchColumn();

        if ($wp_count == 1) {
            $_SESSION['dual_role'] = 'workpoint_supervisor';
        }
    }
}