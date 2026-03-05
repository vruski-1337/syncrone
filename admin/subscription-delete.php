<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('admin');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('danger', 'Invalid plan.'); header('Location: subscriptions.php'); exit; }

// Check if in use
$chk = $conn->prepare("SELECT COUNT(*) FROM company_subscriptions WHERE subscription_id = ? AND is_active = 1");
$chk->bind_param('i', $id);
$chk->execute();
if ($chk->get_result()->fetch_row()[0] > 0) {
    setFlash('danger', 'Cannot delete – plan is currently assigned to active companies.');
    header('Location: subscriptions.php');
    exit;
}
$chk->close();

$stmt = $conn->prepare("DELETE FROM subscriptions WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

setFlash('success', 'Subscription plan deleted.');
header('Location: subscriptions.php');
exit;
