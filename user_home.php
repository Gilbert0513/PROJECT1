
<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'customer') {
    header('Location: index.php');
    exit;
}

// Enhanced search and filtering
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$query = "SELECT f.*, 
          (SELECT COUNT(*) FROM user_favorites uf WHERE uf.user_id = ? AND uf.food_id = f.id) as is_favorite 
          FROM food_items f 
          WHERE f.stock > 0";
$params = [$_SESSION['user']['id']];

if (!empty($search)) {
    $query .= " AND (f.name LIKE ? OR f.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($category) && $category !== 'all') {
    $query .= " AND f.category = ?";
    $params[] = $category;
}

$query .= " ORDER BY f.name ASC";

$stmt = $conn->prepare($query);
$types = str_repeat('s', count($params));
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$foods = [];
while ($r = $result->fetch_assoc()) $foods[] = $r;

// Get unique categories for filter
$categories = [];
$cat_res = $conn->query("SELECT DISTINCT category FROM food_items WHERE category IS NOT NULL AND category != ''");
while ($cat = $cat_res->fetch_assoc()) $categories[] = $cat['category'];

// Get user favorites
$favorites = [];
$fav_res = $conn->prepare("SELECT f.* FROM user_favorites uf JOIN food_items f ON uf.food_id = f.id WHERE uf.user_id = ?");
$fav_res->bind_param('i', $_SESSION['user']['id']);
$fav_res->execute();
$fav_result = $fav_res->get_result();
while ($fav = $fav_result->fetch_assoc()) $favorites[] = $fav;

// Handle add to cart form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $food_id = $_POST['food_id'];
    $quantity = intval($_POST['quantity']);
    
    // Get food item details
    $food_result = $conn->query("SELECT * FROM food_items WHERE id = $food_id");
    $food = $food_result->fetch_assoc();
    
    if ($food && $quantity > 0 && $quantity <= $food['stock']) {
        // Initialize cart if not exists
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Add item to cart or update quantity
        if (isset($_SESSION['cart'][$food_id])) {
            $_SESSION['cart'][$food_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$food_id] = [
                'name' => $food['name'],
                'price' => $food['price'],
                'quantity' => $quantity
            ];
        }
        
        // Redirect to avoid form resubmission
        header('Location: user_home.php?added=' . $food_id);
        exit;
    }
}

// Get cart items from session
$cart = $_SESSION['cart'] ?? [];
$cart_count = count($cart);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Foodhouse | Menu</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .success-message {
      background: #d4edda;
      color: #155724;
      padding: 10px;
      border-radius: 4px;
      margin: 10px 0;
      text-align: center;
    }
    .cart-badge {
      background: #e74c3c;
      color: white;
      border-radius: 50%;
      padding: 2px 6px;
      font-size: 0.8rem;
      margin-left: 5px;
    }
    
    /* Updated Menu Grid Styles */
    .menu-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    
    .menu-item {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      padding: 20px;
      transition: transform 0.3s, box-shadow 0.3s;
      position: relative;
      border: 1px solid #eee;
    }
    
    .menu-item:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .menu-item h3 {
      margin: 0 0 10px 0;
      font-size: 1.2rem;
      color: #333;
    }
    
    .menu-item .description {
      color: #666;
      font-size: 0.9rem;
      margin-bottom: 10px;
      line-height: 1.4;
    }
    
    .menu-item .price {
      font-size: 1.3rem;
      font-weight: 600;
      color: #e74c3c;
      margin: 10px 0;
    }
    
    .menu-item .stock {
      font-size: 0.85rem;
      margin-bottom: 15px;
    }
    
    .low-stock {
      color: #e74c3c;
    }
    
    .stock-warning {
      font-weight: 600;
    }
    
    .category {
      color: #666;
      font-size: 0.8rem;
      background: #f0f0f0;
      padding: 2px 8px;
      border-radius: 12px;
      display: inline-block;
      margin-bottom: 10px;
    }
    
    .quantity-controls {
      display: flex;
      align-items: center;
      margin: 15px 0;
      justify-content: center;
    }
    
    .qty-btn {
      background: #f8f9fa;
      border: 1px solid #ddd;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 1.2rem;
      font-weight: bold;
    }
    
    .qty-btn.minus {
      border-radius: 6px 0 0 6px;
    }
    
    .qty-btn.plus {
      border-radius: 0 6px 6px 0;
    }
    
    .item_qty {
      width: 60px;
      height: 36px;
      text-align: center;
      border: 1px solid #ddd;
      border-left: none;
      border-right: none;
      font-size: 1rem;
    }
    
    .btn-add {
      background: #e74c3c;
      color: white;
      border: none;
      padding: 12px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      width: 100%;
      transition: background 0.3s;
    }
    
    .btn-add:hover {
      background: #c0392b;
    }
    
    .favorite-btn {
      position: absolute;
      top: 15px;
      right: 15px;
      background: none;
      border: none;
      font-size: 1.2rem;
      cursor: pointer;
      padding: 5px;
    }
    
    .search-filter-bar {
      display: flex;
      gap: 15px;
      margin-bottom: 20px;
      flex-wrap: wrap;
      align-items: center;
    }
    
    .search-box {
      position: relative;
      flex-grow: 1;
    }
    
    .search-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #666;
    }
    
    #searchInput {
      width: 100%;
      padding: 12px 12px 12px 40px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 1rem;
    }
    
    .filter-select {
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 1rem;
      min-width: 180px;
    }
    
    .quick-action-btn {
      padding: 12px 20px;
      background: #6c757d;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 1rem;
    }
    
    .quick-action-btn:hover {
      background: #5a6268;
    }
    
    .no-items {
      grid-column: 1 / -1;
      text-align: center;
      padding: 40px;
      color: #666;
    }
    
    .fade-in {
      animation: fadeIn 0.5s ease-in-out;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    /* Favorites Section Styling */
    .favorites-section {
      margin-bottom: 2rem;
      padding: 20px;
      background: #fff9e6;
      border-radius: 12px;
      border-left: 4px solid #ffcc00;
    }
    
    .favorites-section h3 {
      color: #d87b3e;
      border-bottom: 2px solid #d87b3e;
      padding-bottom: 0.5rem;
      margin-top: 0;
    }
  </style>
</head>
<body>
<nav>
  <h1>🍖 Foodhouse Grillhouse</h1>
  <div>
    <span>Hi, <?=htmlspecialchars($_SESSION['user']['full_name'])?></span>
    <!-- Cart Icon with Badge -->
    <a href="user_checkout.php" style="text-decoration: none; font-size: 1.2rem; margin-right: 15px;">
      🛒
      <?php if ($cart_count > 0): ?>
        <span class="cart-badge"><?=$cart_count?></span>
      <?php endif; ?>
    </a>
    <a href="feedback.php">⭐ Feedback</a>
    <a href="auth.php?action=logout">Logout</a>
  </div>
</nav>

<main class="container">
  <section class="card">
    <div class="page-header">
      <h2>🍽️ Our Menu</h2>
      <div class="cart-summary">
 
      </div>
    </div>
    
    <?php if (isset($_GET['added'])): ?>
      <div class="success-message">
        ✅ Item added to cart successfully!
      </div>
    <?php endif; ?>
    
    <!-- Search and Filter Bar -->
    <div class="search-filter-bar">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Search menu items..." value="<?=htmlspecialchars($search)?>">
      </div>
      
      <select id="categoryFilter" class="filter-select" onchange="performSearch()">
        <option value="all">All Categories</option>
        <?php foreach($categories as $cat): ?>
          <option value="<?=htmlspecialchars($cat)?>" <?=$category === $cat ? 'selected' : ''?>>
            <?=htmlspecialchars($cat)?>
          </option>
        <?php endforeach; ?>
      </select>
      
      <button onclick="clearFilters()" class="quick-action-btn">Clear Filters</button>
    </div>

    <!-- Favorites Section -->
    <?php if (!empty($favorites)): ?>
    <div class="favorites-section">
      <h3>⭐ Your Favorites</h3>
      <div class="menu-grid">
        <?php foreach($favorites as $f): ?>
          <form method="POST" class="menu-item-form">
            <input type="hidden" name="food_id" value="<?=$f['id']?>">
            <div class="menu-item" data-id="<?=$f['id']?>" data-price="<?=$f['price']?>">
              <button type="button" class="favorite-btn active" data-food-id="<?=$f['id']?>" onclick="toggleFavorite(<?=$f['id']?>)">❤️</button>
              <h3><?=htmlspecialchars($f['name'])?></h3>
              <?php if (!empty($f['description'])): ?>
                <p class="description"><?=htmlspecialchars($f['description'])?></p>
              <?php endif; ?>
              <?php if (!empty($f['category'])): ?>
                <p class="category"><?=htmlspecialchars($f['category'])?></p>
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
                <input class="item_qty" name="quantity" data-id="<?=$f['id']?>" type="number" min="0" max="<?=$f['stock']?>" value="0">
                <button type="button" class="qty-btn plus" onclick="adjustQuantity(<?=$f['id']?>, 1)">+</button>
              </div>
              <button type="submit" name="add_to_cart" class="btn-add">
                Add to Cart
              </button>
            </div>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <p class="subtitle">Select items and quantities to add to your cart</p>
    
    <div class="menu-grid">
      <?php if (empty($foods)): ?>
        <div class="no-items">
          <p>😔 No items available at the moment.</p>
          <p>Please check back later!</p>
        </div>
      <?php else: ?>
        <?php foreach ($foods as $f): ?>
          <form method="POST" class="menu-item-form">
            <input type="hidden" name="food_id" value="<?=$f['id']?>">
            <div class="menu-item" data-id="<?=$f['id']?>" data-price="<?=$f['price']?>">
              <button type="button" class="favorite-btn <?=$f['is_favorite'] ? 'active' : ''?>" data-food-id="<?=$f['id']?>" onclick="toggleFavorite(<?=$f['id']?>)">
                <?=$f['is_favorite'] ? '❤️' : '🤍'?>
              </button>
              <h3><?=htmlspecialchars($f['name'])?></h3>
              <?php if (!empty($f['description'])): ?>
                <p class="description"><?=htmlspecialchars($f['description'])?></p>
              <?php endif; ?>
              <?php if (!empty($f['category'])): ?>
                <p class="category"><?=htmlspecialchars($f['category'])?></p>
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
                <input class="item_qty" name="quantity" data-id="<?=$f['id']?>" type="number" min="0" max="<?=$f['stock']?>" value="0">
                <button type="button" class="qty-btn plus" onclick="adjustQuantity(<?=$f['id']?>, 1)">+</button>
              </div>
              <button type="submit" name="add_to_cart" class="btn-add">
                Add to Cart
              </button>
            </div>
          </form>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</main>

<script>
// Enhanced JavaScript for menu page

// Search with debouncing
let searchTimeout;
function performSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const searchTerm = document.getElementById('searchInput').value;
        const category = document.getElementById('categoryFilter').value;
        
        const url = new URL(window.location);
        url.searchParams.set('search', searchTerm);
        url.searchParams.set('category', category);
        
        window.location.href = url.toString();
    }, 500);
}

function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('categoryFilter').value = 'all';
    performSearch();
}

// Favorites system (optional - remove if not needed)
async function toggleFavorite(foodId) {
    try {
        // This requires api.php to work - remove if causing errors
        console.log('Favorite toggle for:', foodId);
    } catch (error) {
        console.error('Error toggling favorite:', error);
    }
}

// Cart management functions
function adjustQuantity(itemId, change) {
    const input = document.querySelector(`.item_qty[data-id="${itemId}"]`);
    const currentValue = parseInt(input.value) || 0;
    const newValue = currentValue + change;
    const maxStock = parseInt(input.max);
    
    if (newValue >= 0 && newValue <= maxStock) {
        input.value = newValue;
    }
}

// Form validation
document.querySelectorAll('.menu-item-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const quantity = this.querySelector('.item_qty').value;
        if (quantity <= 0) {
            e.preventDefault();
            alert('Please select a quantity greater than 0');
            return false;
        }
        return true;
    });
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add animation to menu items on load
    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
        item.classList.add('fade-in');
    });
    
    // Add event listener for search input
    document.getElementById('searchInput').addEventListener('input', performSearch);
});
</script>
</body>
</html>