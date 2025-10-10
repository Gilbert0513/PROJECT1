<?php
session_start();
require_once 'db.php';

// Redirect if already logged in as cashier
if (!empty($_SESSION['user']) && $_SESSION['user']['role'] === 'cashier') {
    header('Location: cashier_dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Foodhouse | Cashier Login</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .cashier-features {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin: 1.5rem 0;
    }
    .feature-card {
      background: white;
      padding: 1rem;
      border-radius: 8px;
      text-align: center;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .switch-login {
      text-align: center;
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 1px solid #eee;
    }
  </style>
</head>
<body class="auth-body">
  <nav class="auth-nav">
    <h1>💰 Foodhouse Cashier System</h1>
  </nav>

  <div class="auth-container">
    <!-- Cashier Login -->
    <div class="auth-card">
      <h2>Cashier Login</h2>
      <p class="subtitle">Access cashier dashboard</p>
      
      <div class="cashier-features">
        <div class="feature-card">
          <div style="font-size: 2rem;">💵</div>
          <h4>Quick Orders</h4>
        </div>
        <div class="feature-card">
          <div style="font-size: 2rem;">📊</div>
          <h4>Live Reports</h4>
        </div>
        <div class="feature-card">
          <div style="font-size: 2rem;">🔄</div>
          <h4>Real-time Sync</h4>
        </div>
      </div>
      
      <form id="cashierLoginForm">
        <input id="cashier_username" placeholder="Cashier Username" required>
        <input id="cashier_password" type="password" placeholder="Password" required>
        <button type="submit">Login as Cashier</button>
      </form>
      
      <div class="switch-login">
        <p>Need a cashier account? <a href="#" onclick="showCashierRegister()">Register here</a></p>
        <p>Not a cashier? <a href="index.php">Go to Main Login</a></p>
      </div>
    </div>

    <!-- Cashier Register -->
    <div class="auth-card" id="cashierRegisterCard" style="display: none;">
      <h2>Cashier Registration</h2>
      <p class="subtitle">Create new cashier account</p>
      
      <form id="cashierRegisterForm">
        <input id="cashier_reg_fullname" placeholder="Full name" required>
        <input id="cashier_reg_username" placeholder="Username" required>
        <input id="cashier_reg_password" type="password" placeholder="Password" required>
        <input id="cashier_reg_code" placeholder="Registration Code" required>
        
        <button type="submit">Register Cashier Account</button>
      </form>
      
      <div class="switch-login">
        <p>Already have an account? <a href="#" onclick="showCashierLogin()">Login here</a></p>
      </div>
    </div>
  </div>

  <script>
    function showCashierRegister() {
      document.querySelector('.auth-card').style.display = 'none';
      document.getElementById('cashierRegisterCard').style.display = 'block';
    }
    
    function showCashierLogin() {
      document.getElementById('cashierRegisterCard').style.display = 'none';
      document.querySelector('.auth-card').style.display = 'block';
    }
  </script>
  <script src="js/main.js"></script>
</body>
</html>