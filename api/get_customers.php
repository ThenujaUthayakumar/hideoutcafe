<?php
/**
 * API - Search Customers
 */
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$query = trim($_GET['q'] ?? '');

$sql = "SELECT c.id, c.name, c.phone, c.dob, c.loyalty_points, c.total_spent,
               NOT EXISTS (
                   SELECT 1 FROM orders o
                   WHERE o.customer_id = c.id AND o.payment_status = 'paid'
               ) AS is_first_order
        FROM customers c";
$params = [];

if ($query !== '') {
    $sql .= " WHERE c.name LIKE :q1 OR c.phone LIKE :q2 OR c.dob LIKE :q3";
    $params[':q1'] = "%$query%";
    $params[':q2'] = "%$query%";
    $params[':q3'] = "%$query%";
}

$sql .= " ORDER BY c.id ASC LIMIT 25";

$customers = db()->fetchAll($sql, $params);

jsonResponse(['success' => true, 'customers' => $customers]);
