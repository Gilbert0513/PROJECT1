<?php
// api.php (Fixed with proper error handling)
session_start();
require_once 'db.php';

// Get action from GET or POST
$action = $_GET['action'] ?? null;
if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? null;
}

// If no action specified, use GET
if (!$action) {
    $action = $_GET['action'] ?? null;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
header('Content-Type: application/json');

function isAdmin() {
    return !empty($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}

function sendResponse($success, $message = '', $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Handle preflight CORS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// LOGIN
if ($action === 'login') {
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (!$username || !$password) {
        sendResponse(false, 'Please enter username and password.');
    }

    try {
        $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user'] = [
                    'id' => $row['id'],
                    'full_name' => $row['full_name'],
                    'role' => $row['role'],
                    'username' => $username
                ];
                sendResponse(true, 'Login successful', [
                    'role' => $row['role'],
                    'full_name' => $row['full_name']
                ]);
            } else {
                sendResponse(false, 'Invalid password.');
            }
        } else {
            sendResponse(false, 'Username not found.');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error during login.');
    }
}

// REGISTER
if ($action === 'register') {
    $full_name = trim($input['full_name'] ?? '');
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');
    $role = $input['role'] ?? 'customer';

    if (!$full_name || !$username || !$password) {
        sendResponse(false, 'Please fill in all fields.');
    }

    try {
        // Check existing username
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param('s', $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            sendResponse(false, 'Username already exists.');
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $full_name, $username, $hashed, $role);

        if ($stmt->execute()) {
            sendResponse(true, 'Account created successfully.');
        } else {
            sendResponse(false, 'Database insert failed.');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error during registration.');
    }
}

// PLACE ORDER
if ($action === 'place_order') {
    if (empty($_SESSION['user'])) {
        sendResponse(false, 'Please login to place order.');
    }

    $customer_name = trim($input['customer_name'] ?? '');
    $items = $input['items'] ?? [];
    $payment_type = $input['payment_type'] ?? 'Cash';
    $order_type = $input['order_type'] ?? 'Dine-in';
    $special_instructions = trim($input['special_instructions'] ?? '');

    if (!$customer_name || empty($items)) {
        sendResponse(false, 'Missing order data.');
    }

    $conn->begin_transaction();
    try {
        $total = 0;
        $order_items_details = [];
        
        // Compute total and check stock
        foreach ($items as $it) {
            $id = (int)($it['id'] ?? 0); 
            $qty = (int)($it['qty'] ?? 0);
            
            if ($qty <= 0) continue;
            
            $stmt = $conn->prepare("SELECT name, price, stock FROM food_items WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if (!$row = $res->fetch_assoc()) {
                throw new Exception("Item not found");
            }
            if ($row['stock'] < $qty) {
                throw new Exception("Not enough stock for {$row['name']}");
            }
            
            $actual_price = (float)$row['price'];
            $total += $actual_price * $qty;
            
            $order_items_details[] = [
                'id' => $id,
                'name' => $row['name'],
                'qty' => $qty,
                'price' => $actual_price
            ];
        }

        // Calculate service fee
        $service_fee = min(max($total * 0.05, 10), 50);
        $grand_total = $total + $service_fee;

        // Insert order
        if (!empty($special_instructions)) {
            $stmt = $conn->prepare("INSERT INTO orders (customer_name, order_type, payment_type, total, order_date, special_instructions) VALUES (?, ?, ?, ?, NOW(), ?)");
            $stmt->bind_param('sssds', $customer_name, $order_type, $payment_type, $total, $special_instructions);
        } else {
            $stmt = $conn->prepare("INSERT INTO orders (customer_name, order_type, payment_type, total, order_date) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param('sssd', $customer_name, $order_type, $payment_type, $total);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create order");
        }
        
        $order_id = $conn->insert_id;

        // Update stock and insert order items
        foreach ($order_items_details as $item) {
            // Update stock
            $u = $conn->prepare("UPDATE food_items SET stock = stock - ? WHERE id = ?");
            $u->bind_param('ii', $item['qty'], $item['id']);
            if (!$u->execute()) {
                throw new Exception("Failed to update stock");
            }
            
            // Insert order item
            $oi = $conn->prepare("INSERT INTO order_items (order_id, food_id, quantity, price) VALUES (?,?,?,?)");
            $oi->bind_param('iiid', $order_id, $item['id'], $item['qty'], $item['price']);
            if (!$oi->execute()) {
                throw new Exception("Failed to add order items");
            }
        }

        $conn->commit();
        
        sendResponse(true, 'Order placed successfully', [
            'order_id' => $order_id,
            'total' => (float)$total,
            'service_fee' => (float)$service_fee,
            'grand_total' => (float)$grand_total,
            'items' => $order_items_details
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(false, $e->getMessage());
    }
}

// ADD FOOD ITEM (Admin only)
if ($action === 'add_food') {
    if (!isAdmin()) { 
        sendResponse(false, 'Unauthorized');
    }
    
    $name = trim($input['name'] ?? '');
    $desc = trim($input['description'] ?? '');
    $price = floatval($input['price'] ?? 0);
    $stock = intval($input['stock'] ?? 0);
    $category = trim($input['category'] ?? 'Uncategorized');
    
    if (!$name || $price <= 0 || $stock < 0) { 
        sendResponse(false, 'Missing or invalid data.');
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO food_items (name, description, price, stock, category) VALUES (?,?,?,?,?)");
        $stmt->bind_param('ssdis', $name, $desc, $price, $stock, $category);
        
        if ($stmt->execute()) {
            sendResponse(true, 'Food item added successfully', ['id' => $conn->insert_id]);
        } else {
            sendResponse(false, 'Database insert failed.');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// EDIT FOOD ITEM (Admin only)
if ($action === 'edit_food') {
    if (!isAdmin()) { 
        sendResponse(false, 'Unauthorized');
    }

    $id = intval($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    $desc = trim($input['description'] ?? '');
    $price = floatval($input['price'] ?? 0);
    $stock = intval($input['stock'] ?? 0);
    $category = trim($input['category'] ?? 'Uncategorized');

    if (!$id || !$name || $price <= 0 || $stock < 0) { 
        sendResponse(false, 'Missing required data for update.');
    }

    try {
        $stmt = $conn->prepare("UPDATE food_items SET name=?, description=?, price=?, stock=?, category=? WHERE id=?");
        $stmt->bind_param('ssdssi', $name, $desc, $price, $stock, $category, $id);

        if ($stmt->execute()) {
            sendResponse(true, 'Item updated successfully.');
        } else {
            sendResponse(false, 'Database update failed.');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// DELETE FOOD ITEM (Admin only)
if ($action === 'delete_food') {
    if (!isAdmin()) { 
        sendResponse(false, 'Unauthorized');
    }
    
    $id = intval($input['id'] ?? 0);
    
    if (!$id) { 
        sendResponse(false, 'Missing item ID.');
    }

    try {
        $stmt = $conn->prepare("DELETE FROM food_items WHERE id = ?");
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            sendResponse(true, 'Item deleted successfully.');
        } else {
            sendResponse(false, 'Database delete failed.');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// RESTOCK ITEM (Admin only)
if ($action === 'restock_item') {
    if (!isAdmin()) { 
        sendResponse(false, 'Unauthorized');
    }
    
    $id = intval($input['id'] ?? 0);
    $quantity = intval($input['quantity'] ?? 10);
    
    if (!$id) { 
        sendResponse(false, 'Missing item ID.');
    }
    
    try {
        $stmt = $conn->prepare("UPDATE food_items SET stock = stock + ? WHERE id = ?");
        $stmt->bind_param('ii', $quantity, $id);
        
        if ($stmt->execute()) {
            sendResponse(true, "Item restocked with {$quantity} units.");
        } else {
            sendResponse(false, 'Restock failed.');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// GET FOOD ITEM (Admin only)
if ($action === 'get_item') {
    if (!isAdmin()) { 
        sendResponse(false, 'Unauthorized');
    }
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { 
        sendResponse(false, 'Missing item ID.');
    }
    
    try {
        $stmt = $conn->prepare("SELECT * FROM food_items WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($item = $result->fetch_assoc()) {
            sendResponse(true, 'Item found', ['item' => $item]);
        } else {
            sendResponse(false, 'Item not found.');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// UPDATE ORDER STATUS (Admin only)
if ($action === 'update_order_status') {
    if (!isAdmin()) {
        sendResponse(false, 'Unauthorized');
    }
    
    $order_id = intval($input['order_id'] ?? 0);
    $status = $input['status'] ?? '';
    $valid_statuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
    
    if (!$order_id || !in_array($status, $valid_statuses)) {
        sendResponse(false, 'Invalid data');
    }
    
    try {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $order_id);
        
        if ($stmt->execute()) {
            sendResponse(true, 'Order status updated');
        } else {
            sendResponse(false, 'Update failed');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// GET ORDER STATUS
if ($action === 'get_order_status') {
    if (empty($_SESSION['user'])) {
        sendResponse(false, 'Unauthorized');
    }
    
    $order_id = intval($_GET['order_id'] ?? 0);
    
    if (!$order_id) {
        sendResponse(false, 'Invalid order ID');
    }
    
    try {
        $stmt = $conn->prepare("SELECT status, order_date FROM orders WHERE id = ?");
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($order = $result->fetch_assoc()) {
            sendResponse(true, 'Order found', [
                'status' => $order['status'], 
                'order_date' => $order['order_date']
            ]);
        } else {
            sendResponse(false, 'Order not found');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// BULK UPDATE STOCK (Admin only)
if ($action === 'bulk_update_stock') {
    if (!isAdmin()) {
        sendResponse(false, 'Unauthorized');
    }
    
    $items = $input['items'] ?? [];
    
    if (empty($items)) {
        sendResponse(false, 'No items provided');
    }
    
    $conn->begin_transaction();
    try {
        foreach ($items as $item) {
            $id = intval($item['id'] ?? 0);
            $stock = intval($item['stock'] ?? 0);
            
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE food_items SET stock = ? WHERE id = ?");
                $stmt->bind_param('ii', $stock, $id);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update item {$id}");
                }
            }
        }
        
        $conn->commit();
        sendResponse(true, 'Stock updated successfully');
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(false, 'Bulk update failed: ' . $e->getMessage());
    }
}

// EXPORT DATA (Admin only)
if ($action === 'export_data') {
    if (!isAdmin()) {
        sendResponse(false, 'Unauthorized');
    }
    
    $type = $_GET['type'] ?? 'sales';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$type.'_export_'.date('Y-m-d').'.csv"');
    
    $output = fopen('php://output', 'w');
    
    switch ($type) {
        case 'sales':
            fputcsv($output, ['Order ID', 'Customer', 'Total', 'Order Type', 'Date', 'Status']);
            $result = $conn->query("SELECT * FROM orders ORDER BY order_date DESC");
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['id'],
                    $row['customer_name'],
                    $row['total'],
                    $row['order_type'],
                    $row['order_date'],
                    $row['status'] ?? 'pending'
                ]);
            }
            break;
            
        case 'inventory':
            fputcsv($output, ['Item ID', 'Name', 'Price', 'Stock', 'Category']);
            $result = $conn->query("SELECT * FROM food_items ORDER BY name ASC");
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['id'],
                    $row['name'],
                    $row['price'],
                    $row['stock'],
                    $row['category'] ?? 'Uncategorized'
                ]);
            }
            break;
    }
    
    fclose($output);
    exit;
}

// GET PLATFORM STATS (Admin only)
if ($action === 'get_platform_stats') {
    if (!isAdmin()) {
        sendResponse(false, 'Unauthorized');
    }
    
    try {
        // Platform usage statistics
        $web_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE created_via = 'web' OR created_via IS NULL")->fetch_assoc()['count'] ?? 0;
        $mobile_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE created_via = 'mobile'")->fetch_assoc()['count'] ?? 0;
        $total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;
        
        sendResponse(true, 'Platform stats retrieved', [
            'web_orders' => $web_orders,
            'mobile_orders' => $mobile_orders,
            'total_users' => $total_users,
            'active_today' => 0 // Placeholder for now
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error getting platform stats');
    }
}

// SYNC CART
if ($action === 'sync_cart') {
    if (empty($_SESSION['user'])) {
        sendResponse(false, 'Unauthorized');
    }
    
    $user_id = $_SESSION['user']['id'];
    $cart_data = $input['cart_data'] ?? [];
    
    try {
        // Check if cart exists
        $check = $conn->prepare("SELECT id FROM user_carts WHERE user_id = ?");
        $check->bind_param('i', $user_id);
        $check->execute();
        $check->store_result();
        
        $cart_json = json_encode($cart_data);
        
        if ($check->num_rows > 0) {
            // Update existing cart
            $stmt = $conn->prepare("UPDATE user_carts SET cart_data = ?, last_updated = NOW() WHERE user_id = ?");
            $stmt->bind_param('si', $cart_json, $user_id);
        } else {
            // Insert new cart
            $stmt = $conn->prepare("INSERT INTO user_carts (user_id, cart_data) VALUES (?, ?)");
            $stmt->bind_param('is', $user_id, $cart_json);
        }
        
        if ($stmt->execute()) {
            sendResponse(true, 'Cart synchronized');
        } else {
            sendResponse(false, 'Sync failed');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error during cart sync');
    }
}

// GET CART
if ($action === 'get_cart') {
    if (empty($_SESSION['user'])) {
        sendResponse(false, 'Unauthorized');
    }
    
    $user_id = $_SESSION['user']['id'];
    
    try {
        $stmt = $conn->prepare("SELECT cart_data FROM user_carts WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $cart_data = json_decode($row['cart_data'], true) ?? [];
            sendResponse(true, 'Cart retrieved', ['cart_data' => $cart_data]);
        } else {
            sendResponse(true, 'Cart retrieved', ['cart_data' => []]);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error getting cart');
    }
}

// GET NOTIFICATIONS
if ($action === 'get_notifications') {
    if (empty($_SESSION['user'])) {
        sendResponse(false, 'Unauthorized');
    }
    
    $user_id = $_SESSION['user']['id'];
    $role = $_SESSION['user']['role'];
    
    $notifications = [];
    
    try {
        if ($role === 'admin') {
            // Admin notifications: low stock, new orders
            $low_stock = $conn->query("SELECT COUNT(*) as count FROM food_items WHERE stock <= 5")->fetch_assoc()['count'] ?? 0;
            $new_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;
            
            if ($low_stock > 0) {
                $notifications[] = [
                    'type' => 'warning',
                    'message' => $low_stock . ' items low in stock',
                    'link' => 'admin_dashboard.php?tab=overview'
                ];
            }
            
            if ($new_orders > 0) {
                $notifications[] = [
                    'type' => 'info',
                    'message' => $new_orders . ' new orders pending',
                    'link' => 'admin_dashboard.php?tab=overview'
                ];
            }
        } else {
            // Customer notifications: order status updates
            $order_updates = $conn->query("
                SELECT o.id, o.status 
                FROM orders o 
                WHERE o.customer_name = '".$conn->real_escape_string($_SESSION['user']['full_name'])."' 
                AND o.status != 'completed' 
                ORDER BY o.order_date DESC LIMIT 3
            ");
            
            while ($order = $order_updates->fetch_assoc()) {
                $notifications[] = [
                    'type' => 'info',
                    'message' => "Order #{$order['id']} is {$order['status']}",
                    'link' => 'order_history.php'
                ];
            }
        }
        
        sendResponse(true, 'Notifications retrieved', ['notifications' => $notifications]);
    } catch (Exception $e) {
        sendResponse(false, 'Error getting notifications');
    }
}

// If no action matched
sendResponse(false, 'Unknown action: ' . $action);
?>