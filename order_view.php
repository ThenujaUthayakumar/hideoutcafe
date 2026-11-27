<?php
/**
 * The Hide Out Cafe - Printable A4 Tax Invoice / Order Detail
 */
require_once __DIR__ . '/config/functions.php';
requireAuth();

$orderId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$currentUser = currentUser();
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? 'Rs.';
$statusFilter = $_GET['status'] ?? 'all';
if (!in_array($statusFilter, ['all', 'pending', 'completed', 'cancelled'], true)) {
    $statusFilter = 'all';
}
$ordersUrl = BASE_URL . '/orders.php' . ($statusFilter !== 'all' ? '?status=' . rawurlencode($statusFilter) : '');
ensureOrderDiscountSchema();
$orderOwnerFilter = '';
$orderParams = [':id' => $orderId];

if (hasRole(ROLE_CASHIER)) {
    $orderOwnerFilter = ' AND o.user_id = :user_id';
    $orderParams[':user_id'] = $currentUser['id'];
}

$order = db()->fetchOne(
    "SELECT o.*, t.name as table_name, u.name as cashier_name, c.name as customer_name, c.phone as customer_phone, c.address as customer_address 
     FROM orders o 
     LEFT JOIN tables t ON o.table_id = t.id 
     LEFT JOIN users u ON o.user_id = u.id 
     LEFT JOIN customers c ON o.customer_id = c.id 
     WHERE o.id = :id$orderOwnerFilter",
    $orderParams
);

if (!$order) {
    die("Order not found.");
}

$paymentError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_payment') {
    $paymentMethod = $_POST['payment_method'] ?? '';
    $paidAmountInput = $_POST['paid_amount'] ?? '';
    $allowedPaymentMethods = ['cash', 'card', 'upi'];
    $db = db();

    try {
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

        $db->beginTransaction();
        $lockedOrder = $db->fetchOne(
            "SELECT * FROM orders o WHERE o.id = :id$orderOwnerFilter FOR UPDATE",
            $orderParams
        );
        if (!$lockedOrder) {
            throw new Exception('Order not found or access denied.');
        }
        if ($lockedOrder['payment_status'] !== 'unpaid' || $lockedOrder['order_status'] === 'cancelled') {
            throw new Exception('This order is not awaiting payment.');
        }

        $grandTotal = (float)$lockedOrder['grand_total'];
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

        if (!empty($lockedOrder['customer_id']) && (int)$lockedOrder['customer_id'] > 1) {
            $db->query(
                "UPDATE customers SET total_spent = total_spent + :spent, loyalty_points = ROUND(total_spent / 1000, 2) WHERE id = :customer_id",
                [':spent' => $grandTotal, ':customer_id' => $lockedOrder['customer_id']]
            );
        }

        $db->commit();
        header("Location: " . BASE_URL . "/print_receipt.php?id=" . $orderId . "&copy=main&auto_print=1");
        exit;
    } catch (Exception $e) {
        if ($db->getConnection()->inTransaction()) $db->rollBack();
        $paymentError = $e->getMessage();
    }
}

$items = db()->fetchAll("SELECT * FROM order_items WHERE order_id = :id", [':id' => $orderId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= e($order['invoice_no']) ?> - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
        }
    </style>
</head>
<body class="bg-stone-900 text-stone-800 p-4 sm:p-8 min-h-screen flex flex-col items-center justify-center">

    <!-- Action Toolbar -->
    <div class="max-w-3xl w-full flex items-center justify-between mb-4 no-print">
        <a href="<?= e($ordersUrl) ?>" class="px-4 py-2 bg-stone-800 text-stone-300 font-bold rounded-xl text-xs hover:bg-stone-700 transition">
            &larr; Back to Orders
        </a>
        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>/print_receipt.php?id=<?= $order['id'] ?>&amp;copy=<?= $order['payment_status'] === 'paid' ? 'main' : 'kitchen' ?>" target="_blank" class="px-4 py-2 bg-stone-800 hover:bg-stone-700 text-stone-200 font-bold rounded-xl text-xs transition">
                <i class="fa-solid fa-receipt mr-1"></i> <?= $order['payment_status'] === 'paid' ? 'Customer Bill' : 'Kitchen Ticket' ?>
            </a>
            <?php if ($order['payment_status'] === 'paid'): ?>
            <button onclick="window.print()" class="px-5 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs shadow-md transition">
                <i class="fa-solid fa-print mr-1"></i> Print A4 Invoice
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invoice Paper -->
    <div class="max-w-3xl w-full bg-white rounded-3xl p-8 sm:p-12 shadow-2xl border border-stone-200 text-stone-900">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 pb-8 border-b border-stone-200">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-red-600 text-white flex items-center justify-center text-xl shadow-lg mb-3">
                    <i class="fa-solid fa-mug-hot"></i>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-stone-900"><?= e($settings['cafe_name'] ?? 'The Hide Out Cafe') ?></h1>
                <p class="text-xs text-stone-500 font-medium"><?= e($settings['cafe_address'] ?? 'Colombo, Sri Lanka') ?></p>
                <p class="text-xs text-stone-500">Phone: <?= e($settings['cafe_phone'] ?? '') ?> | Email: <?= e($settings['cafe_email'] ?? '') ?></p>
            </div>
            <div class="sm:text-right">
                <span class="text-xs font-black uppercase tracking-widest text-red-600 bg-red-50 px-3 py-1 rounded-full border border-red-200">Tax Invoice</span>
                <h2 class="text-xl font-black text-stone-900 mt-2 font-mono"><?= e($order['invoice_no']) ?></h2>
                <p class="text-xs text-stone-500 mt-1">Date: <?= date('F j, Y, h:i A', strtotime($order['created_at'])) ?></p>
                <p class="text-xs text-stone-500">Cashier: <?= e($order['cashier_name'] ?? 'Staff') ?></p>
            </div>
        </div>

        <!-- Order & Customer Meta -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 py-6 border-b border-stone-100 text-xs">
            <div>
                <h4 class="font-black uppercase tracking-wider text-stone-400 mb-2">Billed To (Customer):</h4>
                <p class="font-bold text-stone-900 text-sm"><?= e($order['customer_name'] ?? 'Walk-in Customer') ?></p>
                <p class="text-stone-600"><?= e($order['customer_phone'] ?? '') ?></p>
                <p class="text-stone-600"><?= e($order['customer_address'] ?? '') ?></p>
            </div>
            <div class="sm:text-right">
                <h4 class="font-black uppercase tracking-wider text-stone-400 mb-2">Order Information:</h4>
                <p><span class="font-bold text-stone-700">Service Type:</span> <span class="font-black uppercase text-stone-900"><?= e(str_replace('_', ' ', $order['order_type'])) ?></span></p>
                <?php if (!empty($order['table_name'])): ?>
                    <p><span class="font-bold text-stone-700">Seating Table:</span> <span class="font-bold text-red-600"><?= e($order['table_name']) ?></span></p>
                <?php endif; ?>
                <p><span class="font-bold text-stone-700">Payment Status:</span> <span class="font-bold uppercase <?= $order['payment_status'] === 'paid' ? 'text-emerald-600' : 'text-amber-600' ?>"><?= e($order['payment_status']) ?></span></p>
                <p><span class="font-bold text-stone-700">Payment Mode:</span> <span class="font-bold uppercase text-stone-900"><?= $order['payment_status'] === 'paid' ? e(getPaymentMethodLabel($order['payment_method'])) : 'Pending' ?></span></p>
            </div>
        </div>

        <!-- Items Table -->
        <div class="py-6">
            <table class="w-full text-xs text-left">
                <thead class="bg-stone-100 text-stone-700 font-bold uppercase text-[10px] tracking-wider rounded-xl">
                    <tr>
                        <th class="p-3 rounded-l-xl">Item Details</th>
                        <th class="p-3 text-center">Qty</th>
                        <th class="p-3 text-right">Unit Price</th>
                        <th class="p-3 text-right rounded-r-xl">Total Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <?php foreach ($items as $it): ?>
                    <tr>
                        <td class="p-3.5">
                            <span class="font-bold text-stone-900 text-sm"><?= e($it['product_name']) ?></span>
                            <?php if (!empty($it['variant_name'])): ?>
                                <span class="block text-xs text-stone-500 font-semibold"><?= e($it['variant_name']) ?></span>
                            <?php endif; ?>
                            <?php if ((float)($it['promotion_discount'] ?? 0) > 0): ?>
                                <span class="block text-[11px] text-emerald-700">Promotion Discount: -<?= $currency ?> <?= number_format($it['promotion_discount'], 2) ?></span>
                            <?php endif; ?>
                            <?php if ((float)($it['normal_discount'] ?? 0) > 0): ?>
                                <span class="block text-[11px] text-stone-500">Normal Discount: -<?= $currency ?> <?= number_format($it['normal_discount'], 2) ?></span>
                            <?php endif; ?>
                            <?php
                            if (!empty($it['modifiers_json'])) {
                                $mods = json_decode($it['modifiers_json'], true);
                                if (!empty($mods)) {
                                    echo '<div class="text-[11px] text-red-700 font-medium mt-0.5">';
                                    foreach ($mods as $m) {
                                        echo '<span class="mr-2">+ ' . e($m['name']) . ' ' . number_format($m['price'], 2) . '</span>';
                                    }
                                    echo '</div>';
                                }
                            }
                            ?>
                        </td>
                        <td class="p-3.5 text-center font-bold text-stone-800"><?= (int)$it['quantity'] ?></td>
                        <td class="p-3.5 text-right font-medium text-stone-700"><?= $currency ?> <?= number_format($it['unit_price'], 2) ?></td>
                        <td class="p-3.5 text-right font-black text-stone-900"><?= $currency ?> <?= number_format($it['subtotal'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals & Notes -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-6 border-t border-stone-200 text-xs">
            <div>
                <h4 class="font-bold text-stone-700 mb-1">Customer & Barista Notes:</h4>
                <p class="text-stone-500 italic bg-stone-50 p-3 rounded-xl border border-stone-100">
                    <?= !empty($order['notes']) ? e($order['notes']) : 'No special notes recorded.' ?>
                </p>
                <div class="mt-4 text-[11px] text-stone-400">
                    <?= nl2br(e($settings['receipt_footer'] ?? '')) ?>
                </div>
            </div>

            <div class="space-y-2 bg-stone-50 p-4 rounded-2xl border border-stone-100">
                <div class="flex justify-between text-stone-600">
                    <span>Subtotal:</span>
                    <span class="font-bold text-stone-900"><?= $currency ?> <?= number_format($order['subtotal'], 2) ?></span>
                </div>
                <?php $promotionDiscount = (float)($order['promotion_discount'] ?? 0); $normalDiscount = (float)($order['normal_discount'] ?? max(0, (float)$order['discount_amount'] - $promotionDiscount)); ?>
                <?php if ($promotionDiscount > 0): ?>
                <div class="flex justify-between text-rose-600">
                    <span>Promotion Discount:</span>
                    <span class="font-bold">-<?= $currency ?> <?= number_format($promotionDiscount, 2) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($normalDiscount > 0): ?>
                <div class="flex justify-between text-rose-600">
                    <span>Normal Discount:</span>
                    <span class="font-bold">-<?= $currency ?> <?= number_format($normalDiscount, 2) ?></span>
                </div>
                <?php endif; ?>
                <?php if (($promotionDiscount + $normalDiscount) > 0): ?>
                <div class="flex justify-between text-rose-700 font-bold">
                    <span>Total Discount:</span>
                    <span>-<?= $currency ?> <?= number_format($promotionDiscount + $normalDiscount, 2) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['tax_amount'] > 0): ?>
                <div class="flex justify-between text-stone-600">
                    <span>Tax (<?= e($settings['tax_rate'] ?? '0') ?>%):</span>
                    <span class="font-bold text-stone-900"><?= $currency ?> <?= number_format($order['tax_amount'], 2) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['service_charge'] > 0): ?>
                <div class="flex justify-between text-stone-600">
                    <span>Service Charge (<?= e($settings['service_charge'] ?? '0') ?>%):</span>
                    <span class="font-bold text-stone-900"><?= $currency ?> <?= number_format($order['service_charge'], 2) ?></span>
                </div>
                <?php endif; ?>
                <div class="border-t border-stone-300 pt-2 flex justify-between items-center text-base font-black text-stone-900">
                    <span>Grand Total:</span>
                    <span class="text-xl text-red-600"><?= $currency ?> <?= number_format($order['grand_total'], 2) ?></span>
                </div>
                <div class="pt-2 text-[11px] text-stone-500 flex justify-between">
                    <span>Amount Paid: <?= $currency ?> <?= number_format($order['paid_amount'], 2) ?></span>
                    <span>Change: <?= $currency ?> <?= number_format($order['change_amount'], 2) ?></span>
                </div>
            </div>
        </div>

        <?php if ($order['payment_status'] === 'unpaid' && $order['order_status'] !== 'cancelled'): ?>
        <form method="POST" id="order-payment-form" class="mt-6 max-w-3xl w-full rounded-3xl border border-stone-700 bg-stone-900 p-6 shadow-2xl no-print">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="record_payment">
            <div class="flex items-center justify-between border-b border-stone-800 pb-3">
                <div>
                    <h3 class="text-lg font-extrabold text-white">Payment Checkout</h3>
                    <p class="text-xs text-stone-400">Select how the customer is paying and enter the amount received.</p>
                </div>
                <span class="rounded-full border border-amber-800 bg-amber-950 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-amber-300">Unpaid</span>
            </div>
            <div class="mt-4 rounded-2xl border border-red-900/60 bg-gradient-to-r from-red-950 via-stone-900 to-black p-4 text-center text-white shadow-lg">
                <span class="text-xs font-bold uppercase tracking-widest text-red-400">Total Payable</span>
                <h2 class="mt-1 text-3xl font-black"><?= e($currency) ?> <?= number_format((float)$order['grand_total'], 2) ?></h2>
            </div>
            <?php if ($paymentError): ?>
            <p class="mt-3 rounded-xl border border-rose-800 bg-rose-950/60 p-3 text-xs font-semibold text-rose-300"><?= e($paymentError) ?></p>
            <?php endif; ?>
            <input type="hidden" name="payment_method" id="order-payment-method" value="cash">
            <div class="mt-4 grid grid-cols-3 gap-2">
                <button type="button" onclick="setOrderPaymentMethod('cash')" data-payment-method="cash" class="order-payment-method rounded-2xl border-2 border-red-600 bg-red-950/60 py-3 text-xs font-bold text-white transition">
                    <i class="fa-solid fa-money-bill-wave mb-1 block text-lg text-red-400"></i>Cash
                </button>
                <button type="button" onclick="setOrderPaymentMethod('card')" data-payment-method="card" class="order-payment-method rounded-2xl border-2 border-stone-800 bg-stone-800 py-3 text-xs font-bold text-stone-300 transition hover:border-red-600">
                    <i class="fa-solid fa-credit-card mb-1 block text-lg"></i>Card / POS
                </button>
                <button type="button" onclick="setOrderPaymentMethod('upi')" data-payment-method="upi" class="order-payment-method rounded-2xl border-2 border-stone-800 bg-stone-800 py-3 text-xs font-bold text-stone-300 transition hover:border-red-600">
                    <i class="fa-solid fa-building-columns mb-1 block text-lg"></i>Bank Transfer
                </button>
            </div>
            <div class="mt-4">
                <label for="order-paid-amount" id="order-paid-amount-label" class="mb-1 block text-xs font-bold text-stone-300">Cash Given (<?= e($currency) ?>)</label>
                <input type="number" name="paid_amount" id="order-paid-amount" min="<?= e(number_format((float)$order['grand_total'], 2, '.', '')) ?>" step="0.01" inputmode="decimal" value="<?= e(number_format((float)$order['grand_total'], 2, '.', '')) ?>" required class="w-full rounded-xl border border-stone-700 bg-stone-800 px-4 py-3 text-xl font-black text-white focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600" oninput="updateOrderPaymentChange()">
            </div>
            <div id="order-payment-change-box" class="mt-3 flex items-center justify-between rounded-2xl border border-emerald-800 bg-emerald-950/60 p-3.5">
                <span id="order-payment-change-label" class="text-xs font-bold text-stone-300">Change to Return:</span>
                <span id="order-payment-change" class="text-2xl font-black text-emerald-400"><?= e($currency) ?> 0.00</span>
            </div>
            <div class="mt-4 flex gap-2 border-t border-stone-800 pt-3">
                <a href="<?= e($ordersUrl) ?>" class="flex-1 rounded-xl bg-stone-800 py-3.5 text-center text-xs font-bold text-stone-300 transition hover:bg-stone-700">Cancel</a>
                <button type="submit" class="flex-1 rounded-xl bg-red-600 py-3.5 text-sm font-black text-white shadow-xl shadow-red-900/50 transition hover:bg-red-500">
                    <i class="fa-solid fa-check mr-2"></i>Record Payment &amp; Print Bill
                </button>
            </div>
            <p id="order-payment-help" class="mt-2 text-center text-[11px] text-stone-500">Enter the cash received. Any extra amount is calculated as change.</p>
        </form>
        <?php endif; ?>

    </div>

<?php if ($order['payment_status'] === 'unpaid' && $order['order_status'] !== 'cancelled'): ?>
<script>
    const orderPaymentTotal = <?= json_encode((float)$order['grand_total']) ?>;
    const orderPaymentCurrency = <?= json_encode($currency) ?>;

    function setOrderPaymentMethod(method) {
        document.getElementById('order-payment-method').value = method;
        document.querySelectorAll('.order-payment-method').forEach(button => {
            const active = button.dataset.paymentMethod === method;
            button.className = `order-payment-method rounded-2xl border-2 ${active ? 'border-red-600 bg-red-950/60 text-white' : 'border-stone-800 bg-stone-800 text-stone-300 hover:border-red-600'} py-3 text-xs font-bold transition`;
        });
        document.getElementById('order-paid-amount-label').textContent =
            method === 'cash' ? `Cash Given (${orderPaymentCurrency})` : `Amount Charged (${orderPaymentCurrency})`;
        document.getElementById('order-payment-help').textContent = method === 'cash'
            ? 'Enter the cash received. Any extra amount is calculated as change.'
            : 'Enter the amount charged to the card or bank transfer. It must match the bill total.';
        updateOrderPaymentChange();
    }

    function updateOrderPaymentChange() {
        const method = document.getElementById('order-payment-method').value;
        const received = Number(document.getElementById('order-paid-amount').value) || 0;
        const changeBox = document.getElementById('order-payment-change-box');
        const changeLabel = document.getElementById('order-payment-change-label');
        const changeAmount = document.getElementById('order-payment-change');

        if (method === 'cash' && received >= orderPaymentTotal) {
            changeBox.className = 'mt-3 flex items-center justify-between rounded-2xl border border-emerald-800 bg-emerald-950/60 p-3.5';
            changeLabel.textContent = 'Change to Return:';
            changeAmount.className = 'text-2xl font-black text-emerald-400';
            changeAmount.textContent = `${orderPaymentCurrency} ${(received - orderPaymentTotal).toFixed(2)}`;
        } else if (method === 'cash') {
            changeBox.className = 'mt-3 flex items-center justify-between rounded-2xl border border-rose-800 bg-rose-950/60 p-3.5';
            changeLabel.textContent = 'Amount Still Due:';
            changeAmount.className = 'text-2xl font-black text-rose-400';
            changeAmount.textContent = `${orderPaymentCurrency} ${Math.max(0, orderPaymentTotal - received).toFixed(2)}`;
        } else {
            changeBox.className = 'mt-3 flex items-center justify-between rounded-2xl border border-stone-700 bg-stone-800 p-3.5';
            changeLabel.textContent = 'Bill Total:';
            changeAmount.className = 'text-2xl font-black text-white';
            changeAmount.textContent = `${orderPaymentCurrency} ${orderPaymentTotal.toFixed(2)}`;
        }
    }
</script>
<?php endif; ?>
</body>
</html>
