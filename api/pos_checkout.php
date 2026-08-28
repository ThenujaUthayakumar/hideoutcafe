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
    ensureProductDiscountSchema();
    ensureCustomerDobSchema();
    ensureOrderDiscountSchema();
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
    $productDiscountAmount = 0.0;
    $grossSubtotal = 0.0;
    $eligibleSubtotal = 0.0;
    $itemDiscounts = [];

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
        order_id, product_id, product_name, variant_name, unit_price, quantity, subtotal, promotion_discount, normal_discount, modifiers_json, notes
    ) VALUES (
        :order_id, :product_id, :product_name, :variant_name, :unit_price, :quantity, :subtotal, 0, 0, :modifiers_json, :notes
    )";

    foreach ($data['items'] as $item) {
        $prodId      = (int)$item['product_id'];
        $prodName    = sanitize($item['product_name']);
        $variantName = !empty($item['variant_name']) ? sanitize($item['variant_name']) : null;
        $unitPrice   = (float)$item['unit_price'];
        $qty         = max(1, (int)$item['quantity']);
        $itemSubtotal= (float)$item['subtotal'];
        $product = $db->fetchOne("SELECT discount_type, discount_value, discount_start, discount_end FROM products WHERE id = :pid AND status = 'active'", [':pid' => $prodId]);
        if (!$product) throw new Exception('A product in this order is no longer available.');
        $grossSubtotal += $itemSubtotal;
        $promotionValue = getActiveProductDiscount($product);
        if ($promotionValue > 0) {
            $lineDiscount = $product['discount_type'] === 'fixed' ? $promotionValue * $qty : ($itemSubtotal * $promotionValue) / 100;
            $lineDiscount = min($itemSubtotal, $lineDiscount);
            $productDiscountAmount += $lineDiscount;
        } else {
            $lineDiscount = 0.0;
            $eligibleSubtotal += $itemSubtotal;
        }
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
        $itemDiscounts[] = ['id' => (int)$db->lastInsertId(), 'subtotal' => $itemSubtotal, 'promotion' => $lineDiscount];

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

    $orderDiscountBase = max(0, $eligibleSubtotal);
    $orderDiscount = $discountType === 'percentage' ? ($orderDiscountBase * max(0, $discountVal)) / 100 : max(0, $discountVal);
    $orderDiscount = min($orderDiscountBase, $orderDiscount);
    $discountAmt = $productDiscountAmount + $orderDiscount;
    $subtotal = $grossSubtotal;
    $grandTotal = max(0, $grossSubtotal - $productDiscountAmount - $orderDiscount + $taxAmount + $serviceCharge);
    $changeAmount = max(0, $paidAmount - $grandTotal);
    $db->query(
        "UPDATE orders SET subtotal = :subtotal, discount_amount = :discount_amount, promotion_discount = :promotion_discount, normal_discount = :normal_discount, discount_percent = :discount_percent, grand_total = :grand_total, change_amount = :change_amount WHERE id = :order_id",
        [':subtotal' => $subtotal, ':discount_amount' => $discountAmt, ':promotion_discount' => $productDiscountAmount, ':normal_discount' => $orderDiscount, ':discount_percent' => $discountType === 'percentage' ? max(0, $discountVal) : 0, ':grand_total' => $grandTotal, ':change_amount' => $changeAmount, ':order_id' => $orderId]
    );
    foreach ($itemDiscounts as $itemDiscount) {
        $normalLineDiscount = $eligibleSubtotal > 0 && $itemDiscount['promotion'] == 0
            ? ($orderDiscount * $itemDiscount['subtotal']) / $eligibleSubtotal
            : 0;
        $db->query(
            "UPDATE order_items SET promotion_discount = :promotion_discount, normal_discount = :normal_discount WHERE id = :id",
            [':promotion_discount' => $itemDiscount['promotion'], ':normal_discount' => $normalLineDiscount, ':id' => $itemDiscount['id']]
        );
    }

    // Keep spend independent from the optional loyalty feature toggle.
    if ($customerId && $customerId > 1) {
        $db->query(
            "UPDATE customers SET total_spent = total_spent + :spent, loyalty_points = ROUND((total_spent + :spent_for_points) / 1000, 2) WHERE id = :cid",
            [':spent' => $grandTotal, ':spent_for_points' => $grandTotal, ':cid' => $customerId]
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
