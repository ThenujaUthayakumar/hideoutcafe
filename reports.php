<?php
/**
 * Aura Cafe POS - Reports & Financial Analytics (with CSV Export)
 */
require_once __DIR__ . '/config/functions.php';
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$title = 'Reports & Analytics';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? '$';

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$export = $_GET['export'] ?? '';

// CSV Export Handler
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=cafe_sales_report_' . $startDate . '_to_' . $endDate . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Invoice No', 'Date & Time', 'Order Type', 'Table', 'Cashier', 'Customer', 'Payment Method', 'Subtotal', 'Discount', 'Service Charge', 'Grand Total', 'Status']);

    $exportOrders = db()->fetchAll(
        "SELECT o.*, t.name as table_name, u.name as cashier_name, c.name as customer_name 
         FROM orders o 
         LEFT JOIN tables t ON o.table_id = t.id 
         LEFT JOIN users u ON o.user_id = u.id 
         LEFT JOIN customers c ON o.customer_id = c.id 
         WHERE DATE(o.created_at) BETWEEN :s AND :e 
         ORDER BY o.id ASC",
        [':s' => $startDate, ':e' => $endDate]
    );

    foreach ($exportOrders as $row) {
        fputcsv($output, [
            $row['invoice_no'],
            $row['created_at'],
            $row['order_type'],
            $row['table_name'] ?? 'N/A',
            $row['cashier_name'],
            $row['customer_name'] ?? 'Walk-in',
            strtoupper($row['payment_method']),
            $row['subtotal'],
            $row['discount_amount'],
            $row['service_charge'],
            $row['grand_total'],
            strtoupper($row['order_status'])
        ]);
    }
    fclose($output);
    exit;
}

// Financial KPI Summary for selected range
$kpi = db()->fetchOne(
    "SELECT 
        COUNT(id) as total_orders,
        COALESCE(SUM(subtotal), 0) as total_subtotal,
        COALESCE(SUM(discount_amount), 0) as total_discount,
        COALESCE(SUM(service_charge), 0) as total_service,
        COALESCE(SUM(subtotal - discount_amount), 0) as net_amount
     FROM orders 
     WHERE DATE(created_at) BETWEEN :s AND :e AND payment_status = 'paid' AND order_status != 'cancelled'",
    [':s' => $startDate, ':e' => $endDate]
);

// Total Expenses in date range
$expenseSummary = db()->fetchOne(
    "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE expense_date BETWEEN :s AND :e",
    [':s' => $startDate, ':e' => $endDate]
);
$openingFloatSummary = db()->fetchOne(
    "SELECT COALESCE(SUM(opening_cash), 0) as total
     FROM cash_registers
     WHERE opening_time >= CONCAT(:s, ' 00:00:00')
       AND opening_time < DATE_ADD(CONCAT(:e, ' 00:00:00'), INTERVAL 1 DAY)",
    [':s' => $startDate, ':e' => $endDate]
);
$netProfit = ($kpi['net_amount'] ?? 0) - ($expenseSummary['total'] ?? 0);

// Payment breakdown
$paymentBreakdown = db()->fetchAll(
    "SELECT payment_method, COUNT(id) as count, SUM(grand_total) as total 
     FROM orders 
     WHERE DATE(created_at) BETWEEN :s AND :e AND payment_status = 'paid' AND order_status != 'cancelled' 
     GROUP BY payment_method",
    [':s' => $startDate, ':e' => $endDate]
);

// Top Selling Items in Range
$productSales = db()->fetchAll(
    "SELECT oi.product_name, SUM(oi.quantity) as total_qty, SUM(oi.subtotal) as total_sales 
     FROM order_items oi 
     JOIN orders o ON oi.order_id = o.id 
     WHERE DATE(o.created_at) BETWEEN :s AND :e AND o.payment_status = 'paid' AND o.order_status != 'cancelled'
     GROUP BY oi.product_name 
     ORDER BY total_sales DESC 
     LIMIT 10",
    [':s' => $startDate, ':e' => $endDate]
);

// Cashier Performance in Range
$cashierSales = db()->fetchAll(
    "SELECT u.name as cashier_name, COUNT(o.id) as order_count, SUM(o.grand_total) as total_collected 
     FROM orders o 
     JOIN users u ON o.user_id = u.id 
     WHERE DATE(o.created_at) BETWEEN :s AND :e AND o.payment_status = 'paid' AND o.order_status != 'cancelled'
     GROUP BY u.id 
     ORDER BY total_collected DESC",
    [':s' => $startDate, ':e' => $endDate]
);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#fbf9f6]">
    <div class="max-w-7xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-stone-900">Financial Reports & Analytics</h2>
                <p class="text-xs text-stone-500">Analyze sales volume, cashier performance, and net margins</p>
            </div>
            <a href="reports.php?start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>&export=csv" class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-700 hover:bg-emerald-600 text-white font-bold rounded-2xl text-xs shadow-md transition">
                <i class="fa-solid fa-file-csv text-base"></i> Export to CSV / Excel
            </a>
        </div>

        <!-- Date Range Filter -->
        <div class="card-cafe p-4">
            <form method="GET" action="reports.php" class="flex flex-col sm:flex-row items-center gap-3 text-xs">
                <div class="flex items-center gap-2">
                    <label class="font-bold text-stone-700">From:</label>
                    <input type="date" name="start_date" value="<?= e($startDate) ?>" class="px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl font-semibold">
                </div>
                <div class="flex items-center gap-2">
                    <label class="font-bold text-stone-700">To:</label>
                    <input type="date" name="end_date" value="<?= e($endDate) ?>" class="px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl font-semibold">
                </div>
                <button type="submit" class="px-5 py-2 bg-stone-800 text-white font-bold rounded-xl shadow-xs">Generate Report</button>
            </form>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="card-cafe p-5">
                <span class="text-xs font-bold uppercase tracking-wider text-stone-400">Opening Float</span>
                <h3 class="text-2xl font-black text-sky-800 mt-1"><?= $currency ?><?= number_format($openingFloatSummary['total'] ?? 0, 2) ?></h3>
                <span class="text-xs text-stone-500 mt-2 block">Shifts opened in selected range</span>
            </div>

            <div class="card-cafe p-5">
                <span class="text-xs font-bold uppercase tracking-wider text-stone-400">Net Amount</span>
                <h3 class="text-2xl font-black text-amber-900 mt-1"><?= $currency ?><?= number_format($kpi['net_amount'], 2) ?></h3>
                <span class="text-xs text-stone-500 mt-2 block"><?= (int)$kpi['total_orders'] ?> Paid Orders</span>
            </div>

            <div class="card-cafe p-5">
                <span class="text-xs font-bold uppercase tracking-wider text-stone-400">Total Orders</span>
                <h3 class="text-2xl font-black text-stone-900 mt-1"><?= (int)$kpi['total_orders'] ?></h3>
                <span class="text-xs text-stone-500 mt-2 block">Paid orders in selected range</span>
            </div>

            <div class="card-cafe p-5">
                <span class="text-xs font-bold uppercase tracking-wider text-stone-400">Discounts Given</span>
                <h3 class="text-2xl font-black text-rose-600 mt-1">-<?= $currency ?><?= number_format($kpi['total_discount'], 2) ?></h3>
                <span class="text-xs text-stone-500 mt-2 block">Subtotal: <?= $currency ?><?= number_format($kpi['total_subtotal'], 2) ?></span>
            </div>

            <div class="card-cafe p-5">
                <span class="text-xs font-bold uppercase tracking-wider text-stone-400">Net Estimated Profit</span>
                <h3 class="text-2xl font-black <?= $netProfit >= 0 ? 'text-emerald-700' : 'text-rose-600' ?> mt-1"><?= $currency ?><?= number_format($netProfit, 2) ?></h3>
                <span class="text-xs text-stone-500 mt-2 block">After <?= $currency ?><?= number_format($expenseSummary['total'], 2) ?> Expenses</span>
            </div>
        </div>

        <!-- Breakdown Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Top Products in Range -->
            <div class="card-cafe p-6">
                <h3 class="font-extrabold text-stone-900 text-base mb-4">Top 10 Selling Products</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-stone-100 text-stone-600 font-bold uppercase text-[10px]">
                            <tr>
                                <th class="p-2.5 rounded-l-lg">Product</th>
                                <th class="p-2.5 text-center">Qty Sold</th>
                                <th class="p-2.5 text-right rounded-r-lg">Total Sales</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            <?php foreach ($productSales as $ps): ?>
                            <tr>
                                <td class="p-2.5 font-bold text-stone-900"><?= e($ps['product_name']) ?></td>
                                <td class="p-2.5 text-center font-bold text-stone-700"><?= (int)$ps['total_qty'] ?></td>
                                <td class="p-2.5 text-right font-black text-amber-900"><?= $currency ?><?= number_format($ps['total_sales'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payment Methods & Staff Summary -->
            <div class="space-y-6">
                <!-- Payment breakdown -->
                <div class="card-cafe p-6">
                    <h3 class="font-extrabold text-stone-900 text-base mb-4">Payment Methods Share</h3>
                    <div class="space-y-2.5">
                        <?php foreach ($paymentBreakdown as $pb): ?>
                        <div class="flex items-center justify-between p-3 bg-stone-50 rounded-xl">
                            <span class="font-bold text-xs uppercase text-stone-800"><?= e($pb['payment_method']) ?> (<?= (int)$pb['count'] ?> orders)</span>
                            <span class="font-black text-amber-900 text-sm"><?= $currency ?><?= number_format($pb['total'], 2) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Cashier Performance -->
                <div class="card-cafe p-6">
                    <h3 class="font-extrabold text-stone-900 text-base mb-4">Cashier Performance</h3>
                    <div class="space-y-2">
                        <?php foreach ($cashierSales as $cs): ?>
                        <div class="flex items-center justify-between p-2.5 border-b border-stone-100 last:border-0">
                            <div>
                                <span class="font-bold text-xs text-stone-900"><?= e($cs['cashier_name']) ?></span>
                                <span class="block text-[10px] text-stone-400"><?= (int)$cs['order_count'] ?> sales completed</span>
                            </div>
                            <span class="font-black text-xs text-stone-900"><?= $currency ?><?= number_format($cs['total_collected'], 2) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
