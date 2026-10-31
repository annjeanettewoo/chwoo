<?php
// Ordinary CSRF-protected forms update the one authoritative session bag.
session_start();
require_once __DIR__ . '/bag.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Please use the shopping bag controls.');
}
$token = $_POST['form_token'] ?? '';
$rawVariantId = $_POST['variant_id'] ?? '';
$variantId = is_string($rawVariantId) && preg_match('/^[1-9][0-9]*$/D', $rawVariantId)
    ? filter_var($rawVariantId, FILTER_VALIDATE_INT) : false;
$action = $_POST['action'] ?? '';
$context = $_POST['bag_context'] ?? 'full';
$returnTo = chwooReturnTo($_POST['return_to'] ?? 'php/cart.php');
if ($context === 'checkout') { $returnTo = 'php/checkout.php?step=review'; }
if ($context === 'full' && !in_array($returnTo, ['php/cart.php', 'php/cart.php?from=checkout'], true)) {
    $returnTo = 'php/cart.php';
}
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
    unset($_SESSION['chwoo_cart'][$variantId]);
} elseif ($action === 'decrease') {
    $_SESSION['chwoo_cart'][$variantId]['quantity']--;
} else {
    // Recheck this exact variant immediately before increasing its session quantity.
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
$message = trim($message . ' ' . $chwooBagMessage);
if ($message !== '') { $_SESSION['chwoo_bag_flash'] = $message; }
$location = $chwooRootUrl . $returnTo;
if ($context === 'drawer') {
    $location .= (str_contains($returnTo, '?') ? '&' : '?') . 'bag=updated';
}
header('Location: ' . $location, true, 303);
exit;
