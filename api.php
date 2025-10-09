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
        // compute total and check stock
        foreach ($items as $it) {
            $id = (int)$it['id']; $qty = (int)$it['qty'];
            if ($qty <= 0) continue;
            $stmt = $conn->prepare("SELECT price, stock FROM food_items WHERE id = ?");
            $stmt->bind_param('i',$id);
            $stmt->execute();
            $res = $stmt->get_result();
            if (!$row = $res->fetch_assoc()) throw new Exception("Item not found");
            if ($row['stock'] < $qty) throw new Exception("Not enough stock for item ID $id");
            $total += $row['price'] * $qty;
            $stmt->close();
        }

        // insert order
        $stmt = $conn->prepare("INSERT INTO orders (customer_name, total, payment_type, order_type) VALUES (?,?,?,?)");
        $stmt->bind_param('sdss', $customer_name, $total, $payment_type, $order_type);
        $stmt->execute();
        $order_id = $stmt->insert_id;
        $stmt->close();

        // insert order items & update stock
        foreach ($items as $it) {
            $id = (int)$it['id']; $qty = (int)$it['qty'];
            if ($qty <= 0) continue;
            // get price again
            $stmt = $conn->prepare("SELECT price, stock FROM food_items WHERE id = ? FOR UPDATE");
            $stmt->bind_param('i',$id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $price = $row['price'];
            $newStock = $row['stock'] - $qty;
            if ($newStock < 0) throw new Exception("Stock error for item $id");

            // update stock
            $u = $conn->prepare("UPDATE food_items SET stock = ? WHERE id = ?");
            $u->bind_param('ii', $newStock, $id);
            $u->execute();
            $u->close();

            // insert order_item
            $oi = $conn->prepare("INSERT INTO order_items (order_id, food_id, quantity, price) VALUES (?,?,?,?)");
            $oi->bind_param('iiid', $order_id, $id, $qty, $price);
            $oi->execute();
            $oi->close();
            $stmt->close();
        }

        $conn->commit();
        echo json_encode(['success'=>true,'order_id'=>$order_id]);
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
    if ($stmt->execute()) echo json_encode(['success'=>true]);
    else echo json_encode(['success'=>false,'message'=>'DB error']);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action']);
