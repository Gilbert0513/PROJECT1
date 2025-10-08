<?php
require_once 'db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

function require_auth() {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success'=>false,'message'=>'Authentication required']);
        exit;
    }
}

function require_admin() {
    require_auth();
    if ($_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'Admin only']);
        exit;
    }
}

try {
    // Inventory CRUD (Admin only)
    if ($action === 'list_inventory') {
        $stmt = $pdo->query('SELECT * FROM inventory ORDER BY id DESC');
        echo json_encode(['success'=>true,'data'=>$stmt->fetchAll()]);
        exit;
    }

    if ($action === 'add_inventory' && $method==='POST') {
        require_admin();
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare('INSERT INTO inventory (name,sku,qty,price,unit) VALUES (:n,:s,:q,:p,:u)');
        $stmt->execute([
            ':n'=>$data['name'], ':s'=>$data['sku'], ':q'=>(int)$data['qty'],
            ':p'=>(float)$data['price'], ':u'=>$data['unit']
        ]);
        echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId()]);
        exit;
    }

    // Place orders (Staff/Customer)
    if ($action === 'place_order' && $method==='POST') {
        require_auth();
        $data = json_decode(file_get_contents('php://input'), true);
        $items = $data['items'] ?? [];
        $total = 0;
        $details = [];

        $pdo->beginTransaction();
        foreach ($items as $it) {
            $id = (int)$it['id'];
            $qty = (int)$it['qty'];
            $stmt = $pdo->prepare('SELECT * FROM inventory WHERE id=:id'); $stmt->execute([':id'=>$id]);
            $row = $stmt->fetch();
            if (!$row) { $pdo->rollBack(); throw new Exception('Item not found'); }
            if ($row['qty'] < $qty) { $pdo->rollBack(); throw new Exception('Insufficient stock'); }
            $stmt = $pdo->prepare('UPDATE inventory SET qty=:qty WHERE id=:id'); $stmt->execute([':qty'=>$row['qty']-$qty,':id'=>$id]);
            $line = $qty * $row['price']; $total += $line;
            $details[] = ['id'=>$id,'name'=>$row['name'],'qty'=>$qty,'price'=>$row['price'],'line_total'=>$line];
        }

        $stmt = $pdo->prepare('INSERT INTO orders (user_id, customer_name, items_json, total) VALUES (:u,:c,:items,:total)');
        $stmt->execute([
            ':u'=>$_SESSION['user_id'], ':c'=>$data['customer_name'], ':items'=>json_encode($details), ':total'=>$total
        ]);
        $pdo->commit();
        echo json_encode(['success'=>true,'order'=>['id'=>$pdo->lastInsertId(),'total'=>$total,'items'=>$details]]);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
