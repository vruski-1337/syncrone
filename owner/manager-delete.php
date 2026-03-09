<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('owner');

$id  = (int)($_GET['id'] ?? 0);
$cid = (int)$_SESSION['company_id'];
if (!$id) { setFlash('danger', 'Invalid manager.'); header('Location: managers.php'); exit; }

$stmt = $conn->prepare("DELETE FROM users WHERE id=? AND company_id=? AND role='manager'");
$stmt->bind_param('ii', $id, $cid);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    setFlash('success', 'Manager deleted.');
} else {
    setFlash('danger', 'Manager not found or cannot be deleted.');
}
$stmt->close();
header('Location: managers.php');
exit;
