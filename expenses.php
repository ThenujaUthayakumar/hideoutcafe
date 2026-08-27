<?php
/**
 * Aura Cafe POS - Expense Tracking & Petty Cash Logs
 */
require_once __DIR__ . '/config/functions.php';
requireAuth();

$title = 'Expenses';
$settings = getSettings();
$currency = $settings['currency_symbol'] ?? '$';
$user = currentUser();
$isAdmin = hasRole(ROLE_ADMIN);

try {
    $requestNoteColumn = db()->fetchOne("SHOW COLUMNS FROM expenses LIKE 'request_note'");
    if (!$requestNoteColumn) {
        db()->query("ALTER TABLE expenses ADD COLUMN request_note TEXT NULL AFTER description");
    }
    $requestStatusColumn = db()->fetchOne("SHOW COLUMNS FROM expenses LIKE 'request_status'");
    if (!$requestStatusColumn) {
        db()->query("ALTER TABLE expenses ADD COLUMN request_status ENUM('not_requested', 'pending', 'resolved') NOT NULL DEFAULT 'not_requested' AFTER request_note");
    } elseif (strpos($requestStatusColumn['Type'], "'not_requested'") === false) {
        db()->query("ALTER TABLE expenses MODIFY COLUMN request_status ENUM('not_requested', 'pending', 'resolved') NOT NULL DEFAULT 'not_requested'");
    }
    $attachmentColumn = db()->fetchOne("SHOW COLUMNS FROM expenses LIKE 'attachment_name'");
    if (!$attachmentColumn) {
        db()->query("ALTER TABLE expenses ADD COLUMN attachment_name VARCHAR(255) NULL AFTER request_status");
    }
    $attachmentMimeColumn = db()->fetchOne("SHOW COLUMNS FROM expenses LIKE 'attachment_mime'");
    if (!$attachmentMimeColumn) {
        db()->query("ALTER TABLE expenses ADD COLUMN attachment_mime VARCHAR(100) NULL AFTER attachment_name");
    }
} catch (Exception $e) {
    // Existing installations can continue until the schema is upgraded.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_expense') {
        $category = sanitize($_POST['category']);
        $amount = (float)$_POST['amount'];
        $desc = sanitize($_POST['description']);
        $date = sanitize($_POST['expense_date'] ?? date('Y-m-d'));
        $requestNote = '';
        $attachmentName = null;
        $attachmentMime = null;

        $hasAttachment = !empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK;
        if ($desc === '' && !$hasAttachment) {
            setFlash('error', 'Please provide either a Description/Vendor or an image/PDF attachment for this expense.');
            header("Location: " . BASE_URL . "/expenses.php");
            exit;
        }
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['attachment']['size'] > 10 * 1024 * 1024) {
                setFlash('error', 'Attachment must be 10 MB or smaller.');
                header("Location: " . BASE_URL . "/expenses.php");
                exit;
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $attachmentMime = $finfo->file($_FILES['attachment']['tmp_name']);
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($attachmentMime, $allowedMimes, true)) {
                setFlash('error', 'Only PDF, JPG, PNG, or WEBP attachments are allowed.');
                header("Location: " . BASE_URL . "/expenses.php");
                exit;
            }
            $extension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $attachmentName = bin2hex(random_bytes(16)) . '.' . $extension;
            $uploadDir = BASE_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . DIRECTORY_SEPARATOR . $attachmentName)) {
                setFlash('error', 'The attachment could not be saved.');
                header("Location: " . BASE_URL . "/expenses.php");
                exit;
            }
        }

        db()->query(
            "INSERT INTO expenses (category, amount, description, request_note, request_status, attachment_name, attachment_mime, user_id, expense_date) VALUES (:cat, :amt, :desc, :request_note, :request_status, :attachment_name, :attachment_mime, :uid, :date)",
            [':cat' => $category, ':amt' => $amount, ':desc' => $desc, ':request_note' => $requestNote, ':request_status' => $requestNote !== '' ? 'pending' : 'not_requested', ':attachment_name' => $attachmentName, ':attachment_mime' => $attachmentMime, ':uid' => $user['id'], ':date' => $date]
        );
        setFlash('success', 'Expense recorded successfully.');
        header("Location: " . BASE_URL . "/expenses.php");
        exit;
    }

    if ($action === 'update_request_note') {
        $id = (int)$_POST['expense_id'];
        $requestNote = sanitize($_POST['request_note'] ?? '');
        $existingRequest = db()->fetchOne(
            "SELECT request_status FROM expenses WHERE id = :id AND user_id = :user_id",
            [':id' => $id, ':user_id' => $user['id']]
        );
        if (!$existingRequest || $existingRequest['request_status'] === 'resolved') {
            setFlash('error', 'This expense request has already been resolved by Admin.');
            header("Location: " . BASE_URL . "/expenses.php");
            exit;
        }
        db()->query(
            "UPDATE expenses SET request_note = :request_note, request_status = 'pending' WHERE id = :id AND user_id = :user_id",
            [':request_note' => $requestNote, ':id' => $id, ':user_id' => $user['id']]
        );
        setFlash('success', 'Request note sent to Admin.');
        header("Location: " . BASE_URL . "/expenses.php");
        exit;
    }

    if ($action === 'accept_request') {
        if (!$isAdmin) {
            setFlash('error', 'Only Admin can accept expense requests.');
            header("Location: " . BASE_URL . "/expenses.php");
            exit;
        }

        $id = (int)$_POST['expense_id'];
        db()->query("UPDATE expenses SET request_status = 'resolved' WHERE id = :id", [':id' => $id]);
        setFlash('success', 'Expense request marked as resolved.');
        header("Location: " . BASE_URL . "/expenses.php");
        exit;
    }

    if ($action === 'edit_expense') {
        if (!$isAdmin) {
            setFlash('error', 'Only Admin can edit expenses.');
            header("Location: " . BASE_URL . "/expenses.php");
            exit;
        }

        $id = (int)$_POST['expense_id'];
        $category = sanitize($_POST['category'] ?? 'Miscellaneous');
        $amount = (float)($_POST['amount'] ?? 0);
        $desc = sanitize($_POST['description'] ?? '');
        $date = sanitize($_POST['expense_date'] ?? date('Y-m-d'));
        $requestNote = sanitize($_POST['request_note'] ?? '');
        $existingExpense = db()->fetchOne("SELECT attachment_name FROM expenses WHERE id = :id", [':id' => $id]);
        if ($desc === '' && empty($existingExpense['attachment_name'])) {
            setFlash('error', 'Please keep a description or an attachment on the expense.');
            header("Location: " . BASE_URL . "/expenses.php");
            exit;
        }
        db()->query(
            "UPDATE expenses SET category = :category, amount = :amount, description = :description,
             expense_date = :expense_date, request_note = :request_note, request_status = 'resolved' WHERE id = :id",
            [
                ':category' => $category,
                ':amount' => $amount,
                ':description' => $desc,
                ':expense_date' => $date,
                ':request_note' => $requestNote,
                ':id' => $id
            ]
        );
        setFlash('success', 'Expense updated successfully.');
        header("Location: " . BASE_URL . "/expenses.php");
        exit;
    }

    if ($action === 'delete_expense') {
        if (!$isAdmin) {
            setFlash('error', 'Only Admin can delete expenses.');
            header("Location: " . BASE_URL . "/expenses.php");
            exit;
        }

        $id = (int)$_POST['expense_id'];
        db()->query("DELETE FROM expenses WHERE id = :id", [':id' => $id]);
        setFlash('success', 'Expense record deleted.');
        header("Location: " . BASE_URL . "/expenses.php");
        exit;
    }
}

$monthFilter = $_GET['month'] ?? date('Y-m');
$expenseSearch = trim($_GET['q'] ?? '');
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$expenseSql = "SELECT e.*, u.name as staff_name
               FROM expenses e
               JOIN users u ON e.user_id = u.id
               WHERE DATE_FORMAT(e.expense_date, '%Y-%m') = :m";
$expenseParams = [':m' => $monthFilter];
if (!$isAdmin) {
    $expenseSql .= " AND e.user_id = :user_id";
    $expenseParams[':user_id'] = $user['id'];
}
if ($expenseSearch !== '') {
    $expenseSql .= " AND (e.category LIKE :search_category OR e.description LIKE :search_description OR u.name LIKE :search_staff)";
    $expenseParams[':search_category'] = "%$expenseSearch%";
    $expenseParams[':search_description'] = "%$expenseSearch%";
    $expenseParams[':search_staff'] = "%$expenseSearch%";
}
$expenseTotalRow = db()->fetchOne("SELECT COALESCE(SUM(e.amount), 0) AS total FROM expenses e JOIN users u ON e.user_id = u.id WHERE DATE_FORMAT(e.expense_date, '%Y-%m') = :m" . (!$isAdmin ? " AND e.user_id = :user_id" : '') . ($expenseSearch !== '' ? " AND (e.category LIKE :search_category OR e.description LIKE :search_description OR u.name LIKE :search_staff)" : ''), $expenseParams);
$totalExpense = (float)($expenseTotalRow['total'] ?? 0);
$countRow = db()->fetchOne("SELECT COUNT(*) AS total FROM ($expenseSql) filtered_expenses", $expenseParams);
$totalExpenses = (int)($countRow['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalExpenses / $perPage));
$page = min($page, $totalPages);
$expenseSql .= " ORDER BY e.expense_date DESC, e.id DESC LIMIT " . (($page - 1) * $perPage) . ", " . $perPage;
$expenses = db()->fetchAll($expenseSql, $expenseParams);

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
                <input type="text" name="q" value="<?= e($expenseSearch) ?>" placeholder="Search category, description or staff" class="flex-1 min-w-48 px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl">
                <button type="submit" class="px-4 py-2 bg-amber-800 text-white font-bold rounded-xl shadow-xs">Search</button>
                <?php if ($expenseSearch !== '' || $monthFilter !== date('Y-m')): ?>
                    <a href="<?= BASE_URL ?>/expenses.php" class="px-4 py-2 bg-stone-200 text-stone-700 font-bold rounded-xl">Clear</a>
                <?php endif; ?>
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
                            <th class="p-3.5 text-center">Attachment</th>
                            <th class="p-3.5 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <?php if (empty($expenses)): ?>
                            <tr><td colspan="7" class="p-8 text-center text-stone-400">No expenses recorded for this month.</td></tr>
                        <?php else: ?>
                            <?php foreach ($expenses as $exp): ?>
                            <tr class="hover:bg-stone-50 transition">
                                <td class="p-3.5 font-bold text-stone-900"><?= date('M d, Y', strtotime($exp['expense_date'])) ?></td>
                                <td class="p-3.5"><span class="px-2.5 py-0.5 rounded-lg bg-amber-50 text-amber-900 font-bold"><?= e($exp['category']) ?></span></td>
                                <td class="p-3.5 text-stone-700 font-medium"><?= e($exp['description']) ?></td>
                                <td class="p-3.5 text-stone-500"><?= e($exp['staff_name']) ?></td>
                                <td class="p-3.5 text-right font-black text-rose-600 text-sm"><?= $currency ?><?= number_format($exp['amount'], 2) ?></td>
                                <td class="p-3.5 text-center">
                                    <?php if (!empty($exp['attachment_name'])): ?>
                                        <?php if ($isAdmin): ?>
                                            <a href="<?= BASE_URL ?>/expense_attachment.php?id=<?= (int)$exp['id'] ?>" target="_blank" class="text-emerald-700 font-bold hover:underline" title="View attachment"><i class="fa-solid fa-paperclip mr-1"></i>View</a>
                                        <?php else: ?>
                                            <span class="text-emerald-700 font-bold"><i class="fa-solid fa-check mr-1"></i>Attached</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-stone-400">Not required</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5 text-center">
                                    <?php if (!empty($exp['request_note'])): ?>
                                        <span class="block text-[10px] <?= $exp['request_status'] === 'resolved' ? 'text-emerald-700' : 'text-amber-700' ?> mb-1" title="Request note"><i class="fa-solid fa-note-sticky mr-1"></i><?= e($exp['request_note']) ?></span>
                                        <span class="block text-[10px] <?= $exp['request_status'] === 'resolved' ? 'text-emerald-700' : 'text-amber-700' ?> mb-1"><?= $exp['request_status'] === 'resolved' ? 'Resolved by Admin' : 'Pending Admin review' ?></span>
                                    <?php endif; ?>
                                    <?php if ($isAdmin): ?>
                                        <?php if (!empty($exp['request_note']) && $exp['request_status'] !== 'resolved'): ?>
                                            <form method="POST" action="expenses.php" class="inline">
                                                <input type="hidden" name="action" value="accept_request">
                                                <input type="hidden" name="expense_id" value="<?= $exp['id'] ?>">
                                                <button type="submit" class="p-1.5 hover:bg-emerald-50 text-emerald-700 rounded-lg transition" title="Accept request">
                                                    <i class="fa-solid fa-check text-xs"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button type="button" onclick='openExpenseEdit(<?= json_encode($exp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="p-1.5 hover:bg-amber-50 text-amber-700 rounded-lg transition" title="Edit">
                                            <i class="fa-solid fa-pen text-xs"></i>
                                        </button>
                                        <form method="POST" action="expenses.php" onsubmit="return confirm('Delete this expense?');" class="inline">
                                            <input type="hidden" name="action" value="delete_expense">
                                            <input type="hidden" name="expense_id" value="<?= $exp['id'] ?>">
                                            <button type="submit" class="p-1.5 hover:bg-rose-50 text-rose-600 rounded-lg transition" title="Delete">
                                                <i class="fa-solid fa-trash text-xs"></i>
                                            </button>
                                        </form>
                                    <?php elseif (($exp['request_status'] ?? 'resolved') !== 'resolved'): ?>
                                        <button type="button" onclick='openRequestNote(<?= (int)$exp['id'] ?>, <?= json_encode($exp['request_note'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="p-1.5 hover:bg-amber-50 text-amber-700 rounded-lg transition" title="Request Admin correction">
                                            <i class="fa-solid fa-message text-xs"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-[10px] text-emerald-700 font-bold">Request closed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= renderPagination($page, $totalExpenses, $perPage, ['month' => $monthFilter, 'q' => $expenseSearch]) ?>
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
        <form method="POST" action="expenses.php" enctype="multipart/form-data" class="py-4 space-y-3">
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
                <label class="block text-xs font-bold text-stone-700 mb-1">Amount (<?= e($currency) ?>) *</label>
                <input type="number" step="0.01" min="0.01" name="amount" required class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-base font-bold text-rose-600">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Expense Date</label>
                <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Description / Vendor</label>
                <textarea name="description" rows="2" placeholder="e.g. Purchased 10 Gallons Whole Milk from Farm Direct" class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Receipt / Invoice Attachment</label>
                <input type="file" name="attachment" accept="application/pdf,image/jpeg,image/png,image/webp" class="w-full text-xs">
                <p class="text-[10px] text-stone-500 mt-1">Add a description/vendor or an attachment. PDF, JPG, PNG, or WEBP up to 10 MB.</p>
            </div>

            <div class="pt-2 flex gap-2">
                <button type="button" onclick="closeModal('expense-form-modal')" class="flex-1 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl text-xs">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 bg-amber-800 hover:bg-amber-700 text-white font-bold rounded-xl text-xs shadow-md">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<!-- Request Admin Correction Modal -->
<div id="expense-request-modal" class="modal-overlay fixed inset-0 bg-stone-900/60 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-stone-200">
        <div class="flex items-center justify-between pb-3 border-b border-stone-100">
            <h3 class="font-extrabold text-stone-900 text-base">Request Admin Correction</h3>
            <button onclick="closeModal('expense-request-modal')" class="text-stone-400 hover:text-stone-600 p-1"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="expenses.php" class="py-4 space-y-3">
            <input type="hidden" name="action" value="update_request_note">
            <input type="hidden" name="expense_id" id="request_expense_id">
            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Note for Admin *</label>
                <textarea name="request_note" id="request_note_input" rows="4" required placeholder="Explain what is incorrect and what should be changed..." class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs"></textarea>
            </div>
            <div class="pt-2 flex gap-2">
                <button type="button" onclick="closeModal('expense-request-modal')" class="flex-1 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl text-xs">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 bg-amber-800 hover:bg-amber-700 text-white font-bold rounded-xl text-xs shadow-md">Send Request</button>
            </div>
        </form>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Admin Edit Expense Modal -->
<div id="expense-edit-modal" class="modal-overlay fixed inset-0 bg-stone-900/60 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-stone-200">
        <div class="flex items-center justify-between pb-3 border-b border-stone-100">
            <h3 class="font-extrabold text-stone-900 text-base">Edit Expense</h3>
            <button onclick="closeModal('expense-edit-modal')" class="text-stone-400 hover:text-stone-600 p-1"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="expenses.php" class="py-4 space-y-3">
            <input type="hidden" name="action" value="edit_expense">
            <input type="hidden" name="expense_id" id="edit_expense_id">
            <div><label class="block text-xs font-bold text-stone-700 mb-1">Category *</label><input type="text" name="category" id="edit_expense_category" required class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs"></div>
            <div><label class="block text-xs font-bold text-stone-700 mb-1">Amount *</label><input type="number" step="0.01" min="0.01" name="amount" id="edit_expense_amount" required class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs"></div>
            <div><label class="block text-xs font-bold text-stone-700 mb-1">Expense Date *</label><input type="date" name="expense_date" id="edit_expense_date" required class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs"></div>
            <div><label class="block text-xs font-bold text-stone-700 mb-1">Description *</label><textarea name="description" id="edit_expense_description" rows="2" required class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs"></textarea></div>
            <div><label class="block text-xs font-bold text-stone-700 mb-1">Request Note</label><textarea name="request_note" id="edit_expense_note" rows="2" class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs"></textarea></div>
            <div class="pt-2 flex gap-2"><button type="button" onclick="closeModal('expense-edit-modal')" class="flex-1 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl text-xs">Cancel</button><button type="submit" class="flex-1 py-2.5 bg-amber-800 hover:bg-amber-700 text-white font-bold rounded-xl text-xs shadow-md">Save Changes</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openRequestNote(id, note) {
    document.getElementById('request_expense_id').value = id;
    document.getElementById('request_note_input').value = note || '';
    openModal('expense-request-modal');
}

<?php if ($isAdmin): ?>
function openExpenseEdit(expense) {
    document.getElementById('edit_expense_id').value = expense.id;
    document.getElementById('edit_expense_category').value = expense.category;
    document.getElementById('edit_expense_amount').value = expense.amount;
    document.getElementById('edit_expense_date').value = expense.expense_date;
    document.getElementById('edit_expense_description').value = expense.description;
    document.getElementById('edit_expense_note').value = expense.request_note || '';
    openModal('expense-edit-modal');
}
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
