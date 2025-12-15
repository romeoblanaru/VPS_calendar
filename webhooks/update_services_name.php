<?php
/**
 * Update Services Name Webhook
 *
 * This webhook handles updating the English translation of service names in the calendar system.
 * It accepts service identification and updates the name_of_service_in_english field.
 * Supports both single service update and batch updates with partial success capability.
 *
 * Endpoint: /webhooks/update_services_name.php
 * Method: POST (JSON)
 *
 * Input Parameters (JSON) - Single Service Mode:
 * - service_id (required): ID of the service to update (maps to unic_id in services table)
 * - service_name (required): Current service name for validation (name_of_service)
 * - name_of_service_in_english (required): English translation of the service name
 *
 * Input Parameters (JSON) - Batch Mode:
 * - services (required): Array of service objects, each containing:
 *   - service_id (required): ID of the service to update
 *   - service_name (required): Current service name for validation
 *   - name_of_service_in_english (required): English translation of the service name
 *
 * Batch Mode Behavior:
 * - Each service is processed independently
 * - If one service fails, others continue processing
 * - Response includes both successful and failed updates with details
 *
 * Output: JSON response with update details or error information
 *
 * @author Calendar System
 * @version 2.1
 * @since 2025-01-15
 */

// Include required files
require_once '../includes/db.php';
require_once '../includes/webhook_logger.php';

// Set content type to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize webhook logger
$logger = new WebhookLogger($pdo, 'update_services_name');

try {
    // Get JSON input
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);

    // Check for JSON parsing errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorResponse = [
            'error' => 'Invalid JSON format: ' . json_last_error_msg(),
            'status' => 'error',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        http_response_code(400);
        echo json_encode($errorResponse);

        $logger->logError(
            'Invalid JSON input: ' . json_last_error_msg(),
            null,
            400,
            [
                'additional_data' => [
                    'raw_input' => substr($json_input, 0, 500) // Log first 500 chars
                ]
            ]
        );
        exit();
    }

    // Determine if batch mode or single mode
    $isBatchMode = isset($data['services']) && is_array($data['services']);

    // Normalize input to array format
    if ($isBatchMode) {
        $servicesArray = $data['services'];
        if (empty($servicesArray)) {
            throw new Exception('Batch mode requires at least one service in the services array');
        }
    } else {
        // Single service mode - wrap in array for uniform processing
        $servicesArray = [$data];
    }

    // Process each service individually
    $successfulUpdates = [];
    $failedUpdates = [];
    $totalRowsAffected = 0;

    foreach ($servicesArray as $index => $serviceData) {
        try {
            $service_id = $serviceData['service_id'] ?? null;
            $service_name = $serviceData['service_name'] ?? null;
            $name_of_service_in_english = $serviceData['name_of_service_in_english'] ?? null;

            // Define required fields for this service
            $required_fields = [
                'service_id' => $service_id,
                'service_name' => $service_name,
                'name_of_service_in_english' => $name_of_service_in_english
            ];

            // Validate required parameters
            $missing_fields = [];
            foreach ($required_fields as $field_name => $field_value) {
                if ($field_value === null || $field_value === '') {
                    $missing_fields[] = $field_name;
                }
            }

            if (!empty($missing_fields)) {
                throw new Exception('Missing required parameters: ' . implode(', ', $missing_fields));
            }

            // Validate service_id is numeric
            if (!is_numeric($service_id) || $service_id <= 0) {
                throw new Exception('Invalid service_id: must be a positive integer');
            }

            // Verify that service exists and service_name matches
            $stmt = $pdo->prepare("
                SELECT unic_id, name_of_service, name_of_service_in_english, id_specialist, id_work_place, id_organisation, deleted, suspended
                FROM services
                WHERE unic_id = ?
            ");
            $stmt->execute([$service_id]);
            $service = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$service) {
                throw new Exception('Service not found');
            }

            // Check if service is deleted or suspended
            if ($service['deleted'] == 1 || $service['suspended'] == 1) {
                throw new Exception('Service is deleted or suspended and cannot be updated');
            }

            // Validate that service_name matches the database
            if ($service['name_of_service'] !== $service_name) {
                throw new Exception('Service name mismatch: provided "' . $service_name . '" but actual is "' . $service['name_of_service'] . '"');
            }

            // Store old value for logging
            $old_english_name = $service['name_of_service_in_english'];

            // Trim to 100 characters max
            $name_of_service_in_english = substr($name_of_service_in_english, 0, 100);

            // Update the service English name
            $stmt = $pdo->prepare("
                UPDATE services
                SET name_of_service_in_english = ?
                WHERE unic_id = ?
            ");

            $update_success = $stmt->execute([$name_of_service_in_english, $service_id]);

            if (!$update_success) {
                throw new Exception('Failed to update service in database');
            }

            $rows_affected = $stmt->rowCount();
            $totalRowsAffected += $rows_affected;

            // Add to successful updates
            $successfulUpdates[] = [
                'index' => $index,
                'service_id' => $service_id,
                'name_of_service' => $service['name_of_service'],
                'name_of_service_in_english' => $name_of_service_in_english,
                'previous_english_name' => $old_english_name ?? 'not set',
                'specialist_id' => $service['id_specialist'],
                'working_point_id' => $service['id_work_place'],
                'organisation_id' => $service['id_organisation'],
                'rows_affected' => $rows_affected
            ];

        } catch (Exception $e) {
            // Add to failed updates
            $failedUpdates[] = [
                'index' => $index,
                'service_id' => $service_id ?? null,
                'service_name' => $service_name ?? null,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    // Build response based on mode and results
    if ($isBatchMode) {
        // Batch mode response
        $hasFailures = !empty($failedUpdates);
        $hasSuccesses = !empty($successfulUpdates);

        if ($hasSuccesses && !$hasFailures) {
            // All succeeded
            $status = 'success';
            $message = 'All services updated successfully';
            $httpCode = 200;
        } elseif ($hasSuccesses && $hasFailures) {
            // Partial success
            $status = 'partial_success';
            $message = 'Some services updated successfully, others failed';
            $httpCode = 207; // Multi-Status
        } else {
            // All failed
            $status = 'error';
            $message = 'All services failed to update';
            $httpCode = 400;
        }

        $response = [
            'status' => $status,
            'message' => $message,
            'mode' => 'batch',
            'summary' => [
                'total_requested' => count($servicesArray),
                'successful' => count($successfulUpdates),
                'failed' => count($failedUpdates),
                'total_rows_affected' => $totalRowsAffected
            ],
            'successful_updates' => $successfulUpdates,
            'failed_updates' => $failedUpdates,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        http_response_code($httpCode);
        echo json_encode($response);

        // Log based on outcome
        if ($status === 'success') {
            $logger->logSuccess($response, null, [
                'mode' => 'batch',
                'services_count' => count($successfulUpdates),
                'total_rows_affected' => $totalRowsAffected
            ]);
        } elseif ($status === 'partial_success') {
            $logger->logSuccess($response, null, [
                'mode' => 'batch_partial',
                'successful_count' => count($successfulUpdates),
                'failed_count' => count($failedUpdates),
                'total_rows_affected' => $totalRowsAffected,
                'additional_data' => [
                    'failed_services' => array_column($failedUpdates, 'service_id')
                ]
            ]);
        } else {
            $logger->logError(
                'Batch update failed for all services',
                null,
                400,
                [
                    'additional_data' => [
                        'total_failed' => count($failedUpdates),
                        'errors' => $failedUpdates
                    ]
                ]
            );
        }

    } else {
        // Single service mode
        if (!empty($successfulUpdates)) {
            $serviceData = $successfulUpdates[0];

            $response = [
                'status' => 'success',
                'message' => 'Service English name updated successfully',
                'service_details' => [
                    'service_id' => $serviceData['service_id'],
                    'name_of_service' => $serviceData['name_of_service'],
                    'name_of_service_in_english' => $serviceData['name_of_service_in_english'],
                    'previous_english_name' => $serviceData['previous_english_name'],
                    'specialist_id' => $serviceData['specialist_id'],
                    'working_point_id' => $serviceData['working_point_id'],
                    'organisation_id' => $serviceData['organisation_id']
                ],
                'rows_affected' => $serviceData['rows_affected'],
                'timestamp' => date('Y-m-d H:i:s')
            ];

            http_response_code(200);
            echo json_encode($response);

            $logger->logSuccess($response, null, [
                'service_id' => $serviceData['service_id'],
                'specialist_id' => $serviceData['specialist_id'],
                'working_point_id' => $serviceData['working_point_id'],
                'organisation_id' => $serviceData['organisation_id']
            ]);

        } else {
            // Single service failed
            $errorResponse = [
                'error' => $failedUpdates[0]['error'],
                'status' => 'error',
                'service_id' => $failedUpdates[0]['service_id'],
                'timestamp' => date('Y-m-d H:i:s')
            ];

            http_response_code(400);
            echo json_encode($errorResponse);

            $logger->logError(
                'Service update failed: ' . $failedUpdates[0]['error'],
                null,
                400,
                [
                    'service_id' => $failedUpdates[0]['service_id'],
                    'additional_data' => $failedUpdates[0]
                ]
            );
        }
    }

} catch (PDOException $e) {
    // Database error
    $errorResponse = [
        'error' => 'Database error occurred while updating service name',
        'status' => 'error',
        'timestamp' => date('Y-m-d H:i:s')
    ];

    http_response_code(500);
    echo json_encode($errorResponse);

    // Log the database error
    $logger->logError(
        'Database error during service name update: ' . $e->getMessage(),
        $e->getTraceAsString(),
        500,
        [
            'additional_data' => [
                'error_code' => $e->getCode(),
                'sql_state' => $e->errorInfo[0] ?? null
            ]
        ]
    );

} catch (Exception $e) {
    // General error
    $errorResponse = [
        'error' => $e->getMessage(),
        'status' => 'error',
        'timestamp' => date('Y-m-d H:i:s')
    ];

    http_response_code(500);
    echo json_encode($errorResponse);

    // Log the general error
    $logger->logError(
        'Error during service name update: ' . $e->getMessage(),
        $e->getTraceAsString(),
        500,
        [
            'additional_data' => [
                'error_type' => get_class($e)
            ]
        ]
    );
}
?>
