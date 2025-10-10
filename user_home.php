<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'customer') {
    header('Location: index.php');
    exit;
}

// Get food items with stock > 0
$foods = [];
$res = $conn->query("SELECT * FROM food_items WHERE stock > 0 ORDER BY name ASC");
while ($r = $res->fetch_assoc()) $foods[] = $r;

// Get user's recent orders
$user_orders = [];
$user_id = $_SESSION['user']['id'];
$order_res = $conn->query("
    SELECT o.*, SUM(oi.quantity) as total_items 
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.customer_name = '".$conn->real_escape_string($_SESSION['user']['full_name'])."'
    GROUP BY o.id 
    ORDER BY o.order_date DESC 
    LIMIT 5
");
while ($or = $order_res->fetch_assoc()) $user_orders[] = $or;
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

<main class="container grid-2">
  <!-- Left Column: Menu -->
  <section class="card">
    <h2>🍽️ Our Menu</h2>
    <p class="subtitle">Select items and quantities to place your order</p>
    
    <form id="orderForm">
      <div class="menu-grid">
        <?php if (empty($foods)): ?>
          <div class="no-items">
            <p>😔 No items available at the moment.</p>
            <p>Please check back later!</p>
          </div>
        <?php else: ?>
          <?php foreach ($foods as $f): ?>
            <div class="menu-item" data-id="<?=$f['id']?>" data-price="<?=$f['price']?>">
              <h3><?=htmlspecialchars($f['name'])?></h3>
              <?php if (!empty($f['description'])): ?>
                <p class="description"><?=htmlspecialchars($f['description'])?></p>
              <?php endif; ?>
              <p class="price">₱<?=number_format($f['price'], 2)?></p>
              <p class="stock <?=$f['stock'] <= 5 ? 'low-stock' : ''?>">
                Stock: <?=$f['stock']?>
                <?php if ($f['stock'] <= 5): ?>
                  <span class="stock-warning">(Low Stock)</span>
                <?php endif; ?>
              </p>
              <div class="quantity-controls">
                <button type="button" class="qty-btn minus" onclick="adjustQuantity(<?=$f['id']?>, -1)">-</button>
                <input class="item_qty" data-id="<?=$f['id']?>" type="number" min="0" max="<?=$f['stock']?>" value="0" 
                       onchange="validateQuantity(<?=$f['id']?>, <?=$f['stock']?>)">
                <button type="button" class="qty-btn plus" onclick="adjustQuantity(<?=$f['id']?>, 1)">+</button>
              </div>
              <button type="button" class="btn-add" onclick="addToCart(<?=$f['id']?>)">
                Add to Cart
              </button>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <hr style="margin: 1.5rem 0; border-color: #eee;">
      
      <div class="form-group">
        <label for="customer_name">Customer Name</label>
        <input id="customer_name" value="<?=htmlspecialchars($_SESSION['user']['full_name'])?>" readonly>
      </div>
      
      <div class="flex">
        <div class="form-group">
          <label for="order_type">Order Type</label>
          <select id="order_type">
            <option value="Dine-in">🍽️ Dine-in</option>
            <option value="Take-out">🥡 Take-out</option>
            <option value="Delivery">🚚 Delivery</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="payment_type">Payment Method</label>
          <select id="payment_type">
            <option value="Cash">💵 Cash</option>
            <option value="GCash">📱 GCash</option>
            <option value="Credit Card">💳 Credit Card</option>
            <option value="Split">🔀 Split Payment</option>
          </select>
        </div>
      </div>
      
      <div class="form-group">
        <label for="special_instructions">Special Instructions (Optional)</label>
        <textarea id="special_instructions" placeholder="Any special requests or dietary restrictions..." rows="3"></textarea>
      </div>
    </form>
  </section>

  <!-- Right Column: Order Summary & Recent Orders -->
  <section class="order-sidebar">
    <!-- Order Summary -->
    <div class="card">
      <h2>🛒 Order Summary</h2>
      <div id="orderSummaryDetails">
        <p style="text-align: center; color: #888;">Select items from the menu to see the summary.</p>
      </div>
      
      <hr style="margin: 1.5rem 0; border-color: #eee;">
      
      <div class="order-total">
        <div class="total-line">
          <span>Subtotal:</span>
          <span id="subtotal">₱0.00</span>
        </div>
        <div class="total-line">
          <span>Service Fee:</span>
          <span id="serviceFee">₱0.00</span>
        </div>
        <div class="total-line grand-total">
          <span><strong>Total:</strong></span>
          <span id="orderTotal"><strong>₱0.00</strong></span>
        </div>
      </div>
      
      <button type="submit" form="orderForm" class="btn-order">Place Order</button>
      
      <div class="order-notice">
        <p>📞 Need help? Call: (02) 1234-5678</p>
        <p>⏰ Preparation time: 15-25 minutes</p>
      </div>
    </div>

    <!-- Recent Orders -->
    <div class="card">
      <h2>📋 Recent Orders</h2>
      <div class="recent-orders">
        <?php if (empty($user_orders)): ?>
          <p style="text-align: center; color: #888;">No recent orders found.</p>
        <?php else: ?>
          <?php foreach ($user_orders as $order): ?>
            <div class="recent-order-item">
              <div class="order-header">
                <span class="order-id">#<?=$order['id']?></span>
                <span class="order-date"><?=date('M j, g:i A', strtotime($order['order_date']))?></span>
              </div>
              <div class="order-details">
                <span class="order-type"><?=$order['order_type']?></span>
                <span class="order-total">₱<?=number_format($order['total'], 2)?></span>
              </div>
              <div class="order-status <?=strtolower($order['status'] ?? 'completed')?>">
                <?=ucfirst($order['status'] ?? 'completed')?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <?php if (!empty($user_orders)): ?>
        <a href="order_history.php" class="view-all-orders">View All Orders →</a>
      <?php endif; ?>
    </div>
  </section>
</main>

<!-- Order Success Modal -->
<div id="orderSuccessModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>🎉 Order Placed Successfully!</h2>
      <span class="close">&times;</span>
    </div>
    <div class="modal-body">
      <p>Your order <strong id="modalOrderId"></strong> has been placed successfully!</p>
      <div class="order-details-modal">
        <p><strong>Total Amount:</strong> <span id="modalOrderTotal"></span></p>
        <p><strong>Order Type:</strong> <span id="modalOrderType"></span></p>
        <p><strong>Estimated Ready:</strong> <span id="modalReadyTime"></span></p>
      </div>
      <p class="modal-note">You will receive an SMS confirmation shortly.</p>
    </div>
    <div class="modal-footer">
      <button onclick="closeModal()" class="btn-primary">Continue Shopping</button>
      <button onclick="printReceipt()" class="btn-secondary">Print Receipt</button>
    </div>
  </div>
</div>

<script src="js/main.js"></script>
<script>
// Additional JavaScript for user home page
function adjustQuantity(itemId, change) {
    const input = document.querySelector(`.item_qty[data-id="${itemId}"]`);
    const currentValue = parseInt(input.value) || 0;
    const newValue = currentValue + change;
    const maxStock = parseInt(input.max);
    
    if (newValue >= 0 && newValue <= maxStock) {
        input.value = newValue;
        updateOrderPreview();
        
        // Visual feedback
        const itemElement = input.closest('.menu-item');
        if (change > 0) {
            itemElement.classList.add('highlight-add');
            setTimeout(() => itemElement.classList.remove('highlight-add'), 500);
        } else {
            itemElement.classList.add('highlight-remove');
            setTimeout(() => itemElement.classList.remove('highlight-remove'), 500);
        }
    }
}

function validateQuantity(itemId, maxStock) {
    const input = document.querySelector(`.item_qty[data-id="${itemId}"]`);
    let value = parseInt(input.value) || 0;
    
    if (value < 0) value = 0;
    if (value > maxStock) value = maxStock;
    
    input.value = value;
    updateOrderPreview();
}

function addToCart(itemId) {
    const input = document.querySelector(`.item_qty[data-id="${itemId}"]`);
    const maxStock = parseInt(input.max);
    const currentValue = parseInt(input.value) || 0;
    
    if (currentValue < maxStock) {
        input.value = currentValue + 1;
        updateOrderPreview();
        
        // Visual feedback
        const itemElement = input.closest('.menu-item');
        itemElement.classList.add('highlight-add');
        setTimeout(() => itemElement.classList.remove('highlight-add'), 1000);
        
        // Show quick notification
        showQuickNotification('Item added to cart!');
    } else {
        showQuickNotification('Cannot add more - limited stock!', 'error');
    }
}

function showQuickNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `quick-notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

function closeModal() {
    document.getElementById('orderSuccessModal').style.display = 'none';
    // Clear the form after successful order
    document.querySelectorAll('.item_qty').forEach(input => input.value = 0);
    updateOrderPreview();
}

function printReceipt() {
    // Simple print functionality
    window.print();
}

// Close modal when clicking on X
document.querySelector('.close').addEventListener('click', closeModal);

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('orderSuccessModal');
    if (event.target === modal) {
        closeModal();
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + Enter to submit order
    if (e.ctrlKey && e.key === 'Enter') {
        const orderForm = document.getElementById('orderForm');
        orderForm.dispatchEvent(new Event('submit'));
    }
    
    // Escape to close modal
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    updateOrderPreview();
    
    // Add animation to menu items on load
    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
        item.classList.add('fade-in');
    });
});
</script>
</body>
</html>