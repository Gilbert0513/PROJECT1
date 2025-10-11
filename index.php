<?php
session_start();
require_once 'db.php';

// Redirect logged-in users
if (!empty($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else if ($_SESSION['user']['role'] === 'cashier') {
        header('Location: cashier_dashboard.php');
    } else {
        header('Location: user_home.php');
    }
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Foodhouse | Smart Ordering System</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .platform-features {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin: 2rem 0;
    }
    .feature-card {
      background: white;
      padding: 1.5rem;
      border-radius: 10px;
      text-align: center;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .app-download {
      text-align: center;
      margin: 2rem 0;
      padding: 2rem;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 15px;
      color: white;
    }
    .download-buttons {
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-top: 1rem;
    }
    .platform-intro {
      text-align: center;
      padding: 2rem 1rem;
    }
    .platform-intro h2 {
      color: #333;
      margin-bottom: 1rem;
    }
    .role-select {
      display: flex;
      gap: 15px;
      margin: 1rem 0;
      justify-content: center;
    }
    .role-select label {
      display: flex;
      align-items: center;
      gap: 5px;
      cursor: pointer;
    }
    .switch-login {
      text-align: center;
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 1px solid #eee;
    }
    @media (max-width: 768px) {
      .download-buttons {
        flex-direction: column;
        align-items: center;
      }
      .role-select {
        flex-direction: column;
        gap: 10px;
      }
    }
  </style>
</head>
<body class="auth-body">

  <!-- Navigation -->
  <nav class="auth-nav">
    <h1>🍖 Foodhouse Smart Ordering System</h1>
  </nav>

 
  <!-- Authentication Container -->
  <div class="auth-container">
    
    <!-- Login Card -->
    <div class="auth-card">
      <h2>Welcome Back!</h2>
      <p class="subtitle">Login to access multi-platform ordering</p>
      <form id="loginForm">
        <input id="username" placeholder="Username" required>
        <input id="password" type="password" placeholder="Password" required>
        <button type="submit">Login to System</button>
      </form>
      
      <div class="switch-login">
        <p>Are you a cashier? <a href="cashier_login.php">Login as Cashier</a></p>
        <p>Need an account? <a href="#" onclick="showRegister()">Register here</a></p>
      </div>
    </div>

    <!-- Register Card -->
    <div class="auth-card" id="registerCard" style="display: none;">
      <h2>Create Account</h2>
      <p class="subtitle">Join our smart ordering platform</p>
      <form id="registerForm">
        <input id="reg_fullname" placeholder="Full name" required>
        <input id="reg_username" placeholder="Username" required>
        <input id="reg_password" type="password" placeholder="Password" required>

        <div class="role-select">
          <label><input type="radio" name="role" value="customer" checked> 👤 Customer</label>
          <label><input type="radio" name="role" value="cashier"> 💰 Cashier</label>
          <label><input type="radio" name="role" value="admin"> 👑 Admin</label>
        </div>

        <button type="submit">Register Account</button>
      </form>
      
      <div class="switch-login">
        <p>Already have an account? <a href="#" onclick="showLogin()">Login here</a></p>
      </div>
    </div>
  </div>

 

  <footer>
    <p>© <?=date('Y')?> Foodhouse Smart Ordering & Inventory Management System</p>
    <p style="font-size: 0.9rem; color: #666;">Web & Mobile Platform</p>
  </footer>
  
  <script>
    function showRegister() {
      document.querySelector('.auth-card').style.display = 'none';
      document.getElementById('registerCard').style.display = 'block';
    }
    
    function showLogin() {
      document.getElementById('registerCard').style.display = 'none';
      document.querySelector('.auth-card').style.display = 'block';
    }
  </script>
  <script src="js/main.js"></script>
</body>
</html>