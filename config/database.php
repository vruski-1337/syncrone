<?php
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'pharma_care');
define('SITE_NAME', 'Pharma Care');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL', $_protocol . '://' . $_host . '/pharma-care');
unset($_protocol, $_host);

if (!class_exists('mysqli')) {
    die('MySQLi extension is not enabled. Install the php-mysql package and restart the PHP server.');
}

$conn = null;
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
} catch (mysqli_sql_exception $e) {
    $hint = ' Ensure MySQL is running and DB settings are correct.';
    if (stripos($e->getMessage(), 'No such file or directory') !== false) {
        $hint = ' Socket connection failed. Use DB_HOST=127.0.0.1 for TCP and verify MySQL is running.';
    }
    die('Database connection failed: ' . $e->getMessage() . $hint);
}

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
