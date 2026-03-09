<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$id  = (int)($_GET['id'] ?? 0);
$cid = (int)$_SESSION['company_id'];
if (!$id) { setFlash('danger', 'Invalid doctor.'); header('Location: doctors.php'); exit; }

$stmt = $conn->prepare('DELETE FROM doctors WHERE id = ? AND company_id = ?');
$stmt->bind_param('ii', $id, $cid);
$stmt->execute();
if ($stmt->affected_rows > 0) {
    setFlash('success', 'Doctor deleted.');
} else {
    setFlash('danger', 'Doctor not found.');
}
$stmt->close();

header('Location: doctors.php');
exit;
