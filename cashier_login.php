<?php
session_start();
require_once 'db.php';

// Redirect if already logged in as cashier
if (!empty($_SESSION['cashier_user']) && $_SESSION['cashier_user']['role'] === 'cashier') {
    header('Location: cashier_dashboard.php');
    exit;
}

// Handle login form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Check if user exists and is a cashier
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND role = 'cashier'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password (assuming passwords are hashed)
        if (password_verify($password, $user['password'])) {
            // Login successful
            $_SESSION['cashier_user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ];
            
            header('Location: cashier_dashboard.php');
            exit;
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Cashier not found!";
    }
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $reg_code = $_POST['reg_code'];
    
    // Check registration code (you can set this to any value you want)
    $valid_reg_code = "CASHIER123"; // Change this to your preferred code
    
    if ($reg_code !== $valid_reg_code) {
        $error = "Invalid registration code!";
    } else {
        // Check if username already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "Username already exists!";
        } else {
            // Create new cashier account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'cashier')");
            $insert_stmt->bind_param("sss", $username, $hashed_password, $fullname);
            
            if ($insert_stmt->execute()) {
                $success = "Cashier account created successfully! You can now login.";
                // Switch back to login form
                echo "<script>setTimeout(() => showCashierLogin(), 1000);</script>";
            } else {
                $error = "Registration failed!";
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Foodhouse | Cashier Login</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }
    
    .auth-body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    
    .auth-nav {
      background: rgba(255,255,255,0.1);
      padding: 1rem 2rem;
      backdrop-filter: blur(10px);
    }
    
    .auth-nav h1 {
      color: white;
      font-size: 1.5rem;
    }
    
    .auth-container {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 2rem;
    }
    
    .auth-card {
      background: white;
      padding: 2rem;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      width: 100%;
      max-width: 400px;
    }
    
    .auth-card h2 {
      color: #333;
      margin-bottom: 0.5rem;
      text-align: center;
    }
    
    .subtitle {
      color: #666;
      text-align: center;
      margin-bottom: 1.5rem;
    }
    
    .cashier-features {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
      gap: 15px;
      margin: 1.5rem 0;
    }
    
    .feature-card {
      background: #f8f9fa;
      padding: 1rem;
      border-radius: 8px;
      text-align: center;
      border: 1px solid #e9ecef;
    }
    
    .switch-login {
      text-align: center;
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 1px solid #eee;
    }
    
    input {
      width: 100%;
      padding: 0.75rem;
      margin-bottom: 1rem;
      border: 2px solid #ddd;
      border-radius: 5px;
      font-size: 1rem;
    }
    
    input:focus {
      outline: none;
      border-color: #667eea;
    }
    
    button {
      width: 100%;
      padding: 0.75rem;
      background: #667eea;
      color: white;
      border: none;
      border-radius: 5px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
    }
    
    button:hover {
      background: #5a6fd8;
    }
    
    .alert {
      padding: 0.75rem;
      border-radius: 5px;
      margin-bottom: 1rem;
      text-align: center;
    }
    
    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    a {
      color: #667eea;
      text-decoration: none;
    }
    
    a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body class="auth-body">
  <nav class="auth-nav">
    <h1>💰 Foodhouse Cashier System</h1>
  </nav>

  <div class="auth-container">
    <!-- Cashier Login -->
    <div class="auth-card" id="loginCard">
      <h2>Cashier Login</h2>
      <p class="subtitle">Access cashier dashboard</p>
      
      <?php if (!empty($error) && empty($_POST['register'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      
      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      
 
      
      <form method="POST" action="">
        <input type="text" name="username" placeholder="Cashier Username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login as Cashier</button>
      </form>
      
      <div class="switch-login">
        <p>Need a cashier account? <a href="#" onclick="showCashierRegister()">Register here</a></p>
        <p>Not a cashier? <a href="index.php">Go to Main Login</a></p>
      </div>
    </div>

    <!-- Cashier Register -->
    <div class="auth-card" id="registerCard" style="display: none;">
      <h2>Cashier Registration</h2>
      <p class="subtitle">Create new cashier account</p>
      
      <?php if (!empty($error) && isset($_POST['register'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      
      <form method="POST" action="">
        <input type="text" name="fullname" placeholder="Full name" required value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>">
        <input type="text" name="username" placeholder="Username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        <input type="password" name="password" placeholder="Password" required>
        <input type="text" name="reg_code" placeholder="Registration Code" required value="<?= htmlspecialchars($_POST['reg_code'] ?? '') ?>">
        
        <button type="submit" name="register">Register Cashier Account</button>
      </form>
      
      <div class="switch-login">
        <p>Already have an account? <a href="#" onclick="showCashierLogin()">Login here</a></p>
      </div>
    </div>
  </div>

  <script>
    function showCashierRegister() {
      document.getElementById('loginCard').style.display = 'none';
      document.getElementById('registerCard').style.display = 'block';
    }
    
    function showCashierLogin() {
      document.getElementById('registerCard').style.display = 'none';
      document.getElementById('loginCard').style.display = 'block';
    }
  </script>
</body>
</html>