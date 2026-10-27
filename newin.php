<?php
// CHWOO New In | IE4727 Base Version
// A dynamic catalogue page that reuses the ORIGINAL CHWOO HTML classes and CSS.
// No changes are required to the existing products, orders or database structure.
session_start();
require_once __DIR__ . '/php/db.php';

// Browser URLs are anchored to this project folder (e.g. /chwoo/).
// This prevents relative CSS/image paths from resolving in the wrong folder.
$baseUrl = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// The current products table has no "added_at" date. Higher product IDs are
// treated as newer for display purposes. All active products are shown.
$stmt = $conn->prepare(
    'SELECT product_id, product_name, price, image_path
     FROM products
     WHERE is_active = 1
     ORDER BY product_id DESC'
    
);
$stmt->execute();
$result = $stmt->get_result();
$newArrivals = [];
while ($product = $result->fetch_assoc()) {
    // Images must come from CHWOO's own images/ folder, never a user-supplied URL.
    $path = ltrim((string)$product['image_path'], '/\\');
    $safePath = preg_match('~^images/[a-zA-Z0-9_./-]+$~', $path)
                && strpos($path, '..') === false;
    $product['image_url'] = ($safePath && is_file(__DIR__ . '/' . $path)) ? $baseUrl . $path : '';
    $newArrivals[] = $product;
}
$stmt->close();

// Show the SAME shopping bag count used by php/product.php and php/cart.php.
$bagCount = 0;
foreach ($_SESSION['chwoo_cart'] ?? [] as $item) {
    $bagCount += (int)($item['quantity'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New In | CHWOO Fashion Studio</title>

    <!-- Original CHWOO stylesheets, same order as index.php. -->
    <link rel="stylesheet" href="<?= e($baseUrl) ?>css/base.css">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>css/header.css">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>css/home.css">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>css/footer.css">
    <!-- Only styles a missing-photo placeholder. Does NOT redesign the page. -->
    <link rel="stylesheet" href="<?= e($baseUrl) ?>css/newin.css">
</head>
<body class="newin-page">

    <!-- ORIGINAL CHWOO HEADER -->
    <header class="site-header">
        <div class="announcement-bar">
            FREE STANDARD COURIER DELIVERY WITH SGD$80 SPENT
        </div>
        <nav class="navbar" aria-label="Main navigation">
            <a href="<?= e($baseUrl) ?>index.php" class="logo-link">
                <img src="<?= e($baseUrl) ?>images/chwoo-logo.png" alt="CHWOO Fashion Studio" class="logo">
            </a>
            <div class="main-nav">
                <a href="<?= e($baseUrl) ?>newin.php" class="active" aria-current="page">NEW IN</a>
                <a href="<?= e($baseUrl) ?>php/shop.php">SHOP</a>
                <a href="<?= e($baseUrl) ?>collections.html">COLLECTIONS</a>
                <a href="<?= e($baseUrl) ?>lookbook.html">LOOKBOOK</a>
                <a href="<?= e($baseUrl) ?>about.html">ABOUT</a>
            </div>
            <div class="nav-tools">
                <span>SG | SGD $</span>
                <a href="<?= e($baseUrl) ?>search.html">SEARCH</a>
                <a href="<?= e($baseUrl) ?>login.html">ACCOUNT</a>
                <a href="<?= e($baseUrl) ?>php/cart.php">BAG <span>(<?= $bagCount ?>)</span></a>
            </div>
        </nav>
    </header>

    <main>
        <!-- SAME heading, four-column grid, fonts and spacing as homepage New In. -->
        <section class="new-in" id="new-in">
            <div class="section-heading">
                <p>JUST LANDED</p>
                <h1 class="newin-title">New In at CHWOO</h1>
            </div>
            <div class="product-grid">
                <?php foreach ($newArrivals as $product): ?>
                    <article class="product-card">
                        <a href="<?= e($baseUrl) ?>php/product.php?id=<?= (int)$product['product_id'] ?>"
                           aria-label="View <?= e($product['product_name']) ?>">
                            <?php if ($product['image_url'] !== ''): ?>
                                <img src="<?= e($product['image_url']) ?>"
                                     alt="<?= e($product['product_name']) ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="product-photo-placeholder">Product photo coming soon</div>
                            <?php endif; ?>
                        </a>
                        <h3><?= e($product['product_name']) ?></h3>
                        <p class="price">SGD $<?= number_format((float)$product['price'], 2) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if (empty($newArrivals)): ?>
                <p>New pieces are on their way. Explore our collection again soon.</p>
            <?php endif; ?>
            <a href="<?= e($baseUrl) ?>php/shop.php" class="view-all">SHOP ALL PIECES →</a>
        </section>
    </main>

    <!-- ORIGINAL CHWOO FOOTER -->
    <footer>
        <div class="footer-logo">CHWOO</div>
        <div class="footer-links">
            <a href="<?= e($baseUrl) ?>php/shop.php">Shop</a>
            <a href="<?= e($baseUrl) ?>about.html">About Us</a>
            <a href="<?= e($baseUrl) ?>contact.html">Contact</a>
            <a href="<?= e($baseUrl) ?>orders.html">Orders</a>
        </div>
        <p>&copy; 2026 CHWOO Fashion Studio</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>
