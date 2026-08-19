<?php
/**
 * The Hide Out Cafe - Floor Plan & Table Management
 */
require_once __DIR__ . '/config/functions.php';
requireAuth();

$title = 'Floor & Table Management';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? 'Rs.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole([ROLE_ADMIN, ROLE_MANAGER]);
    $action = $_POST['action'] ?? '';

    if ($action === 'save_table') {
        $id = !empty($_POST['table_id']) ? (int)$_POST['table_id'] : null;
        $num = sanitize($_POST['table_number']);
        $name = sanitize($_POST['name']);
        $area = sanitize($_POST['floor_area'] ?? 'Indoor Lounge');
        $cap = (int)$_POST['seating_capacity'];

        if ($id) {
            db()->query(
                "UPDATE tables SET table_number = :num, name = :name, floor_area = :area, seating_capacity = :cap WHERE id = :id",
                [':num' => $num, ':name' => $name, ':area' => $area, ':cap' => $cap, ':id' => $id]
            );
            setFlash('success', "Table $name updated.");
        } else {
            db()->query(
                "INSERT INTO tables (table_number, name, floor_area, seating_capacity, status) VALUES (:num, :name, :area, :cap, 'available')",
                [':num' => $num, ':name' => $name, ':area' => $area, ':cap' => $cap]
            );
            setFlash('success', "New table $name created.");
        }
        header("Location: " . BASE_URL . "/tables.php");
        exit;
    }

    if ($action === 'clear_table') {
        $id = (int)$_POST['table_id'];
        db()->query("UPDATE tables SET status = 'available', current_order_id = NULL WHERE id = :id", [':id' => $id]);
        setFlash('success', 'Table marked available.');
        header("Location: " . BASE_URL . "/tables.php");
        exit;
    }
}

$tables = db()->fetchAll("SELECT * FROM tables ORDER BY floor_area ASC, table_number ASC");

$areas = [];
foreach ($tables as $t) {
    $areas[$t['floor_area']][] = $t;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#0c0c0e]">
    <div class="max-w-7xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-white">Cafe Floor & Tables</h2>
                <p class="text-xs text-stone-400">Live floor seating map and table occupancy monitor</p>
            </div>
            <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
            <button onclick="openAddTableModal()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-2xl text-xs shadow-lg shadow-red-900/40 transition">
                <i class="fa-solid fa-plus"></i> Add New Table
            </button>
            <?php endif; ?>
        </div>

        <!-- Legend -->
        <div class="bg-stone-900 border border-stone-800 p-4 rounded-2xl flex flex-wrap items-center gap-4 text-xs font-bold">
            <span class="text-stone-400">Status Legend:</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-emerald-500"></span> Available</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-red-500"></span> Occupied (Dining)</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-amber-500"></span> Billed / Checkout</span>
        </div>

        <!-- Area Sections -->
        <?php foreach ($areas as $areaName => $areaTables): ?>
        <div class="space-y-3">
            <h3 class="font-extrabold text-white text-base flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-red-500"></i> <?= e($areaName) ?>
            </h3>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <?php foreach ($areaTables as $t): ?>
                    <?php
                    $border = 'border-stone-800 hover:border-red-500';
                    $badge = 'bg-emerald-950 text-emerald-300 border border-emerald-800';
                    if ($t['status'] === 'occupied') { $border = 'border-red-600 bg-red-950/40'; $badge = 'bg-red-950 text-red-300 border border-red-800'; }
                    if ($t['status'] === 'billed') { $border = 'border-amber-600 bg-amber-950/40'; $badge = 'bg-amber-950 text-amber-300 border border-amber-800'; }
                    ?>
                    <div class="bg-stone-900 border-2 <?= $border ?> rounded-3xl p-4 flex flex-col justify-between shadow-lg transition duration-150">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-xs font-bold text-stone-400"><?= e($t['table_number']) ?></span>
                                <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase <?= $badge ?>"><?= e($t['status']) ?></span>
                            </div>
                            <h4 class="font-black text-sm text-white mt-2"><?= e($t['name']) ?></h4>
                            <p class="text-xs text-stone-400 mt-1"><i class="fa-solid fa-chair mr-1 text-red-500"></i> <?= (int)$t['seating_capacity'] ?> Seats</p>
                        </div>

                        <div class="mt-4 pt-3 border-t border-stone-800 space-y-1.5">
                            <a href="<?= BASE_URL ?>/pos.php?table_id=<?= $t['id'] ?>" class="w-full text-center py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-1 shadow-md transition">
                                <i class="fa-solid fa-plus text-[10px]"></i> Take Order
                            </a>
                            <?php if ($t['status'] !== 'available'): ?>
                                <form method="POST" action="tables.php" onsubmit="return confirm('Free this table?');">
                                    <input type="hidden" name="action" value="clear_table">
                                    <input type="hidden" name="table_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="w-full py-1.5 text-stone-400 hover:text-white bg-stone-800 hover:bg-stone-700 rounded-xl text-[11px] font-semibold transition">
                                        Release Table
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</main>

<!-- Add Table Modal -->
<div id="table-form-modal" class="modal-overlay fixed inset-0 bg-black/80 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-stone-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-stone-800">
        <div class="flex items-center justify-between pb-3 border-b border-stone-800">
            <h3 class="font-extrabold text-white text-base">Add New Cafe Table</h3>
            <button onclick="closeModal('table-form-modal')" class="text-stone-400 hover:text-white p-1"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="tables.php" class="py-4 space-y-3">
            <input type="hidden" name="action" value="save_table">
            <input type="hidden" name="table_id" id="tbl_form_id">

            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1">Table Code *</label>
                <input type="text" name="table_number" id="tbl_form_num" placeholder="e.g. T-13" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1">Display Name *</label>
                <input type="text" name="name" id="tbl_form_name" placeholder="e.g. Table 13 (Garden)" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Floor Area</label>
                    <select name="floor_area" id="tbl_form_area" class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                        <option value="Indoor Lounge">Indoor Lounge</option>
                        <option value="Main Hall">Main Hall</option>
                        <option value="Espresso Bar">Espresso Bar</option>
                        <option value="Outdoor Terrace">Outdoor Terrace</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Capacity (Seats)</label>
                    <input type="number" name="seating_capacity" id="tbl_form_cap" value="4" min="1" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                </div>
            </div>

            <div class="pt-2 flex gap-2">
                <button type="button" onclick="closeModal('table-form-modal')" class="flex-1 py-2.5 bg-stone-800 hover:bg-stone-700 text-stone-300 font-bold rounded-xl text-xs">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs shadow-md">Save Table</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddTableModal() {
  document.getElementById('tbl_form_id').value = '';
  document.getElementById('tbl_form_num').value = '';
  document.getElementById('tbl_form_name').value = '';
  document.getElementById('tbl_form_cap').value = '4';
  openModal('table-form-modal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
