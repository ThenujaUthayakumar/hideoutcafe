<?php
/**
 * Aura Cafe POS - Customer Directory & Loyalty Program
 */
require_once __DIR__ . '/config/functions.php';
requireAuth();

$title = 'Customers & Loyalty';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? '$';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_customer') {
        $cid = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $name = sanitize($_POST['name']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $points = (int)($_POST['loyalty_points'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');

        if ($cid) {
            db()->query(
                "UPDATE customers SET name = :name, phone = :phone, email = :email, address = :addr, loyalty_points = :pts, notes = :notes WHERE id = :id",
                [':name' => $name, ':phone' => $phone, ':email' => $email, ':addr' => $address, ':pts' => $points, ':notes' => $notes, ':id' => $cid]
            );
            setFlash('success', "Customer '{$name}' updated.");
        } else {
            db()->query(
                "INSERT INTO customers (name, phone, email, address, loyalty_points, notes) VALUES (:name, :phone, :email, :addr, :pts, :notes)",
                [':name' => $name, ':phone' => $phone, ':email' => $email, ':addr' => $address, ':pts' => $points, ':notes' => $notes]
            );
            setFlash('success', "Customer '{$name}' added.");
        }
        header("Location: " . BASE_URL . "/customers.php");
        exit;
    }

    if ($action === 'delete_customer' && hasRole([ROLE_ADMIN, ROLE_MANAGER])) {
        $cid = (int)$_POST['customer_id'];
        if ($cid > 1) { // Prevent deleting default walk-in customer
            db()->query("DELETE FROM customers WHERE id = :id", [':id' => $cid]);
            setFlash('success', "Customer account deleted.");
        }
        header("Location: " . BASE_URL . "/customers.php");
        exit;
    }
}

$query = trim($_GET['q'] ?? '');
$sql = "SELECT c.*, COUNT(o.id) as order_count 
        FROM customers c 
        LEFT JOIN orders o ON c.id = o.customer_id 
        WHERE 1=1";
$params = [];

if (!empty($query)) {
    $sql .= " AND (c.name LIKE :q1 OR c.phone LIKE :q2 OR c.email LIKE :q3)";
    $params[':q1'] = "%$query%";
    $params[':q2'] = "%$query%";
    $params[':q3'] = "%$query%";
}
$sql .= " GROUP BY c.id ORDER BY c.id DESC";

$customers = db()->fetchAll($sql, $params);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#fbf9f6]">
    <div class="max-w-7xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-stone-900">Customers & Loyalty Program</h2>
                <p class="text-xs text-stone-500">Manage cafe regulars, view reward points, and purchase history</p>
            </div>
            <button onclick="openCustomerModal()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-800 hover:bg-amber-700 text-white font-bold rounded-2xl text-xs shadow-md transition">
                <i class="fa-solid fa-user-plus"></i> Add New Customer
            </button>
        </div>

        <!-- Search Bar -->
        <div class="card-cafe p-4">
            <form method="GET" action="customers.php" class="flex gap-3 text-xs">
                <div class="flex-1 relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-stone-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="q" value="<?= e($query) ?>" placeholder="Search customer by name, phone or email..." class="w-full pl-10 pr-3 py-2.5 bg-stone-50 border border-stone-200 rounded-xl">
                </div>
                <button type="submit" class="px-5 py-2.5 bg-stone-800 text-white font-bold rounded-xl shadow-xs">Search</button>
            </form>
        </div>

        <!-- Table -->
        <div class="card-cafe overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-stone-100/90 text-stone-600 font-bold uppercase text-[10px] tracking-wider border-b border-stone-200">
                        <tr>
                            <th class="p-3.5">Customer Name</th>
                            <th class="p-3.5">Phone Number</th>
                            <th class="p-3.5">Email</th>
                            <th class="p-3.5 text-center">Visits</th>
                            <th class="p-3.5 text-right">Loyalty Points</th>
                            <th class="p-3.5 text-right">Total Spent</th>
                            <th class="p-3.5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <?php if (empty($customers)): ?>
                            <tr><td colspan="7" class="p-8 text-center text-stone-400">No customers found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($customers as $c): ?>
                            <tr class="hover:bg-stone-50 transition">
                                <td class="p-3.5">
                                    <span class="font-bold text-stone-900 text-sm block"><?= e($c['name']) ?></span>
                                    <?php if (!empty($c['notes'])): ?>
                                        <span class="text-[11px] text-amber-700 italic block mt-0.5"><?= e($c['notes']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5 font-semibold text-stone-700"><?= e($c['phone']) ?></td>
                                <td class="p-3.5 text-stone-500"><?= e($c['email'] ?? '-') ?></td>
                                <td class="p-3.5 text-center font-bold text-stone-800"><?= (int)$c['order_count'] ?></td>
                                <td class="p-3.5 text-right">
                                    <span class="px-2.5 py-1 rounded-xl bg-amber-100 text-amber-900 font-black text-xs"><?= (int)$c['loyalty_points'] ?> pts</span>
                                </td>
                                <td class="p-3.5 text-right font-black text-stone-900 text-sm"><?= $currency ?><?= number_format($c['total_spent'], 2) ?></td>
                                <td class="p-3.5 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button onclick="editCustomer(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)" class="p-2 hover:bg-stone-200 text-stone-700 rounded-xl transition" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <?php if ($c['id'] > 1 && hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
                                            <form method="POST" action="customers.php" onsubmit="return confirm('Delete customer <?= addslashes($c['name']) ?>?');" class="inline">
                                                <input type="hidden" name="action" value="delete_customer">
                                                <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="p-2 hover:bg-rose-100 text-rose-600 rounded-xl transition" title="Delete">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
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

<!-- Add/Edit Customer Modal -->
<div id="customer-form-modal" class="modal-overlay fixed inset-0 bg-stone-900/60 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-stone-200">
        <div class="flex items-center justify-between pb-3 border-b border-stone-100">
            <h3 id="cust-modal-title" class="font-extrabold text-stone-900 text-base">Add Customer</h3>
            <button onclick="closeModal('customer-form-modal')" class="text-stone-400 hover:text-stone-600 p-1"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="customers.php" class="py-4 space-y-3">
            <input type="hidden" name="action" value="save_customer">
            <input type="hidden" name="customer_id" id="cust_form_id">

            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Full Name *</label>
                <input type="text" name="name" id="cust_form_name" required class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Phone Number *</label>
                <input type="text" name="phone" id="cust_form_phone" required class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Email Address</label>
                <input type="email" name="email" id="cust_form_email" class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Loyalty Points Balance</label>
                <input type="number" name="loyalty_points" id="cust_form_points" value="0" class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs font-bold text-amber-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Address / Delivery Notes</label>
                <textarea name="address" id="cust_form_address" rows="2" class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Preferences & Notes</label>
                <input type="text" name="notes" id="cust_form_notes" placeholder="e.g. Always takes oat milk" class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs">
            </div>

            <div class="pt-2 flex gap-2">
                <button type="button" onclick="closeModal('customer-form-modal')" class="flex-1 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl text-xs">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 bg-amber-800 hover:bg-amber-700 text-white font-bold rounded-xl text-xs shadow-md">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCustomerModal() {
  document.getElementById('cust-modal-title').textContent = 'Add Customer';
  document.getElementById('cust_form_id').value = '';
  document.getElementById('cust_form_name').value = '';
  document.getElementById('cust_form_phone').value = '';
  document.getElementById('cust_form_email').value = '';
  document.getElementById('cust_form_points').value = '0';
  document.getElementById('cust_form_address').value = '';
  document.getElementById('cust_form_notes').value = '';
  openModal('customer-form-modal');
}
function editCustomer(c) {
  document.getElementById('cust-modal-title').textContent = 'Edit Customer: ' + c.name;
  document.getElementById('cust_form_id').value = c.id;
  document.getElementById('cust_form_name').value = c.name;
  document.getElementById('cust_form_phone').value = c.phone;
  document.getElementById('cust_form_email').value = c.email || '';
  document.getElementById('cust_form_points').value = c.loyalty_points;
  document.getElementById('cust_form_address').value = c.address || '';
  document.getElementById('cust_form_notes').value = c.notes || '';
  openModal('customer-form-modal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
