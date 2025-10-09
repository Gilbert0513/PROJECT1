<?php
session_start();
require_once 'db.php';

// Redirect logged-in users
if (!empty($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header('Location: admin_dashboard.php');
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
  <title>Foodhouse | Login & Register</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-body">

  <!-- Navigation -->
  <nav class="auth-nav">
    <h1>🍖 Foodhouse Grillhouse</h1>
  </nav>

  <!-- Authentication Container -->
  <div class="auth-container">
    
    <!-- Login Card -->
    <div class="auth-card">
      <h2>Welcome Back!</h2>
      <p class="subtitle">Login to your account</p>
      <form id="loginForm">
        <input id="username" placeholder="Username" required>
        <input id="password" type="password" placeholder="Password" required>
        <button type="submit">Login</button>
      </form>
    </div>

    <!-- Register Card -->
<div class="auth-card">
  <h2>Create an Account</h2>
  <p class="subtitle">Select your role below</p>
  <form id="registerForm">
    <input id="reg_fullname" placeholder="Full name" required>
    <input id="reg_username" placeholder="Username" required>
    <input id="reg_password" type="password" placeholder="Password" required>

    <div class="role-select">
      <label><input type="radio" name="role" value="customer" checked> Customer</label>
      <label><input type="radio" name="role" value="admin"> Admin</label>
    </div>

    <button type="submit">Register</button>
  </form>
</div>


  </div>

  <footer>© <?=date('Y')?> Foodhouse Smart Ordering & Inventory</footer>
  <script src="js/main.js"></script>
</body>
</html>
