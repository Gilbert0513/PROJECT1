<?php
// admin_dashboard.php
session_start();
require_once 'db.php';
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php'); exit;
}
$foods = [];
$res = $conn->query("SELECT * FROM food_items ORDER BY id DESC");
while($r = $res->fetch_assoc()) $foods[] = $r;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin - Foodhouse</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<nav>
  <h1>🍖 Foodhouse Grillhouse - Admin</h1>
  <div>
    <a href="index.php">Front</a>
    <a href="auth.php?action=logout">Logout</a>
  </div>
</nav>

<div class="container">
  <div class="dashboard">
    <div class="card">
      <h3>Inventory</h3>
      <table>
        <thead><tr><th>ID</th><th>Name</th><th>Price</th><th>Stock</th></tr></thead>
        <tbody>
          <?php foreach($foods as $f): ?>
          <tr>
            <td><?=$f['id']?></td>
            <td><?=htmlspecialchars($f['name'])?></td>
            <td>₱<?=number_format($f['price'],2)?></td>
            <td><?=$f['stock']?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h3>Add Food Item</h3>
      <form id="addFoodForm">
        <input id="food_name" placeholder="Name" required>
        <input id="food_price" type="number" step="0.01" placeholder="Price" required>
        <input id="food_stock" type="number" placeholder="Stock" required>
        <textarea id="food_desc" placeholder="Description"></textarea>
        <button type="submit">Add Item</button>
      </form>
    </div>
  </div>
</div>

<footer>© <?=date('Y')?> Foodhouse</footer>
<script src="js/main.js"></script>
</body>
</html>
