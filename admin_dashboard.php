<?php
// admin_dashboard.php - UPDATED VERSION
session_start();

// Debug: Check what's in session
error_log("=== ADMIN DASHBOARD SESSION DEBUG ===");
error_log("Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in
if (empty($_SESSION['user'])) {
    error_log("No user session found, redirecting to index.php");
    header('Location: index.php');
    exit;
}

// Check if user is admin
if ($_SESSION['user']['role'] !== 'admin') {
    error_log("User is not admin. Role: " . $_SESSION['user']['role']);
    header('Location: index.php');
    exit;
}

require_once 'db.php';

// DEBUG: Check database connection and orders
error_log("=== ADMIN DASHBOARD DATABASE DEBUG ===");
$total_orders = $conn->query("SELECT COUNT(*) as count FROM customer_order")->fetch_assoc();
$recent_orders_count = $conn->query("SELECT * FROM customer_order ORDER BY order_date DESC LIMIT 10")->num_rows;
error_log("Total orders in database: " . $total_orders['count']);
error_log("Recent orders count: " . $recent_orders_count);

// Get current tab from URL, default to 'overview'
$tab = $_GET['tab'] ?? 'overview';

// --- REAL-TIME DATA ---
$current_month = date('Y-m');

// Monthly Sales
$monthly_sales_res = $conn->query("
    SELECT SUM(total) as monthly_sales, COUNT(id) as monthly_orders 
    FROM customer_order 
    WHERE DATE_FORMAT(order_date, '%Y-%m') = '{$current_month}'
");
$monthly_data = $monthly_sales_res->fetch_assoc();

// Monthly Orders by Type
$monthly_order_types = [];
$order_types_res = $conn->query("
    SELECT order_type, COUNT(id) as count, SUM(total) as revenue 
    FROM customer_order 
    WHERE DATE_FORMAT(order_date, '%Y-%m') = '{$current_month}'
    GROUP BY order_type
");
while($ot = $order_types_res->fetch_assoc()) $monthly_order_types[] = $ot;

// Monthly Top Selling Items
$monthly_top_items = [];
$top_items_res = $conn->query("
    SELECT f.name, SUM(oi.quantity) as total_sold, SUM(oi.quantity * oi.price) as revenue
    FROM customer_order_items oi 
    JOIN food_items f ON oi.food_id = f.id 
    JOIN customer_order o ON oi.order_id = o.id
    WHERE DATE_FORMAT(o.order_date, '%Y-%m') = '{$current_month}'
    GROUP BY f.id, f.name
    ORDER BY total_sold DESC 
    LIMIT 5
");
while($ti = $top_items_res->fetch_assoc()) $monthly_top_items[] = $ti;

// Monthly Sales Trend (Last 6 months)
$monthly_trend = [];
$trend_res = $conn->query("
    SELECT 
        DATE_FORMAT(order_date, '%Y-%m') as month,
        DATE_FORMAT(order_date, '%b %Y') as month_name,
        SUM(total) as monthly_revenue,
        COUNT(id) as order_count
    FROM customer_order 
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(order_date, '%Y-%m')
    ORDER BY month ASC
");
while($mt = $trend_res->fetch_assoc()) $monthly_trend[] = $mt;

// Monthly Customer Stats
$monthly_customers = $conn->query("
    SELECT COUNT(DISTINCT customer_name) as unique_customers 
    FROM customer_order 
    WHERE DATE_FORMAT(order_date, '%Y-%m') = '{$current_month}'
")->fetch_assoc();

// --- SALES REPORT DATA ---
$sales_report = [];
$sales_report_res = $conn->query("
    SELECT 
        DATE_FORMAT(order_date, '%Y-%m-%d') as date,
        COUNT(id) as daily_orders,
        SUM(total) as daily_revenue,
        AVG(total) as avg_order_value
    FROM customer_order 
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE_FORMAT(order_date, '%Y-%m-%d')
    ORDER BY date DESC
");
while($sr = $sales_report_res->fetch_assoc()) $sales_report[] = $sr;

// --- Global Data Fetch ---
$foods = [];
$res = $conn->query("SELECT * FROM food_items ORDER BY id DESC");
while($r = $res->fetch_assoc()) $foods[] = $r;

// Get ALL orders for order management tab
$orders = [];
$order_res = $conn->query("
    SELECT co.*, 
           (SELECT COUNT(*) FROM customer_order_items coi WHERE coi.order_id = co.id) as item_count
    FROM customer_order co 
    ORDER BY order_date DESC
");
while($or = $order_res->fetch_assoc()) $orders[] = $or;

// Get recent orders for dashboard (limit 10)
$recent_orders = [];
$recent_order_res = $conn->query("
    SELECT * FROM customer_order 
    ORDER BY order_date DESC 
    LIMIT 10
");
while($ro = $recent_order_res->fetch_assoc()) $recent_orders[] = $ro;

// --- KPI DATA ---
$kpi_res = $conn->query("SELECT SUM(total) as total_sales, COUNT(id) as total_orders FROM customer_order");
$kpi = $kpi_res->fetch_assoc();

$today = date('Y-m-d');
$today_res = $conn->query("SELECT SUM(total) as today_sales FROM customer_order WHERE DATE(order_date) = '{$today}'");
$today_kpi = $today_res->fetch_assoc();

// Pending orders count
$pending_orders = $conn->query("SELECT COUNT(*) as count FROM customer_order WHERE status = 'pending'")->fetch_assoc();

// --- LOW STOCK ALERTS ---
$low_stock_items = [];
$low_stock_res = $conn->query("SELECT * FROM food_items WHERE stock <= 5 ORDER BY stock ASC");
while($ls = $low_stock_res->fetch_assoc()) $low_stock_items[] = $ls;

// Handle order status updates
if (isset($_GET['update_status'])) {
    $order_id = intval($_GET['order_id']);
    $new_status = $_GET['status'];
    
    $stmt = $conn->prepare("UPDATE customer_order SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    
    if ($stmt->execute()) {
        header('Location: admin_dashboard.php?tab=orders&updated=1');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard - Foodhouse</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .debug-info {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        padding: 10px;
        margin: 10px 0;
        border-radius: 5px;
        font-size: 0.8rem;
        color: #6c757d;
    }
    .status-actions {
        display: flex;
        gap: 5px;
    }
    .btn-status {
        padding: 4px 8px;
        font-size: 0.75rem;
        border: none;
        border-radius: 3px;
        cursor: pointer;
    }
    .btn-preparing { background: #ffc107; color: black; }
    .btn-ready { background: #17a2b8; color: white; }
    .btn-completed { background: #28a745; color: white; }
    .btn-cancelled { background: #dc3545; color: white; }
  </style>
</head>
<body class="admin-body">

<nav>
  <h1><span style="color:#d87b3e;">Admin</span> Dashboard</h1>
  <div class="dashboard-controls">
    <span class="current-month">📅 <?= date('F Y') ?></span>
 
    <a href="auth.php?action=logout" class="btn btn-logout">Logout</a>
  </div>
</nav>

<div class="admin-layout container">
    
    <aside class="sidebar">
        <div class="profile-card">
            <img src="https://placehold.co/80x80/6b5a4b/ffffff?text=ADMIN" alt="Admin Profile" class="profile-img">
            <h4><?=htmlspecialchars($_SESSION['user']['full_name'])?></h4>
            <span class="role-tag">Administrator</span>
        </div>

        <div class="nav-group">
            <h3>MAIN MENU</h3>
            <a href="?tab=overview" class="sidebar-link <?=($tab == 'overview' ? 'active' : '')?>">
                <i class="icon">📊</i> Dashboard Overview
            </a>
            <a href="?tab=sales_report" class="sidebar-link <?=($tab == 'sales_report' ? 'active' : '')?>">
                <i class="icon">📋</i> Sales Report
            </a>
            <a href="?tab=orders" class="sidebar-link <?=($tab == 'orders' ? 'active' : '')?>">
                <i class="icon">📦</i> Order Management
                <?php if ($pending_orders['count'] > 0): ?>
                    <span class="pending-badge"><?= $pending_orders['count'] ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=add_item" class="sidebar-link <?=($tab == 'add_item' ? 'active' : '')?>">
                <i class="icon">➕</i> Add Food Item
            </a>
        </div>
    </aside>

    <main class="content">

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">
            ✅ Order status updated successfully!
        </div>
    <?php endif; ?>

    <!-- Debug Information -->
    <div class="debug-info">
        <strong>System Status:</strong> 
        Total Orders: <?= $total_orders['count'] ?> | 
        Pending: <?= $pending_orders['count'] ?> | 
        Database: <?= $conn->host_info ?>
    </div>

    <?php if ($tab == 'overview'): ?>
        <div class="dashboard-header">
            <h2>📊 Dashboard Overview</h2>
            <p class="content-subtitle">Real-time data for <?= date('F Y') ?></p>
        </div>
        
        <!-- KPI Cards -->
        <div class="dashboard-grid kpi-row">
            <div class="stat-card monthly-card">
                <div class="stat-icon-container bg-primary">
                    <div style="font-size: 1.5rem;">💰</div>
                </div>
                <div class="stat-info">
                    <p class="stat-label">Total Revenue</p>
                    <h4 class="stat-value">₱<?=number_format($kpi['total_sales'] ?? 0, 2)?></h4>
                    <div class="growth-indicator positive">All Time</div>
                </div>
            </div>
            
            <div class="stat-card monthly-card">
                <div class="stat-icon-container bg-success">
                    <div style="font-size: 1.5rem;">📦</div>
                </div>
                <div class="stat-info">
                    <p class="stat-label">Total Orders</p>
                    <h4 class="stat-value"><?=number_format($kpi['total_orders'] ?? 0)?></h4>
                    <div class="growth-indicator positive">All Time</div>
                </div>
            </div>
            
            <div class="stat-card monthly-card">
                <div class="stat-icon-container bg-info">
                    <div style="font-size: 1.5rem;">💰</div>
                </div>
                <div class="stat-info">
                    <p class="stat-label">Monthly Revenue</p>
                    <h4 class="stat-value">₱<?=number_format($monthly_data['monthly_sales'] ?? 0, 2)?></h4>
                    <div class="growth-indicator positive">This Month</div>
                </div>
            </div>
            
            <div class="stat-card monthly-card">
                <div class="stat-icon-container bg-warning">
                    <div style="font-size: 1.5rem;">⏰</div>
                </div>
                <div class="stat-info">
                    <p class="stat-label">Pending Orders</p>
                    <h4 class="stat-value"><?=number_format($pending_orders['count'] ?? 0)?></h4>
                    <div class="growth-indicator <?= ($pending_orders['count'] ?? 0) > 5 ? 'negative' : 'positive' ?>">
                        <?= ($pending_orders['count'] ?? 0) > 5 ? '⚠️ Needs Attention' : '✅ All Good' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Alerts -->
        <?php if (!empty($low_stock_items)): ?>
        <div class="alert alert-warning">
            <h4>⚠️ Low Stock Alert</h4>
            <p>The following items are running low on stock:</p>
            <div class="low-stock-grid">
                <?php foreach($low_stock_items as $item): ?>
                <div class="low-stock-item">
                    <span class="item-name"><?=htmlspecialchars($item['name'])?></span>
                    <span class="stock-count <?= $item['stock'] == 0 ? 'out-of-stock' : 'low-stock' ?>">
                        <?= $item['stock'] == 0 ? 'Out of Stock' : $item['stock'] . ' left' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts Row -->
        <div class="dashboard-grid content-row">
            <div class="card">
                <h3>📈 Sales Trend (Last 6 Months)</h3>
                <div class="chart-container">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>
            
            <div class="card">
                <h3>🍽️ Order Types</h3>
                <div class="chart-container">
                    <canvas id="orderTypesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Items & Recent Activity -->
        <div class="dashboard-grid content-row">
            <div class="card">
                <h3>🔥 Top Selling Items</h3>
                <div class="top-items-list">
                    <?php if (empty($monthly_top_items)): ?>
                        <p class="text-muted">No sales data for this month.</p>
                    <?php endif; ?>
                    <?php foreach($monthly_top_items as $index => $item): ?>
                    <div class="top-item">
                        <span class="item-rank">#<?=$index + 1?></span>
                        <span class="item-name"><?=htmlspecialchars($item['name'])?></span>
                        <span class="item-sales"><?=number_format($item['total_sold'])?> sold</span>
                        <span class="item-revenue">₱<?=number_format($item['revenue'], 2)?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="card">
                <h3>🕒 Recent Orders</h3>
                <div class="recent-orders">
                    <?php if (empty($recent_orders)): ?>
                        <p class="text-muted">No recent orders.</p>
                    <?php endif; ?>
                    <?php foreach($recent_orders as $order): ?>
                    <div class="order-item">
                        <div class="order-header">
                            <span class="order-id">#<?=$order['id']?></span>
                            <span class="order-amount">₱<?=number_format($order['total'], 2)?></span>
                        </div>
                        <div class="order-details">
                            <span class="customer"><?=htmlspecialchars($order['customer_name'])?></span>
                            <span class="order-type <?=$order['order_type']?>"><?=ucfirst($order['order_type'])?></span>
                        </div>
                        <div class="order-footer">
                            <span class="order-time"><?=date('M j, g:i A', strtotime($order['order_date']))?></span>
                            <span class="order-status status-<?=$order['status']?>"><?=ucfirst($order['status'])?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    <?php elseif ($tab == 'sales_report'): ?>
        <div class="dashboard-header">
            <h2>📋 Sales Report</h2>
            <p class="content-subtitle">Daily sales performance for the last 30 days</p>
        </div>

        <div class="card full-width">
            <div class="card-header">
                <h3>Daily Sales Summary</h3>
                <span class="report-period">Last 30 Days</span>
            </div>
            <div class="table-responsive">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                            <th>Average Order Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sales_report)): ?>
                            <tr><td colspan="5" class="text-muted">No sales data available.</td></tr>
                        <?php endif; ?>
                        <?php foreach($sales_report as $sale): ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($sale['date'])) ?></td>
                            <td><?= number_format($sale['daily_orders']) ?></td>
                            <td class="revenue-cell">₱<?= number_format($sale['daily_revenue'], 2) ?></td>
                            <td>₱<?= number_format($sale['avg_order_value'], 2) ?></td>
                            <td>
                                <?php if ($sale['daily_revenue'] > 0): ?>
                                    <span class="trend-up">✅ Active</span>
                                <?php else: ?>
                                    <span class="trend-down">❌ No Sales</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab == 'orders'): ?>
        <div class="dashboard-header">
            <h2>📦 Order Management</h2>
            <p class="content-subtitle">Manage and track all customer orders</p>
        </div>

        <div class="card full-width">
            <div class="card-header">
                <h3>All Orders (<?= count($orders) ?> total)</h3>
                <div class="card-actions">
                    <button class="btn btn-primary" onclick="refreshOrders()">🔄 Refresh</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="8" class="text-muted">No orders found.</td></tr>
                        <?php endif; ?>
                        <?php foreach($orders as $order): ?>
                        <tr>
                            <td>#<?=$order['id']?></td>
                            <td><?=htmlspecialchars($order['customer_name'])?></td>
                            <td>
                                <span class="order-type-badge <?=$order['order_type']?>">
                                    <?=ucfirst($order['order_type'])?>
                                </span>
                            </td>
                            <td><?= $order['item_count'] ?> items</td>
                            <td>₱<?=number_format($order['total'], 2)?></td>
                            <td><?=date('M j, g:i A', strtotime($order['order_date']))?></td>
                            <td>
                                <span class="status-badge status-<?=$order['status']?>">
                                    <?=ucfirst($order['status'])?>
                                </span>
                            </td>
                            <td>
                                <div class="status-actions">
                                    <?php if ($order['status'] == 'pending'): ?>
                                        <a href="?tab=orders&update_status=1&order_id=<?=$order['id']?>&status=preparing" 
                                           class="btn-status btn-preparing" title="Start Preparing">👨‍🍳</a>
                                    <?php elseif ($order['status'] == 'preparing'): ?>
                                        <a href="?tab=orders&update_status=1&order_id=<?=$order['id']?>&status=ready" 
                                           class="btn-status btn-ready" title="Mark Ready">✅</a>
                                    <?php elseif ($order['status'] == 'ready'): ?>
                                        <a href="?tab=orders&update_status=1&order_id=<?=$order['id']?>&status=completed" 
                                           class="btn-status btn-completed" title="Complete">🎉</a>
                                    <?php endif; ?>
                                    <?php if ($order['status'] != 'completed' && $order['status'] != 'cancelled'): ?>
                                        <a href="?tab=orders&update_status=1&order_id=<?=$order['id']?>&status=cancelled" 
                                           class="btn-status btn-cancelled" title="Cancel">❌</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab == 'add_item'): ?>
        <div class="dashboard-header">
            <h2>➕ Add Food Item</h2>
            <p class="content-subtitle">Add new items to your menu</p>
        </div>

        <div class="card">
            <form id="addItemForm" method="POST" action="api.php?action=add_food_item">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Item Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" required>
                            <option value="">Select Category</option>
                            <option value="Main Course">Main Course</option>
                            <option value="Side Dish">Side Dish</option>
                            <option value="Beverage">Beverage</option>
                            <option value="Dessert">Dessert</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="price">Price *</label>
                        <input type="number" id="price" name="price" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="stock">Initial Stock *</label>
                        <input type="number" id="stock" name="stock" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Item</button>
                    <button type="reset" class="btn btn-secondary">Reset</button>
                </div>
            </form>
        </div>

    <?php else: ?>
        <div class="dashboard-header">
            <h2>Welcome to Admin Dashboard</h2>
            <p class="content-subtitle">Select a section from the menu to get started</p>
        </div>

    <?php endif; ?>
    
    </main>
</div>

<script>
// Initialize Charts
document.addEventListener('DOMContentLoaded', function() {
    // Sales Trend Chart
    const salesTrendCtx = document.getElementById('salesTrendChart')?.getContext('2d');
    if (salesTrendCtx) {
        new Chart(salesTrendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($monthly_trend, 'month_name')) ?>,
                datasets: [{
                    label: 'Monthly Revenue',
                    data: <?= json_encode(array_column($monthly_trend, 'monthly_revenue')) ?>,
                    borderColor: '#d87b3e',
                    backgroundColor: 'rgba(216, 123, 62, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // Order Types Chart
    const orderTypesCtx = document.getElementById('orderTypesChart')?.getContext('2d');
    if (orderTypesCtx) {
        new Chart(orderTypesCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($monthly_order_types, 'order_type')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($monthly_order_types, 'count')) ?>,
                    backgroundColor: ['#d87b3e', '#28a745', '#007bff', '#6c757d']
                }]
            },
            options: { responsive: true }
        });
    }
});

function refreshOrders() {
    location.reload();
}

// Auto-refresh orders every 30 seconds
setInterval(() => {
    if (window.location.search.includes('tab=orders') || window.location.search.includes('tab=overview')) {
        refreshOrders();
    }
}, 30000);
</script>

</body>
</html>