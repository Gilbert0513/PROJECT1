<?php
// cashier_dashboard.php - UPDATED VERSION WITH ENHANCED UI
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

// Create tables table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_number INT NOT NULL,
    capacity INT DEFAULT 4,
    status ENUM('available', 'occupied') DEFAULT 'available',
    UNIQUE KEY unique_table (table_number)
)");

// Insert sample tables if empty
$table_check = $conn->query("SELECT COUNT(*) as count FROM tables");
if ($table_check->fetch_assoc()['count'] == 0) {
    for ($i = 1; $i <= 10; $i++) {
        $conn->query("INSERT INTO tables (table_number, capacity) VALUES ($i, 4)");
    }
}

// Get available tables
$tables = [];
$table_res = $conn->query("SELECT * FROM tables WHERE status = 'available' ORDER BY table_number");
while($table = $table_res->fetch_assoc()) $tables[] = $table;

// Get active orders
$active_orders = [];
$orders_res = $conn->query("
    SELECT co.* 
    FROM customer_order co 
    WHERE co.status IN ('pending', 'preparing', 'ready')
    ORDER BY co.order_date DESC
");
while($order = $orders_res->fetch_assoc()) $active_orders[] = $order;

// Get order counts for badges
$pending_count = $conn->query("SELECT COUNT(*) as count FROM customer_order WHERE status = 'pending'")->fetch_assoc()['count'];
$preparing_count = $conn->query("SELECT COUNT(*) as count FROM customer_order WHERE status = 'preparing'")->fetch_assoc()['count'];
$ready_count = $conn->query("SELECT COUNT(*) as count FROM customer_order WHERE status = 'ready'")->fetch_assoc()['count'];

// Handle new order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $order_type = $_POST['order_type'];
    $customer_name = trim($_POST['customer_name']);
    $table_id = $order_type === 'dine_in' ? $_POST['table_id'] : NULL;
    $payment_type = 'Cash';
    $items = $_POST['items'] ?? [];
    
    if (!empty($customer_name) && !empty($items)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Calculate totals
            $subtotal = 0;
            foreach ($items as $food_id => $item) {
                $quantity = intval($item['quantity']);
                if ($quantity > 0) {
                    $food_stmt = $conn->prepare("SELECT price FROM food_items WHERE id = ?");
                    $food_stmt->bind_param("i", $food_id);
                    $food_stmt->execute();
                    $food = $food_stmt->get_result()->fetch_assoc();
                    $subtotal += $food['price'] * $quantity;
                }
            }
            $service_fee = $subtotal * 0.05;
            $total = $subtotal + $service_fee;
            
            // Create order
            $stmt = $conn->prepare("INSERT INTO customer_order (customer_name, order_type, payment_type, subtotal, service_fee, total, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $order_type_formatted = $order_type === 'dine_in' ? 'Dine-in' : 'Take-out';
            $stmt->bind_param("sssddd", $customer_name, $order_type_formatted, $payment_type, $subtotal, $service_fee, $total);
            $stmt->execute();
            $order_id = $conn->insert_id;
            
            // Add order items
            foreach ($items as $food_id => $item) {
                $quantity = intval($item['quantity']);
                if ($quantity > 0) {
                    $food_stmt = $conn->prepare("SELECT price, name FROM food_items WHERE id = ?");
                    $food_stmt->bind_param("i", $food_id);
                    $food_stmt->execute();
                    $food = $food_stmt->get_result()->fetch_assoc();
                    
                    if ($food) {
                        $price = $food['price'];
                        $food_name = $food['name'];
                        
                        $item_stmt = $conn->prepare("INSERT INTO customer_order_items (order_id, food_id, food_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
                        $item_stmt->bind_param("iisid", $order_id, $food_id, $food_name, $quantity, $price);
                        $item_stmt->execute();
                        
                        // Update stock
                        $update_stmt = $conn->prepare("UPDATE food_items SET stock = stock - ? WHERE id = ?");
                        $update_stmt->bind_param("ii", $quantity, $food_id);
                        $update_stmt->execute();
                    }
                }
            }
            
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
    
    $stmt = $conn->prepare("UPDATE customer_order SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    
    if ($stmt->execute()) {
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #374151;
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --info: #0891b2;
            --light: #f8fafc;
            --dark: #1f2937;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.2s ease-in-out;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: var(--secondary);
        }
        
        .cashier-header {
            background: white;
            padding: 1.25rem 2rem;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .header-left h1 {
            color: var(--primary);
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .cashier-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .cashier-name {
            font-weight: 500;
            color: var(--secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--light);
            border-radius: 8px;
            border: 1px solid var(--gray-light);
        }
        
        .role-badge {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .btn-logout {
            background: var(--danger);
            color: white;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .btn-logout:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }
        
        .cashier-layout {
            display: flex;
            min-height: calc(100vh - 80px);
        }
        
        .cashier-sidebar {
            width: 280px;
            background: white;
            padding: 1.5rem 1rem;
            box-shadow: var(--shadow-md);
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--gray-light);
        }
        
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.25rem;
            margin-bottom: 0.5rem;
            text-decoration: none;
            color: var(--secondary);
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-weight: 500;
            border: 1px solid transparent;
        }
        
        .sidebar-link:hover {
            background: #f1f5f9;
            color: var(--primary);
            transform: translateX(4px);
        }
        
        .sidebar-link.active {
            background: var(--primary);
            color: white;
            transform: translateX(4px);
            box-shadow: var(--shadow);
        }
        
        .badge {
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: auto;
        }
        
        .cashier-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }
        
        .content-header {
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
        }
        
        .content-header h2 {
            color: var(--secondary);
            margin-bottom: 0.5rem;
            font-size: 1.75rem;
            font-weight: 700;
        }
        
        .content-subtitle {
            color: var(--gray);
            font-size: 1rem;
        }
        
        .card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-light);
            transition: var(--transition);
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .card h3 {
            color: var(--secondary);
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
            font-weight: 600;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--gray-light);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--secondary);
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid var(--gray-light);
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            background: white;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .order-type-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .order-type-btn {
            padding: 1.25rem 1rem;
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius);
            background: white;
            cursor: pointer;
            text-align: center;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .order-type-btn:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .order-type-btn.active {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .menu-item {
            border: 1.5px solid var(--gray-light);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            cursor: pointer;
            transition: var(--transition);
            background: white;
        }
        
        .menu-item:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .menu-item.selected {
            border-color: var(--primary);
            background: #eff6ff;
            transform: translateY(-2px);
        }
        
        .menu-item-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--secondary);
            font-size: 1.1rem;
        }
        
        .menu-item-price {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }
        
        .menu-item-stock {
            color: var(--gray);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .quantity-control button {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
        }
        
        .quantity-control button:hover {
            background: var(--primary-dark);
        }
        
        .quantity-control input {
            width: 60px;
            text-align: center;
            font-weight: 600;
            border: 1.5px solid var(--gray-light);
            border-radius: 6px;
            padding: 0.5rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            font-size: 1rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-info {
            background: var(--info);
            color: white;
        }
        
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .order-card {
            border: 1.5px solid var(--gray-light);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            background: white;
            transition: var(--transition);
        }
        
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .order-id {
            font-weight: 700;
            color: var(--secondary);
            font-size: 1.1rem;
        }
        
        .order-type {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .order-type.dine_in {
            background: #dbeafe;
            color: var(--primary);
        }
        
        .order-type.take_out {
            background: #f3e8ff;
            color: #7c3aed;
        }
        
        .order-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-preparing { background: #dbeafe; color: #1e40af; }
        .status-ready { background: #d1fae5; color: #065f46; }
        
        .order-customer {
            margin-bottom: 0.75rem;
            font-weight: 500;
        }
        
        .order-total {
            margin-bottom: 1rem;
            font-weight: 600;
            color: var(--secondary);
        }
        
        .order-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border-radius: 6px;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-left-color: var(--success);
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left-color: var(--danger);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-pending .stat-number { color: var(--warning); }
        .stat-preparing .stat-number { color: var(--info); }
        .stat-ready .stat-number { color: var(--success); }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .cashier-layout {
                flex-direction: column;
            }
            
            .cashier-sidebar {
                width: 100%;
            }
            
            .menu-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            
            .orders-grid {
                grid-template-columns: 1fr;
            }
            
            .order-type-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="cashier-header">
        <div class="header-left">
            <h1>💳 Foodhouse - Cashier</h1>
        </div>
        <div class="cashier-info">
            <span class="cashier-name">👤 <?= htmlspecialchars($cashier_user['full_name']) ?></span>
            <span class="role-badge">Cashier</span>
            <a href="cashier_logout.php" class="btn-logout">🚪 Logout</a>
        </div>
    </header>
    
    <div class="cashier-layout">
        <aside class="cashier-sidebar">
            <a href="?tab=new_order" class="sidebar-link <?= $tab == 'new_order' ? 'active' : '' ?>">
                ➕ New Order
            </a>
            <a href="?tab=active_orders" class="sidebar-link <?= $tab == 'active_orders' ? 'active' : '' ?>">
                📦 Active Orders
                <?php if (($pending_count + $preparing_count + $ready_count) > 0): ?>
                    <span class="badge"><?= $pending_count + $preparing_count + $ready_count ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=menu" class="sidebar-link <?= $tab == 'menu' ? 'active' : '' ?>">
                📋 Menu Management
            </a>
        </aside>
        
        <main class="cashier-content">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    ✅ Order created successfully!
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success">
                    ✅ Order status updated successfully!
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($tab == 'new_order'): ?>
                <div class="content-header">
                    <h2>➕ Create New Order</h2>
                    <p class="content-subtitle">Select order type and add menu items</p>
                </div>
                
                <form method="POST" action="">
                    <div class="card">
                        <h3>📋 Order Details</h3>
                        
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
                                <label for="table_id">�️ Select Table *</label>
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
                            <label for="customer_name">👤 Customer Name *</label>
                            <input type="text" name="customer_name" id="customer_name" 
                                   value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>" 
                                   placeholder="Enter customer name" required>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h3>🍽️ Select Menu Items</h3>
                        
                        <div class="menu-grid">
                            <?php foreach($food_items as $item): ?>
                                <div class="menu-item" onclick="toggleItem(<?= $item['id'] ?>)">
                                    <div class="menu-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                    <div class="menu-item-price">₱<?= number_format($item['price'], 2) ?></div>
                                    <div class="menu-item-stock">
                                        📦 Stock: <?= $item['stock'] ?>
                                    </div>
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
                    
                    <button type="submit" name="create_order" class="btn btn-primary">
                        ✅ Create Order
                    </button>
                </form>
                
            <?php elseif ($tab == 'active_orders'): ?>
                <div class="content-header">
                    <h2>📦 Active Orders</h2>
                    <p class="content-subtitle">Manage and track current orders</p>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card stat-pending">
                        <div class="stat-number"><?= $pending_count ?></div>
                        <div class="stat-label">⏳ Pending Orders</div>
                    </div>
                    <div class="stat-card stat-preparing">
                        <div class="stat-number"><?= $preparing_count ?></div>
                        <div class="stat-label">👨‍🍳 Preparing</div>
                    </div>
                    <div class="stat-card stat-ready">
                        <div class="stat-number"><?= $ready_count ?></div>
                        <div class="stat-label">✅ Ready for Pickup</div>
                    </div>
                </div>
                
                <div class="orders-grid">
                    <?php if (empty($active_orders)): ?>
                        <div class="empty-state">
                            <div>📭</div>
                            <h3>No Active Orders</h3>
                            <p>There are no pending orders at the moment.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php foreach($active_orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div>
                                    <div class="order-id">Order #<?= $order['id'] ?></div>
                                    <div class="order-type <?= strtolower(str_replace('-', '_', $order['order_type'])) ?>">
                                        <?= $order['order_type'] == 'Dine-in' ? '🍽️ Dine In' : '🥡 Take Out' ?>
                                    </div>
                                </div>
                                <div class="order-status status-<?= $order['status'] ?>">
                                    <?= $order['status'] == 'pending' ? '⏳' : ($order['status'] == 'preparing' ? '👨‍🍳' : '✅') ?>
                                    <?= ucfirst($order['status']) ?>
                                </div>
                            </div>
                            
                            <div class="order-customer">
                                👤 <strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?>
                            </div>
                            
                            <div class="order-total">
                                💰 <strong>Total:</strong> ₱<?= number_format($order['total'], 2) ?>
                            </div>
                            
                            <div class="order-actions">
                                <?php if ($order['status'] == 'pending'): ?>
                                    <a href="?tab=active_orders&update_status=1&order_id=<?= $order['id'] ?>&status=preparing" 
                                       class="btn btn-warning btn-small">
                                        👨‍🍳 Start Preparing
                                    </a>
                                <?php elseif ($order['status'] == 'preparing'): ?>
                                    <a href="?tab=active_orders&update_status=1&order_id=<?= $order['id'] ?>&status=ready" 
                                       class="btn btn-success btn-small">
                                        ✅ Mark Ready
                                    </a>
                                <?php elseif ($order['status'] == 'ready'): ?>
                                    <a href="?tab=active_orders&update_status=1&order_id=<?= $order['id'] ?>&status=completed" 
                                       class="btn btn-primary btn-small">
                                        🏁 Complete Order
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php elseif ($tab == 'menu'): ?>
                <div class="content-header">
                    <h2>📋 Menu Items</h2>
                    <p class="content-subtitle">Available food items and stock levels</p>
                </div>
                
                <div class="menu-grid">
                    <?php foreach($food_items as $item): ?>
                        <div class="card">
                            <div class="menu-item-name"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="menu-item-stock" style="color: <?= $item['stock'] > 5 ? '#059669' : ($item['stock'] > 0 ? '#d97706' : '#dc2626') ?>; margin: 0.5rem 0;">
                                📦 Stock: <?= $item['stock'] ?>
                                <?php if ($item['stock'] <= 5): ?>
                                    ⚠️
                                <?php endif; ?>
                            </div>
                            <div class="menu-item-price">₱<?= number_format($item['price'], 2) ?></div>
                            <div style="color: #6b7280; font-size: 0.875rem; margin-top: 0.5rem;">
                                🏷️ <?= ucfirst($item['category']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // Fixed JavaScript functions with null checks
        function selectOrderType(type) {
            const orderTypeInput = document.getElementById('order_type');
            if (!orderTypeInput) return;
            
            orderTypeInput.value = type;
            
            document.querySelectorAll('.order-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            const buttons = document.querySelectorAll('.order-type-btn');
            buttons.forEach(btn => {
                if (btn.getAttribute('onclick') && btn.getAttribute('onclick').includes(type)) {
                    btn.classList.add('active');
                }
            });
            
            const dineInSection = document.getElementById('dine_in_section');
            const tableSelect = document.getElementById('table_id');
            
            if (dineInSection && tableSelect) {
                if (type === 'dine_in') {
                    dineInSection.style.display = 'block';
                    tableSelect.required = true;
                } else {
                    dineInSection.style.display = 'none';
                    tableSelect.required = false;
                }
            }
        }
        
        function toggleItem(itemId) {
            const item = document.querySelector(`.menu-item[onclick*="toggleItem(${itemId})"]`);
            const quantityInput = document.getElementById('qty_' + itemId);
            
            if (!item || !quantityInput) return;
            
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
            if (!quantityInput) return;
            
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
        
        document.addEventListener('DOMContentLoaded', function() {
            const currentTab = '<?= $tab ?>';
            
            if (currentTab === 'new_order') {
                selectOrderType('<?= $_POST['order_type'] ?? 'dine_in' ?>');
            }
            
            if (currentTab === 'active_orders') {
                setInterval(() => {
                    window.location.reload();
                }, 30000);
            }
        });
    </script>
</body>
</html>