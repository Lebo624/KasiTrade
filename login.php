<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$email    = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

$_SESSION['user'] = [
    'id'            => $user['id'],
    'email'         => $user['email'],
    'name'          => $user['name'],
    'surname'       => $user['surname'],
    'profile_image' => $user['profile_image'],
    'is_verified'   => $user['is_verified'],
    'role'          => $user['role'],
];

echo json_encode([
    'success' => true,
    'user'    => $_SESSION['user'],
]);
?>
