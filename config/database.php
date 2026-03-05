<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pharma_care');
define('SITE_NAME', 'Pharma Care');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('SITE_URL', 'http://localhost/pharma-care');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
