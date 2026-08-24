<?php
/**
 * API - POS Checkout Endpoint
 */
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized session. Please login.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (empty($data) || empty($data['items'])) {
    jsonResponse(['success' => false, 'message' => 'Cart is empty or invalid data provided.'], 400);
}

try {
    $db = db();
    $db->beginTransaction();

    $currentUser = currentUser();
    $activeShift = getOpenCashRegister();
    if (!$activeShift) {
        throw new Exception('No shift is open. Ask an administrator to open the shift first.');
    }
    if ((int)$activeShift['user_id'] !== (int)$currentUser['id']) {
        throw new Exception('This shift has not been confirmed by your user account.');
    }

    $invoiceNo = generateInvoiceNo();
    
    $orderType     = sanitize($data['order_type'] ?? ORDER_DINE_IN);
    $tableId       = !empty($data['table_id']) ? (int)$data['table_id'] : null;
    $customerId    = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
    $paymentMethod = sanitize($data['payment_method'] ?? 'cash');
    $subtotal      = (float)($data['subtotal'] ?? 0);
    $discountAmt   = (float)($data['discount_amount'] ?? 0);
    $discountVal   = (float)($data['discount_value'] ?? 0);
    $discountType  = sanitize($data['discount_type'] ?? 'percentage');
    $taxAmount     = (float)($data['tax_amount'] ?? 0);
    $serviceCharge = (float)($data['service_charge'] ?? 0);
    $grandTotal    = (float)($data['grand_total'] ?? 0);
    $paidAmount    = (float)($data['paid_amount'] ?? $grandTotal);
    $changeAmount  = (float)($data['change_amount'] ?? 0);
    $notes         = sanitize($data['notes'] ?? '');
    $orderStatus   = sanitize($data['order_status'] ?? STATUS_COMPLETED);

    // Insert Order Record
    $orderSql = "INSERT INTO orders (
        invoice_no, order_type, table_id, customer_id, user_id, 
        subtotal, discount_amount, discount_percent, tax_amount, service_charge, 
        grand_total, paid_amount, change_amount, payment_method, payment_status, 
        order_status, notes, created_at
    ) VALUES (
        :invoice_no, :order_type, :table_id, :customer_id, :user_id,
        :subtotal, :discount_amount, :discount_percent, :tax_amount, :service_charge,
        :grand_total, :paid_amount, :change_amount, :payment_method, 'paid',
        :order_status, :notes, NOW()
    )";

    $db->query($orderSql, [
        ':invoice_no'       => $invoiceNo,
        ':order_type'       => $orderType,
        ':table_id'         => $tableId,
        ':customer_id'      => $customerId,
        ':user_id'          => $currentUser['id'],
        ':subtotal'         => $subtotal,
        ':discount_amount'  => $discountAmt,
        ':discount_percent' => ($discountType === 'percentage' ? $discountVal : 0),
        ':tax_amount'       => $taxAmount,
        ':service_charge'   => $serviceCharge,
        ':grand_total'      => $grandTotal,
        ':paid_amount'      => $paidAmount,
        ':change_amount'    => $changeAmount,
        ':payment_method'   => $paymentMethod,
        ':order_status'     => $orderStatus,
        ':notes'            => $notes
    ]);

    $orderId = (int)$db->lastInsertId();

    // Insert Order Items & Deduct Stock
    $itemSql = "INSERT INTO order_items (
        order_id, product_id, product_name, variant_name, unit_price, quantity, subtotal, modifiers_json, notes
    ) VALUES (
        :order_id, :product_id, :product_name, :variant_name, :unit_price, :quantity, :subtotal, :modifiers_json, :notes
    )";

    foreach ($data['items'] as $item) {
        $prodId      = (int)$item['product_id'];
        $prodName    = sanitize($item['product_name']);
        $variantName = !empty($item['variant_name']) ? sanitize($item['variant_name']) : null;
        $unitPrice   = (float)$item['unit_price'];
        $qty         = max(1, (int)$item['quantity']);
        $itemSubtotal= (float)$item['subtotal'];
        $modifiers   = !empty($item['modifiers']) ? json_encode($item['modifiers']) : '[]';
        $itemNotes   = sanitize($item['notes'] ?? '');

        $db->query($itemSql, [
            ':order_id'       => $orderId,
            ':product_id'     => $prodId,
            ':product_name'   => $prodName,
            ':variant_name'   => $variantName,
            ':unit_price'     => $unitPrice,
            ':quantity'       => $qty,
            ':subtotal'       => $itemSubtotal,
            ':modifiers_json' => $modifiers,
            ':notes'          => $itemNotes
        ]);

        // Deduct inventory if track_stock is enabled
        $db->query(
            "UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :pid AND track_stock = 1",
            [':qty' => $qty, ':pid' => $prodId]
        );
    }

    // Update Table status
    if ($orderType === ORDER_DINE_IN && $tableId) {
        if ($orderStatus === STATUS_COMPLETED) {
            $db->query("UPDATE tables SET status = 'available', current_order_id = NULL WHERE id = :tid", [':tid' => $tableId]);
        } else {
            $db->query("UPDATE tables SET status = 'occupied', current_order_id = :oid WHERE id = :tid", [':oid' => $orderId, ':tid' => $tableId]);
        }
    }

    // Award Loyalty Points to customer
    if ($customerId && $customerId > 1 && getSettings('enable_loyalty') == '1') {
        $pointsRate = (float)(getSettings('points_per_dollar') ?? 1);
        $pointsEarned = (int)floor($grandTotal * $pointsRate / 100); // 1 pt per 100 LKR
        if ($pointsEarned < 1) $pointsEarned = 1;
        $db->query(
            "UPDATE customers SET loyalty_points = loyalty_points + :pts, total_spent = total_spent + :spent WHERE id = :cid",
            [':pts' => $pointsEarned, ':spent' => $grandTotal, ':cid' => $customerId]
        );
    }

    // Update Cash Register shift summary
    if ($activeShift) {
        $column = 'total_cash_sales';
        if ($paymentMethod === 'card') $column = 'total_card_sales';
        if ($paymentMethod === 'upi')  $column = 'total_upi_sales';
        
        $db->query(
            "UPDATE cash_registers SET $column = $column + :amt WHERE id = :sid",
            [':amt' => $grandTotal, ':sid' => $activeShift['id']]
        );
    }

    $db->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Sale completed successfully!',
        'order' => [
            'id'             => $orderId,
            'invoice_no'     => $invoiceNo,
            'grand_total'    => $grandTotal,
            'payment_method' => $paymentMethod,
            'order_status'   => $orderStatus,
            'created_at'     => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    jsonResponse(['success' => false, 'message' => 'Checkout error: ' . $e->getMessage()], 500);
}
