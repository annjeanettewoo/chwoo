<?php
require_once __DIR__ . '/auth.php';
if (chwooCurrentCustomer()) {
    require_once __DIR__ . '/wishlist.php';
    if (isset($_SESSION['chwoo_pending_wishlist_product_id'])) {
        require_once __DIR__ . '/db.php';
        chwooWishlistAfterAuthentication($conn);
    }
    chwooAuthAccount('You are already logged in.');
}
require_once __DIR__ . '/bag.php';
// Keep bag availability warnings until a checkout-initiated sign-in resumes.
if (!empty($_SESSION['chwoo_checkout_login']) && $chwooBagMessage !== '') {
    $_SESSION['chwoo_bag_flash'] = $chwooBagMessage;
}
header('Cache-Control: no-store');
$feedback = $_SESSION['chwoo_auth_feedback'] ?? [];
unset($_SESSION['chwoo_auth_feedback']);
$old = $feedback['old'] ?? [];
$errors = $feedback['errors'] ?? [];
$register = ($_GET['view'] ?? '') === 'register';
$title = $register ? 'Create an Account' : 'Sign In';
$token = chwooAuthEscape($_SESSION['chwoo_form_token']);

// The same inline error elements are used by PHP and the optional JavaScript.
function chwooAuthField($name, $label, $type, $autocomplete, $attributes = '') {
    global $errors, $old, $register;
    $error = $errors[$name] ?? '';
    $value = $type === 'password' ? '' : ($old[$name] ?? '');
    $description = $name . '-error' . ($name === 'password' && $register ? ' password-help' : '');
    ?>
    <div class="auth-field">
        <label for="<?= $name ?>"><?= $label ?></label>
        <input id="<?= $name ?>" name="<?= $name ?>" type="<?= $type ?>" autocomplete="<?= $autocomplete ?>"
            required <?= $attributes ?> value="<?= chwooAuthEscape($value) ?>"
            aria-describedby="<?= $description ?>" aria-invalid="<?= $error ? 'true' : 'false' ?>">
        <p id="<?= $name ?>-error" class="auth-field-error" aria-live="polite" <?= $error ? '' : 'hidden' ?>><?= chwooAuthEscape($error) ?></p>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> | CHWOO</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/bag.css">
    <link rel="stylesheet" href="../css/auth.css">
    <script src="../js/auth-validation.js" defer></script>
</head>
<body class="auth-page">
    <?php include __DIR__ . '/header.php'; ?>
    <main class="auth-main">
        <p class="auth-eyebrow">YOUR CHWOO</p>
        <h1><?= $title ?></h1>
        <?php if (!empty($feedback['message'])): ?>
            <p class="auth-intro" role="status"><?= chwooAuthEscape($feedback['message']) ?></p>
        <?php endif; ?>
        <?php if (!empty($errors['form'])): ?>
            <p class="auth-form-error" role="alert"><?= chwooAuthEscape($errors['form']) ?></p>
        <?php endif; ?>

            <p class="auth-intro"><?= $register ? 'A little more CHWOO, just for you.' : 'Welcome back. It’s lovely to see you.' ?></p>
            <?php if (!empty($_SESSION['chwoo_checkout_login'])): ?><p class="auth-intro">Sign in or create an account to continue to checkout. Your bag is saved.</p><?php endif; ?>
            <form method="post" action="auth_action.php" data-auth-form="<?= $register ? 'register' : 'login' ?>">
                <input type="hidden" name="form_token" value="<?= $token ?>">
                <input type="hidden" name="action" value="<?= $register ? 'register' : 'login' ?>">
                <?php if ($register): chwooAuthField('full_name', 'Full name', 'text', 'name', 'minlength="2" maxlength="120"'); endif; ?>
                <?php chwooAuthField('email', 'Email address', 'email', $register ? 'email' : 'username', 'maxlength="190"'); ?>
                <?php chwooAuthField('password', 'Password', 'password', $register ? 'new-password' : 'current-password', $register ? 'minlength="8"' : ''); ?>
                <?php if ($register): ?>
                    <p id="password-help" class="auth-help">Use at least 8 characters.</p>
                    <?php chwooAuthField('confirm_password', 'Confirm password', 'password', 'new-password'); ?>
                <?php endif; ?>
                <button class="auth-submit" type="submit"><?= $register ? 'Create Account' : 'Log In' ?></button>
            </form>
            <p class="auth-switch"><?= $register ? 'Already have an account?' : 'New to CHWOO?' ?>
                <a href="login.php<?= $register ? '' : '?view=register' ?>"><?= $register ? 'Log in.' : 'Create an account.' ?></a>
            </p>
    </main>
    <?php include __DIR__ . '/bag_drawer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>
