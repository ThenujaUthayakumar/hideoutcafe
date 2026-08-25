<?php
/**
 * The Hide Out Cafe - Interactive POS Terminal (Black & Red Theme)
 */
require_once __DIR__ . '/config/functions.php';
requireAuth();

$title = 'POS Terminal';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? 'Rs.';
$user = currentUser();

// Fetch Categories
$categories = db()->fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order ASC, name ASC");

// Fetch Products with Variants
$products = db()->fetchAll(
    "SELECT p.*, c.name as category_name 
     FROM products p 
     LEFT JOIN categories c ON p.category_id = c.id 
     WHERE p.status = 'active' 
     ORDER BY c.sort_order ASC, p.name ASC"
);

$productIds = array_column($products, 'id');
$variantsByProduct = [];
if (!empty($productIds)) {
    $inClause = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = pdo()->prepare("SELECT * FROM product_variants WHERE product_id IN ($inClause) ORDER BY extra_price ASC");
    $stmt->execute($productIds);
    $variants = $stmt->fetchAll();
    foreach ($variants as $v) {
        $variantsByProduct[$v['product_id']][] = $v;
    }
}
foreach ($products as &$p) {
    $p['variants'] = $variantsByProduct[$p['id']] ?? [];
}

// Fetch Modifiers, Tables, Customers
$modifiers = db()->fetchAll("SELECT * FROM modifiers WHERE status = 'active' ORDER BY category ASC, name ASC");
$tables = db()->fetchAll("SELECT * FROM tables ORDER BY table_number ASC");
$customers = db()->fetchAll("SELECT id, name, phone, loyalty_points FROM customers ORDER BY id ASC LIMIT 50");

$preselectedTableId = !empty($_GET['table_id']) ? (int)$_GET['table_id'] : null;
$preselectedTableName = 'No Table';
if ($preselectedTableId) {
    foreach ($tables as $t) {
        if ($t['id'] == $preselectedTableId) {
            $preselectedTableName = $t['name'];
            break;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<script>
  window.POS_MODIFIERS = <?= json_encode($modifiers) ?>;
  window.POS_SETTINGS = {
    currencySymbol: <?= json_encode($currency) ?>
  };
</script>
<div class="flex-1 flex flex-col lg:flex-row overflow-hidden h-[calc(100vh-4rem)] bg-[#09090b]">
    <!-- LEFT: MENU CATALOG -->
    <div class="flex-1 flex flex-col overflow-hidden bg-[#0c0c0e] border-r border-stone-800">
        <div class="p-4 bg-stone-950 border-b border-stone-800 space-y-3 shadow-md">
            <div class="flex items-center gap-3">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-stone-500 pointer-events-none">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="text" id="pos-search-input" oninput="PosApp.onSearch(this.value)" placeholder="Search coffee, bakery, or SKU... (Press F1)" class="w-full pl-10 pr-10 py-2.5 bg-stone-900 border border-stone-800 rounded-xl text-sm font-medium text-white focus:bg-black focus:ring-2 focus:ring-red-600 focus:border-red-600 focus:outline-none transition placeholder-stone-500">
                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-[10px] font-bold text-stone-500 pointer-events-none">F1</span>
                </div>
                <button onclick="PosApp.openHeldOrdersModal()" class="px-3.5 py-2.5 bg-stone-900 hover:bg-stone-800 text-stone-300 font-bold rounded-xl text-xs flex items-center gap-2 border border-stone-800 transition">
                    <i class="fa-solid fa-hand-holding-dollar text-red-500"></i>
                    <span class="hidden sm:inline">Held Orders</span> (F7)
                </button>
            </div>
            <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar">
                <button onclick="PosApp.setCategory('all')" data-category="all" class="cat-pill active flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-extrabold whitespace-nowrap bg-stone-900 text-stone-300 border border-stone-800 hover:bg-stone-800 transition">
                    <i class="fa-solid fa-border-all"></i><span>All Menu</span>
                </button>
                <?php foreach ($categories as $cat): ?>
                <button onclick="PosApp.setCategory(<?= $cat['id'] ?>)" data-category="<?= $cat['id'] ?>" class="cat-pill flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-extrabold whitespace-nowrap bg-stone-900 text-stone-300 border border-stone-800 hover:bg-stone-800 transition">
                    <i class="fa-solid <?= e($cat['icon']) ?>"></i><span><?= e($cat['name']) ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-4 sm:p-5">
            <div id="pos-products-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3.5 sm:gap-4">
                <?php foreach ($products as $prod): ?>
                    <script type="application/json" id="product-raw-<?= $prod['id'] ?>"><?= json_encode($prod) ?></script>
                    <div onclick="PosApp.selectProduct(<?= $prod['id'] ?>)" data-category="<?= $prod['category_id'] ?>" data-name="<?= e($prod['name']) ?>" data-code="<?= e($prod['code']) ?>" class="pos-product-card bg-stone-900 border border-stone-800 rounded-2xl p-3 flex flex-col justify-between cursor-pointer hover:border-red-600 shadow-md group">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-red-400 bg-red-950/70 border border-red-900 px-2 py-0.5 rounded-md truncate max-w-[80%]"><?= e($prod['category_name']) ?></span>
                                <?php if ($prod['track_stock'] && $prod['stock_quantity'] <= $prod['alert_quantity']): ?>
                                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse" title="Low Stock: <?= $prod['stock_quantity'] ?>"></span>
                                <?php endif; ?>
                            </div>
                            <h4 class="font-bold text-white text-xs sm:text-sm leading-snug group-hover:text-red-400 transition line-clamp-2"><?= e($prod['name']) ?></h4>
                            <?php if (!empty($prod['description'])): ?>
                                <p class="text-[11px] text-stone-400 line-clamp-1 mt-1"><?= e($prod['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="mt-3 pt-2.5 border-t border-stone-800 flex items-center justify-between">
                            <div>
                                <span class="text-sm sm:text-base font-black text-red-400"><?= $currency ?> <?= number_format($prod['price'], 2) ?></span>
                                <?php if (!empty($prod['variants'])): ?>
                                    <span class="block text-[10px] text-stone-500 font-semibold">+ Sizes</span>
                                <?php endif; ?>
                            </div>
                            <div class="w-7 h-7 rounded-xl bg-stone-800 group-hover:bg-red-600 text-stone-400 group-hover:text-white flex items-center justify-center text-xs transition shadow-xs">
                                <i class="fa-solid fa-plus"></i>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="pos-empty-products" class="hidden flex flex-col items-center justify-center py-20 text-stone-500">
                <i class="fa-solid fa-magnifying-glass text-4xl mb-3 text-stone-700"></i>
                <p class="font-bold text-stone-400">No matching items found</p>
            </div>
        </div>
    </div>
    <!-- RIGHT: LIVE CART & CHECKOUT PANEL -->
    <div class="w-full lg:w-[430px] flex flex-col justify-between bg-stone-950 border-l border-stone-800 shadow-2xl overflow-hidden">
        <div class="p-4 border-b border-stone-800 bg-stone-900/60 space-y-3">
            <div class="flex items-center gap-1.5 p-1 bg-stone-900 rounded-xl border border-stone-800">
                <button onclick="PosApp.setOrderType('dine_in')" data-type="dine_in" class="order-type-btn flex-1 py-2 px-3 rounded-lg text-xs font-bold uppercase transition bg-red-600 text-white shadow-md shadow-red-900/30">
                    <i class="fa-solid fa-chair mr-1"></i> Dine-In
                </button>
                <button onclick="PosApp.setOrderType('takeaway')" data-type="takeaway" class="order-type-btn flex-1 py-2 px-3 rounded-lg text-xs font-bold uppercase transition bg-stone-800 text-stone-300 hover:bg-stone-700">
                    <i class="fa-solid fa-bag-shopping mr-1"></i> Takeaway
                </button>
                <button onclick="PosApp.setOrderType('delivery')" data-type="delivery" class="order-type-btn flex-1 py-2 px-3 rounded-lg text-xs font-bold uppercase transition bg-stone-800 text-stone-300 hover:bg-stone-700">
                    <i class="fa-solid fa-motorcycle mr-1"></i> Delivery
                </button>
            </div>
            <div class="flex items-center justify-between gap-2">
                <button onclick="openModal('customer-search-modal')" class="flex-1 flex items-center justify-between p-2.5 bg-stone-900 border border-stone-800 rounded-xl hover:border-red-600 text-left transition shadow-xs">
                    <div class="flex items-center gap-2 overflow-hidden">
                        <i class="fa-solid fa-user-circle text-red-500 text-base flex-shrink-0"></i>
                        <div class="truncate">
                            <span id="pos-customer-name" class="block font-bold text-xs text-white truncate">No Customer Selected</span>
                            <span id="pos-customer-points" class="block text-[10px] text-stone-400 font-semibold">Optional</span>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-[10px] text-stone-500"></i>
                </button>
                <button id="pos-table-selector-btn" onclick="openModal('table-selection-modal')" class="flex items-center gap-2 px-3 py-2.5 bg-stone-900 border border-stone-800 rounded-xl hover:border-red-600 text-xs font-bold text-white transition shadow-xs">
                    <i class="fa-solid fa-utensils text-red-500"></i>
                    <span id="pos-selected-table-label" class="truncate max-w-[80px]"><?= e($preselectedTableName) ?></span>
                </button>
            </div>
        </div>
        <div id="pos-cart-items" class="flex-1 overflow-y-auto divide-y divide-stone-900 p-2"></div>
        <div class="p-4 bg-stone-900/80 border-t border-stone-800 space-y-3">
            <div class="flex items-center justify-between text-xs">
                <button onclick="openModal('discount-modal')" class="text-stone-300 hover:text-red-400 font-bold flex items-center gap-1.5">
                    <i class="fa-solid fa-tag text-red-500"></i> Add Discount
                </button>
                <div class="flex items-center gap-3">
                    <button onclick="PosApp.confirmClearCart()" class="text-rose-400 hover:text-rose-300 font-bold flex items-center gap-1" title="Clear Cart (F9)">
                        <i class="fa-solid fa-trash-can"></i> Clear (F9)
                    </button>
                    <button onclick="PosApp.holdCurrentOrder()" class="text-red-400 hover:text-red-300 font-bold flex items-center gap-1" title="Hold Order (F4)">
                        <i class="fa-solid fa-pause"></i> Hold (F4)
                    </button>
                </div>
            </div>
            <div class="bg-stone-950 p-3.5 rounded-2xl border border-stone-800 space-y-1.5 text-xs shadow-inner">
                <div class="flex justify-between text-stone-400 font-medium">
                    <span>Subtotal:</span>
                    <span id="pos-subtotal" class="font-bold text-white"><?= $currency ?> 0.00</span>
                </div>
                <div class="flex justify-between text-stone-400 font-medium">
                    <span>Discount:</span>
                    <span id="pos-discount" class="font-bold text-rose-400">-<?= $currency ?> 0.00</span>
                </div>
                <div class="border-t border-stone-800 pt-2 flex justify-between items-center text-base font-black text-white">
                    <span>Total Amount:</span>
                    <span id="pos-grand-total" class="text-xl text-red-500"><?= $currency ?> 0.00</span>
                </div>
            </div>
            <button onclick="PosApp.openCheckoutModal()" class="w-full py-4 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-500 hover:to-red-600 text-white font-black rounded-2xl text-base shadow-xl shadow-red-900/40 flex items-center justify-between px-6 transition duration-150 transform active:scale-98">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-lg bg-red-950 flex items-center justify-center text-xs font-bold border border-red-800">F2</span>
                    <span>CHARGE / PAY</span>
                </div>
                <span id="pos-pay-btn-amount" class="text-lg"><?= $currency ?> 0.00</span>
            </button>
        </div>
    </div>
</div>
<!-- MODAL: PRODUCT CUSTOMIZATION -->
<div id="product-modifier-modal" class="modal-overlay fixed inset-0 bg-black/80 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-stone-900 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-stone-800 max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between pb-3 border-b border-stone-800">
            <div>
                <h3 id="modal-product-name" class="font-extrabold text-white text-lg">Customize Item</h3>
                <p id="modal-product-price" class="text-xs font-bold text-red-500"><?= $currency ?> 0.00</p>
            </div>
            <button onclick="closeModal('product-modifier-modal')" class="text-stone-400 hover:text-white p-2"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto py-4 space-y-4">
            <div id="modal-variants-section">
                <h4 class="text-xs font-extrabold uppercase text-stone-400 tracking-wider mb-2">Select Size / Variant</h4>
                <div id="modal-variants-list" class="grid grid-cols-2 gap-2"></div>
            </div>
            <div>
                <h4 class="text-xs font-extrabold uppercase text-stone-400 tracking-wider mb-2">Milks, Syrups & Add-ons</h4>
                <div id="modal-modifiers-list" class="grid grid-cols-2 gap-2"></div>
            </div>
            <div>
                <label class="block text-xs font-extrabold uppercase text-stone-400 tracking-wider mb-1.5">Special Instructions (Barista Notes)</label>
                <input type="text" id="modal-item-notes" placeholder="e.g. Extra hot, splash of oat milk, no lid..." class="w-full px-3.5 py-2.5 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white focus:bg-black focus:ring-2 focus:ring-red-600">
            </div>
        </div>
        <div class="pt-3 border-t border-stone-800 flex gap-2">
            <button type="button" onclick="closeModal('product-modifier-modal')" class="flex-1 py-3 bg-stone-800 hover:bg-stone-700 text-stone-300 font-bold rounded-xl text-xs transition">Cancel</button>
            <button type="button" onclick="PosApp.confirmModifierSelection()" class="flex-1 py-3 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs shadow-md shadow-red-900/40 transition">Add to Order</button>
        </div>
    </div>
</div>

<!-- MODAL: TABLE SELECTION -->
<div id="table-selection-modal" class="modal-overlay fixed inset-0 bg-black/80 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-stone-900 rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-stone-800 max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between pb-3 border-b border-stone-800">
            <div>
                <h3 class="font-extrabold text-white text-lg">Select Cafe Table</h3>
                <p class="text-xs text-stone-400">Pick a table to assign this dine-in order</p>
            </div>
            <button onclick="closeModal('table-selection-modal')" class="text-stone-400 hover:text-white p-2"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto py-4">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                <?php foreach ($tables as $tbl): ?>
                    <?php
                    $bg = 'bg-stone-800 border-stone-700 text-white hover:border-red-500';
                    $statusBadge = 'Available';
                    if ($tbl['status'] === 'occupied') { $bg = 'bg-red-950/70 border-red-800 text-red-200 hover:bg-red-950'; $statusBadge = 'Occupied'; }
                    if ($tbl['status'] === 'billed') { $bg = 'bg-amber-950/70 border-amber-800 text-amber-200 hover:bg-amber-950'; $statusBadge = 'Billed'; }
                    ?>
                    <div onclick="PosApp.selectTable(<?= $tbl['id'] ?>, '<?= addslashes($tbl['name']) ?>')" class="cursor-pointer border-2 rounded-2xl p-3 text-center transition <?= $bg ?>">
                        <div class="text-xs font-bold opacity-75"><?= e($tbl['table_number']) ?></div>
                        <h4 class="font-extrabold text-sm mt-0.5"><?= e($tbl['name']) ?></h4>
                        <div class="text-[10px] mt-1 font-semibold text-stone-400"><?= $tbl['seating_capacity'] ?> Seats &bull; <?= $statusBadge ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="pt-3 border-t border-stone-800 flex justify-end">
            <button type="button" onclick="closeModal('table-selection-modal')" class="px-5 py-2.5 bg-stone-800 hover:bg-stone-700 text-stone-300 font-bold rounded-xl text-xs transition">Close</button>
        </div>
    </div>
</div>

<!-- MODAL: CUSTOMER LOOKUP -->
<div id="customer-search-modal" class="modal-overlay fixed inset-0 bg-black/80 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-stone-900 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-stone-800 max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between pb-3 border-b border-stone-800">
            <div>
                <h3 class="font-extrabold text-white text-lg">Customer Account</h3>
                <p class="text-xs text-stone-400">Search customer or register a new one</p>
            </div>
            <button onclick="closeModal('customer-search-modal')" class="text-stone-400 hover:text-white p-2"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="py-4 space-y-4 flex-1 overflow-y-auto">
            <div class="flex bg-stone-800 p-1 rounded-xl">
                <button onclick="switchCustomerTab('search')" id="cust-tab-search-btn" class="flex-1 py-2 text-xs font-bold rounded-lg bg-stone-950 shadow-xs text-white">Existing Customer</button>
                <button onclick="switchCustomerTab('new')" id="cust-tab-new-btn" class="flex-1 py-2 text-xs font-bold rounded-lg text-stone-400 hover:text-white">+ New Customer</button>
            </div>
            <div id="cust-section-search" class="space-y-3">
                <input type="text" id="cust-search-input" oninput="searchCustomers(this.value)" placeholder="Search by name or phone..." class="w-full px-3.5 py-2.5 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white focus:bg-black focus:ring-2 focus:ring-red-600">
                <div id="cust-search-results" class="space-y-2 max-h-60 overflow-y-auto">
                    <?php foreach ($customers as $c): ?>
                    <div onclick="PosApp.selectCustomer({ id: <?= $c['id'] ?>, name: '<?= addslashes($c['name']) ?>', phone: '<?= e($c['phone']) ?>', points: <?= (float)$c['loyalty_points'] ?> })" class="flex items-center justify-between p-3 border border-stone-800 rounded-xl hover:border-red-600 hover:bg-stone-800/80 cursor-pointer transition">
                        <div>
                            <h4 class="font-bold text-xs text-white"><?= e($c['name']) ?></h4>
                            <p class="text-[11px] text-stone-400"><?= e($c['phone']) ?></p>
                        </div>
                        <span class="text-xs font-extrabold text-red-400 bg-red-950/70 border border-red-900 px-2 py-0.5 rounded-lg"><?= number_format((float)$c['loyalty_points'], 2) ?> pts</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <form id="cust-section-new" onsubmit="handleQuickCustomerAdd(event)" class="hidden space-y-3">
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Full Name *</label>
                    <input type="text" id="new_cust_name" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Phone Number *</label>
                    <input type="text" id="new_cust_phone" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Email Address</label>
                    <input type="email" id="new_cust_email" class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                </div>
                <button type="submit" class="w-full py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs shadow-md shadow-red-900/40 transition">Save & Assign Customer</button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: DISCOUNT -->
<div id="discount-modal" class="modal-overlay fixed inset-0 bg-black/80 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-stone-900 rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-stone-800">
        <div class="flex items-center justify-between pb-3 border-b border-stone-800">
            <h3 class="font-extrabold text-white text-base">Apply Order Discount</h3>
            <button onclick="closeModal('discount-modal')" class="text-stone-400 hover:text-white p-1"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="py-4 space-y-3">
            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1">Discount Mode</label>
                <select id="discount_type_select" class="w-full px-3 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                    <option value="percentage">Percentage (%)</option>
                    <option value="fixed">Fixed Amount (<?= $currency ?>)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1">Discount Value</label>
                <input type="number" step="0.01" min="0" id="discount_val_input" value="0" class="w-full px-3.5 py-2.5 bg-stone-800 border border-stone-700 rounded-xl text-base font-bold text-white">
            </div>
        </div>
        <div class="flex gap-2">
            <button type="button" onclick="closeModal('discount-modal')" class="flex-1 py-2.5 bg-stone-800 hover:bg-stone-700 text-stone-300 font-bold rounded-xl text-xs">Cancel</button>
            <button type="button" onclick="applyDiscountSettings()" class="flex-1 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs shadow-md">Apply</button>
        </div>
    </div>
</div>

<!-- MODAL: CHECKOUT & PAYMENT -->
<div id="pos-checkout-modal" class="modal-overlay fixed inset-0 bg-black/80 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-stone-900 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-stone-800 max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between pb-3 border-b border-stone-800">
            <div>
                <h3 class="font-extrabold text-white text-lg">Payment Checkout</h3>
                <p class="text-xs text-stone-400">Select payment mode and complete transaction</p>
            </div>
            <button onclick="closeModal('pos-checkout-modal')" class="text-stone-400 hover:text-white p-2"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="py-4 space-y-4 flex-1 overflow-y-auto">
            <div class="p-4 bg-gradient-to-r from-red-950 via-stone-900 to-black rounded-2xl text-white text-center shadow-lg border border-red-900/60">
                <span class="text-xs font-bold text-red-400 uppercase tracking-widest">Total Payable</span>
                <h2 id="pay-modal-total" class="text-3xl font-black mt-1 text-white"><?= $currency ?> 0.00</h2>
            </div>
            <div class="grid grid-cols-4 gap-2">
                <button type="button" onclick="setCheckoutMethod('cash')" id="btn-pay-cash" class="pay-method-pill py-3 rounded-2xl border-2 border-red-600 bg-red-950/60 text-white font-bold text-xs flex flex-col items-center gap-1 transition">
                    <i class="fa-solid fa-money-bill-wave text-lg text-red-400"></i><span>Cash</span>
                </button>
                <button type="button" onclick="setCheckoutMethod('card')" id="btn-pay-card" class="pay-method-pill py-3 rounded-2xl border-2 border-stone-800 bg-stone-800 text-stone-300 font-bold text-xs flex flex-col items-center gap-1 hover:border-red-600 transition">
                    <i class="fa-solid fa-credit-card text-lg"></i><span>Card / POS</span>
                </button>
                <button type="button" onclick="setCheckoutMethod('upi')" id="btn-pay-upi" class="pay-method-pill py-3 rounded-2xl border-2 border-stone-800 bg-stone-800 text-stone-300 font-bold text-xs flex flex-col items-center gap-1 hover:border-red-600 transition">
                    <i class="fa-solid fa-qrcode text-lg"></i><span>QR Pay</span>
                </button>
                <button type="button" onclick="setCheckoutMethod('split')" id="btn-pay-split" class="pay-method-pill py-3 rounded-2xl border-2 border-stone-800 bg-stone-800 text-stone-300 font-bold text-xs flex flex-col items-center gap-1 hover:border-red-600 transition">
                    <i class="fa-solid fa-arrows-split-up-and-left text-lg"></i><span>Split Bill</span>
                </button>
            </div>
            <div id="cash-payment-section" class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Cash Tendered (<?= $currency ?>)</label>
                    <input type="number" step="0.01" min="0" id="pay-cash-tendered" oninput="PosApp.calculateChange()" class="w-full px-4 py-3 bg-stone-800 border border-stone-700 rounded-xl text-xl font-black text-white focus:bg-black focus:ring-2 focus:ring-red-600">
                </div>
                <div id="pay-preset-buttons" class="grid grid-cols-4 gap-2"></div>
                <div id="pay-change-box" class="p-3.5 rounded-2xl border bg-emerald-950/60 border-emerald-800 flex items-center justify-between">
                    <span id="pay-change-label" class="text-xs font-bold text-stone-300">Change to Return:</span>
                    <span id="pay-change-amount" class="text-2xl font-black text-emerald-400"><?= $currency ?> 0.00</span>
                </div>
            </div>
        </div>
        <div class="pt-3 border-t border-stone-800 flex gap-2">
            <button type="button" onclick="closeModal('pos-checkout-modal')" class="flex-1 py-3.5 bg-stone-800 hover:bg-stone-700 text-stone-300 font-bold rounded-xl text-xs transition">Cancel</button>
            <button type="button" id="pay-confirm-btn" onclick="PosApp.submitOrder(window.ACTIVE_PAY_METHOD || 'cash')" class="flex-2 py-3.5 bg-red-600 hover:bg-red-500 text-white font-black rounded-xl text-sm shadow-xl shadow-red-900/50 transition">
                <i class="fa-solid fa-check mr-2"></i> Complete Sale & Print
            </button>
        </div>
    </div>
</div>

<!-- MODAL: THERMAL RECEIPT SUCCESS PREVIEW -->
<div id="receipt-success-modal" class="modal-overlay fixed inset-0 bg-black/85 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-stone-900 rounded-3xl max-w-sm w-full p-5 shadow-2xl border border-stone-800 flex flex-col">
        <div class="flex items-center justify-between pb-2 border-b border-stone-800">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-400 text-lg"></i>
                <h4 class="font-extrabold text-white text-sm">Receipt: <span id="receipt-invoice-no"></span></h4>
            </div>
            <button onclick="closeModal('receipt-success-modal')" class="text-stone-400 hover:text-white p-1"><i class="fa-solid fa-xmark text-base"></i></button>
        </div>
        <div class="py-3 flex-1 overflow-y-auto">
            <iframe id="thermal-receipt-frame" class="w-full h-80 border border-stone-700 rounded-xl bg-white"></iframe>
        </div>
        <div class="pt-2 flex gap-2">
            <button onclick="closeModal('receipt-success-modal')" class="flex-1 py-2.5 bg-stone-800 hover:bg-stone-700 text-stone-300 font-bold rounded-xl text-xs">New Sale</button>
            <button onclick="document.getElementById('thermal-receipt-frame').contentWindow.print()" class="flex-1 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs shadow-md">
                <i class="fa-solid fa-print mr-1"></i> Print Receipt
            </button>
        </div>
    </div>
</div>

<!-- MODAL: HELD ORDERS -->
<div id="held-orders-modal" class="modal-overlay fixed inset-0 bg-black/80 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-stone-900 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-stone-800 max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between pb-3 border-b border-stone-800">
            <div>
                <h3 class="font-extrabold text-white text-lg">Parked / Held Orders</h3>
                <p class="text-xs text-stone-400">Recall a suspended cart session</p>
            </div>
            <button onclick="closeModal('held-orders-modal')" class="text-stone-400 hover:text-white p-2"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div id="held-orders-list" class="py-4 space-y-2 flex-1 overflow-y-auto"></div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/pos.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    PosApp.init(window.POS_SETTINGS);
    <?php if ($preselectedTableId): ?>
      PosApp.selectTable(<?= $preselectedTableId ?>, "<?= addslashes($preselectedTableName) ?>");
    <?php endif; ?>
  });

  window.ACTIVE_PAY_METHOD = 'cash';
  function setCheckoutMethod(method) {
    window.ACTIVE_PAY_METHOD = method;
    document.querySelectorAll('.pay-method-pill').forEach(b => {
      b.className = 'pay-method-pill py-3 rounded-2xl border-2 border-stone-800 bg-stone-800 text-stone-300 font-bold text-xs flex flex-col items-center gap-1 hover:border-red-600 transition';
    });
    const activeBtn = document.getElementById('btn-pay-' + method);
    if (activeBtn) {
      activeBtn.className = 'pay-method-pill py-3 rounded-2xl border-2 border-red-600 bg-red-950/60 text-white font-bold text-xs flex flex-col items-center gap-1 transition';
    }
  }

  function applyDiscountSettings() {
    PosApp.state.discountType = document.getElementById('discount_type_select').value;
    PosApp.state.discountValue = parseFloat(document.getElementById('discount_val_input').value) || 0;
    PosApp.renderCart();
    closeModal('discount-modal');
    Toast.success('Discount applied');
  }

  function switchCustomerTab(tab) {
    if (tab === 'search') {
      document.getElementById('cust-section-search').classList.remove('hidden');
      document.getElementById('cust-section-new').classList.add('hidden');
      document.getElementById('cust-tab-search-btn').className = 'flex-1 py-2 text-xs font-bold rounded-lg bg-stone-950 shadow-xs text-white';
      document.getElementById('cust-tab-new-btn').className = 'flex-1 py-2 text-xs font-bold rounded-lg text-stone-400 hover:text-white';
    } else {
      document.getElementById('cust-section-search').classList.add('hidden');
      document.getElementById('cust-section-new').classList.remove('hidden');
      document.getElementById('cust-tab-new-btn').className = 'flex-1 py-2 text-xs font-bold rounded-lg bg-stone-950 shadow-xs text-white';
      document.getElementById('cust-tab-search-btn').className = 'flex-1 py-2 text-xs font-bold rounded-lg text-stone-400 hover:text-white';
    }
  }

  async function handleQuickCustomerAdd(e) {
    e.preventDefault();
    const name = document.getElementById('new_cust_name').value;
    const phone = document.getElementById('new_cust_phone').value;
    const email = document.getElementById('new_cust_email').value;

    const res = await fetch('<?= BASE_URL ?>/api/save_customer.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, phone, email })
    });
    const data = await res.json();
    if (data.success) {
      PosApp.selectCustomer(data.customer);
    } else {
      Toast.error(data.message || 'Could not save customer');
    }
  }
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
