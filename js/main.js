// Enhanced main.js with all new features
async function postData(url = '', data = {}) {
  const resp = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  return resp.json();
}

// ======================================
// 1. AUTHENTICATION LOGIC (Original Code)
// ======================================

// LOGIN
const loginForm = document.getElementById('loginForm');
if (loginForm) {
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const res = await postData('auth.php?action=login', { username, password });

    if (res.success) {
      if (res.role === 'admin') location = 'admin_dashboard.php';
      else location = 'user_home.php';
    } else {
      alert(res.message || 'Login failed');
    }
  });
}

// REGISTER
const registerForm = document.getElementById('registerForm');
if (registerForm) {
  registerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const full_name = document.getElementById('reg_fullname').value.trim();
    const username = document.getElementById('reg_username').value.trim();
    const password = document.getElementById('reg_password').value;
    const role = document.querySelector('input[name="role"]:checked').value;

    const res = await postData('auth.php?action=register', {
      full_name, username, password, role
    });

    if (res.success) {
      alert('Registration successful! Please log in.');
      location = 'index.php'; // Redirect to login page
    } else {
      alert(res.message || 'Registration failed');
    }
  });
}

// ======================================
// 2. FEATURE: CUSTOMER ORDERING (Smart Ordering & Preview)
// ======================================

// ORDER PLACEMENT SUBMISSION - Fixed price handling
const orderForm = document.getElementById('orderForm');
if (orderForm) {
  orderForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const customer_name = document.getElementById('customer_name').value;
    const order_type = document.getElementById('order_type').value;
    const payment_type = document.getElementById('payment_type').value;
    const special_instructions = document.getElementById('special_instructions').value;

    const itemInputs = document.querySelectorAll('.item_qty');
    const items = [];
    let hasItems = false;

    // Collect ordered items with proper price conversion
    itemInputs.forEach(input => {
      const qty = parseInt(input.value) || 0;
      if (qty > 0) {
        const itemContainer = input.closest('.menu-item');
        const price = parseFloat(itemContainer.dataset.price) || 0;
        const name = itemContainer.querySelector('h3').textContent;
        
        items.push({
          id: input.dataset.id,
          qty: qty,
          price: price,
          name: name
        });
        hasItems = true;
      }
    });

    if (!hasItems) {
      showQuickNotification('Please select at least one item to order.', 'error');
      return;
    }

    // Disable button and show loading state
    const submitBtn = document.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Placing Order...';
    submitBtn.disabled = true;

    try {
      const res = await postData('api.php?action=place_order', {
        customer_name,
        items,
        order_type,
        payment_type,
        special_instructions
      });

      if (res.success) {
        // Store order data for receipt
        currentOrderData = {
          order_id: res.order_id,
          customer_name: customer_name,
          order_type: order_type,
          payment_type: payment_type,
          special_instructions: special_instructions,
          total: res.total,
          service_fee: res.service_fee,
          grand_total: res.grand_total,
          items: res.items || items
        };

        // Show success modal
        document.getElementById('modalOrderId').textContent = '#' + res.order_id;
        document.getElementById('modalOrderTotal').textContent = '₱' + res.grand_total.toFixed(2);
        document.getElementById('modalOrderType').textContent = order_type;
        
        // Calculate estimated ready time (15-25 minutes from now)
        const now = new Date();
        const readyTime = new Date(now.getTime() + (20 * 60 * 1000)); // 20 minutes average
        document.getElementById('modalReadyTime').textContent = readyTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        document.getElementById('orderSuccessModal').style.display = 'block';
        
      } else {
        showQuickNotification(res.message || 'Failed to place order. Please try again.', 'error');
      }
    } catch (error) {
      showQuickNotification('Network error. Please check your connection and try again.', 'error');
      console.error('Order placement error:', error);
    } finally {
      // Re-enable button
      submitBtn.textContent = originalText;
      submitBtn.disabled = false;
    }
  });
}

// Enhanced order preview with safe number handling
function updateOrderPreview() {
  const itemInputs = document.querySelectorAll('.item_qty');
  const subtotalElement = document.getElementById('subtotal');
  const serviceFeeElement = document.getElementById('serviceFee');
  const orderTotalElement = document.getElementById('orderTotal');
  const summaryDetails = document.getElementById('orderSummaryDetails');
  
  let subtotal = 0;
  let summaryHtml = '<table><thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead><tbody>';
  let hasItems = false;

  itemInputs.forEach(input => {
    const qty = parseInt(input.value) || 0;
    const itemContainer = input.closest('.menu-item');
    
    if (!itemContainer || qty <= 0) return;

    const price = parseFloat(itemContainer.dataset.price) || 0;
    const name = itemContainer.querySelector('h3').textContent;
    const subtotalItem = price * qty;
    
    subtotal += subtotalItem;
    hasItems = true;

    summaryHtml += `
      <tr>
        <td>${name}</td>
        <td>${qty}</td>
        <td>₱${price.toFixed(2)}</td>
        <td>₱${subtotalItem.toFixed(2)}</td>
      </tr>
    `;
  });

  summaryHtml += '</tbody></table>';

  // Calculate service fee with safe number handling
  const serviceFee = Math.min(Math.max(subtotal * 0.05, 10), 50);
  const total = subtotal + serviceFee;

  // Update display
  if (summaryDetails) {
    if (hasItems) {
      summaryDetails.innerHTML = summaryHtml;
    } else {
      summaryDetails.innerHTML = '<p style="text-align: center; color: #888;">Select items from the menu to see the summary.</p>';
    }
  }

  if (subtotalElement) subtotalElement.textContent = `₱${subtotal.toFixed(2)}`;
  if (serviceFeeElement) serviceFeeElement.textContent = `₱${serviceFee.toFixed(2)}`;
  if (orderTotalElement) orderTotalElement.innerHTML = `<strong>₱${total.toFixed(2)}</strong>`;
}

// ======================================
// 3. FEATURE: ADMIN INVENTORY MANAGEMENT
// ======================================

const addFoodForm = document.getElementById('addFoodForm');
if (addFoodForm) {
  addFoodForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('food_name').value.trim();
    const price = parseFloat(document.getElementById('food_price').value);
    const stock = parseInt(document.getElementById('food_stock').value);
    const description = document.getElementById('food_desc').value.trim();
    const category = document.getElementById('food_category').value;

    const res = await postData('api.php?action=add_food', {
      name, price, stock, description, category
    });

    if (res.success) {
      alert('Food item added successfully!');
      location.reload(); 
    } else {
      alert(res.message || 'Failed to add food item.');
    }
  });
}

// ======================================
// 4. ENHANCED FEATURES
// ======================================

// Quick Restock Function
async function restockItem(itemId, quantity = 10) {
  if (!confirm(`Restock this item with ${quantity} units?`)) return;
  
  const res = await postData('api.php?action=restock_item', {
      id: itemId,
      quantity: quantity
  });
  
  if (res.success) {
      showQuickNotification(res.message);
      location.reload();
  } else {
      showQuickNotification(res.message || 'Restock failed.', 'error');
  }
}

// Quick notification system
function showQuickNotification(message, type = 'success') {
  // Remove existing notifications
  const existingNotifications = document.querySelectorAll('.quick-notification');
  existingNotifications.forEach(notif => notif.remove());

  const notification = document.createElement('div');
  notification.className = `quick-notification ${type}`;
  notification.textContent = message;
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    color: white;
    font-weight: 600;
    z-index: 10000;
    animation: slideInRight 0.3s ease;
    max-width: 300px;
  `;
  
  if (type === 'success') {
    notification.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
  } else {
    notification.style.background = 'linear-gradient(135deg, #dc3545, #e83e8c)';
  }
  
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.animation = 'slideOutRight 0.3s ease';
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
  @keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
  @keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
  }
`;
document.head.appendChild(style);

// Auto-refresh dashboard data
function startAutoRefresh(interval = 30000) {
  if (document.querySelector('.admin-body')) {
    setInterval(() => {
      const currentTab = new URLSearchParams(window.location.search).get('tab') || 'overview';
      if (['overview', 'orders'].includes(currentTab)) {
        location.reload();
      }
    }, interval);
  }
}

// Initialize auto-refresh on admin pages
if (document.querySelector('.admin-body')) {
  startAutoRefresh();
}

// ======================================
// 5. FEATURE: CUSTOMER ORDER ENHANCEMENTS
// ======================================

// Add item to cart with animation
function addToCart(itemId, quantity = 1) {
  const itemElement = document.querySelector(`[data-id="${itemId}"]`);
  if (itemElement) {
      const input = itemElement.querySelector('.item_qty');
      const currentValue = parseInt(input.value) || 0;
      const maxStock = parseInt(input.max);
      
      if (currentValue < maxStock) {
          input.value = currentValue + quantity;
          input.dispatchEvent(new Event('change'));
          
          // Visual feedback
          itemElement.style.backgroundColor = '#e8f5e8';
          setTimeout(() => {
              itemElement.style.backgroundColor = '';
          }, 1000);
          
          showQuickNotification('Item added to cart!');
      } else {
          showQuickNotification('Cannot add more - limited stock!', 'error');
      }
  }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
  // Ctrl + Enter to submit order
  if (e.ctrlKey && e.key === 'Enter') {
    const orderForm = document.getElementById('orderForm');
    if (orderForm) {
      orderForm.dispatchEvent(new Event('submit'));
    }
  }
  
  // Escape to close modal
  if (e.key === 'Escape') {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
      if (modal.style.display === 'block') {
        modal.style.display = 'none';
      }
    });
  }
});

// Initialize all enhanced features
document.addEventListener('DOMContentLoaded', function() {
  // Add animation to menu items on load
  const menuItems = document.querySelectorAll('.menu-item');
  menuItems.forEach((item, index) => {
    item.style.animationDelay = `${index * 0.1}s`;
    item.classList.add('fade-in');
  });
  
  // Initialize search functionality if search input exists
  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.addEventListener('input', performSearch);
  }
});

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