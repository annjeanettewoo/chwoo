<?php
// CHWOO Homepage | IE4727 Base Version
// Preserve the original CHWOO homepage design; only the four product cards load from MySQL.
require_once __DIR__ . '/php/auth.php';
require_once __DIR__ . '/php/bag.php';

// Escape database text before displaying it in HTML.
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Keep the original homepage selection and order: products 1-4.
// The full active catalogue remains available in php/shop.php.
$sql = 'SELECT p.product_id, p.product_name, c.price, c.image_path
        FROM products AS p
        JOIN product_colours AS c ON c.product_id = p.product_id AND c.is_default = 1
        WHERE p.is_active = 1
        ORDER BY p.product_id ASC
        LIMIT 4';

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$newArrivals = [];
while ($product = $result->fetch_assoc()) {
    $path = ltrim((string)$product['image_path'], '/\\');
    $safePath = preg_match('~^images/[a-zA-Z0-9_./-]+$~', $path)
                && strpos($path, '..') === false;
    $product['image_url'] = ($safePath && is_file(__DIR__ . '/' . $path)) ? $path : '';
    $newArrivals[] = $product;
}
$stmt->close();

// Use the same cart session as php/product.php and php/cart.php.
$bagCount = $chwooBagCount;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CHWOO Fashion Studio</title>

    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/home.css">
    <link rel="stylesheet" href="css/footer.css">
    <link rel="stylesheet" href="<?= chwooEscape($chwooRootUrl) ?>css/bag.css">
</head>

<body class="home-page">

    <!-- ========================================
         SITE HEADER
         ======================================== -->

    <?php include __DIR__ . '/php/header.php'; ?>

    <!-- ========================================
         HERO SECTION
         ======================================== -->

    <section class="hero">

        <!-- Hero Text -->
        <div class="hero-content">

            <p class="hero-small">
                CHWOO SUMMER '26
            </p>

            <h1>
                Wear a little<br>
                more fun.
            </h1>

            <p class="hero-description">
                Playful pieces made for everyday moments.
            </p>

            <a href="php/shop.php" class="shop-button">
                SHOP NEW IN
            </a>
        </div>

        <!-- Scroll Indicator -->
        <a href="#new-in" class="scroll-indicator">
            ↓
        </a>
    </section>

    <!-- ========================================
         MAIN CONTENT
         ======================================== -->

    <main>

        <!-- New In Section -->
        <section class="new-in" id="new-in">

            <div class="section-heading">
                <p>
                    JUST LANDED
                </p>
                <h2>
                    New In at CHWOO
                </h2>
            </div>

            <div class="product-grid">

                <!-- The product cards now come directly from MySQL. -->
                <?php foreach ($newArrivals as $product): ?>
                    <article class="product-card">

                        <a href="php/product.php?id=<?= (int)$product['product_id'] ?>">
                            <?php if ($product['image_url'] !== ''): ?>
                                <img src="<?= e($product['image_url']) ?>"
                                     alt="<?= e($product['product_name']) ?>">
                            <?php else: ?>
                                <div style="aspect-ratio: 3 / 4; display: flex; align-items: center; justify-content: center; padding: 16px; background: var(--warm-white); color: var(--brown); font-size: 13px; text-align: center;">Product photo coming soon</div>
                            <?php endif; ?>
                        </a>

                        <h3>
                            <?= e($product['product_name']) ?>
                        </h3>

                        <p class="price">
                            SGD $<?= number_format((float)$product['price'], 2) ?>
                        </p>

                    </article>
                <?php endforeach; ?>

                <?php if (empty($newArrivals)): ?>
                    <p>No new arrivals available at the moment.</p>
                <?php endif; ?>

            </div>

            <a href="php/shop.php" class="view-all">
                VIEW ALL NEW ARRIVALS →
            </a>
        </section>

        <!-- ========================================
             BRAND SECTION
             ======================================== -->

        <section class="brand-section">

            <p class="brand-small">
                HELLO, WE'RE CHWOO
            </p>

            <h2>
                Clothes with<br>
                a little personality.
            </h2>

            <p>
                CHWOO is a playful fashion studio creating pieces
                for everyday dressing, spontaneous plans and
                everything in between.
            </p>

            <!-- Our Story is on hold until an About page is available. -->
        </section>

    </main>

    <!-- ========================================
         FOOTER
         ======================================== -->

    <footer>

        <div class="footer-logo">
            CHWOO
        </div>

        <div class="footer-links">

            <a href="php/shop.php">
                Shop
            </a>

            <!-- ABOUT is on hold until a page is available. -->

            <!-- Contact is on hold until a page is available. -->

            <a href="php/account.php?section=orders">
                Orders
            </a>
        </div>

        <p>
            &copy; 2026 CHWOO Fashion Studio
        </p>

    </footer>

    <?php include __DIR__ . '/php/bag_drawer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>
