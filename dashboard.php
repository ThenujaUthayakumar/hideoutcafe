<?php
/**
 * The Hide Out Cafe - Analytics Dashboard (Black & Red Theme)
 */
require_once __DIR__ . '/config/functions.php';
requireRole([ROLE_ADMIN, ROLE_MANAGER], 'pos.php');

$title = 'Dashboard Overview';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? 'Rs.';

// Today Metrics
$todayStats = db()->fetchOne(
    "SELECT 
        COUNT(id) as total_orders,
        COALESCE(SUM(grand_total), 0) as gross_sales,
        COALESCE(AVG(grand_total), 0) as avg_order_val
     FROM orders 
     WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid' AND order_status != 'cancelled'"
);

// Today Expenses
$todayExpenses = db()->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE expense_date = CURDATE()");
$netToday = ($todayStats['gross_sales'] ?? 0) - ($todayExpenses['total'] ?? 0);

// Tables
$occupiedTables = db()->fetchOne("SELECT COUNT(id) as total FROM tables WHERE status = 'occupied'");
$totalTables = db()->fetchOne("SELECT COUNT(id) as total FROM tables");

// Low Stock Alert
$lowStockCount = db()->fetchOne("SELECT COUNT(id) as total FROM products WHERE track_stock = 1 AND stock_quantity <= alert_quantity AND status = 'active'");

// 7-Day Trend
$sevenDaysSales = db()->fetchAll(
    "SELECT DATE(created_at) as sale_date, SUM(grand_total) as daily_total, COUNT(id) as order_count 
     FROM orders 
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND payment_status = 'paid' AND order_status != 'cancelled'
     GROUP BY DATE(created_at) 
     ORDER BY sale_date ASC"
);

$datesMap = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $datesMap[$d] = 0;
}
foreach ($sevenDaysSales as $row) {
    if (isset($datesMap[$row['sale_date']])) {
        $datesMap[$row['sale_date']] = (float)$row['daily_total'];
    }
}
$chartLabels = array_map(function($d) { return date('D, M d', strtotime($d)); }, array_keys($datesMap));
$chartValues = array_values($datesMap);

// Top 5 Products
$topProducts = db()->fetchAll(
    "SELECT oi.product_name, SUM(oi.quantity) as total_qty, SUM(oi.subtotal) as total_revenue 
     FROM order_items oi 
     JOIN orders o ON oi.order_id = o.id 
     WHERE o.payment_status = 'paid' AND o.order_status != 'cancelled'
     GROUP BY oi.product_name 
     ORDER BY total_qty DESC 
     LIMIT 5"
);

// Recent Orders
$recentOrders = db()->fetchAll(
    "SELECT o.*, t.name as table_name, u.name as cashier_name 
     FROM orders o 
     LEFT JOIN tables t ON o.table_id = t.id 
     LEFT JOIN users u ON o.user_id = u.id 
     ORDER BY o.id DESC 
     LIMIT 6"
);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#0c0c0e]">
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Welcome Banner (Black & Red) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-stone-950 via-stone-900 to-red-950 p-6 rounded-3xl text-white shadow-2xl border border-stone-800">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-600/20 text-red-400 text-xs font-bold mb-2 border border-red-800">
                    <i class="fa-solid fa-clock"></i> <?= date('l, F j, Y') ?> &bull; Sri Lanka
                </div>
                <h2 class="text-2xl font-black tracking-tight">Welcome, <?= e($user['name']) ?></h2>
                <p class="text-stone-400 text-xs mt-1">Live dashboard for <?= e($settings['cafe_name'] ?? 'The Hide Out Cafe') ?></p>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?= BASE_URL ?>/pos.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-2xl text-xs shadow-lg shadow-red-900/50 transition transform active:scale-95">
                    <i class="fa-solid fa-cash-register text-sm"></i> Launch POS Terminal
                </a>
                <a href="<?= BASE_URL ?>/kds.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-stone-800 hover:bg-stone-700 text-white font-bold rounded-2xl text-xs transition border border-stone-700">
                    <i class="fa-solid fa-kitchen-set text-sm"></i> Live KDS
                </a>
            </div>
        </div>

        <!-- 4 Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <div class="bg-stone-900 border border-stone-800 rounded-2xl p-5 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-stone-400 uppercase tracking-wider">Today's Revenue</p>
                        <h3 class="text-2xl font-black text-white mt-1"><?= $currency ?> <?= number_format($todayStats['gross_sales'] ?? 0, 2) ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-red-950 text-red-400 border border-red-800 flex items-center justify-center text-xl shadow-md">
                        <i class="fa-solid fa-sack-dollar"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs text-stone-400 pt-3 border-t border-stone-800">
                    <span>Expenses Today:</span>
                    <span class="font-bold text-rose-400">-<?= $currency ?> <?= number_format($todayExpenses['total'] ?? 0, 2) ?></span>
                </div>
            </div>

            <div class="bg-stone-900 border border-stone-800 rounded-2xl p-5 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-stone-400 uppercase tracking-wider">Orders Today</p>
                        <h3 class="text-2xl font-black text-white mt-1"><?= (int)($todayStats['total_orders'] ?? 0) ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-stone-800 text-stone-300 border border-stone-700 flex items-center justify-center text-xl shadow-md">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs text-stone-400 pt-3 border-t border-stone-800">
                    <span>Avg Order Value:</span>
                    <span class="font-bold text-white"><?= $currency ?> <?= number_format($todayStats['avg_order_val'] ?? 0, 2) ?></span>
                </div>
            </div>

            <div class="bg-stone-900 border border-stone-800 rounded-2xl p-5 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-stone-400 uppercase tracking-wider">Occupied Tables</p>
                        <h3 class="text-2xl font-black text-white mt-1"><?= (int)($occupiedTables['total'] ?? 0) ?> <span class="text-sm font-semibold text-stone-500">/ <?= (int)($totalTables['total'] ?? 0) ?></span></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-950 text-emerald-400 border border-emerald-800 flex items-center justify-center text-xl shadow-md">
                        <i class="fa-solid fa-chair"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs text-stone-400 pt-3 border-t border-stone-800">
                    <span>Floor Status:</span>
                    <a href="<?= BASE_URL ?>/tables.php" class="font-bold text-red-400 hover:underline">Manage Floor &rarr;</a>
                </div>
            </div>

            <div class="bg-stone-900 border border-stone-800 rounded-2xl p-5 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-stone-400 uppercase tracking-wider">Low Stock Items</p>
                        <h3 class="text-2xl font-black <?= ($lowStockCount['total'] ?? 0) > 0 ? 'text-red-400' : 'text-white' ?> mt-1"><?= (int)($lowStockCount['total'] ?? 0) ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl <?= ($lowStockCount['total'] ?? 0) > 0 ? 'bg-red-950 text-red-400 border border-red-800' : 'bg-stone-800 text-stone-300' ?> flex items-center justify-center text-xl shadow-md">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs text-stone-400 pt-3 border-t border-stone-800">
                    <span>Inventory:</span>
                    <a href="<?= BASE_URL ?>/products.php" class="font-bold text-red-400 hover:underline">View Products &rarr;</a>
                </div>
            </div>

        </div>

        <!-- Charts & Top Selling Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="bg-stone-900 border border-stone-800 rounded-2xl p-6 lg:col-span-2 shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-extrabold text-white text-base">7-Day Sales Trend (LKR)</h3>
                        <p class="text-xs text-stone-400">Daily gross revenue over past week</p>
                    </div>
                    <span class="text-xs font-bold px-2.5 py-1 bg-red-950 text-red-300 border border-red-800 rounded-lg">Last 7 Days</span>
                </div>
                <div class="h-64 relative">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>

            <div class="bg-stone-900 border border-stone-800 rounded-2xl p-6 flex flex-col justify-between shadow-lg">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-extrabold text-white text-base">Top Selling Items</h3>
                            <p class="text-xs text-stone-400">Popular items by quantity sold</p>
                        </div>
                        <i class="fa-solid fa-fire text-red-500"></i>
                    </div>

                    <div class="space-y-3">
                        <?php if (empty($topProducts)): ?>
                            <p class="text-xs text-stone-500 py-8 text-center">No sales recorded yet.</p>
                        <?php else: ?>
                            <?php foreach ($topProducts as $idx => $tp): ?>
                                <div class="flex items-center justify-between p-2.5 bg-stone-950 rounded-xl border border-stone-800">
                                    <div class="flex items-center gap-3">
                                        <span class="w-6 h-6 rounded-lg bg-red-600 text-white text-[11px] font-bold flex items-center justify-center"><?= $idx + 1 ?></span>
                                        <div>
                                            <h4 class="font-bold text-xs text-white leading-tight"><?= e($tp['product_name']) ?></h4>
                                            <p class="text-[11px] text-stone-400"><?= (int)$tp['total_qty'] ?> sold</p>
                                        </div>
                                    </div>
                                    <span class="text-xs font-black text-red-400"><?= $currency ?> <?= number_format($tp['total_revenue'], 0) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Recent Sales Feed -->
        <div class="bg-stone-900 border border-stone-800 rounded-2xl p-6 shadow-lg">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-extrabold text-white text-base">Recent Cafe Orders</h3>
                    <p class="text-xs text-stone-400">Latest transactions processed at the counter</p>
                </div>
                <a href="<?= BASE_URL ?>/orders.php" class="text-xs font-bold text-red-400 hover:text-red-300 hover:underline">
                    View All Orders &rarr;
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-stone-950 text-stone-400 font-bold uppercase text-[10px] tracking-wider rounded-xl">
                        <tr>
                            <th class="p-3 rounded-l-xl">Invoice</th>
                            <th class="p-3">Type & Table</th>
                            <th class="p-3">Cashier</th>
                            <th class="p-3">Payment</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 text-right">Total</th>
                            <th class="p-3 text-center rounded-r-xl">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800">
                        <?php if (empty($recentOrders)): ?>
                            <tr>
                                <td colspan="7" class="p-6 text-center text-stone-500">No recent orders.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $ro): ?>
                            <tr class="hover:bg-stone-950/80 transition">
                                <td class="p-3 font-bold text-white"><?= e($ro['invoice_no']) ?><br><span class="text-[10px] font-normal text-stone-500"><?= date('h:i A', strtotime($ro['created_at'])) ?></span></td>
                                <td class="p-3">
                                    <span class="font-semibold text-stone-200 uppercase text-[11px]"><?= e(str_replace('_', ' ', $ro['order_type'])) ?></span>
                                    <?php if (!empty($ro['table_name'])): ?>
                                        <span class="block text-[10px] text-red-400 font-medium"><i class="fa-solid fa-chair mr-1"></i><?= e($ro['table_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-stone-400"><?= e($ro['cashier_name'] ?? 'Staff') ?></td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-stone-800 text-stone-300 border border-stone-700"><?= e($ro['payment_method']) ?></span>
                                </td>
                                <td class="p-3">
                                    <?php
                                    $stClass = 'bg-stone-800 text-stone-300';
                                    if ($ro['order_status'] === 'preparing') $stClass = 'bg-amber-950 text-amber-300 border border-amber-800';
                                    if ($ro['order_status'] === 'ready') $stClass = 'bg-emerald-950 text-emerald-300 border border-emerald-800';
                                    if ($ro['order_status'] === 'completed') $stClass = 'bg-emerald-900/60 text-emerald-300 border border-emerald-700';
                                    if ($ro['order_status'] === 'cancelled') $stClass = 'bg-rose-950 text-rose-300 border border-rose-800';
                                    ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase <?= $stClass ?>"><?= e($ro['order_status']) ?></span>
                                </td>
                                <td class="p-3 text-right font-black text-red-400"><?= $currency ?> <?= number_format($ro['grand_total'], 2) ?></td>
                                <td class="p-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <a href="<?= BASE_URL ?>/order_view.php?id=<?= $ro['id'] ?>" class="p-1.5 hover:bg-stone-800 rounded-lg text-stone-300 transition" title="View Details">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>/print_receipt.php?id=<?= $ro['id'] ?>" target="_blank" class="p-1.5 hover:bg-stone-800 rounded-lg text-red-400 transition" title="Print Thermal Slip">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<script>
  const ctx = document.getElementById('salesTrendChart').getContext('2d');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode($chartLabels) ?>,
      datasets: [{
        label: 'Gross Sales (<?= $currency ?>)',
        data: <?= json_encode($chartValues) ?>,
        borderColor: '#dc2626',
        backgroundColor: 'rgba(220, 38, 38, 0.12)',
        borderWidth: 3,
        fill: true,
        tension: 0.35,
        pointBackgroundColor: '#dc2626',
        pointRadius: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(255,255,255,0.05)' },
          ticks: { color: '#a1a1aa' }
        },
        x: {
          grid: { display: false },
          ticks: { color: '#a1a1aa' }
        }
      }
    }
  });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
