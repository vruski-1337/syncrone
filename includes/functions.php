<?php

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function formatCurrency($amount) {
    return '$' . number_format((float)$amount, 2);
}

function formatDate($date) {
    if (!$date) return 'N/A';
    return date('M d, Y', strtotime($date));
}

function formatDateTime($date) {
    if (!$date) return 'N/A';
    return date('M d, Y h:i A', strtotime($date));
}

function generateInvoiceNumber() {
    return 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

function getCompanyData($conn, $company_id) {
    $stmt = $conn->prepare("
        SELECT c.*, s.name AS subscription_name, s.price AS subscription_price,
               cs.start_date, cs.end_date,
               DATEDIFF(cs.end_date, CURDATE()) AS days_remaining
        FROM companies c
        LEFT JOIN company_subscriptions cs ON cs.company_id = c.id AND cs.is_active = 1
        LEFT JOIN subscriptions s ON s.id = cs.subscription_id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data;
}

function checkSubscription($conn, $company_id) {
    $stmt = $conn->prepare("
        SELECT cs.end_date, DATEDIFF(cs.end_date, CURDATE()) AS days_remaining
        FROM company_subscriptions cs
        WHERE cs.company_id = ? AND cs.is_active = 1
        ORDER BY cs.end_date DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sub = $result->fetch_assoc();
    $stmt->close();

    if (!$sub) {
        return ['status' => 'none', 'days_remaining' => 0];
    }
    $days = (int)$sub['days_remaining'];
    if ($days < 0) {
        return ['status' => 'expired', 'days_remaining' => $days];
    }
    if ($days <= 7) {
        return ['status' => 'expiring', 'days_remaining' => $days];
    }
    return ['status' => 'active', 'days_remaining' => $days];
}

function getFooterContent($conn) {
    $result = $conn->query("SELECT content FROM footer_settings WHERE id = 1 LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        return $row['content'];
    }
    return '&copy; ' . date('Y') . ' Pharma Care. All rights reserved.';
}

function getActiveAlerts($conn) {
    $result = $conn->query("SELECT * FROM alerts WHERE is_active = 1 ORDER BY created_at DESC");
    if ($result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

function uploadLogo($file) {
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed.'];
    }
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large. Max 2MB.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed.'];
    }

    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = UPLOAD_PATH . 'logos/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'error' => 'Could not save file.'];
    }

    return ['success' => true, 'filename' => $filename];
}

function deleteLogo($filename) {
    if ($filename && file_exists(UPLOAD_PATH . 'logos/' . $filename)) {
        unlink(UPLOAD_PATH . 'logos/' . $filename);
    }
}

function getLogoUrl($filename, $baseDepth = 1) {
    if (!$filename) return null;
    $prefix = str_repeat('../', $baseDepth);
    return $prefix . 'uploads/logos/' . $filename;
}

function renderFlash() {
    $flash = getFlash();
    if (!$flash) return '';
    $type = sanitize($flash['type']);
    $msg  = sanitize($flash['message']);
    return "<div class=\"alert alert-{$type} alert-dismissible fade show\" role=\"alert\">"
         . "<i class=\"fas fa-info-circle me-2\"></i>{$msg}"
         . "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>"
         . "</div>";
}

function renderSubscriptionWarning($subStatus) {
    if ($subStatus['status'] === 'expired') {
        return '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Your subscription has <strong>expired</strong>. Please renew to continue using the system.</div>';
    }
    if ($subStatus['status'] === 'expiring') {
        $days = $subStatus['days_remaining'];
        return "<div class=\"alert alert-warning\"><i class=\"fas fa-clock me-2\"></i>Your subscription expires in <strong>{$days} day(s)</strong>. Please renew soon.</div>";
    }
    if ($subStatus['status'] === 'none') {
        return '<div class="alert alert-warning"><i class="fas fa-exclamation-circle me-2"></i>No active subscription found. Please contact the administrator.</div>';
    }
    return '';
}
