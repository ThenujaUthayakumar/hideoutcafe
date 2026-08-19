<?php
/**
 * The Hide Out Cafe - Kitchen Display System (KDS) & Barista Queue
 */
require_once __DIR__ . '/config/functions.php';
requireAuth();

$title = 'Kitchen Display System';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? 'Rs.';

$activeOrders = db()->fetchAll(
    "SELECT o.*, t.name as table_name, u.name as cashier_name 
     FROM orders o 
     LEFT JOIN tables t ON o.table_id = t.id 
     LEFT JOIN users u ON o.user_id = u.id 
     WHERE o.order_status IN ('pending', 'preparing', 'ready') 
     ORDER BY o.created_at ASC"
);

$orderIds = array_column($activeOrders, 'id');
$itemsByOrder = [];
if (!empty($orderIds)) {
    $in = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = pdo()->prepare("SELECT * FROM order_items WHERE order_id IN ($in)");
    $stmt->execute($orderIds);
    $items = $stmt->fetchAll();
    foreach ($items as $it) {
        $itemsByOrder[$it['order_id']][] = $it;
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#0c0c0e]">
    <div class="max-w-7xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-red-600 text-white flex items-center justify-center text-xl shadow-lg shadow-red-900/40">
                    <i class="fa-solid fa-kitchen-set"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-white">Live Kitchen / Barista Display</h2>
                    <p class="text-xs text-stone-400">Real-time order tickets for baristas and kitchen staff</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-extrabold bg-red-950 text-red-400 border border-red-800">
                    <span class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span> Live Auto-Refresh
                </span>
                <button onclick="location.reload()" class="p-2.5 bg-stone-900 hover:bg-stone-800 border border-stone-800 rounded-xl text-stone-300 transition">
                    <i class="fa-solid fa-arrow-rotate-right"></i>
                </button>
            </div>
        </div>

        <div id="kds-tickets-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php if (empty($activeOrders)): ?>
                <div class="col-span-full py-24 text-center bg-stone-900 border border-stone-800 rounded-3xl">
                    <i class="fa-solid fa-mug-saucer text-6xl text-stone-700 mb-4"></i>
                    <h3 class="text-lg font-bold text-stone-300">All Kitchen Orders Cleared!</h3>
                    <p class="text-xs text-stone-500 mt-1">New incoming orders from POS terminal will appear here automatically.</p>
                </div>
            <?php else: ?>
                <?php foreach ($activeOrders as $ord): ?>
                    <?php
                    $items = $itemsByOrder[$ord['id']] ?? [];
                    $minutesAgo = round((time() - strtotime($ord['created_at'])) / 60);
                    $topBorder = 'border-t-4 border-t-red-500';
                    if ($ord['order_status'] === 'preparing') $topBorder = 'border-t-4 border-t-amber-500';
                    if ($ord['order_status'] === 'ready') $topBorder = 'border-t-4 border-t-emerald-500';
                    ?>
                    <div id="kds-order-<?= $ord['id'] ?>" class="bg-stone-900 border border-stone-800 rounded-2xl shadow-xl flex flex-col justify-between overflow-hidden <?= $topBorder ?>">
                        <div class="p-4 border-b border-stone-800 bg-stone-950/80">
                            <div class="flex items-center justify-between">
                                <span class="font-mono font-bold text-xs text-stone-300"><?= e($ord['invoice_no']) ?></span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider <?= $minutesAgo > 10 ? 'bg-red-950 text-red-400 border border-red-800 animate-pulse' : 'bg-stone-800 text-stone-400' ?>">
                                    <?= $minutesAgo ?>m ago
                                </span>
                            </div>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="font-extrabold text-sm text-white uppercase"><?= e(str_replace('_', ' ', $ord['order_type'])) ?></span>
                                <?php if (!empty($ord['table_name'])): ?>
                                    <span class="px-2 py-0.5 bg-red-950 text-red-400 border border-red-900 rounded-lg text-xs font-bold">
                                        <i class="fa-solid fa-chair mr-1"></i><?= e($ord['table_name']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="p-4 flex-1 overflow-y-auto space-y-3 max-h-64">
                            <?php foreach ($items as $it): ?>
                            <div class="flex items-start justify-between border-b border-stone-800/80 pb-2.5 last:border-0">
                                <div class="flex-1 pr-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-lg bg-red-600 text-white font-black text-xs flex items-center justify-center flex-shrink-0"><?= (int)$it['quantity'] ?>x</span>
                                        <span class="font-extrabold text-white text-xs"><?= e($it['product_name']) ?></span>
                                    </div>
                                    <?php if (!empty($it['variant_name'])): ?>
                                        <span class="block text-[11px] text-stone-400 font-semibold ml-8"><?= e($it['variant_name']) ?></span>
                                    <?php endif; ?>
                                    <?php
                                    if (!empty($it['modifiers_json'])) {
                                        $mods = json_decode($it['modifiers_json'], true);
                                        if (!empty($mods)) {
                                            echo '<div class="ml-8 mt-1 flex flex-wrap gap-1">';
                                            foreach ($mods as $m) {
                                                echo '<span class="text-[10px] bg-red-950 text-red-300 border border-red-900 px-1.5 py-0.2 rounded font-medium">+ ' . e($m['name']) . '</span>';
                                            }
                                            echo '</div>';
                                        }
                                    }
                                    if (!empty($it['notes'])) {
                                        echo '<p class="text-[11px] text-rose-400 font-bold italic ml-8 mt-0.5"><i class="fa-solid fa-bell mr-1"></i>' . e($it['notes']) . '</p>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="p-3 bg-stone-950 border-t border-stone-800 flex gap-1.5">
                            <?php if ($ord['order_status'] === 'pending'): ?>
                                <button onclick="updateKdsStatus(<?= $ord['id'] ?>, 'preparing')" class="flex-1 py-2.5 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-xs shadow-md transition">
                                    <i class="fa-solid fa-fire mr-1"></i> Start Prep
                                </button>
                            <?php elseif ($ord['order_status'] === 'preparing'): ?>
                                <button onclick="updateKdsStatus(<?= $ord['id'] ?>, 'ready')" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs shadow-md transition">
                                    <i class="fa-solid fa-bell-concierge mr-1"></i> Mark Ready
                                </button>
                            <?php elseif ($ord['order_status'] === 'ready'): ?>
                                <button onclick="updateKdsStatus(<?= $ord['id'] ?>, 'completed')" class="flex-1 py-2.5 bg-stone-700 hover:bg-stone-600 text-white font-bold rounded-xl text-xs shadow-md transition">
                                    <i class="fa-solid fa-circle-check mr-1"></i> Served / Complete
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</main>

<script>
  async function updateKdsStatus(orderId, status) {
    try {
      const res = await fetch('<?= BASE_URL ?>/api/update_kds.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, status: status })
      });
      const data = await res.json();
      if (data.success) {
        SoundFX.bell();
        Toast.success(data.message);
        setTimeout(() => location.reload(), 400);
      } else {
        Toast.error(data.message);
      }
    } catch (e) {
      Toast.error('Failed to update kitchen status');
    }
  }

  // Auto-refresh every 15s
  setInterval(() => {
    location.reload();
  }, 15000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
