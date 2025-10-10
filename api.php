 <?php
// api.php (Fixed - ensure numeric prices)
session_start();
require_once 'db.php';
$action = $_GET['action'] ?? null;
$input = json_decode(file_get_contents('php://input'), true);
header('Content-Type: application/json');

function isAdmin() {
    return !empty($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}

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

// ... rest of your API code ...

if ($action === 'add_food') {
    if (!isAdmin()) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
    
    $name = trim($input['name'] ?? '');
    $desc = trim($input['description'] ?? '');
    $price = floatval($input['price'] ?? 0);
    $stock = intval($input['stock'] ?? 0);
    
    if (!$name || $price <= 0 || $stock < 0) { echo json_encode(['success'=>false,'message'=>'Missing or invalid data.']); exit; }
    
    $stmt = $conn->prepare("INSERT INTO food_items (name, description, price, stock) VALUES (?,?,?,?)");
    $stmt->bind_param('ssdi', $name, $desc, $price, $stock);
    
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

    if (!$id || !$name || $price <= 0 || $stock < 0) { echo json_encode(['success'=>false,'message'=>'Missing required data for update.']); exit; }

    $stmt = $conn->prepare("UPDATE food_items SET name=?, description=?, price=?, stock=? WHERE id=?");
    $stmt->bind_param('ssdii', $name, $desc, $price, $stock, $id);

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