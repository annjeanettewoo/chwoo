<?php
// One navigation template; retain the existing home/editorial and shop CSS classes.
require_once __DIR__ . '/auth.php';
$headerRoot = chwooAuthRootUrl();
$headerPage = basename($_SERVER['SCRIPT_NAME']);
$headerShop = in_array($headerPage, ['shop.php', 'product.php', 'cart.php'], true);
$headerActive = $headerPage === 'product.php' ? 'shop.php' : $headerPage;
if ($headerShop): ?>
<div class="announcement-bar">FREE STANDARD COURIER DELIVERY WITH SGD$80 SPENT</div>
<?php endif; ?>
<header class="<?= $headerShop ? 'shop-header' : 'site-header' ?>">
    <?php if (!$headerShop): ?>
    <div class="announcement-bar">FREE STANDARD COURIER DELIVERY WITH SGD$80 SPENT</div>
    <?php endif; ?>
    <nav class="navbar" aria-label="Main navigation">
        <a class="logo-link" href="<?= chwooAuthEscape($headerRoot) ?>index.php">
            <img class="logo" src="<?= chwooAuthEscape($headerRoot) ?>images/chwoo-logo.png" alt="CHWOO Fashion Studio">
        </a>
        <div class="main-nav">
            <?php foreach (['newin.php' => 'NEW IN', 'shop.php' => 'SHOP', 'outfit.php' => 'LOOKBOOK'] as $navPage => $navLabel): ?>
            <a href="<?= chwooAuthEscape($headerRoot . 'php/' . $navPage) ?>" <?= $headerActive === $navPage ? 'aria-current="page"' : '' ?>><?= $navLabel ?></a>
            <?php endforeach; ?>
            <?php // ABOUT is on hold: no About page currently exists. ?>
        </div>
        <div class="nav-tools">
            <span>SG | SGD $</span>
            <a class="chwoo-account-link" href="<?= chwooAuthEscape($headerRoot) ?>php/<?= chwooCurrentCustomer() ? 'account.php' : 'login.php' ?>" <?= in_array($headerPage, ['login.php', 'account.php'], true) ? 'aria-current="page"' : '' ?>><?= chwooCurrentCustomer() ? 'ACCOUNT' : 'LOGIN' ?></a>
            <a data-bag-trigger href="<?= chwooAuthEscape($headerRoot) ?>php/cart.php">BAG (<?= (int)$chwooBagCount ?>)</a>
        </div>
    </nav>
</header>
<?php if (!empty($_SESSION['chwoo_auth_notice'])): ?>
<?php $temporarySuccess = in_array($_SESSION['chwoo_auth_notice'], [
    'Welcome back. You are now logged in.', 'Welcome to CHWOO. Your account has been created.',
    'You have been logged out.', 'You are already logged in.', 'Saved to your wishlist.', 'Removed from your wishlist.'
], true); ?>
<p class="chwoo-auth-notice" role="<?= $temporarySuccess ? 'status' : 'alert' ?>" <?= $temporarySuccess ? 'data-auto-dismiss' : '' ?>><?= chwooAuthEscape($_SESSION['chwoo_auth_notice']) ?></p>
<?php unset($_SESSION['chwoo_auth_notice']); endif; ?>
<script src="<?= chwooAuthEscape($headerRoot) ?>js/main.js" defer></script>
