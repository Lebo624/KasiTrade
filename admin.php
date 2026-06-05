<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

// GET stats
if ($method === 'GET') {
    $products = $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $users    = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $orders   = $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    echo json_encode(['products' => (int)$products, 'users' => (int)$users, 'orders' => (int)$orders]);
    exit;
}

// POST toggle featured
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $productId = $data['product_id'] ?? 0;
    $featured  = $data['is_featured'] ?? false;

    $stmt = $pdo->prepare('UPDATE products SET is_featured = ? WHERE id = ?');
    $stmt->execute([$featured ? 1 : 0, $productId]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>