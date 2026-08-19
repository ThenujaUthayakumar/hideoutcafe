<?php
/**
 * Aura Cafe POS - Store Settings & Receipt Customization
 */
require_once __DIR__ . '/config/functions.php';
requireRole([ROLE_ADMIN]);

$title = 'Store Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settingsToSave = [
        'cafe_name'          => sanitize($_POST['cafe_name'] ?? 'Aura Artisan Cafe'),
        'cafe_tagline'       => sanitize($_POST['cafe_tagline'] ?? ''),
        'cafe_phone'         => sanitize($_POST['cafe_phone'] ?? ''),
        'cafe_email'         => sanitize($_POST['cafe_email'] ?? ''),
        'cafe_address'       => sanitize($_POST['cafe_address'] ?? ''),
        'currency_symbol'    => sanitize($_POST['currency_symbol'] ?? '$'),
        'currency_code'      => sanitize($_POST['currency_code'] ?? 'USD'),
        'tax_rate'           => (float)($_POST['tax_rate'] ?? 5.00),
        'service_charge'     => (float)($_POST['service_charge'] ?? 2.50),
        'invoice_prefix'     => sanitize($_POST['invoice_prefix'] ?? 'AUR-'),
        'receipt_header'     => sanitize($_POST['receipt_header'] ?? ''),
        'receipt_footer'     => sanitize($_POST['receipt_footer'] ?? ''),
        'enable_loyalty'     => isset($_POST['enable_loyalty']) ? '1' : '0',
        'points_per_dollar'  => (int)($_POST['points_per_dollar'] ?? 1)
    ];

    foreach ($settingsToSave as $k => $v) {
        updateSetting($k, (string)$v);
    }

    setFlash('success', 'Store settings and receipt parameters saved successfully.');
    header("Location: " . BASE_URL . "/settings.php");
    exit;
}

$settings = getSettings();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-[#fbf9f6]">
    <div class="max-w-4xl mx-auto space-y-6">

        <div>
            <h2 class="text-2xl font-black tracking-tight text-stone-900">Cafe & POS System Settings</h2>
            <p class="text-xs text-stone-500">Configure business identity, taxation, thermal receipts, and loyalty rules</p>
        </div>

        <form method="POST" action="settings.php" class="space-y-6">
            
            <!-- Cafe Identity -->
            <div class="card-cafe p-6 space-y-4">
                <h3 class="font-extrabold text-stone-900 text-base flex items-center gap-2">
                    <i class="fa-solid fa-store text-amber-800"></i> Cafe Profile & Branding
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Cafe Name *</label>
                        <input type="text" name="cafe_name" value="<?= e($settings['cafe_name'] ?? '') ?>" required class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl font-bold text-stone-900">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Tagline / Subtitle</label>
                        <input type="text" name="cafe_tagline" value="<?= e($settings['cafe_tagline'] ?? '') ?>" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Phone Number</label>
                        <input type="text" name="cafe_phone" value="<?= e($settings['cafe_phone'] ?? '') ?>" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Email Address</label>
                        <input type="email" name="cafe_email" value="<?= e($settings['cafe_email'] ?? '') ?>" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-stone-700 mb-1">Store Physical Address</label>
                        <input type="text" name="cafe_address" value="<?= e($settings['cafe_address'] ?? '') ?>" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl">
                    </div>
                </div>
            </div>

            <!-- Currency & Tax -->
            <div class="card-cafe p-6 space-y-4">
                <h3 class="font-extrabold text-stone-900 text-base flex items-center gap-2">
                    <i class="fa-solid fa-coins text-amber-800"></i> Currency & Financial Rates
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Currency Symbol *</label>
                        <input type="text" name="currency_symbol" value="<?= e($settings['currency_symbol'] ?? '$') ?>" required class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl font-bold text-stone-900 text-center">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Currency ISO Code</label>
                        <input type="text" name="currency_code" value="<?= e($settings['currency_code'] ?? 'USD') ?>" required class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-center uppercase font-bold">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Sales Tax Rate (%)</label>
                        <input type="number" step="0.01" min="0" name="tax_rate" value="<?= e($settings['tax_rate'] ?? '5.00') ?>" required class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl font-bold">
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Dine-in Service Charge (%)</label>
                        <input type="number" step="0.01" min="0" name="service_charge" value="<?= e($settings['service_charge'] ?? '2.50') ?>" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl font-bold">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-stone-700 mb-1">Invoice Prefix</label>
                        <input type="text" name="invoice_prefix" value="<?= e($settings['invoice_prefix'] ?? 'AUR-') ?>" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl font-mono">
                    </div>
                </div>
            </div>

            <!-- Receipt Template Notes -->
            <div class="card-cafe p-6 space-y-4">
                <h3 class="font-extrabold text-stone-900 text-base flex items-center gap-2">
                    <i class="fa-solid fa-receipt text-amber-800"></i> Thermal Receipt Layout Notes
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Receipt Header Text</label>
                        <textarea name="receipt_header" rows="3" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl"><?= e($settings['receipt_header'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Receipt Footer Note (WiFi, Socials)</label>
                        <textarea name="receipt_footer" rows="3" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl"><?= e($settings['receipt_footer'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Loyalty Program Settings -->
            <div class="card-cafe p-6 space-y-4">
                <h3 class="font-extrabold text-stone-900 text-base flex items-center gap-2">
                    <i class="fa-solid fa-award text-amber-800"></i> Customer Loyalty Rewards
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs items-center">
                    <div>
                        <label class="font-bold text-stone-800 flex items-center gap-2">
                            <input type="checkbox" name="enable_loyalty" value="1" <?= ($settings['enable_loyalty'] ?? '1') == '1' ? 'checked' : '' ?> class="w-4 h-4 text-amber-800 rounded">
                            <span>Enable Customer Loyalty Points on POS Checkout</span>
                        </label>
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Points Earned per $1.00 spent</label>
                        <input type="number" name="points_per_dollar" value="<?= e($settings['points_per_dollar'] ?? '1') ?>" min="1" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl font-bold">
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-8 py-3.5 bg-amber-800 hover:bg-amber-700 text-white font-extrabold rounded-2xl text-sm shadow-xl shadow-amber-900/20 transition">
                    <i class="fa-solid fa-floppy-disk mr-2"></i> Save All Settings
                </button>
            </div>

        </form>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
