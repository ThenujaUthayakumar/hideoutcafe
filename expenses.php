<?php
/**
 * Aura Cafe POS - Expense Tracking & Petty Cash Logs
 */
require_once __DIR__ . '/config/functions.php';
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$title = 'Expenses';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? '$';
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_expense') {
        $category = sanitize($_POST['category']);
        $amount = (float)$_POST['amount'];
        $desc = sanitize($_POST['description']);
        $date = sanitize($_POST['expense_date'] ?? date('Y-m-d'));

        db()->query(
            "INSERT INTO expenses (category, amount, description, user_id, expense_date) VALUES (:cat, :amt, :desc, :uid, :date)",
            [':cat' => $category, ':amt' => $amount, ':desc' => $desc, ':uid' => $user['id'], ':date' => $date]
        );
        setFlash('success', 'Expense recorded successfully.');
        header("Location: " . BASE_URL . "/expenses.php");
        exit;
    }

    if ($action === 'delete_expense') {
        $id = (int)$_POST['expense_id'];
        db()->query("DELETE FROM expenses WHERE id = :id", [':id' => $id]);
        setFlash('success', 'Expense record deleted.');
        header("Location: " . BASE_URL . "/expenses.php");
        exit;
    }
}

$monthFilter = $_GET['month'] ?? date('Y-m');
$expenses = db()->fetchAll(
    "SELECT e.*, u.name as staff_name 
     FROM expenses e 
     JOIN users u ON e.user_id = u.id 
     WHERE DATE_FORMAT(e.expense_date, '%Y-%m') = :m 
     ORDER BY e.expense_date DESC, e.id DESC",
    [':m' => $monthFilter]
);

$totalExpense = array_sum(array_column($expenses, 'amount'));

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#fbf9f6]">
    <div class="max-w-7xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-stone-900">Cafe Expenses & Petty Cash</h2>
                <p class="text-xs text-stone-500">Record day-to-day ingredient purchases, dairy supplies, and utilities</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="px-4 py-2 bg-white border border-stone-200 rounded-2xl shadow-xs text-right">
                    <span class="text-[10px] uppercase font-bold text-stone-400">Total Month Expenses</span>
                    <div class="text-base font-black text-rose-600"><?= $currency ?><?= number_format($totalExpense, 2) ?></div>
                </div>
                <button onclick="openModal('expense-form-modal')" class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-800 hover:bg-amber-700 text-white font-bold rounded-2xl text-xs shadow-md transition">
                    <i class="fa-solid fa-plus"></i> Record Expense
                </button>
            </div>
        </div>

        <!-- Month Filter -->
        <div class="card-cafe p-4">
            <form method="GET" action="expenses.php" class="flex items-center gap-3 text-xs">
                <label class="font-bold text-stone-700">Filter Month:</label>
                <input type="month" name="month" value="<?= e($monthFilter) ?>" class="px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl font-bold">
                <button type="submit" class="px-4 py-2 bg-stone-800 text-white font-bold rounded-xl shadow-xs">View</button>
            </form>
        </div>

        <!-- Expenses Table -->
        <div class="card-cafe overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-stone-100/90 text-stone-600 font-bold uppercase text-[10px] tracking-wider border-b border-stone-200">
                        <tr>
                            <th class="p-3.5">Date</th>
                            <th class="p-3.5">Category</th>
                            <th class="p-3.5">Description</th>
                            <th class="p-3.5">Logged By</th>
                            <th class="p-3.5 text-right">Amount</th>
                            <th class="p-3.5 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <?php if (empty($expenses)): ?>
                            <tr><td colspan="6" class="p-8 text-center text-stone-400">No expenses recorded for this month.</td></tr>
                        <?php else: ?>
                            <?php foreach ($expenses as $exp): ?>
                            <tr class="hover:bg-stone-50 transition">
                                <td class="p-3.5 font-bold text-stone-900"><?= date('M d, Y', strtotime($exp['expense_date'])) ?></td>
                                <td class="p-3.5"><span class="px-2.5 py-0.5 rounded-lg bg-amber-50 text-amber-900 font-bold"><?= e($exp['category']) ?></span></td>
                                <td class="p-3.5 text-stone-700 font-medium"><?= e($exp['description']) ?></td>
                                <td class="p-3.5 text-stone-500"><?= e($exp['staff_name']) ?></td>
                                <td class="p-3.5 text-right font-black text-rose-600 text-sm"><?= $currency ?><?= number_format($exp['amount'], 2) ?></td>
                                <td class="p-3.5 text-center">
                                    <form method="POST" action="expenses.php" onsubmit="return confirm('Delete this expense?');" class="inline">
                                        <input type="hidden" name="action" value="delete_expense">
                                        <input type="hidden" name="expense_id" value="<?= $exp['id'] ?>">
                                        <button type="submit" class="p-1.5 hover:bg-rose-50 text-rose-600 rounded-lg transition" title="Delete">
                                            <i class="fa-solid fa-trash text-xs"></i>
                                        </button>
                                    </form>
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

<!-- Add Expense Modal -->
<div id="expense-form-modal" class="modal-overlay fixed inset-0 bg-stone-900/60 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-stone-200">
        <div class="flex items-center justify-between pb-3 border-b border-stone-100">
            <h3 class="font-extrabold text-stone-900 text-base">Record Cafe Expense</h3>
            <button onclick="closeModal('expense-form-modal')" class="text-stone-400 hover:text-stone-600 p-1"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="expenses.php" class="py-4 space-y-3">
            <input type="hidden" name="action" value="save_expense">

            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Expense Category *</label>
                <select name="category" required class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs font-semibold">
                    <option value="Coffee Beans & Roasts">Coffee Beans & Roasts</option>
                    <option value="Dairy & Plant Milks">Dairy & Plant Milks</option>
                    <option value="Bakery Ingredients">Bakery Ingredients</option>
                    <option value="Store Utilities">Store Utilities (Cups, Lids, Bags)</option>
                    <option value="Equipment & Maintenance">Equipment & Maintenance</option>
                    <option value="Staff Meals & Welfare">Staff Meals & Welfare</option>
                    <option value="Miscellaneous">Miscellaneous</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Amount ($) *</label>
                <input type="number" step="0.01" min="0.01" name="amount" required class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-base font-bold text-rose-600">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Expense Date</label>
                <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Description / Vendor *</label>
                <textarea name="description" rows="2" required placeholder="e.g. Purchased 10 Gallons Whole Milk from Farm Direct" class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs"></textarea>
            </div>

            <div class="pt-2 flex gap-2">
                <button type="button" onclick="closeModal('expense-form-modal')" class="flex-1 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl text-xs">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 bg-amber-800 hover:bg-amber-700 text-white font-bold rounded-xl text-xs shadow-md">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
