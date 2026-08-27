<?php
/**
 * Aura Cafe POS - Shift Cash Register & Drawer Management
 */
require_once __DIR__ . '/config/functions.php';
requireAuth();

$title = 'Shift Cash Registers';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? '$';
$currentUser = currentUser();
$shiftWhere = '';
$shiftParams = [];
$shiftSearch = trim($_GET['q'] ?? '');
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

if (hasRole(ROLE_CASHIER)) {
    $shiftWhere = ' WHERE cr.user_id = :user_id';
    $shiftParams[':user_id'] = $currentUser['id'];
}
$shiftSql = "SELECT cr.*, u.name as cashier_name FROM cash_registers cr JOIN users u ON cr.user_id = u.id" . $shiftWhere;
if ($shiftSearch !== '') {
    $shiftSql .= " AND (CAST(cr.id AS CHAR) LIKE :search_id OR u.name LIKE :search_cashier OR cr.status LIKE :search_status)";
    $shiftParams[':search_id'] = "%$shiftSearch%";
    $shiftParams[':search_cashier'] = "%$shiftSearch%";
    $shiftParams[':search_status'] = "%$shiftSearch%";
}
$countRow = db()->fetchOne("SELECT COUNT(*) AS total FROM ($shiftSql) filtered_shifts", $shiftParams);
$totalShifts = (int)($countRow['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalShifts / $perPage));
$page = min($page, $totalPages);

$shifts = db()->fetchAll($shiftSql . " ORDER BY cr.id DESC LIMIT " . (($page - 1) * $perPage) . ", " . $perPage, $shiftParams);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#fbf9f6]">
    <div class="max-w-7xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-stone-900">Shift Cash Register & Drawers</h2>
                <p class="text-xs text-stone-500">Audit cashier opening float, shift sales totals, and cash drawer reconciliations</p>
            </div>
            <button onclick="openModal('shift-register-modal')" class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-800 hover:bg-amber-700 text-white font-bold rounded-2xl text-xs shadow-md transition">
                <i class="fa-solid fa-vault"></i> Open / Close Shift
            </button>
        </div>

        <div class="card-cafe p-4">
            <form method="GET" action="cash_register.php" class="flex flex-col sm:flex-row gap-3 text-xs">
                <input type="text" name="q" value="<?= e($shiftSearch) ?>" placeholder="Search shift number, cashier or status..." class="flex-1 px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl">
                <button type="submit" class="px-5 py-2 bg-amber-800 text-white font-bold rounded-xl">Search</button>
                <?php if ($shiftSearch !== ''): ?><a href="<?= BASE_URL ?>/cash_register.php" class="px-5 py-2 bg-stone-200 text-stone-700 font-bold rounded-xl text-center">Clear</a><?php endif; ?>
            </form>
        </div>

        <div class="card-cafe overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-stone-100/90 text-stone-600 font-bold uppercase text-[10px] tracking-wider border-b border-stone-200">
                        <tr>
                            <th class="p-3.5">Shift #</th>
                            <th class="p-3.5">Cashier</th>
                            <th class="p-3.5">Opening Time</th>
                            <th class="p-3.5">Closing Time</th>
                            <th class="p-3.5 text-right">Opening Float</th>
                            <th class="p-3.5 text-right">Cash Sales</th>
                            <th class="p-3.5 text-right">Card Sales</th>
                            <th class="p-3.5 text-right">Expenses</th>
                            <th class="p-3.5 text-right">Closing Cash</th>
                            <th class="p-3.5 text-right">Discrepancy</th>
                            <th class="p-3.5 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <?php if (empty($shifts)): ?>
                            <tr><td colspan="11" class="p-8 text-center text-stone-400">No shift records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($shifts as $s): ?>
                            <?php 
                            $expenseAmount = getCashRegisterExpenseTotal($s);
                            $shiftSales = getCashRegisterSalesSummary($s);
                            $expectedCash = (float)$s['opening_cash']
                                + (float)($shiftSales['total_sales'] ?? 0)
                                - (float)($shiftSales['total_discount'] ?? 0);
                            $diff = (float)($s['difference_amount'] ?? 0);
                            $diffClass = $diff < 0 ? 'text-rose-600 font-black' : ($diff > 0 ? 'text-emerald-700 font-black' : 'text-stone-500');
                            ?>
                            <tr class="hover:bg-stone-50 transition">
                                <td class="p-3.5 font-bold text-stone-900">#<?= $s['id'] ?></td>
                                <td class="p-3.5 font-semibold text-stone-800"><?= e($s['cashier_name']) ?></td>
                                <td class="p-3.5 text-stone-600"><?= date('M d, h:i A', strtotime($s['opening_time'])) ?></td>
                                <td class="p-3.5 text-stone-600"><?= !empty($s['closing_time']) ? date('M d, h:i A', strtotime($s['closing_time'])) : '-' ?></td>
                                <td class="p-3.5 text-right font-semibold text-stone-800"><?= $currency ?><?= number_format($s['opening_cash'], 2) ?></td>
                                <td class="p-3.5 text-right font-bold text-emerald-700"><?= $currency ?><?= number_format($s['total_cash_sales'], 2) ?></td>
                                <td class="p-3.5 text-right font-bold text-sky-700"><?= $currency ?><?= number_format($s['total_card_sales'], 2) ?></td>
                                <td class="p-3.5 text-right font-bold text-rose-600">-<?= $currency ?><?= number_format($expenseAmount, 2) ?></td>
                                <td class="p-3.5 text-right font-black text-stone-900"><?= $s['closing_cash'] !== null ? $currency . number_format($s['closing_cash'], 2) : '-' ?></td>
                                <td class="p-3.5 text-right <?= $diffClass ?>"><?= $s['difference_amount'] !== null ? ($diff >= 0 ? '+' : '') . $currency . number_format($diff, 2) : '-' ?></td>
                                <td class="p-3.5 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase <?= $s['status'] === 'open' ? 'bg-emerald-100 text-emerald-800 animate-pulse' : 'bg-stone-100 text-stone-600' ?>">
                                        <?= e($s['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= renderPagination($page, $totalShifts, $perPage, ['q' => $shiftSearch]) ?>
        </div>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
