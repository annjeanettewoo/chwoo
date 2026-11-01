<?php
// New In — Latest active products with default-colour photos and prices.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/bag.php';

// This page is under php/; root-relative URLs keep links and public assets under the same installation.
$baseUrl = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/') . '/';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// --- New In — Retrieve the latest eight products ---
// The current products table has no "added_at" date. Higher product IDs are
// treated as newer. New In always shows only the latest eight active products;
// legacy page query parameters do not change this selection.
$stmt = $conn->prepare(
    'SELECT p.product_id, p.product_name, c.price, c.image_path
     FROM products AS p
     JOIN product_colours AS c ON c.product_id = p.product_id AND c.is_default = 1
     WHERE p.is_active = 1
     ORDER BY p.product_id DESC
     LIMIT 8'
);
$stmt->execute();
$result = $stmt->get_result();
$newArrivals = [];
// Collect default-colour display rows; an empty image URL selects the missing-photo placeholder.
while ($product = $result->fetch_assoc()) {
    // Images must come from CHWOO's own images/ folder, never a user-supplied URL.
    $path = ltrim((string)$product['image_path'], '/\\');
    $safePath = preg_match('~^images/[a-zA-Z0-9_./-]+$~', $path)
                && strpos($path, '..') === false;
    $product['image_url'] = ($safePath && is_file(__DIR__ . '/../' . $path)) ? $baseUrl . $path : '';
    $newArrivals[] = $product;
}
$stmt->close();

// Reuse the session bag count loaded by bag.php for the shared header.
$bagCount = $chwooBagCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New In | CHWOO Fashion Studio</title>

    <!-- New In — Shared homepage layout styles. -->
    <link rel="stylesheet" href="<?= e($baseUrl) ?>css/base.css">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>css/header.css">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>css/home.css">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>css/footer.css">
    <!-- Page heading, card alignment and missing-photo styles. -->
    <link rel="stylesheet" href="<?= e($baseUrl) ?>css/newin.css">
    <link rel="stylesheet" href="<?= chwooEscape($chwooRootUrl) ?>css/bag.css">
</head>
<body class="newin-page">

    <!-- Shared navigation. -->
    <?php include __DIR__ . '/header.php'; ?>

    <main>
        <!-- New In — Product listing and full-catalogue link. -->
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

    <!-- Site footer. -->
    <footer>
        <div class="footer-logo">CHWOO</div>
        <div class="footer-links">
            <a href="<?= e($baseUrl) ?>php/shop.php">Shop</a>
            <a href="<?= e($baseUrl) ?>php/account.php?section=orders">Orders</a>
        </div>
        <p>&copy; 2026 CHWOO Fashion Studio</p>
    </footer>
    <?php include __DIR__ . '/bag_drawer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>
