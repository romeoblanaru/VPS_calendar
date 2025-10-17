<?php
/**
 * Specialist Settings and Attributes Helper Functions
 * 
 * This file contains functions to manage specialist settings including:
 * - Color settings for calendar display
 * - Daily email notification preferences
 */

/**
 * Get specialist settings by specialist ID
 * 
 * @param string $specialist_id The specialist's unic_id
 * @return array|null Array with settings or null if not found
 */
function getSpecialistSettings($specialist_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM specialists_setting_and_attr 
            WHERE specialist_id = ?
        ");
        $stmt->execute([$specialist_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting specialist settings: " . $e->getMessage());
        return null;
    }
}

/**
 * Create or update specialist settings
 * 
 * @param string $specialist_id The specialist's unic_id
 * @param array $settings Array with settings to update
 * @return bool Success status
 */
function updateSpecialistSettings($specialist_id, $settings) {
    global $pdo;
    
    try {
        // Check if settings exist for this specialist
        $existing = getSpecialistSettings($specialist_id);
        
        if ($existing) {
            // Update existing settings
            $sql = "UPDATE specialists_setting_and_attr SET ";
            $params = [];
            $updates = [];
            
            if (isset($settings['back_color'])) {
                $updates[] = "back_color = ?";
                $params[] = $settings['back_color'];
            }
            if (isset($settings['foreground_color'])) {
                $updates[] = "foreground_color = ?";
                $params[] = $settings['foreground_color'];
            }
            if (isset($settings['daily_email_enabled'])) {
                $updates[] = "daily_email_enabled = ?";
                $params[] = $settings['daily_email_enabled'];
            }
            
            if (empty($updates)) {
                return true; // No changes to make
            }
            
            $sql .= implode(", ", $updates) . " WHERE specialist_id = ?";
            $params[] = $specialist_id;
            
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($params);
        } else {
            // Create new settings
            $sql = "INSERT INTO specialists_setting_and_attr (specialist_id, back_color, foreground_color, daily_email_enabled) 
                    VALUES (?, ?, ?, ?)";
            
            $back_color = $settings['back_color'] ?? '#667eea';
            $foreground_color = $settings['foreground_color'] ?? '#ffffff';
            $daily_email_enabled = $settings['daily_email_enabled'] ?? 0;
            
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$specialist_id, $back_color, $foreground_color, $daily_email_enabled]);
        }
    } catch (PDOException $e) {
        error_log("Error updating specialist settings: " . $e->getMessage());
        return false;
    }
}

/**
 * Get specialist color settings for calendar display
 * 
 * @param string $specialist_id The specialist's unic_id
 * @return array Array with back_color and foreground_color
 */
function getSpecialistColors($specialist_id) {
    $settings = getSpecialistSettings($specialist_id);
    
    if ($settings) {
        return [
            'back_color' => $settings['back_color'],
            'foreground_color' => $settings['foreground_color']
        ];
    }
    
    // Return default colors if no settings found
    return [
        'back_color' => '#667eea',
        'foreground_color' => '#ffffff'
    ];
}

/**
 * Check if specialist has daily email enabled
 * 
 * @param string $specialist_id The specialist's unic_id
 * @return bool True if daily email is enabled
 */
function isDailyEmailEnabled($specialist_id) {
    $settings = getSpecialistSettings($specialist_id);
    return $settings ? (bool)$settings['daily_email_enabled'] : false;
}

/**
 * Get all specialists with daily email enabled
 * 
 * @return array Array of specialist IDs with daily email enabled
 */
function getSpecialistsWithDailyEmail() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT specialist_id 
            FROM specialists_setting_and_attr 
            WHERE daily_email_enabled = 1
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error getting specialists with daily email: " . $e->getMessage());
        return [];
    }
}

/**
 * Initialize default settings for a new specialist
 * 
 * @param string $specialist_id The specialist's unic_id
 * @return bool Success status
 */
function initializeSpecialistSettings($specialist_id) {
    $default_settings = [
        'back_color' => '#667eea',
        'foreground_color' => '#ffffff',
        'daily_email_enabled' => 0
    ];
    
    return updateSpecialistSettings($specialist_id, $default_settings);
}

/**
 * Get specialist settings with specialist details
 * 
 * @param string $specialist_id The specialist's unic_id
 * @return array|null Array with specialist and settings data
 */
function getSpecialistWithSettings($specialist_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, ssa.back_color, ssa.foreground_color, ssa.daily_email_enabled
            FROM specialists s
            LEFT JOIN specialists_setting_and_attr ssa ON s.unic_id = ssa.specialist_id
            WHERE s.unic_id = ?
        ");
        $stmt->execute([$specialist_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting specialist with settings: " . $e->getMessage());
        return null;
    }
}
?> 