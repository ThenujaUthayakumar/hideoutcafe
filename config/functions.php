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
