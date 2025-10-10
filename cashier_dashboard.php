<?php
// cashier_dashboard.php
session_start();
require_once 'db.php';

// Redirect if not logged in as cashier
if (empty($_SESSION['cashier_user']) || $_SESSION['cashier_user']['role'] !== 'cashier') {
    header('Location: cashier_login.php');
    exit;
}

$cashier_user = $_SESSION['cashier_user'];

// Get current tab
$tab = $_GET['tab'] ?? 'new_order';

// Get available food items
$food_items = [];
$food_res = $conn->query("SELECT * FROM food_items WHERE stock > 0 ORDER BY category, name");
while($food = $food_res->fetch_assoc()) $food_items[] = $food;

// Get available tables
$tables = [];
$table_res = $conn->query("SELECT * FROM tables WHERE status = 'available' ORDER BY table_number");
while($table = $table_res->fetch_assoc()) $tables[] = $table;

// Get active orders
$active_orders = [];
$orders_res = $conn->query("
    SELECT co.*, t.table_number 
    FROM cashier_orders co 
    LEFT JOIN tables t ON co.table_id = t.id 
    WHERE co.status IN ('pending', 'preparing', 'ready')
    ORDER BY co.order_date DESC
");
while($order = $orders_res->fetch_assoc()) $active_orders[] = $order;

// Handle new order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $order_type = $_POST['order_type'];
    $customer_name = trim($_POST['customer_name']);
    $table_id = $order_type === 'dine_in' ? $_POST['table_id'] : NULL;
    $items = $_POST['items'] ?? [];
    
    if (!empty($customer_name) && !empty($items)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create order
            $stmt = $conn->prepare("INSERT INTO cashier_orders (table_id, order_type, customer_name) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $table_id, $order_type, $customer_name);
            $stmt->execute();
            $order_id = $conn->insert_id;
            
            $total_amount = 0;
            
            // Add order items
            foreach ($items as $food_id => $item) {
                $quantity = intval($item['quantity']);
                if ($quantity > 0) {
                    $food_stmt = $conn->prepare("SELECT price, stock FROM food_items WHERE id = ?");
                    $food_stmt->bind_param("i", $food_id);
                    $food_stmt->execute();
                    $food = $food_stmt->get_result()->fetch_assoc();
                    
                    if ($food && $food['stock'] >= $quantity) {
                        $price = $food['price'];
                        $subtotal = $price * $quantity;
                        $total_amount += $subtotal;
                        
                        $item_stmt = $conn->prepare("INSERT INTO cashier_order_items (order_id, food_id, quantity, price) VALUES (?, ?, ?, ?)");
                        $item_stmt->bind_param("iiid", $order_id, $food_id, $quantity, $price);
                        $item_stmt->execute();
                        
                        // Update stock
                        $update_stmt = $conn->prepare("UPDATE food_items SET stock = stock - ? WHERE id = ?");
                        $update_stmt->bind_param("ii", $quantity, $food_id);
                        $update_stmt->execute();
                    }
                }
            }
            
            // Update order total
            $update_total = $conn->prepare("UPDATE cashier_orders SET total = ? WHERE id = ?");
            $update_total->bind_param("di", $total_amount, $order_id);
            $update_total->execute();
            
            // Update table status if dine-in
            if ($order_type === 'dine_in' && $table_id) {
                $table_update = $conn->prepare("UPDATE tables SET status = 'occupied' WHERE id = ?");
                $table_update->bind_param("i", $table_id);
                $table_update->execute();
            }
            
            $conn->commit();
            header('Location: cashier_dashboard.php?tab=active_orders&success=1');
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to create order: " . $e->getMessage();
        }
    } else {
        $error = "Please fill all required fields and add at least one item!";
    }
}

// Handle order status update
if (isset($_GET['update_status'])) {
    $order_id = intval($_GET['order_id']);
    $new_status = $_GET['status'];
    
    $stmt = $conn->prepare("UPDATE cashier_orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    
    if ($stmt->execute()) {
        // If order is completed and it's dine-in, free the table
        if ($new_status === 'completed') {
            $order_stmt = $conn->prepare("SELECT table_id FROM cashier_orders WHERE id = ?");
            $order_stmt->bind_param("i", $order_id);
            $order_stmt->execute();
            $order = $order_stmt->get_result()->fetch_assoc();
            
            if ($order && $order['table_id']) {
                $table_update = $conn->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
                $table_update->bind_param("i", $order['table_id']);
                $table_update->execute();
            }
        }
        
        header('Location: cashier_dashboard.php?tab=active_orders&updated=1');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Dashboard - Foodhouse</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: #f5f5f5;
        }
        
        .cashier-header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left h1 {
            color: #d87b3e;
            font-size: 1.5rem;
        }
        
        .cashier-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .cashier-name {
            font-weight: 600;
            color: #333;
        }
        
        .role-badge {
            background: #d87b3e;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .btn-logout {
            background: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
        }
        
        .cashier-layout {
            display: flex;
            min-height: calc(100vh - 80px);
        }
        
        .cashier-sidebar {
            width: 250px;
            background: white;
            padding: 1rem;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            text-decoration: none;
            color: #333;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .sidebar-link:hover, .sidebar-link.active {
            background: #d87b3e;
            color: white;
        }
        
        .cashier-content {
            flex: 1;
            padding: 2rem;
        }
        
        .content-header {
            margin-bottom: 2rem;
        }
        
        .content-header h2 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #d87b3e;
        }
        
        .order-type-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .order-type-btn {
            flex: 1;
            padding: 1rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            background: white;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        
        .order-type-btn.active {
            border-color: #d87b3e;
            background: #d87b3e;
            color: white;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .menu-item {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .menu-item.selected {
            border-color: #d87b3e;
            background: #fff5f0;
        }
        
        .menu-item-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .menu-item-price {
            color: #d87b3e;
            font-weight: 600;
        }
        
        .menu-item-stock {
            color: #666;
            font-size: 0.8rem;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .quantity-control input {
            width: 60px;
            text-align: center;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: #d87b3e;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: black;
        }
        
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .order-card {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 1.5rem;
            background: white;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .order-id {
            font-weight: 600;
            color: #333;
        }
        
        .order-type {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .order-type.dine_in {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .order-type.take_out {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .order-status {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-preparing { background: #cce7ff; color: #004085; }
        .status-ready { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        
        .order-items {
            margin-bottom: 1rem;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .order-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <header class="cashier-header">
        <div class="header-left">
            <h1>🍽️ Foodhouse - Cashier</h1>
        </div>
        <div class="cashier-info">
            <span class="cashier-name"><?= htmlspecialchars($cashier_user['full_name']) ?></span>
            <span class="role-badge">Cashier</span>
            <a href="cashier_logout.php" class="btn-logout">Logout</a>
        </div>
    </header>
    
    <div class="cashier-layout">
        <aside class="cashier-sidebar">
            <a href="?tab=new_order" class="sidebar-link <?= $tab == 'new_order' ? 'active' : '' ?>">
                ➕ New Order
            </a>
            <a href="?tab=active_orders" class="sidebar-link <?= $tab == 'active_orders' ? 'active' : '' ?>">
                📦 Active Orders
            </a>
            <a href="?tab=menu" class="sidebar-link <?= $tab == 'menu' ? 'active' : '' ?>">
                📋 Menu
            </a>
        </aside>
        
        <main class="cashier-content">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Order created successfully!</div>
            <?php endif; ?>
            
            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success">Order status updated successfully!</div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($tab == 'new_order'): ?>
                <div class="content-header">
                    <h2>➕ Create New Order</h2>
                    <p>Select order type and add items</p>
                </div>
                
                <form method="POST" action="">
                    <div class="card">
                        <h3>1. Order Type</h3>
                        <div class="order-type-selector">
                            <div class="order-type-btn <?= ($_POST['order_type'] ?? '') == 'dine_in' ? 'active' : '' ?>" 
                                 onclick="selectOrderType('dine_in')">
                                🍽️ Dine In
                            </div>
                            <div class="order-type-btn <?= ($_POST['order_type'] ?? '') == 'take_out' ? 'active' : '' ?>" 
                                 onclick="selectOrderType('take_out')">
                                🥡 Take Out
                            </div>
                        </div>
                        
                        <input type="hidden" name="order_type" id="order_type" value="<?= $_POST['order_type'] ?? 'dine_in' ?>" required>
                        
                        <div id="dine_in_section" style="display: <?= ($_POST['order_type'] ?? 'dine_in') == 'dine_in' ? 'block' : 'none' ?>;">
                            <div class="form-group">
                                <label for="table_id">Select Table *</label>
                                <select name="table_id" id="table_id" required>
                                    <option value="">Choose a table</option>
                                    <?php foreach($tables as $table): ?>
                                        <option value="<?= $table['id'] ?>" <?= ($_POST['table_id'] ?? '') == $table['id'] ? 'selected' : '' ?>>
                                            Table <?= $table['table_number'] ?> (Capacity: <?= $table['capacity'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_name">Customer Name *</label>
                            <input type="text" name="customer_name" id="customer_name" 
                                   value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>" 
                                   placeholder="Enter customer name" required>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h3>2. Select Menu Items</h3>
                        <div class="menu-grid">
                            <?php foreach($food_items as $item): ?>
                                <div class="menu-item" onclick="toggleItem(<?= $item['id'] ?>)">
                                    <div class="menu-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                    <div class="menu-item-price">₱<?= number_format($item['price'], 2) ?></div>
                                    <div class="menu-item-stock">Stock: <?= $item['stock'] ?></div>
                                    <div class="quantity-control">
                                        <button type="button" onclick="event.stopPropagation(); changeQuantity(<?= $item['id'] ?>, -1)">-</button>
                                        <input type="number" id="qty_<?= $item['id'] ?>" name="items[<?= $item['id'] ?>][quantity]" 
                                               value="0" min="0" max="<?= $item['stock'] ?>" readonly>
                                        <button type="button" onclick="event.stopPropagation(); changeQuantity(<?= $item['id'] ?>, 1)">+</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <button type="submit" name="create_order" class="btn btn-primary">Create Order</button>
                </form>
                
            <?php elseif ($tab == 'active_orders'): ?>
                <div class="content-header">
                    <h2>📦 Active Orders</h2>
                    <p>Manage current orders</p>
                </div>
                
                <div class="orders-grid">
                    <?php if (empty($active_orders)): ?>
                        <div class="card">
                            <p>No active orders found.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php foreach($active_orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div>
                                    <div class="order-id">Order #<?= $order['id'] ?></div>
                                    <div class="order-type <?= $order['order_type'] ?>">
                                        <?= $order['order_type'] == 'dine_in' ? '🍽️ Dine In' : '🥡 Take Out' ?>
                                        <?php if ($order['order_type'] == 'dine_in' && $order['table_number']): ?>
                                            (Table <?= $order['table_number'] ?>)
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="order-status status-<?= $order['status'] ?>">
                                    <?= ucfirst($order['status']) ?>
                                </div>
                            </div>
                            
                            <div class="order-customer">
                                <strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?>
                            </div>
                            
                            <div class="order-total">
                                <strong>Total:</strong> ₱<?= number_format($order['total'], 2) ?>
                            </div>
                            
                            <div class="order-actions">
                                <?php if ($order['status'] == 'pending'): ?>
                                    <a href="?tab=active_orders&update_status=1&order_id=<?= $order['id'] ?>&status=preparing" 
                                       class="btn btn-warning btn-small">Start Preparing</a>
                                <?php elseif ($order['status'] == 'preparing'): ?>
                                    <a href="?tab=active_orders&update_status=1&order_id=<?= $order['id'] ?>&status=ready" 
                                       class="btn btn-success btn-small">Mark Ready</a>
                                <?php elseif ($order['status'] == 'ready'): ?>
                                    <a href="?tab=active_orders&update_status=1&order_id=<?= $order['id'] ?>&status=completed" 
                                       class="btn btn-primary btn-small">Complete Order</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php elseif ($tab == 'menu'): ?>
                <div class="content-header">
                    <h2>📋 Menu Items</h2>
                    <p>Available food items</p>
                </div>
                
                <div class="menu-grid">
                    <?php foreach($food_items as $item): ?>
                        <div class="card">
                            <div class="menu-item-name"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="menu-item-category" style="color: #666; font-size: 0.9rem;">
                                <?= ucfirst($item['category']) ?>
                            </div>
                            <div class="menu-item-price" style="color: #d87b3e; font-weight: 600; margin: 0.5rem 0;">
                                ₱<?= number_format($item['price'], 2) ?>
                            </div>
                            <div class="menu-item-stock" style="color: <?= $item['stock'] > 5 ? '#28a745' : ($item['stock'] > 0 ? '#ffc107' : '#dc3545') ?>;">
                                Stock: <?= $item['stock'] ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function selectOrderType(type) {
            document.getElementById('order_type').value = type;
            
            // Update button styles
            document.querySelectorAll('.order-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Show/hide table selection
            const dineInSection = document.getElementById('dine_in_section');
            const tableSelect = document.getElementById('table_id');
            
            if (type === 'dine_in') {
                dineInSection.style.display = 'block';
                tableSelect.required = true;
            } else {
                dineInSection.style.display = 'none';
                tableSelect.required = false;
            }
        }
        
        function toggleItem(itemId) {
            const item = event.target.closest('.menu-item');
            const quantityInput = document.getElementById('qty_' + itemId);
            
            if (quantityInput.value == 0) {
                quantityInput.value = 1;
                item.classList.add('selected');
            } else {
                quantityInput.value = 0;
                item.classList.remove('selected');
            }
        }
        
        function changeQuantity(itemId, change) {
            const quantityInput = document.getElementById('qty_' + itemId);
            const item = quantityInput.closest('.menu-item');
            let newQuantity = parseInt(quantityInput.value) + change;
            
            if (newQuantity >= 0 && newQuantity <= parseInt(quantityInput.max)) {
                quantityInput.value = newQuantity;
                
                if (newQuantity > 0) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial order type
            selectOrderType('<?= $_POST['order_type'] ?? 'dine_in' ?>');
        });
    </script>
</body>
</html>