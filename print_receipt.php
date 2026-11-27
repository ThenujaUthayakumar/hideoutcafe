<?php
/**
 * The Hide Out Cafe - Thermal Slip Print Window
 */
require_once __DIR__ . '/config/functions.php';
requireAuth();

$orderId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$autoPrint = !empty($_GET['auto_print']);
$refreshOrders = !empty($_GET['refresh_orders']);
$requestedCopy = $_GET['copy'] ?? 'main';
$copy = in_array($requestedCopy, ['main', 'kitchen'], true) ? $requestedCopy : 'all';
$currentUser = currentUser();
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? 'Rs.';
ensureOrderDiscountSchema();
$orderOwnerFilter = '';
$orderParams = [':id' => $orderId];

if (hasRole(ROLE_CASHIER)) {
    $orderOwnerFilter = ' AND o.user_id = :user_id';
    $orderParams[':user_id'] = $currentUser['id'];
}

$order = db()->fetchOne(
    "SELECT o.*, t.name as table_name, u.name as cashier_name, c.name as customer_name 
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

if ($copy !== 'kitchen' && $order['payment_status'] !== 'paid') {
    die("The customer bill is available after payment is recorded.");
}

$order['items'] = db()->fetchAll("SELECT * FROM order_items WHERE order_id = :id", [':id' => $orderId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $copy === 'kitchen' ? 'Kitchen Copy' : ($copy === 'main' ? 'Main Bill' : 'Receipt') ?> <?= e($order['invoice_no']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-stone-100 flex flex-col items-center justify-center p-4">

    <?php if ($copy === 'kitchen'): ?>
    <div class="no-print mb-3 max-w-sm text-center text-xs text-stone-700">
        <strong class="block text-sm text-stone-900">Kitchen copy is ready</strong>
        Select the kitchen printer, then click Print Kitchen Copy.
    </div>
    <?php endif; ?>

    <!-- Print Button (Hidden during print) -->
    <div class="mb-4 flex gap-2 no-print">
        <button onclick="window.print()" class="px-5 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs shadow-md transition">
            <i class="fa-solid fa-print mr-1.5"></i> Print <?= $copy === 'kitchen' ? 'Kitchen Copy' : ($copy === 'main' ? 'Main Bill' : 'Both Bills') ?>
        </button>
        <button onclick="closeReceiptWindow()" class="px-4 py-2.5 bg-stone-200 text-stone-700 font-bold rounded-xl text-xs hover:bg-stone-300 transition">
            Close
        </button>
    </div>

    <!-- 80mm / 58mm Thermal Slip Content -->
    <div class="bg-white shadow-xl rounded-xl overflow-hidden">
        <?php if ($copy === 'kitchen'): ?>
            <?php require __DIR__ . '/includes/kitchen_receipt_template.php'; ?>
        <?php elseif ($copy === 'main'): ?>
            <?php require __DIR__ . '/includes/receipt_template.php'; ?>
        <?php else: ?>
            <?php require __DIR__ . '/includes/receipt_template.php'; ?>
            <?php require __DIR__ . '/includes/kitchen_receipt_template.php'; ?>
        <?php endif; ?>
    </div>

    <?php if ($autoPrint): ?>
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
    <?php endif; ?>
    <?php if (!$refreshOrders && $copy === 'main' && $order['payment_status'] === 'paid'): ?>
    <script>
        window.addEventListener('afterprint', function() {
            if (window.opener && !window.opener.closed) {
                window.opener.location.reload();
            }
        });
    </script>
    <?php endif; ?>
    <script>
        function closeReceiptWindow() {
            <?php if ($refreshOrders): ?>
            if (window.opener && !window.opener.closed) {
                window.opener.location.reload();
            }
            <?php endif; ?>
            window.close();
            <?php if (!$refreshOrders): ?>
            window.setTimeout(function() {
                if (!window.closed) {
                    window.location.href = <?= json_encode(BASE_URL . '/orders.php') ?>;
                }
            }, 150);
            <?php endif; ?>
        }
    </script>

</body>
</html>
