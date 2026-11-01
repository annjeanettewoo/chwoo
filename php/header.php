<?php
// Shared Header — Brand navigation, account access, bag count and session feedback.
require_once __DIR__ . '/auth.php';
// The installation root supports both index.php and pages inside php/; escape URLs at output.
$headerRoot = chwooAuthRootUrl();
$headerPage = basename($_SERVER['SCRIPT_NAME']);
// Shop pages place the announcement outside the header; Product Details keeps SHOP active.
$headerShop = in_array($headerPage, ['shop.php', 'product.php', 'cart.php'], true);
$headerActive = $headerPage === 'product.php' ? 'shop.php' : $headerPage;
// Account destination follows session identity; protected pages enforce access themselves.
// The including page supplies $chwooBagCount as total units from the shared bag loader.
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
            <?php // LOOKBOOK opens the Outfit Builder; its navigation label differs from the feature name. ?>
        </div>
        <div class="nav-tools">
            <span>SG | SGD $</span>
            <a class="chwoo-account-link" href="<?= chwooAuthEscape($headerRoot) ?>php/<?= chwooCurrentCustomer() ? 'account.php' : 'login.php' ?>" <?= in_array($headerPage, ['login.php', 'account.php'], true) ? 'aria-current="page"' : '' ?>><?= chwooCurrentCustomer() ? 'ACCOUNT' : 'LOGIN' ?></a>
            <a data-bag-trigger href="<?= chwooAuthEscape($headerRoot) ?>php/cart.php">BAG (<?= (int)$chwooBagCount ?>)</a>
        </div>
    </nav>
</header>
<?php // Consume feedback after rendering once. Only these recognised success messages expire in main.js.
if (!empty($_SESSION['chwoo_auth_notice'])): ?>
<?php $temporarySuccess = in_array($_SESSION['chwoo_auth_notice'], [
    'Welcome back. You are now logged in.', 'Welcome to CHWOO. Your account has been created.',
    'You have been logged out.', 'You are already logged in.', 'Saved to your wishlist.', 'Removed from your wishlist.'
], true); ?>
<p class="chwoo-auth-notice" role="<?= $temporarySuccess ? 'status' : 'alert' ?>" <?= $temporarySuccess ? 'data-auto-dismiss' : '' ?>><?= chwooAuthEscape($_SESSION['chwoo_auth_notice']) ?></p>
<?php unset($_SESSION['chwoo_auth_notice']); endif;
// Deferred shared JavaScript handles notice dismissal; navigation uses ordinary links. ?>
<script src="<?= chwooAuthEscape($headerRoot) ?>js/main.js" defer></script>
