<!DOCTYPE html>
<html>
<head>
    <title>Foodhouse Register</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header>Foodhouse Smart Ordering System</header>
<div class="container">
    <h2>Register</h2>
    <form onsubmit="register(event)">
        <input type="text" id="reg_fullname" placeholder="Full Name" required><br><br>
        <input type="text" id="reg_username" placeholder="Username" required><br><br>
        <input type="password" id="reg_password" placeholder="Password" required><br><br>
        <label><input type="radio" name="role" value="customer" checked> Customer</label>
        <label><input type="radio" name="role" value="staff"> Staff</label>
        <label><input type="radio" name="role" value="admin"> Admin</label><br><br>
        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="index.php">Login</a></p>
</div>
<script src="js/main.js"></script>
</body>
</html>
