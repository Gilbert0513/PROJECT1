<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['user_role']!=='admin') header('Location:index.php');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header>Admin Dashboard - Foodhouse</header>
<nav>
    <a href="admin_dashboard.php">Inventory</a>
    <a href="admin_dashboard.php#users">Users</a>
    <a href="logout.php">Logout</a>
</nav>
<div class="container">
    <h2>Inventory</h2>
    <form id="addInventoryForm">
        <input type="text" id="inv_name" placeholder="Item Name" required>
        <input type="text" id="inv_sku" placeholder="SKU">
        <input type="number" id="inv_qty" placeholder="Quantity" required>
        <input type="number" step="0.01" id="inv_price" placeholder="Price" required>
        <input type="text" id="inv_unit" placeholder="Unit" value="pcs">
        <button type="submit">Add Item</button>
    </form>
    <table id="inventory_table"><thead><tr><th>ID</th><th>Name</th><th>Qty</th><th>Price</th></tr></thead><tbody></tbody></table>
</div>
<script src="js/main.js"></script>
<script>
async function loadInventory(){
    const res = await fetch('api.php?action=list_inventory').then(r=>r.json());
    if(res.success){
        const tbody = document.querySelector('#inventory_table tbody'); tbody.innerHTML='';
        res.data.forEach(i=>{
            tbody.innerHTML += `<tr><td>${i.id}</td><td>${i.name}</td><td>${i.qty}</td><td>${i.price}</td></tr>`;
        });
    }
}
document.getElementById('addInventoryForm').onsubmit = async e=>{
    e.preventDefault();
    const data = {
        name: document.getElementById('inv_name').value,
        sku: document.getElementById('inv_sku').value,
        qty: parseInt(document.getElementById('inv_qty').value),
        price: parseFloat(document.getElementById('inv_price').value),
        unit: document.getElementById('inv_unit').value
    };
    const res = await fetch('api.php?action=add_inventory',{method:'POST',body:JSON.stringify(data),headers:{'Content-Type':'application/json'}});
    if(res.ok) { alert('Item added'); loadInventory(); }
}
loadInventory();
</script>
</body>
</html>
