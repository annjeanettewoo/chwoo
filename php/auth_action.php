<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/wishlist.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Please use the account forms.');
}
$token = $_POST['form_token'] ?? '';
$action = $_POST['action'] ?? '';
$view = $action === 'register' ? 'register' : 'login';
if (!is_string($token) || !hash_equals($_SESSION['chwoo_form_token'], $token)) {
    chwooAuthRedirect('', ['form' => 'Please refresh this page and try again.'], [], $view);
}
if ($action === 'logout') {
    unset($_SESSION['chwoo_customer'], $_SESSION['chwoo_pending_wishlist_product_id'],
        $_SESSION['chwoo_pending_wishlist_return_to'], $_SESSION['chwoo_checkout_login'], $_SESSION['chwoo_orders_login'],
        $_SESSION['chwoo_checkout'], $_SESSION['chwoo_checkout_feedback'], $_SESSION['chwoo_purchase'],
        $_SESSION['chwoo_order_error'], $_SESSION['chwoo_payment_error']);
    session_regenerate_id(true);
    chwooAuthHome('You have been logged out.');
}
if (!in_array($action, ['register', 'login'], true)) {
    chwooAuthRedirect('', ['form' => 'Please choose a valid account action.']);
}
if (chwooCurrentCustomer()) {
    if (isset($_SESSION['chwoo_pending_wishlist_product_id'])) {
        require_once __DIR__ . '/db.php';
        chwooWishlistAfterAuthentication($conn);
    }
    chwooAuthAccount('You are already logged in.');
}
// Treat array-shaped submissions as missing fields, rather than PHP type errors.
$email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
$submittedPassword = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$fullName = is_string($_POST['full_name'] ?? null) ? $_POST['full_name'] : '';
$confirmation = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
$old = ['action' => $action, 'email' => substr($email, 0, 190)];
$errors = [];
$emailPattern = '/\A[A-Za-z0-9!#$%&\x27*+\/=?^_`{|}~-]+(?:\.[A-Za-z0-9!#$%&\x27*+\/=?^_`{|}~-]+)*@(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,}\z/D';
$validEmail = strlen($email) <= 190 && preg_match($emailPattern, $email)
    && !str_contains($email, '..') && filter_var($email, FILTER_VALIDATE_EMAIL);
if (!$validEmail) {
    $errors['email'] = $email === '' ? 'Please enter your email address.'
        : 'Please enter a valid email address, such as name@example.com.';
}
if ($submittedPassword === '') { $errors['password'] = 'Please enter your password.'; }
if ($action === 'register') {
    $old['full_name'] = mb_substr($fullName, 0, 120, 'UTF-8');
    $namePattern = "/\A\p{L}[\p{L}\p{M} .'’\-]*\z/u";
    if (trim($fullName) === '') {
        $errors['full_name'] = 'Please enter your full name.';
    } elseif ($fullName[0] === ' ' || substr($fullName, -1) === ' ' || str_contains($fullName, '  ')) {
        $errors['full_name'] = 'Please remove the extra spaces in your name.';
    } elseif (!mb_check_encoding($fullName, 'UTF-8') || !preg_match($namePattern, $fullName)) {
        $errors['full_name'] = 'Please check the characters in your name.';
    } elseif (mb_strlen($fullName, 'UTF-8') < 2 || mb_strlen($fullName, 'UTF-8') > 120) {
        $errors['full_name'] = 'Please use 2 to 120 characters for your full name.';
    }
    // PHP 8.2 PASSWORD_DEFAULT uses bcrypt, which only considers the first 72 bytes.
    if ($submittedPassword !== '') {
        if (!mb_check_encoding($submittedPassword, 'UTF-8') || str_contains($submittedPassword, "\0")) {
            $errors['password'] = 'Please choose a different password.';
        } elseif (mb_strlen($submittedPassword, 'UTF-8') < 8) {
            $errors['password'] = 'Your password must contain at least 8 characters.';
        } elseif (strlen($submittedPassword) > 72) {
            $errors['password'] = 'This password is too long. Please use a shorter password.';
        }
    }
    if ($confirmation === '') { $errors['confirm_password'] = 'Please confirm your password.'; }
    elseif ($submittedPassword !== $confirmation) { $errors['confirm_password'] = 'The passwords do not match.'; }
} elseif (strlen($submittedPassword) > 72 || str_contains($submittedPassword, "\0")) {
    $errors['password'] = 'Incorrect email or password. Please try again.';
}
if ($errors) { chwooAuthRedirect('', $errors, $old, $view); }

require __DIR__ . '/db.php';
try {
    if ($action === 'register') {
        $hash = password_hash($submittedPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO customers (full_name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->bind_param('sss', $fullName, $email, $hash);
        $stmt->execute();
        $customer = ['customer_id' => $conn->insert_id, 'full_name' => $fullName];
        $stmt->close();
        chwooAuthenticate($customer);
        chwooWishlistAfterAuthentication($conn);
        chwooAuthAccount('Welcome to CHWOO. Your account has been created.');
    }
    $stmt = $conn->prepare('SELECT customer_id, full_name, password_hash FROM customers WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$customer || !password_verify($submittedPassword, $customer['password_hash'])) {
        chwooAuthRedirect('', ['password' => 'Incorrect email or password. Please try again.'], $old);
    }
    chwooAuthenticate($customer);
    chwooWishlistAfterAuthentication($conn);
    chwooAuthAccount('Welcome back. You are now logged in.');
} catch (mysqli_sql_exception $error) {
    // The unique index handles duplicates, including concurrent registrations.
    $duplicate = $action === 'register' && $error->getCode() === 1062;
    $errors = $duplicate ? ['email' => 'An account with this email already exists. Please log in.']
        : ['form' => 'We could not complete your request. Please try again later.'];
    chwooAuthRedirect('', $errors, $old, $view);
}
