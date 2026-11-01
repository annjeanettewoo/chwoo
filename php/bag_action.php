<?php
// Shopping Bag — CSRF-protected quantity changes and removal, followed by a page redirect.
// bag.php first validates existing session lines and reloads current catalogue data.
session_start();
require_once __DIR__ . '/bag.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Please use the shopping bag controls.');
}
// --- Shopping Bag — Validate action identity and return context ---
// Accept a stored variant ID and one-step action, not a browser-supplied replacement quantity or price.
$token = $_POST['form_token'] ?? '';
$rawVariantId = $_POST['variant_id'] ?? '';
$variantId = is_string($rawVariantId) && preg_match('/^[1-9][0-9]*$/D', $rawVariantId)
    ? filter_var($rawVariantId, FILTER_VALIDATE_INT) : false;
$action = $_POST['action'] ?? '';
$context = $_POST['bag_context'] ?? 'full';
// Drawer actions return to an allowlisted originating page; full-bag actions stay on cart.php.
// Checkout actions return directly to review; from=checkout preserves the full bag's review-return link.
$returnTo = chwooReturnTo($_POST['return_to'] ?? 'php/cart.php');
if ($context === 'checkout') { $returnTo = 'php/checkout.php?step=review'; }
if ($context === 'full' && !in_array($returnTo, ['php/cart.php', 'php/cart.php?from=checkout'], true)) {
    $returnTo = 'php/cart.php';
}
// --- Shopping Bag — Apply quantity or removal action ---
$message = '';
if (!is_string($token) || !hash_equals($_SESSION['chwoo_form_token'], $token)) {
    $message = 'Please refresh the page and try again.';
} elseif (!in_array($context, ['full', 'drawer', 'checkout'], true)) {
    $message = 'Invalid bag action context.';
} elseif (!$variantId || !isset($_SESSION['chwoo_cart'][$variantId])) {
    $message = 'That variant is no longer in your bag.';
} elseif (!in_array($action, ['increase', 'decrease', 'remove'], true)) {
    $message = 'Invalid bag action.';
} elseif ($action === 'remove' || ($action === 'decrease' && $_SESSION['chwoo_cart'][$variantId]['quantity'] === 1)) {
    // Decreasing the last unit removes the line rather than leaving a zero-quantity entry.
    unset($_SESSION['chwoo_cart'][$variantId]);
} elseif ($action === 'decrease') {
    $_SESSION['chwoo_cart'][$variantId]['quantity']--;
} else {
    // Previously displayed stock may be stale; recheck this active variant before adding one more unit.
    // This changes only the session bag, not inventory. Order finalisation performs its own stock validation.
    $stmt = $conn->prepare('SELECT v.stock_quantity FROM product_variants AS v
                            JOIN product_colours AS c ON c.colour_id = v.colour_id
                            JOIN products AS p ON p.product_id = c.product_id
                            WHERE v.variant_id = ? AND p.is_active = 1');
    $stmt->bind_param('i', $variantId);
    $stmt->execute();
    $variant = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$variant || $_SESSION['chwoo_cart'][$variantId]['quantity'] >= (int)$variant['stock_quantity']) {
        $message = 'No more stock is available for this colour and size.';
    } else {
        $_SESSION['chwoo_cart'][$variantId]['quantity']++;
    }
}
// --- Shopping Bag — Preserve feedback and return to the active view ---
$message = trim($message . ' ' . $chwooBagMessage);
if ($message !== '') { $_SESSION['chwoo_bag_flash'] = $message; }
$location = $chwooRootUrl . $returnTo;
if ($context === 'drawer') {
    // The returned page renders its updated drawer open, with the first opening transition suppressed.
    $location .= (str_contains($returnTo, '?') ? '&' : '?') . 'bag=updated';
}
// POST/Redirect/GET prevents refresh from repeating a quantity change.
header('Location: ' . $location, true, 303);
exit;
