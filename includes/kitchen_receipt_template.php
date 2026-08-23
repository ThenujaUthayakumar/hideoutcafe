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
    <div class="text-center pb-2">
        <div class="font-black text-base uppercase tracking-wider">KITCHEN COPY</div>
        <div class="text-[11px] text-stone-600 font-medium"><?php echo nl2br(e($settings['receipt_header'] ?? '')); ?></div>
        <div class="text-[11px] text-stone-600"><?php echo e($settings['cafe_address'] ?? ''); ?></div>
        <div class="text-[11px] text-stone-600 font-bold">Tel: <?php echo e($settings['cafe_phone'] ?? ''); ?></div>
    </div>

    <div class="receipt-divider"></div>

    <!-- Invoice Meta -->
    <div class="text-xs space-y-0.5">
        <div class="flex justify-between">
            <span>Invoice:</span>
            <span class="font-bold"><?php echo e($order['invoice_no']); ?></span>
        </div>
        <div class="flex justify-between">
            <span>Date/Time:</span>
            <span><?php echo date('d-M-Y H:i', strtotime($order['created_at'])); ?></span>
        </div>
        <div class="flex justify-between">
            <span>Table:</span>
            <span><?php echo e($order['table_name'] ?? ''); ?></span>
        </div>
    </div>

    <div class="receipt-divider"></div>

    <!-- Items Table -->
    <table class="w-full text-xs text-left my-1">
        <thead>
            <tr class="border-b border-stone-800 pb-1">
                <th class="py-1">Item</th>
                <th class="py-1 text-center">Qty</th>
                <th class="py-1 text-right">Price</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-200">
            <?php foreach ($order['items'] as $item): ?>
            <tr>
                <td class="py-1.5 align-top">
                    <div class="font-bold leading-tight"><?php echo e($item['product_name']); ?></div>
                    <?php if (!empty($item['variant_name'])): ?>
                        <div class="text-[10px] text-stone-500"><?php echo e($item['variant_name']); ?></div>
                    <?php endif; ?>
                </td>
                <td class="py-1.5 text-center align-top"><?php echo (int)$item['quantity']; ?></td>
                <td class="py-1.5 text-right align-top"><?php echo number_format($item['unit_price'], 2); ?></td>
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
    <div class="text-center pt-2 space-y-2">
        <div class="text-[11px] text-stone-700 font-medium leading-tight">
            <?php echo nl2br(e($settings['receipt_footer'] ?? 'Thank you for your visit!')); ?>
        </div>
    </div>
</div>
