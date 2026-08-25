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
    if (!hasRole(ROLE_ADMIN)) {
        jsonResponse(['success' => false, 'message' => 'Only an administrator can open a shift.'], 403);
    }

    if (getOpenCashRegister()) {
        jsonResponse(['success' => false, 'message' => 'A shift is already open.'], 409);
    }

    $openingCash = (float)($_POST['opening_cash'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? 'Shift opened');

    db()->query(
        "INSERT INTO cash_registers (user_id, opening_cash, status, notes) VALUES (:uid, :cash, 'open', :notes)",
        [':uid' => $currentUser['id'], ':cash' => $openingCash, ':notes' => $notes]
    );

    jsonResponse(['success' => true, 'message' => 'Shift opened with initial cash float.']);
}

if ($action === 'claim_shift') {
    if (hasRole(ROLE_ADMIN)) {
        jsonResponse(['success' => false, 'message' => 'Administrators open the shift; staff confirm it.'], 403);
    }

    $openShift = getOpenCashRegister();
    if (!$openShift) {
        jsonResponse(['success' => false, 'message' => 'No open shift is available to confirm.'], 404);
    }

    db()->query(
        "UPDATE cash_registers SET user_id = :uid, notes = CONCAT(COALESCE(notes, ''), ' | Confirmed by: ', :name)
         WHERE id = :id AND status = 'open'",
        [':uid' => $currentUser['id'], ':name' => $currentUser['name'], ':id' => $openShift['id']]
    );

    jsonResponse(['success' => true, 'message' => 'Shift confirmed. You can now process sales.']);
}

if ($action === 'close_shift') {
    if (hasRole(ROLE_ADMIN)) {
        jsonResponse(['success' => false, 'message' => 'Only the confirmed shift user can close the shift.'], 403);
    }

    $activeShift = getActiveCashRegister($currentUser['id']);
    if (!$activeShift) {
        jsonResponse(['success' => false, 'message' => 'No active shift found.'], 404);
    }

    $closingCash = (float)($_POST['closing_cash'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? '');

        $shiftSales = getCashRegisterSalesSummary($activeShift);
        $expenseAmount = getCashRegisterExpenseTotal($activeShift);
        $expectedCash = (float)$activeShift['opening_cash']
            + (float)($shiftSales['total_sales'] ?? 0)
            - (float)($shiftSales['total_discount'] ?? 0);
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
            'expenses' => $expenseAmount,
            'counted'  => $closingCash,
            'difference' => $diff
        ]
    ]);
}
