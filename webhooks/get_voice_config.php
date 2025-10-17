<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include database connection
require_once __DIR__ . '/../includes/db.php';

try {
    // Get parameters from URL
    $phone_nr = isset($_GET['phone_nr']) ? trim($_GET['phone_nr']) : '';
    $ip = isset($_GET['ip']) ? trim($_GET['ip']) : '';
    
    $workpoint_id = null;
    $found_via_ip = false;
    
    // Find workpoint by phone number
    if (!empty($phone_nr)) {
        $stmt = $pdo->prepare("SELECT unic_id FROM working_points WHERE booking_phone_nr = ? LIMIT 1");
        $stmt->execute([$phone_nr]);
        $workpoint = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($workpoint) {
            $workpoint_id = $workpoint['unic_id'];
        }
    }
    
    // Find workpoint by IP if phone number not found or not provided
    if (is_null($workpoint_id) && !empty($ip)) {
        // Step 1: Get phone number associated with this IP from ip_address table
        $stmt = $pdo->prepare("SELECT phone_number FROM ip_address WHERE ip_address = ? LIMIT 1");
        $stmt->execute([$ip]);
        $ip_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ip_record) {
            // Use the phone number found via IP to search for workpoint
            $phone_nr = '+' . $ip_record['phone_number'];
            $found_via_ip = true;
            
            // Search for workpoint with this phone number
            $stmt = $pdo->prepare("SELECT unic_id FROM working_points WHERE booking_phone_nr = ? LIMIT 1");
            $stmt->execute([$phone_nr]);
            $workpoint = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($workpoint) {
                $workpoint_id = $workpoint['unic_id'];
            }
        }
    }
    
    // Check if specific phone/IP was provided but not found
    if (is_null($workpoint_id) && (!empty($phone_nr) || !empty($ip))) {
        $identifier = !empty($phone_nr) ? "phone number '$phone_nr'" : "IP address '$ip'";
        echo json_encode([
            'success' => false,
            'error' => "Workpoint for given $identifier could not be found",
            'data' => null
        ]);
        exit;
    }
    
    // If no workpoint found and no specific identifier provided, return error
    if (is_null($workpoint_id) && empty($phone_nr) && empty($ip)) {
        echo json_encode([
            'success' => false,
            'error' => 'Needs a ip or phone number as a parameter',
            'data' => null
        ]);
        exit;
    }
    
    // If no workpoint found but no specific parameters given, get default (smallest ID) - This case shouldn't happen now
    if (is_null($workpoint_id)) {
        $stmt = $pdo->prepare("SELECT unic_id FROM working_points ORDER BY unic_id ASC LIMIT 1");
        $stmt->execute();
        $workpoint = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($workpoint) {
            $workpoint_id = $workpoint['unic_id'];
        }
    }
    
    // If still no workpoint found, return error
    if (is_null($workpoint_id)) {
        echo json_encode([
            'success' => false,
            'error' => 'No workpoints found in database',
            'data' => null
        ]);
        exit;
    }
    
    // Get voice configuration for the workpoint
    $stmt = $pdo->prepare("
        SELECT 
            vc.*,
            w.name_of_the_place as workpoint_name,
            w.booking_phone_nr as phone_number,
            w.ip_address,
            w.language as workpoint_language
        FROM voice_config vc
        JOIN working_points w ON vc.workpoint_id = w.unic_id
        WHERE vc.workpoint_id = ? AND vc.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$workpoint_id]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        // Return default configuration if no specific config found
        echo json_encode([
            'success' => false,
            'error' => 'No voice configuration found for workpoint',
            'workpoint_id' => $workpoint_id,
            'data' => null
        ]);
        exit;
    }
    
    // Parse JSON fields
    if (!empty($config['voice_settings'])) {
        $config['voice_settings'] = json_decode($config['voice_settings'], true);
    }
    
    // Remove sensitive information for security
    $safe_config = [
        'workpoint_id' => $config['workpoint_id'],
        'workpoint_name' => $config['workpoint_name'],
        'phone_number' => $config['phone_number'],
        'ip_address' => $found_via_ip ? $ip : $config['ip_address'],
        'workpoint_language' => $config['workpoint_language'],
        'tts_model' => $config['tts_model'],
        'tts_access_link' => $config['tts_access_link'],
        'stt_model' => $config['stt_model'],
        'language' => $config['language'],
        'welcome_message' => $config['welcome_message'],
        'answer_after_rings' => intval($config['answer_after_rings']),
        'voice_settings' => $config['voice_settings'],
        'vad_threshold' => floatval($config['vad_threshold']),
        'silence_timeout' => intval($config['silence_timeout']),
        'audio_format' => $config['audio_format'],
        'buffer_size' => intval($config['buffer_size']),
        'is_active' => boolval($config['is_active']),
        'updated_at' => $config['updated_at']
    ];
    
    // Include API key only if explicitly requested (for security)
    if (isset($_GET['include_key']) && $_GET['include_key'] === '1') {
        $safe_config['tts_secret_key'] = $config['tts_secret_key'];
    }
    
    // Determine search method based on what was used to find the workpoint
    $search_method = 'default';
    $actual_ip = null;
    
    if (!empty($phone_nr) && $config['phone_number'] == $phone_nr) {
        $search_method = 'phone_number';
    } elseif ($found_via_ip && !empty($ip)) {
        $search_method = 'ip_address';
        $actual_ip = $ip;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Voice configuration retrieved successfully',
        'search_method' => $search_method,
        'data' => $safe_config
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'data' => null
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'General error: ' . $e->getMessage(),
        'data' => null
    ]);
}
?>