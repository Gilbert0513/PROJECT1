<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'customer') {
    header('Location: index.php');
    exit;
}

$foods = [];
$res = $conn->query("SELECT * FROM food_items ORDER BY name ASC");
while ($r = $res->fetch_assoc()) $foods[] = $r;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Foodhouse | Order</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<nav>
  <h1>🍖 Foodhouse Grillhouse</h1>
  <div>
    <span>Hi, <?=htmlspecialchars($_SESSION['user']['full_name'])?></span>
    <a href="auth.php?action=logout">Logout</a>
  </div>
</nav>

<main class="container">
  <section class="card">
    <h2>Menu</h2>
    <form id="orderForm">
      <div class="menu-grid">
        <?php foreach ($foods as $f): ?>
          <div class="menu-item">
            <h3><?=htmlspecialchars($f['name'])?></h3>
            <p><?=htmlspecialchars($f['description'])?></p>
            <p class="price">₱<?=number_format($f['price'], 2)?></p>
            <p class="stock">Stock: <?=$f['stock']?></p>
            <input class="item_qty" data-id="<?=$f['id']?>" type="number" min="0" value="0" />
          </div>
        <?php endforeach; ?>
      </div>

      <input id="customer_name" value="<?=htmlspecialchars($_SESSION['user']['full_name'])?>" readonly>
      <div class="flex">
        <select id="order_type">
          <option value="Dine-in">Dine-in</option>
          <option value="Take-out">Take-out</option>
        </select>
        <select id="payment_type">
          <option value="Cash">Cash</option>
          <option value="GCash">GCash</option>
          <option value="Split">Split</option>
        </select>
      </div>
      <button type="submit">Place Order</button>
    </form>
  </section>
</main>

<footer>© <?=date('Y')?> Foodhouse</footer>
<script src="js/main.js"></script>
</body>
</html>
