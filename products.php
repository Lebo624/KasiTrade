<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

// ---------- GET products (list or single) ----------
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        // Single product
        $stmt = $pdo->prepare('SELECT p.*, u.name AS seller_name FROM products p JOIN users u ON p.seller_id = u.id WHERE p.id = ?');
        $stmt->execute([$_GET['id']]);
        $product = $stmt->fetch();
        if (!$product) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            exit;
        }
        echo json_encode($product);
        exit;
    }

    // List with filters
    $search   = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    $minPrice = $_GET['minPrice'] ?? 0;
    $maxPrice = $_GET['maxPrice'] ?? 999999;

    $sql = 'SELECT p.*, u.name AS seller_name FROM products p JOIN users u ON p.seller_id = u.id WHERE 1=1';
    $params = [];
    if ($search) {
        $sql .= ' AND (p.name LIKE ? OR u.name LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($category) {
        $sql .= ' AND p.category = ?';
        $params[] = $category;
    }
    $sql .= ' AND p.price BETWEEN ? AND ?';
    $params[] = $minPrice;
    $params[] = $maxPrice;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // If logged in, attach favourite flag
    if (isLoggedIn()) {
        $userId = getCurrentUser()['id'];
        $favStmt = $pdo->prepare('SELECT product_id FROM favorites WHERE user_id = ?');
        $favStmt->execute([$userId]);
        $favs = $favStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($products as &$p) {
            $p['is_favorite'] = in_array($p['id'], $favs);
        }
    }

    echo json_encode($products);
    exit;
}

// ---------- POST add product ----------
if ($method === 'POST') {
    requireLogin();
    $data = json_decode(file_get_contents('php://input'), true);
    $name     = $data['name'] ?? '';
    $desc     = $data['desc'] ?? '';
    $price    = $data['price'] ?? 0;
    $category = $data['category'] ?? '';
    $image    = $data['image'] ?? '';

    $stmt = $pdo->prepare('INSERT INTO products (seller_id, name, description, price, category, image_path) VALUES (?,?,?,?,?,?)');
    $stmt->execute([getCurrentUser()['id'], $name, $desc, $price, $category, $image]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

// ---------- PUT update product ----------
if ($method === 'PUT') {
    requireLogin();
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;

    $stmt = $pdo->prepare('SELECT seller_id FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product || ($product['seller_id'] != getCurrentUser()['id'] && !isAdmin())) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE products SET name=?, description=?, price=?, category=?, image_path=? WHERE id=?');
    $stmt->execute([$data['name'], $data['desc'], $data['price'], $data['category'], $data['image'] ?? '', $id]);
    echo json_encode(['success' => true]);
    exit;
}

// ---------- DELETE product ----------
if ($method === 'DELETE') {
    requireLogin();
    parse_str(file_get_contents("php://input"), $delData); // for DELETE with body
    $id = $delData['id'] ?? ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT seller_id FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product || ($product['seller_id'] != getCurrentUser()['id'] && !isAdmin())) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>