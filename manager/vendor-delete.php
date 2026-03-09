<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$id = (int)($_GET['id'] ?? 0);
$cid = (int)($_SESSION['company_id'] ?? 0);
$token = (string)($_GET['token'] ?? '');

if (!$id || !verifyCsrfToken($token)) {
    setFlash('danger', 'Invalid vendor delete request.');
    header('Location: vendors.php');
    exit;
}

$stmt = $conn->prepare('DELETE FROM vendors WHERE id = ? AND company_id = ?');
$stmt->bind_param('ii', $id, $cid);
$stmt->execute();
$ok = $stmt->affected_rows > 0;
$stmt->close();

setFlash($ok ? 'success' : 'danger', $ok ? 'Vendor deleted.' : 'Vendor not found or in use.');
header('Location: vendors.php');
exit;
