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
        const name = itemContainer.dataset.name || 'Unknown Item';
        
        items.push({
          id: input.dataset.id,
          qty: qty,
          price: price, // This is now a number
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
        // Prepare order data with proper number conversion
        const orderData = {
          order_id: res.order_id,
          customer_name: customer_name,
          order_type: order_type,
          payment_type: payment_type,
          special_instructions: special_instructions,
          total: parseFloat(res.total) || 0,
          service_fee: parseFloat(res.service_fee) || 0,
          grand_total: parseFloat(res.grand_total) || 0,
          items: res.items || items
        };

        // Show receipt directly with thank you message
        showReceipt(orderData);
        
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

// Safe number conversion utility function
function safeToFixed(value, decimals = 2) {
  const num = parseFloat(value) || 0;
  return num.toFixed(decimals);
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

    const res = await postData('api.php?action=add_food', {
      name, price, stock, description
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
// 4. FEATURE: ADMIN INTERACTIVE FUNCTIONS
// ======================================

// Quick Restock Function
async function restockItem(itemId, quantity = 10) {
  if (!confirm(`Restock this item with ${quantity} units?`)) return;
  
  const res = await postData('api.php?action=restock_item', {
      id: itemId,
      quantity: quantity
  });
  
  if (res.success) {
      alert(res.message);
      location.reload();
  } else {
      alert(res.message || 'Restock failed.');
  }
}

// Edit Item Modal (Enhanced Add Food Form)
function openEditModal(itemId) {
  // Fetch item details and populate form
  fetch(`api.php?action=get_item&id=${itemId}`)
      .then(r => r.json())
      .then(res => {
          if (res.success) {
              const item = res.item;
              // Populate your edit form here
              document.getElementById('edit_food_id').value = item.id;
              document.getElementById('edit_food_name').value = item.name;
              document.getElementById('edit_food_desc').value = item.description;
              document.getElementById('edit_food_price').value = item.price;
              document.getElementById('edit_food_stock').value = item.stock;
              
              // Show modal
              document.getElementById('editModal').style.display = 'block';
          }
      });
}

// Quick Actions for Inventory Table
function addQuickActions() {
  document.querySelectorAll('.menu-item').forEach(item => {
      const itemId = item.dataset.id;
      const actionsHtml = `
          <div class="quick-actions" style="margin-top: 0.5rem;">
              <button onclick="restockItem(${itemId}, 5)" class="btn-small" style="padding: 0.2rem 0.5rem; font-size: 0.8rem;">+5</button>
              <button onclick="restockItem(${itemId}, 10)" class="btn-small" style="padding: 0.2rem 0.5rem; font-size: 0.8rem;">+10</button>
          </div>
      `;
      item.innerHTML += actionsHtml;
  });
}

// Auto-refresh dashboard data
function startAutoRefresh(interval = 30000) { // 30 seconds
  setInterval(() => {
      if (document.querySelector('.admin-body') && !document.querySelector('#addFoodForm')) {
          // Only refresh if on dashboard and not in middle of form
          const currentTab = new URLSearchParams(window.location.search).get('tab') || 'overview';
          if (['overview', 'velocity', 'revenue', 'analytics'].includes(currentTab)) {
              window.location.href = window.location.href; // Simple refresh
          }
      }
  }, interval);
}

// Initialize auto-refresh on admin pages
if (document.querySelector('.admin-body')) {
  // startAutoRefresh(); // Uncomment to enable auto-refresh
}

// ======================================
// 5. FEATURE: CUSTOMER ORDER ENHANCEMENTS
// ======================================

// Add item to cart with animation
function addToCart(itemId, quantity = 1) {
  const itemElement = document.querySelector(`[data-id="${itemId}"]`);
  if (itemElement) {
      const input = itemElement.querySelector('.item_qty');
      input.value = parseInt(input.value) + quantity;
      input.dispatchEvent(new Event('change'));
      
      // Visual feedback
      itemElement.style.backgroundColor = '#e8f5e8';
      setTimeout(() => {
          itemElement.style.backgroundColor = '';
      }, 1000);
  }
}

  // ======================================
// 6. FEATURE: CHART MANAGEMENT
// ======================================

// Chart instances storage
let chartInstances = {};

// Initialize all charts on page load
function initializeCharts() {
    // Destroy existing charts to prevent memory leaks
    Object.values(chartInstances).forEach(chart => {
        if (chart && typeof chart.destroy === 'function') {
            chart.destroy();
        }
    });
    chartInstances = {};
}

// Export chart as PNG
function exportChart(chartId, filename = 'chart') {
    const chart = chartInstances[chartId];
    if (chart) {
        const link = document.createElement('a');
        link.download = `${filename}-${new Date().toISOString().split('T')[0]}.png`;
        link.href = chart.toBase64Image();
        link.click();
    }
}

// Refresh chart data
async function refreshChartData(chartId, dataUrl) {
    try {
        const response = await fetch(dataUrl);
        const data = await response.json();
        
        if (chartInstances[chartId]) {
            chartInstances[chartId].data = data;
            chartInstances[chartId].update();
        }
    } catch (error) {
        console.error('Error refreshing chart data:', error);
    }
}

// Auto-refresh charts every 5 minutes
function startChartAutoRefresh() {
    setInterval(() => {
        if (document.querySelector('.admin-body')) {
            // Reload page to refresh all data
            window.location.reload();
        }
    }, 300000); // 5 minutes
}

// Initialize chart auto-refresh on admin pages
if (document.querySelector('.admin-body')) {
    // startChartAutoRefresh(); // Uncomment to enable auto-refresh
}

// Chart color palette
const chartColors = {
    primary: '#d87b3e',
    secondary: '#4CAF50',
    info: '#2196F3',
    warning: '#FFC107',
    danger: '#dc3545',
    success: '#28a745',
    dark: '#343a40'
};

// Utility function to generate random colors
function generateColors(count) {
    const colors = [];
    const baseColors = Object.values(chartColors);
    
    for (let i = 0; i < count; i++) {
        colors.push(baseColors[i % baseColors.length]);
    }
    
    return colors;
}

// Keyboard shortcuts for customer
document.addEventListener('keydown', (e) => {
  // Ctrl + Enter to submit order
  if (e.ctrlKey && e.key === 'Enter') {

// ORDER PLACEMENT SUBMISSION - FIXED
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

    // Collect ordered items with prices and names
    itemInputs.forEach(input => {
      const qty = parseInt(input.value);
      if (qty > 0) {
        const itemContainer = input.closest('.menu-item');
        const price = parseFloat(itemContainer.dataset.price);
        const name = itemContainer.dataset.name;
        
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

// Enhanced order preview with service fee calculation
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
    const qty = parseInt(input.value);
    const itemContainer = input.closest('.menu-item');
    
    if (!itemContainer || qty <= 0) return;

    const price = parseFloat(itemContainer.dataset.price);
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

  // Calculate service fee (5% of subtotal, min ₱10, max ₱50)
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

// Helper function to calculate total
function calculateTotal(items) {
  return items.reduce((total, item) => total + (item.price * item.qty), 0);
}    
  }
});