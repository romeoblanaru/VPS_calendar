<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

function safe($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : '';
}
$fields = [
    'alias_name','oficial_company_name','contact_name','position','email_address','www_address',
    'company_head_office_address','company_phone_nr','country','owner_name','owner_phone_nr','user','pasword'
];
$data = [];
foreach ($fields as $f) $data[$f] = safe($f);

if (empty($data['alias_name']) || empty($data['oficial_company_name'])) {
    echo json_encode(['success'=>false, 'error'=>'Alias and Official Name are required.']); exit;
}
try {
    $sql = "INSERT INTO organisations (" . implode(",", $fields) . ") VALUES (" . implode(",", array_fill(0,count($fields),"?" )) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($data));
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>