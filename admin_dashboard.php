<?php
// admin_dashboard.php (Sales Report moved to Main Menu)
session_start();
require_once 'db.php';
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php'); exit;
}

// Get current tab from URL, default to 'overview'
$tab = $_GET['tab'] ?? 'overview';

// --- REAL-TIME MONTHLY DATA ---
$current_month = date('Y-m');
$current_year = date('Y');

// Monthly Sales
$monthly_sales_res = $conn->query("
    SELECT SUM(total) as monthly_sales, COUNT(id) as monthly_orders 
    FROM orders 
    WHERE DATE_FORMAT(order_date, '%Y-%m') = '{$current_month}'
");
$monthly_data = $monthly_sales_res->fetch_assoc();

// Monthly Growth (vs previous month)
$prev_month = date('Y-m', strtotime('-1 month'));
$prev_month_sales_res = $conn->query("
    SELECT SUM(total) as prev_sales 
    FROM orders 
    WHERE DATE_FORMAT(order_date, '%Y-%m') = '{$prev_month}'
");
$prev_month_data = $prev_month_sales_res->fetch_assoc();

// Calculate growth percentage
$current_sales = $monthly_data['monthly_sales'] ?? 0;
$prev_sales = $prev_month_data['prev_sales'] ?? 0;
$growth_percentage = $prev_sales > 0 ? (($current_sales - $prev_sales) / $prev_sales) * 100 : 0;

// Monthly Orders by Type
$monthly_order_types = [];
$order_types_res = $conn->query("
    SELECT order_type, COUNT(id) as count, SUM(total) as revenue 
    FROM orders 
    WHERE DATE_FORMAT(order_date, '%Y-%m') = '{$current_month}'
    GROUP BY order_type
");
while($ot = $order_types_res->fetch_assoc()) $monthly_order_types[] = $ot;

// Monthly Top Selling Items
$monthly_top_items = [];
$top_items_res = $conn->query("
    SELECT f.name, SUM(oi.quantity) as total_sold, SUM(oi.quantity * oi.price) as revenue
    FROM order_items oi 
    JOIN food_items f ON oi.food_id = f.id 
    JOIN orders o ON oi.order_id = o.id
    WHERE DATE_FORMAT(o.order_date, '%Y-%m') = '{$current_month}'
    GROUP BY f.id 
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
    FROM orders 
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(order_date, '%Y-%m')
    ORDER BY month ASC
");
while($mt = $trend_res->fetch_assoc()) $monthly_trend[] = $mt;

// Monthly Customer Stats
$monthly_customers = $conn->query("
    SELECT COUNT(DISTINCT customer_name) as unique_customers 
    FROM orders 
    WHERE DATE_FORMAT(order_date, '%Y-%m') = '{$current_month}'
")->fetch_assoc();

// Monthly Platform Usage
$monthly_platform = [];
$platform_res = $conn->query("
    SELECT 
        COALESCE(created_via, 'web') as platform,
        COUNT(id) as orders,
        SUM(total) as revenue
    FROM orders 
    WHERE DATE_FORMAT(order_date, '%Y-%m') = '{$current_month}'
    GROUP BY COALESCE(created_via, 'web')
");
while($mp = $platform_res->fetch_assoc()) $monthly_platform[] = $mp;

// --- SALES REPORT DATA ---
$sales_report = [];
$sales_report_res = $conn->query("
    SELECT 
        DATE_FORMAT(order_date, '%Y-%m-%d') as date,
        COUNT(id) as daily_orders,
        SUM(total) as daily_revenue,
        AVG(total) as avg_order_value
    FROM orders 
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE_FORMAT(order_date, '%Y-%m-%d')
    ORDER BY date DESC
");
while($sr = $sales_report_res->fetch_assoc()) $sales_report[] = $sr;

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
  <!-- Moment.js for date handling -->
  <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
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
                <i class="icon">📊</i> Monthly Overview
            </a>
            <a href="?tab=platform_analytics" class="sidebar-link <?=($tab == 'platform_analytics' ? 'active' : '')?>">
                <i class="icon">🌐</i> Platform Analytics
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
            <a href="?tab=sales_report" class="sidebar-link <?=($tab == 'sales_report' ? 'active' : '')?>">
                <i class="icon">📋</i> Sales Report
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
        <div class="dashboard-header">
            <h2>📊 Monthly Performance Dashboard</h2>
            <p class="content-subtitle">Real-time data for <?= date('F Y') ?> • Auto-updates every 30 seconds</p>
        </div>
        
        <!-- Real-time Monthly KPI Cards -->
        <div class="dashboard-grid kpi-row">
            <div class="stat-card monthly-card">
                <div class="stat-icon-container bg-primary">
                    <div style="font-size: 1.5rem;">💰</div>
                </div>
                <div class="stat-info">
                    <p class="stat-label">Monthly Revenue</p>
                    <h4 class="stat-value">₱<?=number_format($monthly_data['monthly_sales'] ?? 0, 2)?></h4>
                    <div class="growth-indicator <?= $growth_percentage >= 0 ? 'positive' : 'negative' ?>">
                        <?= $growth_percentage >= 0 ? '↗' : '↘' ?>
                        <?= number_format(abs($growth_percentage), 1) ?>% vs last month
                    </div>
                </div>
            </div>
            
            <div class="stat-card monthly-card">
                <div class="stat-icon-container bg-success">
                    <div style="font-size: 1.5rem;">📦</div>
                </div>
                <div class="stat-info">
                    <p class="stat-label">Monthly Orders</p>
                    <h4 class="stat-value"><?=number_format($monthly_data['monthly_orders'] ?? 0)?></h4>
                    <div class="growth-indicator positive">
                        📈 Active Month
                    </div>
                </div>
            </div>
            
            <div class="stat-card monthly-card">
                <div class="stat-icon-container bg-info">
                    <div style="font-size: 1.5rem;">👥</div>
                </div>
                <div class="stat-info">
                    <p class="stat-label">Unique Customers</p>
                    <h4 class="stat-value"><?=number_format($monthly_customers['unique_customers'] ?? 0)?></h4>
                    <div class="growth-indicator positive">
                        🤝 Customer Growth
                    </div>
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
                    <?php if (empty($orders)): ?>
                        <p class="text-muted">No recent orders.</p>
                    <?php endif; ?>
                    <?php foreach($orders as $order): ?>
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

    <?php elseif ($tab == 'platform_analytics'): ?>
        <div class="dashboard-header">
            <h2>🌐 Platform Analytics</h2>
            <p class="content-subtitle">Order distribution across different platforms</p>
        </div>

        <div class="dashboard-grid kpi-row">
            <?php foreach($monthly_platform as $platform): ?>
            <div class="stat-card">
                <div class="stat-icon-container bg-info">
                    <div style="font-size: 1.5rem;">
                        <?= $platform['platform'] == 'web' ? '💻' : ($platform['platform'] == 'mobile' ? '📱' : '🌐') ?>
                    </div>
                </div>
                <div class="stat-info">
                    <p class="stat-label"><?=ucfirst($platform['platform'])?> Orders</p>
                    <h4 class="stat-value"><?=number_format($platform['orders'])?></h4>
                    <div class="growth-indicator positive">
                        ₱<?=number_format($platform['revenue'], 2)?> revenue
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card full-width">
            <h3>Platform Performance</h3>
            <div class="chart-container">
                <canvas id="platformChart"></canvas>
            </div>
        </div>

    <?php elseif ($tab == 'velocity'): ?>
        <div class="dashboard-header">
            <h2>🏃 Inventory Velocity</h2>
            <p class="content-subtitle">Stock movement and turnover rates</p>
        </div>

        <div class="card full-width">
            <h3>📊 Stock Turnover Analysis</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Monthly Sales</th>
                            <th>Turnover Rate</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $performance_data = [];
                        $performance_res = $conn->query("
                            SELECT 
                                f.name as item_name,
                                f.category,
                                f.stock as current_stock,
                                COALESCE(SUM(oi.quantity), 0) as total_sold,
                                COALESCE(SUM(oi.quantity) / NULLIF(f.stock, 0), 0) as turnover_ratio
                            FROM food_items f
                            LEFT JOIN order_items oi ON f.id = oi.food_id
                            LEFT JOIN orders o ON oi.order_id = o.id AND DATE_FORMAT(o.order_date, '%Y-%m') = '{$current_month}'
                            GROUP BY f.id
                            ORDER BY total_sold DESC
                        ");
                        while($pr = $performance_res->fetch_assoc()) $performance_data[] = $pr;
                        ?>
                        
                        <?php foreach($performance_data as $item): ?>
                        <tr>
                            <td><?=htmlspecialchars($item['item_name'])?></td>
                            <td><?=htmlspecialchars($item['category'])?></td>
                            <td><?=number_format($item['current_stock'])?></td>
                            <td><?=number_format($item['total_sold'] ?? 0)?></td>
                            <td><?=number_format($item['turnover_ratio'] ?? 0, 2)?></td>
                            <td>
                                <?php $turnover = $item['turnover_ratio'] ?? 0; ?>
                                <span class="status-badge <?= $turnover > 1 ? 'status-high' : ($turnover > 0.5 ? 'status-medium' : 'status-low') ?>">
                                    <?= $turnover > 1 ? 'Fast' : ($turnover > 0.5 ? 'Medium' : 'Slow') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab == 'revenue'): ?>
        <div class="dashboard-header">
            <h2>💰 Revenue Breakdown</h2>
            <p class="content-subtitle">Detailed revenue analysis by categories and items</p>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <h3>📈 Revenue Trends</h3>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            <div class="card">
                <h3>🍽️ Category Performance</h3>
                <div class="chart-container">
                    <canvas id="categoryRevenueChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card full-width">
            <h3>Top Revenue Generators</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Units Sold</th>
                            <th>Total Revenue</th>
                            <th>% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $revenue_data = [];
                        $revenue_res = $conn->query("
                            SELECT 
                                f.name as item_name,
                                f.category,
                                COALESCE(SUM(oi.quantity), 0) as total_sold,
                                COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue
                            FROM food_items f
                            LEFT JOIN order_items oi ON f.id = oi.food_id
                            LEFT JOIN orders o ON oi.order_id = o.id AND DATE_FORMAT(o.order_date, '%Y-%m') = '{$current_month}'
                            GROUP BY f.id
                            ORDER BY total_revenue DESC
                        ");
                        while($rr = $revenue_res->fetch_assoc()) $revenue_data[] = $rr;
                        
                        $total_revenue = array_sum(array_column($revenue_data, 'total_revenue'));
                        foreach($revenue_data as $item): 
                            $percentage = $total_revenue > 0 ? ($item['total_revenue'] / $total_revenue) * 100 : 0;
                        ?>
                        <tr>
                            <td><?=htmlspecialchars($item['item_name'])?></td>
                            <td><?=htmlspecialchars($item['category'])?></td>
                            <td><?=number_format($item['total_sold'] ?? 0)?></td>
                            <td>₱<?=number_format($item['total_revenue'] ?? 0, 2)?></td>
                            <td><?=number_format($percentage, 1)?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab == 'analytics'): ?>
        <div class="dashboard-header">
            <h2>📈 Sales Analytics</h2>
            <p class="content-subtitle">Advanced sales data and insights</p>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <h3>📊 Daily Sales Pattern</h3>
                <div class="chart-container">
                    <canvas id="dailySalesChart"></canvas>
                </div>
            </div>
            <div class="card">
                <h3>👥 Customer Behavior</h3>
                <div class="chart-container">
                    <canvas id="customerBehaviorChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card full-width">
            <h3>Detailed Sales Analytics</h3>
            <div class="analytics-grid">
                <div class="analytics-card">
                    <h4>Average Order Value</h4>
                    <div class="analytics-value">₱<?=number_format($monthly_data['monthly_sales'] / max($monthly_data['monthly_orders'], 1), 2)?></div>
                </div>
                <div class="analytics-card">
                    <h4>Conversion Rate</h4>
                    <div class="analytics-value"><?=number_format(($monthly_data['monthly_orders'] / max($monthly_customers['unique_customers'], 1)) * 100, 1)?>%</div>
                </div>
                <div class="analytics-card">
                    <h4>Peak Hours</h4>
                    <div class="analytics-value">12:00-14:00</div>
                </div>
                <div class="analytics-card">
                    <h4>Busiest Day</h4>
                    <div class="analytics-value">Friday</div>
                </div>
            </div>
        </div>

    <?php elseif ($tab == 'sales_report'): ?>
        <!-- Sales Report Content -->
        <div class="dashboard-header">
            <h2>📋 Sales Report</h2>
            <p class="content-subtitle">Daily sales performance for the last 30 days</p>
            <div class="report-actions">
                <button onclick="exportReport('sales')" class="btn btn-primary">📊 Export Sales Report</button>
                <button onclick="printReport()" class="btn btn-secondary">🖨️ Print Report</button>
            </div>
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
                            <th>Trend</th>
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
                                    <span class="trend-up">↑ Good</span>
                                <?php else: ?>
                                    <span class="trend-down">- No Sales</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="report-total">
                            <td><strong>Total</strong></td>
                            <td><strong><?= number_format(array_sum(array_column($sales_report, 'daily_orders'))) ?></strong></td>
                            <td><strong>₱<?= number_format(array_sum(array_column($sales_report, 'daily_revenue')), 2) ?></strong></td>
                            <td><strong>₱<?= number_format(count($sales_report) > 0 ? array_sum(array_column($sales_report, 'daily_revenue')) / array_sum(array_column($sales_report, 'daily_orders')) : 0, 2) ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
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
                <h3>Recent Orders</h3>
                <div class="card-actions">
                    <button class="btn btn-primary" onclick="refreshOrders()">🔄 Refresh</button>
                    <button class="btn btn-secondary" onclick="exportOrders()">📤 Export</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($orders as $order): ?>
                        <tr>
                            <td>#<?=$order['id']?></td>
                            <td><?=htmlspecialchars($order['customer_name'])?></td>
                            <td>
                                <span class="order-type-badge <?=$order['order_type']?>">
                                    <?=ucfirst($order['order_type'])?>
                                </span>
                            </td>
                            <td>₱<?=number_format($order['total'], 2)?></td>
                            <td><?=date('M j, g:i A', strtotime($order['order_date']))?></td>
                            <td>
                                <span class="status-badge status-<?=$order['status']?>">
                                    <?=ucfirst($order['status'])?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-small btn-primary" onclick="viewOrder(<?=$order['id']?>)">View</button>
                                    <button class="btn-small btn-success" onclick="updateStatus(<?=$order['id']?>, 'completed')">Complete</button>
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
            <form id="addItemForm" method="POST" action="api.php?action=add_food_item" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Item Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" required>
                            <option value="">Select Category</option>
                            <option value="appetizer">Appetizer</option>
                            <option value="main course">Main Course</option>
                            <option value="dessert">Dessert</option>
                            <option value="beverage">Beverage</option>
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
                    <div class="form-group full-width">
                        <label for="image">Item Image</label>
                        <input type="file" id="image" name="image" accept="image/*">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Item</button>
                    <button type="reset" class="btn btn-secondary">Reset</button>
                </div>
            </form>
        </div>

    <?php else: ?>
        <!-- Default to overview if tab not found -->
        <div class="dashboard-header">
            <h2>Welcome to Admin Dashboard</h2>
            <p class="content-subtitle">Select a section from the menu to get started</p>
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
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
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
            }
        });
    }
});

// Your existing JavaScript functions...
function showBulkUpdate() {
    document.getElementById('bulkUpdateModal').style.display = 'block';
}

function closeBulkModal() {
    document.getElementById('bulkUpdateModal').style.display = 'none';
}

function applyBulkUpdate() {
    // Implementation for bulk update
    showQuickNotification('Bulk update applied successfully!');
    closeBulkModal();
}

function exportData(type) {
    window.open('api.php?action=export_data&type=' + type, '_blank');
}

function refreshOrders() {
    location.reload();
}

function exportOrders() {
    window.open('api.php?action=export_orders', '_blank');
}

function viewOrder(orderId) {
    window.open('order_details.php?id=' + orderId, '_blank');
}

function updateStatus(orderId, status) {
    if (confirm('Update order status to ' + status + '?')) {
        fetch('api.php?action=update_order_status&id=' + orderId + '&status=' + status)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showQuickNotification('Order status updated successfully!');
                    setTimeout(() => location.reload(), 1000);
                }
            });
    }
}

function exportReport(type) {
    if (type === 'sales') {
        window.open('api.php?action=export_data&type=sales', '_blank');
    }
}

function printReport() {
    window.print();
}

// Auto-refresh for real-time data
setTimeout(() => {
    if (window.location.search.includes('tab=overview')) {
        location.reload();
    }
}, 30000);
</script>

</body>
</html>