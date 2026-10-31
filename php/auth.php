<?php
// Small shared entry point for the standalone page and future account forms.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['chwoo_form_token'])) {
    $_SESSION['chwoo_form_token'] = bin2hex(random_bytes(16));
}

function chwooAuthEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function chwooCurrentCustomer() {
    return $_SESSION['chwoo_customer'] ?? null;
}

function chwooAuthRootUrl() {
    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    return rtrim(basename($directory) === 'php' ? dirname($directory) : $directory, '/') . '/';
}

// Shared allowlist for ordinary form redirects; paths are relative to CHWOO.
function chwooReturnTo($value, $fallback = 'php/cart.php') {
    $allowed = ['index.php', 'php/shop.php', 'php/newin.php', 'php/product.php',
                'php/cart.php', 'php/collections.php', 'php/account.php', 'php/checkout.php'];
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

function chwooAuthenticate($customer) {
    // Regeneration keeps the bag, shared CSRF token and pending wishlist intact.
    session_regenerate_id(true);
    $_SESSION['chwoo_customer'] = [
        'customer_id' => (int)$customer['customer_id'],
        'full_name' => $customer['full_name']
    ];
}

function chwooAuthRedirect($message, $errors = [], $old = [], $view = 'login') {
    // Only explicitly supplied name/email values may be retained, never passwords.
    $_SESSION['chwoo_auth_feedback'] = [
        'message' => $message, 'errors' => $errors, 'old' => $old
    ];
    header('Location: ' . chwooAuthRootUrl() . 'php/login.php'
        . ($view === 'register' ? '?view=register' : ''), true, 303);
    exit;
}

function chwooAuthHome($message) {
    unset($_SESSION['chwoo_auth_feedback']);
    $_SESSION['chwoo_auth_notice'] = $message;
    header('Location: ' . chwooAuthRootUrl() . 'index.php', true, 303);
    exit;
}

function chwooAuthAccount($message) {
    unset($_SESSION['chwoo_auth_feedback']);
    $_SESSION['chwoo_auth_notice'] = $message;
    $destination = !empty($_SESSION['chwoo_checkout_login']) ? 'php/checkout.php' : 'php/account.php';
    unset($_SESSION['chwoo_checkout_login']);
    header('Location: ' . chwooAuthRootUrl() . $destination, true, 303);
    exit;
}
