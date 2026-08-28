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

$sql = "SELECT id, name, phone, dob, loyalty_points, total_spent FROM customers";
$params = [];

if (!empty($query)) {
    $sql .= " WHERE name LIKE :q1 OR phone LIKE :q2 OR dob LIKE :q3";
    $params[':q1'] = "%$query%";
    $params[':q2'] = "%$query%";
    $params[':q3'] = "%$query%";
}

$sql .= " ORDER BY id ASC LIMIT 25";

$customers = db()->fetchAll($sql, $params);

jsonResponse(['success' => true, 'customers' => $customers]);
