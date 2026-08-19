<?php
/**
 * The Hide Out Cafe - Printable A4 Tax Invoice / Order Detail
 */
require_once __DIR__ . '/config/functions.php';
requireAuth();

$orderId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? 'Rs.';

$order = db()->fetchOne(
    "SELECT o.*, t.name as table_name, u.name as cashier_name, c.name as customer_name, c.phone as customer_phone, c.address as customer_address 
     FROM orders o 
     LEFT JOIN tables t ON o.table_id = t.id 
     LEFT JOIN users u ON o.user_id = u.id 
     LEFT JOIN customers c ON o.customer_id = c.id 
     WHERE o.id = :id",
    [':id' => $orderId]
);

if (!$order) {
    die("Order not found.");
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
        <a href="<?= BASE_URL ?>/orders.php" class="px-4 py-2 bg-stone-800 text-stone-300 font-bold rounded-xl text-xs hover:bg-stone-700 transition">
            &larr; Back to Orders
        </a>
        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>/print_receipt.php?id=<?= $order['id'] ?>" target="_blank" class="px-4 py-2 bg-stone-800 hover:bg-stone-700 text-stone-200 font-bold rounded-xl text-xs transition">
                <i class="fa-solid fa-receipt mr-1"></i> Thermal Slip
            </a>
            <button onclick="window.print()" class="px-5 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs shadow-md transition">
                <i class="fa-solid fa-print mr-1"></i> Print A4 Invoice
            </button>
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
                <p><span class="font-bold text-stone-700">Payment Status:</span> <span class="font-bold uppercase text-emerald-600"><?= e($order['payment_status']) ?></span></p>
                <p><span class="font-bold text-stone-700">Payment Mode:</span> <span class="font-bold uppercase text-stone-900"><?= e($order['payment_method']) ?></span></p>
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
                            <?php
                            if (!empty($it['modifiers_json'])) {
                                $mods = json_decode($it['modifiers_json'], true);
                                if (!empty($mods)) {
                                    echo '<div class="text-[11px] text-red-700 font-medium mt-0.5">';
                                    foreach ($mods as $m) {
                                        echo '<span class="mr-2">+ ' . e($m['name']) . ' (' . $currency . ' ' . number_format($m['price'], 2) . ')</span>';
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
                <?php if ($order['discount_amount'] > 0): ?>
                <div class="flex justify-between text-rose-600">
                    <span>Discount:</span>
                    <span class="font-bold">-<?= $currency ?> <?= number_format($order['discount_amount'], 2) ?></span>
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

    </div>

</body>
</html>
