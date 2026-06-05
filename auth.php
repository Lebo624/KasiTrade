<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['user']['role'] === 'admin';
}

function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Login required']);
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access only']);
        exit;
    }
}
?>