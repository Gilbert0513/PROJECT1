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
    @media (max-width: 768px) {
      .download-buttons {
        flex-direction: column;
        align-items: center;
      }
    }
  </style>
</head>
<body class="auth-body">

  <!-- Navigation -->
  <nav class="auth-nav">
    <h1>🍖 Foodhouse Smart Ordering System</h1>
  </nav>

  <!-- Platform Introduction -->
  <div class="platform-intro">
    <h2>Multi-Platform Food Ordering System</h2>
    <p>Access our system seamlessly on any device - desktop, tablet, or mobile</p>
    
    <div class="platform-features">
      <div class="feature-card">
        <div style="font-size: 3rem;">💻</div>
        <h3>Web Platform</h3>
        <p>Full-featured access through any web browser</p>
      </div>
      <div class="feature-card">
        <div style="font-size: 3rem;">📱</div>
        <h3>Mobile Responsive</h3>
        <p>Optimized experience on all mobile devices</p>
      </div>
      <div class="feature-card">
        <div style="font-size: 3rem;">🔄</div>
        <h3>Real-time Sync</h3>
        <p>Instant updates across all platforms</p>
      </div>
    </div>
  </div>

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
    </div>

    <!-- Register Card -->
     <!-- Add this to your admin login page -->
   <div class="switch-login" style="text-align: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
    <p>Are you a cashier? <a href="cashier_login.php">Login as Cashier</a></p>
    
  </div>
    <div class="auth-card">
      <h2>Create Account</h2>
      <p class="subtitle">Join our smart ordering platform</p>
      <form id="registerForm">
        <input id="reg_fullname" placeholder="Full name" required>
        <input id="reg_username" placeholder="Username" required>
        <input id="reg_password" type="password" placeholder="Password" required>

        <div class="role-select">
          <label><input type="radio" name="role" value="customer" checked> Customer</label>
          <label><input type="radio" name="role" value="admin"> Admin</label>
        </div>

        <button type="submit">Register Account</button>
      </form>
    </div>
  </div>

  <!-- Mobile App Section -->
  <div class="app-download">
    <h3>📱 Mobile App Coming Soon!</h3>
    <p>Native iOS and Android apps for enhanced mobile experience</p>
    <div class="download-buttons">
      <button class="btn-app" style="background: #000; color: white; padding: 12px 24px; border: none; border-radius: 8px;">
        🍎 App Store (Soon)
      </button>
      <button class="btn-app" style="background: #0F9D58; color: white; padding: 12px 24px; border: none; border-radius: 8px;">
        🤖 Play Store (Soon)
      </button>
    </div>
  </div>

  <footer>
    <p>© <?=date('Y')?> Foodhouse Smart Ordering & Inventory Management System</p>
    <p style="font-size: 0.9rem; color: #666;">Web & Mobile Platform</p>
  </footer>
  
  <script src="js/main.js"></script>
</body>
</html>