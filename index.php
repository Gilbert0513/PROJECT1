<?php session_start(); if(isset($_SESSION['user_id'])){
    $role = $_SESSION['user_role'];
    if($role==='admin') header('Location: admin_dashboard.php');
    elseif($role==='staff') header('Location: staff_orders.php');
    else header('Location: customer_orders.php');
} ?>
<!DOCTYPE html>
<html>
<head>
    <title>Foodhouse Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header>Foodhouse Smart Ordering System</header>
<div class="container">
    <h2>Login</h2>
    <form onsubmit="login(event)">
        <input type="text" id="username" placeholder="Username" required><br><br>
        <input type="password" id="password" placeholder="Password" required><br><br>
        <button type="submit">Login</button>
    </form>
    <p>Don't have an account? <a href="register.php">Register</a></p>
</div>
<script src="js/main.js"></script>
</body>
</html>
