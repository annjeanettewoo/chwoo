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
    header('Location: ' . chwooAuthRootUrl() . 'php/account.php', true, 303);
    exit;
}
