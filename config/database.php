<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pharma_care');
define('SITE_NAME', 'Pharma Care');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL', $_protocol . '://' . $_host . '/pharma-care');
unset($_protocol, $_host);

if (!class_exists('mysqli')) {
    die('MySQLi extension is not enabled. Install the php-mysql package and restart the PHP server.');
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
