<?php
require_once __DIR__ . '/config/functions.php';
requireRole([ROLE_ADMIN]);
ensurePromotionsSchema();

$title = 'Promotions';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? 'Rs.';
$error = '';
$categories = db()->fetchAll('SELECT id, name FROM categories ORDER BY sort_order, name');
$products = db()->fetchAll(
    "SELECT p.id, p.name, p.code, p.category_id, p.price, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     ORDER BY p.name"
);
$categoryNames = [];
foreach ($categories as $category) {
    $categoryNames[(int)$category['id']] = $category['name'];
}
$productsById = [];
foreach ($products as $product) {
    $productsById[(int)$product['id']] = $product;
}
$parseStoredIds = static function ($value): array {
    if ($value === null || $value === '') return [];
    $parts = is_array($value) ? $value : explode(',', (string)$value);
    $ids = [];
    foreach ($parts as $part) {
        if (is_scalar($part) && filter_var((string)$part, FILTER_VALIDATE_INT) !== false && (int)$part > 0) {
            $ids[] = (int)$part;
        }
    }
    return array_values(array_unique($ids));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'save_promotion') {
                $id = !empty($_POST['promotion_id']) ? (int)$_POST['promotion_id'] : null;
                $name = sanitize($_POST['name'] ?? '');
                $readPostedIds = static function (string $key): array {
                    $values = $_POST[$key] ?? [];
                    if (!is_array($values)) {
                        throw new Exception('Invalid product or category selection.');
                    }
                    $ids = [];
                    foreach ($values as $value) {
                        if (!is_scalar($value) || filter_var((string)$value, FILTER_VALIDATE_INT) === false || (int)$value < 1) {
                            throw new Exception('Invalid product or category selection.');
                        }
                        $ids[] = (int)$value;
                    }
                    return array_values(array_unique($ids));
                };
                $analysisCategoryIds = $readPostedIds('analysis_category_ids');
                $analysisProductIds = $readPostedIds('analysis_product_ids');
                $analysisRewardProductIds = $readPostedIds('analysis_reward_product_ids');
                $analysisBuyInput = $_POST['analysis_buy_quantity'] ?? '1';
                $analysisGetInput = $_POST['analysis_get_quantity'] ?? '1';
                if (!is_string($analysisBuyInput) || !is_string($analysisGetInput)) {
                    throw new Exception('Enter valid analytics quantities between 1 and 999.');
                }
                $analysisBuyQuantity = filter_var($analysisBuyInput, FILTER_VALIDATE_INT);
                $analysisGetQuantity = filter_var($analysisGetInput, FILTER_VALIDATE_INT);
                if ($analysisBuyQuantity === false || $analysisBuyQuantity < 1 || $analysisBuyQuantity > 999
                    || $analysisGetQuantity === false || $analysisGetQuantity < 1 || $analysisGetQuantity > 999) {
                    throw new Exception('Enter valid analytics quantities between 1 and 999.');
                }
                $availableCategoryIds = array_map('intval', array_column($categories, 'id'));
                $availableProductIds = array_map('intval', array_column($products, 'id'));
                foreach ($analysisCategoryIds as $categoryId) {
                    if (!in_array($categoryId, $availableCategoryIds, true)) {
                        throw new Exception('Select a valid product category.');
                    }
                }
                foreach (array_merge($analysisProductIds, $analysisRewardProductIds) as $productId) {
                    if (!in_array($productId, $availableProductIds, true)) {
                        throw new Exception('Select a valid product.');
                    }
                }
                $promotionType = $_POST['promotion_type'] ?? 'percentage';
                $allowedTypes = ['percentage', 'fixed', 'bogo', 'buy_x_get_y', 'combo', 'happy_hour', 'day_based', 'loyalty', 'first_order', 'minimum_spend'];
                if (!in_array($promotionType, $allowedTypes, true)) $promotionType = 'percentage';
                $type = ($_POST['discount_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
                $value = max(0, (float)($_POST['discount_value'] ?? 0));
                $buyQuantity = max(1, (int)($_POST['buy_quantity'] ?? 1));
                $getQuantity = max(1, (int)($_POST['get_quantity'] ?? 1));
                $minimumSpend = max(0, (float)($_POST['minimum_spend'] ?? 0));
                $minimumPoints = max(0, (float)($_POST['minimum_points'] ?? 0));
                $dayOfWeek = ($_POST['day_of_week'] ?? '') !== '' ? (int)$_POST['day_of_week'] : null;
                $start = !empty($_POST['start_at']) ? date('Y-m-d H:i:s', strtotime($_POST['start_at'])) : null;
                $end = !empty($_POST['end_at']) ? date('Y-m-d H:i:s', strtotime($_POST['end_at'])) : null;
                $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
                if ($name === '' || ($value <= 0 && !in_array($promotionType, ['bogo', 'buy_x_get_y'], true)) || ($type === 'percentage' && $value > 100)) {
                    throw new Exception('Enter a valid promotion name and discount value.');
                }
                $params = [
                    ':name' => $name,
                    ':promotion_type' => $promotionType,
                    ':type' => $type,
                    ':value' => $value,
                    ':buy_quantity' => $buyQuantity,
                    ':get_quantity' => $getQuantity,
                    ':analysis_category_ids' => $analysisCategoryIds ? implode(',', $analysisCategoryIds) : null,
                    ':analysis_product_ids' => $analysisProductIds ? implode(',', $analysisProductIds) : null,
                    ':analysis_reward_product_ids' => $analysisRewardProductIds ? implode(',', $analysisRewardProductIds) : null,
                    ':analysis_buy_quantity' => $analysisBuyQuantity,
                    ':analysis_get_quantity' => $analysisGetQuantity,
                    ':minimum_spend' => $minimumSpend,
                    ':minimum_points' => $minimumPoints,
                    ':day_of_week' => $dayOfWeek,
                    ':start_at' => $start,
                    ':end_at' => $end,
                    ':status' => $status
                ];
                if ($id) {
                    $params[':id'] = $id;
                    db()->query(
                        "UPDATE promotions SET
                            name=:name, promotion_type=:promotion_type, discount_type=:type, discount_value=:value,
                            buy_quantity=:buy_quantity, get_quantity=:get_quantity,
                            analysis_category_ids=:analysis_category_ids, analysis_product_ids=:analysis_product_ids,
                            analysis_reward_product_ids=:analysis_reward_product_ids,
                            analysis_buy_quantity=:analysis_buy_quantity, analysis_get_quantity=:analysis_get_quantity,
                            minimum_spend=:minimum_spend, minimum_points=:minimum_points, day_of_week=:day_of_week,
                            start_at=:start_at, end_at=:end_at, status=:status
                         WHERE id=:id",
                        $params
                    );
                } else {
                    db()->query(
                        "INSERT INTO promotions (
                            name, promotion_type, discount_type, discount_value, buy_quantity, get_quantity,
                            analysis_category_ids, analysis_product_ids, analysis_reward_product_ids,
                            analysis_buy_quantity, analysis_get_quantity, minimum_spend, minimum_points, day_of_week,
                            start_at, end_at, status
                         ) VALUES (
                            :name, :promotion_type, :type, :value, :buy_quantity, :get_quantity,
                            :analysis_category_ids, :analysis_product_ids, :analysis_reward_product_ids,
                            :analysis_buy_quantity, :analysis_get_quantity, :minimum_spend, :minimum_points, :day_of_week,
                            :start_at, :end_at, :status
                         )",
                        $params
                    );
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

$promotions = db()->fetchAll("SELECT * FROM promotions ORDER BY id DESC");
$promotionAnalytics = [];
foreach ($promotions as $promotion) {
    $analysisCategoryIds = $parseStoredIds($promotion['analysis_category_ids'] ?? null);
    $analysisProductIds = $parseStoredIds($promotion['analysis_product_ids'] ?? null);
    $analysisRewardProductIds = $parseStoredIds($promotion['analysis_reward_product_ids'] ?? null);
    $analysisBuyQuantity = max(1, (int)($promotion['analysis_buy_quantity'] ?? 1));
    $analysisGetQuantity = max(1, (int)($promotion['analysis_get_quantity'] ?? 1));
    $productLines = [];
    $regularValue = 0.0;
    $hasRegularValue = false;
    $type = $promotion['promotion_type'] ?? $promotion['discount_type'];

    if (in_array($type, ['bogo', 'buy_x_get_y'], true)) {
        foreach ($analysisProductIds as $productId) {
            if (!isset($productsById[$productId])) continue;
            $product = $productsById[$productId];
            $lineTotal = (float)$product['price'] * $analysisBuyQuantity;
            $regularValue += $lineTotal;
            $hasRegularValue = true;
            $productLines[] = ['kind' => 'Buy', 'quantity' => $analysisBuyQuantity, 'product' => $product, 'line_total' => $lineTotal];
        }
        if (!$analysisRewardProductIds) {
            $analysisRewardProductIds = $analysisProductIds;
        }
        foreach ($analysisRewardProductIds as $productId) {
            if (!isset($productsById[$productId])) continue;
            $product = $productsById[$productId];
            $lineTotal = (float)$product['price'] * $analysisGetQuantity;
            $regularValue += $lineTotal;
            $hasRegularValue = true;
            $productLines[] = ['kind' => 'Get', 'quantity' => $analysisGetQuantity, 'product' => $product, 'line_total' => $lineTotal];
        }
    } elseif ($type === 'combo') {
        foreach ($analysisProductIds as $productId) {
            if (!isset($productsById[$productId])) continue;
            $product = $productsById[$productId];
            $lineTotal = (float)$product['price'];
            $regularValue += $lineTotal;
            $hasRegularValue = true;
            $productLines[] = ['kind' => 'Combo', 'quantity' => 1, 'product' => $product, 'line_total' => $lineTotal];
        }
    } else {
        foreach (array_unique(array_merge($analysisProductIds, $analysisRewardProductIds)) as $productId) {
            if (isset($productsById[$productId])) {
                $productLines[] = ['kind' => 'Selected', 'quantity' => null, 'product' => $productsById[$productId], 'line_total' => null];
            }
        }
    }

    $promotionAnalytics[(int)$promotion['id']] = [
        'categories' => array_values(array_filter(array_map(
            static function ($categoryId) use ($categoryNames) {
                return $categoryNames[$categoryId] ?? null;
            },
            $analysisCategoryIds
        ))),
        'product_lines' => $productLines,
        'regular_value' => $regularValue,
        'has_regular_value' => $hasRegularValue
    ];
}

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
        <div class="card-cafe-dark overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-stone-900 text-stone-400 font-bold uppercase text-[10px]">
                        <tr>
                            <th class="p-3.5">Promotion</th>
                            <th class="p-3.5">Products &amp; Categories</th>
                            <th class="p-3.5">Actual Retail Value</th>
                            <th class="p-3.5">Discount / Offer</th>
                            <th class="p-3.5">Period</th>
                            <th class="p-3.5">Status</th>
                            <th class="p-3.5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800">
                    <?php foreach ($promotions as $promotion): ?>
                        <?php $analytics = $promotionAnalytics[(int)$promotion['id']]; ?>
                        <tr class="hover:bg-stone-800">
                            <td class="p-3.5 font-bold text-white">
                                <?= e($promotion['name']) ?>
                                <span class="block text-[10px] text-stone-500"><?= e(getPromotionTypeLabel($promotion['promotion_type'] ?? $promotion['discount_type'])) ?></span>
                            </td>
                            <td class="p-3.5 min-w-64 text-stone-300">
                                <?php if ($analytics['categories']): ?>
                                    <div class="mb-1 text-[10px]"><span class="font-bold text-stone-200">Categories:</span> <?= e(implode(', ', $analytics['categories'])) ?></div>
                                <?php endif; ?>
                                <?php foreach ($analytics['product_lines'] as $line): ?>
                                    <div class="mt-1">
                                        <span class="font-semibold"><?= e($line['kind']) ?><?php if ($line['quantity'] !== null): ?> <?= (int)$line['quantity'] ?> x<?php endif; ?> <?= e($line['product']['name']) ?></span>
                                        <span class="block text-[10px] text-stone-500">
                                            <?= e($line['product']['category_name'] ?? 'Uncategorized') ?>
                                            <?php if ($line['line_total'] !== null): ?>
                                                - <?= e($currency) ?> <?= number_format((float)$line['product']['price'], 2) ?> each
                                                - <?= e($currency) ?> <?= number_format((float)$line['line_total'], 2) ?> line total
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!$analytics['categories'] && !$analytics['product_lines']): ?>
                                    <span class="text-stone-500">No analytical products selected</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5 whitespace-nowrap font-bold text-amber-300">
                                <?php if ($analytics['has_regular_value']): ?>
                                    <?= e($currency) ?> <?= number_format((float)$analytics['regular_value'], 2) ?>
                                    <span class="block text-[10px] font-normal text-stone-500">Current catalog prices</span>
                                <?php else: ?>
                                    <span class="text-stone-500">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5 text-emerald-400 font-bold"><?= $promotion['discount_type'] === 'fixed' ? e($currency) . ' ' . number_format($promotion['discount_value'], 2) : number_format($promotion['discount_value'], 2) . '%' ?></td>
                            <td class="p-3.5 text-stone-400"><?= e($promotion['start_at'] ?: 'Any time') ?> to <?= e($promotion['end_at'] ?: 'No end') ?></td>
                            <td class="p-3.5"><span class="px-2 py-1 rounded-full <?= $promotion['status'] === 'active' ? 'bg-emerald-950 text-emerald-400' : 'bg-stone-800 text-stone-500' ?>"><?= e($promotion['status']) ?></span></td>
                            <td class="p-3.5 text-center">
                                <button onclick='editPromotion(<?= json_encode($promotion, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="p-2 text-stone-300"><i class="fa-solid fa-pen"></i></button>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this promotion?')">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_promotion">
                                    <input type="hidden" name="promotion_id" value="<?= (int)$promotion['id'] ?>">
                                    <button class="p-2 text-rose-400"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$promotions): ?>
                        <tr><td colspan="7" class="p-8 text-center text-stone-500">No promotions created yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<div id="promotion-rule-modal" class="modal-overlay fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-3 backdrop-blur-sm sm:p-6">
    <div class="flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-stone-800 bg-stone-900 text-white shadow-2xl">
        <div class="flex items-start justify-between border-b border-stone-800 px-5 py-4 sm:px-7">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-red-400">Promotion setup</p>
                <h3 id="promotion-rule-title" class="mt-1 text-lg font-black sm:text-xl">New Promotion</h3>
                <p class="mt-1 text-xs text-stone-400">Set the offer, schedule, and optional product details.</p>
            </div>
            <button type="button" onclick="closeModal('promotion-rule-modal')" aria-label="Close promotion form" class="rounded-xl p-2 text-stone-400 hover:bg-stone-800 hover:text-white">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" class="flex min-h-0 flex-1 flex-col">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_promotion">
            <input type="hidden" name="promotion_id" id="rule_promotion_id">
            <input type="hidden" name="buy_quantity" id="rule_buy_quantity" value="1">
            <input type="hidden" name="get_quantity" id="rule_get_quantity" value="1">
            <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5 sm:p-7">
                <section class="rounded-2xl border border-stone-800 bg-stone-950/60 p-4 sm:p-5">
                    <div class="mb-4 flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-red-950 text-xs font-black text-red-300">01</span>
                        <div><h4 class="text-sm font-bold text-white">Promotion details</h4><p class="text-[11px] text-stone-500">Give the offer a clear name and choose its type.</p></div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="rule_name" class="mb-1.5 block text-xs font-semibold text-stone-300">Promotion name <span class="text-red-400">*</span></label>
                            <input name="name" id="rule_name" required placeholder="e.g. Friday Burger Deal" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3.5 py-3 text-sm text-white placeholder:text-stone-500 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20">
                        </div>
                        <div>
                            <label for="rule_promotion_type" class="mb-1.5 block text-xs font-semibold text-stone-300">Promotion type</label>
                            <select name="promotion_type" id="rule_promotion_type" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3.5 py-3 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20">
                                <option value="percentage">Percentage Discount</option><option value="fixed">Fixed Amount Discount</option>
                                <option value="bogo">Buy One Get One (BOGO)</option><option value="buy_x_get_y">Buy X Get Y</option>
                                <option value="combo">Combo / Meal Deal</option><option value="happy_hour">Happy Hour</option>
                                <option value="day_based">Day-Based Promotion</option><option value="loyalty">Customer / Loyalty Promotion</option>
                                <option value="first_order">First Order / New Customer</option><option value="minimum_spend">Minimum Spend Promotion</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label for="rule_status" class="mb-1.5 block text-xs font-semibold text-stone-300">Status</label>
                            <select name="status" id="rule_status" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3.5 py-3 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20 md:max-w-xs">
                                <option value="active">Active</option><option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-stone-800 bg-stone-950/60 p-4 sm:p-5">
                    <div class="mb-4 flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-950 text-xs font-black text-emerald-300">02</span>
                        <div><h4 class="text-sm font-bold text-white">Offer and schedule</h4><p class="text-[11px] text-stone-500">Set the offer value and when the promotion is available.</p></div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label for="rule_discount_type" class="mb-1.5 block text-xs font-semibold text-stone-300">Offer value type</label>
                            <select name="discount_type" id="rule_discount_type" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3.5 py-3 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20">
                                <option value="percentage">Percentage (%)</option><option value="fixed">Fixed amount (<?= e($currency) ?>)</option>
                            </select>
                        </div>
                        <div>
                            <label for="rule_discount_value" class="mb-1.5 block text-xs font-semibold text-stone-300">Discount / offer amount</label>
                            <input type="number" min="0" step="0.01" name="discount_value" id="rule_discount_value" required placeholder="Enter value" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3.5 py-3 text-sm text-white placeholder:text-stone-500 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20">
                        </div>
                        <div>
                            <label for="rule_day_of_week" class="mb-1.5 block text-xs font-semibold text-stone-300">Available day</label>
                            <select name="day_of_week" id="rule_day_of_week" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3.5 py-3 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20">
                                <option value="">Any day</option><option value="1">Monday</option><option value="2">Tuesday</option>
                                <option value="3">Wednesday</option><option value="4">Thursday</option><option value="5">Friday</option>
                                <option value="6">Saturday</option><option value="7">Sunday</option>
                            </select>
                        </div>
                        <div>
                            <label for="rule_minimum_spend" class="mb-1.5 block text-xs font-semibold text-stone-300">Minimum spend <span class="font-normal text-stone-500">(optional, <?= e($currency) ?>)</span></label>
                            <input type="number" min="0" step="0.01" name="minimum_spend" id="rule_minimum_spend" placeholder="No minimum" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3.5 py-3 text-sm text-white placeholder:text-stone-500 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20">
                        </div>
                        <div>
                            <label for="rule_minimum_points" class="mb-1.5 block text-xs font-semibold text-stone-300">Minimum loyalty points <span class="font-normal text-stone-500">(optional)</span></label>
                            <input type="number" min="0" step="0.01" name="minimum_points" id="rule_minimum_points" placeholder="No points requirement" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3.5 py-3 text-sm text-white placeholder:text-stone-500 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20">
                        </div>
                        <div>
                            <label for="rule_start" class="mb-1.5 block text-xs font-semibold text-stone-300">Starts <span class="font-normal text-stone-500">(optional)</span></label>
                            <input type="datetime-local" name="start_at" id="rule_start" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3.5 py-3 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20">
                        </div>
                        <div>
                            <label for="rule_end" class="mb-1.5 block text-xs font-semibold text-stone-300">Ends <span class="font-normal text-stone-500">(optional)</span></label>
                            <input type="datetime-local" name="end_at" id="rule_end" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3.5 py-3 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20">
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-stone-800 bg-stone-950/60 p-4 sm:p-5">
                    <div class="mb-4 flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-950 text-xs font-black text-amber-300">03</span>
                        <div><h4 class="text-sm font-bold text-white">Product details for the grid</h4><p class="text-[11px] text-stone-500">Optional details used to show products and retail value on this page.</p></div>
                    </div>
                    <div class="mb-4 flex gap-3 rounded-xl border border-sky-900/70 bg-sky-950/30 p-3.5 text-xs text-sky-200">
                        <i class="fa-solid fa-circle-info mt-0.5"></i>
                        <p>These selections are for promotion analysis display only. They do not change checkout prices, order totals, or reports.</p>
                    </div>
                    <div class="mb-4">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <div><h5 class="text-xs font-bold text-stone-200">Categories</h5><p class="text-[10px] text-stone-500">Optional. Select all that apply.</p></div>
                            <span id="rule_category_count" class="text-[10px] text-stone-400">0 selected</span>
                        </div>
                        <div id="rule_category_options" class="grid max-h-36 grid-cols-1 gap-2 overflow-y-auto rounded-xl border border-stone-800 bg-stone-900 p-3 sm:grid-cols-2 lg:grid-cols-3">
                            <?php foreach ($categories as $category): ?>
                                <label data-promotion-option class="flex cursor-pointer items-center gap-2 rounded-lg px-2.5 py-2 text-xs text-stone-300 hover:bg-stone-800">
                                    <input type="checkbox" name="analysis_category_ids[]" value="<?= (int)$category['id'] ?>" onchange="updatePromotionSelectionCount('rule_category_options', 'rule_category_count')" class="h-4 w-4 shrink-0 accent-red-500">
                                    <span><?= e($category['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (!$categories): ?><p class="col-span-full text-xs text-stone-500">No product categories available.</p><?php endif; ?>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        <div class="rounded-xl border border-stone-800 bg-stone-900 p-3">
                            <div class="mb-2 flex items-start justify-between gap-3">
                                <div><h5 class="text-xs font-bold text-stone-200">Buy products</h5><p class="text-[10px] text-stone-500">Items included in the offer.</p></div>
                                <span id="rule_product_count" class="shrink-0 text-[10px] text-stone-400">0 selected</span>
                            </div>
                            <input type="search" oninput="filterPromotionOptions(this, 'rule_product_options')" placeholder="Search products..." aria-label="Search buy products" class="mb-2 w-full rounded-lg border border-stone-700 bg-stone-800 px-3 py-2 text-xs text-white placeholder:text-stone-500 focus:border-red-500 focus:outline-none">
                            <div id="rule_product_options" class="max-h-56 space-y-1 overflow-y-auto">
                                <?php foreach ($products as $product): ?>
                                    <label data-promotion-option class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2.5 py-2 hover:bg-stone-800">
                                        <input type="checkbox" name="analysis_product_ids[]" value="<?= (int)$product['id'] ?>" onchange="updatePromotionSelectionCount('rule_product_options', 'rule_product_count')" class="mt-0.5 h-4 w-4 shrink-0 accent-red-500">
                                        <span class="min-w-0 flex-1"><span class="block truncate text-xs font-semibold text-stone-200"><?= e($product['name']) ?></span><span class="mt-0.5 block truncate text-[10px] text-stone-500"><?= e($product['category_name'] ?? 'Uncategorized') ?></span></span>
                                        <span class="shrink-0 text-[10px] text-stone-400"><?= e($currency) ?> <?= number_format((float)$product['price'], 2) ?></span>
                                    </label>
                                <?php endforeach; ?>
                                <?php if (!$products): ?><p class="p-2 text-xs text-stone-500">No products available.</p><?php endif; ?>
                            </div>
                        </div>
                        <div class="rounded-xl border border-stone-800 bg-stone-900 p-3">
                            <div class="mb-2 flex items-start justify-between gap-3">
                                <div><h5 class="text-xs font-bold text-stone-200">Get products</h5><p class="text-[10px] text-stone-500">Reward items, when applicable.</p></div>
                                <span id="rule_reward_product_count" class="shrink-0 text-[10px] text-stone-400">0 selected</span>
                            </div>
                            <input type="search" oninput="filterPromotionOptions(this, 'rule_reward_product_options')" placeholder="Search products..." aria-label="Search get products" class="mb-2 w-full rounded-lg border border-stone-700 bg-stone-800 px-3 py-2 text-xs text-white placeholder:text-stone-500 focus:border-red-500 focus:outline-none">
                            <div id="rule_reward_product_options" class="max-h-56 space-y-1 overflow-y-auto">
                                <?php foreach ($products as $product): ?>
                                    <label data-promotion-option class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2.5 py-2 hover:bg-stone-800">
                                        <input type="checkbox" name="analysis_reward_product_ids[]" value="<?= (int)$product['id'] ?>" onchange="updatePromotionSelectionCount('rule_reward_product_options', 'rule_reward_product_count')" class="mt-0.5 h-4 w-4 shrink-0 accent-red-500">
                                        <span class="min-w-0 flex-1"><span class="block truncate text-xs font-semibold text-stone-200"><?= e($product['name']) ?></span><span class="mt-0.5 block truncate text-[10px] text-stone-500"><?= e($product['category_name'] ?? 'Uncategorized') ?></span></span>
                                        <span class="shrink-0 text-[10px] text-stone-400"><?= e($currency) ?> <?= number_format((float)$product['price'], 2) ?></span>
                                    </label>
                                <?php endforeach; ?>
                                <?php if (!$products): ?><p class="p-2 text-xs text-stone-500">No products available.</p><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="rule_analysis_buy_quantity" class="mb-1.5 block text-xs font-semibold text-stone-300">Buy quantity <span class="font-normal text-stone-500">(grid only)</span></label>
                            <input type="number" min="1" max="999" name="analysis_buy_quantity" id="rule_analysis_buy_quantity" value="1" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3.5 py-3 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20">
                        </div>
                        <div>
                            <label for="rule_analysis_get_quantity" class="mb-1.5 block text-xs font-semibold text-stone-300">Get quantity <span class="font-normal text-stone-500">(grid only)</span></label>
                            <input type="number" min="1" max="999" name="analysis_get_quantity" id="rule_analysis_get_quantity" value="1" class="w-full rounded-xl border border-stone-700 bg-stone-800 px-3.5 py-3 text-sm text-white focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20">
                        </div>
                    </div>
                </section>
            </div>
            <div class="flex flex-col-reverse gap-2 border-t border-stone-800 bg-stone-900 px-5 py-4 sm:flex-row sm:justify-end sm:px-7">
                <button type="button" onclick="closeModal('promotion-rule-modal')" class="rounded-xl border border-stone-700 px-5 py-3 text-xs font-bold text-stone-300 hover:bg-stone-800">Cancel</button>
                <button type="submit" class="rounded-xl bg-red-600 px-6 py-3 text-xs font-bold text-white shadow-lg shadow-red-950/40 hover:bg-red-500"><i class="fa-solid fa-check mr-1"></i> Save Promotion</button>
            </div>
        </form>
    </div>
</div>
<script>
function setSelectedValues(containerId, values) {
    const selected = new Set((Array.isArray(values) ? values : String(values || '').split(',')).map(String));
    document.querySelectorAll('#' + containerId + ' input[type="checkbox"]').forEach(input => {
        input.checked = selected.has(input.value);
    });
}

function updatePromotionSelectionCount(containerId, countId) {
    const count = document.querySelectorAll('#' + containerId + ' input[type="checkbox"]:checked').length;
    document.getElementById(countId).textContent = count + ' selected';
}

function filterPromotionOptions(input, containerId) {
    const query = input.value.trim().toLowerCase();
    document.querySelectorAll('#' + containerId + ' [data-promotion-option]').forEach(option => {
        option.hidden = !option.textContent.toLowerCase().includes(query);
    });
}

function refreshPromotionSelectionCounts() {
    updatePromotionSelectionCount('rule_category_options', 'rule_category_count');
    updatePromotionSelectionCount('rule_product_options', 'rule_product_count');
    updatePromotionSelectionCount('rule_reward_product_options', 'rule_reward_product_count');
}

function resetPromotionProductFilters() {
    document.querySelectorAll('#promotion-rule-modal input[type="search"]').forEach(input => input.value = '');
    document.querySelectorAll('#rule_category_options [data-promotion-option], #rule_product_options [data-promotion-option], #rule_reward_product_options [data-promotion-option]').forEach(option => option.hidden = false);
}

function openPromotionModal() {
    document.getElementById('promotion-rule-title').textContent = 'New Promotion';
    const form = document.querySelector('#promotion-rule-modal form');
    form.reset();
    document.getElementById('rule_promotion_id').value = '';
    resetPromotionProductFilters();
    refreshPromotionSelectionCounts();
    openModal('promotion-rule-modal');
}

function editPromotion(promotion) {
    document.getElementById('promotion-rule-title').textContent = 'Edit Promotion';
    document.getElementById('rule_promotion_id').value = promotion.id;
    document.getElementById('rule_name').value = promotion.name;
    document.getElementById('rule_promotion_type').value = promotion.promotion_type || promotion.discount_type;
    setSelectedValues('rule_category_options', promotion.analysis_category_ids);
    setSelectedValues('rule_product_options', promotion.analysis_product_ids);
    setSelectedValues('rule_reward_product_options', promotion.analysis_reward_product_ids);
    document.getElementById('rule_discount_type').value = promotion.discount_type;
    document.getElementById('rule_discount_value').value = promotion.discount_value;
    document.getElementById('rule_buy_quantity').value = promotion.buy_quantity || 1;
    document.getElementById('rule_get_quantity').value = promotion.get_quantity || 1;
    document.getElementById('rule_analysis_buy_quantity').value = promotion.analysis_buy_quantity || 1;
    document.getElementById('rule_analysis_get_quantity').value = promotion.analysis_get_quantity || 1;
    document.getElementById('rule_minimum_spend').value = promotion.minimum_spend || '';
    document.getElementById('rule_minimum_points').value = promotion.minimum_points || '';
    document.getElementById('rule_day_of_week').value = promotion.day_of_week || '';
    document.getElementById('rule_start').value = promotion.start_at ? promotion.start_at.replace(' ', 'T').slice(0, 16) : '';
    document.getElementById('rule_end').value = promotion.end_at ? promotion.end_at.replace(' ', 'T').slice(0, 16) : '';
    document.getElementById('rule_status').value = promotion.status;
    resetPromotionProductFilters();
    refreshPromotionSelectionCounts();
    openModal('promotion-rule-modal');
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
