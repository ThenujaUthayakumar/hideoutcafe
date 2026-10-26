<?php
/**
 * The Hide Out Cafe - Kitchen Receipt Template (80mm / 58mm POS Slip)
 * This copy is intended for kitchen use.
 */
if (!isset($settings)) {
    $settings = getSettings();
}
$currency = $settings['currency_symbol'] ?? 'Rs.';
?>
<div class="receipt-container" id="thermal-receipt-area-kitchen">
    <!-- Header / Brand -->
    <div class="receipt-header pb-1">
        <div class="receipt-brand">KITCHEN COPY</div>
        <!-- <div class="receipt-contact"><?= e($settings['cafe_name'] ?? 'THE HIDE OUT CAFE') ?></div> -->
    </div>

    <div class="receipt-divider"></div>

    <!-- Invoice Meta -->
    <div class="receipt-meta space-y-0.5">
        <div class="receipt-meta-row">
            <span class="receipt-meta-label">Invoice:</span>
            <span class="receipt-meta-value"><?php echo e($order['invoice_no']); ?></span>
        </div>
        <div class="receipt-meta-row">
            <span class="receipt-meta-label">Cashier:</span>
            <span class="receipt-meta-value"><?php echo e($order['cashier_name'] ?? 'Staff'); ?></span>
        </div>
        <div class="receipt-meta-inline">
            <span class="font-normal"><?php echo date('d-M-y H:i', strtotime($order['created_at'])); ?></span>
            <span class="uppercase font-normal"><?= e(str_replace('_', ' ', $order['order_type'])) ?></span>
            <span class="font-normal"><?php echo e($order['table_name'] ?? '-'); ?></span>
        </div>
    </div>

    <div class="receipt-divider"></div>

    <!-- Items Table -->
    <table class="w-full text-xs text-left my-1">
        <thead>
            <tr class="border-b border-stone-800 pb-1">
                <th class="py-0.5 font-normal">DESCRIPTION</th>
                <th class="py-0.5 text-center font-normal">QTY</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($order['items'] as $item): ?>
            <tr>
                <td class="py-1 align-top">
                    <div class="font-bold leading-tight"><?php echo e($item['product_name']); ?></div>
                    <?php if (!empty($item['variant_name'])): ?>
                        <div class="text-[11px] text-stone-500"><?php echo e($item['variant_name']); ?></div>
                    <?php endif; ?>
                </td>
                <td class="py-1 text-center align-top"><?php echo (int)$item['quantity']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- <div class="receipt-divider"></div> -->
    <!-- Totals Summary -->
    <!-- <div class="text-xs space-y-1">
        <div class="flex justify-between">
            <span>Subtotal:</span>
            <span><?php echo number_format($order['subtotal'], 2); ?></span>
        </div>
        <?php if ($order['discount_amount'] > 0): ?>
        <div class="flex justify-between text-stone-700">
            <span>Discount:</span>
            <span><?php echo number_format($order['discount_amount'], 2); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="receipt-divider"></div>
    <div class="flex justify-between text-sm font-black pt-0.5">
        <span>Balance:</span>
        <span><?php echo number_format($order['change_amount'], 2); ?></span>
    </div> -->

    <div class="receipt-divider"></div>
    <!-- Footer Note -->
    <!-- <div class="text-center pt-2 space-y-2">
        <div class="text-[11px] text-stone-700 font-medium leading-tight">
            <?php echo nl2br(e($settings['receipt_footer'] ?? 'Thank you for your visit!')); ?>
        </div>
    </div> -->
</div>
