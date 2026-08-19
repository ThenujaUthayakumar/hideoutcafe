<?php
/**
 * The Hide Out Cafe - Product Menu & Inventory
 */
require_once __DIR__ . '/config/functions.php';
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$title = 'Products Catalog';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? 'Rs.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_product') {
        $id = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
        $name = sanitize($_POST['name']);
        $code = sanitize($_POST['code']);
        $catId = (int)$_POST['category_id'];
        $price = (float)$_POST['price'];
        $cost = (float)$_POST['cost_price'];
        $desc = sanitize($_POST['description'] ?? '');
        $trackStock = isset($_POST['track_stock']) ? 1 : 0;
        $stock = (int)($_POST['stock_quantity'] ?? 0);
        $alert = (int)($_POST['alert_quantity'] ?? 10);
        $status = sanitize($_POST['status'] ?? 'active');

        if ($id) {
            db()->query(
                "UPDATE products SET category_id = :cat, name = :name, code = :code, description = :desc, price = :price, cost_price = :cost, track_stock = :ts, stock_quantity = :sq, alert_quantity = :aq, status = :st WHERE id = :id",
                [':cat' => $catId, ':name' => $name, ':code' => $code, ':desc' => $desc, ':price' => $price, ':cost' => $cost, ':ts' => $trackStock, ':sq' => $stock, ':aq' => $alert, ':st' => $status, ':id' => $id]
            );
            setFlash('success', "Product '$name' updated.");
        } else {
            db()->query(
                "INSERT INTO products (category_id, name, code, description, price, cost_price, track_stock, stock_quantity, alert_quantity, status) VALUES (:cat, :name, :code, :desc, :price, :cost, :ts, :sq, :aq, :st)",
                [':cat' => $catId, ':name' => $name, ':code' => $code, ':desc' => $desc, ':price' => $price, ':cost' => $cost, ':ts' => $trackStock, ':sq' => $stock, ':aq' => $alert, ':st' => $status]
            );
            $id = (int)db()->lastInsertId();
            setFlash('success', "New product '$name' added.");
        }

        // Save Variants
        if (!empty($_POST['variants'])) {
            db()->query("DELETE FROM product_variants WHERE product_id = :pid", [':pid' => $id]);
            foreach ($_POST['variants'] as $v) {
                if (!empty(trim($v['name']))) {
                    db()->query(
                        "INSERT INTO product_variants (product_id, variant_name, extra_price) VALUES (:pid, :vname, :extra)",
                        [':pid' => $id, ':vname' => sanitize($v['name']), ':extra' => (float)($v['price'] ?? 0)]
                    );
                }
            }
        }

        header("Location: " . BASE_URL . "/products.php");
        exit;
    }

    if ($action === 'delete_product') {
        $id = (int)$_POST['product_id'];
        db()->query("DELETE FROM products WHERE id = :id", [':id' => $id]);
        setFlash('success', 'Product deleted.');
        header("Location: " . BASE_URL . "/products.php");
        exit;
    }
}

$categories = db()->fetchAll("SELECT * FROM categories ORDER BY sort_order ASC, name ASC");
$catFilter = $_GET['cat'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE 1=1";
$params = [];
if ($catFilter !== 'all') {
    $sql .= " AND p.category_id = :cat";
    $params[':cat'] = $catFilter;
}
if (!empty($search)) {
    $sql .= " AND (p.name LIKE :q OR p.code LIKE :q)";
    $params[':q'] = "%$search%";
}
$sql .= " ORDER BY c.sort_order ASC, p.name ASC";
$products = db()->fetchAll($sql, $params);

// Fetch Variants
$variantsMap = [];
$variantsRaw = db()->fetchAll("SELECT * FROM product_variants ORDER BY extra_price ASC");
foreach ($variantsRaw as $vr) {
    $variantsMap[$vr['product_id']][] = $vr;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#0c0c0e]">
    <div class="max-w-7xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-white">Menu & Products Catalog</h2>
                <p class="text-xs text-stone-400">Manage coffee beverages, bakery items, prices, and inventory stock</p>
            </div>
            <button onclick="openProductModal()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-2xl text-xs shadow-lg shadow-red-900/40 transition">
                <i class="fa-solid fa-plus"></i> Add New Product
            </button>
        </div>

        <!-- Filter Bar -->
        <div class="bg-stone-900 border border-stone-800 p-4 rounded-2xl shadow-lg">
            <form method="GET" action="products.php" class="flex flex-col sm:flex-row items-center gap-3 text-xs">
                <div class="flex-1 w-full relative">
                    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search product name or SKU code..." class="w-full pl-9 pr-4 py-2 bg-stone-800 border border-stone-700 rounded-xl text-white">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-stone-500"></i>
                </div>
                <div class="w-full sm:w-64">
                    <select name="cat" class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-white">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $catFilter == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl shadow-md">Filter</button>
            </form>
        </div>

        <!-- Products Table -->
        <div class="bg-stone-900 border border-stone-800 rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-stone-950 text-stone-400 font-bold uppercase text-[10px] tracking-wider border-b border-stone-800">
                        <tr>
                            <th class="p-3.5">Item & Code</th>
                            <th class="p-3.5">Category</th>
                            <th class="p-3.5 text-right">Selling Price</th>
                            <th class="p-3.5 text-right">Cost Price</th>
                            <th class="p-3.5">Sizes / Variants</th>
                            <th class="p-3.5 text-center">Stock Level</th>
                            <th class="p-3.5 text-center">Status</th>
                            <th class="p-3.5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800">
                        <?php if (empty($products)): ?>
                            <tr><td colspan="8" class="p-8 text-center text-stone-500">No products found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                            <tr class="hover:bg-stone-950/80 transition">
                                <td class="p-3.5">
                                    <span class="font-bold text-white text-sm"><?= e($p['name']) ?></span>
                                    <span class="block text-[10px] font-mono text-stone-500"><?= e($p['code']) ?></span>
                                </td>
                                <td class="p-3.5">
                                    <span class="px-2.5 py-0.5 rounded-lg bg-red-950 text-red-300 border border-red-900 font-bold text-[10px]"><?= e($p['category_name']) ?></span>
                                </td>
                                <td class="p-3.5 text-right font-black text-red-400 text-sm"><?= $currency ?> <?= number_format($p['price'], 2) ?></td>
                                <td class="p-3.5 text-right font-medium text-stone-400"><?= $currency ?> <?= number_format($p['cost_price'], 2) ?></td>
                                <td class="p-3.5">
                                    <?php if (!empty($variantsMap[$p['id']])): ?>
                                        <div class="flex flex-wrap gap-1">
                                            <?php foreach ($variantsMap[$p['id']] as $v): ?>
                                                <span class="text-[10px] bg-stone-800 border border-stone-700 text-stone-300 px-1.5 py-0.5 rounded"><?= e($v['variant_name']) ?> (+<?= $currency ?> <?= number_format($v['extra_price'], 0) ?>)</span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-stone-500 text-[10px] font-normal">Standard only</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5 text-center">
                                    <?php if ($p['track_stock']): ?>
                                        <span class="font-bold px-2 py-0.5 rounded-full text-[10px] <?= $p['stock_quantity'] <= $p['alert_quantity'] ? 'bg-rose-950 text-rose-300 border border-rose-800 animate-pulse' : 'bg-emerald-950 text-emerald-300 border border-emerald-800' ?>">
                                            <?= $p['stock_quantity'] ?> units
                                        </span>
                                    <?php else: ?>
                                        <span class="text-stone-500 text-[10px]">Unmanaged</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $p['status'] === 'active' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-stone-800 text-stone-400' ?>">
                                        <?= e($p['status']) ?>
                                    </span>
                                </td>
                                <td class="p-3.5 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button onclick="editProduct(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($variantsMap[$p['id']] ?? []), ENT_QUOTES, 'UTF-8') ?>)" class="p-1.5 hover:bg-stone-800 text-stone-300 rounded-lg transition" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <form method="POST" action="products.php" onsubmit="return confirm('Delete product <?= addslashes($p['name']) ?>?');" class="inline">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="p-1.5 hover:bg-rose-950/60 text-rose-400 rounded-lg transition" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
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

<!-- Product Modal -->
<div id="product-form-modal" class="modal-overlay fixed inset-0 bg-black/80 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-stone-900 rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-stone-800 max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between pb-3 border-b border-stone-800">
            <h3 id="product-modal-title" class="font-extrabold text-white text-base">Add New Menu Item</h3>
            <button onclick="closeModal('product-form-modal')" class="text-stone-400 hover:text-white p-1"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="products.php" class="py-4 space-y-3 flex-1 overflow-y-auto">
            <input type="hidden" name="action" value="save_product">
            <input type="hidden" name="product_id" id="prod_form_id">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Product Name *</label>
                    <input type="text" name="name" id="prod_form_name" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">SKU / Code *</label>
                    <input type="text" name="code" id="prod_form_code" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Category *</label>
                    <select name="category_id" id="prod_form_cat" required class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Price (<?= $currency ?>) *</label>
                    <input type="number" step="0.01" min="0" name="price" id="prod_form_price" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs font-bold text-red-400">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Cost Price (<?= $currency ?>)</label>
                    <input type="number" step="0.01" min="0" name="cost_price" id="prod_form_cost" value="0.00" class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1">Description</label>
                <textarea name="description" id="prod_form_desc" rows="2" class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white"></textarea>
            </div>

            <!-- Inventory Tracking -->
            <div class="p-3 bg-stone-950 rounded-2xl border border-stone-800 space-y-2">
                <label class="font-bold text-xs text-stone-300 flex items-center gap-2">
                    <input type="checkbox" name="track_stock" id="prod_form_track_stock" value="1" checked class="w-4 h-4 text-red-600 rounded">
                    <span>Enable Stock Tracking for this item</span>
                </label>
                <div class="grid grid-cols-2 gap-3 pt-1">
                    <div>
                        <label class="block text-[11px] font-bold text-stone-400 mb-1">Current Stock Quantity</label>
                        <input type="number" name="stock_quantity" id="prod_form_stock" value="100" class="w-full px-3 py-1.5 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-stone-400 mb-1">Low Stock Alert Level</label>
                        <input type="number" name="alert_quantity" id="prod_form_alert" value="15" class="w-full px-3 py-1.5 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                    </div>
                </div>
            </div>

            <!-- Variants Sub-Form -->
            <div class="p-3 bg-stone-950 rounded-2xl border border-stone-800 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-stone-300">Sizes / Variants</span>
                    <button type="button" onclick="addVariantRow()" class="text-red-400 hover:text-red-300 text-xs font-bold">+ Add Size</button>
                </div>
                <div id="variants-container" class="space-y-2"></div>
            </div>

            <div class="pt-2 flex gap-2">
                <button type="button" onclick="closeModal('product-form-modal')" class="flex-1 py-2.5 bg-stone-800 hover:bg-stone-700 text-stone-300 font-bold rounded-xl text-xs">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs shadow-md shadow-red-900/40">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
function addVariantRow(name = '', price = '0.00') {
  const container = document.getElementById('variants-container');
  const index = container.children.length;
  const div = document.createElement('div');
  div.className = 'flex items-center gap-2';
  div.innerHTML = `
    <input type="text" name="variants[${index}][name]" value="${name}" placeholder="Size (e.g. Large 16oz)" class="flex-1 px-3 py-1.5 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
    <input type="number" step="0.01" min="0" name="variants[${index}][price]" value="${price}" placeholder="Extra Price" class="w-24 px-3 py-1.5 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
    <button type="button" onclick="this.parentElement.remove()" class="p-1.5 text-rose-400 hover:bg-rose-950 rounded-lg"><i class="fa-solid fa-trash"></i></button>
  `;
  container.appendChild(div);
}

function openProductModal() {
  document.getElementById('product-modal-title').textContent = 'Add New Menu Item';
  document.getElementById('prod_form_id').value = '';
  document.getElementById('prod_form_name').value = '';
  document.getElementById('prod_form_code').value = 'COF-' + Math.floor(Math.random() * 9000 + 1000);
  document.getElementById('prod_form_price').value = '';
  document.getElementById('prod_form_cost').value = '0.00';
  document.getElementById('prod_form_desc').value = '';
  document.getElementById('prod_form_track_stock').checked = true;
  document.getElementById('prod_form_stock').value = '100';
  document.getElementById('prod_form_alert').value = '15';
  document.getElementById('variants-container').innerHTML = '';
  openModal('product-form-modal');
}

function editProduct(p, variants) {
  document.getElementById('product-modal-title').textContent = 'Edit Product: ' + p.name;
  document.getElementById('prod_form_id').value = p.id;
  document.getElementById('prod_form_name').value = p.name;
  document.getElementById('prod_form_code').value = p.code;
  document.getElementById('prod_form_cat').value = p.category_id;
  document.getElementById('prod_form_price').value = p.price;
  document.getElementById('prod_form_cost').value = p.cost_price;
  document.getElementById('prod_form_desc').value = p.description || '';
  document.getElementById('prod_form_track_stock').checked = p.track_stock == 1;
  document.getElementById('prod_form_stock').value = p.stock_quantity;
  document.getElementById('prod_form_alert').value = p.alert_quantity;
  
  const vContainer = document.getElementById('variants-container');
  vContainer.innerHTML = '';
  if (variants && variants.length > 0) {
    variants.forEach(v => addVariantRow(v.variant_name, v.extra_price));
  }
  openModal('product-form-modal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
