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
    setFlash('danger', 'Invalid sale delete request.');
    header('Location: sales.php');
    exit;
}

$conn->begin_transaction();
try {
    $saleStmt = $conn->prepare('SELECT id FROM sales WHERE id = ? AND company_id = ? LIMIT 1');
    $saleStmt->bind_param('ii', $id, $cid);
    $saleStmt->execute();
    $sale = $saleStmt->get_result()->fetch_assoc();
    $saleStmt->close();

    if (!$sale) {
        throw new RuntimeException('Sale not found.');
    }

    $itemStmt = $conn->prepare('SELECT product_id, quantity FROM sale_items WHERE sale_id = ?');
    $itemStmt->bind_param('i', $id);
    $itemStmt->execute();
    $items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemStmt->close();

    foreach ($items as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $qty = (float)($item['quantity'] ?? 0);
        if ($productId > 0 && $qty > 0) {
            $stockStmt = $conn->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND company_id = ?');
            $stockStmt->bind_param('dii', $qty, $productId, $cid);
            $stockStmt->execute();
            $stockStmt->close();
        }
    }

    $delStmt = $conn->prepare('DELETE FROM sales WHERE id = ? AND company_id = ?');
    $delStmt->bind_param('ii', $id, $cid);
    $delStmt->execute();
    $affected = $delStmt->affected_rows;
    $delStmt->close();

    if ($affected <= 0) {
        throw new RuntimeException('Sale could not be deleted.');
    }

    $conn->commit();
    setFlash('success', 'Sale deleted and stock restored successfully.');
} catch (Throwable $e) {
    $conn->rollback();
    setFlash('danger', 'Delete failed: ' . $e->getMessage());
}

header('Location: sales.php');
exit;
