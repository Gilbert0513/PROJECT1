<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

// REGISTER
if ($action === 'register') {
    $full_name = trim($input['full_name'] ?? '');
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');
    $role = $input['role'] ?? 'customer';

    if (!$full_name || !$username || !$password) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit;
    }

    // check existing username
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param('s', $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists.']);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $full_name, $username, $hashed, $role);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account created successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database insert failed.']);
    }
    exit;
}

// LOGIN
if ($action === 'login') {
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (!$username || !$password) {
        echo json_encode(['success' => false, 'message' => 'Please enter username and password.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user'] = [
                'id' => $row['id'],
                'full_name' => $row['full_name'],
                'role' => $row['role'],
                'username' => $username
            ];
            echo json_encode([
                'success' => true,
                'role' => $row['role'],
                'full_name' => $row['full_name']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid password.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Username not found.']);
    }
    exit;
}

// LOGOUT
if ($action === 'logout') {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
