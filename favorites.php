<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

// Toggle favourite (POST)
if ($method === 'POST') {
    requireLogin();
    $data = json_decode(file_get_contents('php://input'), true);
    $productId = $data['product_id'] ?? 0;
    $userId = getCurrentUser()['id'];

    $stmt = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$userId, $productId]);
    $exists = $stmt->fetch();

    if ($exists) {
        $stmt = $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$userId, $productId]);
        echo json_encode(['added' => false]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO favorites (user_id, product_id) VALUES (?, ?)');
        $stmt->execute([$userId, $productId]);
        echo json_encode(['added' => true]);
    }
    exit;
}

// Get user's favourites (GET)
if ($method === 'GET') {
    requireLogin();
    $stmt = $pdo->prepare('SELECT p.*, u.name AS seller_name FROM favorites f JOIN products p ON f.product_id = p.id JOIN users u ON p.seller_id = u.id WHERE f.user_id = ?');
    $stmt->execute([getCurrentUser()['id']]);
    echo json_encode($stmt->fetchAll());
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>