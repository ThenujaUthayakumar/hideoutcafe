<?php
/**
 * The Hide Out Cafe - User Authentication (Black & Red Theme)
 */
require_once __DIR__ . '/config/functions.php';

if (isLoggedIn()) {
    $user = currentUser();
    if ($user['role'] === ROLE_CASHIER) {
        header("Location: " . BASE_URL . "/pos.php");
    } else {
        header("Location: " . BASE_URL . "/dashboard.php");
    }
    exit;
}

$error = '';
$settings = getSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            try {
                $user = db()->fetchOne("SELECT * FROM users WHERE email = :email LIMIT 1", [':email' => $email]);
                
                if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
                    $_SESSION['user_id']    = $user['id'];
                    $_SESSION['user_name']  = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role']  = $user['role'];
                    $_SESSION['user_phone'] = $user['phone'];

                    setFlash('success', "Welcome back to The Hide Out Cafe, {$user['name']}!");

                    if ($user['role'] === ROLE_CASHIER) {
                        header("Location: " . BASE_URL . "/pos.php");
                    } else {
                        header("Location: " . BASE_URL . "/dashboard.php");
                    }
                    exit;
                } else {
                    $error = 'Invalid email address or password.';
                }
            } catch (Exception $e) {
                $error = 'Authentication error. Please verify database connection.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-black">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | <?= e($settings['cafe_name'] ?? 'The Hide Out Cafe') ?></title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/assets/img/hideout.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="h-full flex items-center justify-center p-4 antialiased bg-[#09090b] text-stone-100 relative overflow-hidden">

    <!-- Background Glow -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-red-600/15 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-red-800/20 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
        
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-red-700 to-red-500 shadow-xl shadow-red-900/50 text-white mb-4">
                    <img src="/assets/img/hideout.ico" alt="Cafe Logo" class="w-full h-full object-cover">
            </div>
            <h2 class="text-3xl font-black tracking-tight text-white"><?= e($settings['cafe_name'] ?? 'The Hide Out Cafe') ?></h2>
            <p class="text-xs font-bold text-red-500 uppercase tracking-widest mt-1.5"><?= e($settings['cafe_tagline'] ?? 'Specialty Coffee & Bistro - Sri Lanka') ?></p>
        </div>

        <!-- Login Card -->
        <div class="bg-stone-900/90 backdrop-blur-md border border-stone-800 rounded-3xl p-7 shadow-2xl">
            
            <h3 class="text-lg font-bold text-white mb-1">Staff Terminal Login</h3>
            <p class="text-xs text-stone-400 mb-6">Select demo staff account or enter credentials</p>

            <?php if (!empty($error)): ?>
                <div class="mb-5 p-3.5 bg-red-950/80 border border-red-800 rounded-2xl flex items-center gap-3 text-red-300 text-xs">
                    <i class="fa-solid fa-circle-exclamation text-base"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-4">
                <?= csrf_field() ?>

                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1.5">Email Address</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-stone-500 pointer-events-none">
                            <i class="fa-solid fa-envelope text-sm"></i>
                        </span>
                        <input type="email" name="email" id="login_email" required value="admin@cafepos.com" 
                               class="w-full pl-10 pr-4 py-3 bg-stone-800/80 border border-stone-700 rounded-xl text-white text-sm focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition placeholder-stone-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1.5">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-stone-500 pointer-events-none">
                            <i class="fa-solid fa-lock text-sm"></i>
                        </span>
                        <input type="password" name="password" id="login_password" required value="admin123" 
                               class="w-full pl-10 pr-4 py-3 bg-stone-800/80 border border-stone-700 rounded-xl text-white text-sm focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition placeholder-stone-500">
                    </div>
                </div>

                <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-500 hover:to-red-600 text-white font-bold rounded-xl text-sm shadow-lg shadow-red-900/40 transition duration-150 transform active:scale-98 mt-2">
                    <i class="fa-solid fa-right-to-bracket mr-2"></i> Access Terminal
                </button>
            </form>

            <!-- Quick Demo Switcher -->
            <div class="mt-6 pt-5 border-t border-stone-800">
                <p class="text-[10px] font-black text-stone-400 uppercase tracking-widest mb-2.5">1-Click Demo Login:</p>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" onclick="fillCreds('admin@cafepos.com', 'admin123')" class="py-2 px-2 bg-stone-800/80 hover:bg-red-950/60 hover:border-red-600 border border-stone-700 rounded-xl text-center transition group">
                        <div class="text-[11px] font-bold text-stone-200 group-hover:text-red-400">Admin</div>
                        <div class="text-[10px] text-stone-500">Full Access</div>
                    </button>
                    <button type="button" onclick="fillCreds('manager@cafepos.com', 'manager123')" class="py-2 px-2 bg-stone-800/80 hover:bg-red-950/60 hover:border-red-600 border border-stone-700 rounded-xl text-center transition group">
                        <div class="text-[11px] font-bold text-stone-200 group-hover:text-red-400">Manager</div>
                        <div class="text-[10px] text-stone-500">Inventory</div>
                    </button>
                    <button type="button" onclick="fillCreds('cashier@cafepos.com', 'cashier123')" class="py-2 px-2 bg-stone-800/80 hover:bg-red-950/60 hover:border-red-600 border border-stone-700 rounded-xl text-center transition group">
                        <div class="text-[11px] font-bold text-stone-200 group-hover:text-red-400">Cashier</div>
                        <div class="text-[10px] text-stone-500">POS Sales</div>
                    </button>
                </div>
            </div>

        </div>

        <div class="text-center mt-6 text-xs text-stone-500">
            <?= APP_NAME ?> &copy; <?= date('Y') ?> &bull; IT Intelligence
        </div>
    </div>

    <script>
      function fillCreds(email, pass) {
        document.getElementById('login_email').value = email;
        document.getElementById('login_password').value = pass;
      }
    </script>
</body>
</html>
