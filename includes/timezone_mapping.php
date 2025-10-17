<?php
/**
 * Timezone mapping for European and UK countries
 * This file maps country codes to their correct timezone identifiers
 */

$timezone_mapping = [
    // United Kingdom
    'GB' => 'Europe/London',
    'UK' => 'Europe/London',
    
    // Ireland
    'IE' => 'Europe/Dublin',
    
    // Western Europe
    'FR' => 'Europe/Paris',      // France
    'DE' => 'Europe/Berlin',     // Germany
    'IT' => 'Europe/Rome',       // Italy
    'ES' => 'Europe/Madrid',     // Spain
    'PT' => 'Europe/Lisbon',     // Portugal
    'BE' => 'Europe/Brussels',   // Belgium
    'NL' => 'Europe/Amsterdam',  // Netherlands
    'LU' => 'Europe/Luxembourg', // Luxembourg
    'CH' => 'Europe/Zurich',     // Switzerland
    'AT' => 'Europe/Vienna',     // Austria
    
    // Northern Europe
    'SE' => 'Europe/Stockholm',  // Sweden
    'NO' => 'Europe/Oslo',       // Norway
    'DK' => 'Europe/Copenhagen', // Denmark
    'FI' => 'Europe/Helsinki',   // Finland
    'IS' => 'Atlantic/Reykjavik', // Iceland
    
    // Eastern Europe
    'PL' => 'Europe/Warsaw',     // Poland
    'CZ' => 'Europe/Prague',     // Czech Republic
    'SK' => 'Europe/Bratislava', // Slovakia
    'HU' => 'Europe/Budapest',   // Hungary
    'RO' => 'Europe/Bucharest',  // Romania
    'BG' => 'Europe/Sofia',      // Bulgaria
    'HR' => 'Europe/Zagreb',     // Croatia
    'SI' => 'Europe/Ljubljana',  // Slovenia
    'EE' => 'Europe/Tallinn',    // Estonia
    'LV' => 'Europe/Riga',       // Latvia
    'LT' => 'Europe/Vilnius',    // Lithuania
    
    // Southern Europe
    'GR' => 'Europe/Athens',     // Greece
    'CY' => 'Asia/Nicosia',      // Cyprus
    'MT' => 'Europe/Malta',      // Malta
    
    // Central Europe
    'RS' => 'Europe/Belgrade',   // Serbia
    'ME' => 'Europe/Podgorica',  // Montenegro
    'BA' => 'Europe/Sarajevo',   // Bosnia and Herzegovina
    'MK' => 'Europe/Skopje',     // North Macedonia
    'AL' => 'Europe/Tirane',     // Albania
    
    // Other European countries
    'UA' => 'Europe/Kiev',       // Ukraine
    'BY' => 'Europe/Minsk',      // Belarus
    'MD' => 'Europe/Chisinau',   // Moldova
    'TR' => 'Europe/Istanbul',   // Turkey (European part)
    
    // Default fallback
    'DEFAULT' => 'Europe/London'
];

/**
 * Get timezone for a given country code
 * @param string $country_code The country code (e.g., 'GB', 'RO', 'LT')
 * @return string The timezone identifier
 */
function getTimezoneForCountry($country_code) {
    global $timezone_mapping;
    
    $country_code = strtoupper(trim($country_code));
    
    if (isset($timezone_mapping[$country_code])) {
        return $timezone_mapping[$country_code];
    }
    
    // Return default timezone if country not found
    return $timezone_mapping['DEFAULT'];
}

/**
 * Get all available country codes
 * @return array Array of country codes
 */
function getAvailableCountryCodes() {
    global $timezone_mapping;
    return array_keys($timezone_mapping);
}

/**
 * Validate if a timezone is valid
 * @param string $timezone The timezone identifier
 * @return bool True if valid, false otherwise
 */
function isValidTimezone($timezone) {
    return in_array($timezone, timezone_identifiers_list());
}
?> 