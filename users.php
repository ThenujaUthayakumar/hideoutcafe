<?php
/**
 * Aura Cafe POS - Staff Management & Role-Based Access Control (RBAC)
 */
require_once __DIR__ . '/config/functions.php';
requireRole([ROLE_ADMIN]);

$title = 'Staff & Users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_user') {
        $userId = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $role = sanitize($_POST['role'] ?? ROLE_CASHIER);
        $phone = sanitize($_POST['phone'] ?? '');
        $status = sanitize($_POST['status'] ?? 'active');
        $password = $_POST['password'] ?? '';

        if ($userId) {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                db()->query(
                    "UPDATE users SET name = :name, email = :email, role = :role, phone = :phone, status = :st, password = :pass WHERE id = :id",
                    [':name' => $name, ':email' => $email, ':role' => $role, ':phone' => $phone, ':st' => $status, ':pass' => $hash, ':id' => $userId]
                );
            } else {
                db()->query(
                    "UPDATE users SET name = :name, email = :email, role = :role, phone = :phone, status = :st WHERE id = :id",
                    [':name' => $name, ':email' => $email, ':role' => $role, ':phone' => $phone, ':st' => $status, ':id' => $userId]
                );
            }
            setFlash('success', "Staff member '{$name}' updated.");
        } else {
            if (empty($password)) $password = 'password123';
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db()->query(
                "INSERT INTO users (name, email, password, role, phone, status) VALUES (:name, :email, :pass, :role, :phone, :st)",
                [':name' => $name, ':email' => $email, ':pass' => $hash, ':role' => $role, ':phone' => $phone, ':st' => $status]
            );
            setFlash('success', "New staff member '{$name}' registered.");
        }
        header("Location: " . BASE_URL . "/users.php");
        exit;
    }

    if ($action === 'delete_user') {
        $userId = (int)$_POST['user_id'];
        $curr = currentUser();
        if ($userId !== $curr['id'] && $userId > 1) {
            db()->query("DELETE FROM users WHERE id = :id", [':id' => $userId]);
            setFlash('success', "User account removed.");
        } else {
            setFlash('error', "Cannot delete root admin or your own current account.");
        }
        header("Location: " . BASE_URL . "/users.php");
        exit;
    }
}

$users = db()->fetchAll("SELECT * FROM users ORDER BY role ASC, name ASC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#0c0c0e]">
    <div class="max-w-6xl mx-auto space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-white">Staff & Role Permissions</h2>
                <p class="text-xs text-stone-400">Manage cashier, barista, manager, and administrator staff accounts</p>
            </div>
            <button onclick="openStaffModal()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-2xl text-xs shadow-md transition">
                <i class="fa-solid fa-user-plus"></i> Add Staff Member
            </button>
        </div>

        <div class="card-cafe-dark overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-stone-900 text-stone-400 font-bold uppercase text-[10px] tracking-wider border-b border-stone-700">
                        <tr>
                            <th class="p-3.5">Staff Name</th>
                            <th class="p-3.5">Email Address</th>
                            <th class="p-3.5">Assigned Role</th>
                            <th class="p-3.5">Phone Number</th>
                            <th class="p-3.5">Account Status</th>
                            <th class="p-3.5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800">
                        <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-stone-800 transition">
                            <td class="p-3.5 font-bold text-white flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-red-600 text-white flex items-center justify-center font-bold text-xs">
                                    <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                </div>
                                <span><?= e($u['name']) ?></span>
                            </td>
                            <td class="p-3.5 font-medium text-stone-300"><?= e($u['email']) ?></td>
                            <td class="p-3.5">
                                <?php
                                $roleBadge = 'bg-stone-800 text-stone-300';
                                if ($u['role'] === 'admin') $roleBadge = 'bg-rose-950 text-rose-400 font-extrabold';
                                if ($u['role'] === 'manager') $roleBadge = 'bg-amber-950 text-amber-400 font-extrabold';
                                if ($u['role'] === 'cashier') $roleBadge = 'bg-sky-950 text-sky-400 font-bold';
                                ?>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] uppercase <?= $roleBadge ?>"><?= e($u['role']) ?></span>
                            </td>
                            <td class="p-3.5 text-stone-400"><?= e($u['phone'] ?: '-') ?></td>
                            <td class="p-3.5">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $u['status'] === 'active' ? 'bg-emerald-950 text-emerald-400' : 'bg-stone-800 text-stone-500' ?>">
                                    <?= e($u['status']) ?>
                                </span>
                            </td>
                            <td class="p-3.5 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button onclick="editStaff(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)" class="p-2 hover:bg-stone-700 text-stone-300 rounded-xl transition" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <?php if ($u['id'] > 1 && $u['id'] !== $user['id']): ?>
                                        <form method="POST" action="users.php" onsubmit="return confirm('Delete staff account <?= addslashes($u['name']) ?>?');" class="inline">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="p-2 hover:bg-rose-950 text-rose-500 rounded-xl transition" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
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

<!-- Add/Edit Staff Modal -->
<div id="staff-form-modal" class="modal-overlay fixed inset-0 bg-stone-900/60 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-stone-200">
        <div class="flex items-center justify-between pb-3 border-b border-stone-100">
            <h3 id="staff-modal-title" class="font-extrabold text-stone-900 text-base">Add Staff Member</h3>
            <button onclick="closeModal('staff-form-modal')" class="text-stone-400 hover:text-stone-600 p-1"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="users.php" class="py-4 space-y-3">
            <input type="hidden" name="action" value="save_user">
            <input type="hidden" name="user_id" id="staff_form_id">

            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Full Name *</label>
                <input type="text" name="name" id="staff_form_name" required class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Email Address *</label>
                <input type="email" name="email" id="staff_form_email" required class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-stone-700 mb-1">Role *</label>
                    <select name="role" id="staff_form_role" required class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs font-semibold">
                        <option value="cashier">Cashier / Barista</option>
                        <option value="manager">Store Manager</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-700 mb-1">Status</label>
                    <select name="status" id="staff_form_status" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs font-semibold">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Phone Number</label>
                <input type="text" name="phone" id="staff_form_phone" class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-700 mb-1">Password <span id="staff-pwd-hint" class="font-normal text-stone-400">(leave blank to keep current)</span></label>
                <input type="password" name="password" id="staff_form_password" class="w-full px-3.5 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs">
            </div>

            <div class="pt-2 flex gap-2">
                <button type="button" onclick="closeModal('staff-form-modal')" class="flex-1 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl text-xs">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 bg-amber-800 hover:bg-amber-700 text-white font-bold rounded-xl text-xs shadow-md">Save Staff</button>
            </div>
        </form>
    </div>
</div>

<script>
function openStaffModal() {
  document.getElementById('staff-modal-title').textContent = 'Add Staff Member';
  document.getElementById('staff_form_id').value = '';
  document.getElementById('staff_form_name').value = '';
  document.getElementById('staff_form_email').value = '';
  document.getElementById('staff_form_role').value = 'cashier';
  document.getElementById('staff_form_phone').value = '';
  document.getElementById('staff_form_password').value = 'cashier123';
  document.getElementById('staff-pwd-hint').textContent = '(default: cashier123)';
  openModal('staff-form-modal');
}
function editStaff(u) {
  document.getElementById('staff-modal-title').textContent = 'Edit Staff: ' + u.name;
  document.getElementById('staff_form_id').value = u.id;
  document.getElementById('staff_form_name').value = u.name;
  document.getElementById('staff_form_email').value = u.email;
  document.getElementById('staff_form_role').value = u.role;
  document.getElementById('staff_form_status').value = u.status;
  document.getElementById('staff_form_phone').value = u.phone || '';
  document.getElementById('staff_form_password').value = '';
  document.getElementById('staff-pwd-hint').textContent = '(leave blank to keep current)';
  openModal('staff-form-modal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
