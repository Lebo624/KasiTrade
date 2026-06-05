<?php
require_once __DIR__ . '/../includes/auth.php';
session_destroy();
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>