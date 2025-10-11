<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'customer') {
    header('Location: index.php');
    exit;
}

$customer_name = $_SESSION['user']['full_name'];
$customer_id = $_SESSION['user']['id'];

// Get cart items from session FIRST
$cart = $_SESSION['cart'] ?? [];

// DEBUG: Check database and tables - MOVED AFTER $cart is defined
error_log("=== USER CHECKOUT DEBUG ===");
error_log("Customer: " . $_SESSION['user']['full_name']);
error_log("Cart count: " . count($cart));

// Check what tables exist
$tables_result = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $tables_result->fetch_row()) {
    $tables[] = $row[0];
}
error_log("Available tables: " . implode(', ', $tables));

// Check if customer_order table exists
$table_check = $conn->query("SHOW TABLES LIKE 'customer_order'");
error_log("customer_order table exists: " . ($table_check->num_rows > 0 ? 'YES' : 'NO'));

// Check current orders count
$current_orders = $conn->query("SELECT COUNT(*) as count FROM customer_order")->fetch_assoc();
error_log("Current orders in database: " . $current_orders['count']);

// Check database name
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
error_log("Database name: " . $db_name);

// Create tables if they don't exist
$create_tables_sql = [
    "CREATE TABLE IF NOT EXISTS customer_order (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(255) NOT NULL,
        order_type VARCHAR(50) NOT NULL,
        payment_type VARCHAR(50) NOT NULL,
        special_instructions TEXT,
        subtotal DECIMAL(10,2) NOT NULL,
        service_fee DECIMAL(10,2) NOT NULL,
        total DECIMAL(10,2) NOT NULL,
        status VARCHAR(50) DEFAULT 'pending',
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS customer_order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT,
        food_id INT,
        food_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES customer_order(id) ON DELETE CASCADE
    )"
];

foreach ($create_tables_sql as $sql) {
    if (!$conn->query($sql)) {
        error_log("Table creation failed: " . $conn->error);
    } else {
        error_log("Table created/verified successfully");
    }
}

// Calculate totals
$subtotal = 0;
$service_fee = 0;
$total = 0;

foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$service_fee = $subtotal * 0.05; // 5% service fee
$total = $subtotal + $service_fee;

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_type = $_POST['order_type'] ?? 'Dine-in';
    $payment_type = $_POST['payment_type'] ?? 'Cash';
    $special_instructions = $_POST['special_instructions'] ?? '';
    
    // Debug: Check if cart is valid
    if (empty($cart)) {
        $error = "Cart is empty";
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
    }
    
    // Debug: Check database connection
    if ($conn->connect_error) {
        $error = "Database connection failed: " . $conn->connect_error;
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Debug: Log order attempt
        error_log("Attempting to place order for customer: $customer_name");
        error_log("Cart items: " . count($cart));
        
        // 1. Insert into customer_order table
        $order_query = "INSERT INTO customer_order (customer_name, order_type, payment_type, special_instructions, subtotal, service_fee, total, status) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
        $order_stmt = $conn->prepare($order_query);
        
        if (!$order_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $order_stmt->bind_param('ssssddd', $customer_name, $order_type, $payment_type, $special_instructions, $subtotal, $service_fee, $total);
        
        if (!$order_stmt->execute()) {
            throw new Exception("Order insert failed: " . $order_stmt->error);
        }
        
        $order_id = $conn->insert_id;
        error_log("Order created with ID: $order_id");
        
        // 2. Insert into customer_order_items table and update stock
        $order_item_query = "INSERT INTO customer_order_items (order_id, food_id, food_name, quantity, price) VALUES (?, ?, ?, ?, ?)";
        $stock_update_query = "UPDATE food_items SET stock = stock - ? WHERE id = ?";
        
        $order_item_stmt = $conn->prepare($order_item_query);
        $stock_stmt = $conn->prepare($stock_update_query);
        
        if (!$order_item_stmt) {
            throw new Exception("Order items prepare failed: " . $conn->error);
        }
        
        if (!$stock_stmt) {
            throw new Exception("Stock update prepare failed: " . $conn->error);
        }
        
        foreach ($cart as $food_id => $item) {
            // Debug: Log each item
            error_log("Processing item: {$item['name']} (ID: $food_id) x {$item['quantity']}");
            
            // Insert order item
            $order_item_stmt->bind_param('iisid', $order_id, $food_id, $item['name'], $item['quantity'], $item['price']);
            if (!$order_item_stmt->execute()) {
                throw new Exception("Order item insert failed for food_id $food_id: " . $order_item_stmt->error);
            }
            
            // Update stock
            $stock_stmt->bind_param('ii', $item['quantity'], $food_id);
            if (!$stock_stmt->execute()) {
                throw new Exception("Stock update failed for food_id $food_id: " . $stock_stmt->error);
            }
            
            error_log("Successfully processed item: {$item['name']}");
        }
        
        // 3. Commit transaction
        $conn->commit();
        error_log("Order $order_id successfully committed");
        
        // 4. Store receipt data and clear cart
        // Convert cart object to array for consistent structure
        $cart_items = [];
        foreach ($cart as $food_id => $item) {
            $cart_items[] = [
                'food_id' => $food_id,
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['quantity']
            ];
        }
        
        $receipt_data = [
            'order_id' => $order_id,
            'total' => $total,
            'order_type' => $order_type,
            'payment_type' => $payment_type,
            'subtotal' => $subtotal,
            'service_fee' => $service_fee,
            'customer_name' => $customer_name,
            'order_date' => date('Y-m-d H:i:s'),
            'items' => $cart_items  // Use the array instead of the object
        ];
        
        unset($_SESSION['cart']);
        
        // Return JSON response for AJAX
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'receipt' => $receipt_data
            ]);
            exit;
        } else {
            $_SESSION['receipt_data'] = $receipt_data;
            header('Location: user_checkout.php?success=1');
            exit;
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Detailed error logging
        error_log("Order Error: " . $e->getMessage());
        error_log("Cart contents: " . print_r($cart, true));
        error_log("Post data: " . print_r($_POST, true));
        
        $error = "Order failed: " . $e->getMessage();
        
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'error' => $error,
                'debug' => [
                    'message' => $e->getMessage(),
                    'cart_count' => count($cart),
                    'post_data' => $_POST
                ]
            ]);
            exit;
        }
    }
}

// Check if we have receipt data from redirect
$receipt_data = $_SESSION['receipt_data'] ?? null;
if ($receipt_data && isset($_GET['success'])) {
    // Clear the receipt data after displaying
    unset($_SESSION['receipt_data']);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Foodhouse | Checkout</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .receipt-modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }
    .receipt-content {
      background: white;
      padding: 30px;
      border-radius: 10px;
      max-width: 500px;
      width: 90%;
      max-height: 90vh;
      overflow-y: auto;
      font-family: 'Courier New', monospace;
      border: 2px solid #333;
    }
    .receipt-header {
      text-align: center;
      border-bottom: 2px dashed #333;
      padding-bottom: 15px;
      margin-bottom: 15px;
    }
    .receipt-item {
      display: flex;
      justify-content: space-between;
      margin: 8px 0;
      padding: 5px 0;
      border-bottom: 1px dashed #eee;
    }
    .receipt-total {
      border-top: 2px dashed #333;
      padding-top: 15px;
      margin-top: 15px;
      font-weight: bold;
      font-size: 1.1em;
    }
    .receipt-footer {
      text-align: center;
      margin-top: 20px;
      font-size: 0.9rem;
      color: #666;
    }
    .modal-buttons {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin-top: 20px;
    }
    .close-receipt {
      position: absolute;
      top: 10px;
      right: 15px;
      font-size: 24px;
      cursor: pointer;
      background: none;
      border: none;
    }
    @media print {
      body * {
        visibility: hidden;
      }
      .receipt-content, .receipt-content * {
        visibility: visible;
      }
      .receipt-content {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        border: none;
        box-shadow: none;
      }
      .no-print {
        display: none !important;
      }
    }
  </style>
</head>
<body>
<nav>
  <h1>🍖 Foodhouse</h1>
  <div>
    <span>Hi, <?=htmlspecialchars($customer_name)?></span>
    <a href="user_home.php">← Back to Menu</a>
    <a href="auth.php?action=logout">Logout</a>
  </div>
</nav>

<main class="container">
  <section class="card">
    <h2>Checkout</h2>
    
    <?php if (isset($error)): ?>
      <div class="alert alert-error"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>

    <?php if (empty($cart) && !isset($_GET['success'])): ?>
      <div class="empty-cart">
        <p>Your cart is empty</p>
        <a href="user_home.php" class="btn btn-primary">Browse Menu</a>
      </div>
    <?php elseif (!isset($_GET['success'])): ?>
      <form method="POST" id="checkoutForm">
        <div class="grid-2">
          <!-- Order Items -->
          <div>
            <h3>Order Items</h3>
            <div class="order-items">
              <?php foreach ($cart as $food_id => $item): ?>
                <div class="order-item">
                  <div class="item-info">
                    <h4><?=htmlspecialchars($item['name'])?></h4>
                    <p>₱<?=number_format($item['price'], 2)?> x <?=$item['quantity']?></p>
                  </div>
                  <div class="item-total">
                    ₱<?=number_format($item['price'] * $item['quantity'], 2)?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Order Details -->
          <div>
            <h3>Order Details</h3>
            <div class="form-group">
              <label for="order_type">Order Type</label>
              <select id="order_type" name="order_type" required>
                <option value="Dine-in">Dine-in</option>
                <option value="Take-out">Take-out</option>
              </select>
            </div>
            
            <div class="form-group">
              <label for="payment_type">Payment Method</label>
              <select id="payment_type" name="payment_type" required>
                <option value="Cash">Cash</option>
                <option value="GCash">GCash</option>
                <option value="Credit Card">Credit Card</option>
              </select>
            </div>
            
            <div class="form-group">
              <label for="special_instructions">Special Instructions</label>
              <textarea id="special_instructions" name="special_instructions" rows="3" placeholder="Any special requests..."></textarea>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
              <div class="summary-line">
                <span>Subtotal:</span>
                <span>₱<?=number_format($subtotal, 2)?></span>
              </div>
              <div class="summary-line">
                <span>Service Fee (5%):</span>
                <span>₱<?=number_format($service_fee, 2)?></span>
              </div>
              <div class="summary-line total">
                <span><strong>Total:</strong></span>
                <span><strong>₱<?=number_format($total, 2)?></strong></span>
              </div>
            </div>

            <button type="submit" class="btn btn-primary btn-large" id="placeOrderBtn">Place Order</button>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </section>
</main>

<!-- Receipt Modal -->
<div id="receiptModal" class="receipt-modal">
  <div class="receipt-content">
    <button class="close-receipt" onclick="closeReceipt()">&times;</button>
    <div class="receipt-header">
      <h2>🍖 FOODHOUSE</h2>
      <p>Order Receipt</p>
      <p><strong>Order #: <span id="receiptOrderId"></span></strong></p>
      <p>Date: <span id="receiptDate"></span></p>
    </div>
    
    <div class="receipt-customer">
      <p><strong>Customer:</strong> <span id="receiptCustomer"></span></p>
      <p><strong>Order Type:</strong> <span id="receiptOrderType"></span></p>
      <p><strong>Payment:</strong> <span id="receiptPayment"></span></p>
    </div>
    
    <hr style="border: 1px dashed #333; margin: 15px 0;">
    
    <div class="receipt-items">
      <p><strong>ITEMS ORDERED:</strong></p>
      <div id="receiptItemsList"></div>
    </div>
    
    <hr style="border: 1px dashed #333; margin: 15px 0;">
    
    <div class="receipt-totals">
      <div class="receipt-item">
        <span>Subtotal:</span>
        <span id="receiptSubtotal">₱0.00</span>
      </div>
      <div class="receipt-item">
        <span>Service Fee:</span>
        <span id="receiptServiceFee">₱0.00</span>
      </div>
      <div class="receipt-item receipt-total">
        <span>TOTAL:</span>
        <span id="receiptTotal">₱0.00</span>
      </div>
    </div>
    
    <div class="receipt-footer">
      <p><strong>Thank you for your order!</strong></p>
      <p>Estimated ready: 15-25 minutes</p>
      <p>For questions: (02) 1234-5678</p>
    </div>
    
    <div class="modal-buttons">
      <button onclick="printReceipt()" class="btn btn-primary">🖨️ Print Receipt</button>
      <button onclick="closeReceipt()" class="btn btn-secondary">Continue Shopping</button>
    </div>
  </div>
</div>

<script>
// Handle form submission with AJAX to show receipt popup
document.getElementById('checkoutForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    const originalText = placeOrderBtn.innerHTML;
    placeOrderBtn.innerHTML = 'Placing Order...';
    placeOrderBtn.disabled = true;
    
    try {
        const formData = new FormData(this);
        formData.append('ajax', 'true');
        
        console.log('Sending order request...');
        
        const response = await fetch('user_checkout.php', {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        console.log('Raw response:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Failed to parse JSON:', parseError);
            throw new Error('Invalid response from server');
        }
        
        console.log('Parsed data:', data);
        
        if (data.success) {
            console.log('Receipt data structure:', data.receipt);
            console.log('Items type:', typeof data.receipt.items);
            console.log('Items value:', data.receipt.items);
            
            showReceipt(data.receipt);
        } else {
            console.error('Order failed:', data);
            alert('Order failed: ' + (data.error || 'Unknown error'));
            placeOrderBtn.innerHTML = originalText;
            placeOrderBtn.disabled = false;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Order failed. Please try again. Check console for details.');
        placeOrderBtn.innerHTML = originalText;
        placeOrderBtn.disabled = false;
    }
});

// Show receipt modal
function showReceipt(receiptData) {
    console.log('Showing receipt:', receiptData);
    
    // Populate receipt data
    document.getElementById('receiptOrderId').textContent = receiptData.order_id || 'N/A';
    document.getElementById('receiptDate').textContent = new Date(receiptData.order_date || Date.now()).toLocaleString();
    document.getElementById('receiptCustomer').textContent = receiptData.customer_name || 'N/A';
    document.getElementById('receiptOrderType').textContent = receiptData.order_type || 'N/A';
    document.getElementById('receiptPayment').textContent = receiptData.payment_type || 'N/A';
    document.getElementById('receiptSubtotal').textContent = '₱' + (receiptData.subtotal || 0).toFixed(2);
    document.getElementById('receiptServiceFee').textContent = '₱' + (receiptData.service_fee || 0).toFixed(2);
    document.getElementById('receiptTotal').textContent = '₱' + (receiptData.total || 0).toFixed(2);
    
    // Populate items list
    const itemsList = document.getElementById('receiptItemsList');
    itemsList.innerHTML = '';
    
    // Handle different cart structures
    let items = [];
    
    if (Array.isArray(receiptData.items)) {
        // If items is already an array, use it directly
        items = receiptData.items;
    } else if (receiptData.items && typeof receiptData.items === 'object') {
        // If items is an object (with food IDs as keys), convert to array
        items = Object.values(receiptData.items);
    }
    
    console.log('Processed items:', items);
    
    if (items.length === 0) {
        const emptyElement = document.createElement('div');
        emptyElement.className = 'receipt-item';
        emptyElement.innerHTML = '<span>No items found</span>';
        itemsList.appendChild(emptyElement);
    } else {
        items.forEach(item => {
            const itemElement = document.createElement('div');
            itemElement.className = 'receipt-item';
            itemElement.innerHTML = `
                <span>${item.quantity}x ${item.name}</span>
                <span>₱${((item.price || 0) * (item.quantity || 0)).toFixed(2)}</span>
            `;
            itemsList.appendChild(itemElement);
        });
    }
    
    // Show modal
    document.getElementById('receiptModal').style.display = 'flex';
}

// Close receipt modal
function closeReceipt() {
    document.getElementById('receiptModal').style.display = 'none';
    // Redirect to menu page
    window.location.href = 'user_home.php';
}

// Print receipt
function printReceipt() {
    window.print();
}

// Close modal when clicking outside
document.getElementById('receiptModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReceipt();
    }
});

// Show receipt if we have data from redirect (non-AJAX fallback)
<?php if ($receipt_data && isset($_GET['success'])): ?>
showReceipt(<?=json_encode($receipt_data)?>);
<?php endif; ?>
</script>
</body>
</html>