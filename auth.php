<?php
require_once 'db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $username = trim($data['username']);
        $password = $data['password'];
        $full_name = $data['full_name'];
        $role = $data['role'] ?? 'customer';

        $pwHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username,password,full_name,role) VALUES (:u,:p,:f,:r)');
        $stmt->execute([':u'=>$username, ':p'=>$pwHash, ':f'=>$full_name, ':r'=>$role]);
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $username = $data['username'];
        $password = $data['password'];

        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u');
        $stmt->execute([':u'=>$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['success'=>false,'message'=>'Invalid credentials']);
            exit;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        echo json_encode(['success'=>true,'role'=>$user['role']]);
        exit;
    }

    if ($action === 'logout') {
        session_unset();
        session_destroy();
        echo json_encode(['success'=>true]);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
