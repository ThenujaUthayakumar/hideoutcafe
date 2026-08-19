<?php
/**
 * API - Park / Hold and Recall Orders
 */
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? '';
$currentUser = currentUser();

if ($action === 'list') {
    $orders = db()->fetchAll(
        "SELECT id, hold_reference, customer_name, table_id, order_type, total_amount, created_at 
         FROM held_orders ORDER BY id DESC"
    );
    jsonResponse(['success' => true, 'orders' => $orders]);
}

if ($action === 'recall') {
    $id = (int)($_GET['id'] ?? 0);
    $order = db()->fetchOne("SELECT * FROM held_orders WHERE id = :id", [':id' => $id]);
    if (!$order) {
        jsonResponse(['success' => false, 'message' => 'Held order not found'], 404);
    }
    // Delete held order on recall
    db()->query("DELETE FROM held_orders WHERE id = :id", [':id' => $id]);
    jsonResponse(['success' => true, 'order' => $order]);
}

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    db()->query("DELETE FROM held_orders WHERE id = :id", [':id' => $id]);
    jsonResponse(['success' => true, 'message' => 'Held order deleted']);
}

// POST: Save Hold Order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    $ref = sanitize($data['hold_reference'] ?? ('Hold #' . rand(100, 999)));
    $customerName = sanitize($data['customer_name'] ?? 'Walk-in Customer');
    $customerId = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
    $tableId = !empty($data['table_id']) ? (int)$data['table_id'] : null;
    $orderType = sanitize($data['order_type'] ?? 'dine_in');
    $cartJson = $data['cart_json'] ?? '[]';
    $totalAmount = (float)($data['total_amount'] ?? 0);

    try {
        db()->query(
            "INSERT INTO held_orders (hold_reference, customer_name, customer_id, table_id, order_type, cart_json, total_amount, cashier_id) 
             VALUES (:ref, :cname, :cid, :tid, :otype, :cjson, :amt, :uid)",
            [
                ':ref'   => $ref,
                ':cname' => $customerName,
                ':cid'   => $customerId,
                ':tid'   => $tableId,
                ':otype' => $orderType,
                ':cjson' => $cartJson,
                ':amt'   => $totalAmount,
                ':uid'   => $currentUser['id']
            ]
        );
        jsonResponse(['success' => true, 'message' => 'Order parked successfully']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to hold order: ' . $e->getMessage()], 500);
    }
}
