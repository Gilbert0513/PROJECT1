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
            <div class="menu-item" data-id="<?=$f['id']?>" data-price="<?=$f['price']?>" data-name="<?=htmlspecialchars($f['name'])?>">
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

  <!-- Right Column: Order Summary -->
  <section class="order-sidebar">
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
  </section>
</main>

<!-- Receipt Modal -->
<div id="receiptModal" class="modal">
  <div class="modal-content receipt">
    <div class="receipt-header">
      <div class="restaurant-icon">🍖</div>
      <h2>Foodhouse Grillhouse</h2>
      <p>Official Receipt</p>
    </div>
    
    <div class="receipt-body">
      <div class="receipt-info">
        <div class="receipt-row">
          <span>Order ID:</span>
          <span id="receiptOrderId">-</span>
        </div>
        <div class="receipt-row">
          <span>Date & Time:</span>
          <span id="receiptDate">-</span>
        </div>
        <div class="receipt-row">
          <span>Customer:</span>
          <span id="receiptCustomer">-</span>
        </div>
        <div class="receipt-row">
          <span>Order Type:</span>
          <span id="receiptOrderType">-</span>
        </div>
        <div class="receipt-row">
          <span>Payment Method:</span>
          <span id="receiptPayment">-</span>
        </div>
      </div>
      
      <div class="receipt-divider">══════════════════════════════</div>
      
      <div class="receipt-items">
        <table id="receiptItemsTable">
          <thead>
            <tr>
              <th style="text-align: left;">Item</th>
              <th style="text-align: center;">Qty</th>
              <th style="text-align: right;">Price</th>
              <th style="text-align: right;">Total</th>
            </tr>
          </thead>
          <tbody id="receiptItemsBody">
            <!-- Items will be populated here -->
          </tbody>
        </table>
      </div>
      
      <div class="receipt-divider">────────────────────────────</div>
      
      <div class="receipt-totals">
        <div class="receipt-row">
          <span>Subtotal:</span>
          <span id="receiptSubtotal">₱0.00</span>
        </div>
        <div class="receipt-row">
          <span>Service Fee:</span>
          <span id="receiptServiceFee">₱0.00</span>
        </div>
        <div class="receipt-row grand-total">
          <span><strong>TOTAL:</strong></span>
          <span id="receiptGrandTotal"><strong>₱0.00</strong></span>
        </div>
      </div>
      
      <div class="receipt-divider">══════════════════════════════</div>
      
      <div class="thank-you-section">
        <div class="thank-you-icon">🎉</div>
        <h3>Thank You for Your Order!</h3>
        <p>Your food is being prepared with love ❤️</p>
        <div class="order-timing">
          <p><strong>Estimated Ready Time:</strong></p>
          <p id="receiptReadyTime" class="ready-time">-</p>
        </div>
      </div>
      
      <div class="receipt-footer">
        <p><strong>We appreciate your business!</strong></p>
        <p>📞 (02) 1234-5678</p>
        <p>📍 123 Food Street, Manila</p>
        <p>🕒 Mon-Sun: 8:00 AM - 10:00 PM</p>
      </div>
    </div>
    
    <div class="receipt-actions">
      <button onclick="printReceipt()" class="btn-print">🖨️ Print Receipt</button>
      <button onclick="closeReceipt()" class="btn-close">Close</button>
    </div>
  </div>
</div>

<script src="js/main.js"></script>
<script>
// Store order data for receipt
let currentOrderData = null;

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

// In your user_home.php - Replace the showReceipt function with this:

function showReceipt(orderData) {
    currentOrderData = orderData;
    
    // Ensure all prices are numbers
    const processedItems = orderData.items.map(item => ({
        ...item,
        price: parseFloat(item.price) || 0,
        qty: parseInt(item.qty) || 0
    }));
    
    // Update orderData with processed items
    const processedOrderData = {
        ...orderData,
        items: processedItems,
        total: parseFloat(orderData.total) || 0,
        service_fee: parseFloat(orderData.service_fee) || 0,
        grand_total: parseFloat(orderData.grand_total) || 0
    };

    // Populate receipt data
    document.getElementById('receiptOrderId').textContent = '#' + processedOrderData.order_id;
    document.getElementById('receiptDate').textContent = new Date().toLocaleString();
    document.getElementById('receiptCustomer').textContent = processedOrderData.customer_name;
    document.getElementById('receiptOrderType').textContent = processedOrderData.order_type;
    document.getElementById('receiptPayment').textContent = processedOrderData.payment_type;
    document.getElementById('receiptSubtotal').textContent = '₱' + (processedOrderData.total || 0).toFixed(2);
    document.getElementById('receiptServiceFee').textContent = '₱' + (processedOrderData.service_fee || 0).toFixed(2);
    document.getElementById('receiptGrandTotal').textContent = '₱' + (processedOrderData.grand_total || 0).toFixed(2);
    
    // Calculate estimated ready time
    const now = new Date();
    const readyTime = new Date(now.getTime() + (20 * 60 * 1000));
    document.getElementById('receiptReadyTime').textContent = readyTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    
    // Populate items with safe number handling
    const itemsBody = document.getElementById('receiptItemsBody');
    itemsBody.innerHTML = '';
    
    processedItems.forEach(item => {
        const row = document.createElement('tr');
        const price = parseFloat(item.price) || 0;
        const qty = parseInt(item.qty) || 0;
        const total = price * qty;
        
        row.innerHTML = `
            <td style="text-align: left;">${item.name || 'Unknown Item'}</td>
            <td style="text-align: center;">${qty}</td>
            <td style="text-align: right;">₱${price.toFixed(2)}</td>
            <td style="text-align: right;">₱${total.toFixed(2)}</td>
        `;
        itemsBody.appendChild(row);
    });
    
    // Show receipt modal directly
    document.getElementById('receiptModal').style.display = 'block';
    
    // Auto-close after 30 seconds
    setTimeout(() => {
        if (document.getElementById('receiptModal').style.display === 'block') {
            closeReceipt();
        }
    }, 30000);
}


function closeReceipt() {
    document.getElementById('receiptModal').style.display = 'none';
    // Clear the form after successful order
    document.querySelectorAll('.item_qty').forEach(input => input.value = 0);
    updateOrderPreview();
}

function printReceipt() {
    const receiptContent = document.querySelector('.receipt').cloneNode(true);
    const printWindow = window.open('', '_blank');
    
    // Remove action buttons for print
    const actions = receiptContent.querySelector('.receipt-actions');
    if (actions) actions.remove();
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Receipt - Order ${currentOrderData.order_id}</title>
            <style>
                body { 
                    font-family: 'Courier New', monospace; 
                    margin: 0; 
                    padding: 20px;
                    background: white;
                    color: black;
                }
                .receipt { 
                    max-width: 300px; 
                    margin: 0 auto;
                    border: 1px solid #000;
                    padding: 20px;
                }
                .receipt-header { 
                    text-align: center; 
                    margin-bottom: 15px;
                    border-bottom: 2px dashed #000;
                    padding-bottom: 10px;
                }
                .receipt-header h2 { 
                    margin: 5px 0; 
                    font-size: 18px;
                    font-weight: bold;
                }
                .restaurant-icon {
                    font-size: 24px;
                    margin-bottom: 5px;
                }
                .receipt-divider {
                    text-align: center;
                    margin: 10px 0;
                    font-family: monospace;
                    color: #666;
                }
                .receipt-row { 
                    display: flex; 
                    justify-content: space-between; 
                    margin: 3px 0; 
                    font-size: 12px;
                }
                .grand-total { 
                    border-top: 2px solid #000; 
                    padding-top: 8px; 
                    margin-top: 8px; 
                    font-weight: bold;
                    font-size: 14px;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 10px 0;
                    font-size: 11px;
                }
                th, td { 
                    padding: 3px; 
                    text-align: left; 
                }
                th { 
                    font-weight: bold;
                    border-bottom: 1px dashed #000;
                }
                .thank-you-section {
                    text-align: center;
                    margin: 15px 0;
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 5px;
                }
                .thank-you-icon {
                    font-size: 24px;
                    margin-bottom: 5px;
                }
                .thank-you-section h3 {
                    margin: 5px 0;
                    color: #d87b3e;
                }
                .receipt-footer { 
                    text-align: center; 
                    margin-top: 15px; 
                    font-size: 10px; 
                    color: #666;
                    border-top: 1px dashed #000;
                    padding-top: 10px;
                }
                .ready-time {
                    font-size: 14px;
                    color: #d87b3e;
                    font-weight: bold;
                }
                @media print { 
                    body { margin: 0; }
                    .receipt { border: none; padding: 10px; }
                }
            </style>
        </head>
        <body>
            ${receiptContent.innerHTML}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const receiptModal = document.getElementById('receiptModal');
    if (event.target === receiptModal) {
        closeReceipt();
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
        closeReceipt();
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