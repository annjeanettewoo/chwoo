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
            <?php foreach (['newin.php' => 'NEW IN', 'shop.php' => 'SHOP', 'collections.php' => 'COLLECTIONS'] as $page => $label): ?>
            <a href="<?= chwooAuthEscape($headerRoot . 'php/' . $page) ?>" <?= $headerActive === $page ? 'aria-current="page"' : '' ?>><?= $label ?></a>
            <?php endforeach; ?>
            <a href="<?= chwooAuthEscape($headerRoot) ?>lookbook.html">LOOKBOOK</a>
            <a href="<?= chwooAuthEscape($headerRoot) ?>about.html">ABOUT</a>
        </div>
        <div class="nav-tools">
            <span>SG | SGD $</span>
            <a href="<?= chwooAuthEscape($headerRoot) ?>search.html">SEARCH</a>
            <a class="chwoo-account-link" href="<?= chwooAuthEscape($headerRoot) ?>php/<?= chwooCurrentCustomer() ? 'account.php' : 'login.php' ?>" <?= in_array($headerPage, ['login.php', 'account.php'], true) ? 'aria-current="page"' : '' ?>><?= chwooCurrentCustomer() ? 'Account' : 'LOGIN' ?></a>
            <a data-bag-trigger href="<?= chwooAuthEscape($headerRoot) ?>php/cart.php">BAG (<?= (int)$chwooBagCount ?>)</a>
        </div>
    </nav>
</header>
<?php if (!empty($_SESSION['chwoo_auth_notice'])): ?>
<p class="chwoo-auth-notice" role="status"><?= chwooAuthEscape($_SESSION['chwoo_auth_notice']) ?></p>
<?php unset($_SESSION['chwoo_auth_notice']); endif; ?>
