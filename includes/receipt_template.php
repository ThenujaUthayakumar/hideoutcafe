<?php
/**
 * The Hide Out Cafe - Thermal Receipt Template (80mm / 58mm POS Slip)
 */
if (!isset($settings)) {
    $settings = getSettings();
}
$currency = $settings['currency_symbol'] ?? 'Rs.';
?>
<div class="receipt-container" id="thermal-receipt-area">
    <!-- Header / Brand -->
    <div class="text-center pb-2">
        <div class="font-black text-base uppercase tracking-wider"><?= e($settings['cafe_name'] ?? 'THE HIDE OUT CAFE') ?></div>
        <div class="text-[11px] text-stone-600 font-medium"><?= nl2br(e($settings['receipt_header'] ?? '')) ?></div>
        <div class="text-[11px] text-stone-600"><?= e($settings['cafe_address'] ?? '') ?></div>
        <div class="text-[11px] text-stone-600 font-bold">Tel: <?= e($settings['cafe_phone'] ?? '') ?></div>
    </div>

    <div class="receipt-divider"></div>

    <!-- Invoice Meta -->
    <div class="text-xs space-y-0.5">
        <div class="flex justify-between">
            <span>Invoice:</span>
            <span class="font-bold"><?= e($order['invoice_no']) ?></span>
        </div>
        <div class="flex justify-between">
            <span>Date/Time:</span>
            <span><?= date('d-M-Y H:i', strtotime($order['created_at'])) ?></span>
        </div>
        <div class="flex justify-between">
            <span>Cashier:</span>
            <span><?= e($order['cashier_name'] ?? 'Staff') ?></span>
        </div>
        <div class="flex justify-between">
            <span>Order Type:</span>
            <span class="font-bold uppercase"><?= e(str_replace('_', ' ', $order['order_type'])) ?><?= !empty($order['table_name']) ? ' (' . e($order['table_name']) . ')' : '' ?></span>
        </div>
        <?php if (!empty($order['customer_name']) && $order['customer_name'] !== 'Walk-in Customer'): ?>
        <div class="flex justify-between">
            <span>Customer:</span>
            <span><?= e($order['customer_name']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="receipt-divider"></div>

    <!-- Items Table -->
    <table class="w-full text-xs text-left my-1">
        <thead>
            <tr class="border-b border-stone-800 pb-1">
                <th class="py-1">Item</th>
                <th class="py-1 text-center">Qty</th>
                <th class="py-1 text-right">Price</th>
                <th class="py-1 text-right">Amount</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-200">
            <?php foreach ($order['items'] as $item): ?>
            <tr>
                <td class="py-1.5 align-top">
                    <div class="font-bold leading-tight"><?= e($item['product_name']) ?></div>
                    <?php if (!empty($item['variant_name'])): ?>
                        <div class="text-[10px] text-stone-500"><?= e($item['variant_name']) ?></div>
                    <?php endif; ?>
                    <?php 
                    if (!empty($item['modifiers_json'])) {
                        $mods = json_decode($item['modifiers_json'], true);
                        if (!empty($mods)) {
                            foreach ($mods as $m) {
                                echo '<div class="text-[10px] text-stone-600 pl-1">+ ' . e($m['name']) . ' (' . $currency . ' ' . number_format($m['price'], 2) . ')</div>';
                            }
                        }
                    }
                    if (!empty($item['notes'])) {
                        echo '<div class="text-[10px] italic text-stone-500">Note: ' . e($item['notes']) . '</div>';
                    }
                    ?>
                </td>
                <td class="py-1.5 text-center align-top"><?= (int)$item['quantity'] ?></td>
                <td class="py-1.5 text-right align-top"><?= number_format($item['unit_price'], 2) ?></td>
                <td class="py-1.5 text-right align-top font-bold"><?= number_format($item['subtotal'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="receipt-divider-double"></div>

    <!-- Totals Summary -->
    <div class="text-xs space-y-1">
        <div class="flex justify-between">
            <span>Subtotal:</span>
            <span> <?= number_format($order['subtotal'], 2) ?></span>
        </div>
        <?php if ($order['discount_amount'] > 0): ?>
        <div class="flex justify-between text-stone-700">
            <span>Discount:</span>
            <span><?= number_format($order['discount_amount'], 2) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($order['paid_amount'] > 0): ?>
        <div class="flex justify-between">
            <span><?= e($order['payment_method']) ?></span>
            <span><?= number_format($order['paid_amount'], 2) ?></span>
        </div>
        <?php endif; ?>
    

        <div class="receipt-divider"></div>

        <div class="flex justify-between text-sm font-black pt-0.5">
            <span>Balance:</span>
            <span><?= number_format($order['change_amount'], 2) ?></span>
        </div>

        <div class="receipt-divider"></div>
    </div>

    <!-- Footer Note -->
    <div class="text-center pt-2 space-y-2">
        <div class="text-[11px] text-stone-700 font-medium leading-tight">
            <?= nl2br(e($settings['receipt_footer'] ?? 'Thank you for your visit!')) ?>
        </div>
        <div class="text-[10px] text-stone-400 font-mono tracking-widest pt-1">
            * <?= e($order['invoice_no']) ?> *
        </div>
    </div>
</div>
