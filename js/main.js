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

// ORDER PREVIEW & CALCULATION
function updateOrderPreview() {
  const itemInputs = document.querySelectorAll('.item_qty');
  const orderTotalElement = document.getElementById('orderTotal');
  const summaryDetails = document.getElementById('orderSummaryDetails');
  let total = 0;
  let summaryHtml = '<table><thead><tr><th>Item</th><th>Qty</th><th>Subtotal</th></tr></thead><tbody>';
  let hasItems = false;

  itemInputs.forEach(input => {
    const qty = parseInt(input.value);
    const itemContainer = input.closest('.menu-item');
    
    if (!itemContainer) return; 

    // Retrieve data attributes set in user_home.php
    const price = parseFloat(itemContainer.dataset.price); 
    const name = itemContainer.querySelector('h3').textContent;

    if (qty > 0) {
      hasItems = true;
      const subtotal = price * qty;
      total += subtotal;

      summaryHtml += `
        <tr>
          <td>${name}</td>
          <td>${qty} x ₱${price.toFixed(2)}</td>
          <td>₱${subtotal.toFixed(2)}</td>
        </tr>
      `;
    }
  });

  summaryHtml += '</tbody></table>';

  if (summaryDetails) {
      if (hasItems) {
        summaryDetails.innerHTML = summaryHtml;
      } else {
        summaryDetails.innerHTML = '<p style="text-align: center; color: #888;">Select items from the menu to see the summary.</p>';
      }
  }

  if (orderTotalElement) {
    orderTotalElement.textContent = `₱${total.toFixed(2)}`;
  }
}

// ORDER PLACEMENT SUBMISSION
const orderForm = document.getElementById('orderForm');
if (orderForm) {
  orderForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const customer_name = document.getElementById('customer_name').value;
    const order_type = document.getElementById('order_type').value;
    const payment_type = document.getElementById('payment_type').value;

    const itemInputs = document.querySelectorAll('.item_qty');
    const items = [];
    let hasItems = false;

    // Collect ordered items
    itemInputs.forEach(input => {
      const qty = parseInt(input.value);
      if (qty > 0) {
        items.push({
          id: input.dataset.id, // Ensure data-id is set in user_home.php
          qty: qty
        });
        hasItems = true;
      }
    });

    if (!hasItems) {
      alert('Please select at least one item to order.');
      return;
    }

    const res = await postData('api.php?action=place_order', {
      customer_name,
      items,
      order_type,
      payment_type
    });

    if (res.success) {
      // Use the total returned from the server (api.php) for accuracy
      alert(`Order #${res.order_id} successfully placed! Total: ₱${res.total.toFixed(2)}. Order Type: ${order_type}`);
      location.reload(); 
    } else {
      alert(res.message || 'Failed to place order. Check stock or server logs.');
    }
  });
  
  // Attach the preview function to all quantity inputs on page load
  document.addEventListener('DOMContentLoaded', () => {
      const itemInputs = document.querySelectorAll('.item_qty');
      itemInputs.forEach(input => {
        input.addEventListener('change', updateOrderPreview);
        input.addEventListener('keyup', updateOrderPreview);
      });
      updateOrderPreview();
  });
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