<?php
/**
 * Root Router
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

header("Location: " . BASE_URL . "/login.php");
exit;
