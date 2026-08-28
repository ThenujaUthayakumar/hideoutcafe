<?php
/**
 * The Hide Out Cafe - Core Functions & Security Helpers
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return trim(strip_tags((string)$data));
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(?string $token = null): bool {
    $token = $token ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role'] ?? ROLE_CASHIER,
        'phone' => $_SESSION['user_phone'] ?? ''
    ];
}

function hasRole($roles): bool {
    if (!isLoggedIn()) return false;
    $userRole = $_SESSION['user_role'] ?? '';
    if (is_string($roles)) {
        $roles = [$roles];
    }
    return in_array($userRole, $roles, true);
}

function requireAuth(string $redirect = 'login.php'): void {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'Please log in to continue.';
        header("Location: " . BASE_URL . "/$redirect");
        exit;
    }
}

function requireRole($roles, string $redirect = 'pos.php'): void {
    requireAuth();
    if (!hasRole($roles)) {
        $_SESSION['flash_error'] = 'Access denied. You do not have permission to view this page.';
        header("Location: " . BASE_URL . "/$redirect");
        exit;
    }
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash_' . $type] = $message;
}

function getFlash(string $type): ?string {
    $key = 'flash_' . $type;
    if (!empty($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

function getSettings(?string $key = null) {
    static $settings = null;
    if ($settings === null) {
        try {
            $rows = db()->fetchAll("SELECT setting_key, setting_value FROM settings");
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settings = [
                'cafe_name' => 'The Hide Out Cafe',
                'cafe_tagline' => 'Artisan Coffee & Bistro - Sri Lanka',
                'cafe_phone' => '+94 77 123 4567',
                'cafe_email' => 'hello@thehideoutcafe.lk',
                'cafe_address' => 'No. 45 Beach Road, Colombo 03, Sri Lanka',
                'currency_symbol' => 'Rs.',
                'currency_code' => 'LKR',
                'tax_rate' => '0.00',
                'service_charge' => '0.00',
                'receipt_header' => "THE HIDE OUT CAFE\nSpecialty Coffee & Kitchen\nColombo, Sri Lanka",
                'receipt_footer' => "Thank you for visiting The Hide Out Cafe!\nFollow us on Instagram @TheHideOutCafeLK\nFree Wi-Fi: HideOutGuest / Pass: CoffeeTime",
                'invoice_prefix' => 'HOC-',
                'enable_loyalty' => '1',
                'points_per_dollar' => '1'
            ];
        }
    }

    if ($key !== null) {
        return $settings[$key] ?? null;
    }
    return $settings;
}

function updateSetting(string $key, string $value): bool {
    try {
        db()->query(
            "INSERT INTO settings (setting_key, setting_value) VALUES (:key, :val) 
             ON DUPLICATE KEY UPDATE setting_value = :val2",
            [':key' => $key, ':val' => $value, ':val2' => $value]
        );
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function formatCurrency($amount): string {
    $symbol = getSettings('currency_symbol') ?? 'Rs.';
    return $symbol . ' ' . number_format((float)$amount, 2);
}

function formatDate(?string $date, string $format = 'M d, Y h:i A'): string {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

function ensureProductDiscountSchema(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $columns = db()->fetchAll("SHOW COLUMNS FROM products");
        $existing = array_column($columns, 'Field');
        $definitions = [
            'discount_type' => "ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage'",
            'discount_value' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00",
            'discount_start' => 'DATETIME NULL',
            'discount_end' => 'DATETIME NULL'
        ];
        foreach ($definitions as $column => $definition) {
            if (!in_array($column, $existing, true)) {
                db()->query("ALTER TABLE products ADD COLUMN `$column` $definition");
            }
        }
    } catch (Exception $e) {
        // The schema file remains the source of truth for fresh installations.
    }
}

function getActiveProductDiscount(array $product, ?int $timestamp = null): float {
    $value = max(0, (float)($product['discount_value'] ?? 0));
    if ($value <= 0) return 0.0;

    $now = $timestamp ?? time();
    $start = !empty($product['discount_start']) ? strtotime($product['discount_start']) : null;
    $end = !empty($product['discount_end']) ? strtotime($product['discount_end']) : null;
    if (($start !== null && $now < $start) || ($end !== null && $now > $end)) return 0.0;

    return $value;
}

function ensureCustomerDobSchema(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        if (!db()->fetchOne("SHOW COLUMNS FROM customers LIKE 'dob'")) {
            db()->query("ALTER TABLE customers ADD COLUMN dob DATE NULL AFTER email");
        }
    } catch (Exception $e) {
        // The schema file remains the source of truth for fresh installations.
    }
}

function ensureOrderDiscountSchema(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $columns = db()->fetchAll("SHOW COLUMNS FROM orders");
        $existing = array_column($columns, 'Field');
        foreach (['promotion_discount', 'normal_discount'] as $column) {
            if (!in_array($column, $existing, true)) {
                db()->query("ALTER TABLE orders ADD COLUMN `$column` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_amount");
            }
        }
        $itemColumns = db()->fetchAll("SHOW COLUMNS FROM order_items");
        $itemExisting = array_column($itemColumns, 'Field');
        foreach (['promotion_discount', 'normal_discount'] as $column) {
            if (!in_array($column, $itemExisting, true)) {
                db()->query("ALTER TABLE order_items ADD COLUMN `$column` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER subtotal");
            }
        }
        db()->query(
            "CREATE TABLE IF NOT EXISTS product_discount_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                discount_type ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
                discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                discount_start DATETIME NULL,
                discount_end DATETIME NULL,
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (Exception $e) {
        // The schema files remain the source of truth for fresh installations.
    }
}

function paginationUrl(array $params, int $page): string {
    $params['page'] = $page;
    return '?' . http_build_query(array_filter($params, static function ($value) {
        return $value !== '' && $value !== null && $value !== 'all' && $value !== 0;
    }));
}

function renderPagination(int|string $page, int $total, int $perPage, array $params = [], bool $dark = false): string {
    if (is_string($page)) {
        $page = max(1, (int)($_GET['page'] ?? 1));
    }
    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($totalPages <= 1) return '';

    $page = min(max(1, $page), $totalPages);
    $buttonClass = $dark
        ? 'border-stone-700 bg-stone-800 text-stone-300 hover:bg-stone-700'
        : 'border-stone-200 bg-white text-stone-700 hover:bg-stone-50';
    $activeClass = $dark ? 'border-red-600 bg-red-600 text-white' : 'border-amber-800 bg-amber-800 text-white';
    $disabledClass = 'pointer-events-none opacity-40';
    $html = '<nav class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-xs" aria-label="Pagination">';
    $first = (($page - 1) * $perPage) + 1;
    $last = min($page * $perPage, $total);
    $html .= '<span class="font-semibold ' . ($dark ? 'text-stone-400' : 'text-stone-500') . '">Showing ' . $first . '-' . $last . ' of ' . $total . '</span>';
    $html .= '<div class="flex items-center gap-1">';
    $html .= '<a class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 ' . $buttonClass . ' ' . ($page === 1 ? $disabledClass : '') . '" href="' . e(paginationUrl($params, $page - 1)) . '" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>';

    $start = max(1, min($page - 2, $totalPages - 4));
    $end = min($totalPages, $start + 4);
    for ($number = $start; $number <= $end; $number++) {
        $html .= '<a class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 font-bold ' . ($number === $page ? $activeClass : $buttonClass) . '" href="' . e(paginationUrl($params, $number)) . '">' . $number . '</a>';
    }
    $html .= '<a class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 ' . $buttonClass . ' ' . ($page === $totalPages ? $disabledClass : '') . '" href="' . e(paginationUrl($params, $page + 1)) . '" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>';
    return $html . '</div></nav>';
}

function generateInvoiceNo(): string {
    $prefix = getSettings('invoice_prefix') ?? 'HOC-';
    $datePart = date('Ymd');
    try {
        $row = db()->fetchOne("SELECT COUNT(id) as total FROM orders WHERE DATE(created_at) = CURDATE()");
        $sequence = ($row['total'] ?? 0) + 1;
        return $prefix . $datePart . '-' . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        return $prefix . $datePart . '-' . rand(1000, 9999);
    }
}

function getActiveCashRegister(int $userId): ?array {
    try {
        return db()->fetchOne(
            "SELECT * FROM cash_registers WHERE user_id = :uid AND status = 'open' ORDER BY id DESC LIMIT 1",
            [':uid' => $userId]
        );
    } catch (Exception $e) {
        return null;
    }
}

function getOpenCashRegister(): ?array {
    try {
        return db()->fetchOne(
            "SELECT cr.*, u.name as cashier_name, u.role as cashier_role
             FROM cash_registers cr
             JOIN users u ON cr.user_id = u.id
             WHERE cr.status = 'open'
             ORDER BY cr.id DESC LIMIT 1"
        );
    } catch (Exception $e) {
        return null;
    }
}

    function getCashRegisterExpenseTotal(array $shift): float {
        try {
            $result = db()->fetchOne(
                "SELECT COALESCE(SUM(amount), 0) as total
                 FROM expenses
                 WHERE user_id = :user_id
                 AND expense_date BETWEEN DATE(:opening_time) AND DATE(COALESCE(:closing_time, NOW()))",
                [
                    ':user_id' => $shift['user_id'],
                    ':opening_time' => $shift['opening_time'],
                    ':closing_time' => $shift['closing_time'] ?? null
                ]
            );
            return (float)($result['total'] ?? 0);
        } catch (Exception $e) {
            return 0.0;
        }
    }

function jsonResponse(array $data, int $statusCode = 200): void {
    if (ob_get_length()) ob_clean();
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getCashRegisterSalesSummary(array $shift): array {
    try {
        return db()->fetchOne(
            "SELECT
                COALESCE(SUM(subtotal), 0) as total_sales,
                COALESCE(SUM(discount_amount), 0) as total_discount,
                COALESCE(SUM(subtotal - discount_amount), 0) as net_sales,
                COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN grand_total ELSE 0 END), 0) as cash_sales,
                COALESCE(SUM(CASE WHEN payment_method = 'card' THEN grand_total ELSE 0 END), 0) as card_sales
             FROM orders
             WHERE user_id = :user_id
             AND created_at >= :opening_time
             AND created_at <= COALESCE(:closing_time, NOW())
             AND payment_status = 'paid'
             AND order_status != 'cancelled'",
            [
                ':user_id' => $shift['user_id'],
                ':opening_time' => $shift['opening_time'],
                ':closing_time' => $shift['closing_time'] ?? null
            ]
        ) ?: [];
    } catch (Exception $e) {
        return [];
    }
}
