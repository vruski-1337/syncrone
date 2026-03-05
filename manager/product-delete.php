<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('manager');

$id  = (int)($_GET['id'] ?? 0);
$cid = (int)$_SESSION['company_id'];
if (!$id) { setFlash('danger', 'Invalid product.'); header('Location: products.php'); exit; }

$stmt = $conn->prepare("DELETE FROM products WHERE id=? AND company_id=?");
$stmt->bind_param('ii', $id, $cid);
$stmt->execute();
if ($stmt->affected_rows > 0) setFlash('success', 'Product deleted.');
else setFlash('danger', 'Product not found.');
$stmt->close();
header('Location: products.php');
exit;
