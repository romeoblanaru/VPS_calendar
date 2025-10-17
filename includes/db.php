<?php
 $host = 'localhost';
 $db = 'nuuitasi_calendar4';
 $user = 'root';
 $pass = '';

$host = 'localhost';
$db = 'nuuitasi_calendar4';
$user = 'nuuitasi_calendar';
$pass = 'Romeo_calendar1202';

// WhatsApp Business Webhook Configuration Constants
define('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'Romy_1202');
define('WHATSAPP_WEBHOOK_URL', 'https://voice.rom2.co.uk/webhook/meta');

// Facebook Messenger Webhook Configuration Constants
define('FACEBOOK_WEBHOOK_VERIFY_TOKEN', 'Romy_1202');
define('FACEBOOK_WEBHOOK_URL', 'https://voice.rom2.co.uk/webhook/meta');

// External API base for refreshing credentials
define('CREDENTIALS_REFRESH_BASE_URL', 'https://voice.rom2.co.uk/api/refresh-credentials');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}