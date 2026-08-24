<?php
/**
 * User / Cashier Performance Report
 */
require_once __DIR__ . '/config/functions.php';
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$title = 'Users Report';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? 'Rs.';

$clearFilters = isset($_GET['clear']);
$startDate = $clearFilters ? '' : ($_GET['start_date'] ?? date('Y-m-01'));
$endDate = $clearFilters ? '' : ($_GET['end_date'] ?? date('Y-m-d'));
$startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) ? $startDate : '';
$endDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) ? $endDate : '';
if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}
$search = trim($_GET['q'] ?? '');
$likeSearch = '%' . $search . '%';
$startDateTime = $startDate !== '' ? $startDate . ' 00:00:00' : '';
$endDateExclusiveTime = $endDate !== '' ? date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00' : '';

$orderDateFilter = '';
$expenseDateFilter = '';
$shiftDateFilter = '';
$reportParams = [':user_search' => $likeSearch];
if ($startDate !== '') {
    $orderDateFilter .= ' AND created_at >= :order_start_time';
    $expenseDateFilter .= ' AND expense_date >= :expense_start';
    $shiftDateFilter .= ' AND COALESCE(closing_time, NOW()) >= :shift_start_time';
    $reportParams[':order_start_time'] = $startDateTime;
    $reportParams[':expense_start'] = $startDate;
    $reportParams[':shift_start_time'] = $startDateTime;
}
if ($endDate !== '') {
    $orderDateFilter .= ' AND created_at < :order_end_time';
    $expenseDateFilter .= ' AND expense_date < :expense_end';
    $shiftDateFilter .= ' AND opening_time < :shift_end_time';
    $reportParams[':order_end_time'] = $endDateExclusiveTime;
    $reportParams[':expense_end'] = date('Y-m-d', strtotime($endDate . ' +1 day'));
    $reportParams[':shift_end_time'] = $endDateExclusiveTime;
}

$usersReport = db()->fetchAll(
    "SELECT
        u.id,
        u.name,
        u.role,
        COALESCE(o.order_count, 0) as order_count,
        COALESCE(o.total_sales, 0) as total_sales,
        COALESCE(o.total_discount, 0) as total_discount,
        COALESCE(o.net_sales, 0) as net_sales,
        COALESCE(o.cash_sales, 0) as cash_sales,
        COALESCE(o.card_sales, 0) as card_sales,
        COALESCE(e.total_expenses, 0) as total_expenses,
        COALESCE(r.opening_float, 0) as opening_float,
        r.counted_cash,
        (COALESCE(r.opening_float, 0) + COALESCE(o.cash_sales, 0) - COALESCE(e.total_expenses, 0)) as expected_cash,
        (r.counted_cash - (COALESCE(r.opening_float, 0) + COALESCE(o.cash_sales, 0) - COALESCE(e.total_expenses, 0))) as variance
     FROM users u
     LEFT JOIN (
         SELECT user_id,
                COUNT(id) as order_count,
                SUM(subtotal) as total_sales,
                SUM(discount_amount) as total_discount,
                SUM(subtotal - discount_amount) as net_sales,
                SUM(CASE WHEN payment_method = 'cash' THEN subtotal - discount_amount ELSE 0 END) as cash_sales,
                SUM(CASE WHEN payment_method = 'card' THEN subtotal - discount_amount ELSE 0 END) as card_sales
         FROM orders
                 WHERE 1=1
                     $orderDateFilter
           AND payment_status = 'paid'
           AND order_status = 'completed'
         GROUP BY user_id
     ) o ON o.user_id = u.id
     LEFT JOIN (
         SELECT user_id, SUM(amount) as total_expenses
         FROM expenses
                 WHERE 1=1
                     $expenseDateFilter
         GROUP BY user_id
     ) e ON e.user_id = u.id
     LEFT JOIN (
         SELECT user_id,
                SUM(opening_cash) as opening_float,
                SUM(closing_cash) as counted_cash
         FROM cash_registers
                 WHERE 1=1
                     $shiftDateFilter
         GROUP BY user_id
     ) r ON r.user_id = u.id
         WHERE o.order_count > 0
             AND u.name LIKE :user_search
    ORDER BY o.order_count DESC, o.net_sales DESC, u.name ASC",
    $reportParams
);

$exportPdf = ($_GET['export'] ?? '') === 'pdf';
$formatAmount = static function ($amount): string {
    return number_format((float)$amount, 2);
};
?>
<?php if ($exportPdf): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Report | <?= e($settings['cafe_name'] ?? APP_NAME) ?></title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; color: #292524; background: white; }
        .report-page { max-width: 1600px; margin: 0 auto; padding: 32px; }
        .report-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; margin-bottom: 24px; }
        .report-header h1 { margin: 0 0 6px; font-size: 26px; }
        .report-header p { margin: 0; color: #78716c; font-size: 13px; }
        .toolbar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
        .toolbar input { padding: 9px 11px; border: 1px solid #d6d3d1; border-radius: 8px; }
        .toolbar button, .toolbar a { padding: 10px 14px; border: 0; border-radius: 8px; color: white; background: #292524; text-decoration: none; font-size: 13px; cursor: pointer; }
        .toolbar a { background: #047857; }
        .report-table { width: 100%; border-collapse: collapse; background: white; font-size: 11px; }
        .report-table th { background: #292524; color: white; text-align: left; padding: 10px 8px; white-space: nowrap; }
        .report-table td { padding: 10px 8px; border-bottom: 1px solid #e7e5e4; white-space: nowrap; }
        .report-table td.number, .report-table th.number { text-align: right; }
        .report-table td.center, .report-table th.center { text-align: center; }
        .report-table tr:nth-child(even) { background: #fafaf9; }
        .status-balanced { color: #047857; font-weight: bold; }
        .status-short { color: #be123c; font-weight: bold; }
        .status-over { color: #0369a1; font-weight: bold; }
        .muted { color: #a8a29e; }
        @media print {
            @page { size: landscape; margin: 10mm; }
            body { background: white; }
            .report-page { max-width: none; padding: 0; }
            .toolbar { display: none; }
            .report-header { margin-bottom: 12px; }
            .report-table { font-size: 8px; }
            .report-table th, .report-table td { padding: 5px 4px; }
        }
    </style>
</head>
<body>
<?php else: ?>
<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<style>
    .report-page { max-width: 80rem; padding: 0; }
    .report-table { min-width: 1280px; background: #1c1917; }
    .report-table th { background: #1c1917; color: #a8a29e; }
    .report-table td { border-color: #292524; color: #d6d3d1; }
    .report-table tr:nth-child(even) { background: rgba(28, 25, 23, 0.55); }
    .report-table tr:hover { background: rgba(41, 37, 36, 0.8); }
    .report-table .muted { color: #78716c; }
    .report-table .amount-sales { color: #f87171; font-weight: 800; }
    .report-table .amount-discount, .report-table .amount-expense { color: #fb7185; font-weight: 800; }
    .report-table .amount-net { color: #fbbf24; font-weight: 900; }
    .report-table .amount-cash { color: #34d399; font-weight: 800; }
    .report-table .amount-card { color: #38bdf8; font-weight: 800; }
    .report-table .amount-opening, .report-table .amount-expected { color: #d6d3d1; font-weight: 800; }
    .status-badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 3px 8px; font-size: 10px; font-weight: 900; text-transform: uppercase; }
    .status-balanced { color: #6ee7b7; background: #064e3b; border: 1px solid #047857; }
    .status-short { color: #fda4af; background: #4c0519; border: 1px solid #be123c; }
    .status-over { color: #7dd3fc; background: #082f49; border: 1px solid #0369a1; }
    .status-open { color: #fcd34d; background: #451a03; border: 1px solid #b45309; }
    .status-legend { display: flex; gap: 16px; flex-wrap: wrap; align-items: center; color: #a8a29e; font-size: 11px; }
    .status-legend span { display: inline-flex; align-items: center; gap: 5px; }
    .status-legend i { font-size: 9px; }
    @media (max-width: 767px) {
        .report-table { min-width: 0; background: transparent; border-spacing: 0 10px; border-collapse: separate; }
        .report-table thead { display: none; }
        .report-table tbody { display: block; }
        .report-table tr { display: block; background: #1c1917 !important; border: 1px solid #292524; border-radius: 16px; padding: 8px 12px; margin-bottom: 10px; }
        .report-table td { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 8px 0; border-bottom: 1px solid #292524; text-align: right !important; white-space: normal; }
        .report-table td:last-child { border-bottom: 0; }
        .report-table td::before { content: attr(data-label); color: #a8a29e; font-size: 10px; font-weight: 800; text-align: left; text-transform: uppercase; }
        .report-table td:first-child { display: block; text-align: left !important; padding-top: 4px; }
        .report-table td:first-child::before { display: none; }
        .report-table td:first-child .muted { display: block; margin-top: 3px; }
    }
</style>
<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#0c0c0e]">
<?php endif; ?>
<div class="report-page <?= !$exportPdf ? 'max-w-7xl mx-auto space-y-6' : '' ?>">
    <div class="report-header <?= !$exportPdf ? 'flex flex-col sm:flex-row sm:items-center justify-between gap-4' : '' ?>">
        <div>
            <h1 class="<?= !$exportPdf ? 'text-2xl font-black tracking-tight text-white' : '' ?>"><?= !$exportPdf ? 'Users Report' : e($settings['cafe_name'] ?? APP_NAME) . ' - Users Report' ?></h1>
            <p class="<?= !$exportPdf ? 'text-xs text-stone-400' : '' ?>"><?= e($startDate) ?> to <?= e($endDate) ?><?= $search !== '' ? ' | Search: ' . e($search) : '' ?></p>
        </div>
        <?php if (!$exportPdf): ?>
            <a href="<?= BASE_URL ?>/users_report.php?start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>&q=<?= urlencode($search) ?>&export=pdf" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-2xl text-xs shadow-lg shadow-red-900/40 transition"><i class="fa-solid fa-file-pdf"></i> Download / Print PDF</a>
        <?php endif; ?>
    </div>

    <?php if (!$exportPdf): ?>
    <form method="GET" action="users_report.php" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs bg-stone-900 border border-stone-800 p-4 rounded-2xl shadow-lg">
        <div>
            <label class="block font-bold text-stone-300 mb-1">From</label>
            <input type="date" name="start_date" value="<?= e($startDate) ?>" class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-white">
        </div>
        <div>
            <label class="block font-bold text-stone-300 mb-1">To</label>
            <input type="date" name="end_date" value="<?= e($endDate) ?>" class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-white">
        </div>
        <div>
            <label class="block font-bold text-stone-300 mb-1">Search Employee</label>
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Employee name" class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-white">
        </div>
        <div class="flex items-end">
            <div class="flex w-full gap-2">
                <button type="submit" class="flex-1 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl shadow-md transition"><i class="fa-solid fa-filter mr-1"></i> Filter</button>
                <a href="<?= BASE_URL ?>/users_report.php?clear=1" class="px-4 py-2 bg-stone-700 hover:bg-stone-600 text-white font-bold rounded-xl shadow-md transition" title="Clear filters"><i class="fa-solid fa-xmark"></i></a>
            </div>
        </div>
    </form>
    <div class="status-legend mb-1">
        <span><i class="fa-solid fa-circle text-emerald-400"></i> Balanced - 0</span>
        <span><i class="fa-solid fa-circle text-rose-400"></i> Short - negative variance</span>
        <span><i class="fa-solid fa-circle text-sky-400"></i> Over - positive variance</span>
    </div>
    <?php endif; ?>

    <div class="<?= !$exportPdf ? 'card-cafe-dark overflow-hidden' : '' ?>">
    <div class="overflow-x-auto">
    <table class="report-table w-full text-xs text-left">
        <thead class="bg-stone-900 text-stone-400 font-bold uppercase text-[10px] tracking-wider border-b border-stone-700">
            <tr>
                <th>Cashier</th>
                <th class="center">Orders</th>
                <th class="number">Total Sales</th>
                <th class="number">Discount</th>
                <th class="number">Net Sales</th>
                <th class="number">Cash Sales</th>
                <th class="number">Card Sales</th>
                <th class="number">Expenses</th>
                <th class="number">Opening Float</th>
                <th class="number">Expected Cash</th>
                <th class="number">Counted Cash</th>
                <th class="number">Variance</th>
                <th class="center">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-800">
        <?php if (empty($usersReport)): ?>
            <tr><td colspan="13" class="center muted">No users found.</td></tr>
        <?php else: ?>
            <?php foreach ($usersReport as $row): ?>
                <?php
                $countedCash = $row['counted_cash'];
                $variance = $countedCash === null ? null : (float)$row['variance'];
                $status = $variance === null ? 'Open' : ($variance == 0.0 ? 'Balanced' : ($variance < 0 ? 'Short' : 'Over'));
                $statusClass = $status === 'Balanced' ? 'status-balanced' : ($status === 'Short' ? 'status-short' : ($status === 'Over' ? 'status-over' : 'status-open'));
                ?>
                <tr class="hover:bg-stone-800 transition">
                    <td class="p-3.5"><strong><?= e($row['name']) ?></strong><br><span class="muted"><?= e(ucfirst($row['role'])) ?></span></td>
                    <td data-label="Orders" class="p-3.5 center"><?= (int)$row['order_count'] ?></td>
                    <td data-label="Total Sales" class="p-3.5 number amount-sales"><?= $formatAmount($row['total_sales']) ?></td>
                    <td data-label="Discount" class="p-3.5 number amount-discount">-<?= $formatAmount($row['total_discount']) ?></td>
                    <td data-label="Net Sales" class="p-3.5 number amount-net"><?= $formatAmount($row['net_sales']) ?></td>
                    <td data-label="Cash Sales" class="p-3.5 number amount-cash"><?= $formatAmount($row['cash_sales']) ?></td>
                    <td data-label="Card Sales" class="p-3.5 number amount-card"><?= $formatAmount($row['card_sales']) ?></td>
                    <td data-label="Expenses" class="p-3.5 number amount-expense">-<?= $formatAmount($row['total_expenses']) ?></td>
                    <td data-label="Opening Float" class="p-3.5 number amount-opening"><?= $formatAmount($row['opening_float']) ?></td>
                    <td data-label="Expected Cash" class="p-3.5 number amount-expected"><?= $formatAmount($row['expected_cash']) ?></td>
                    <td data-label="Counted Cash" class="p-3.5 number"><?= $countedCash === null ? '-' : $formatAmount($countedCash) ?></td>
                    <td data-label="Variance" class="p-3.5 number <?= $variance !== null && $variance < 0 ? 'status-short' : '' ?>"><?= $variance === null ? '-' : ($variance >= 0 ? '+' : '') . number_format($variance, 2) ?></td>
                    <td class="p-3.5 center"><span class="status-badge <?= $statusClass ?>"><i class="fa-solid <?= $status === 'Balanced' ? 'fa-check' : ($status === 'Short' ? 'fa-arrow-down' : ($status === 'Over' ? 'fa-arrow-up' : 'fa-clock')) ?> mr-1"></i><?= $status ?></span></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    </div>
</div>
<?php if ($exportPdf): ?><script>window.addEventListener('load', function () { window.print(); });</script></body></html><?php else: ?></main><?php require_once __DIR__ . '/includes/footer.php'; ?><?php endif; ?>