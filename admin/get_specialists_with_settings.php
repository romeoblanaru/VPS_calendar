<?php
require_once '../includes/db.php';
require_once '../includes/specialist_settings.php';

header('Content-Type: application/json');

try {
    $workpoint_id = $_GET['workpoint_id'] ?? null;
    
    if (!$workpoint_id) {
        throw new Exception('Workpoint ID is required');
    }
    
    // Get specialists for this workpoint with their settings (only those with at least one non-zero shift)
    $stmt = $pdo->prepare("
        SELECT s.*, ssa.back_color, ssa.foreground_color, ssa.daily_email_enabled,
               ssa.specialist_can_delete_booking, ssa.specialist_can_modify_booking,
               ssa.specialist_nr_visible_to_client, ssa.specialist_email_visible_to_client,
               ssa.specialist_can_add_services, ssa.specialist_can_modify_services,
               ssa.specialist_can_delete_services
        FROM specialists s
        INNER JOIN (
            SELECT specialist_id 
            FROM working_program 
            WHERE working_place_id = ?
              AND ((shift1_start <> '00:00:00' AND shift1_end <> '00:00:00')
                OR (shift2_start <> '00:00:00' AND shift2_end <> '00:00:00')
                OR (shift3_start <> '00:00:00' AND shift3_end <> '00:00:00'))
            GROUP BY specialist_id
        ) wpr ON s.unic_id = wpr.specialist_id 
        LEFT JOIN specialists_setting_and_attr ssa ON s.unic_id = ssa.specialist_id
        ORDER BY s.name
    ");
    $stmt->execute([$workpoint_id]);
    $specialists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get services for each specialist
    $stmt = $pdo->prepare("
        SELECT 
            s.id_specialist,
            s.unic_id as service_id,
            s.name_of_service,
            s.duration,
            s.price_of_service
        FROM services s
        WHERE s.id_work_place = ? AND (s.deleted = 0 OR s.deleted IS NULL)
        ORDER BY s.name_of_service
    ");
    $stmt->execute([$workpoint_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group services by specialist
    $servicesBySpecialist = [];
    foreach ($services as $service) {
        $specialist_id = $service['id_specialist'];
        if (!isset($servicesBySpecialist[$specialist_id])) {
            $servicesBySpecialist[$specialist_id] = [];
        }
        $servicesBySpecialist[$specialist_id][] = [
            'service_id' => $service['service_id'],
            'name_of_service' => $service['name_of_service'],
            'duration' => $service['duration'],
            'price_of_service' => $service['price_of_service']
        ];
    }
    
    // Process each specialist to ensure they have colors and add their services
    foreach ($specialists as &$specialist) {
        if (empty($specialist['back_color']) || empty($specialist['foreground_color'])) {
            // Generate random colors
            $back_color = generateRandomColor();
            $foreground_color = generateContrastColor($back_color);
            
            // Save to database
            updateSpecialistSettings($specialist['unic_id'], [
                'back_color' => $back_color,
                'foreground_color' => $foreground_color
            ]);
            
            // Update the specialist data
            $specialist['back_color'] = $back_color;
            $specialist['foreground_color'] = $foreground_color;
        }
        
        // Add services for this specialist
        $specialist['services'] = $servicesBySpecialist[$specialist['unic_id']] ?? [];
    }
    
    echo json_encode([
        'success' => true,
        'specialists' => $specialists
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Generate a random color
 */
function generateRandomColor() {
    $colors = [
        '#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe',
        '#43e97b', '#38f9d7', '#fa709a', '#fee140', '#a8edea', '#fed6e3',
        '#ffecd2', '#fcb69f', '#ff9a9e', '#fecfef', '#fecfef', '#fad0c4',
        '#ffd1ff', '#a8caba', '#5d4e75', '#ffecd2', '#fcb69f', '#667eea'
    ];
    
    return $colors[array_rand($colors)];
}

/**
 * Generate a contrasting color for text
 */
function generateContrastColor($hexColor) {
    // Remove # if present
    $hex = ltrim($hexColor, '#');
    
    // Convert to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Calculate luminance
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    
    // Return black or white based on luminance
    return $luminance > 0.5 ? '#000000' : '#ffffff';
}
?> 