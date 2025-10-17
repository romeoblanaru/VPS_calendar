<?php
/**
 * Timezone Configuration
 * Maps countries to proper timezone identifiers
 */

$timezone_map = [
    // European countries
    'RO' => 'Europe/Bucharest',      // Romania
    'LT' => 'Europe/Vilnius',        // Lithuania
    'LV' => 'Europe/Riga',           // Latvia
    'EE' => 'Europe/Tallinn',        // Estonia
    'PL' => 'Europe/Warsaw',         // Poland
    'CZ' => 'Europe/Prague',         // Czech Republic
    'SK' => 'Europe/Bratislava',     // Slovakia
    'HU' => 'Europe/Budapest',       // Hungary
    'BG' => 'Europe/Sofia',          // Bulgaria
    'HR' => 'Europe/Zagreb',         // Croatia
    'SI' => 'Europe/Ljubljana',      // Slovenia
    'AT' => 'Europe/Vienna',         // Austria
    'DE' => 'Europe/Berlin',         // Germany
    'FR' => 'Europe/Paris',          // France
    'IT' => 'Europe/Rome',           // Italy
    'ES' => 'Europe/Madrid',         // Spain
    'PT' => 'Europe/Lisbon',         // Portugal
    'NL' => 'Europe/Amsterdam',      // Netherlands
    'BE' => 'Europe/Brussels',       // Belgium
    'LU' => 'Europe/Luxembourg',     // Luxembourg
    'IE' => 'Europe/Dublin',         // Ireland
    'DK' => 'Europe/Copenhagen',     // Denmark
    'SE' => 'Europe/Stockholm',      // Sweden
    'NO' => 'Europe/Oslo',           // Norway
    'FI' => 'Europe/Helsinki',       // Finland
    'IS' => 'Atlantic/Reykjavik',    // Iceland
    'MT' => 'Europe/Malta',          // Malta
    'CY' => 'Asia/Nicosia',          // Cyprus
    'GR' => 'Europe/Athens',         // Greece
    
    // UK and related
    'GB' => 'Europe/London',         // United Kingdom
    'UK' => 'Europe/London',         // United Kingdom (alternative)
    
    // Other European countries
    'CH' => 'Europe/Zurich',         // Switzerland
    'LI' => 'Europe/Vaduz',          // Liechtenstein
    'MC' => 'Europe/Monaco',         // Monaco
    'SM' => 'Europe/San_Marino',     // San Marino
    'VA' => 'Europe/Vatican',        // Vatican City
    'AD' => 'Europe/Andorra',        // Andorra
    
    // Default fallback
    'DEFAULT' => 'UTC'
];

/**
 * Get timezone for a country
 * @param string $country_code Two-letter country code
 * @return string Timezone identifier
 */
function getTimezoneForCountry($country_code) {
    global $timezone_map;
    
    $country_code = strtoupper(trim($country_code));
    
    if (isset($timezone_map[$country_code])) {
        return $timezone_map[$country_code];
    }
    
    return $timezone_map['DEFAULT'];
}

/**
 * Get timezone for an organization
 * @param array $organisation Organization data array
 * @return string Timezone identifier
 */
function getTimezoneForOrganisation($organisation) {
    // Default to GB (United Kingdom) if country is missing or empty
    $country = (isset($organisation['country']) && !empty($organisation['country'])) ? $organisation['country'] : 'GB';
    return getTimezoneForCountry($country);
}

/**
 * Set timezone for an organization
 * @param array $organisation Organization data array
 * @return bool Success status
 */
function setTimezoneForOrganisation($organisation) {
    $timezone = getTimezoneForOrganisation($organisation);
    
    try {
        date_default_timezone_set($timezone);
        return true;
    } catch (Exception $e) {
        error_log("Failed to set timezone: $timezone - " . $e->getMessage());
        // Fallback to UTC
        date_default_timezone_set('UTC');
        return false;
    }
}

/**
 * Get current time in organization timezone
 * @param array $organisation Organization data array
 * @param string $format Date format (default: 'Y-m-d H:i:s')
 * @return string Formatted date/time
 */
function getCurrentTimeInOrganisationTimezone($organisation, $format = 'Y-m-d H:i:s') {
    $timezone = getTimezoneForOrganisation($organisation);
    $date = new DateTime('now', new DateTimeZone($timezone));
    return $date->format($format);
}

/**
 * Get current time in organization timezone without changing global timezone
 * @param array $organisation Organization data array
 * @param string $format Date format (default: 'Y-m-d H:i:s')
 * @return string Formatted date/time
 */
function getCurrentTimeInOrganisationTimezoneOnly($organisation, $format = 'Y-m-d H:i:s') {
    try {
        $timezone = getTimezoneForOrganisation($organisation);
        $date = new DateTime('now', new DateTimeZone($timezone));
        return $date->format($format);
    } catch (Exception $e) {
        error_log("Timezone error in getCurrentTimeInOrganisationTimezoneOnly: " . $e->getMessage());
        // Fallback to current server time
        return date($format);
    }
}

/**
 * Get timezone for a working point
 * @param array $working_point Working point data array
 * @return string Timezone identifier
 */
function getTimezoneForWorkingPoint($working_point) {
    // Default to GB (United Kingdom) if country is missing or empty
    $country = (isset($working_point['country']) && !empty($working_point['country'])) ? $working_point['country'] : 'GB';
    return getTimezoneForCountry($country);
}

/**
 * Set timezone for a working point
 * @param array $working_point Working point data array
 * @return bool Success status
 */
function setTimezoneForWorkingPoint($working_point) {
    $timezone = getTimezoneForWorkingPoint($working_point);
    
    try {
        date_default_timezone_set($timezone);
        return true;
    } catch (Exception $e) {
        error_log("Failed to set timezone: $timezone - " . $e->getMessage());
        // Fallback to UTC
        date_default_timezone_set('UTC');
        return false;
    }
}

/**
 * Get current time in working point timezone
 * @param array $working_point Working point data array
 * @param string $format Date format (default: 'Y-m-d H:i:s')
 * @return string Formatted date/time
 */
function getCurrentTimeInWorkingPointTimezone($working_point, $format = 'Y-m-d H:i:s') {
    $timezone = getTimezoneForWorkingPoint($working_point);
    $date = new DateTime('now', new DateTimeZone($timezone));
    return $date->format($format);
}

/**
 * Get current time in working point timezone without changing global timezone
 * @param array $working_point Working point data array
 * @param string $format Date format (default: 'Y-m-d H:i:s')
 * @return string Formatted date/time
 */
function getCurrentTimeInWorkingPointTimezoneOnly($working_point, $format = 'Y-m-d H:i:s') {
    try {
        $timezone = getTimezoneForWorkingPoint($working_point);
        $date = new DateTime('now', new DateTimeZone($timezone));
        return $date->format($format);
    } catch (Exception $e) {
        error_log("Timezone error in getCurrentTimeInWorkingPointTimezoneOnly: " . $e->getMessage());
        // Fallback to current server time
        return date($format);
    }
}

/**
 * Convert time between timezones
 * @param string $time Time string
 * @param string $from_timezone Source timezone
 * @param string $to_timezone Target timezone
 * @param string $format Output format
 * @return string Converted time
 */
function convertTimezone($time, $from_timezone, $to_timezone, $format = 'Y-m-d H:i:s') {
    try {
        $date = new DateTime($time, new DateTimeZone($from_timezone));
        $date->setTimezone(new DateTimeZone($to_timezone));
        return $date->format($format);
    } catch (Exception $e) {
        error_log("Timezone conversion failed: " . $e->getMessage());
        return $time;
    }
}
?> 