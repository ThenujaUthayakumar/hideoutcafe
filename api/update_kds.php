<?php
/**
 * API - Kitchen Display System (KDS) Live Handler
 */
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$action = $_REQUEST['action'] ?? 'fetch';

if ($action === 'fetch') {
    // Fetch all active orders (pending, preparing, ready)
    $orders = db()->fetchAll(
        "SELECT o.*, t.name as table_name, u.name as cashier_name 
         FROM orders o 
         LEFT JOIN tables t ON o.table_id = t.id 
         LEFT JOIN users u ON o.user_id = u.id 
         WHERE o.order_status IN ('pending', 'preparing', 'ready') 
         ORDER BY o.id ASC"
    );

    $orderIds = array_column($orders, 'id');
    $itemsByOrder = [];

    if (!empty($orderIds)) {
        $inClause = implode(',', array_fill(0, count($orderIds), '?'));
        $stmt = pdo()->prepare("SELECT * FROM order_items WHERE order_id IN ($inClause)");
        $stmt->execute($orderIds);
        $items = $stmt->fetchAll();

        foreach ($items as $it) {
            $itemsByOrder[$it['order_id']][] = $it;
        }
    }

    foreach ($orders as &$ord) {
        $ord['items'] = $itemsByOrder[$ord['id']] ?? [];
        $ord['time_ago'] = humanTiming(strtotime($ord['created_at']));
    }

    jsonResponse(['success' => true, 'orders' => $orders]);
}

if ($action === 'update_status') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? '');

    $allowedStatuses = [STATUS_PENDING, STATUS_PREPARING, STATUS_READY, STATUS_COMPLETED, STATUS_CANCELLED];
    if (!in_array($status, $allowedStatuses)) {
        jsonResponse(['success' => false, 'message' => 'Invalid status'], 400);
    }

    try {
        db()->query("UPDATE orders SET order_status = :status WHERE id = :id", [':status' => $status, ':id' => $orderId]);
        
        // If order completed or cancelled, free associated table
        if (in_array($status, [STATUS_COMPLETED, STATUS_CANCELLED])) {
            $order = db()->fetchOne("SELECT table_id FROM orders WHERE id = :id", [':id' => $orderId]);
            if (!empty($order['table_id'])) {
                db()->query("UPDATE tables SET status = 'available', current_order_id = NULL WHERE id = :tid", [':tid' => $order['table_id']]);
            }
        }

        jsonResponse(['success' => true, 'message' => "Order marked as " . strtoupper($status)]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to update order: ' . $e->getMessage()], 500);
    }
}

function humanTiming($time) {
    $time = time() - $time;
    $time = ($time < 1) ? 1 : $time;
    $tokens = [
        31536000 => 'yr',
        2592000 => 'mo',
        604800 => 'w',
        86400 => 'd',
        3600 => 'h',
        60 => 'min',
        1 => 'sec'
    ];

    foreach ($tokens as $unit => $text) {
        if ($time < $unit) continue;
        $numberOfUnits = floor($time / $unit);
        return $numberOfUnits . ' ' . $text . ' ago';
    }
    return 'just now';
}
