<?php
/**
 * The Hide Out Cafe - Header & Top Navigation (Black & Red Theme)
 */
require_once __DIR__ . '/../config/functions.php';
requireAuth();

$user = currentUser();

$settings = getSettings();

$currentShift = getOpenCashRegister();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$currency = $settings['currency_symbol'] ?? 'Rs.';

$shiftSales = $currentShift ? getCashRegisterSalesSummary($currentShift) : [];

$shiftExpenses = $currentShift ? getCashRegisterExpenseTotal($currentShift) : 0;

$shiftHeaderAmount = $currentShift
    ? (float)$currentShift['opening_cash']
        + (float)($shiftSales['cash_sales'] ?? 0)
        - (float)$shiftExpenses
    : 0;
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-stone-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? APP_NAME) ?> | <?= e($settings['cafe_name'] ?? APP_NAME) ?></title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/assets/img/hideout.ico">
    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              brand: {
                50: '#fef2f2',
                100: '#fee2e2',
                200: '#fecaca',
                300: '#fca5a5',
                400: '#f87171',
                500: '#ef4444',
                600: '#dc2626',
                700: '#b91c1c',
                800: '#991b1b',
                900: '#7f1d1d',
                950: '#450a0a'
              },
              dark: {
                800: '#27272a',
                850: '#1f1f23',
                900: '#18181b',
                950: '#09090b'
              }
            }
          }
        }
      }
    </script>
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="h-full flex flex-col antialiased text-stone-100 bg-[#0c0c0e]">

    <!-- Top Navbar -->
    <header class="bg-stone-950 border-b border-stone-800 sticky top-0 z-30 shadow-md">
        <div class="px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
            
            <!-- Left: Brand & Mobile Sidebar Toggle -->
            <div class="flex items-center gap-4">
                <button id="sidebar-toggle-btn" class="lg:hidden text-stone-400 hover:text-red-500 p-2 rounded-lg">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <a href="<?= BASE_URL ?>/dashboard.php" class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-red-700 to-red-500 flex items-center justify-center text-white shadow-lg shadow-red-900/40">
                        <i class="fa-solid fa-mug-hot text-lg"></i>
                    </div>
                    <div>
                        <h1 class="font-extrabold text-white text-lg leading-tight tracking-tight"><?= e($settings['cafe_name'] ?? 'The Hide Out Cafe') ?></h1>
                        <p class="text-[10px] font-bold text-red-500 uppercase tracking-widest"><?= e($settings['cafe_tagline'] ?? 'Point of Sale') ?></p>
                    </div>
                </a>
            </div>

            <!-- Middle: Quick Navigation Pills -->
            <nav class="hidden md:flex items-center gap-1.5 bg-stone-900 p-1.5 rounded-2xl border border-stone-800">
                <a href="<?= BASE_URL ?>/pos.php" class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $currentPage === 'pos' ? 'bg-red-600 text-white shadow-md shadow-red-900/30' : 'text-stone-300 hover:text-white hover:bg-stone-800' ?>">
                    <i class="fa-solid fa-cash-register"></i> POS Terminal
                </a>
                <a href="<?= BASE_URL ?>/kds.php" class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $currentPage === 'kds' ? 'bg-red-600 text-white shadow-md shadow-red-900/30' : 'text-stone-300 hover:text-white hover:bg-stone-800' ?>">
                    <i class="fa-solid fa-kitchen-set"></i> Kitchen KDS
                </a>
                <a href="<?= BASE_URL ?>/tables.php" class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $currentPage === 'tables' ? 'bg-red-600 text-white shadow-md shadow-red-900/30' : 'text-stone-300 hover:text-white hover:bg-stone-800' ?>">
                    <i class="fa-solid fa-chair"></i> Tables
                </a>
                <a href="<?= BASE_URL ?>/orders.php" class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $currentPage === 'orders' ? 'bg-red-600 text-white shadow-md shadow-red-900/30' : 'text-stone-300 hover:text-white hover:bg-stone-800' ?>">
                    <i class="fa-solid fa-receipt"></i> Orders
                </a>
            </nav>

            <!-- Right: Shift Status & Profile -->
            <div class="flex items-center gap-3">
                <?php if ($currentShift): ?>
                    <button onclick="openModal('shift-register-modal')" class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-950 text-emerald-300 border border-emerald-800 hover:bg-emerald-900 transition">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Shift Open (<?= e($currency) ?> <?= number_format($shiftHeaderAmount, 0) ?>)</span>
                    </button>
                <?php else: ?>
                    <button onclick="openModal('shift-register-modal')" class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold bg-red-950 text-red-300 border border-red-800 hover:bg-red-900 transition">
                        <i class="fa-solid fa-lock text-red-400"></i>
                        <span>Start Shift Float</span>
                    </button>
                <?php endif; ?>

                <!-- User Profile Dropdown -->
                <div class="relative group">
                    <button class="flex items-center gap-3 p-1.5 pl-2.5 rounded-xl hover:bg-stone-900 transition border border-transparent hover:border-stone-800">
                        <div class="text-right hidden sm:block">
                            <div class="font-bold text-xs text-white leading-tight"><?= e($user['name']) ?></div>
                            <span class="text-[10px] uppercase tracking-wider font-extrabold px-1.5 py-0.5 rounded bg-red-950 text-red-300 border border-red-900"><?= e(strtoupper($user['role'])) ?></span>
                        </div>
                        <div class="w-9 h-9 rounded-xl bg-red-600 text-white flex items-center justify-center font-bold text-sm shadow-md">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <i class="fa-solid fa-chevron-down text-xs text-stone-500"></i>
                    </button>

                    <div class="hidden group-hover:block absolute right-0 mt-1 w-48 bg-stone-900 border border-stone-800 rounded-2xl shadow-2xl py-2 z-50 animate-in fade-in duration-150">
                        <div class="px-4 py-2 border-b border-stone-800">
                            <p class="text-[10px] text-stone-400">Signed in as</p>
                            <p class="text-xs font-bold text-stone-200 truncate"><?= e($user['email']) ?></p>
                        </div>
                        <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
                            <a href="<?= BASE_URL ?>/settings.php" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-stone-300 hover:bg-stone-800 hover:text-red-400">
                                <i class="fa-solid fa-sliders text-stone-500 w-4"></i> Settings
                            </a>
                            <a href="<?= BASE_URL ?>/users.php" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-stone-300 hover:bg-stone-800 hover:text-red-400">
                                <i class="fa-solid fa-user-gear text-stone-500 w-4"></i> Staff Management
                            </a>
                        <?php endif; ?>
                        <div class="border-t border-stone-800 my-1"></div>
                        <a href="<?= BASE_URL ?>/logout.php" class="flex items-center gap-2.5 px-4 py-2 text-xs font-bold text-rose-400 hover:bg-rose-950/50">
                            <i class="fa-solid fa-arrow-right-from-bracket w-4"></i> Sign Out
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </header>

    <div class="flex-1 flex overflow-hidden">
