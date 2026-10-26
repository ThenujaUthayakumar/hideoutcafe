<?php
require_once __DIR__ . '/config/functions.php';
ensureUserUsernameSchema();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $username = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', sanitize($_POST['username'] ?? '')));
        $email = sanitize($_POST['email'] ?? '');
        $adminPasscode = $_POST['admin_passcode'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $target = db()->fetchOne(
            "SELECT id, name FROM users WHERE username = :username AND email = :email LIMIT 1",
            [':username' => $username, ':email' => $email]
        );

        if (!$target || !hash_equals(ADMIN_RESET_PASSCODE, $adminPasscode)) {
            $error = 'The username, email, or administrator passcode is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'The new password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'The new passwords do not match.';
        } else {
            db()->query(
                "UPDATE users SET password = :password WHERE id = :id",
                [':password' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $target['id']]
            );
            $success = 'Password reset successfully. You can now sign in with the new password.';
        }
    }
}

$settings = getSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Password Reset | <?= e($settings['cafe_name'] ?? APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="min-h-screen bg-[#0c0c0e] text-stone-100 flex items-center justify-center p-4">
    <main class="w-full max-w-md bg-stone-900 border border-stone-800 rounded-3xl p-7 shadow-2xl">
        <a href="<?= BASE_URL ?>/login.php" class="text-xs font-bold text-stone-400 hover:text-white"><i class="fa-solid fa-arrow-left mr-1"></i> Back to login</a>
        <h1 class="text-xl font-black text-white mt-6">Forgot Password</h1>
        <p class="text-xs text-stone-400 mt-1 mb-6">Enter the staff username and email exactly as registered. Only an administrator passcode can authorize a reset.</p>

        <?php if ($error): ?>
            <div class="mb-5 p-3.5 bg-red-950/80 border border-red-800 rounded-2xl text-red-300 text-xs"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="mb-5 p-3.5 bg-emerald-950/80 border border-emerald-800 rounded-2xl text-emerald-300 text-xs"><?= e($success) ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1.5">Staff username</label>
                <input type="text" name="username" required pattern="[A-Za-z0-9_]+" value="<?= e($_POST['username'] ?? '') ?>" autocomplete="username" class="w-full px-3.5 py-3 bg-stone-800 border border-stone-700 rounded-xl text-sm text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1.5">Registered email</label>
                <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" class="w-full px-3.5 py-3 bg-stone-800 border border-stone-700 rounded-xl text-sm text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1.5">Administrator passcode</label> <!-- AdminReset@2026 -->
                <input type="password" name="admin_passcode" required autocomplete="off" class="w-full px-3.5 py-3 bg-stone-800 border border-stone-700 rounded-xl text-sm text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1.5">New password</label>
                <input type="password" name="new_password" required minlength="8" autocomplete="new-password" class="w-full px-3.5 py-3 bg-stone-800 border border-stone-700 rounded-xl text-sm text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-300 mb-1.5">Confirm new password</label>
                <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password" class="w-full px-3.5 py-3 bg-stone-800 border border-stone-700 rounded-xl text-sm text-white">
            </div>
            <button type="submit" class="w-full py-3 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-sm"><i class="fa-solid fa-key mr-2"></i> Reset Password</button>
        </form>
    </main>
</body>
</html>
