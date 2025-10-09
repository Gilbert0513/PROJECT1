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

// --- FEATURE: Fetch recent orders for reporting ---
$orders = [];
$order_res = $conn->query("SELECT * FROM orders ORDER BY order_date DESC LIMIT 10");
while($or = $order_res->fetch_assoc()) $orders[] = $or;
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
          <?php
            // --- FEATURE: Low-Stock Alert Logic ---
            $LOW_THRESHOLD = 5;
            // Highlight row if stock is low
            $low_stock_class = ($f['stock'] <= $LOW_THRESHOLD) ? 'style="background-color: #ffeaea; font-weight: 600;"' : '';
          ?>
          <tr <?=$low_stock_class?>>
            <td><?=$f['id']?></td>
            <td><?=htmlspecialchars($f['name'])?></td>
            <td>₱<?=number_format($f['price'],2)?></td>
            <td>
                <?=$f['stock']?>
                <?php if ($f['stock'] <= $LOW_THRESHOLD): ?>
                    <span style="color: red; font-size: 0.8em;">(LOW!)</span>
                <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h3>Add Food Item</h3>
      <form id="addFoodForm">
        <input id="food_name" placeholder="Name" required>
        <input id="food_desc" placeholder="Description (optional)">
        <input id="food_price" type="number" step="0.01" placeholder="Price" required>
        <input id="food_stock" type="number" placeholder="Initial Stock" required>
        <button type="submit">Add Item</button>
      </form>
    </div>

    <div class="card" style="grid-column: 1 / -1;">
        <h3>Recent Sales Report (Last 10 Orders)</h3>
        <table>
            <thead><tr><th>ID</th><th>Customer</th><th>Total</th><th>Type</th><th>Payment</th><th>Date</th></tr></thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="6" style="text-align: center; color: #888;">No recent orders found.</td></tr>
                <?php endif; ?>
                <?php foreach($orders as $o): ?>
                <tr>
                    <td>#<?=$o['id']?></td>
                    <td><?=htmlspecialchars($o['customer_name'])?></td>
                    <td>₱<?=number_format($o['total'] ?? 0, 2)?></td>
                    <td><?=$o['order_type']?></td>
                    <td><?=$o['payment_type']?></td>
                    <td><?=date('M j, Y H:i', strtotime($o['order_date']))?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
  </div> </div> <script src="main.js"></script>
</body>
</html>