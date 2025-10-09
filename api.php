<?php
// api.php
session_start();
require_once 'db.php';
$action = $_GET['action'] ?? null;
$input = json_decode(file_get_contents('php://input'), true);
header('Content-Type: application/json');

if ($action === 'place_order') {
    $customer_name = trim($input['customer_name'] ?? '');
    $items = $input['items'] ?? [];
    $payment_type = $input['payment_type'] ?? 'Cash';
    $order_type = $input['order_type'] ?? 'Dine-in';

    if (!$customer_name || empty($items)) {
        echo json_encode(['success'=>false,'message'=>'Missing order data']); exit;
    }

    // start transaction
    $conn->begin_transaction();
    try {
        $total = 0;
        $order_items_to_insert = [];

        // compute total and check stock
        foreach ($items as $it) {
            $id = (int)$it['id']; $qty = (int)$it['qty'];
            if ($qty <= 0) continue;
            
            $stmt = $conn->prepare("SELECT price, stock FROM food_items WHERE id = ?");
            $stmt->bind_param('i',$id);
            $stmt->execute();
            $res = $stmt->get_result();
            if (!$row = $res->fetch_assoc()) throw new Exception("Item not found");
            
            if ($row['stock'] < $qty) throw new Exception("Not enough stock for " . $row['name']);

            $price = $row['price'];
            $subtotal = $price * $qty;
            $total += $subtotal;
            
            // Collect item details for batch insertion
            $order_items_to_insert[] = [
                'id' => $id, 
                'qty' => $qty, 
                'price' => $price
            ];

            $stmt->close();
        }

        // 1. Insert into orders table
        $stmt = $conn->prepare("INSERT INTO orders (customer_name, total, order_type, payment_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('sdss', $customer_name, $total, $order_type, $payment_type);
        $stmt->execute();
        $order_id = $conn->insert_id;
        $stmt->close();

        // 2. Update stock and insert order items
        foreach ($order_items_to_insert as $item) {
            $id = $item['id'];
            $qty = $item['qty'];
            $price = $item['price'];

            // update stock (Inventory Management)
            $u = $conn->prepare("UPDATE food_items SET stock = stock - ? WHERE id = ?");
            $u->bind_param('ii', $qty, $id);
            $u->execute();
            $u->close();

            // insert order_item
            $oi = $conn->prepare("INSERT INTO order_items (order_id, food_id, quantity, price) VALUES (?,?,?,?)");
            $oi->bind_param('iiid', $order_id, $id, $qty, $price);
            $oi->execute();
            $oi->close();
        }

        $conn->commit();
        // --- MODIFICATION HERE: Return the final total ---
        echo json_encode(['success'=>true,'order_id'=>$order_id, 'total' => $total]);
        // --------------------------------------------------
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

if ($action === 'add_food') {
    // only for admin - check session role
    if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
    }
    $name = trim($input['name'] ?? '');
    $desc = trim($input['description'] ?? '');
    $price = floatval($input['price'] ?? 0);
    $stock = intval($input['stock'] ?? 0);
    
    if (!$name) { echo json_encode(['success'=>false,'message'=>'Missing name']); exit; }

    $stmt = $conn->prepare("INSERT INTO food_items (name, description, price, stock) VALUES (?,?,?,?)");
    $stmt->bind_param('ssdi', $name, $desc, $price, $stock);

    if ($stmt->execute()) {
        echo json_encode(['success'=>true,'message'=>'Food item added.']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Database insert failed.']);
    }
    exit;
}