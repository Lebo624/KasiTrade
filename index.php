<?php
session_start();

// ========== DATABASE CONFIGURATION  ==========
$db_host = 'sql207.infinityfree.com';          
$db_name = 'if0_41899490_kt_db';
$db_user = 'if0_41899490';
$db_pass = 'qPCmEclPwK0X';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// ---------- HELPER FUNCTIONS ----------
function isLoggedIn() { return isset($_SESSION['user']); }
function isAdmin() { return isLoggedIn() && $_SESSION['user']['role'] === 'admin'; }
function getCurrentUser() { return $_SESSION['user'] ?? null; }
function requireLogin() {
    if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Login required']); exit; }
}
function requireAdmin() {
    if (!isAdmin()) { http_response_code(403); echo json_encode(['error' => 'Admin access only']); exit; }
}

// ---------- API ROUTER ----------
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    // ---------- AUTH ----------
    if ($action === 'register') {
        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? ''); $email = trim($data['email'] ?? ''); $password = $data['password'] ?? '';
        if (!$name || !$email || !$password) { http_response_code(400); echo json_encode(['error' => 'All fields required']); exit; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(['error' => 'Invalid email']); exit; }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?'); $stmt->execute([$email]);
        if ($stmt->fetch()) { http_response_code(409); echo json_encode(['error' => 'Email already registered']); exit; }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, $hash]);
        echo json_encode(['success' => true, 'message' => 'Registration successful']);
        exit;
    }

    if ($action === 'login') {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = trim($data['email'] ?? ''); $password = $data['password'] ?? '';
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?'); $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) { http_response_code(401); echo json_encode(['error' => 'Invalid credentials']); exit; }
        $_SESSION['user'] = ['id' => $user['id'], 'email' => $user['email'], 'name' => $user['name'], 'role' => $user['role']];
        echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
        exit;
    }

    if ($action === 'logout') { session_destroy(); echo json_encode(['success' => true]); exit; }

    if ($action === 'editProfile') {
        requireLogin();
        $data = json_decode(file_get_contents('php://input'), true);
        $currentPassword = $data['currentPassword'] ?? ''; $newName = trim($data['name'] ?? ''); $newPassword = $data['newPassword'] ?? '';
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?'); $stmt->execute([getCurrentUser()['id']]);
        $user = $stmt->fetch();
        if (!password_verify($currentPassword, $user['password_hash'])) { http_response_code(403); echo json_encode(['error' => 'Current password incorrect']); exit; }
        $update = ['name = ?']; $params = [$newName];
        if ($newPassword) { $update[] = 'password_hash = ?'; $params[] = password_hash($newPassword, PASSWORD_DEFAULT); }
        $params[] = getCurrentUser()['id'];
        $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $update) . ' WHERE id = ?'); $stmt->execute($params);
        $_SESSION['user']['name'] = $newName;
        echo json_encode(['success' => true]);
        exit;
    }

    // ---------- PRODUCTS ----------
    if ($action === 'getProducts') {
        $search = $_GET['search'] ?? ''; $category = $_GET['category'] ?? ''; $minPrice = $_GET['minPrice'] ?? 0; $maxPrice = $_GET['maxPrice'] ?? 999999;
        $sql = 'SELECT p.*, u.name AS seller_name FROM products p JOIN users u ON p.seller_id = u.id WHERE 1=1';
        $params = [];
        if ($search) { $sql .= ' AND (p.name LIKE ? OR u.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
        if ($category) { $sql .= ' AND p.category = ?'; $params[] = $category; }
        $sql .= ' AND p.price BETWEEN ? AND ?'; $params[] = $minPrice; $params[] = $maxPrice;
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        $products = $stmt->fetchAll();
        if (isLoggedIn()) {
            $userId = getCurrentUser()['id'];
            $favStmt = $pdo->prepare('SELECT product_id FROM favorites WHERE user_id = ?'); $favStmt->execute([$userId]);
            $favs = $favStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($products as &$p) { $p['is_favorite'] = in_array($p['id'], $favs); }
        }
        echo json_encode($products);
        exit;
    }

    if ($action === 'getProduct') {
        $id = $_GET['id'] ?? 0;
        $stmt = $pdo->prepare('SELECT p.*, u.name AS seller_name FROM products p JOIN users u ON p.seller_id = u.id WHERE p.id = ?');
        $stmt->execute([$id]); $product = $stmt->fetch();
        if ($product) echo json_encode($product); else { http_response_code(404); echo json_encode(['error' => 'Not found']); }
        exit;
    }

    if ($action === 'addProduct') {
        requireLogin();
        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['name'] ?? ''; $desc = $data['desc'] ?? ''; $price = $data['price'] ?? 0; $category = $data['category'] ?? ''; $image = $data['image'] ?? '';
        $stmt = $pdo->prepare('INSERT INTO products (seller_id, name, description, price, category, image_path) VALUES (?,?,?,?,?,?)');
        $stmt->execute([getCurrentUser()['id'], $name, $desc, $price, $category, $image]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($action === 'updateProduct') {
        requireLogin();
        $data = json_decode(file_get_contents('php://input'), true); $id = $data['id'] ?? 0;
        $stmt = $pdo->prepare('SELECT seller_id FROM products WHERE id = ?'); $stmt->execute([$id]); $product = $stmt->fetch();
        if (!$product || ($product['seller_id'] != getCurrentUser()['id'] && !isAdmin())) { http_response_code(403); echo json_encode(['error' => 'Permission denied']); exit; }
        $stmt = $pdo->prepare('UPDATE products SET name=?, description=?, price=?, category=?, image_path=? WHERE id=?');
        $stmt->execute([$data['name'], $data['desc'], $data['price'], $data['category'], $data['image'] ?? '', $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'deleteProduct') {
        requireLogin();
        $data = json_decode(file_get_contents('php://input'), true); $id = $data['id'] ?? 0;
        $stmt = $pdo->prepare('SELECT seller_id FROM products WHERE id = ?'); $stmt->execute([$id]); $product = $stmt->fetch();
        if (!$product || ($product['seller_id'] != getCurrentUser()['id'] && !isAdmin())) { http_response_code(403); echo json_encode(['error' => 'Permission denied']); exit; }
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?'); $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ---------- ORDERS ----------
    if ($action === 'placeOrder') {
        requireLogin();
        $data = json_decode(file_get_contents('php://input'), true); $productId = $data['product_id'] ?? 0;
        $stmt = $pdo->prepare('SELECT id, seller_id FROM products WHERE id = ?'); $stmt->execute([$productId]); $product = $stmt->fetch();
        if (!$product) { http_response_code(404); echo json_encode(['error' => 'Product not found']); exit; }
        if ($product['seller_id'] == getCurrentUser()['id']) { http_response_code(400); echo json_encode(['error' => 'Cannot buy your own product']); exit; }
        $stmt = $pdo->prepare('INSERT INTO orders (buyer_id, product_id, seller_id) VALUES (?,?,?)');
        $stmt->execute([getCurrentUser()['id'], $productId, $product['seller_id']]);
        echo json_encode(['success' => true, 'order_id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($action === 'getOrders') {
        requireLogin(); $type = $_GET['type'] ?? 'buyer';
        if ($type === 'buyer') {
            $stmt = $pdo->prepare('SELECT o.*, p.name AS product_name, p.price AS product_price, u.name AS seller_name FROM orders o JOIN products p ON o.product_id = p.id JOIN users u ON o.seller_id = u.id WHERE o.buyer_id = ? ORDER BY o.created_at DESC');
            $stmt->execute([getCurrentUser()['id']]);
        } else {
            $stmt = $pdo->prepare('SELECT o.*, p.name AS product_name, p.price AS product_price, u.email AS buyer_email FROM orders o JOIN products p ON o.product_id = p.id JOIN users u ON o.buyer_id = u.id WHERE o.seller_id = ? ORDER BY o.created_at DESC');
            $stmt->execute([getCurrentUser()['id']]);
        }
        echo json_encode($stmt->fetchAll());
        exit;
    }

    // ---------- BUYER MARK ORDER AS PAID ----------
    if ($action === 'buyerMarkPaid') {
        requireLogin();
        $data = json_decode(file_get_contents('php://input'), true);
        $orderId = $data['order_id'] ?? 0;

        $stmt = $pdo->prepare('SELECT id, buyer_id, status FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order || $order['buyer_id'] != getCurrentUser()['id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            exit;
        }
        if ($order['status'] !== 'Accepted') {
            http_response_code(400);
            echo json_encode(['error' => 'Order must be Accepted before marking as Paid']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute(['Paid', $orderId]);

        // Notify the seller
        $stmt = $pdo->prepare('SELECT seller_id, product_id FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $orderInfo = $stmt->fetch();
        $stmt = $pdo->prepare('SELECT name FROM products WHERE id = ?');
        $stmt->execute([$orderInfo['product_id']]);
        $productName = $stmt->fetchColumn();

        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
        $stmt->execute([$orderInfo['seller_id'], "The buyer has confirmed payment for \"$productName\". You can now complete the order."]);

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'updateOrderStatus') {
        requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true); $orderId = $data['order_id'] ?? 0; $newStatus = $data['status'] ?? '';
        $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?'); $stmt->execute([$newStatus, $orderId]);
        $stmt = $pdo->prepare('SELECT buyer_id, product_id FROM orders WHERE id = ?'); $stmt->execute([$orderId]); $order = $stmt->fetch();
        $stmt = $pdo->prepare('SELECT name FROM products WHERE id = ?'); $stmt->execute([$order['product_id']]); $productName = $stmt->fetchColumn();
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
        $stmt->execute([$order['buyer_id'], "Your order for \"$productName\" has been $newStatus."]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ---------- FAVORITES ----------
    if ($action === 'toggleFavorite') {
        requireLogin();
        $data = json_decode(file_get_contents('php://input'), true); $productId = $data['product_id'] ?? 0; $userId = getCurrentUser()['id'];
        $stmt = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND product_id = ?'); $stmt->execute([$userId, $productId]);
        $exists = $stmt->fetch();
        if ($exists) { $stmt = $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND product_id = ?'); $stmt->execute([$userId, $productId]); echo json_encode(['added' => false]); }
        else { $stmt = $pdo->prepare('INSERT INTO favorites (user_id, product_id) VALUES (?, ?)'); $stmt->execute([$userId, $productId]); echo json_encode(['added' => true]); }
        exit;
    }

    if ($action === 'getFavorites') {
        requireLogin();
        $stmt = $pdo->prepare('SELECT p.*, u.name AS seller_name FROM favorites f JOIN products p ON f.product_id = p.id JOIN users u ON p.seller_id = u.id WHERE f.user_id = ?');
        $stmt->execute([getCurrentUser()['id']]); echo json_encode($stmt->fetchAll());
        exit;
    }

    // ---------- NOTIFICATIONS ----------
    if ($action === 'getNotifications') {
        requireLogin();
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC'); $stmt->execute([getCurrentUser()['id']]);
        $notifs = $stmt->fetchAll();
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0'); $stmt->execute([getCurrentUser()['id']]);
        echo json_encode($notifs);
        exit;
    }

    if ($action === 'unreadNotifications') {
        requireLogin();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'); $stmt->execute([getCurrentUser()['id']]);
        echo json_encode(['count' => (int)$stmt->fetchColumn()]);
        exit;
    }

    // ---------- ADMIN ----------
    if ($action === 'adminStats') {
        requireAdmin();
        $products = $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
        $users = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $orders = $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        echo json_encode(['products' => (int)$products, 'users' => (int)$users, 'orders' => (int)$orders]);
        exit;
    }

    if ($action === 'adminToggleFeatured') {
        requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true); $productId = $data['product_id'] ?? 0; $featured = $data['is_featured'] ?? false;
        $stmt = $pdo->prepare('UPDATE products SET is_featured = ? WHERE id = ?'); $stmt->execute([$featured ? 1 : 0, $productId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'adminGetUsers') {
        requireAdmin();
        $stmt = $pdo->query('SELECT id, email, name, role, created_at FROM users ORDER BY created_at DESC');
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'adminGetAllOrders') {
        requireAdmin();
        $stmt = $pdo->query('
            SELECT o.*, p.name AS product_name, 
                   buyer.email AS buyer_email, 
                   seller.email AS seller_email
            FROM orders o
            JOIN products p ON o.product_id = p.id
            JOIN users buyer ON o.buyer_id = buyer.id
            JOIN users seller ON o.seller_id = seller.id
            ORDER BY o.created_at DESC
        ');
        echo json_encode($stmt->fetchAll());
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
  <title>KasiTrade – Buy & Sell in the Township</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    :root { --bg: #fdf8f0; --surface: #ffffff; --card: #ffffff; --border: #e2d9c8; --text: #2e241b; --text-secondary: #6b5e4f; --accent: #2a9d8f; --accent-hover: #1f7a6f; --accent-light: rgba(42,157,143,0.12); --accent-gold: #f7b267; --shadow: 0 4px 12px rgba(0,0,0,0.08); --radius: 18px; --transition: 0.25s ease; --header-bg: #111111; --header-text: #ffffff; --footer-bg: #111111; --footer-text: #b0b0b0; }
    body { font-family:'Segoe UI',system-ui,-apple-system,sans-serif; background:linear-gradient(135deg,#fdf8f0 0%,#fef4e4 100%); color:var(--text); line-height:1.6; min-height:100vh; display:flex; flex-direction:column; }
    h1,h2,h3 { font-weight:700; color:#2e241b; }
    a { text-decoration:none; color:var(--accent); font-weight:500; }
    a:hover { color:var(--accent-hover); }
    .container { width:100%; max-width:1200px; margin:0 auto; padding:0 1.2rem; }
    .main-header { background:var(--header-bg); border-bottom:1px solid #333; position:sticky; top:0; z-index:100; }
    .header-inner { display:flex; align-items:center; justify-content:space-between; height:70px; }
    .logo { font-size:2rem; font-weight:800; color:var(--accent); }
    .logo span { color:#fff; }
    .nav-links { display:flex; gap:2rem; list-style:none; align-items:center; }
    .nav-links a { color:#ccc; font-weight:600; padding:0.5rem 0; border-bottom:3px solid transparent; transition:all var(--transition); font-size:0.95rem; cursor:pointer; position:relative; }
    .nav-links a:hover,.nav-links a.active { color:#fff; border-bottom-color:var(--accent); }
    .badge-pill { position:absolute; top:-8px; right:-16px; background:var(--accent); color:#fff; border-radius:12px; padding:0.1rem 0.5rem; font-size:0.7rem; font-weight:700; min-width:18px; text-align:center; }
    .badge.paid { background-color: #f7b267; color: #000; }
    .menu-toggle { display:none; background:none; border:none; color:#fff; font-size:2rem; cursor:pointer; }
    .main-footer { background:var(--footer-bg); border-top:1px solid #333; margin-top:auto; padding:2rem 0; text-align:center; color:var(--footer-text); font-size:0.9rem; }
    .card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:1.5rem; box-shadow:var(--shadow); transition:transform var(--transition),box-shadow var(--transition); }
    .card:hover { transform:translateY(-5px); box-shadow:0 12px 24px rgba(0,0,0,0.1); }
    .btn { display:inline-block; background:var(--accent); color:#fff; font-weight:700; padding:0.7rem 1.8rem; border-radius:12px; border:none; cursor:pointer; transition:all var(--transition); text-align:center; box-shadow:0 4px 8px rgba(42,157,143,0.3); }
    .btn:hover { background:var(--accent-hover); transform:translateY(-2px); }
    .btn-outline { background:transparent; border:2px solid var(--accent); color:var(--accent); box-shadow:none; }
    .btn-outline:hover { background:var(--accent-light); }
    .btn-sm { padding:0.4rem 1.2rem; font-size:0.85rem; }
    .btn-danger { background:#e76f51; }
    .btn-danger:hover { background:#d25a3a; }
    .form-group { margin-bottom:1.2rem; }
    .form-group label { display:block; margin-bottom:0.3rem; color:var(--text-secondary); font-weight:600; font-size:0.9rem; }
    .form-control { width:100%; padding:0.8rem; background:#fff; border:2px solid var(--border); border-radius:12px; color:var(--text); font-size:0.95rem; }
    .form-control:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 4px var(--accent-light); }
    .search-bar { display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:2rem; }
    .search-bar input,.search-bar select { flex:1 1 200px; padding:0.8rem 1rem; background:#fff; border:2px solid var(--border); border-radius:12px; }
    .product-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:1.8rem; margin:2rem 0; }
    .product-card { overflow:hidden; position:relative; background:#fff; }
    .product-card img { width:100%; height:200px; object-fit:cover; border-radius:12px; margin-bottom:0.8rem; background:#e9f7f5; }
    .product-card .price { font-size:1.6rem; font-weight:800; color:var(--accent); margin:0.3rem 0; }
    .product-card .seller { font-size:0.85rem; color:var(--text-secondary); }
    .category-tag { display:inline-block; background:var(--accent-light); color:var(--accent); font-size:0.75rem; padding:0.2rem 0.9rem; border-radius:20px; margin-bottom:0.5rem; font-weight:600; }
    .featured-badge { background:rgba(42,157,143,0.15); color:var(--accent); margin-left:0.3rem; }
    .fav-icon { position:absolute; top:12px; right:12px; background:rgba(255,255,255,0.8); border-radius:50%; width:36px; height:36px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; cursor:pointer; transition:background var(--transition); box-shadow:0 2px 6px rgba(0,0,0,0.08); }
    .fav-icon:hover { background:var(--accent-light); }
    .hero { background:linear-gradient(135deg,rgba(42,157,143,0.12) 0%,rgba(42,157,143,0.2) 100%); padding:3rem 2rem; border-radius:24px; margin:2rem 0; text-align:center; }
    .hero h1 { font-size:2.8rem; margin-bottom:1rem; }
    .hero p { font-size:1.2rem; color:var(--text-secondary); max-width:600px; margin:0 auto 2rem; }
    .page { display:none; padding:2rem 0; animation:fade 0.3s ease; }
    .page.active { display:block; }
    @keyframes fade { from{opacity:0;transform:translateY(6px);} to{opacity:1;transform:translateY(0);} }
    .admin-layout { display:flex; gap:2rem; flex-wrap:wrap; }
    .admin-sidebar { flex:1 1 200px; background:var(--surface); border-radius:var(--radius); padding:1.5rem; box-shadow:var(--shadow); }
    .admin-sidebar a { display:block; padding:0.6rem 1rem; color:var(--text-secondary); font-weight:600; margin-bottom:0.5rem; border-radius:10px; }
    .admin-sidebar a:hover,.admin-sidebar a.active { background:var(--accent-light); color:var(--accent); }
    .admin-content { flex:3 1 500px; }
    .stat-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1rem; margin-bottom:2rem; }
    .stat-card { background:linear-gradient(135deg,#fff 0%,#e9f7f5 100%); padding:1.8rem; border-radius:var(--radius); text-align:center; box-shadow:var(--shadow); }
    .stat-card .number { font-size:2.2rem; font-weight:800; color:var(--accent); }
    .list-item { display:flex; justify-content:space-between; align-items:center; padding:0.8rem 0; border-bottom:1px solid var(--border); }
    .actions a { margin-left:0.5rem; font-size:0.85rem; }
    .badge { display:inline-block; padding:0.2rem 0.8rem; border-radius:12px; font-size:0.75rem; font-weight:700; background:#f0ebe4; color:#6b5e4f; }
    .badge.accepted { background:#e9f7f5; color:var(--accent); }
    .badge.completed { background:#e6f7e6; color:#2a9d2a; }
    .badge.cancelled { background:#fde8e8; color:#e76f51; }
    .file-upload-wrapper { position:relative; display:inline-block; width:100%; }
    .file-upload-wrapper input[type="file"] { position:absolute; left:0; top:0; opacity:0; width:100%; height:100%; cursor:pointer; }
    .file-upload-trigger { display:flex; align-items:center; justify-content:center; gap:0.5rem; padding:0.8rem; background:#e9f7f5; border:2px dashed var(--accent); border-radius:12px; color:var(--text-secondary); cursor:pointer; font-weight:600; }
    .file-upload-trigger:hover { border-color:var(--accent-hover); }
    .image-preview { max-width:160px; max-height:100px; margin-top:0.5rem; border-radius:10px; display:none; }
    .notif-item { padding:1rem; border-bottom:1px solid var(--border); background:#fff; }
    .notif-item.unread { background:var(--accent-light); }
    @media(max-width:768px) { .nav-links { position:fixed; top:70px; left:0; width:100%; background:#111; flex-direction:column; gap:0; padding:1rem 0; transform:translateY(-150%); transition:transform 0.3s ease; box-shadow:0 4px 20px rgba(0,0,0,0.3); } .nav-links.show { transform:translateY(0); } .nav-links a { display:block; padding:0.8rem 2rem; border-left:4px solid transparent; color:#ccc; } .nav-links a:hover,.nav-links a.active { border-left-color:var(--accent); background:rgba(255,255,255,0.05); color:#fff; } .menu-toggle { display:block; } .admin-layout { flex-direction:column; } }
  </style>
</head>
<body>
  <header class="main-header">
    <div class="container header-inner">
      <a href="#" class="logo" onclick="showPage('home')">Kasi<span>Trade</span></a>
      <button class="menu-toggle" id="menuToggle" aria-label="Menu">☰</button>
      <nav>
        <ul class="nav-links" id="navLinks">
          <li><a onclick="showPage('home')" class="active">Home</a></li>
          <li><a onclick="showPage('products')">Products</a></li>
          <li><a onclick="showPage('about')">About</a></li>
          <li><a onclick="showPage('contact')">Contact</a></li>
          <li id="authLinks"></li>
        </ul>
      </nav>
    </div>
  </header>

  <main class="container">
    <!-- HOME PAGE -->
    <section id="home" class="page active">
      <div class="hero">
        <h1>Trade with your Kasi</h1>
        <p>Buy and sell directly within your community. Simple, fast, and built for side‑hustlers.</p>
        <a href="#" class="btn" onclick="showPage('products')">Browse Products</a>
        <a href="#" class="btn btn-outline" style="margin-left:0.5rem;" onclick="showPage('sell')">Start Selling</a>
      </div>
      <h2 style="margin-bottom: 1rem;">Featured Products</h2>
      <div class="product-grid" id="featuredGrid"></div>
      <h2 style="margin: 2rem 0 1rem;">Trending</h2>
      <div class="product-grid" id="homeProductGrid"></div>
      <div style="text-align: center; margin: 2rem 0;">
        <a href="#" class="btn btn-outline" onclick="showPage('products')">View All Listings</a>
      </div>
    </section>

    <!-- PRODUCTS PAGE -->
    <section id="products" class="page">
      <h2>All Products</h2>
      <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Search products...">
        <select id="categoryFilter">
          <option value="">All Categories</option>
          <option value="Fashion">Fashion</option>
          <option value="Electronics">Electronics</option>
          <option value="Furniture">Furniture</option>
          <option value="Food">Food</option>
        </select>
        <input type="number" id="priceMin" placeholder="Min R" style="flex:0.5;">
        <input type="number" id="priceMax" placeholder="Max R" style="flex:0.5;">
      </div>
      <div class="product-grid" id="allProductsGrid"></div>
    </section>

    <!-- PRODUCT DETAILS PAGE -->
    <section id="productDetails" class="page">
      <div id="productDetailContent" class="card" style="max-width:600px;"></div>
    </section>

    <!-- SELL PAGE -->
    <section id="sell" class="page">
      <h2>Sell a Product</h2>
      <div class="card" style="max-width:500px;">
        <form id="sellForm">
          <div class="form-group"><label>Product Name</label><input type="text" class="form-control" id="sellName" required></div>
          <div class="form-group"><label>Description</label><textarea class="form-control" id="sellDesc" rows="3" required></textarea></div>
          <div class="form-group"><label>Price (R)</label><input type="number" class="form-control" id="sellPrice" min="1" required></div>
          <div class="form-group"><label>Category</label><select class="form-control" id="sellCategory" required>
            <option value="">Select category</option>
            <option value="Fashion">Fashion</option>
            <option value="Electronics">Electronics</option>
            <option value="Furniture">Furniture</option>
            <option value="Food">Food</option>
          </select></div>
          <div class="form-group">
            <label>Product Image</label>
            <div class="file-upload-wrapper">
              <div class="file-upload-trigger" id="sellUploadTrigger">Choose image or paste URL below</div>
              <input type="file" id="sellFileInput" accept="image/*">
            </div>
            <input type="text" class="form-control" id="sellImageUrl" placeholder="Or paste image URL" style="margin-top:0.5rem;">
            <img id="sellImagePreview" class="image-preview" alt="Preview">
          </div>
          <button type="submit" class="btn">List Product</button>
        </form>
      </div>
    </section>

    <!-- EDIT PRODUCT PAGE -->
    <section id="editProduct" class="page">
      <h2>Edit Product</h2>
      <div class="card" style="max-width:500px;">
        <form id="editForm">
          <input type="hidden" id="editId">
          <div class="form-group"><label>Product Name</label><input type="text" class="form-control" id="editName" required></div>
          <div class="form-group"><label>Description</label><textarea class="form-control" id="editDesc" rows="3" required></textarea></div>
          <div class="form-group"><label>Price (R)</label><input type="number" class="form-control" id="editPrice" min="1" required></div>
          <div class="form-group"><label>Category</label><select class="form-control" id="editCategory" required>
            <option value="Fashion">Fashion</option><option value="Electronics">Electronics</option>
            <option value="Furniture">Furniture</option><option value="Food">Food</option>
          </select></div>
          <div class="form-group">
            <label>Product Image</label>
            <div class="file-upload-wrapper">
              <div class="file-upload-trigger" id="editUploadTrigger">Choose new image</div>
              <input type="file" id="editFileInput" accept="image/*">
            </div>
            <input type="text" class="form-control" id="editImageUrl" placeholder="Image URL" style="margin-top:0.5rem;">
            <img id="editImagePreview" class="image-preview" alt="Preview">
          </div>
          <button type="submit" class="btn">Update Product</button>
          <button type="button" class="btn btn-outline" onclick="showPage('myListings')">Cancel</button>
        </form>
      </div>
    </section>

    <!-- MY LISTINGS PAGE -->
    <section id="myListings" class="page">
      <h2>My Listings</h2>
      <div class="card" style="margin-bottom:1rem;"><a href="#" class="btn btn-sm" onclick="showPage('sell')">+ Add New Listing</a></div>
      <div id="myListingsContainer"></div>
    </section>

    <!-- SALES PAGE -->
    <section id="mySales" class="page">
      <h2>My Sales (Requests for my products)</h2>
      <div id="mySalesContainer"></div>
    </section>

    <!-- FAVORITES PAGE -->
    <section id="favorites" class="page">
      <h2>My Favorites</h2>
      <div class="product-grid" id="favoritesGrid"></div>
    </section>

    <!-- NOTIFICATIONS PAGE -->
    <section id="notifications" class="page">
      <h2>Notifications</h2>
      <div id="notificationsContainer"></div>
    </section>

    <!-- ABOUT PAGE -->
    <section id="about" class="page">
      <h2>About KasiTrade</h2>
      <div class="card" style="margin-top:1.5rem;">
        <p>KasiTrade is a community‑driven marketplace built for South African townships. We connect buyers and sellers directly, making it easy for anyone to start a side hustle or find great deals locally.</p>
        <p style="margin-top:1rem;">Our mission is to empower informal traders and individuals with a secure, simple platform that works on any device, even with limited data.</p>
      </div>
    </section>

    <!-- CONTACT PAGE -->
    <section id="contact" class="page">
      <h2>Contact Us</h2>
      <div class="card" style="max-width:500px; margin-top:1.5rem;">
        <p>Email: support@kasitrade.co.za</p>
        <p>WhatsApp: +27 67 064 3457</p>
        <p>Based in Midrand, Johannesburg</p>
      </div>
    </section>

    <!-- LOGIN PAGE -->
    <section id="login" class="page">
      <h2>Login</h2>
      <div class="card" style="max-width:400px; margin-top:1.5rem;">
        <form id="loginForm">
          <div class="form-group"><label>Email</label><input type="email" class="form-control" id="loginEmail" required></div>
          <div class="form-group"><label>Password</label><input type="password" class="form-control" id="loginPassword" required></div>
          <button type="submit" class="btn">Sign In</button>
        </form>
        <p style="margin-top:1rem; font-size:0.9rem;">Don't have an account? <a href="#" onclick="showPage('register')">Register here</a></p>
      </div>
    </section>

    <!-- REGISTER PAGE -->
    <section id="register" class="page">
      <h2>Register</h2>
      <div class="card" style="max-width:400px; margin-top:1.5rem;">
        <form id="registerForm">
          <div class="form-group"><label>Full Name</label><input type="text" class="form-control" id="regName" required></div>
          <div class="form-group"><label>Email</label><input type="email" class="form-control" id="regEmail" required></div>
          <div class="form-group"><label>Password</label><input type="password" class="form-control" id="regPassword" required minlength="4"></div>
          <button type="submit" class="btn">Create Account</button>
        </form>
        <p style="margin-top:1rem; font-size:0.9rem;">Already have an account? <a href="#" onclick="showPage('login')">Login</a></p>
      </div>
    </section>

    <!-- PROFILE PAGE -->
    <section id="profile" class="page">
      <h2>My Profile</h2>
      <div class="card" style="max-width:500px;">
        <p><strong>Name:</strong> <span id="profileName"></span></p>
        <p><strong>Email:</strong> <span id="profileEmail"></span></p>
        <p><strong>Role:</strong> <span id="profileRole"></span></p>
        <button class="btn btn-outline" onclick="showPage('editProfile')">Edit Profile</button>
      </div>
    </section>

    <!-- EDIT PROFILE PAGE -->
    <section id="editProfile" class="page">
      <h2>Edit Profile</h2>
      <div class="card" style="max-width:400px;">
        <form id="editProfileForm">
          <div class="form-group"><label>Name</label><input type="text" class="form-control" id="editProfileName" required></div>
          <div class="form-group"><label>New Password (leave blank to keep)</label><input type="password" class="form-control" id="editProfileNewPassword"></div>
          <div class="form-group"><label>Current Password (required to save changes)</label><input type="password" class="form-control" id="editProfileCurrentPassword" required></div>
          <button type="submit" class="btn">Save Changes</button>
          <button type="button" class="btn btn-outline" onclick="showPage('profile')">Cancel</button>
        </form>
      </div>
    </section>

    <!-- MY ORDERS PAGE -->
    <section id="orders" class="page">
      <h2>My Requests / Orders</h2>
      <div id="ordersList"></div>
    </section>

    <!-- ADMIN PAGE -->
    <section id="admin" class="page">
      <h2>Admin Dashboard</h2>
      <div class="admin-layout">
        <div class="admin-sidebar">
          <a href="#" class="active" onclick="showAdminTab('stats')">Overview</a>
          <a href="#" onclick="showAdminTab('productsList')">All Products</a>
          <a href="#" onclick="showAdminTab('usersList')">Users</a>
          <a href="#" onclick="showAdminTab('ordersManagement')">Orders</a>
          <a href="#" onclick="showAdminTab('featuredManagement')">Featured</a>
        </div>
        <div class="admin-content" id="adminContent"></div>
      </div>
    </section>
  </main>

  <footer class="main-footer">
    <div class="container">
      <p>&copy; 2026 KasiTrade. Built for the Kasi, by the Kasi.</p>
    </div>
  </footer>

  <script>
    const API_BASE = window.location.pathname + '?action=';
    let currentUser = <?php echo json_encode(getCurrentUser()); ?>;

    async function api(action, data = {}, method = 'POST') {
        let url = API_BASE + action;
        const options = { method, headers: {} };
        if (method === 'GET') {
            const params = new URLSearchParams(data).toString();
            if (params) url += '&' + params;
        } else {
            options.headers['Content-Type'] = 'application/json';
            if (data && Object.keys(data).length) options.body = JSON.stringify(data);
        }
        const res = await fetch(url, options);
        const json = await res.json();
        if (!res.ok) throw new Error(json.error || 'Request failed');
        return json;
    }

    function updateAuthUI() {
        const authLinks = document.getElementById('authLinks');
        if (currentUser) {
            fetchUnreadCount();
            authLinks.innerHTML = `
                <a onclick="showPage('sell')">Sell</a>
                <a onclick="showPage('myListings')">My Listings</a>
                <a onclick="showPage('mySales')">Sales</a>
                <a onclick="showPage('orders')">Orders</a>
                <a onclick="showPage('favorites')">Favorites</a>
                <a onclick="showPage('notifications')" style="position:relative;" id="notifLink">
                    Notifications <span class="badge-pill" id="notifCount" style="display:none;"></span>
                </a>
                <a onclick="showPage('profile')">${currentUser.name}</a>
                ${currentUser.role === 'admin' ? '<a onclick="showPage(\'admin\')" style="color:#2a9d8f;">Admin</a>' : ''}
                <a onclick="handleLogout()" style="color:#e76f51;">Logout</a>
            `;
        } else {
            authLinks.innerHTML = `
                <a onclick="showPage('login')">Login</a>
                <a onclick="showPage('register')">Register</a>
            `;
        }
    }

    async function fetchUnreadCount() {
        try {
            const data = await api('unreadNotifications', {}, 'GET');
            const badge = document.getElementById('notifCount');
            if (badge) {
                if (data.count > 0) { badge.textContent = data.count; badge.style.display = 'inline'; }
                else { badge.style.display = 'none'; }
            }
        } catch (e) {}
    }

    async function handleLogout() { await api('logout'); currentUser = null; updateAuthUI(); showPage('home'); }

    const pages = document.querySelectorAll('.page');
    function showPage(pageId, param = null) {
        pages.forEach(p => p.classList.remove('active'));
        document.getElementById(pageId)?.classList.add('active');

        if (pageId === 'productDetails') renderProductDetails(param);
        if (pageId === 'home') { renderFeatured(); renderHomeProducts(); }
        if (pageId === 'products') renderAllProducts();
        if (pageId === 'myListings') renderMyListings();
        if (pageId === 'mySales') renderMySales();
        if (pageId === 'favorites') renderFavorites();
        if (pageId === 'notifications') renderNotifications();
        if (pageId === 'editProduct') prefillEditForm(param);
        if (pageId === 'orders') renderMyOrders();
        if (pageId === 'profile') renderProfile();
        if (pageId === 'editProfile') fillEditProfile();
        if (pageId === 'admin') renderAdminStats();
        if (pageId === 'sell') clearSellForm();

        document.querySelectorAll('.nav-links a.active').forEach(l => l.classList.remove('active'));
        document.querySelectorAll('.nav-links a').forEach(link => {
            if (link.getAttribute('onclick')?.includes(`'${pageId}'`)) link.classList.add('active');
        });
        document.getElementById('navLinks').classList.remove('show');
        window.scrollTo(0, 0);
    }

    function createProductCard(product) {
        const isFav = currentUser ? product.is_favorite : false;
        const favHTML = currentUser ? `<span class="fav-icon" onclick="event.stopPropagation(); handleToggleFavorite(${product.id})">${isFav ? '❤️' : '🤍'}</span>` : '';
        const featuredTag = product.is_featured ? '<span class="category-tag featured-badge">Featured</span>' : '';
        return `
            <div class="card product-card" onclick="showPage('productDetails', ${product.id})">
                ${favHTML}
                <img src="${product.image_path || 'https://placehold.co/400x250/2a9d8f/ffffff?text=No+Image'}" alt="${product.name}">
                <span class="category-tag">${product.category}</span>${featuredTag}
                <h3>${product.name}</h3>
                <p class="price">R${parseFloat(product.price).toFixed(2)}</p>
                <p class="seller">By: ${product.seller_name}</p>
            </div>`;
    }

    async function fetchProducts(filters = {}) { return await api('getProducts', filters, 'GET'); }

    async function renderFeatured() {
        const products = await fetchProducts();
        const featured = products.filter(p => p.is_featured).slice(0, 4);
        document.getElementById('featuredGrid').innerHTML = featured.length ? featured.map(createProductCard).join('') : '<p>No featured products yet.</p>';
    }

    async function renderHomeProducts() {
        const products = await fetchProducts();
        const nonFeatured = products.filter(p => !p.is_featured).slice(0, 4);
        document.getElementById('homeProductGrid').innerHTML = nonFeatured.map(createProductCard).join('');
    }

    async function renderAllProducts(filtered = null) {
        const filters = filtered || {};
        const products = await fetchProducts(filters);
        document.getElementById('allProductsGrid').innerHTML = products.length ? products.map(createProductCard).join('') : '<p>No products found.</p>';
    }

    async function renderProductDetails(id) {
        const product = await api('getProduct', { id }, 'GET');
        const content = document.getElementById('productDetailContent');
        if (!product || product.error) { content.innerHTML = '<p>Product not found.</p>'; return; }
        const canEdit = currentUser && (currentUser.id == product.seller_id || currentUser.role === 'admin');
        const isFav = currentUser ? product.is_favorite : false;
        content.innerHTML = `
            <img src="${product.image_path}" style="width:100%; height:250px; object-fit:cover; border-radius:12px; margin-bottom:1rem;">
            <span class="category-tag">${product.category}</span>${product.is_featured ? '<span class="category-tag featured-badge">Featured</span>' : ''}
            <h2>${product.name}</h2>
            <p style="font-size:1.8rem; color:var(--accent);">R${parseFloat(product.price).toFixed(2)}</p>
            <p><strong>Seller:</strong> ${product.seller_name}</p>
            <p style="margin:1rem 0;">${product.description}</p>
            <button class="btn" onclick="handleRequestProduct(${product.id})">Request to Buy</button>
            <button class="btn btn-outline" style="margin-left:0.5rem;" onclick="handleToggleFavorite(${product.id})">${isFav ? '❤️ Remove from Favorites' : '🤍 Add to Favorites'}</button>
            <button class="btn btn-outline" style="margin-left:0.5rem;" onclick="showPage('products')">← Back</button>
            ${canEdit ? `<button class="btn btn-outline" style="margin-left:0.5rem;" onclick="showPage('editProduct', ${product.id})">Edit</button>
            <button class="btn btn-danger" style="margin-left:0.5rem;" onclick="handleDeleteProduct(${product.id})">Delete</button>` : ''}
        `;
    }

    async function handleToggleFavorite(productId) {
        if (!currentUser) { alert('Please login to add favorites.'); showPage('login'); return; }
        await api('toggleFavorite', { product_id: productId }, 'POST');
        const currentPage = document.querySelector('.page.active').id;
        if (currentPage === 'productDetails') renderProductDetails(productId);
        else if (currentPage === 'home') { renderFeatured(); renderHomeProducts(); }
        else if (currentPage === 'products') filterProducts();
        else if (currentPage === 'favorites') renderFavorites();
    }

    async function renderFavorites() {
        if (!currentUser) { document.getElementById('favoritesGrid').innerHTML = '<p>Please login.</p>'; return; }
        const favorites = await api('getFavorites', {}, 'GET');
        document.getElementById('favoritesGrid').innerHTML = favorites.length ? favorites.map(createProductCard).join('') : '<p>No favorites yet.</p>';
    }

    async function renderMyListings() {
        if (!currentUser) { document.getElementById('myListingsContainer').innerHTML = '<p>Please login.</p>'; return; }
        const all = await fetchProducts();
        const myProducts = all.filter(p => p.seller_id == currentUser.id);
        document.getElementById('myListingsContainer').innerHTML = myProducts.length
            ? myProducts.map(p => `<div class="card list-item" style="margin-bottom:0.5rem;"><div><strong>${p.name}</strong> – R${p.price} <span class="category-tag">${p.category}</span></div><div class="actions"><a href="#" class="btn btn-sm" onclick="event.stopPropagation(); showPage('editProduct', ${p.id})">Edit</a><a href="#" class="btn btn-sm btn-danger" onclick="event.stopPropagation(); handleDeleteProduct(${p.id})">Delete</a></div></div>`).join('')
            : '<p>No listings yet.</p>';
    }

    async function prefillEditForm(id) {
        const product = await api('getProduct', { id }, 'GET');
        if (!product || product.error) { alert('Not found.'); showPage('myListings'); return; }
        document.getElementById('editId').value = product.id;
        document.getElementById('editName').value = product.name;
        document.getElementById('editDesc').value = product.description;
        document.getElementById('editPrice').value = product.price;
        document.getElementById('editCategory').value = product.category;
        document.getElementById('editImageUrl').value = product.image_path || '';
        const preview = document.getElementById('editImagePreview');
        if (product.image_path) { preview.src = product.image_path; preview.style.display = 'block'; } else { preview.style.display = 'none'; }
    }

    async function handleDeleteProduct(id) { if (!confirm('Delete this listing?')) return; await api('deleteProduct', { id }, 'POST'); alert('Deleted!'); showPage('myListings'); }

    document.getElementById('sellForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        if (!currentUser) { alert('Please login to sell.'); showPage('login'); return; }
        const data = {
            name: document.getElementById('sellName').value,
            desc: document.getElementById('sellDesc').value,
            price: parseFloat(document.getElementById('sellPrice').value),
            category: document.getElementById('sellCategory').value,
            image: document.getElementById('sellImageUrl').value || 'https://placehold.co/400x250/2a9d8f/ffffff?text=' + encodeURIComponent(document.getElementById('sellName').value)
        };
        await api('addProduct', data, 'POST');
        alert('Product listed!');
        showPage('myListings');
    });

    document.getElementById('editForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const id = parseInt(document.getElementById('editId').value);
        const data = {
            id,
            name: document.getElementById('editName').value,
            desc: document.getElementById('editDesc').value,
            price: parseFloat(document.getElementById('editPrice').value),
            category: document.getElementById('editCategory').value,
            image: document.getElementById('editImageUrl').value || 'https://placehold.co/400x250/2a9d8f/ffffff?text=' + encodeURIComponent(document.getElementById('editName').value)
        };
        await api('updateProduct', data, 'POST');
        alert('Product updated!');
        showPage('myListings');
    });

    async function handleRequestProduct(productId) {
        if (!currentUser) { alert('Please login to request.'); showPage('login'); return; }
        await api('placeOrder', { product_id: productId }, 'POST');
        alert('Request sent!');
        showPage('orders');
    }

    async function renderMyOrders() {
        if (!currentUser) { document.getElementById('ordersList').innerHTML = '<p>Please login.</p>'; return; }
        const orders = await api('getOrders', { type: 'buyer' }, 'GET');
        document.getElementById('ordersList').innerHTML = orders.length
            ? orders.map(o => `
                <div class="card" style="margin-bottom:0.5rem;">
                    <strong>${o.product_name}</strong> – R${o.product_price}<br>
                    <small>${o.created_at} | <span class="badge ${o.status.toLowerCase()}">${o.status}</span></small>
                    ${o.status === 'Accepted' ? `
                        <div style="margin-top:0.5rem;">
                            <button class="btn btn-sm" style="background:#f7b267; color:#000;" onclick="confirmPayment(${o.id})">Confirm Payment</button>
                        </div>
                    ` : ''}
                </div>
            `).join('')
            : '<p>No requests yet.</p>';
    }

    async function confirmPayment(orderId) {
        if (!confirm('Confirm that you have paid for this item? This will mark the order as Paid.')) return;
        try {
            await api('buyerMarkPaid', { order_id: orderId }, 'POST');
            alert('Payment confirmed! The seller has been notified.');
            renderMyOrders();
        } catch (err) {
            alert(err.message);
        }
    }

    async function renderMySales() {
        if (!currentUser) { document.getElementById('mySalesContainer').innerHTML = '<p>Please login.</p>'; return; }
        const orders = await api('getOrders', { type: 'seller' }, 'GET');
        document.getElementById('mySalesContainer').innerHTML = orders.length
            ? orders.map(o => `<div class="card" style="margin-bottom:0.5rem;"><strong>${o.product_name}</strong> – R${o.product_price} (Buyer: ${o.buyer_email})<br><small>${o.created_at} | <span class="badge ${o.status.toLowerCase()}">${o.status}</span></small></div>`).join('')
            : '<p>No sales yet.</p>';
    }

    async function renderNotifications() {
        if (!currentUser) { document.getElementById('notificationsContainer').innerHTML = '<p>Please login.</p>'; return; }
        const notifs = await api('getNotifications', {}, 'GET');
        document.getElementById('notificationsContainer').innerHTML = notifs.length
            ? notifs.map(n => `<div class="notif-item ${n.is_read ? '' : 'unread'}"><p>${n.message}</p><small>${n.created_at}</small></div>`).join('')
            : '<p>No notifications.</p>';
        fetchUnreadCount();
    }

    async function showAdminTab(tab) {
        const content = document.getElementById('adminContent');
        if (tab === 'stats') {
            const stats = await api('adminStats', {}, 'GET');
            content.innerHTML = `<div class="stat-cards"><div class="stat-card"><div class="number">${stats.products}</div>Products</div><div class="stat-card"><div class="number">${stats.users}</div>Users</div><div class="stat-card"><div class="number">${stats.orders}</div>Orders</div></div>`;
        } else if (tab === 'productsList') {
            const products = await fetchProducts();
            content.innerHTML = '<h3>All Products</h3><div class="product-grid">' + products.map(p => `<div class="card"><strong>${p.name}</strong><br>R${p.price} - ${p.seller_name}</div>`).join('') + '</div>';
        } else if (tab === 'usersList') {
            const users = await api('adminGetUsers', {}, 'GET');
            content.innerHTML = '<h3>All Users</h3>' + users.map(u => `
                <div class="card" style="margin-bottom:0.5rem;">
                    <strong>${u.name}</strong> (${u.email})<br>
                    <small>Role: ${u.role} | Joined: ${u.created_at}</small>
                </div>
            `).join('');
        } else if (tab === 'ordersManagement') {
            const orders = await api('adminGetAllOrders', {}, 'GET');
            content.innerHTML = '<h3>All Orders</h3>' + (orders.length ? orders.map(o => `
                <div class="card" style="margin-bottom:0.5rem;">
                    <strong>${o.product_name}</strong> – R${o.product_price}<br>
                    Buyer: ${o.buyer_email} | Seller: ${o.seller_email}<br>
                    <small>${o.created_at} | <span class="badge ${o.status.toLowerCase()}">${o.status}</span></small>
                    <div style="margin-top:0.5rem;">
                        <button class="btn btn-sm" onclick="updateOrderStatus(${o.id}, 'Accepted')">Accept</button>
                        <button class="btn btn-sm" style="background:#4caf50; color:#fff;" onclick="updateOrderStatus(${o.id}, 'Completed')">Complete</button>
                        <button class="btn btn-sm btn-danger" onclick="updateOrderStatus(${o.id}, 'Cancelled')">Cancel</button>
                    </div>
                </div>
            `).join('') : '<p>No orders yet.</p>');
        } else if (tab === 'featuredManagement') {
            const products = await fetchProducts();
            content.innerHTML = '<h3>Manage Featured Products</h3><p>Select products to feature on the homepage.</p>' + products.map(p => `
                <div class="card list-item" style="margin-bottom:0.3rem;">
                    <span>${p.name} (R${p.price})</span>
                    <label><input type="checkbox" ${p.is_featured ? 'checked' : ''} onchange="toggleFeatured(${p.id}, this.checked)"> Featured</label>
                </div>`).join('');
        }
    }

    window.toggleFeatured = async function(id, isFeatured) { await api('adminToggleFeatured', { product_id: id, is_featured: isFeatured }, 'POST'); showAdminTab('featuredManagement'); };
    window.updateOrderStatus = async function(orderId, newStatus) {
        try {
            await api('updateOrderStatus', { order_id: orderId, status: newStatus }, 'POST');
            alert(`Order #${orderId} marked as ${newStatus}.`);
            showAdminTab('ordersManagement');
        } catch (err) {
            alert(err.message);
        }
    };
    async function renderAdminStats() { showAdminTab('stats'); }

    async function renderProfile() {
        if (!currentUser) { showPage('login'); return; }
        document.getElementById('profileName').textContent = currentUser.name;
        document.getElementById('profileEmail').textContent = currentUser.email;
        document.getElementById('profileRole').textContent = currentUser.role || 'user';
    }

    function fillEditProfile() {
        if (!currentUser) { showPage('login'); return; }
        document.getElementById('editProfileName').value = currentUser.name;
        document.getElementById('editProfileNewPassword').value = '';
        document.getElementById('editProfileCurrentPassword').value = '';
    }

    document.getElementById('editProfileForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const data = {
            currentPassword: document.getElementById('editProfileCurrentPassword').value,
            name: document.getElementById('editProfileName').value,
            newPassword: document.getElementById('editProfileNewPassword').value
        };
        try { await api('editProfile', data, 'POST'); currentUser.name = data.name; updateAuthUI(); alert('Profile updated successfully.'); showPage('profile'); }
        catch (err) { alert(err.message); }
    });

    document.getElementById('registerForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const data = { name: document.getElementById('regName').value, email: document.getElementById('regEmail').value, password: document.getElementById('regPassword').value };
        try { await api('register', data, 'POST'); alert('Registered! Please login.'); showPage('login'); }
        catch (err) { alert(err.message); }
    });

    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const data = { email: document.getElementById('loginEmail').value, password: document.getElementById('loginPassword').value };
        try { const res = await api('login', data, 'POST'); currentUser = res.user; updateAuthUI(); alert(`Welcome, ${currentUser.name}!`); showPage('home'); }
        catch (err) { alert(err.message); }
    });

    document.getElementById('searchInput').addEventListener('input', filterProducts);
    document.getElementById('categoryFilter').addEventListener('change', filterProducts);
    document.getElementById('priceMin').addEventListener('input', filterProducts);
    document.getElementById('priceMax').addEventListener('input', filterProducts);

    async function filterProducts() {
        const filters = {
            search: document.getElementById('searchInput').value,
            category: document.getElementById('categoryFilter').value,
            minPrice: document.getElementById('priceMin').value || 0,
            maxPrice: document.getElementById('priceMax').value || 999999
        };
        renderAllProducts(filters);
    }

    function setupImageUpload(fileInputId, triggerId, previewId, urlInputId) {
        const fileInput = document.getElementById(fileInputId);
        const trigger = document.getElementById(triggerId);
        const preview = document.getElementById(previewId);
        const urlInput = document.getElementById(urlInputId);
        trigger.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) { alert('Image too large (max 2MB).'); fileInput.value = ''; return; }
                const reader = new FileReader();
                reader.onload = (e) => { urlInput.value = e.target.result; preview.src = e.target.result; preview.style.display = 'block'; };
                reader.readAsDataURL(file);
            }
        });
        urlInput.addEventListener('input', () => {
            const val = urlInput.value.trim();
            if (val) { preview.src = val; preview.style.display = 'block'; } else { preview.style.display = 'none'; }
        });
    }
    setupImageUpload('sellFileInput', 'sellUploadTrigger', 'sellImagePreview', 'sellImageUrl');
    setupImageUpload('editFileInput', 'editUploadTrigger', 'editImagePreview', 'editImageUrl');

    function clearSellForm() { document.getElementById('sellForm').reset(); document.getElementById('sellImagePreview').style.display = 'none'; }

    document.getElementById('menuToggle').addEventListener('click', () => { document.getElementById('navLinks').classList.toggle('show'); });
    document.addEventListener('click', (e) => { if (!e.target.closest('.main-header')) document.getElementById('navLinks').classList.remove('show'); });

    async function init() { updateAuthUI(); await renderFeatured(); await renderHomeProducts(); }
    init();
  </script>
</body>
</html>
