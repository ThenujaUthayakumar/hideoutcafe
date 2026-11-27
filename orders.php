<?php
/**
 * The Hide Out Cafe - Sales History & Orders Management
 */
require_once __DIR__ . '/config/functions.php';
requireAuth();

$title = 'Orders & Sales';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? 'Rs.';
$currentUser = currentUser();
$isCashier = hasRole(ROLE_CASHIER);
$statusFilter = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['status'] ?? 'all') : ($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'pending', 'completed', 'cancelled'], true)) {
    $statusFilter = 'all';
}
$statusRedirect = $statusFilter === 'all' ? '' : '?status=' . rawurlencode($statusFilter);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_payment') {
    $db = db();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? '';
    $paidAmountInput = $_POST['paid_amount'] ?? '';
    $allowedPaymentMethods = ['cash', 'card', 'upi'];

    try {
        if (!hasRole([ROLE_ADMIN, ROLE_MANAGER, ROLE_CASHIER])) {
            throw new Exception('You do not have permission to record payments.');
        }
        if (!verify_csrf()) {
            throw new Exception('Invalid security token. Refresh the page and try again.');
        }
        if (!is_string($paymentMethod) || !in_array($paymentMethod, $allowedPaymentMethods, true)) {
            throw new Exception('Select a valid payment method.');
        }
        if (!is_string($paidAmountInput) || trim($paidAmountInput) === '' || !is_numeric($paidAmountInput)) {
            throw new Exception('Enter the amount received.');
        }
        $paidAmount = (float)$paidAmountInput;
        if ($paidAmount <= 0) {
            throw new Exception('The amount received must be greater than zero.');
        }

        ensureCustomerDobSchema();
        $db->beginTransaction();
        $orderParams = [':id' => $orderId];
        $ownerFilter = '';
        if (hasRole(ROLE_CASHIER)) {
            $ownerFilter = ' AND user_id = :user_id';
            $orderParams[':user_id'] = $currentUser['id'];
        }
        $order = $db->fetchOne("SELECT * FROM orders WHERE id = :id$ownerFilter FOR UPDATE", $orderParams);
        if (!$order) {
            throw new Exception('Order not found or access denied.');
        }
        if ($order['payment_status'] !== 'unpaid' || $order['order_status'] === 'cancelled') {
            throw new Exception('This order is not awaiting payment.');
        }

        $customerNameInput = $_POST['customer_name'] ?? '';
        $customerPhoneInput = $_POST['customer_phone'] ?? '';
        $customerDobInput = $_POST['customer_dob'] ?? '';
        if (!is_string($customerNameInput) || !is_string($customerPhoneInput) || !is_string($customerDobInput)) {
            throw new Exception('Enter valid customer details.');
        }
        $customerNameInput = sanitize($customerNameInput);
        $customerPhoneInput = sanitize($customerPhoneInput);
        $customerDobInput = sanitize($customerDobInput);
        $customerId = !empty($order['customer_id']) && (int)$order['customer_id'] > 1
            ? (int)$order['customer_id']
            : null;
        if ($customerId === null && ($customerNameInput !== '' || $customerPhoneInput !== '' || $customerDobInput !== '')) {
            if ($customerNameInput === '' || $customerPhoneInput === '') {
                throw new Exception('Enter both the customer name and phone number.');
            }
            if (strlen($customerNameInput) > 400 || strlen($customerPhoneInput) > 25) {
                throw new Exception('Customer name or phone number is too long.');
            }
            if ($customerDobInput !== '') {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $customerDobInput)) {
                    throw new Exception('Enter a valid date of birth.');
                }
                [$dobYear, $dobMonth, $dobDay] = array_map('intval', explode('-', $customerDobInput));
                if (!checkdate($dobMonth, $dobDay, $dobYear)) {
                    throw new Exception('Enter a valid date of birth.');
                }
            }

            $customer = $db->fetchOne(
                "SELECT id, dob FROM customers WHERE phone = :phone",
                [':phone' => $customerPhoneInput]
            );
            if ($customer && (int)$customer['id'] === 1) {
                throw new Exception('That phone number belongs to the walk-in customer.');
            }
            if (!$customer) {
                $db->query(
                    "INSERT INTO customers (name, phone, dob) VALUES (:name, :phone, :dob)",
                    [':name' => $customerNameInput, ':phone' => $customerPhoneInput, ':dob' => $customerDobInput !== '' ? $customerDobInput : null]
                );
                $customerId = (int)$db->lastInsertId();
            } else {
                $customerId = (int)$customer['id'];
                if ($customerDobInput !== '' && empty($customer['dob'])) {
                    $db->query(
                        "UPDATE customers SET dob = :dob WHERE id = :customer_id",
                        [':dob' => $customerDobInput, ':customer_id' => $customerId]
                    );
                }
            }
            $db->query(
                "UPDATE orders SET customer_id = :customer_id WHERE id = :id",
                [':customer_id' => $customerId, ':id' => $orderId]
            );
        }

        $grandTotal = (float)$order['grand_total'];
        if ($paymentMethod === 'cash' && $paidAmount < $grandTotal) {
            throw new Exception('Cash received must cover the full bill amount.');
        }
        if ($paymentMethod !== 'cash' && abs($paidAmount - $grandTotal) > 0.009) {
            throw new Exception('For card or bank transfer, enter the exact bill amount.');
        }

        $changeAmount = $paymentMethod === 'cash' ? max(0, $paidAmount - $grandTotal) : 0;
        $db->query(
            "UPDATE orders SET payment_method = :payment_method, paid_amount = :paid_amount, change_amount = :change_amount, payment_status = 'paid' WHERE id = :id",
            [
                ':payment_method' => $paymentMethod,
                ':paid_amount' => $paidAmount,
                ':change_amount' => $changeAmount,
                ':id' => $orderId
            ]
        );

        $activeShift = getOpenCashRegister();
        if ($activeShift) {
            $column = $paymentMethod === 'card' ? 'total_card_sales' : ($paymentMethod === 'upi' ? 'total_upi_sales' : 'total_cash_sales');
            $db->query(
                "UPDATE cash_registers SET $column = $column + :amount WHERE id = :shift_id",
                [':amount' => $grandTotal, ':shift_id' => $activeShift['id']]
            );
        }

        if ($customerId !== null) {
            $db->query(
                "UPDATE customers SET total_spent = total_spent + :spent, loyalty_points = ROUND(total_spent / 1000, 2) WHERE id = :customer_id",
                [':spent' => $grandTotal, ':customer_id' => $customerId]
            );
        }

        $db->commit();
        header("Location: " . BASE_URL . "/print_receipt.php?id=" . $orderId . "&copy=main&auto_print=1&refresh_orders=1");
        exit;
    } catch (Exception $e) {
        if ($db->getConnection()->inTransaction()) $db->rollBack();
        setFlash('error', $e->getMessage());
        header("Location: " . BASE_URL . "/orders.php" . $statusRedirect);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole([ROLE_ADMIN, ROLE_MANAGER]);
    $action = $_POST['action'] ?? '';

    if ($action === 'refund_order') {
        if (!hasRole(ROLE_ADMIN)) {
            setFlash('error', 'Only Admin can refund orders.');
            header("Location: " . BASE_URL . "/orders.php" . $statusRedirect);
            exit;
        }
        $orderId = (int)$_POST['order_id'];
        $reason = sanitize($_POST['refund_reason'] ?? 'Refunded by manager');
        $db = db();

        try {
            $db->beginTransaction();
            $order = $db->fetchOne("SELECT * FROM orders WHERE id = :id FOR UPDATE", [':id' => $orderId]);
            if (!$order) throw new Exception('Order not found.');
            if ($order['payment_status'] === 'refunded' || $order['order_status'] === 'cancelled') {
                throw new Exception('This order has already been cancelled or refunded.');
            }

            $items = $db->fetchAll("SELECT * FROM order_items WHERE order_id = :id", [':id' => $orderId]);
            foreach ($items as $item) {
                $db->query("UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :pid AND track_stock = 1", [':qty' => $item['quantity'], ':pid' => $item['product_id']]);
            }

            if (!empty($order['customer_id']) && (int)$order['customer_id'] > 1) {
                $db->query("UPDATE customers SET total_spent = GREATEST(0, total_spent - :spent), loyalty_points = ROUND(GREATEST(0, total_spent - :spent_for_points) / 1000, 2) WHERE id = :customer_id", [':spent' => $order['grand_total'], ':spent_for_points' => $order['grand_total'], ':customer_id' => $order['customer_id']]);
            }

            if (!empty($order['table_id'])) {
                $db->query("UPDATE tables SET status = 'available', current_order_id = NULL WHERE id = :table_id", [':table_id' => $order['table_id']]);
            }

            $db->query("UPDATE orders SET payment_status = 'refunded', order_status = 'cancelled', notes = CONCAT(COALESCE(notes, ''), ' [Refunded: ', :reason, ']') WHERE id = :id", [':reason' => $reason, ':id' => $orderId]);

            $shift = getOpenCashRegister();
            if ($shift && (int)$shift['user_id'] === (int)$order['user_id'] && strtotime($order['created_at']) >= strtotime($shift['opening_time'])) {
                $column = $order['payment_method'] === 'card' ? 'total_card_sales' : ($order['payment_method'] === 'upi' ? 'total_upi_sales' : 'total_cash_sales');
                $db->query("UPDATE cash_registers SET $column = GREATEST(0, $column - :amount) WHERE id = :shift_id", [':amount' => $order['grand_total'], ':shift_id' => $shift['id']]);
            }

            $db->commit();
            setFlash('success', "Order #{$order['invoice_no']} refunded successfully and stock restored.");
        } catch (Exception $e) {
            if ($db->getConnection()->inTransaction()) $db->rollBack();
            setFlash('error', $e->getMessage());
        }
        header("Location: " . BASE_URL . "/orders.php" . $statusRedirect);
        exit;
    }

    if ($action === 'cancel_order') {
        $orderId = (int)$_POST['order_id'];
        $reason = sanitize($_POST['cancel_reason'] ?? 'Voided by manager');

        $order = db()->fetchOne("SELECT * FROM orders WHERE id = :id", [':id' => $orderId]);
        if ($order) {
            if ($order['payment_status'] === 'paid' && !hasRole(ROLE_ADMIN)) {
                setFlash('error', 'Only Admin can void or refund a paid order.');
                header("Location: " . BASE_URL . "/orders.php" . $statusRedirect);
                exit;
            }
            // Restore inventory
            $items = db()->fetchAll("SELECT * FROM order_items WHERE order_id = :id", [':id' => $orderId]);
            foreach ($items as $it) {
                db()->query(
                    "UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :pid AND track_stock = 1",
                    [':qty' => $it['quantity'], ':pid' => $it['product_id']]
                );
            }

            if (!empty($order['customer_id']) && (int)$order['customer_id'] > 1) {
                db()->query("UPDATE customers SET total_spent = GREATEST(0, total_spent - :spent), loyalty_points = ROUND(GREATEST(0, total_spent - :spent_for_points) / 1000, 2) WHERE id = :customer_id", [':spent' => $order['grand_total'], ':spent_for_points' => $order['grand_total'], ':customer_id' => $order['customer_id']]);
            }

            // Free table
            if (!empty($order['table_id'])) {
                db()->query("UPDATE tables SET status = 'available', current_order_id = NULL WHERE id = :tid", [':tid' => $order['table_id']]);
            }

            // Mark cancelled
            db()->query(
                "UPDATE orders SET order_status = 'cancelled', payment_status = 'refunded', notes = CONCAT(COALESCE(notes,''), ' [Cancelled: ', :reason, ']') WHERE id = :id",
                [':reason' => $reason, ':id' => $orderId]
            );

            setFlash('success', "Order #{$order['invoice_no']} has been cancelled and stock restored.");
        }
        header("Location: " . BASE_URL . "/orders.php" . $statusRedirect);
        exit;
    }
}

// Filter query
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) ? $startDate : date('Y-m-d');
$endDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) ? $endDate : date('Y-m-d');
if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}
$typeFilter = $_GET['type'] ?? 'all';
$payFilter  = $_GET['payment'] ?? 'all';
$userFilter = (int)($_GET['user_id'] ?? 0);
$search     = trim($_GET['q'] ?? '');
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$users = [];
if (!$isCashier) {
    $users = db()->fetchAll("SELECT id, name, role FROM users ORDER BY name ASC");
}

$sql = "SELECT o.*, t.name as table_name, u.name as cashier_name, c.name as customer_name 
        FROM orders o 
        LEFT JOIN tables t ON o.table_id = t.id 
        LEFT JOIN users u ON o.user_id = u.id 
        LEFT JOIN customers c ON o.customer_id = c.id 
        WHERE 1=1";
$params = [];

if ($isCashier) {
    $sql .= " AND o.user_id = :user_id";
    $params[':user_id'] = $currentUser['id'];
} elseif ($userFilter > 0) {
    $sql .= " AND o.user_id = :user_filter";
    $params[':user_filter'] = $userFilter;
}

$sql .= " AND DATE(o.created_at) BETWEEN :start_date AND :end_date";
$params[':start_date'] = $startDate;
$params[':end_date'] = $endDate;
if ($typeFilter !== 'all') {
    $sql .= " AND o.order_type = :t";
    $params[':t'] = $typeFilter;
}
if ($payFilter !== 'all') {
    $sql .= " AND o.payment_method = :p";
    $params[':p'] = $payFilter;
}
if ($statusFilter !== 'all') {
    $sql .= " AND o.order_status = :status";
    $params[':status'] = $statusFilter;
}
if (!empty($search)) {
    $sql .= " AND (o.invoice_no LIKE :order_invoice OR c.name LIKE :order_customer OR c.phone LIKE :order_phone)";
    $params[':order_invoice'] = "%$search%";
    $params[':order_customer'] = "%$search%";
    $params[':order_phone'] = "%$search%";
}

$countRow = db()->fetchOne("SELECT COUNT(*) AS total FROM ($sql) filtered_orders", $params);
$totalOrders = (int)($countRow['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalOrders / $perPage));
$page = min($page, $totalPages);
$sql .= " ORDER BY o.id DESC LIMIT " . (($page - 1) * $perPage) . ", " . $perPage;
$orders = db()->fetchAll($sql, $params);

// Summary uses the same filters as the orders table.
$summarySql = "SELECT COUNT(o.id) as total_count,
                      COALESCE(SUM(o.subtotal), 0) as total_sales,
                      COALESCE(SUM(o.discount_amount), 0) as total_discount
               FROM orders o
               LEFT JOIN customers c ON o.customer_id = c.id
               WHERE DATE(o.created_at) BETWEEN :summary_start_date AND :summary_end_date
                 AND o.payment_status = 'paid' AND o.order_status != 'cancelled'";
$summaryParams = [':summary_start_date' => $startDate, ':summary_end_date' => $endDate];
if ($isCashier) {
    $summarySql .= " AND o.user_id = :summary_user_id";
    $summaryParams[':summary_user_id'] = $currentUser['id'];
} elseif ($userFilter > 0) {
    $summarySql .= " AND o.user_id = :summary_user_filter";
    $summaryParams[':summary_user_filter'] = $userFilter;
}
if ($typeFilter !== 'all') {
    $summarySql .= " AND o.order_type = :summary_type";
    $summaryParams[':summary_type'] = $typeFilter;
}
if ($payFilter !== 'all') {
    $summarySql .= " AND o.payment_method = :summary_payment";
    $summaryParams[':summary_payment'] = $payFilter;
}
if ($statusFilter !== 'all') {
    $summarySql .= " AND o.order_status = :summary_status";
    $summaryParams[':summary_status'] = $statusFilter;
}
if (!empty($search)) {
    $summarySql .= " AND (o.invoice_no LIKE :summary_invoice OR c.name LIKE :summary_customer OR c.phone LIKE :summary_phone)";
    $summaryParams[':summary_invoice'] = "%$search%";
    $summaryParams[':summary_customer'] = "%$search%";
    $summaryParams[':summary_phone'] = "%$search%";
}
$dailySummary = db()->fetchOne($summarySql, $summaryParams);

$netAmount = (float)($dailySummary['total_sales'] ?? 0) - (float)($dailySummary['total_discount'] ?? 0);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#0c0c0e]">
    <div class="max-w-7xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-white">Orders & Sales History</h2>
                <p class="text-xs text-stone-400">View transactions, reprint thermal slips, and view invoices</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?= BASE_URL ?>/pos.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-2xl text-xs shadow-lg shadow-red-900/40 transition">
                    <i class="fa-solid fa-plus"></i> New POS Order
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-stone-900 border border-stone-800 p-4 rounded-2xl shadow-lg">
            <form method="GET" action="orders.php" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3 text-xs">
                <div>
                    <label class="block font-bold text-stone-300 mb-1">From</label>
                    <input type="date" name="start_date" value="<?= e($startDate) ?>" class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-white">
                </div>
                <div>
                    <label class="block font-bold text-stone-300 mb-1">To</label>
                    <input type="date" name="end_date" value="<?= e($endDate) ?>" class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-white">
                </div>
                <div>
                    <label class="block font-bold text-stone-300 mb-1">Order Type</label>
                    <select name="type" class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-white">
                        <option value="all">All Types</option>
                        <option value="dine_in" <?= $typeFilter === 'dine_in' ? 'selected' : '' ?>>Dine-In</option>
                        <option value="takeaway" <?= $typeFilter === 'takeaway' ? 'selected' : '' ?>>Takeaway</option>
                        <option value="delivery" <?= $typeFilter === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-stone-300 mb-1">Payment</label>
                    <select name="payment" class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-white">
                        <option value="all">All Payments</option>
                        <option value="cash" <?= $payFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="card" <?= $payFilter === 'card' ? 'selected' : '' ?>>Card / POS</option>
                        <option value="upi" <?= $payFilter === 'upi' ? 'selected' : '' ?>>Bank Transfer</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-stone-300 mb-1">Order Status</label>
                    <select name="status" class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-white">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <?php if (!$isCashier): ?>
                <div>
                    <label class="block font-bold text-stone-300 mb-1">User</label>
                    <select name="user_id" class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-white">
                        <option value="0">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= (int)$user['id'] ?>" <?= $userFilter === (int)$user['id'] ? 'selected' : '' ?>>
                                <?= e($user['name']) ?> (<?= e(ucfirst($user['role'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <label class="block font-bold text-stone-300 mb-1">Search Invoice/Customer</label>
                    <input type="text" name="q" value="<?= e($search) ?>" placeholder="e.g. HOC-..." class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-white">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl shadow-md transition">
                        <i class="fa-solid fa-filter mr-1"></i> Filter
                    </button>
                    <?php if ($search !== '' || $typeFilter !== 'all' || $payFilter !== 'all' || $statusFilter !== 'all' || $userFilter > 0 || $startDate !== date('Y-m-d') || $endDate !== date('Y-m-d')): ?>
                        <a href="<?= BASE_URL ?>/orders.php" class="w-full py-2 bg-stone-800 hover:bg-stone-700 text-stone-300 font-bold rounded-xl text-center transition">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- KPI Strip -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-stone-900 border border-stone-800 p-4 rounded-2xl">
                <span class="text-[10px] font-bold text-stone-400 uppercase">Total Sales</span>
                <div class="text-lg font-black text-red-400 mt-0.5"><?= $currency ?> <?= number_format($dailySummary['total_sales'] ?? 0, 2) ?></div>
            </div>
            <div class="bg-stone-900 border border-stone-800 p-4 rounded-2xl">
                <span class="text-[10px] font-bold text-stone-400 uppercase">Total Orders</span>
                <div class="text-lg font-black text-white mt-0.5"><?= (int)($dailySummary['total_count'] ?? 0) ?></div>
            </div>
            <div class="bg-stone-900 border border-stone-800 p-4 rounded-2xl">
                <span class="text-[10px] font-bold text-stone-400 uppercase">Discounts Given</span>
                <div class="text-lg font-black text-rose-400 mt-0.5">-<?= $currency ?> <?= number_format($dailySummary['total_discount'] ?? 0, 2) ?></div>
            </div>
            <div class="bg-stone-900 border border-stone-800 p-4 rounded-2xl">
                <span class="text-[10px] font-bold text-stone-400 uppercase">Net Amount</span>
                <div class="text-lg font-black text-white mt-0.5"><?= $currency ?> <?= number_format($netAmount, 2) ?></div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="bg-stone-900 border border-stone-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-stone-950 text-stone-400 font-bold uppercase text-[10px] tracking-wider border-b border-stone-800">
                        <tr>
                            <th class="p-3.5">Invoice & Time</th>
                            <th class="p-3.5">Type & Table</th>
                            <th class="p-3.5">Customer</th>
                            <th class="p-3.5">Cashier</th>
                            <th class="p-3.5">Payment</th>
                            <th class="p-3.5">Status</th>
                            <th class="p-3.5 text-right">Grand Total</th>
                            <th class="p-3.5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800">
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="p-8 text-center text-stone-500 font-medium">No orders found matching the filter criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $o): ?>
                            <?php
                            $isWalkInCustomer = (int)($o['customer_id'] ?? 0) === 1
                                && strcasecmp(trim((string)($o['customer_name'] ?? '')), 'Walk-in Customer') === 0;
                            $orderHasCustomer = !empty($o['customer_id']) && !$isWalkInCustomer;
                            ?>
                            <tr class="hover:bg-stone-950/80 transition">
                                <td class="p-3.5">
                                    <span class="font-bold text-white"><?= e($o['invoice_no']) ?></span>
                                    <span class="block text-[10px] text-stone-500"><?= date('M d, Y h:i A', strtotime($o['created_at'])) ?></span>
                                </td>
                                <td class="p-3.5">
                                    <span class="font-bold uppercase text-[11px] text-stone-300"><?= e(str_replace('_', ' ', $o['order_type'])) ?></span>
                                    <?php if (!empty($o['table_name'])): ?>
                                        <span class="block text-[10px] text-red-400 font-medium"><i class="fa-solid fa-chair mr-1"></i><?= e($o['table_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5 text-stone-300"><?= e($o['customer_name'] ?? 'Walk-in') ?></td>
                                <td class="p-3.5 text-stone-400"><?= e($o['cashier_name'] ?? 'Staff') ?></td>
                                <td class="p-3.5">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase <?= $o['payment_status'] === 'paid' ? 'bg-stone-800 text-stone-300 border border-stone-700' : 'bg-amber-950 text-amber-300 border border-amber-800' ?>"><?= $o['payment_status'] === 'paid' ? e(getPaymentMethodLabel($o['payment_method'])) : 'unpaid' ?></span>
                                </td>
                                <td class="p-3.5">
                                    <?php
                                    $stClass = 'bg-stone-800 text-stone-300';
                                    if ($o['order_status'] === 'preparing') $stClass = 'bg-amber-950 text-amber-300 border border-amber-800';
                                    if ($o['order_status'] === 'ready') $stClass = 'bg-emerald-950 text-emerald-300 border border-emerald-800';
                                    if ($o['order_status'] === 'completed') $stClass = 'bg-emerald-900/60 text-emerald-300 border border-emerald-700';
                                    if ($o['order_status'] === 'cancelled') $stClass = 'bg-rose-950 text-rose-300 border border-rose-800';
                                    ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase <?= $stClass ?>"><?= e($o['order_status']) ?></span>
                                </td>
                                <td class="p-3.5 text-right font-black text-red-400 text-sm"><?= $currency ?> <?= number_format($o['grand_total'], 2) ?></td>
                                <td class="p-3.5 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <a href="<?= BASE_URL ?>/order_view.php?id=<?= $o['id'] ?><?= $statusFilter !== 'all' ? '&amp;status=' . rawurlencode($statusFilter) : '' ?>" class="p-1.5 hover:bg-stone-800 rounded-lg text-stone-300 transition" title="View Full Invoice">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>/print_receipt.php?id=<?= $o['id'] ?>&amp;copy=<?= $o['payment_status'] === 'paid' ? 'main' : 'kitchen' ?>" target="_blank" class="p-1.5 hover:bg-stone-800 rounded-lg text-red-400 transition" title="<?= $o['payment_status'] === 'paid' ? 'Reprint Customer Bill' : 'Reprint Kitchen Ticket' ?>">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                        <?php if ($o['payment_status'] === 'unpaid' && $o['order_status'] !== 'cancelled'): ?>
                                            <button type="button" onclick="openOrderPaymentModal(this)" data-order-id="<?= (int)$o['id'] ?>" data-invoice-no="<?= e($o['invoice_no']) ?>" data-grand-total="<?= e(number_format((float)$o['grand_total'], 2, '.', '')) ?>" data-has-customer="<?= $orderHasCustomer ? 'true' : 'false' ?>" class="inline-flex items-center gap-1 rounded-lg p-1.5 text-[10px] font-bold text-emerald-400 transition hover:bg-emerald-950/60" title="Pay Later / Collect Payment" aria-label="Pay later for invoice <?= e($o['invoice_no']) ?>">
                                                <i class="fa-solid fa-money-bill-wave"></i>
                                                <span>Pay Later</span>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($o['payment_status'] === 'paid' && $o['order_status'] !== 'cancelled' && hasRole(ROLE_ADMIN)): ?>
                                            <button onclick="promptRefundOrder(<?= $o['id'] ?>, '<?= addslashes($o['invoice_no']) ?>', '<?= number_format($o['grand_total'], 2, '.', '') ?>')" class="p-1.5 hover:bg-amber-950/60 text-amber-400 rounded-lg transition" title="Refund Order">
                                                <i class="fa-solid fa-money-bill-transfer"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($o['order_status'] !== 'cancelled' && hasRole([ROLE_ADMIN, ROLE_MANAGER]) && ($o['payment_status'] !== 'paid' || hasRole(ROLE_ADMIN))): ?>
                                            <button onclick="promptVoidOrder(<?= $o['id'] ?>, '<?= addslashes($o['invoice_no']) ?>')" class="p-1.5 hover:bg-rose-950/60 text-rose-400 rounded-lg transition" title="Void / Cancel">
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= renderPagination($page, $totalOrders, $perPage, ['start_date' => $startDate, 'end_date' => $endDate, 'type' => $typeFilter, 'payment' => $payFilter, 'status' => $statusFilter, 'user_id' => $userFilter, 'q' => $search], true) ?>
        </div>

    </div>
</main>

<div id="order-payment-modal" class="modal-overlay fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4 backdrop-blur-xs">
    <div class="flex max-h-[90vh] w-full max-w-lg flex-col rounded-3xl border border-stone-800 bg-stone-900 p-6 shadow-2xl">
        <div class="flex items-center justify-between border-b border-stone-800 pb-3">
            <div>
                <h3 class="text-lg font-extrabold text-white">Payment Checkout</h3>
                <p class="text-xs text-stone-400">Invoice <span id="order-payment-invoice" class="font-bold text-stone-200"></span></p>
            </div>
            <button type="button" onclick="closeModal('order-payment-modal')" class="p-2 text-stone-400 hover:text-white" aria-label="Close payment dialog">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form id="order-payment-form" method="POST" action="orders.php" target="_blank" class="flex-1 overflow-y-auto">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
            <input type="hidden" name="order_id" id="order-payment-id">
            <input type="hidden" name="payment_method" id="order-payment-method" value="cash">
            <div id="order-payment-customer-fields" class="mt-4 hidden rounded-2xl border border-stone-700 bg-stone-950/60 p-4">
                <p class="mb-3 text-xs font-bold text-stone-200">Customer details <span class="font-normal text-stone-500">(Optional)</span></p>
                <div class="grid grid-cols-1 gap-3">
                    <div>
                        <label for="order-payment-customer-name" class="mb-1 block text-[11px] font-bold text-stone-400">Full Name</label>
                        <input type="text" name="customer_name" id="order-payment-customer-name" maxlength="100" autocomplete="name" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3 py-2.5 text-sm text-white focus:border-red-600 focus:outline-none">
                    </div>
                    <div>
                        <label for="order-payment-customer-phone" class="mb-1 block text-[11px] font-bold text-stone-400">Phone Number</label>
                        <input type="tel" name="customer_phone" id="order-payment-customer-phone" maxlength="25" autocomplete="tel" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3 py-2.5 text-sm text-white focus:border-red-600 focus:outline-none">
                    </div>
                    <div>
                        <label for="order-payment-customer-dob" class="mb-1 block text-[11px] font-bold text-stone-400">Date of Birth <span class="font-normal text-stone-500">(Optional)</span></label>
                        <input type="date" name="customer_dob" id="order-payment-customer-dob" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3 py-2.5 text-sm text-white focus:border-red-600 focus:outline-none">
                    </div>
                </div>
                <p class="mt-2 text-[10px] text-stone-500">Leave blank to continue without registering a customer. If this phone number is already registered, the existing customer will be linked to the order.</p>
            </div>
            <div class="mt-4 rounded-2xl border border-red-900/60 bg-gradient-to-r from-red-950 via-stone-900 to-black p-4 text-center text-white shadow-lg">
                <span class="text-xs font-bold uppercase tracking-widest text-red-400">Total Payable</span>
                <h2 class="mt-1 text-3xl font-black"><?= e($currency) ?> <span id="order-payment-total">0.00</span></h2>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-2">
                <button type="button" onclick="setGridPaymentMethod('cash')" data-payment-method="cash" class="grid-payment-method rounded-2xl border-2 border-red-600 bg-red-950/60 py-3 text-xs font-bold text-white transition">
                    <i class="fa-solid fa-money-bill-wave mb-1 block text-lg text-red-400"></i>Cash
                </button>
                <button type="button" onclick="setGridPaymentMethod('card')" data-payment-method="card" class="grid-payment-method rounded-2xl border-2 border-stone-800 bg-stone-800 py-3 text-xs font-bold text-stone-300 transition hover:border-red-600">
                    <i class="fa-solid fa-credit-card mb-1 block text-lg"></i>Card / POS
                </button>
                <button type="button" onclick="setGridPaymentMethod('upi')" data-payment-method="upi" class="grid-payment-method rounded-2xl border-2 border-stone-800 bg-stone-800 py-3 text-xs font-bold text-stone-300 transition hover:border-red-600">
                    <i class="fa-solid fa-building-columns mb-1 block text-lg"></i>Bank Transfer
                </button>
            </div>
            <label id="order-payment-amount-label" for="order-payment-amount" class="mb-1 mt-4 block text-xs font-bold text-stone-300">Cash Given (<?= e($currency) ?>)</label>
            <input type="number" name="paid_amount" id="order-payment-amount" min="0.01" step="0.01" inputmode="decimal" required oninput="updateGridPaymentChange()" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-4 py-3 text-xl font-black text-white focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600">
            <div id="grid-payment-change-box" class="mt-3 flex items-center justify-between rounded-2xl border border-emerald-800 bg-emerald-950/60 p-3.5">
                <span id="grid-payment-change-label" class="text-xs font-bold text-stone-300">Change to Return:</span>
                <span id="grid-payment-change" class="text-2xl font-black text-emerald-400"><?= e($currency) ?> 0.00</span>
            </div>
            <p id="grid-payment-help" class="mt-2 text-center text-[11px] text-stone-500">Enter the cash received. Any extra amount is calculated as change.</p>
            <div class="mt-4 flex gap-2 border-t border-stone-800 pt-3">
                <button type="button" onclick="closeModal('order-payment-modal')" class="flex-1 rounded-xl bg-stone-800 py-3.5 text-xs font-bold text-stone-300 transition hover:bg-stone-700">Cancel</button>
                <button type="submit" class="flex-1 rounded-xl bg-red-600 py-3.5 text-sm font-black text-white shadow-xl shadow-red-900/50 transition hover:bg-red-500">
                    <i class="fa-solid fa-check mr-2"></i>Record Payment &amp; Print
                </button>
            </div>
        </form>
    </div>
</div>

<form id="void-order-form" method="POST" action="orders.php" class="hidden">
    <input type="hidden" name="action" value="cancel_order">
    <input type="hidden" name="order_id" id="void_order_id">
    <input type="hidden" name="cancel_reason" id="void_cancel_reason">
    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
</form>

<form id="refund-order-form" method="POST" action="orders.php" class="hidden">
    <input type="hidden" name="action" value="refund_order">
    <input type="hidden" name="order_id" id="refund_order_id">
    <input type="hidden" name="refund_reason" id="refund_reason">
    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
</form>

<script>
let activeGridPaymentTotal = 0;
const gridPaymentCurrency = <?= json_encode($currency) ?>;

function openOrderPaymentModal(button) {
    const form = document.getElementById('order-payment-form');
    form.reset();
    activeGridPaymentTotal = Number(button.dataset.grandTotal) || 0;
    document.getElementById('order-payment-id').value = button.dataset.orderId;
    document.getElementById('order-payment-invoice').textContent = button.dataset.invoiceNo;
    document.getElementById('order-payment-total').textContent = activeGridPaymentTotal.toFixed(2);
    const hasCustomer = button.dataset.hasCustomer === 'true';
    document.getElementById('order-payment-customer-fields').classList.toggle('hidden', hasCustomer);
    document.getElementById('order-payment-amount').value = activeGridPaymentTotal.toFixed(2);
    setGridPaymentMethod('cash');
    openModal('order-payment-modal');
}

function setGridPaymentMethod(method) {
    document.getElementById('order-payment-method').value = method;
    const amountInput = document.getElementById('order-payment-amount');
    amountInput.min = activeGridPaymentTotal.toFixed(2);
    if (method === 'cash') {
        amountInput.removeAttribute('max');
    } else {
        amountInput.max = activeGridPaymentTotal.toFixed(2);
        amountInput.value = activeGridPaymentTotal.toFixed(2);
    }
    document.querySelectorAll('.grid-payment-method').forEach(button => {
        const active = button.dataset.paymentMethod === method;
        button.className = `grid-payment-method rounded-2xl border-2 ${active ? 'border-red-600 bg-red-950/60 text-white' : 'border-stone-800 bg-stone-800 text-stone-300 hover:border-red-600'} py-3 text-xs font-bold transition`;
    });
    document.getElementById('order-payment-amount-label').textContent =
        method === 'cash' ? `Cash Given (<?= e($currency) ?>)` : `Amount Charged (<?= e($currency) ?>)`;
    document.getElementById('grid-payment-help').textContent = method === 'cash'
        ? 'Enter the cash received. Any extra amount is calculated as change.'
        : 'Enter the amount charged to the card or bank transfer. It must match the bill total.';
    updateGridPaymentChange();
}

function updateGridPaymentChange() {
    const method = document.getElementById('order-payment-method').value;
    const received = Number(document.getElementById('order-payment-amount').value) || 0;
    const changeBox = document.getElementById('grid-payment-change-box');
    const changeLabel = document.getElementById('grid-payment-change-label');
    const changeAmount = document.getElementById('grid-payment-change');

    if (method === 'cash' && received >= activeGridPaymentTotal) {
        changeBox.className = 'mt-3 flex items-center justify-between rounded-2xl border border-emerald-800 bg-emerald-950/60 p-3.5';
        changeLabel.textContent = 'Change to Return:';
        changeAmount.className = 'text-2xl font-black text-emerald-400';
        changeAmount.textContent = `${gridPaymentCurrency} ${(received - activeGridPaymentTotal).toFixed(2)}`;
    } else if (method === 'cash') {
        changeBox.className = 'mt-3 flex items-center justify-between rounded-2xl border border-rose-800 bg-rose-950/60 p-3.5';
        changeLabel.textContent = 'Amount Still Due:';
        changeAmount.className = 'text-2xl font-black text-rose-400';
        changeAmount.textContent = `${gridPaymentCurrency} ${Math.max(0, activeGridPaymentTotal - received).toFixed(2)}`;
    } else {
        changeBox.className = 'mt-3 flex items-center justify-between rounded-2xl border border-stone-700 bg-stone-800 p-3.5';
        changeLabel.textContent = 'Bill Total:';
        changeAmount.className = 'text-2xl font-black text-white';
        changeAmount.textContent = `${gridPaymentCurrency} ${activeGridPaymentTotal.toFixed(2)}`;
    }
}

async function promptVoidOrder(id, inv) {
  const { value: reason } = await Swal.fire({
    title: `Void Order #${inv}?`,
    input: 'text',
    inputLabel: 'Reason for cancellation',
    inputPlaceholder: 'e.g. Wrong items entered / Customer request',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Yes, Cancel Order'
  });

  if (reason) {
    document.getElementById('void_order_id').value = id;
    document.getElementById('void_cancel_reason').value = reason;
    document.getElementById('void-order-form').submit();
  }
}

async function promptRefundOrder(id, inv, amount) {
    const { value: reason } = await Swal.fire({
        title: `Refund Order #${inv}?`,
        html: `Refund amount: <b><?= e($currency) ?> ${amount}</b>`,
        input: 'text',
        inputLabel: 'Reason for refund',
        inputPlaceholder: 'e.g. Customer request / Incorrect item',
        showCancelButton: true,
        confirmButtonColor: '#d97706',
        confirmButtonText: 'Refund Order',
        inputValidator: value => !value ? 'A refund reason is required.' : undefined
    });

    if (reason) {
        document.getElementById('refund_order_id').value = id;
        document.getElementById('refund_reason').value = reason;
        document.getElementById('refund-order-form').submit();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
