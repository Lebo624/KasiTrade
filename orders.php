<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

// Place order (POST)
if ($method === 'POST') {
    requireLogin();
    $data = json_decode(file_get_contents('php://input'), true);
    $productId = $data['product_id'] ?? 0;

    $stmt = $pdo->prepare('SELECT id, seller_id, name FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    if ($product['seller_id'] == getCurrentUser()['id']) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot buy your own product']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO orders (buyer_id, product_id, seller_id, status) VALUES (?,?,?,?)');
    $stmt->execute([getCurrentUser()['id'], $productId, $product['seller_id'], 'Requested']);

    echo json_encode(['success' => true, 'order_id' => $pdo->lastInsertId()]);
    exit;
}

// Get buyer orders (GET ?type=buyer)
if ($method === 'GET' && ($_GET['type'] ?? '') === 'buyer') {
    requireLogin();
    $stmt = $pdo->prepare('SELECT o.*, p.name AS product_name, p.price AS product_price, p.image_path, u.name AS seller_name FROM orders o JOIN products p ON o.product_id = p.id JOIN users u ON o.seller_id = u.id WHERE o.buyer_id = ? ORDER BY o.created_at DESC');
    $stmt->execute([getCurrentUser()['id']]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// Get seller sales (GET ?type=seller)
if ($method === 'GET' && ($_GET['type'] ?? '') === 'seller') {
    requireLogin();
    $stmt = $pdo->prepare('SELECT o.*, p.name AS product_name, p.price AS product_price, u.email AS buyer_email FROM orders o JOIN products p ON o.product_id = p.id JOIN users u ON o.buyer_id = u.id WHERE o.seller_id = ? ORDER BY o.created_at DESC');
    $stmt->execute([getCurrentUser()['id']]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// Admin update status (PUT)
if ($method === 'PUT') {
    requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true);
    $orderId   = $data['order_id'] ?? 0;
    $newStatus = $data['status'] ?? '';

    $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->execute([$newStatus, $orderId]);

    // Notify buyer
    $stmt = $pdo->prepare('SELECT buyer_id, product_id FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    $stmt = $pdo->prepare('SELECT name FROM products WHERE id = ?');
    $stmt->execute([$order['product_id']]);
    $productName = $stmt->fetchColumn();

    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    $stmt->execute([$order['buyer_id'], "Your order for \"$productName\" has been $newStatus."]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>