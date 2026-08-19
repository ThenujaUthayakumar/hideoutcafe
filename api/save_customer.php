<?php
/**
 * API - Quick Add Customer
 */
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$name = sanitize($data['name'] ?? '');
$phone = sanitize($data['phone'] ?? '');
$email = sanitize($data['email'] ?? '');
$address = sanitize($data['address'] ?? '');

if (empty($name) || empty($phone)) {
    jsonResponse(['success' => false, 'message' => 'Name and Phone number are required.'], 400);
}

try {
    // Check if phone already exists
    $existing = db()->fetchOne("SELECT id, name, phone, loyalty_points FROM customers WHERE phone = :phone", [':phone' => $phone]);
    if ($existing) {
        jsonResponse(['success' => true, 'customer' => $existing, 'message' => 'Customer already exists, selected!']);
    }

    db()->query(
        "INSERT INTO customers (name, phone, email, address, loyalty_points, total_spent) 
         VALUES (:name, :phone, :email, :address, 0, 0.00)",
        [
            ':name'    => $name,
            ':phone'   => $phone,
            ':email'   => $email,
            ':address' => $address
        ]
    );

    $newId = (int)db()->lastInsertId();

    jsonResponse([
        'success' => true,
        'message' => 'Customer registered successfully!',
        'customer' => [
            'id'             => $newId,
            'name'           => $name,
            'phone'          => $phone,
            'email'          => $email,
            'loyalty_points' => 0
        ]
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Failed to save customer: ' . $e->getMessage()], 500);
}
