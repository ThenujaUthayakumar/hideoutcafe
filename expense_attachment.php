<?php
/**
 * Admin-only expense attachment viewer.
 */
require_once __DIR__ . '/config/functions.php';
requireRole([ROLE_ADMIN]);

$expenseId = (int)($_GET['id'] ?? 0);
$expense = db()->fetchOne(
    "SELECT attachment_name, attachment_mime FROM expenses WHERE id = :id",
    [':id' => $expenseId]
);

if (!$expense || empty($expense['attachment_name']) || !in_array($expense['attachment_mime'], ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(404);
    exit('Attachment not found.');
}

$filePath = BASE_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($expense['attachment_name']);
if (!is_file($filePath)) {
    http_response_code(404);
    exit('Attachment file not found.');
}

header('Content-Type: ' . $expense['attachment_mime']);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="expense-' . $expenseId . '"');
readfile($filePath);
