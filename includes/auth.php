<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . getLoginUrl());
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        $redirect = getDefaultRedirect();
        header('Location: ' . $redirect);
        exit;
    }
}

function getLoginUrl() {
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $depth = substr_count(str_replace(realpath(__DIR__ . '/..'), '', $script), DIRECTORY_SEPARATOR);
    $prefix = str_repeat('../', max(0, $depth - 1));
    return $prefix . 'index.php';
}

function getDefaultRedirect() {
    $role = $_SESSION['role'] ?? '';
    switch ($role) {
        case 'admin':   return '../admin/dashboard.php';
        case 'owner':   return '../owner/dashboard.php';
        case 'manager': return '../manager/dashboard.php';
        default:        return '../index.php';
    }
}

function getCurrentUser($conn) {
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = $conn->prepare("SELECT id, username, email, full_name, role, company_id, is_active FROM users WHERE id = ? AND is_active = 1");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

function login($conn, $username, $password) {
    $stmt = $conn->prepare("SELECT id, username, password, email, full_name, role, company_id, is_active FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !$user['is_active']) {
        return false;
    }

    if (!password_verify($password, $user['password'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['full_name']  = $user['full_name'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['company_id'] = $user['company_id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    return $user;
}

function logout() {
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
