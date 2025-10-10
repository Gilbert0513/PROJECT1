<?php
// api.php (Enhanced with new features)
session_start();
require_once 'db.php';
$action = $_GET['action'] ?? null;
$input = json_decode(file_get_contents('php://input'), true);
header('Content-Type: application/json');

function isAdmin() {
    return !empty($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}

// NEW: Real-time Notifications
if ($action === 'get_notifications') {
    if (empty($_SESSION['user'])) {
        echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
    }
    
    $user_id = $_SESSION['user']['id'];
    $role = $_SESSION['user']['role'];
    
    $notifications = [];
    
    if ($role === 'admin') {
        // Admin notifications: low stock, new orders
        $low_stock = $conn->query("SELECT COUNT(*) as count FROM food_items WHERE stock <= 5");
        $new_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
        
        $notifications[] = [
            'type' => 'warning',
            'message' => $low_stock->fetch_assoc()['count'] . ' items low in stock',
            'link' => 'admin_dashboard.php?tab=overview'
        ];
        
        $notifications[] = [
            'type' => 'info',
            'message' => $new_orders->fetch_assoc()['count'] . ' new orders pending',
            'link' => 'admin_dashboard.php?tab=overview'
        ];
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
    
    echo json_encode(['success'=>true, 'notifications'=>$notifications]);
    exit;
}

// NEW: Favorites System
if ($action === 'toggle_favorite') {
    if (empty($_SESSION['user'])) {
        echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
    }
    
    $user_id = $_SESSION['user']['id'];
    $food_id = intval($input['food_id'] ?? 0);
    
    if (!$food_id) {
        echo json_encode(['success'=>false,'message'=>'Invalid food item']); exit;
    }
    
    // Check if already favorited
    $check = $conn->prepare("SELECT id FROM user_favorites WHERE user_id = ? AND food_id = ?");
    $check->bind_param('ii', $user_id, $food_id);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        // Remove favorite
        $delete = $conn->prepare("DELETE FROM user_favorites WHERE user_id = ? AND food_id = ?");
        $delete->bind_param('ii', $user_id, $food_id);
        $delete->execute();
        echo json_encode(['success'=>true, 'is_favorite'=>false]);
    } else {
        // Add favorite
        $insert = $conn->prepare("INSERT INTO user_favorites (user_id, food_id) VALUES (?, ?)");
        $insert->bind_param('ii', $user_id, $food_id);
        $insert->execute();
        echo json_encode(['success'=>true, 'is_favorite'=>true]);
    }
    exit;
}

// NEW: Order Status Management
if ($action === 'update_order_status') {
    if (!isAdmin()) {
        echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
    }
    
    $order_id = intval($input['order_id'] ?? 0);
    $status = $input['status'] ?? '';
    $valid_statuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
    
    if (!$order_id || !in_array($status, $valid_statuses)) {
        echo json_encode(['success'=>false,'message'=>'Invalid data']); exit;
    }
    
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $order_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success'=>true,'message'=>'Order status updated']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Update failed']);
    }
    exit;
}

if ($action === 'get_order_status') {
    if (empty($_SESSION['user'])) {
        echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
    }
    
    $order_id = intval($_GET['order_id'] ?? 0);
    
    if (!$order_id) {
        echo json_encode(['success'=>false,'message'=>'Invalid order ID']); exit;
    }
    
    $stmt = $conn->prepare("SELECT status, order_date FROM orders WHERE id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($order = $result->fetch_assoc()) {
        echo json_encode(['success'=>true, 'status'=>$order['status'], 'order_date'=>$order['order_date']]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Order not found']);
    }
    exit;
}

// NEW: Bulk Operations
if ($action === 'bulk_update_stock') {
    if (!isAdmin()) {
        echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
    }
    
    $items = $input['items'] ?? [];
    
    if (empty($items)) {
        echo json_encode(['success'=>false,'message'=>'No items provided']); exit;
    }
    
    $conn->begin_transaction();
    try {
        foreach ($items as $item) {
            $id = intval($item['id']);
            $stock = intval($item['stock']);
            
            $stmt = $conn->prepare("UPDATE food_items SET stock = ? WHERE id = ?");
            $stmt->bind_param('ii', $stock, $id);
            $stmt->execute();
        }
        
        $conn->commit();
        echo json_encode(['success'=>true,'message'=>'Stock updated successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success'=>false,'message'=>'Bulk update failed']);
    }
    exit;
}

// NEW: Export Data
if ($action === 'export_data') {
    if (!isAdmin()) {
        echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
    }
    
    $type = $_GET['type'] ?? 'sales';
    $format = $_GET['format'] ?? 'csv';
    
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

// ... REST OF YOUR EXISTING API CODE (place_order, add_food, etc.) ...
if ($action === 'place_order') {
    $customer_name = trim($input['customer_name'] ?? '');
    $items = $input['items'] ?? [];
    $payment_type = $input['payment_type'] ?? 'Cash';
    $order_type = $input['order_type'] ?? 'Dine-in';
    $special_instructions = trim($input['special_instructions'] ?? '');

    if (!$customer_name || empty($items)) {
        echo json_encode(['success'=>false,'message'=>'Missing order data']); exit;
    }

    $conn->begin_transaction();
    try {
        $total = 0;
        $order_items_details = [];
        
        // Compute total and check stock
        foreach ($items as $it) {
            $id = (int)$it['id']; 
            $qty = (int)$it['qty'];
            
            if ($qty <= 0) continue;
            
            $stmt = $conn->prepare("SELECT name, price, stock FROM food_items WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if (!$row = $res->fetch_assoc()) throw new Exception("Item not found");
            if ($row['stock'] < $qty) throw new Exception("Not enough stock for {$row['name']}");
            
            // Ensure price is numeric
            $actual_price = (float)$row['price'];
            $total += $actual_price * $qty;
            
            $order_items_details[] = [
                'id' => $id,
                'name' => $row['name'],
                'qty' => $qty,
                'price' => $actual_price // Ensure this is a number
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
        
        $stmt->execute();
        $order_id = $conn->insert_id;

        // Update stock and insert order items
        foreach ($order_items_details as $item) {
            // Update stock
            $u = $conn->prepare("UPDATE food_items SET stock = stock - ? WHERE id = ?");
            $u->bind_param('ii', $item['qty'], $item['id']);
            $u->execute();
            
            // Insert order item
            $oi = $conn->prepare("INSERT INTO order_items (order_id, food_id, quantity, price) VALUES (?,?,?,?)");
            $oi->bind_param('iiid', $order_id, $item['id'], $item['qty'], $item['price']);
            $oi->execute();
        }

        $conn->commit();
        
        // Return data with proper numeric types
        echo json_encode([
            'success' => true, 
            'order_id' => $order_id,
            'total' => (float)$total,
            'service_fee' => (float)$service_fee,
            'grand_total' => (float)$grand_total,
            'items' => $order_items_details
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

if ($action === 'add_food') {
    if (!isAdmin()) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
    
    $name = trim($input['name'] ?? '');
    $desc = trim($input['description'] ?? '');
    $price = floatval($input['price'] ?? 0);
    $stock = intval($input['stock'] ?? 0);
    $category = trim($input['category'] ?? 'Uncategorized');
    
    if (!$name || $price <= 0 || $stock < 0) { echo json_encode(['success'=>false,'message'=>'Missing or invalid data.']); exit; }
    
    $stmt = $conn->prepare("INSERT INTO food_items (name, description, price, stock, category) VALUES (?,?,?,?,?)");
    $stmt->bind_param('ssdis', $name, $desc, $price, $stock, $category);
    
    if ($stmt->execute()) {
        echo json_encode(['success'=>true,'id'=>$conn->insert_id]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Database insert failed.']);
    }
    exit;
}

if ($action === 'edit_food') {
    if (!isAdmin()) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

    $id = intval($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    $desc = trim($input['description'] ?? '');
    $price = floatval($input['price'] ?? 0);
    $stock = intval($input['stock'] ?? 0);
    $category = trim($input['category'] ?? 'Uncategorized');

    if (!$id || !$name || $price <= 0 || $stock < 0) { echo json_encode(['success'=>false,'message'=>'Missing required data for update.']); exit; }

    $stmt = $conn->prepare("UPDATE food_items SET name=?, description=?, price=?, stock=?, category=? WHERE id=?");
    $stmt->bind_param('ssdssi', $name, $desc, $price, $stock, $category, $id);

    if ($stmt->execute()) {
        echo json_encode(['success'=>true,'message'=>'Item updated successfully.']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Database update failed.']);
    }
    exit;
}

if ($action === 'delete_food') {
    if (!isAdmin()) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
    
    $id = intval($input['id'] ?? 0);
    
    if (!$id) { echo json_encode(['success'=>false,'message'=>'Missing item ID.']); exit; }

    $stmt = $conn->prepare("DELETE FROM food_items WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        echo json_encode(['success'=>true,'message'=>'Item deleted successfully.']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Database delete failed.']);
    }
    exit;
}

if ($action === 'restock_item') {
    if (!isAdmin()) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
    
    $id = intval($input['id'] ?? 0);
    $quantity = intval($input['quantity'] ?? 10);
    
    if (!$id) { echo json_encode(['success'=>false,'message'=>'Missing item ID.']); exit; }
    
    $stmt = $conn->prepare("UPDATE food_items SET stock = stock + ? WHERE id = ?");
    $stmt->bind_param('ii', $quantity, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success'=>true,'message'=>"Item restocked with {$quantity} units."]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Restock failed.']);
    }
    exit;
}

if ($action === 'get_item') {
    if (!isAdmin()) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'message'=>'Missing item ID.']); exit; }
    
    $stmt = $conn->prepare("SELECT * FROM food_items WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($item = $result->fetch_assoc()) {
        echo json_encode(['success'=>true,'item'=>$item]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Item not found.']);
    }
    exit;
}

// LOGOUT
if ($action === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action.']);
?>

put that in my API