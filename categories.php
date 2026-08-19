<?php
/**
 * The Hide Out Cafe - Category Management
 */
require_once __DIR__ . '/config/functions.php';
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$title = 'Categories';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_category') {
        $id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $name = sanitize($_POST['name']);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
        $icon = sanitize($_POST['icon'] ?? 'fa-mug-hot');
        $sort = (int)($_POST['sort_order'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'active');

        if ($id) {
            db()->query(
                "UPDATE categories SET name = :name, slug = :slug, icon = :icon, sort_order = :sort, status = :st WHERE id = :id",
                [':name' => $name, ':slug' => $slug, ':icon' => $icon, ':sort' => $sort, ':st' => $status, ':id' => $id]
            );
            setFlash('success', "Category '$name' updated.");
        } else {
            db()->query(
                "INSERT INTO categories (name, slug, icon, sort_order, status) VALUES (:name, :slug, :icon, :sort, :st)",
                [':name' => $name, ':slug' => $slug, ':icon' => $icon, ':sort' => $sort, ':st' => $status]
            );
            setFlash('success', "New category '$name' added.");
        }
        header("Location: " . BASE_URL . "/categories.php");
        exit;
    }

    if ($action === 'delete_category') {
        $id = (int)$_POST['category_id'];
        db()->query("DELETE FROM categories WHERE id = :id", [':id' => $id]);
        setFlash('success', 'Category removed.');
        header("Location: " . BASE_URL . "/categories.php");
        exit;
    }
}

$categories = db()->fetchAll("SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id = p.category_id GROUP BY c.id ORDER BY c.sort_order ASC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#0c0c0e]">
    <div class="max-w-6xl mx-auto space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-white">Menu Categories</h2>
                <p class="text-xs text-stone-400">Organize your beverage and food items into ribbon groups</p>
            </div>
            <button onclick="openCategoryModal()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-2xl text-xs shadow-lg shadow-red-900/40 transition">
                <i class="fa-solid fa-plus"></i> Add Category
            </button>
        </div>

        <div class="bg-stone-900 border border-stone-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-stone-950 text-stone-400 font-bold uppercase text-[10px] tracking-wider border-b border-stone-800">
                        <tr>
                            <th class="p-3.5">Category Name</th>
                            <th class="p-3.5">FontAwesome Icon</th>
                            <th class="p-3.5 text-center">Items Count</th>
                            <th class="p-3.5 text-center">Sort Order</th>
                            <th class="p-3.5 text-center">Status</th>
                            <th class="p-3.5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800">
                        <?php foreach ($categories as $c): ?>
                        <tr class="hover:bg-stone-950/80 transition">
                            <td class="p-3.5 font-bold text-white flex items-center gap-3">
                                <div class="w-8 h-8 rounded-xl bg-red-950 text-red-400 border border-red-900 flex items-center justify-center text-sm shadow-xs">
                                    <i class="fa-solid <?= e($c['icon']) ?>"></i>
                                </div>
                                <span><?= e($c['name']) ?></span>
                            </td>
                            <td class="p-3.5 font-mono text-stone-400 text-xs"><?= e($c['icon']) ?></td>
                            <td class="p-3.5 text-center font-bold text-stone-300"><?= (int)$c['product_count'] ?> products</td>
                            <td class="p-3.5 text-center text-stone-400 font-semibold"><?= (int)$c['sort_order'] ?></td>
                            <td class="p-3.5 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $c['status'] === 'active' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-stone-800 text-stone-400' ?>">
                                    <?= e($c['status']) ?>
                                </span>
                            </td>
                            <td class="p-3.5 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button onclick="editCategory(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)" class="p-1.5 hover:bg-stone-800 text-stone-300 rounded-lg transition" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST" action="categories.php" onsubmit="return confirm('Delete category <?= addslashes($c['name']) ?>?');" class="inline">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
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

<!-- Category Modal -->
<div id="category-form-modal" class="modal-overlay fixed inset-0 bg-black/80 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-stone-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-stone-800">
        <div class="flex items-center justify-between pb-3 border-b border-stone-800">
            <h3 id="category-modal-title" class="font-extrabold text-white text-base">Add Menu Category</h3>
            <button onclick="closeModal('category-form-modal')" class="text-stone-400 hover:text-white p-1"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="categories.php" class="py-4 space-y-3">
            <input type="hidden" name="action" value="save_category">
            <input type="hidden" name="category_id" id="cat_form_id">

            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1">Category Name *</label>
                <input type="text" name="name" id="cat_form_name" placeholder="e.g. Specialty Tea" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Icon Class</label>
                    <input type="text" name="icon" id="cat_form_icon" value="fa-mug-hot" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white font-mono">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" id="cat_form_sort" value="0" class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1">Status</label>
                <select name="status" id="cat_form_status" class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="pt-2 flex gap-2">
                <button type="button" onclick="closeModal('category-form-modal')" class="flex-1 py-2.5 bg-stone-800 hover:bg-stone-700 text-stone-300 font-bold rounded-xl text-xs">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs shadow-md">Save Category</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCategoryModal() {
  document.getElementById('category-modal-title').textContent = 'Add Menu Category';
  document.getElementById('cat_form_id').value = '';
  document.getElementById('cat_form_name').value = '';
  document.getElementById('cat_form_icon').value = 'fa-mug-hot';
  document.getElementById('cat_form_sort').value = '0';
  document.getElementById('cat_form_status').value = 'active';
  openModal('category-form-modal');
}
function editCategory(c) {
  document.getElementById('category-modal-title').textContent = 'Edit Category: ' + c.name;
  document.getElementById('cat_form_id').value = c.id;
  document.getElementById('cat_form_name').value = c.name;
  document.getElementById('cat_form_icon').value = c.icon;
  document.getElementById('cat_form_sort').value = c.sort_order;
  document.getElementById('cat_form_status').value = c.status;
  openModal('category-form-modal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
