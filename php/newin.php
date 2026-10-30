<?php
// CHWOO New In | IE4727 Base Version
// A dynamic catalogue page that reuses the ORIGINAL CHWOO HTML classes and CSS.
session_start();
require_once __DIR__ . '/bag.php';

// newin.php lives in chwoo/php, but public assets still live in chwoo/.
// Resolve the project root from the script URL to preserve CSS and image paths.
$baseUrl = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/') . '/';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// The current products table has no "added_at" date. Higher product IDs are
// treated as newer. Show exactly 8 products per page (except the last page).
$productsPerPage = 8;

// Count all active products so we know how many page buttons to display.
$countResult = $conn->query(
    'SELECT COUNT(*) AS total
     FROM products AS p
     JOIN product_colours AS c ON c.product_id = p.product_id AND c.is_default = 1
     WHERE p.is_active = 1'
);
$totalProducts = (int)$countResult->fetch_assoc()['total'];
$totalPages = max(1, (int)ceil($totalProducts / $productsPerPage));

// ?page=1, ?page=2, etc. Reject invalid and out-of-range page numbers.
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if ($page === false || $page === null) {
    $page = 1;
}
$page = min($page, $totalPages);

// OFFSET skips the products shown on earlier pages.
$offset = ($page - 1) * $productsPerPage;
$stmt = $conn->prepare(
    'SELECT p.product_id, p.product_name, c.price, c.image_path
     FROM products AS p
     JOIN product_colours AS c ON c.product_id = p.product_id AND c.is_default = 1
     WHERE p.is_active = 1
     ORDER BY p.product_id DESC
     LIMIT ? OFFSET ?'
);
$stmt->bind_param('ii', $productsPerPage, $offset);
$stmt->execute();
$result = $stmt->get_result();
$newArrivals = [];
while ($product = $result->fetch_assoc()) {
    // Images must come from CHWOO's own images/ folder, never a user-supplied URL.
    $path = ltrim((string)$product['image_path'], '/\\');
    $safePath = preg_match('~^images/[a-zA-Z0-9_./-]+$~', $path)
                && strpos($path, '..') === false;
    $product['image_url'] = ($safePath && is_file(__DIR__ . '/../' . $path)) ? $baseUrl . $path : '';
    $newArrivals[] = $product;
}
$stmt->close();

// Show the SAME shopping bag count used by php/product.php and php/cart.php.
$bagCount = $chwooBagCount;
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
    <!-- Pagination-only styles. Original CHWOO CSS stays untouched. -->
    <link rel="stylesheet" href="<?= e($baseUrl) ?>css/newin-pagination.css">
    <link rel="stylesheet" href="<?= chwooEscape($chwooRootUrl) ?>css/bag.css">
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
                <a href="newin.php">NEW IN</a>
                <a href="shop.php" aria-current="page">SHOP</a>
                <a href="collections.php">COLLECTIONS</a>
                <a href="../lookbook.html">LOOKBOOK</a>
                <a href="../about.html">ABOUT</a>
            </div>
            <div class="nav-tools">
                <span>SG | SGD $</span>
                <a href="<?= e($baseUrl) ?>search.html">SEARCH</a>
                <a href="<?= e($baseUrl) ?>login.html">ACCOUNT</a>
                <a data-bag-trigger href="<?= e($baseUrl) ?>php/cart.php">BAG <span>(<?= $bagCount ?>)</span></a>
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

            <?php if ($totalPages > 1): ?>
                <nav class="newin-pagination" aria-label="New In pages">
                    <?php if ($page > 1): ?>
                        <a class="pagination-link" href="newin.php?page=<?= $page - 1 ?>">&larr; Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a class="pagination-link<?= $i === $page ? ' is-current' : '' ?>"
                           href="newin.php?page=<?= $i ?>"
                           <?= $i === $page ? 'aria-current="page"' : '' ?>>
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a class="pagination-link" href="newin.php?page=<?= $page + 1 ?>">Next &rarr;</a>
                    <?php endif; ?>
                </nav>
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
    <?php include __DIR__ . '/bag_drawer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>
