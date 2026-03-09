<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$id = (int)($_GET['id'] ?? 0);
$cid = (int)($_SESSION['company_id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid prescription request.';
    exit;
}

$stmt = $conn->prepare("\n    SELECT pr.file_name, pr.original_name\n    FROM patient_prescriptions pr\n    JOIN patients p ON p.id = pr.patient_id\n    WHERE pr.id = ? AND p.company_id = ?\n    LIMIT 1\n");
$stmt->bind_param('ii', $id, $cid);
$stmt->execute();
$rx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rx) {
    http_response_code(404);
    echo 'Prescription not found.';
    exit;
}

$fileName = (string)($rx['file_name'] ?? '');
$path = UPLOAD_PATH . 'prescriptions/' . $fileName;
if (!is_file($path)) {
    http_response_code(404);
    echo 'Prescription file missing.';
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path) ?: 'application/octet-stream';
$displayName = (string)($rx['original_name'] ?: $fileName);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . str_replace('"', '', basename($displayName)) . '"');
readfile($path);
exit;
