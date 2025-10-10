<?php
// admin_dashboard.php (Updated with charts)
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
            <a href="?tab=add_item" class="sidebar-link <?=($tab == 'add_item' ? 'active' : '')?>">
                <i class="icon">➕</i> Add Food Item
            </a>
        </div>
    </aside>

    <main class="content">

    <?php if ($tab == 'overview'): ?>
        <h2>Dashboard Overview</h2>
        <p class="content-subtitle">Welcome back! Here's a quick look at your business performance.</p>
        
        <!-- KPI Cards -->
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
                        <thead><tr><th>ID</th><th>Customer</th><th>Total</th><th>Type</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr><td colspan="5" class="text-muted">No recent orders found.</td></tr>
                            <?php endif; ?>
                            <?php foreach($orders as $o): ?>
                            <tr>
                                <td>#<?=$o['id']?></td>
                                <td><?=htmlspecialchars($o['customer_name'])?></td>
                                <td>₱<?=number_format($o['total'] ?? 0, 2)?></td>
                                <td><?=$o['order_type']?></td>
                                <td><?=date('M j, Y H:i', strtotime($o['order_date']))?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($tab == 'velocity'): ?>
        <h2>Inventory Velocity Dashboard</h2>
        <p class="content-subtitle">Identify fast-moving vs. slow-moving items to optimize stocking levels.</p>

        <div class="card full-width">
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Rank</th><th>Item</th><th>Sold Units</th><th>Current Stock</th><th>Velocity Score</th></tr></thead>
                    <tbody>
                        <?php if (empty($inventory_velocity) || $inventory_velocity[0]['name'] === null): ?>
                            <tr><td colspan="5" class="text-muted">No sales data available to calculate velocity.</td></tr>
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

<script src="js/main.js"></script>
<script>
// Initialize Charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($tab == 'overview'): ?>
        initOverviewCharts();
    <?php elseif ($tab == 'revenue'): ?>
        initRevenueChart();
    <?php elseif ($tab == 'analytics'): ?>
        initAnalyticsCharts();
    <?php endif; ?>
});

function initOverviewCharts() {
    // Hourly Sales Chart (Line Chart)
    const hourlyCtx = document.getElementById('hourlySalesChart').getContext('2d');
    const hourlyLabels = [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23];
    const hourlyData = Array(24).fill(0);
    
    <?php foreach($hourly_sales as $hour): ?>
        hourlyData[<?=$hour['hour']?>] = <?=$hour['hourly_total']?>;
    <?php endforeach; ?>

    new Chart(hourlyCtx, {
        type: 'line',
        data: {
            labels: hourlyLabels.map(h => h + ':00'),
            datasets: [{
                label: 'Sales (₱)',
                data: hourlyData,
                borderColor: '#d87b3e',
                backgroundColor: 'rgba(216, 123, 62, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Today\'s Sales by Hour'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Sales (₱)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Hour of Day'
                    }
                }
            }
        }
    });

    // Top Items Chart (Bar Chart)
    const topItemsCtx = document.getElementById('topItemsChart').getContext('2d');
    const topItemsLabels = <?= json_encode(array_column($top_selling, 'name')) ?>;
    const topItemsData = <?= json_encode(array_column($top_selling, 'total_sold')) ?>;

    new Chart(topItemsCtx, {
        type: 'bar',
        data: {
            labels: topItemsLabels,
            datasets: [{
                label: 'Units Sold',
                data: topItemsData,
                backgroundColor: [
                    '#d87b3e', '#4CAF50', '#2196F3', '#FFC107', '#9C27B0'
                ],
                borderColor: [
                    '#b86934', '#45a049', '#1976D2', '#FFA000', '#7B1FA2'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Units Sold'
                    }
                }
            }
        }
    });
}

function initRevenueChart() {
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueLabels = <?= json_encode(array_column($revenue_by_type, 'order_type')) ?>;
    const revenueData = <?= json_encode(array_column($revenue_by_type, 'total_revenue')) ?>;
    const orderCounts = <?= json_encode(array_column($revenue_by_type, 'count')) ?>;

    new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: revenueLabels,
            datasets: [
                {
                    label: 'Total Revenue (₱)',
                    data: revenueData,
                    backgroundColor: '#d87b3e',
                    borderColor: '#b86934',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Order Count',
                    data: orderCounts,
                    backgroundColor: '#2196F3',
                    borderColor: '#1976D2',
                    borderWidth: 1,
                    type: 'line',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Revenue (₱)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Order Count'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
}

function initAnalyticsCharts() {
    // Sales Trend Chart (Line Chart)
    const trendCtx = document.getElementById('salesTrendChart').getContext('2d');
    const trendLabels = <?= json_encode(array_map(function($day) { return date('M j', strtotime($day['date'])); }, $daily_sales)) ?>;
    const trendData = <?= json_encode(array_column($daily_sales, 'daily_total')) ?>;
    const trendOrders = <?= json_encode(array_column($daily_sales, 'order_count')) ?>;

    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [
                {
                    label: 'Daily Revenue (₱)',
                    data: trendData,
                    borderColor: '#d87b3e',
                    backgroundColor: 'rgba(216, 123, 62, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Order Count',
                    data: trendOrders,
                    borderColor: '#2196F3',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Revenue (₱)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Order Count'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });

    // Item Performance Chart
    const performanceCtx = document.getElementById('itemPerformanceChart').getContext('2d');
    const performanceLabels = <?= json_encode(array_column($category_performance, 'item_name')) ?>;
    const performanceData = <?= json_encode(array_column($category_performance, 'total_sold')) ?>;

    new Chart(performanceCtx, {
        type: 'bar',
        data: {
            labels: performanceLabels,
            datasets: [{
                label: 'Units Sold',
                data: performanceData,
                backgroundColor: '#4CAF50',
                borderColor: '#45a049',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Units Sold'
                    }
                }
            }
        }
    });

    // Stock vs Sales Chart
    const stockCtx = document.getElementById('stockSalesChart').getContext('2d');
    const stockLabels = <?= json_encode(array_slice(array_column($foods, 'name'), 0, 6)) ?>;
    const stockData = <?= json_encode(array_slice(array_column($foods, 'stock'), 0, 6)) ?>;
    
    // Get sales data for these items (simplified - you might want to join with order_items)
    const salesData = stockData.map(stock => Math.min(stock * 2, 50)); // Mock data

    new Chart(stockCtx, {
        type: 'bar',
        data: {
            labels: stockLabels,
            datasets: [
                {
                    label: 'Current Stock',
                    data: stockData,
                    backgroundColor: '#2196F3',
                    borderColor: '#1976D2',
                    borderWidth: 1
                },
                {
                    label: 'Sales (Last 30 days)',
                    data: salesData,
                    backgroundColor: '#FFC107',
                    borderColor: '#FFA000',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantity'
                    }
                }
            }
        }
    });
}
</script>
</body>
</html>