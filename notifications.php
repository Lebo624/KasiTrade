<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

// GET notifications (and mark as read)
if ($method === 'GET') {
    requireLogin();
    $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([getCurrentUser()['id']]);
    $notifs = $stmt->fetchAll();

    // Mark as read
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->execute([getCurrentUser()['id']]);

    echo json_encode($notifs);
    exit;
}

// GET unread count
if ($method === 'GET' && isset($_GET['unread'])) {
    requireLogin();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([getCurrentUser()['id']]);
    echo json_encode(['count' => (int)$stmt->fetchColumn()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>