<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('admin');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('danger', 'Invalid company.'); header('Location: companies.php'); exit; }

$stmt = $conn->prepare("SELECT c.*, u.id AS owner_user_id FROM companies c LEFT JOIN users u ON u.id = c.owner_id WHERE c.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$company) { setFlash('danger', 'Company not found.'); header('Location: companies.php'); exit; }

// Delete company, owner, and logo
$conn->begin_transaction();
try {
    if ($company['logo']) deleteLogo($company['logo']);

    $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    if ($company['owner_user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $company['owner_user_id']);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    setFlash('success', 'Company deleted successfully.');
} catch (Exception $e) {
    $conn->rollback();
    setFlash('danger', 'Delete failed: ' . $e->getMessage());
}

header('Location: companies.php');
exit;
