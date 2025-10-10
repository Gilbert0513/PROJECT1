 
<?php
// admin_dashboard.php (Enhanced with new features)
session_start();
require_once 'db.php';
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php'); exit;
}

// Get current tab from URL, default to 'overview'
$tab = $_GET['tab'] ?? 'overview';

// --- Global Data Fetch ---
$foods = [];
$res = $conn->query("SELECT * FROM food_items ORDER BY id DESC");
while($r = $res->fetch_assoc()) $foods[] = $r;

$orders = [];
$order_res = $conn->query("SELECT * FROM orders ORDER BY order_date DESC LIMIT 10");
while($or = $order_res->fetch_assoc()) $orders[] = $or;

// --- KPI DASHBOARD LOGIC (Overview Tab) ---
$kpi_res = $conn->query("SELECT SUM(total) as total_sales, COUNT(id) as total_orders FROM orders");
$kpi = $kpi_res->fetch_assoc();

$today = date('Y-m-d');
$today_res = $conn->query("SELECT SUM(total) as today_sales FROM orders WHERE DATE(order_date) = '{$today}'");
$today_kpi = $today_res->fetch_assoc();

// NEW: Pending orders count
$pending_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")->fetch_assoc();

// --- NEW: LOW STOCK ALERTS ---
$low_stock_items = [];
$low_stock_res = $conn->query("SELECT * FROM food_items WHERE stock <= 5 ORDER BY stock ASC");
while($ls = $low_stock_res->fetch_assoc()) $low_stock_items[] = $ls;

// --- NEW: TOP SELLING ITEMS ---
$top_selling = [];
$top_selling_res = $conn->query("
    SELECT f.name, SUM(oi.quantity) as total_sold, f.price
    FROM order_items oi 
    JOIN food_items f ON oi.food_id = f.id 
    GROUP BY f.id 
    ORDER BY total_sold DESC 
    LIMIT 5
");
while($ts = $top_selling_res->fetch_assoc()) $top_selling[] = $ts;

// --- NEW: DAILY SALES TREND (Last 7 days) ---
$daily_sales = [];
$daily_sales_res = $conn->query("
    SELECT DATE(order_date) as date, SUM(total) as daily_total, COUNT(id) as order_count
    FROM orders 
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(order_date) 
    ORDER BY date ASC
");
while($ds = $daily_sales_res->fetch_assoc()) $daily_sales[] = $ds;

// --- NEW: SALES BY HOUR (For today) ---
$hourly_sales = [];
$hourly_res = $conn->query("
    SELECT HOUR(order_date) as hour, SUM(total) as hourly_total, COUNT(id) as order_count
    FROM orders 
    WHERE DATE(order_date) = CURDATE()
    GROUP BY HOUR(order_date)
    ORDER BY hour ASC
");
while($hs = $hourly_res->fetch_assoc()) $hourly_sales[] = $hs;

// --- DASHBOARD 1: INVENTORY VELOCITY (Velocity Tab) ---
$inventory_velocity = [];
$velocity_res = $conn->query("
    SELECT 
        f.name, 
        f.stock AS current_stock,
        SUM(oi.quantity) as total_sold,
        (SUM(oi.quantity) / f.stock) AS velocity_score
    FROM food_items f
    LEFT JOIN order_items oi ON f.id = oi.food_id
    GROUP BY f.id
    ORDER BY velocity_score DESC
    LIMIT 10
");
while($iv = $velocity_res->fetch_assoc()) $inventory_velocity[] = $iv;

// --- DASHBOARD 2: REVENUE BY ORDER TYPE (Revenue Tab) ---
$revenue_by_type = [];
$revenue_res = $conn->query("
    SELECT 
        order_type, 
        COUNT(id) as count, 
        SUM(total) as total_revenue,
        AVG(total) as average_order_value
    FROM orders
    GROUP BY order_type
");
while($rr = $revenue_res->fetch_assoc()) $revenue_by_type[] = $rr;

// --- NEW: CATEGORY PERFORMANCE (For bar chart) ---
$category_performance = [];
$category_res = $conn->query("
    SELECT 
        f.name as item_name,
        SUM(oi.quantity) as total_sold,
        SUM(oi.quantity * oi.price) as total_revenue
    FROM order_items oi
    JOIN food_items f ON oi.food_id = f.id
    GROUP BY f.id
    ORDER BY total_sold DESC
    LIMIT 8
");
while($cp = $category_res->fetch_assoc()) $category_performance[] = $cp;

// NEW: Order Management Data
$pending_orders_list = [];
$pending_res = $conn->query("SELECT * FROM orders WHERE status = 'pending' ORDER BY order_date DESC");
while($po = $pending_res->fetch_assoc()) $pending_orders_list[] = $po;

// NEW: Categories for food items
$categories = [];
$cat_res = $conn->query("SELECT DISTINCT category FROM food_items WHERE category IS NOT NULL AND category != ''");
while($cat = $cat_res->fetch_assoc()) $categories[] = $cat['category'];

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard - Foodhouse</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <!-- Chart.js Library -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-body">

<nav>
  <h1><span style="color:#d87b3e;">Admin</span> Dashboard</h1>
  <div>
    
    <a href="auth.php?action=logout" class="btn btn-logout">Logout</a>
  </div>
</nav>

<div class="admin-layout container">
    
    <!-- --- SIDEBAR NAVIGATION --- -->
    <aside class="sidebar">
        <div class="profile-card">
            <img src="https://placehold.co/80x80/6b5a4b/ffffff?text=ADMIN" alt="Admin Profile" class="profile-img">
            <h4><?=htmlspecialchars($_SESSION['user']['full_name'])?></h4>
            <span class="role-tag">Administrator</span>
        </div>

        <!-- NEW: Quick Actions in Sidebar -->
        <div class="nav-group">
            <h3>QUICK ACTIONS</h3>
            <a href="javascript:void(0)" onclick="exportData('sales')" class="sidebar-link">
                <i class="icon">📤</i> Export Sales
            </a>
            <a href="javascript:void(0)" onclick="exportData('inventory')" class="sidebar-link">
                <i class="icon">📦</i> Export Inventory
            </a>
            <a href="javascript:void(0)" onclick="showBulkUpdate()" class="sidebar-link">
                <i class="icon">🔄</i> Bulk Update
            </a>
        </div>

        <div class="nav-group">
            <h3>MAIN MENU</h3>
            <a href="?tab=overview" class="sidebar-link <?=($tab == 'overview' ? 'active' : '')?>">
                <i class="icon">📊</i> Overview & Inventory
            </a>
            <a href="?tab=velocity" class="sidebar-link <?=($tab == 'velocity' ? 'active' : '')?>">
                <i class="icon">🏃</i> Inventory Velocity
            </a>
            <a href="?tab=revenue" class="sidebar-link <?=($tab == 'revenue' ? 'active' : '')?>">
                <i class="icon">💰</i> Revenue Breakdown
            </a>
            <a href="?tab=analytics" class="sidebar-link <?=($tab == 'analytics' ? 'active' : '')?>">
                <i class="icon">📈</i> Sales Analytics
            </a>
            <a href="?tab=orders" class="sidebar-link <?=($tab == 'orders' ? 'active' : '')?>">
                <i class="icon">📦</i> Order Management
            </a>
            <a href="?tab=add_item" class="sidebar-link <?=($tab == 'add_item' ? 'active' : '')?>">
                <i class="icon">➕</i> Add Food Item
            </a>
        </div>
    </aside>

    <main class="content">

    <?php if ($tab == 'overview'): ?>
        <h2>Dashboard Overview</h2>
        <p class="content-subtitle">Welcome back! Here's a quick look at your business performance.</p>
        
        <!-- Enhanced KPI Cards -->
        <div class="dashboard-grid kpi-row">
            <div class="stat-card">
                <div class="stat-icon-container bg-primary">
                    <img src="https://placehold.co/40x40/d87b3e/ffffff?text=$" alt="Sales Icon">
                </div>
                <div class="stat-info">
                    <p class="stat-label">Total Sales</p>
                    <h4 class="stat-value">₱<?=number_format($kpi['total_sales'] ?? 0, 2)?></h4>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon-container bg-success">
                    <img src="https://placehold.co/40x40/4CAF50/ffffff?text=O" alt="Orders Icon">
                </div>
                <div class="stat-info">
                    <p class="stat-label">Total Orders</p>
                    <h4 class="stat-value"><?=number_format($kpi['total_orders'] ?? 0)?></h4>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-container bg-info">
                    <img src="https://placehold.co/40x40/2196F3/ffffff?text=T" alt="Today's Sales Icon">
                </div>
                <div class="stat-info">
                    <p class="stat-label">Today's Sales (<?=date('M j')?>)</p>
                    <h4 class="stat-value">₱<?=number_format($today_kpi['today_sales'] ?? 0, 2)?></h4>
                </div>
            </div>

            <!-- NEW: Pending Orders KPI -->
            <div class="stat-card">
                <div class="stat-icon-container bg-warning">
                    <img src="https://placehold.co/40x40/FFC107/ffffff?text=P" alt="Pending Icon">
                </div>
                <div class="stat-info">
                    <p class="stat-label">Pending Orders</p>
                    <h4 class="stat-value"><?=number_format($pending_orders['count'] ?? 0)?></h4>
                </div>
            </div>
        </div>

        <!-- NEW: Sales Chart -->
        <div class="dashboard-grid content-row">
            <div class="card">
                <h3>📊 Today's Sales by Hour</h3>
                <div class="chart-container">
                    <canvas id="hourlySalesChart" height="250"></canvas>
                </div>
            </div>

            <div class="card">
                <h3>🔥 Top Selling Items</h3>
                <div class="chart-container">
                    <canvas id="topItemsChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Low Stock Alerts and Inventory -->
        <div class="dashboard-grid content-row">
            <div class="card" style="border-left: 4px solid #dc3545;">
                <h3>⚠️ Low Stock Alerts</h3>
                <?php if (empty($low_stock_items)): ?>
                    <p class="text-success">All items are sufficiently stocked!</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Item</th><th>Current Stock</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach($low_stock_items as $item): ?>
                                <tr>
                                    <td><?=htmlspecialchars($item['name'])?></td>
                                    <td><span class="text-danger"><?=$item['stock']?></span></td>
                                    <td>
                                        <button class="btn-small btn-primary" onclick="restockItem(<?=$item['id']?>)">Restock</button>
                                        <button class="btn-small" onclick="quickRestock(<?=$item['id']?>, 10)">+10</button>
                                        <button class="btn-small" onclick="quickRestock(<?=$item['id']?>, 25)">+25</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Sales Report -->
            <div class="card card-table">
                <h3>Recent Sales Report</h3>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>ID</th><th>Customer</th><th>Total</th><th>Type</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr><td colspan="7" class="text-muted">No recent orders found.</td></tr>
                            <?php endif; ?>
                            <?php foreach($orders as $o): ?>
                            <tr>
                                <td>#<?=$o['id']?></td>
                                <td><?=htmlspecialchars($o['customer_name'])?></td>
                                <td>₱<?=number_format($o['total'] ?? 0, 2)?></td>
                                <td><?=$o['order_type']?></td>
                                <td>
                                    <span class="status-badge status-<?=$o['status'] ?? 'pending'?>">
                                        <?=ucfirst($o['status'] ?? 'pending')?>
                                    </span>
                                </td>
                                <td><?=date('M j, Y H:i', strtotime($o['order_date']))?></td>
                                <td>
                                    <select onchange="updateOrderStatus(<?=$o['id']?>, this.value)" class="status-select">
                                        <option value="pending" <?=($o['status'] ?? 'pending') == 'pending' ? 'selected' : ''?>>Pending</option>
                                        <option value="preparing" <?=($o['status'] ?? 'pending') == 'preparing' ? 'selected' : ''?>>Preparing</option>
                                        <option value="ready" <?=($o['status'] ?? 'pending') == 'ready' ? 'selected' : ''?>>Ready</option>
                                        <option value="completed" <?=($o['status'] ?? 'pending') == 'completed' ? 'selected' : ''?>>Completed</option>
                                        <option value="cancelled" <?=($o['status'] ?? 'pending') == 'cancelled' ? 'selected' : ''?>>Cancelled</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($tab == 'orders'): ?>
        <h2>Order Management</h2>
        <p class="content-subtitle">Manage and track all customer orders.</p>

        <div class="card full-width">
            <h3>📦 Pending Orders</h3>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Order ID</th><th>Customer</th><th>Items</th><th>Total</th><th>Type</th><th>Order Date</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($pending_orders_list)): ?>
                            <tr><td colspan="8" class="text-muted">No pending orders.</td></tr>
                        <?php endif; ?>
                        <?php 
                        foreach($pending_orders_list as $order): 
                            // Get order items
                            $items_res = $conn->query("
                                SELECT f.name, oi.quantity 
                                FROM order_items oi 
                                JOIN food_items f ON oi.food_id = f.id 
                                WHERE oi.order_id = {$order['id']}
                            ");
                            $items = [];
                            while($item = $items_res->fetch_assoc()) $items[] = $item;
                        ?>
                        <tr>
                            <td>#<?=$order['id']?></td>
                            <td><?=htmlspecialchars($order['customer_name'])?></td>
                            <td>
                                <?php foreach($items as $item): ?>
                                    <div><?=$item['name']?> (x<?=$item['quantity']?>)</div>
                                <?php endforeach; ?>
                            </td>
                            <td>₱<?=number_format($order['total'], 2)?></td>
                            <td><?=$order['order_type']?></td>
                            <td><?=date('M j, H:i', strtotime($order['order_date']))?></td>
                            <td>
                                <span class="status-badge status-<?=$order['status']?>">
                                    <?=ucfirst($order['status'])?>
                                </span>
                            </td>
                            <td>
                                <select onchange="updateOrderStatus(<?=$order['id']?>, this.value)" class="status-select">
                                    <option value="pending" <?=$order['status'] == 'pending' ? 'selected' : ''?>>Pending</option>
                                    <option value="preparing" <?=$order['status'] == 'preparing' ? 'selected' : ''?>>Preparing</option>
                                    <option value="ready" <?=$order['status'] == 'ready' ? 'selected' : ''?>>Ready</option>
                                    <option value="completed" <?=$order['status'] == 'completed' ? 'selected' : ''?>>Completed</option>
                                    <option value="cancelled" <?=$order['status'] == 'cancelled' ? 'selected' : ''?>>Cancelled</option>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab == 'velocity'): ?>
        <h2>Inventory Velocity Dashboard</h2>
        <p class="content-subtitle">Identify fast-moving vs. slow-moving items to optimize stocking levels.</p>

        <div class="card full-width">
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Rank</th><th>Item</th><th>Sold Units</th><th>Current Stock</th><th>Velocity Score</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if (empty($inventory_velocity) || $inventory_velocity[0]['name'] === null): ?>
                            <tr><td colspan="6" class="text-muted">No sales data available to calculate velocity.</td></tr>
                        <?php endif; ?>
                        <?php $rank = 1; foreach($inventory_velocity as $iv): ?>
                        <tr>
                            <td>#<?=$rank++?></td>
                            <td><?=htmlspecialchars($iv['name'])?></td>
                            <td><?=number_format($iv['total_sold'] ?? 0)?></td>
                            <td><?=number_format($iv['current_stock'])?></td>
                            <?php
                                $score = $iv['velocity_score'] ?? 0;
                                $score_class = ($score > 2 ? 'text-success' : ($score > 0.5 ? 'text-warning' : 'text-danger'));
                            ?>
                            <td class="<?=$score_class?>" style="font-weight: 600;">
                                <?=number_format($score, 2)?>
                            </td>
                            <td>
                                <?php if ($score > 2): ?>
                                    <span class="text-success">High Demand</span>
                                <?php elseif ($score > 0.5): ?>
                                    <span class="text-warning">Moderate</span>
                                <?php else: ?>
                                    <button class="btn-small" onclick="restockItem(<?=$iv['id'] ?? 0?>)">Restock</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab == 'revenue'): ?>
        <h2>Revenue Breakdown by Order Type</h2>
        <p class="content-subtitle">Analyze how customers prefer to dine (Dine-in vs. Take-out) and the value of those orders.</p>

        <div class="dashboard-grid kpi-row">
            <?php foreach($revenue_by_type as $rb): ?>
            <div class="stat-card stat-card-full">
                <div class="stat-icon-container bg-info">
                    <img src="https://placehold.co/40x40/94A3B8/ffffff?text=Type" alt="Order Type Icon">
                </div>
                <div class="stat-info">
                    <p class="stat-label"><?=$rb['order_type']?> Revenue</p>
                    <h4 class="stat-value">₱<?=number_format($rb['total_revenue'] ?? 0, 2)?></h4>
                    <p class="stat-sub-text">Orders: <?=number_format($rb['count'])?> | AOV: ₱<?=number_format($rb['average_order_value'] ?? 0, 2)?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- NEW: Revenue Chart -->
        <div class="card full-width">
            <h3>💰 Revenue by Order Type</h3>
            <div class="chart-container">
                <canvas id="revenueChart" height="300"></canvas>
            </div>
        </div>

    <?php elseif ($tab == 'analytics'): ?>
        <h2>Sales Analytics</h2>
        <p class="content-subtitle">Track your business performance over time.</p>

        <!-- 7-Day Sales Trend Line Chart -->
        <div class="card full-width">
            <h3>📈 7-Day Sales Trend</h3>
            <div class="chart-container">
                <canvas id="salesTrendChart" height="350"></canvas>
            </div>
        </div>

        <!-- Performance Charts -->
        <div class="dashboard-grid content-row">
            <div class="card">
                <h3>🔥 Item Performance</h3>
                <div class="chart-container">
                    <canvas id="itemPerformanceChart" height="300"></canvas>
                </div>
            </div>

            <div class="card">
                <h3>📦 Stock vs Sales</h3>
                <div class="chart-container">
                    <canvas id="stockSalesChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Daily Sales Table -->
        <div class="card">
            <h3>📋 Daily Sales Details</h3>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Date</th><th>Orders</th><th>Daily Revenue</th><th>Trend</th></tr></thead>
                    <tbody>
                        <?php if (empty($daily_sales)): ?>
                            <tr><td colspan="4" class="text-muted">No sales data for the past 7 days.</td></tr>
                        <?php endif; ?>
                        <?php foreach($daily_sales as $day): ?>
                        <tr>
                            <td><?=date('M j, Y', strtotime($day['date']))?></td>
                            <td><?=$day['order_count']?></td>
                            <td>₱<?=number_format($day['daily_total'], 2)?></td>
                            <td>
                                <?php if ($day['daily_total'] > 0): ?>
                                    <span class="text-success">↑ Active</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab == 'add_item'): ?>
        <h2>Add New Food Item</h2>
        <p class="content-subtitle">Use this form to quickly add new items to the menu and inventory.</p>
        <div class="card" style="max-width: 500px; margin-top: 1.5rem;">
            <div class="form-image-header">
                <img src="https://placehold.co/150x100/F4D03F/6b5a4b?text=NEW+FOOD" alt="Add Food Item Image">
                <h4>Item Details</h4>
            </div>
            <form id="addFoodForm">
                <label for="food_name">Item Name</label>
                <input id="food_name" placeholder="E.g., BBQ Skewers" required>
                
                <label for="food_desc">Description</label>
                <input id="food_desc" placeholder="Brief description (optional)">

                <label for="food_category">Category</label>
                <select id="food_category">
                    <option value="Uncategorized">Uncategorized</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?=htmlspecialchars($cat)?>"><?=htmlspecialchars($cat)?></option>
                    <?php endforeach; ?>
                </select>

                <label for="food_price">Price (₱)</label>
                <input id="food_price" type="number" step="0.01" placeholder="0.00" required>
                
                <label for="food_stock">Initial Stock</label>
                <input id="food_stock" type="number" placeholder="Quantity" required>
                
                <button type="submit" class="btn btn-primary">Save New Item</button>
            </form>
        </div>

    <?php endif; ?>
    
    </main>
</div>

<!-- Bulk Update Modal -->
<div id="bulkUpdateModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Bulk Stock Update</h2>
      <span class="close" onclick="closeBulkModal()">&times;</span>
    </div>
    <div class="modal-body">
      <div id="bulkUpdateContent">
        <p>Select items to update stock levels:</p>
        <div class="bulk-items-list" style="max-height: 400px; overflow-y: auto;">
          <?php foreach($foods as $item): ?>
            <div class="bulk-item" style="display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee;">
              <span><?=htmlspecialchars($item['name'])?></span>
              <input type="number" id="stock_<?=$item['id']?>" value="<?=$item['stock']?>" min="0" style="width: 80px; padding: 5px;">
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button onclick="applyBulkUpdate()" class="btn-primary">Apply Changes</button>
      <button onclick="closeBulkModal()" class="btn-secondary">Cancel</button>
    </div>
  </div>
</div>

<script src="js/main.js"></script>
<script>
// Enhanced Admin JavaScript

// Real-time notifications for admin
async function fetchNotifications() {
    try {
        const res = await fetch('api.php?action=get_notifications');
        const data = await res.json();
        
        if (data.success) {
            updateNotificationBadge(data.notifications.length);
            renderNotifications(data.notifications);
        }
    } catch (error) {
        console.error('Error fetching notifications:', error);
    }
}

function updateNotificationBadge(count) {
    let badge = document.querySelector('.notification-count');
    if (!badge && count > 0) {
        badge = document.createElement('span');
        badge.className = 'notification-count';
        document.querySelector('.notification-badge').appendChild(badge);
    }
    
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'block' : 'none';
    }
}

function renderNotifications(notifications) {
    const panel = document.querySelector('.notification-panel');
    if (!panel) return;
    
    if (notifications.length === 0) {
        panel.innerHTML = '<div class="notification-item">No new notifications</div>';
        return;
    }
    
    panel.innerHTML = notifications.map(notif => `
        <div class="notification-item ${notif.type}">
            <strong>${notif.type.toUpperCase()}:</strong> ${notif.message}
            ${notif.link ? `<br><a href="${notif.link}" style="font-size: 0.8rem; color: #007bff;">View</a>` : ''}
        </div>
    `).join('');
}

function toggleNotifications() {
    const panel = document.querySelector('.notification-panel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

// Order status management
async function updateOrderStatus(orderId, status) {
    try {
        const res = await postData('api.php?action=update_order_status', {
            order_id: orderId,
            status: status
        });
        
        if (res.success) {
            showQuickNotification('Order status updated successfully!');
            // Reload to reflect changes
            setTimeout(() => location.reload(), 1000);
        } else {
            showQuickNotification('Failed to update order status', 'error');
        }
    } catch (error) {
        console.error('Error updating order status:', error);
        showQuickNotification('Error updating order status', 'error');
    }
}

// Quick restock functions
async function quickRestock(itemId, quantity) {
    if (!confirm(`Restock this item with ${quantity} units?`)) return;
    
    try {
        const res = await postData('api.php?action=restock_item', {
            id: itemId,
            quantity: quantity
        });
        
        if (res.success) {
            showQuickNotification(res.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            showQuickNotification('Restock failed', 'error');
        }
    } catch (error) {
        console.error('Error restocking item:', error);
        showQuickNotification('Error restocking item', 'error');
    }
}

// Bulk operations
function showBulkUpdate() {
    document.getElementById('bulkUpdateModal').style.display = 'block';
}

function closeBulkModal() {
    document.getElementById('bulkUpdateModal').style.display = 'none';
}

async function applyBulkUpdate() {
    const items = [];
    const inputs = document.querySelectorAll('.bulk-items-list input[type="number"]');
    
    inputs.forEach(input => {
        const itemId = input.id.replace('stock_', '');
        const stock = parseInt(input.value);
        items.push({ id: parseInt(itemId), stock: stock });
    });
    
    try {
        const res = await postData('api.php?action=bulk_update_stock', { items: items });
        
        if (res.success) {
            showQuickNotification('Stock updated successfully!');
            closeBulkModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showQuickNotification('Bulk update failed', 'error');
        }
    } catch (error) {
        console.error('Error in bulk update:', error);
        showQuickNotification('Error in bulk update', 'error');
    }
}

// Export functionality
function exportData(type) {
    window.open(`api.php?action=export_data&type=${type}`, '_blank');
}

// Auto-refresh for admin dashboard
setInterval(fetchNotifications, 30000); // Every 30 seconds
setInterval(() => {
    // Auto-refresh the page every 2 minutes if on overview tab
    const currentTab = new URLSearchParams(window.location.search).get('tab') || 'overview';
    if (['overview', 'orders'].includes(currentTab)) {
        location.reload();
    }
}, 120000); // 2 minutes

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    fetchNotifications();
    
    <?php if ($tab == 'overview'): ?>
        initOverviewCharts();
    <?php elseif ($tab == 'revenue'): ?>
        initRevenueChart();
    <?php elseif ($tab == 'analytics'): ?>
        initAnalyticsCharts();
    <?php endif; ?>
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        // Close notification panel when clicking outside
        const notificationPanel = document.querySelector('.notification-panel');
        const notificationBell = document.querySelector('.notification-badge');
        if (notificationPanel && !notificationBell.contains(event.target)) {
            notificationPanel.style.display = 'none';
        }
    });
});

// Your existing chart functions remain the same
function initOverviewCharts() {
    // ... existing chart code ...
}

function initRevenueChart() {
    // ... existing chart code ...
}

function initAnalyticsCharts() {
    // ... existing chart code ...
}

// Quick notification function
function showQuickNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `quick-notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>
</body>
</html>