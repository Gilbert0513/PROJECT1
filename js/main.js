// Helper for POST requests
async function postData(url = '', data = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(data)
    });
    return response.json();
}

// Login
async function login(event) {
    event.preventDefault();
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const res = await postData('auth.php?action=login', {username,password});
    if(res.success){
        if(res.role === 'admin') window.location = 'admin_dashboard.php';
        else if(res.role === 'staff') window.location = 'staff_orders.php';
        else window.location = 'customer_orders.php';
    } else { alert(res.message); }
}

// Register
async function register(event){
    event.preventDefault();
    const username = document.getElementById('reg_username').value;
    const password = document.getElementById('reg_password').value;
    const fullname = document.getElementById('reg_fullname').value;
    const role = document.querySelector('input[name="role"]:checked').value;
    const res = await postData('auth.php?action=register',{username,password,full_name:fullname,role});
    if(res.success) { alert('Registered!'); window.location='index.php'; }
    else { alert(res.message); }
}

// Place order
async function placeOrder(event){
    event.preventDefault();
    const customer_name = document.getElementById('customer_name').value;
    const items = Array.from(document.querySelectorAll('.item_qty')).map(input=>{
        return {id:input.dataset.id, qty: parseInt(input.value)};
    });
    const res = await postData('api.php?action=place_order',{customer_name, items});
    if(res.success){ alert('Order Placed!'); window.location.reload();}
    else{ alert(res.message); }
}
