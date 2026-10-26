<?php
/**
 * The Hide Out Cafe - Thermal Receipt Template (80mm / 58mm POS Slip)
 */
if (!isset($settings)) {
    $settings = getSettings();
}
$currency = $settings['currency_symbol'] ?? 'Rs.';
$promotionDiscount = (float)($order['promotion_discount'] ?? 0);
$normalDiscount = (float)($order['normal_discount'] ?? max(0, (float)($order['discount_amount'] ?? 0) - $promotionDiscount));
?>
<div class="receipt-container" id="thermal-receipt-area">
    <!-- Header / Brand -->
    <div class="receipt-header pb-1">
        <div class="receipt-brand"><?= e($settings['cafe_name'] ?? 'THE HIDE OUT CAFE') ?></div>
        <div class="receipt-contact">
            <?= e($settings['cafe_address'] ?? '') ?>
            <?php if (!empty($settings['cafe_address']) && !empty($settings['cafe_phone'])): ?> · <?php endif; ?><br>
            <?php if (!empty($settings['cafe_phone'])): ?>Tel: <?= e($settings['cafe_phone']) ?><?php endif; ?>
        </div>
    </div>

    <div class="receipt-divider"></div>

    <!-- Invoice Meta -->
    <div class="receipt-meta space-y-0.5">
        <div class="receipt-meta-row">
            <span class="receipt-meta-label">Invoice:</span>
            <span class="receipt-meta-value"><?= e($order['invoice_no']) ?></span>
        </div>
        <div class="receipt-meta-row">
            <span class="receipt-meta-label">Cashier:</span>
            <span class="receipt-meta-value"><?= e($order['cashier_name'] ?? 'Staff') ?></span>
        </div>
        <div class="receipt-meta-inline">
            <span class="font-normal"><?= date('d-M-y H:i', strtotime($order['created_at'])) ?></span>
            <span class="uppercase font-normal"><?= e(str_replace('_', ' ', $order['order_type'])) ?></span>
            <?php if (!empty($order['table_name'])): ?>
                <span class="font-normal"><?= e($order['table_name']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="receipt-divider"></div>

    <!-- Items Table -->
    <table class="w-full text-sm text-left my-1">
        <thead>
            <tr class="border-b border-stone-800 pb-1">
                <th class="py-0.5 font-normal">DESCRIPTION</th>
                <th class="py-0.5 text-center font-normal">QTY</th>
                <th class="py-0.5 text-right font-normal">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($order['items'] as $item): ?>
            <tr>
                <td class="py-1 align-top">
                    <div class="leading-tight"><?= e($item['product_name']) ?></div>
                    <?php if (!empty($item['variant_name'])): ?>
                        <div class="text-[11px] text-stone-500"><?= e($item['variant_name']) ?></div>
                    <?php endif; ?>
                    <?php 
                    if (!empty($item['modifiers_json'])) {
                        $mods = json_decode($item['modifiers_json'], true);
                        if (!empty($mods)) {
                            foreach ($mods as $m) {
                                echo '<div class="text-[11px] text-stone-600 pl-1">+ ' . e($m['name']) . ' (' . $currency . ' ' . number_format($m['price'], 2) . ')</div>';
                            }
                        }
                    }
                    if (!empty($item['notes'])) {
                        echo '<div class="text-[11px] italic text-stone-500">Note: ' . e($item['notes']) . '</div>';
                    }
                    if ((float)($item['promotion_discount'] ?? 0) > 0) {
                        echo '<div class="text-[11px] text-emerald-700">Promotion Discount: -' . number_format($item['promotion_discount'], 2) . '</div>';
                    }
                    if ((float)($item['normal_discount'] ?? 0) > 0) {
                        echo '<div class="text-[11px] text-stone-600">Normal Discount: -' . number_format($item['normal_discount'], 2) . '</div>';
                    }
                    ?>
                </td>
                <td class="py-1 text-center align-top"><?= (int)$item['quantity'] ?></td>
                <!-- <td class="py-1.5 text-right align-top"><?= number_format($item['unit_price'], 2) ?></td> -->
                <!-- <td class="py-1.5 text-right align-top font-bold"><?= number_format($item['subtotal'], 2) ?></td> -->
                <td class="py-1 text-right align-top"><?= number_format($item['subtotal'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="receipt-divider-double"></div>

    <!-- Totals Summary -->
    <div class="text-sm space-y-1">
        <div class="flex justify-between">
            <span>Subtotal:</span>
            <span> <?= number_format($order['subtotal'], 2) ?></span>
        </div>
        <div class="flex justify-between text-sm font-black pt-0.5">
            <span>Total:</span>
            <span><?= number_format($order['grand_total'], 2) ?></span>
        </div>
        <?php if ($promotionDiscount > 0): ?>
        <div class="flex justify-between text-emerald-700">
            <span>Promotion Discount:</span>
            <span>-<?= number_format($promotionDiscount, 2) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($normalDiscount > 0): ?>
        <div class="flex justify-between text-stone-700">
            <span>Normal Discount:</span>
            <span>-<?= number_format($normalDiscount, 2) ?></span>
        </div>
        <?php endif; ?>
        <?php if (($promotionDiscount + $normalDiscount) > 0): ?>
        <div class="flex justify-between font-normal">
            <span>Total Discount:</span>
            <span>-<?= number_format($promotionDiscount + $normalDiscount, 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="receipt-divider"></div>
        <?php if ($order['paid_amount'] > 0): ?>
        <div class="flex justify-between">
            <span><?= e($order['payment_method']) ?></span>
            <span><?= number_format($order['paid_amount'], 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="flex justify-between text-sm font-normal pt-0.5">
            <span>Balance:</span>
            <span><?= number_format($order['change_amount'], 2) ?></span>
        </div>

        <div class="receipt-divider"></div>
    </div>

    <!-- Footer Note -->
    <div class="text-center pt-2 space-y-2">
        <div class="text-xs text-stone-700 font-medium leading-tight">
            <?= nl2br(e($settings['receipt_footer'] ?? 'Thank you for your visit!')) ?>
        </div>
        <!-- <div class="text-[10px] text-stone-400 font-mono tracking-widest pt-1">
            * <?= e($order['invoice_no']) ?> *
        </div> -->
    </div>
</div>
