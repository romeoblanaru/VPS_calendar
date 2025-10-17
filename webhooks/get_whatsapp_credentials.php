<?php
/**
 * Webhook: get_whatsapp_credentilas
 * 
 * Returns WhatsApp Business credentials and related working point + organisation info.
 * Supports GET and POST. Parameters:
 * - whatsapp_phone_nr_id (optional): WhatsApp Phone Number ID (preferred when provided)
 * - whatsapp_business_acount_id (optional): WhatsApp Business Account ID
 * - Aliases accepted: whatsapp_phone_number_id, whatsapp_business_account_id, id_nr (legacy)
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

$logger = new WebhookLogger($pdo, 'get_whatsapp_credentilas');

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

    // Accept multiple parameter names
    $phone_number_id = $_GET['whatsapp_phone_nr_id']
        ?? $_POST['whatsapp_phone_nr_id']
        ?? ($jsonBody['whatsapp_phone_nr_id'] ?? null);
    $phone_number_id = $phone_number_id
        ?? ($_GET['whatsapp_phone_number_id'] ?? $_POST['whatsapp_phone_number_id'] ?? ($jsonBody['whatsapp_phone_number_id'] ?? null));

    $business_account_id = $_GET['whatsapp_business_acount_id']
        ?? $_POST['whatsapp_business_acount_id']
        ?? ($jsonBody['whatsapp_business_acount_id'] ?? null);
    // Accept correctly spelled alias and legacy id_nr
    $business_account_id = $business_account_id
        ?? ($_GET['whatsapp_business_account_id'] ?? $_POST['whatsapp_business_account_id'] ?? ($jsonBody['whatsapp_business_account_id'] ?? null))
        ?? ($_GET['id_nr'] ?? $_POST['id_nr'] ?? ($jsonBody['id_nr'] ?? null));

    // Validate at least one provided
    if (!$phone_number_id && !$business_account_id) {
        http_response_code(400);
        $resp = [
            'status' => 'error',
            'message' => 'Missing required parameter: provide whatsapp_phone_nr_id or whatsapp_business_acount_id',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo json_encode($resp);
        $logger->logError('Missing required params: whatsapp_phone_nr_id or whatsapp_business_acount_id', null, 400);
        exit();
    }

    // Build query conditionally (prefer phone_number_id when present)
    $sql = "
        SELECT wsm.*, wp.unic_id as workpoint_unic_id, wp.name_of_the_place, wp.address, 
               wp.lead_person_name, wp.lead_person_phone_nr, wp.workplace_phone_nr, wp.booking_phone_nr, wp.email as workplace_email,
               org.unic_id as organisation_unic_id, org.alias_name, org.oficial_company_name, org.email_address, org.www_address, org.country
        FROM workpoint_social_media wsm
        JOIN working_points wp ON wp.unic_id = wsm.workpoint_id
        JOIN organisations org ON org.unic_id = wp.organisation_id
        WHERE wsm.platform = 'whatsapp_business' AND %s
        LIMIT 1
    ";

    if (!empty($phone_number_id)) {
        $where = "wsm.whatsapp_phone_number_id = ?";
        $param = [$phone_number_id];
        $query_meta = ['whatsapp_phone_nr_id' => $phone_number_id];
    } else {
        $where = "wsm.whatsapp_business_account_id = ?";
        $param = [$business_account_id];
        $query_meta = ['whatsapp_business_acount_id' => $business_account_id];
    }

    $stmt = $pdo->prepare(sprintf($sql, $where));
    $stmt->execute($param);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(200);
        $resp = [
            'status' => 'unsuccesful',
            'message' => 'No WhatsApp credentials found for: ' . json_encode($query_meta),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo json_encode($resp);
        $logger->logError('WhatsApp credentials not found', null, 200, [
            'additional_data' => array_merge(['platform' => 'whatsapp_business'], $query_meta)
        ]);
        exit();
    }

    $response = [
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'query' => $query_meta,
        'credentials' => [
            'platform' => 'whatsapp_business',
            'whatsapp_phone_number' => $row['whatsapp_phone_number'],
            'whatsapp_phone_number_id' => $row['whatsapp_phone_number_id'],
            'whatsapp_business_account_id' => $row['whatsapp_business_account_id'],
            'whatsapp_access_token' => $row['whatsapp_access_token'],
            'whatsapp_webhook_verify_token' => WHATSAPP_WEBHOOK_VERIFY_TOKEN,
            'whatsapp_webhook_url' => WHATSAPP_WEBHOOK_URL,
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
        'additional_data' => array_merge([
            'platform' => 'whatsapp_business',
            'is_active' => (int)$row['is_active']
        ], $query_meta)
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
