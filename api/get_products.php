<?php
/**
 * API - Fetch Products by Category or Search
 */
require_once __DIR__ . '/../config/functions.php';
ensureProductDiscountSchema();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$categoryId = $_GET['category_id'] ?? 'all';
$query = trim($_GET['q'] ?? '');

$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 'active'";
$params = [];

if ($categoryId !== 'all' && is_numeric($categoryId)) {
    $sql .= " AND p.category_id = :cat_id";
    $params[':cat_id'] = $categoryId;
}

if (!empty($query)) {
    $sql .= " AND (p.name LIKE :q1 OR p.code LIKE :q2 OR p.description LIKE :q3)";
    $params[':q1'] = "%$query%";
    $params[':q2'] = "%$query%";
    $params[':q3'] = "%$query%";
}

$sql .= " ORDER BY c.sort_order ASC, p.name ASC";

$products = db()->fetchAll($sql, $params);

// Fetch Variants for each product
$productIds = array_column($products, 'id');
$variantsByProduct = [];

if (!empty($productIds)) {
    $inClause = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = pdo()->prepare("SELECT * FROM product_variants WHERE product_id IN ($inClause) ORDER BY extra_price ASC");
    $stmt->execute($productIds);
    $variants = $stmt->fetchAll();
    
    foreach ($variants as $v) {
        $variantsByProduct[$v['product_id']][] = $v;
    }
}

foreach ($products as &$p) {
    $p['variants'] = $variantsByProduct[$p['id']] ?? [];
}

jsonResponse(['success' => true, 'products' => $products]);
