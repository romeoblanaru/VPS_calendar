<?php
/**
 * Webhook: get_messinger_credentials
 * 
 * Returns Facebook Messenger credentials and related working point + organisation info.
 * Supports GET and POST. Parameters:
 * - page_id (required): Facebook Page ID
 */

require_once '../includes/db.php';
require_once '../includes/webhook_logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$logger = new WebhookLogger($pdo, 'get_messinger_credentials');

try {
    // Read JSON body if POST with application/json
    $rawBody = file_get_contents('php://input');
    $jsonBody = null;
    if (!empty($rawBody)) {
        $maybeJson = json_decode($rawBody, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $jsonBody = $maybeJson;
        }
    }

    $page_id = $_GET['page_id'] ?? $_POST['page_id'] ?? ($jsonBody['page_id'] ?? null);

    if (!$page_id) {
        http_response_code(400);
        $resp = [
            'status' => 'error',
            'message' => 'Missing required parameter: page_id',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo json_encode($resp);
        $logger->logError('Missing required parameter: page_id', null, 400);
        exit();
    }

    // Lookup credentials by Facebook Page ID
    $sql = "
        SELECT wsm.*, wp.unic_id as workpoint_unic_id, wp.name_of_the_place, wp.address, 
               wp.lead_person_name, wp.lead_person_phone_nr, wp.workplace_phone_nr, wp.booking_phone_nr, wp.email as workplace_email,
               org.unic_id as organisation_unic_id, org.alias_name, org.oficial_company_name, org.email_address, org.www_address, org.country
        FROM workpoint_social_media wsm
        JOIN working_points wp ON wp.unic_id = wsm.workpoint_id
        JOIN organisations org ON org.unic_id = wp.organisation_id
        WHERE wsm.platform = 'facebook_messenger' AND wsm.facebook_page_id = ?
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$page_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(200);
        $resp = [
            'status' => 'unsuccesful',
            'message' => 'No Facebook Messenger credentials found for page_id: ' . $page_id,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo json_encode($resp);
        $logger->logError('Facebook Messenger credentials not found for page_id: ' . $page_id, null, 200, [
            'additional_data' => [
                'platform' => 'facebook_messenger',
                'page_id' => $page_id
            ]
        ]);
        exit();
    }

    $response = [
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'query' => [ 'page_id' => $page_id ],
        'credentials' => [
            'platform' => 'facebook_messenger',
            'facebook_page_id' => $row['facebook_page_id'],
            'facebook_page_access_token' => $row['facebook_page_access_token'],
            'facebook_app_id' => $row['facebook_app_id'],
            'facebook_app_secret' => $row['facebook_app_secret'],
            'facebook_webhook_verify_token' => FACEBOOK_WEBHOOK_VERIFY_TOKEN,
            'facebook_webhook_url' => FACEBOOK_WEBHOOK_URL,
            'is_active' => (int)$row['is_active'],
            'last_test_status' => $row['last_test_status'],
            'last_test_at' => $row['last_test_at'],
            'last_test_message' => $row['last_test_message']
        ],
        'working_point' => [
            'unic_id' => $row['workpoint_unic_id'],
            'name_of_the_place' => $row['name_of_the_place'],
            'address' => $row['address'],
            'lead_person_name' => $row['lead_person_name'],
            'lead_person_phone_nr' => $row['lead_person_phone_nr'],
            'workplace_phone_nr' => $row['workplace_phone_nr'],
            'booking_phone_nr' => $row['booking_phone_nr'],
            'email' => $row['workplace_email']
        ],
        'company_details' => [
            'unic_id' => $row['organisation_unic_id'],
            'alias_name' => $row['alias_name'],
            'official_company_name' => $row['oficial_company_name'] ?? 'unavailable',
            'email_address' => $row['email_address'] ?? 'unavailable',
            'www_address' => $row['www_address'] ?? 'unavailable',
            'country' => $row['country'] ?? 'unavailable'
        ]
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);

    $logger->logSuccess($response, null, [
        'related_working_point_id' => $row['workpoint_unic_id'],
        'related_organisation_id' => $row['organisation_unic_id'],
        'additional_data' => [
            'platform' => 'facebook_messenger',
            'page_id' => $page_id,
            'is_active' => (int)$row['is_active']
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    $resp = [
        'status' => 'error',
        'message' => 'Database error occurred',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    echo json_encode($resp);
    $logger->logError('DB error: ' . $e->getMessage(), $e->getTraceAsString(), 500);
} catch (Exception $e) {
    http_response_code(500);
    $resp = [
        'status' => 'error',
        'message' => 'Unexpected error occurred',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    echo json_encode($resp);
    $logger->logError('Unexpected error: ' . $e->getMessage(), $e->getTraceAsString(), 500);
}
