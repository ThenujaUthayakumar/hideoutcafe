<?php
require_once __DIR__ . '/config/functions.php';
requireRole([ROLE_ADMIN]);
ensurePromotionsSchema();

$title = 'Promotions';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? 'Rs.';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'save_promotion') {
                $id = !empty($_POST['promotion_id']) ? (int)$_POST['promotion_id'] : null;
                $name = sanitize($_POST['name'] ?? '');
                $categoryIds = [];
                $productIds = [];
                $categoryId = null;
                $productId = null;
                $promotionType = $_POST['promotion_type'] ?? 'percentage';
                $allowedTypes = ['percentage', 'fixed', 'bogo', 'buy_x_get_y', 'combo', 'happy_hour', 'day_based', 'loyalty', 'first_order', 'minimum_spend'];
                if (!in_array($promotionType, $allowedTypes, true)) $promotionType = 'percentage';
                $type = ($_POST['discount_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
                $value = max(0, (float)($_POST['discount_value'] ?? 0));
                $buyQuantity = max(1, (int)($_POST['buy_quantity'] ?? 1));
                $getQuantity = max(1, (int)($_POST['get_quantity'] ?? 1));
                $rewardProductIds = [];
                $rewardProductId = null;
                $comboProductIds = null;
                $minimumSpend = max(0, (float)($_POST['minimum_spend'] ?? 0));
                $minimumPoints = max(0, (float)($_POST['minimum_points'] ?? 0));
                $dayOfWeek = ($_POST['day_of_week'] ?? '') !== '' ? (int)$_POST['day_of_week'] : null;
                $start = !empty($_POST['start_at']) ? date('Y-m-d H:i:s', strtotime($_POST['start_at'])) : null;
                $end = !empty($_POST['end_at']) ? date('Y-m-d H:i:s', strtotime($_POST['end_at'])) : null;
                $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
                if ($name === '' || ($value <= 0 && !in_array($promotionType, ['bogo', 'buy_x_get_y'], true)) || ($type === 'percentage' && $value > 100)) {
                    throw new Exception('Enter a valid promotion name and discount value.');
                }
                $params = [':name' => $name, ':promotion_type' => $promotionType, ':type' => $type, ':value' => $value, ':buy_quantity' => $buyQuantity, ':get_quantity' => $getQuantity, ':minimum_spend' => $minimumSpend, ':minimum_points' => $minimumPoints, ':day_of_week' => $dayOfWeek, ':start_at' => $start, ':end_at' => $end, ':status' => $status];
                if ($id) {
                    $params[':id'] = $id;
                    db()->query("UPDATE promotions SET name=:name, promotion_type=:promotion_type, discount_type=:type, discount_value=:value, buy_quantity=:buy_quantity, get_quantity=:get_quantity, minimum_spend=:minimum_spend, minimum_points=:minimum_points, day_of_week=:day_of_week, start_at=:start_at, end_at=:end_at, status=:status WHERE id=:id", $params);
                } else {
                    db()->query("INSERT INTO promotions (name, promotion_type, discount_type, discount_value, buy_quantity, get_quantity, minimum_spend, minimum_points, day_of_week, start_at, end_at, status) VALUES (:name, :promotion_type, :type, :value, :buy_quantity, :get_quantity, :minimum_spend, :minimum_points, :day_of_week, :start_at, :end_at, :status)", $params);
                }
                setFlash('success', 'Promotion saved successfully.');
                header('Location: ' . BASE_URL . '/promotions.php');
                exit;
            }
            if ($action === 'delete_promotion') {
                db()->query('DELETE FROM promotions WHERE id = :id', [':id' => (int)$_POST['promotion_id']]);
                setFlash('success', 'Promotion deleted.');
                header('Location: ' . BASE_URL . '/promotions.php');
                exit;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$categories = db()->fetchAll('SELECT id, name FROM categories ORDER BY sort_order, name');
$products = db()->fetchAll('SELECT id, name, code FROM products ORDER BY name');
$promotions = db()->fetchAll("SELECT * FROM promotions ORDER BY id DESC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#0c0c0e]">
    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div><h2 class="text-2xl font-black tracking-tight text-white">Promotions</h2><p class="text-xs text-stone-400">Create standalone order promotions. Product prices and catalog discounts are not changed.</p></div>
            <button onclick="openPromotionModal()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-2xl text-xs"><i class="fa-solid fa-plus"></i> New Promotion</button>
        </div>
        <?php if ($error): ?><div class="p-3.5 bg-red-950 border border-red-800 rounded-2xl text-red-300 text-xs"><?= e($error) ?></div><?php endif; ?>
        <div class="card-cafe-dark overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-xs text-left"><thead class="bg-stone-900 text-stone-400 font-bold uppercase text-[10px]"><tr><th class="p-3.5">Promotion</th><th class="p-3.5">Applies To</th><th class="p-3.5">Discount</th><th class="p-3.5">Period</th><th class="p-3.5">Status</th><th class="p-3.5 text-center">Actions</th></tr></thead><tbody class="divide-y divide-stone-800">
        <?php foreach ($promotions as $promotion): ?>
            <tr class="hover:bg-stone-800"><td class="p-3.5 font-bold text-white"><?= e($promotion['name']) ?><span class="block text-[10px] text-stone-500"><?= e(getPromotionTypeLabel($promotion['promotion_type'] ?? $promotion['discount_type'])) ?></span></td><td class="p-3.5 text-stone-300">Order total only</td><td class="p-3.5 text-emerald-400 font-bold"><?= $promotion['discount_type'] === 'fixed' ? e($currency) . ' ' . number_format($promotion['discount_value'], 2) : number_format($promotion['discount_value'], 2) . '%' ?></td><td class="p-3.5 text-stone-400"><?= e($promotion['start_at'] ?: 'Any time') ?> to <?= e($promotion['end_at'] ?: 'No end') ?></td><td class="p-3.5"><span class="px-2 py-1 rounded-full <?= $promotion['status'] === 'active' ? 'bg-emerald-950 text-emerald-400' : 'bg-stone-800 text-stone-500' ?>"><?= e($promotion['status']) ?></span></td><td class="p-3.5 text-center"><button onclick='editPromotion(<?= json_encode($promotion, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="p-2 text-stone-300"><i class="fa-solid fa-pen"></i></button><form method="POST" class="inline" onsubmit="return confirm('Delete this promotion?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_promotion"><input type="hidden" name="promotion_id" value="<?= (int)$promotion['id'] ?>"><button class="p-2 text-rose-400"><i class="fa-solid fa-trash"></i></button></form></td></tr>
        <?php endforeach; ?>
        <?php if (!$promotions): ?><tr><td colspan="6" class="p-8 text-center text-stone-500">No promotions created yet.</td></tr><?php endif; ?></tbody></table></div></div>
    </div>
</main>
<div id="promotion-rule-modal" class="modal-overlay fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-4"><div class="bg-white rounded-3xl max-w-2xl w-full p-6 text-stone-900 max-h-[90vh] overflow-y-auto"><div class="flex justify-between border-b pb-3"><div><h3 id="promotion-rule-title" class="font-black">New Promotion</h3><p class="text-xs text-stone-500 mt-1">Choose a promotion category and configure its rule.</p></div><button onclick="closeModal('promotion-rule-modal')"><i class="fa-solid fa-xmark"></i></button></div><form method="POST" class="space-y-3 pt-4"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_promotion"><input type="hidden" name="promotion_id" id="rule_promotion_id"><input name="name" id="rule_name" required placeholder="Promotion name, e.g. Friday Coffee Deal" class="w-full px-3 py-2 border rounded-xl text-xs"><select name="promotion_type" id="rule_promotion_type" class="w-full px-3 py-2 border rounded-xl text-xs"><option value="percentage">Percentage Discount</option><option value="fixed">Fixed Amount Discount</option><option value="bogo">Buy One Get One (BOGO)</option><option value="buy_x_get_y">Buy X Get Y</option><option value="combo">Combo / Meal Deal</option><option value="happy_hour">Happy Hour</option><option value="day_based">Day-Based Promotion</option><option value="loyalty">Customer / Loyalty Promotion</option><option value="first_order">First Order / New Customer</option><option value="minimum_spend">Minimum Spend Promotion</option></select><div class="grid grid-cols-2 gap-3"><select name="category_id" id="rule_category" class="px-3 py-2 border rounded-xl text-xs"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select><select name="product_id" id="rule_product" class="px-3 py-2 border rounded-xl text-xs"><option value="">All products</option><?php foreach ($products as $product): ?><option value="<?= (int)$product['id'] ?>"><?= e($product['name']) ?> (<?= e($product['code']) ?>)</option><?php endforeach; ?></select></div><div class="grid grid-cols-2 gap-3"><select name="discount_type" id="rule_discount_type" class="px-3 py-2 border rounded-xl text-xs"><option value="percentage">Percentage reward</option><option value="fixed">Fixed reward</option></select><input type="number" min="0" step="0.01" name="discount_value" id="rule_discount_value" required placeholder="Discount / combo price" class="px-3 py-2 border rounded-xl text-xs"></div><div class="grid grid-cols-3 gap-3"><input type="number" min="1" name="buy_quantity" id="rule_buy_quantity" value="1" placeholder="Buy qty" class="px-3 py-2 border rounded-xl text-xs"><input type="number" min="1" name="get_quantity" id="rule_get_quantity" value="1" placeholder="Get qty" class="px-3 py-2 border rounded-xl text-xs"><select name="reward_product_id" id="rule_reward_product" class="px-3 py-2 border rounded-xl text-xs"><option value="">Same product free</option><?php foreach ($products as $product): ?><option value="<?= (int)$product['id'] ?>"><?= e($product['name']) ?></option><?php endforeach; ?></select></div><div class="grid grid-cols-2 gap-3"><input type="number" min="0" step="0.01" name="minimum_spend" id="rule_minimum_spend" placeholder="Minimum spend (Rs.)" class="px-3 py-2 border rounded-xl text-xs"><input type="number" min="0" step="0.01" name="minimum_points" id="rule_minimum_points" placeholder="Minimum loyalty points" class="px-3 py-2 border rounded-xl text-xs"></div><div class="grid grid-cols-3 gap-3"><select name="day_of_week" id="rule_day_of_week" class="px-3 py-2 border rounded-xl text-xs"><option value="">Any day</option><option value="1">Monday</option><option value="2">Tuesday</option><option value="3">Wednesday</option><option value="4">Thursday</option><option value="5">Friday</option><option value="6">Saturday</option><option value="7">Sunday</option></select><input type="datetime-local" name="start_at" id="rule_start" class="px-3 py-2 border rounded-xl text-xs"><input type="datetime-local" name="end_at" id="rule_end" class="px-3 py-2 border rounded-xl text-xs"></div><select name="status" id="rule_status" class="w-full px-3 py-2 border rounded-xl text-xs"><option value="active">Active</option><option value="inactive">Inactive</option></select><button class="w-full py-3 bg-red-600 text-white font-bold rounded-xl text-xs">Save Promotion Rule</button></form></div></div>
<div id="promotion-modal" class="modal-overlay fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-4"><div class="bg-white rounded-3xl max-w-lg w-full p-6 text-stone-900"><div class="flex justify-between border-b pb-3"><h3 id="promotion-title" class="font-black">New Promotion</h3><button onclick="closeModal('promotion-modal')"><i class="fa-solid fa-xmark"></i></button></div><form method="POST" class="space-y-3 pt-4"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_promotion"><input type="hidden" name="promotion_id" id="promotion_id"><input name="name" id="promotion_name" required placeholder="Promotion name" class="w-full px-3 py-2 border rounded-xl text-xs"><div class="grid grid-cols-2 gap-3"><select name="category_id" id="promotion_category" class="px-3 py-2 border rounded-xl text-xs"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select><select name="product_id" id="promotion_product" class="px-3 py-2 border rounded-xl text-xs"><option value="">All products</option><?php foreach ($products as $product): ?><option value="<?= (int)$product['id'] ?>"><?= e($product['name']) ?> (<?= e($product['code']) ?>)</option><?php endforeach; ?></select></div><div class="grid grid-cols-2 gap-3"><select name="discount_type" id="promotion_type" class="px-3 py-2 border rounded-xl text-xs"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed amount</option></select><input type="number" min="0.01" step="0.01" name="discount_value" id="promotion_value" required placeholder="Discount value" class="px-3 py-2 border rounded-xl text-xs"></div><div class="grid grid-cols-2 gap-3"><input type="datetime-local" name="start_at" id="promotion_start" class="px-3 py-2 border rounded-xl text-xs"><input type="datetime-local" name="end_at" id="promotion_end" class="px-3 py-2 border rounded-xl text-xs"></div><select name="status" id="promotion_status" class="w-full px-3 py-2 border rounded-xl text-xs"><option value="active">Active</option><option value="inactive">Inactive</option></select><button class="w-full py-3 bg-red-600 text-white font-bold rounded-xl text-xs">Save Promotion</button></form></div></div>
<script>
function ensureMultiSelects(){const category=document.getElementById('rule_category');const product=document.getElementById('rule_product');const reward=document.getElementById('rule_reward_product');[[category,'category_ids[]'],[product,'product_ids[]'],[reward,'reward_product_ids[]']].forEach(([field,name])=>{if(field){field.name=name;field.multiple=true;field.size=4;field.closest('div').style.display='none';}});}
function setSelectedValues(id, values){const selected=new Set((Array.isArray(values)?values:String(values||'').split(',')).map(String));document.querySelectorAll('#'+id+' option').forEach(option=>{option.selected=selected.has(option.value);});}
function ensureComboField(){ensureMultiSelects();const form=document.querySelector('#promotion-rule-modal form');if(!document.getElementById('rule_combo_product_ids')){form.querySelector('button').insertAdjacentHTML('beforebegin','<input type="text" name="combo_product_ids" id="rule_combo_product_ids" placeholder="Combo product IDs, comma-separated (e.g. 2,8,13)" class="w-full px-3 py-2 border rounded-xl text-xs">');}}
function openPromotionModal(){ensureComboField();document.getElementById('promotion-rule-title').textContent='New Promotion';document.querySelector('#promotion-rule-modal form').reset();document.getElementById('rule_promotion_id').value='';openModal('promotion-rule-modal');}
function editPromotion(p){ensureComboField();document.getElementById('promotion-rule-title').textContent='Edit Promotion';document.getElementById('rule_promotion_id').value=p.id;document.getElementById('rule_name').value=p.name;document.getElementById('rule_promotion_type').value=p.promotion_type||p.discount_type;setSelectedValues('rule_category',p.category_ids||p.category_id);setSelectedValues('rule_product',p.product_ids||p.product_id);document.getElementById('rule_discount_type').value=p.discount_type;document.getElementById('rule_discount_value').value=p.discount_value;document.getElementById('rule_buy_quantity').value=p.buy_quantity||1;document.getElementById('rule_get_quantity').value=p.get_quantity||1;setSelectedValues('rule_reward_product',p.reward_product_ids||p.reward_product_id);document.getElementById('rule_combo_product_ids').value=p.combo_product_ids||'';document.getElementById('rule_minimum_spend').value=p.minimum_spend||'';document.getElementById('rule_minimum_points').value=p.minimum_points||'';document.getElementById('rule_day_of_week').value=p.day_of_week||'';document.getElementById('rule_start').value=p.start_at?p.start_at.replace(' ','T').slice(0,16):'';document.getElementById('rule_end').value=p.end_at?p.end_at.replace(' ','T').slice(0,16):'';document.getElementById('rule_status').value=p.status;openModal('promotion-rule-modal');}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
