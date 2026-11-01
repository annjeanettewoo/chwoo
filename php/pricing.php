<?php
// Pricing — Shared SGD line totals, delivery policy and monetary formatting for bag, review and orders.
// --- Pricing — Convert catalogue prices to integer cents ---
// Parse decimal text before arithmetic to avoid accumulating binary floating-point price errors.
// Reject invalid/negative prices and more than two decimal places rather than silently rounding them.
function chwooPriceCents($price) {
    if (!preg_match('/\A[0-9]+(?:\.[0-9]{1,2})?\z/D', (string)$price)) {
        throw new RuntimeException('A product price is invalid. Please review your bag.');
    }
    $parts = explode('.', (string)$price);
    return (int)$parts[0] * 100 + (int)str_pad($parts[1] ?? '', 2, '0');
}
// Format cents as a two-decimal dollar string without a currency prefix; views add the visible SGD label.
function chwooMoney($cents) { return number_format($cents / 100, 2, '.', ''); }
// --- Pricing — Delivery threshold ---
// Subtotal and return value are dollars; itemCount is total units, not distinct variants.
// Charge $5 only for a nonempty bag below $80; otherwise charge $0.
function chwooDeliveryCharge($subtotal, $itemCount) {
    return $itemCount > 0 && $subtotal < 80 ? 5.0 : 0.0;
}
// --- Pricing — Calculate shared bag totals ---
// Each display row supplies price_cents and quantity; accumulate line totals in cents, not formatted dollars.
// Return subtotal_cents, delivery_cents, total_cents and count (total units, not distinct variants).
function chwooBagTotals($items) {
    $subtotal = 0;
    $count = 0;
    foreach ($items as $item) {
        $subtotal += $item['price_cents'] * $item['quantity'];
        $count += $item['quantity'];
    }
    // Bridge the dollar-based delivery helper to cents, then keep the final addition in cents.
    $delivery = (int)(chwooDeliveryCharge($subtotal / 100, $count) * 100);
    return ['count' => $count, 'subtotal_cents' => $subtotal, 'delivery_cents' => $delivery,
        'total_cents' => $subtotal + $delivery];
}
