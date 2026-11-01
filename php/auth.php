<?php
// Authentication — Shared customer session, form token, identity and internal redirect helpers.
// --- Authentication — Reuse the shopping session and CSRF token ---
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['chwoo_form_token'])) {
    $_SESSION['chwoo_form_token'] = bin2hex(random_bytes(16));
}

function chwooAuthEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Return cached session identity or null; this helper does not query account records or enforce page access.
function chwooCurrentCustomer() {
    return $_SESSION['chwoo_customer'] ?? null;
}

// Resolve the installation root for pages served at the root or under php/.
function chwooAuthRootUrl() {
    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    return rtrim(basename($directory) === 'php' ? dirname($directory) : $directory, '/') . '/';
}

// --- Authentication — Internal shopping destinations ---
// Shared by bag/wishlist forms: allow only CHWOO paths and recognised scalar query fields, otherwise use the fallback.
function chwooReturnTo($value, $fallback = 'php/cart.php') {
    $allowed = ['index.php', 'php/shop.php', 'php/newin.php', 'php/product.php',
                'php/cart.php', 'php/account.php', 'php/checkout.php', 'php/outfit.php'];
    if (!is_string($value) || strlen($value) > 1000) { return $fallback; }
    $parts = parse_url($value);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])
        || !in_array($parts['path'] ?? '', $allowed, true)) {
        return $fallback;
    }
    parse_str($parts['query'] ?? '', $query);
    $safeQuery = [];
    foreach (['id', 'colour', 'size', 'page', 'category', 'sort', 'section'] as $key) {
        if (isset($query[$key]) && is_string($query[$key]) && strlen($query[$key]) <= 80) {
            $safeQuery[$key] = $query[$key];
        }
    }
    if (($parts['path'] ?? '') === 'php/checkout.php' && in_array($query['step'] ?? '', ['details', 'review'], true)) {
        $safeQuery['step'] = $query['step'];
    }
    if (($parts['path'] ?? '') === 'php/cart.php' && ($query['from'] ?? '') === 'checkout') {
        $safeQuery['from'] = 'checkout';
    }
    return $parts['path'] . ($safeQuery ? '?' . http_build_query($safeQuery) : '');
}

// --- Authentication — Establish identity and carry shopping intent across sign-in ---
// Store customer ID/name only; credentials stay out of session identity and existing bag contents are retained.
function chwooAuthenticate($customer) {
    // Regeneration keeps the bag, shared CSRF token and pending wishlist intact.
    session_regenerate_id(true);
    $_SESSION['chwoo_customer'] = [
        'customer_id' => (int)$customer['customer_id'],
        'full_name' => $customer['full_name']
    ];
}

// Keep one-time form feedback through a redirect to login/registration; callers supply non-secret restored values.
function chwooAuthRedirect($message, $errors = [], $old = [], $view = 'login') {
    // Authentication callers omit passwords from old values; the form renderer also leaves password fields blank.
    $_SESSION['chwoo_auth_feedback'] = [
        'message' => $message, 'errors' => $errors, 'old' => $old
    ];
    header('Location: ' . chwooAuthRootUrl() . 'php/login.php'
        . ($view === 'register' ? '?view=register' : ''), true, 303);
    exit;
}

// Finish logout with a shared notice on Home rather than retaining form-validation feedback.
function chwooAuthHome($message) {
    unset($_SESSION['chwoo_auth_feedback']);
    $_SESSION['chwoo_auth_notice'] = $message;
    header('Location: ' . chwooAuthRootUrl() . 'index.php', true, 303);
    exit;
}

// Checkout intent takes priority over order-history intent, then normal Account navigation; consume both flags once.
function chwooAuthAccount($message) {
    unset($_SESSION['chwoo_auth_feedback']);
    $_SESSION['chwoo_auth_notice'] = $message;
    $destination = !empty($_SESSION['chwoo_checkout_login']) ? 'php/checkout.php'
        : (!empty($_SESSION['chwoo_orders_login']) ? 'php/account.php?section=orders' : 'php/account.php');
    unset($_SESSION['chwoo_checkout_login'], $_SESSION['chwoo_orders_login']);
    header('Location: ' . chwooAuthRootUrl() . $destination, true, 303);
    exit;
}
