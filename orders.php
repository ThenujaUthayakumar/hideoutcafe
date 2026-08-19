<?php
/**
 * The Hide Out Cafe - Sales History & Orders Management
 */
require_once __DIR__ . '/config/functions.php';
requireAuth();

$title = 'Orders & Sales';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? 'Rs.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole([ROLE_ADMIN, ROLE_MANAGER]);
    $action = $_POST['action'] ?? '';

    if ($action === 'cancel_order') {
        $orderId = (int)$_POST['order_id'];
        $reason = sanitize($_POST['cancel_reason'] ?? 'Voided by manager');

        $order = db()->fetchOne("SELECT * FROM orders WHERE id = :id", [':id' => $orderId]);
        if ($order) {
            // Restore inventory
            $items = db()->fetchAll("SELECT * FROM order_items WHERE order_id = :id", [':id' => $orderId]);
            foreach ($items as $it) {
                db()->query(
                    "UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :pid AND track_stock = 1",
                    [':qty' => $it['quantity'], ':pid' => $it['product_id']]
                );
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
        header("Location: " . BASE_URL . "/orders.php");
        exit;
    }
}

// Filter query
$dateFilter = $_GET['date'] ?? date('Y-m-d');
$typeFilter = $_GET['type'] ?? 'all';
$payFilter  = $_GET['payment'] ?? 'all';
$search     = trim($_GET['q'] ?? '');

$sql = "SELECT o.*, t.name as table_name, u.name as cashier_name, c.name as customer_name 
        FROM orders o 
        LEFT JOIN tables t ON o.table_id = t.id 
        LEFT JOIN users u ON o.user_id = u.id 
        LEFT JOIN customers c ON o.customer_id = c.id 
        WHERE 1=1";
$params = [];

if (!empty($dateFilter)) {
    $sql .= " AND DATE(o.created_at) = :d";
    $params[':d'] = $dateFilter;
}
if ($typeFilter !== 'all') {
    $sql .= " AND o.order_type = :t";
    $params[':t'] = $typeFilter;
}
if ($payFilter !== 'all') {
    $sql .= " AND o.payment_method = :p";
    $params[':p'] = $payFilter;
}
if (!empty($search)) {
    $sql .= " AND (o.invoice_no LIKE :q OR c.name LIKE :q OR c.phone LIKE :q)";
    $params[':q'] = "%$search%";
}

$sql .= " ORDER BY o.id DESC LIMIT 100";
$orders = db()->fetchAll($sql, $params);

// Daily summary
$dailySummary = db()->fetchOne(
    "SELECT 
        COUNT(id) as total_count,
        COALESCE(SUM(grand_total), 0) as total_sales,
        COALESCE(SUM(tax_amount), 0) as total_tax,
        COALESCE(SUM(discount_amount), 0) as total_discount
     FROM orders 
     WHERE DATE(created_at) = :d AND payment_status = 'paid' AND order_status != 'cancelled'",
    [':d' => $dateFilter]
);

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
            <form method="GET" action="orders.php" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 text-xs">
                <div>
                    <label class="block font-bold text-stone-300 mb-1">Date</label>
                    <input type="date" name="date" value="<?= e($dateFilter) ?>" class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-white">
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
                        <option value="upi" <?= $payFilter === 'upi' ? 'selected' : '' ?>>QR / UPI</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-stone-300 mb-1">Search Invoice/Customer</label>
                    <input type="text" name="q" value="<?= e($search) ?>" placeholder="e.g. HOC-..." class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-white">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl shadow-md transition">
                        <i class="fa-solid fa-filter mr-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- KPI Strip -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-stone-900 border border-stone-800 p-4 rounded-2xl">
                <span class="text-[10px] font-bold text-stone-400 uppercase">Filtered Sales</span>
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
                <span class="text-[10px] font-bold text-stone-400 uppercase">Tax Collected</span>
                <div class="text-lg font-black text-white mt-0.5"><?= $currency ?> <?= number_format($dailySummary['total_tax'] ?? 0, 2) ?></div>
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
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-stone-800 text-stone-300 border border-stone-700"><?= e($o['payment_method']) ?></span>
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
                                        <a href="<?= BASE_URL ?>/order_view.php?id=<?= $o['id'] ?>" class="p-1.5 hover:bg-stone-800 rounded-lg text-stone-300 transition" title="View Full Invoice">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>/print_receipt.php?id=<?= $o['id'] ?>" target="_blank" class="p-1.5 hover:bg-stone-800 rounded-lg text-red-400 transition" title="Reprint Thermal Slip">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                        <?php if ($o['order_status'] !== 'cancelled' && hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
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
        </div>

    </div>
</main>

<form id="void-order-form" method="POST" action="orders.php" class="hidden">
    <input type="hidden" name="action" value="cancel_order">
    <input type="hidden" name="order_id" id="void_order_id">
    <input type="hidden" name="cancel_reason" id="void_cancel_reason">
</form>

<script>
async function promptVoidOrder(id, inv) {
  const { value: reason } = await Swal.fire({
    title: `Void Order #${inv}?`,
    input: 'text',
    inputLabel: 'Reason for Cancellation / Refund',
    inputPlaceholder: 'e.g. Wrong items entered / Customer request',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Yes, Cancel & Refund'
  });

  if (reason) {
    document.getElementById('void_order_id').value = id;
    document.getElementById('void_cancel_reason').value = reason;
    document.getElementById('void-order-form').submit();
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
