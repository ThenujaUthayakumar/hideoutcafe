<?php
/**
 * 1-Click Database Auto-Installer / Reset Script
 */
require_once __DIR__ . '/config/config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? DB_HOST);
    $dbPort = trim($_POST['db_port'] ?? DB_PORT);
    $dbUser = trim($_POST['db_user'] ?? DB_USER);
    $dbPass = $_POST['db_pass'] ?? DB_PASS;
    $dbName = trim($_POST['db_name'] ?? DB_NAME);

    try {
        // Connect to MySQL server
        $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $sqlFile = __DIR__ . '/database/database.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("database/database.sql not found!");
        }

        $sqlContent = file_get_contents($sqlFile);
        $pdo->exec($sqlContent);

        $message = "Database <strong>$dbName</strong> has been successfully imported and configured!";
    } catch (Exception $e) {
        $error = "Import Failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-stone-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup | <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="h-full flex items-center justify-center p-4 antialiased text-stone-100 bg-stone-950">

    <div class="w-full max-w-lg bg-stone-900 border border-stone-800 rounded-3xl p-7 shadow-2xl">
        <div class="flex items-center gap-3 pb-4 border-b border-stone-800 mb-6">
            <div class="w-12 h-12 rounded-2xl bg-amber-800 text-white flex items-center justify-center text-xl shadow-lg">
                <i class="fa-solid fa-database"></i>
            </div>
            <div>
                <h2 class="text-xl font-black text-white">Database Auto-Installer</h2>
                <p class="text-xs text-stone-400">1-Click setup & schema importer</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="p-4 bg-emerald-950/70 border border-emerald-700 rounded-2xl text-emerald-300 text-xs mb-6 space-y-3">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-base text-emerald-400"></i>
                    <span><?= $message ?></span>
                </div>
                <a href="login.php" class="inline-block w-full text-center py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition">
                    &rarr; Go to Login Screen
                </a>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="p-4 bg-rose-950/70 border border-rose-800 rounded-2xl text-rose-300 text-xs mb-6 flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-base"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="install.php" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Host</label>
                    <input type="text" name="db_host" value="<?= e(DB_HOST) ?>" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Port</label>
                    <input type="text" name="db_port" value="<?= e(DB_PORT) ?>" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1">Database Name</label>
                <input type="text" name="db_name" value="<?= e(DB_NAME) ?>" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Username</label>
                    <input type="text" name="db_user" value="<?= e(DB_USER) ?>" required class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-300 mb-1">Password</label>
                    <input type="password" name="db_pass" value="<?= e(DB_PASS) ?>" placeholder="(empty for root)" class="w-full px-3.5 py-2 bg-stone-800 border border-stone-700 rounded-xl text-xs text-white">
                </div>
            </div>

            <div class="pt-3">
                <button type="submit" class="w-full py-3 bg-amber-800 hover:bg-amber-700 text-white font-bold rounded-xl text-xs shadow-lg transition">
                    <i class="fa-solid fa-play mr-1.5"></i> Run 1-Click Database Import
                </button>
            </div>
        </form>

        <div class="text-center mt-6 pt-4 border-t border-stone-800 text-xs text-stone-500">
            <a href="login.php" class="text-amber-500 hover:underline">&larr; Back to Login</a>
        </div>
    </div>

</body>
</html>
