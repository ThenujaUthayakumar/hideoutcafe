<?php
/**
 * API - Cash Drawer Shift Management & Petty Cash
 */
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$currentUser = currentUser();
$action = $_POST['action'] ?? '';

if ($action === 'open_shift') {
    $openingCash = (float)($_POST['opening_cash'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? 'Shift opened');

    // Close any previous open shift for this user
    db()->query("UPDATE cash_registers SET status = 'closed', closing_time = NOW() WHERE user_id = :uid AND status = 'open'", [':uid' => $currentUser['id']]);

    db()->query(
        "INSERT INTO cash_registers (user_id, opening_cash, status, notes) VALUES (:uid, :cash, 'open', :notes)",
        [':uid' => $currentUser['id'], ':cash' => $openingCash, ':notes' => $notes]
    );

    jsonResponse(['success' => true, 'message' => 'Shift opened with initial cash float.']);
}

if ($action === 'close_shift') {
    $activeShift = getActiveCashRegister($currentUser['id']);
    if (!$activeShift) {
        jsonResponse(['success' => false, 'message' => 'No active shift found.'], 404);
    }

    $closingCash = (float)($_POST['closing_cash'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? '');

    $expectedCash = $activeShift['opening_cash'] + $activeShift['total_cash_sales'];
    $diff = $closingCash - $expectedCash;

    db()->query(
        "UPDATE cash_registers SET 
            closing_cash = :close_cash, 
            difference_amount = :diff, 
            closing_time = NOW(), 
            status = 'closed', 
            notes = CONCAT(COALESCE(notes, ''), ' | Closed: ', :notes) 
         WHERE id = :id",
        [
            ':close_cash' => $closingCash,
            ':diff'       => $diff,
            ':notes'      => $notes,
            ':id'         => $activeShift['id']
        ]
    );

    jsonResponse([
        'success' => true, 
        'message' => 'Shift successfully closed!', 
        'summary' => [
            'expected' => $expectedCash,
            'counted'  => $closingCash,
            'difference' => $diff
        ]
    ]);
}
