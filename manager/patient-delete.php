<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$id  = (int)($_GET['id'] ?? 0);
$cid = (int)($_SESSION['company_id'] ?? 0);
if (!$id) { setFlash('danger', 'Invalid patient.'); header('Location: patients.php'); exit; }

$stmt = $conn->prepare('SELECT file_name FROM patient_prescriptions WHERE patient_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare('DELETE FROM patients WHERE id = ? AND company_id = ?');
$stmt->bind_param('ii', $id, $cid);
$stmt->execute();
$deleted = $stmt->affected_rows > 0;
$stmt->close();

if ($deleted) {
    foreach ($files as $file) {
        deletePrescriptionFile($file['file_name'] ?? '');
    }
    setFlash('success', 'Patient deleted successfully.');
} else {
    setFlash('danger', 'Patient not found.');
}

header('Location: patients.php');
exit;
