<?php
/**
 * Navigation Sidebar - Black & Red Theme
 */
$page = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside id="app-sidebar" class="w-64 bg-stone-950 border-r border-stone-800 flex-shrink-0 flex flex-col justify-between hidden lg:flex transition-all duration-200 z-20">
    <div class="p-4 space-y-6 overflow-y-auto">

        <!-- POS & Operations -->
        <div>
            <p class="px-3 text-[10px] font-black tracking-widest text-stone-500 uppercase mb-2">POS & Operations</p>
            <div class="space-y-1">
                <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
                <a href="<?= BASE_URL ?>/dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition <?= $page === 'dashboard' ? 'bg-red-600 text-white shadow-lg shadow-red-900/30' : 'text-stone-400 hover:bg-stone-900 hover:text-white' ?>">
                    <i class="fa-solid fa-chart-pie text-sm w-5 <?= $page === 'dashboard' ? 'text-white' : 'text-stone-500' ?>"></i>
                    <span>Dashboard</span>
                </a>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>/pos.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition <?= $page === 'pos' ? 'bg-red-600 text-white shadow-lg shadow-red-900/30' : 'text-stone-400 hover:bg-stone-900 hover:text-white' ?>">
                    <i class="fa-solid fa-cash-register text-sm w-5 <?= $page === 'pos' ? 'text-white' : 'text-stone-500' ?>"></i>
                    <span>POS Terminal</span>
                </a>

                <!-- <a href="<?= BASE_URL ?>/kds.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition <?= $page === 'kds' ? 'bg-red-600 text-white shadow-lg shadow-red-900/30' : 'text-stone-400 hover:bg-stone-900 hover:text-white' ?>">
                    <i class="fa-solid fa-kitchen-set text-sm w-5 <?= $page === 'kds' ? 'text-white' : 'text-stone-500' ?>"></i>
                    <span>Kitchen KDS</span>
                </a> -->

                <a href="<?= BASE_URL ?>/tables.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition <?= $page === 'tables' ? 'bg-red-600 text-white shadow-lg shadow-red-900/30' : 'text-stone-400 hover:bg-stone-900 hover:text-white' ?>">
                    <i class="fa-solid fa-chair text-sm w-5 <?= $page === 'tables' ? 'text-white' : 'text-stone-500' ?>"></i>
                    <span>Floor & Tables</span>
                </a>

                <a href="<?= BASE_URL ?>/orders.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition <?= $page === 'orders' || $page === 'order_view' ? 'bg-red-600 text-white shadow-lg shadow-red-900/30' : 'text-stone-400 hover:bg-stone-900 hover:text-white' ?>">
                    <i class="fa-solid fa-receipt text-sm w-5 <?= $page === 'orders' || $page === 'order_view' ? 'text-white' : 'text-stone-500' ?>"></i>
                    <span>Sales & Orders</span>
                </a>
            </div>
        </div>

        <!-- Menu & Catalog -->
        <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
        <div>
            <p class="px-3 text-[10px] font-black tracking-widest text-stone-500 uppercase mb-2">Menu & Catalog</p>
            <div class="space-y-1">
                <a href="<?= BASE_URL ?>/products.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition <?= $page === 'products' ? 'bg-red-600 text-white shadow-lg shadow-red-900/30' : 'text-stone-400 hover:bg-stone-900 hover:text-white' ?>">
                    <i class="fa-solid fa-cubes text-sm w-5 <?= $page === 'products' ? 'text-white' : 'text-stone-500' ?>"></i>
                    <span>Products / Menu</span>
                </a>

                <a href="<?= BASE_URL ?>/categories.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition <?= $page === 'categories' ? 'bg-red-600 text-white shadow-lg shadow-red-900/30' : 'text-stone-400 hover:bg-stone-900 hover:text-white' ?>">
                    <i class="fa-solid fa-tags text-sm w-5 <?= $page === 'categories' ? 'text-white' : 'text-stone-500' ?>"></i>
                    <span>Categories</span>
                </a>
<!-- 
                <a href="<?= BASE_URL ?>/modifiers.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition <?= $page === 'modifiers' ? 'bg-red-600 text-white shadow-lg shadow-red-900/30' : 'text-stone-400 hover:bg-stone-900 hover:text-white' ?>">
                    <i class="fa-solid fa-wand-magic-sparkles text-sm w-5 <?= $page === 'modifiers' ? 'text-white' : 'text-stone-500' ?>"></i>
                    <span>Modifiers & Add-ons</span>
                </a> -->
            </div>
        </div>
        <?php endif; ?>

        <!-- Finance & CRM -->
        <div>
            <p class="px-3 text-[10px] font-black tracking-widest text-stone-500 uppercase mb-2">Finance & CRM</p>
            <div class="space-y-1">
                <a href="<?= BASE_URL ?>/customers.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition <?= $page === 'customers' ? 'bg-red-600 text-white shadow-lg shadow-red-900/30' : 'text-stone-400 hover:bg-stone-900 hover:text-white' ?>">
                    <i class="fa-solid fa-users text-sm w-5 <?= $page === 'customers' ? 'text-white' : 'text-stone-500' ?>"></i>
                    <span>Customers & Loyalty</span>
                </a>

                <a href="<?= BASE_URL ?>/cash_register.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition <?= $page === 'cash_register' ? 'bg-red-600 text-white shadow-lg shadow-red-900/30' : 'text-stone-400 hover:bg-stone-900 hover:text-white' ?>">
                    <i class="fa-solid fa-vault text-sm w-5 <?= $page === 'cash_register' ? 'text-white' : 'text-stone-500' ?>"></i>
                    <span>Shift Cash Register</span>
                </a>

                <a href="<?= BASE_URL ?>/expenses.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition <?= $page === 'expenses' ? 'bg-red-600 text-white shadow-lg shadow-red-900/30' : 'text-stone-400 hover:bg-stone-900 hover:text-white' ?>">
                    <i class="fa-solid fa-money-bill-transfer text-sm w-5 <?= $page === 'expenses' ? 'text-white' : 'text-stone-500' ?>"></i>
                    <span>Expenses</span>
                </a>

                <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
                <a href="<?= BASE_URL ?>/reports.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition <?= $page === 'reports' ? 'bg-red-600 text-white shadow-lg shadow-red-900/30' : 'text-stone-400 hover:bg-stone-900 hover:text-white' ?>">
                    <i class="fa-solid fa-chart-line text-sm w-5 <?= $page === 'reports' ? 'text-white' : 'text-stone-500' ?>"></i>
                    <span>Reports & Analytics</span>
                </a>
                <a href="<?= BASE_URL ?>/users_report.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition <?= $page === 'users_report' ? 'bg-red-600 text-white shadow-lg shadow-red-900/30' : 'text-stone-400 hover:bg-stone-900 hover:text-white' ?>">
                    <i class="fa-solid fa-chart-bar text-sm w-5 <?= $page === 'users_report' ? 'text-white' : 'text-stone-500' ?>"></i>
                    <span>Users Report</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Administration -->
        <?php if (hasRole([ROLE_ADMIN])): ?>
        <div>
            <p class="px-3 text-[10px] font-black tracking-widest text-stone-500 uppercase mb-2">Administration</p>
            <div class="space-y-1">
                <a href="<?= BASE_URL ?>/users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition <?= $page === 'users' ? 'bg-red-600 text-white shadow-lg shadow-red-900/30' : 'text-stone-400 hover:bg-stone-900 hover:text-white' ?>">
                    <i class="fa-solid fa-user-shield text-sm w-5 <?= $page === 'users' ? 'text-white' : 'text-stone-500' ?>"></i>
                    <span>Staff & Roles</span>
                </a>

                <a href="<?= BASE_URL ?>/settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition <?= $page === 'settings' ? 'bg-red-600 text-white shadow-lg shadow-red-900/30' : 'text-stone-400 hover:bg-stone-900 hover:text-white' ?>">
                    <i class="fa-solid fa-gear text-sm w-5 <?= $page === 'settings' ? 'text-white' : 'text-stone-500' ?>"></i>
                    <span>Store Settings</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Quick Footer Info -->
    <div class="p-4 border-t border-stone-800 bg-stone-900/50">
        <div class="flex items-center justify-between text-xs text-stone-500 font-medium">
            <span><?= APP_NAME ?></span>
            <span class="font-bold text-red-500">v<?= APP_VERSION ?></span>
        </div>
    </div>
</aside>
