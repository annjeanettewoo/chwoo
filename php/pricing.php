<?php
// One SGD pricing policy for bag, review and final order processing.
function chwooPriceCents($price) {
    if (!preg_match('/\A[0-9]+(?:\.[0-9]{1,2})?\z/D', (string)$price)) {
        throw new RuntimeException('A product price is invalid. Please review your bag.');
    }
    $parts = explode('.', (string)$price);
    return (int)$parts[0] * 100 + (int)str_pad($parts[1] ?? '', 2, '0');
}
function chwooMoney($cents) { return number_format($cents / 100, 2, '.', ''); }
function chwooDeliveryCharge($subtotal, $itemCount) {
    return $itemCount > 0 && $subtotal < 80 ? 5.0 : 0.0;
}
function chwooBagTotals($items) {
    $subtotal = 0;
    $count = 0;
    foreach ($items as $item) {
        $subtotal += $item['price_cents'] * $item['quantity'];
        $count += $item['quantity'];
    }
    $delivery = (int)(chwooDeliveryCharge($subtotal / 100, $count) * 100);
    return ['count' => $count, 'subtotal_cents' => $subtotal, 'delivery_cents' => $delivery,
        'total_cents' => $subtotal + $delivery];
}
