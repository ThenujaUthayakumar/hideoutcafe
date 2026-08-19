<?php
/**
 * The Hide Out Cafe - Modifiers & Add-ons (Black & Red Theme)
 */
require_once __DIR__ . '/config/functions.php';
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$title = 'Modifiers & Add-ons';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? 'Rs.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_modifier') {
        $id = !empty($_POST['modifier_id']) ? (int)$_POST['modifier_id'] : null;
        $name = sanitize($_POST['name']);
        $category = sanitize($_POST['category'] ?? 'Milk Option');
        $price = (float)$_POST['price'];
        $status = sanitize($_POST['status'] ?? 'active');

        if ($id) {
            db()->query(
                "UPDATE modifiers SET name = :name, category = :cat, price = :price, status = :st WHERE id = :id",
                [':name' => $name, ':cat' => $category, ':price' => $price, ':st' => $status, ':id' => $id]
            );
            setFlash('success', "Modifier '$name' updated.");
        } else {
            db()->query(
                "INSERT INTO modifiers (name, category, price, status) VALUES (:name, :cat, :price, :st)",
                [':name' => $name, ':cat' => $category, ':price' => $price, ':st' => $status]
            );
            setFlash('success', "New modifier '$name' added.");
        }
        header("Location: " . BASE_URL . "/modifiers.php");
        exit;
    }

    if ($action === 'delete_modifier') {
        $id = (int)$_POST['modifier_id'];
        db()->query("DELETE FROM modifiers WHERE id = :id", [':id' => $id]);
        setFlash('success', 'Modifier deleted.');
        header("Location: " . BASE_URL . "/modifiers.php");
        exit;
    }
}

$modifiers = db()->fetchAll("SELECT * FROM modifiers ORDER BY category ASC, name ASC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#0c0c0e]">
    <div class="max-w-6xl mx-auto space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-white">Modifiers & Add-ons</h2>
                <p class="text-xs text-stone-400">Configure plant milks, espresso shots, artisanal syrups, and sweetness levels</p>
            </div>
            <button onclick="openModifierModal()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-2xl text-xs shadow-lg shadow-red-900/40 transition">
                <i class="fa-solid fa-plus"></i> Add Modifier
            </button>
        </div>

        <div class="bg-stone-900 border border-stone-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-stone-950 text-stone-400 font-bold uppercase text-[10px] tracking-wider border-b border-stone-800">
                        <tr>
                            <th class="p-3.5">Add-on Name</th>
                            <th class="p-3.5">Category Group</th>
                            <th class="p-3.5 text-right">Extra Price</th>
                            <th class="p-3.5 text-center">Status</th>
                            <th class="p-3.5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800">
                        <?php foreach ($modifiers as $m): ?>
                        <tr class="hover:bg-stone-950/80 transition">
                            <td class="p-3.5 font-bold text-white"><?= e($m['name']) ?></td>
                            <td class="p-3.5">
                                <span class="px-2.5 py-0.5 rounded-lg bg-red-950 text-red-300 border border-red-900 font-bold text-[10px]"><?= e($m['category']) ?></span>
                            </td>
                            <td class="p-3.5 text-right font-black text-red-400 text-sm">
                                <?= $m['price'] > 0 ? $currency . ' ' . number_format($m['price'], 2) : '<span class="text-emerald-400 font-bold">Free</span>' ?>
                            </td>
                            <td class="p-3.5 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $m['status'] === 'active' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-stone-800 text-stone-400' ?>">
                                    <?= e($m['status']) ?>
                                </span>
                            </td>
                            <td class="p-3.5 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button onclick="editModifier(<?= htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8') ?>)" class="p-1.5 hover:bg-stone-800 text-stone-300 rounded-lg transition" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST" action="modifiers.php" onsubmit="return confirm('Delete modifier <?= addslashes($m['name']) ?>?');" class="inline">
                                        <input type="hidden" name="action" value="delete_modifier">
                                        <input type="hidden" name="modifier_id" value="<?= $m['id'] ?>">
                                        <button type="submit" class="p-1.5 hover:bg-rose-950/60 text-rose-400 rounded-lg transition" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<!-- Modifier Modal -->
<div id="modifier-form-modal" class="modal-overlay fixed inset-0 bg-black/80 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-stone-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-stone-800">
        <div class="flex items-center justify-between pb-3 border-b border-stone-800">
            <h3 id="modifier-modal-title" class="font-extrabold text-white text-base">Add Modifier</h3>
            <button onclick="closeModal('modifier-form-modal')" class="text-stone-400 hover:text-white p-1"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="modifiers.php" class="py-4 space-y-3">
            <input type="hidden" name="action" value="save_modifier">
            <input type="hidden" name="modifier_id" id="mod_form_id">

            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1">Modifier Name *</label>
                <input type="text" name="name" id="mod_form_name" placeholder="e.g. Oat Milk" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1">Category Group *</label>
                <select name="category" id="mod_form_cat" required class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                    <option value="Milk Option">Milk Option</option>
                    <option value="Coffee Add-on">Coffee Add-on</option>
                    <option value="Flavors & Syrups">Flavors & Syrups</option>
                    <option value="Topping">Topping</option>
                    <option value="Sweetness">Sweetness</option>
                    <option value="Temperature">Temperature</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1">Extra Price (<?= $currency ?>) *</label>
                <input type="number" step="0.01" min="0" name="price" id="mod_form_price" value="0.00" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs font-bold text-red-400">
            </div>

            <div class="pt-2 flex gap-2">
                <button type="button" onclick="closeModal('modifier-form-modal')" class="flex-1 py-2.5 bg-stone-800 hover:bg-stone-700 text-stone-300 font-bold rounded-xl text-xs">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs shadow-md">Save Modifier</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModifierModal() {
  document.getElementById('modifier-modal-title').textContent = 'Add Modifier';
  document.getElementById('mod_form_id').value = '';
  document.getElementById('mod_form_name').value = '';
  document.getElementById('mod_form_price').value = '0.00';
  openModal('modifier-form-modal');
}
function editModifier(m) {
  document.getElementById('modifier-modal-title').textContent = 'Edit Modifier: ' + m.name;
  document.getElementById('mod_form_id').value = m.id;
  document.getElementById('mod_form_name').value = m.name;
  document.getElementById('mod_form_cat').value = m.category;
  document.getElementById('mod_form_price').value = m.price;
  openModal('modifier-form-modal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
