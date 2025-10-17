<?php
/**
 * Google Calendar Event Manager
 * 
 * Professional-grade class for managing Google Calendar events with Event ID storage
 * Handles create, update, delete operations with proper error handling and logging
 * Supports multi-channel booking system (web, webhook, mobile app)
 */

require_once __DIR__ . '/timezone_mapping.php';
require_once __DIR__ . '/google_calendar_logger.php';

class GoogleCalendarEventManager 
{
    private PDO $pdo;
    private $logger;
    
    public function __construct(PDO $pdo, $verbose = false) 
    {
        $this->pdo = $pdo;
        
        // Initialize enhanced logger
        $this->logger = new GoogleCalendarLogger($verbose);
    }
    
    /**
     * Create a Google Calendar event and store the Event ID
     * 
     * @param int $bookingId Booking unic_id
     * @param array $credentials Google Calendar credentials
     * @param array $eventData Event data (summary, description, start, end)
     * @return array ['success' => bool, 'error' => string, 'google_event_id' => string]
     */
    public function createEvent(int $bookingId, array $credentials, array $eventData): array
    {
        try {
            // Check if booking already has a Google Event ID (prevent duplicates)
            $existingEventId = $this->getBookingEventId($bookingId);
            if ($existingEventId) {
                $this->logger->logOperation('CREATE_DUPLICATE_CHECK', $bookingId, [
                    'existing_event_id' => $existingEventId,
                    'action' => 'Updating instead of creating'
                ]);
                return $this->updateEvent($bookingId, $credentials, $eventData);
            }
            
            // Make API call to Google Calendar
            $result = $this->makeGoogleApiCall('POST', $credentials, 'events', $eventData);
            
            if ($result['success']) {
                $googleEventId = $result['response_data']['id'] ?? null;
                if ($googleEventId) {
                    // Store Google Event ID in booking table
                    $this->storeBookingEventId($bookingId, $googleEventId);
                    $this->logger->logSuccess("Created Google Calendar event", [
                        'booking_id' => $bookingId,
                        'event_id' => $googleEventId,
                        'summary' => $eventData['summary'] ?? 'N/A',
                        'client' => $eventData['description'] ?? ''
                    ]);
                    
                    return [
                        'success' => true,
                        'google_event_id' => $googleEventId,
                        'action' => 'created'
                    ];
                } else {
                    $error = "Google Calendar API responded successfully but no event ID returned";
                    $this->logger->logError('CREATE_EVENT_NO_ID', $error, [
                        'booking_id' => $bookingId,
                        'response' => $result['response_data'] ?? null
                    ]);
                    return ['success' => false, 'error' => $error];
                }
            } else {
                $this->logger->logError('CREATE_EVENT_API_FAIL', $result['error'], [
                    'booking_id' => $bookingId,
                    'http_code' => $result['http_code'] ?? null
                ]);
                return ['success' => false, 'error' => $result['error']];
            }
            
        } catch (Exception $e) {
            $error = "Exception creating Google Calendar event: " . $e->getMessage();
            $this->logger->logError('CREATE_EVENT_EXCEPTION', $error, ['booking_id' => $bookingId]);
            return ['success' => false, 'error' => $error];
        }
    }
    
    /**
     * Update an existing Google Calendar event using stored Event ID
     * 
     * @param int $bookingId Booking unic_id
     * @param array $credentials Google Calendar credentials
     * @param array $eventData Event data (summary, description, start, end)
     * @return array ['success' => bool, 'error' => string, 'google_event_id' => string]
     */
    public function updateEvent(int $bookingId, array $credentials, array $eventData): array
    {
        try {
            // Get stored Google Event ID
            $googleEventId = $this->getBookingEventId($bookingId);
            if (!$googleEventId) {
                $this->log("No Google Event ID found for booking {$bookingId}. Creating new event instead.");
                return $this->createEvent($bookingId, $credentials, $eventData);
            }
            
            // Make API call to Google Calendar
            $result = $this->makeGoogleApiCall('PUT', $credentials, "events/{$googleEventId}", $eventData);
            
            if ($result['success']) {
                $this->logger->logSuccess("Updated Google Calendar event", [
                    'booking_id' => $bookingId,
                    'event_id' => $googleEventId
                ]);
                return [
                    'success' => true,
                    'google_event_id' => $googleEventId,
                    'action' => 'updated'
                ];
            } else {
                // If event not found, create a new one
                if (strpos($result['error'], '404') !== false || strpos($result['error'], 'not found') !== false) {
                    $this->logger->logOperation('UPDATE_NOT_FOUND', $bookingId, [
                        'event_id' => $googleEventId,
                        'action' => 'Creating new event'
                    ]);
                    $this->clearBookingEventId($bookingId); // Clear invalid Event ID
                    return $this->createEvent($bookingId, $credentials, $eventData);
                }
                
                $this->logger->logError('UPDATE_EVENT_API_FAIL', $result['error'], [
                    'booking_id' => $bookingId,
                    'event_id' => $googleEventId
                ]);
                return ['success' => false, 'error' => $result['error']];
            }
            
        } catch (Exception $e) {
            $error = "Exception updating Google Calendar event: " . $e->getMessage();
            $this->logger->logError('UPDATE_EVENT_EXCEPTION', $error, ['booking_id' => $bookingId]);
            return ['success' => false, 'error' => $error];
        }
    }
    
    /**
     * Delete a Google Calendar event using stored Event ID
     * 
     * @param int $bookingId Booking unic_id
     * @param array $credentials Google Calendar credentials
     * @return array ['success' => bool, 'error' => string]
     */
    public function deleteEvent(int $bookingId, array $credentials): array
    {
        try {
            // Get stored Google Event ID
            $googleEventId = $this->getBookingEventId($bookingId);
            if (!$googleEventId) {
                $this->log("No Google Event ID found for booking {$bookingId}. Nothing to delete.");
                return ['success' => true, 'message' => 'No Google Calendar event to delete'];
            }
            
            // Make API call to Google Calendar
            $result = $this->makeGoogleApiCall('DELETE', $credentials, "events/{$googleEventId}");
            
            if ($result['success']) {
                // Clear stored Event ID
                $this->clearBookingEventId($bookingId);
                $this->logger->logDeletion($bookingId, $googleEventId, 'SUCCESS', [
                    'message' => 'Event deleted from Google Calendar',
                    'booking_cleared' => true
                ]);
                return [
                    'success' => true,
                    'google_event_id' => $googleEventId,
                    'action' => 'deleted'
                ];
            } else {
                // If event not found, consider it successfully deleted
                if (strpos($result['error'], '404') !== false || strpos($result['error'], 'not found') !== false) {
                    $this->clearBookingEventId($bookingId);
                    $this->logger->logDeletion($bookingId, $googleEventId, 'ALREADY_DELETED', [
                        'http_code' => 404,
                        'message' => 'Event was already removed from Google Calendar'
                    ]);
                    return ['success' => true, 'message' => 'Google Calendar event already deleted'];
                }
                
                $this->logger->logDeletion($bookingId, $googleEventId, 'FAILED', [
                    'error' => $result['error'],
                    'http_code' => $result['http_code'] ?? null
                ]);
                return ['success' => false, 'error' => $result['error']];
            }
            
        } catch (Exception $e) {
            $error = "Exception deleting Google Calendar event: " . $e->getMessage();
            $this->logger->logError('DELETE_EVENT_EXCEPTION', $error, [
                'booking_id' => $bookingId,
                'event_id' => $googleEventId
            ]);
            return ['success' => false, 'error' => $error];
        }
    }
    
    /**
     * Delete a Google Calendar event using a specific Event ID
     * (Used for cleanup of canceled bookings)
     * 
     * @param string $googleEventId Google Calendar Event ID
     * @param array $credentials Google Calendar credentials
     * @return array ['success' => bool, 'error' => string]
     */
    public function deleteEventById(string $googleEventId, array $credentials): array
    {
        try {
            // Make API call to Google Calendar
            $result = $this->makeGoogleApiCall('DELETE', $credentials, "events/{$googleEventId}");
            
            if ($result['success']) {
                $this->logger->logDeletion(null, $googleEventId, 'SUCCESS', [
                    'message' => 'Event deleted by ID directly'
                ]);
                return [
                    'success' => true,
                    'google_event_id' => $googleEventId,
                    'action' => 'deleted'
                ];
            } else {
                // If event not found, consider it successfully deleted
                if (strpos($result['error'], '404') !== false || strpos($result['error'], 'not found') !== false) {
                    $this->logger->logDeletion(null, $googleEventId, 'ALREADY_DELETED', [
                        'http_code' => 404,
                        'message' => 'Event was already removed'
                    ]);
                    return ['success' => true, 'message' => 'Google Calendar event already deleted'];
                }
                
                $this->logger->logDeletion(null, $googleEventId, 'FAILED', [
                    'error' => $result['error'],
                    'http_code' => $result['http_code'] ?? null
                ]);
                return ['success' => false, 'error' => $result['error']];
            }
            
        } catch (Exception $e) {
            $error = "Exception deleting Google Calendar event by ID: " . $e->getMessage();
            $this->logger->logError('DELETE_BY_ID_EXCEPTION', $error, ['event_id' => $googleEventId]);
            return ['success' => false, 'error' => $error];
        }
    }
    
    /**
     * Build Google Calendar event data from booking information
     * 
     * @param array $booking Booking data from database
     * @param string $serviceName Service name
     * @param string $country Working point country for timezone
     * @return array Google Calendar event data
     */
    public function buildEventData(array $booking, string $serviceName = null, string $country = null): array
    {
        try {
            $startDateTime = new DateTime($booking['booking_start_datetime']);
            $endDateTime = new DateTime($booking['booking_end_datetime']);
            
            // Get timezone from working point's country using existing function
            $timezone = $country ? getTimezoneForCountry($country) : 'Europe/London';
            
            return [
                'summary' => $serviceName ?: 'Booking',
                'description' => sprintf(
                    "Booking ID: %s\nClient: %s\nPhone: %s\nService: %s\nBooked on: %s\nBooked via: %s",
                    $booking['unic_id'] ?? 'N/A',
                    $booking['client_full_name'] ?? 'N/A',
                    $booking['client_phone_nr'] ?? 'N/A',
                    $serviceName ?: 'N/A',
                    $booking['day_of_creation'] ?? 'N/A',
                    $booking['received_through'] ?? 'N/A'
                ),
                'start' => [
                    'dateTime' => $startDateTime->format('Y-m-d\TH:i:s'),
                    'timeZone' => $timezone
                ],
                'end' => [
                    'dateTime' => $endDateTime->format('Y-m-d\TH:i:s'),
                    'timeZone' => $timezone
                ]
            ];
        } catch (Exception $e) {
            $this->logger->logError('BUILD_EVENT_DATA', $e->getMessage(), ['booking' => $booking]);
            throw $e;
        }
    }
    
    /**
     * Get stored Google Event ID for a booking
     * 
     * @param int $bookingId Booking unic_id
     * @return string|null Google Event ID or null if not found
     */
    public function getBookingEventId(int $bookingId): ?string
    {
        try {
            // First check active booking table
            $stmt = $this->pdo->prepare("SELECT google_event_id FROM booking WHERE unic_id = ? LIMIT 1");
            $stmt->execute([$bookingId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['google_event_id'])) {
                return $result['google_event_id'];
            }
            
            // If not found, check cancelled bookings table
            $stmt = $this->pdo->prepare("SELECT google_event_id FROM booking_canceled WHERE unic_id = ? LIMIT 1");
            $stmt->execute([$bookingId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['google_event_id'] ?? null;
        } catch (Exception $e) {
            $this->logger->logError('GET_EVENT_ID', $e->getMessage(), ['booking_id' => $bookingId]);
            return null;
        }
    }
    
    /**
     * Store Google Event ID for a booking
     * 
     * @param int $bookingId Booking unic_id
     * @param string $googleEventId Google Calendar Event ID
     * @return bool Success status
     */
    private function storeBookingEventId(int $bookingId, string $googleEventId): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE booking SET google_event_id = ? WHERE unic_id = ?");
            return $stmt->execute([$googleEventId, $bookingId]);
        } catch (Exception $e) {
            $this->logger->logError('STORE_EVENT_ID', $e->getMessage(), [
                'booking_id' => $bookingId,
                'event_id' => $googleEventId
            ]);
            return false;
        }
    }
    
    /**
     * Clear stored Google Event ID for a booking
     * 
     * @param int $bookingId Booking unic_id
     * @return bool Success status
     */
    private function clearBookingEventId(int $bookingId): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE booking SET google_event_id = NULL WHERE unic_id = ?");
            return $stmt->execute([$bookingId]);
        } catch (Exception $e) {
            $this->logger->logError('CLEAR_EVENT_ID', $e->getMessage(), ['booking_id' => $bookingId]);
            return false;
        }
    }
    
    /**
     * Make Google Calendar API call
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param array $credentials Google Calendar credentials
     * @param string $endpoint API endpoint (e.g., 'events', 'events/event_id')
     * @param array $data Optional data for POST/PUT requests
     * @return array ['success' => bool, 'error' => string, 'response_data' => array]
     */
    private function makeGoogleApiCall(string $method, array $credentials, string $endpoint, array $data = []): array
    {
        try {
            $ch = curl_init();
            
            $url = "https://www.googleapis.com/calendar/v3/calendars/{$credentials['calendar_id']}/{$endpoint}";
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $credentials['access_token'],
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]
            ]);
            
            if (in_array($method, ['POST', 'PUT']) && !empty($data)) {
                $jsonData = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            }
            
            // Log all API requests
            $this->logger->logApiRequest($method, $url, 
                in_array($method, ['POST', 'PUT']) ? $data : null,
                [
                    'Authorization' => 'Bearer ' . substr($credentials['access_token'], 0, 10) . '...',
                    'Content-Type' => 'application/json'
                ]
            );
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                $error = "cURL Error: {$curlError}";
                $this->logger->logApiResponse(0, null, $error);
                return ['success' => false, 'error' => $error];
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                $responseData = $response ? json_decode($response, true) : [];
                $this->logger->logApiResponse($httpCode, $responseData);
                return ['success' => true, 'response_data' => $responseData, 'http_code' => $httpCode];
            } else {
                $errorDetail = "HTTP {$httpCode}";
                if ($response) {
                    $decodedResponse = json_decode($response, true);
                    if ($decodedResponse && isset($decodedResponse['error'])) {
                        $errorDetail .= " - " . $decodedResponse['error']['message'];
                        if (isset($decodedResponse['error']['code'])) {
                            $errorDetail .= " (Code: " . $decodedResponse['error']['code'] . ")";
                        }
                    } else {
                        $errorDetail .= " - Response: " . substr($response, 0, 200);
                    }
                }
                $this->logger->logApiResponse($httpCode, $response, $errorDetail);
                return ['success' => false, 'error' => $errorDetail, 'http_code' => $httpCode];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => "Exception: " . $e->getMessage()];
        }
    }
    
    /**
     * Get the logger instance (for external use)
     * 
     * @return GoogleCalendarLogger
     */
    public function getLogger(): GoogleCalendarLogger
    {
        return $this->logger;
    }
}
?> 