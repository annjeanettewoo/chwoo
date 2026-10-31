<?php
// CHWOO | Basic shopping bag stored in a PHP session.
// Database orders will be created in a later checkout step.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/bag.php';
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
$bagItems = $chwooBagItems;
$bagCount = $chwooBagCount;
$bagTotal = $chwooBagTotal;
$fromCheckout = ($_GET['from'] ?? '') === 'checkout';
$bagReturnTo = 'php/cart.php' . ($fromCheckout ? '?from=checkout' : '');
$drawerOpen = in_array($_GET['bag'] ?? '', ['open', 'updated'], true);
$bagNotice = $drawerOpen ? '' : $chwooBagMessage;
// Show a flash in the active view only, rather than announcing it twice.
if (!$drawerOpen) { $chwooBagMessage = ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Bag | CHWOO Fashion Studio</title>
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/shop.css">
    <link rel="stylesheet" href="../css/cart.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="<?= chwooEscape($chwooRootUrl) ?>css/bag.css">
</head>
<body class="shop-page bag-page">
    <?php include __DIR__ . '/header.php'; ?>
    <main class="shop-main">
        <div class="breadcrumb"><a href="../index.php">Home</a><span>/</span>Bag</div>
        <p class="shop-eyebrow">CHWOO COLLECTION</p>
        <h1 class="shop-title">Shopping Bag</h1>
        <p class="shop-description"><?= $bagCount ?> item(s)</p>

        <?php if ($bagNotice): ?><p class="chwoo-bag-message" role="status"><?= e($bagNotice) ?></p><?php endif; ?>
        <div class="bag-layout">
            <section class="bag-list" aria-label="Items in your bag">
                <?php if (!$bagItems): ?>
                    <div class="bag-empty">
                        <h2>Your bag is empty.</h2>
                        <p>Find a little something you love.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($bagItems as $item): ?>
                    <article class="bag-item">
                        <a class="bag-item-image" href="product.php?id=<?= $item['product_id'] ?>">
                            <?php if ($item['image_url']): ?>
                                <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>">
                            <?php else: ?>
                                <span>CHWOO</span>
                            <?php endif; ?>
                        </a>
                        <div class="bag-item-details">
                            <h2><a href="product.php?id=<?= $item['product_id'] ?>"><?= e($item['name']) ?></a></h2>
                            <p>Colour: <?= e($item['colour']) ?></p>
                            <p>Size: <?= e($item['size']) ?></p>
                            <p>Unit price: SGD $<?= number_format($item['price'], 2) ?></p>
                            <p>Subtotal: SGD $<?= number_format($item['subtotal'], 2) ?></p>
                            <?php if ($item['availability']): ?><p role="alert"><?= e($item['availability']) ?></p><?php endif; ?>
                            <?php chwooBagControls($item, $bagReturnTo, 'full'); ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
            <aside class="bag-summary">
                <h2>Order Summary</h2>
                <div class="bag-total"><span>Subtotal</span><strong>SGD $<?= number_format($bagTotal, 2) ?></strong></div>
                <div class="bag-total"><span>Delivery</span><span><?= !$bagCount ? 'SGD $0.00' : ($chwooDelivery > 0 ? 'SGD $' . number_format($chwooDelivery, 2) : 'Complimentary') ?></span></div>
                <div class="bag-total bag-estimated-total"><span>Estimated total</span><strong>SGD $<?= number_format($chwooEstimatedTotal, 2) ?></strong></div>
                <p class="bag-delivery-note">Demonstration delivery: SGD $5 below SGD $80; complimentary from SGD $80.</p>
                <a href="<?= e($chwooCheckoutUrl . ($fromCheckout ? '?step=review' : '')) ?>" class="chwoo-bag-primary"><?= $fromCheckout ? 'RETURN TO ORDER REVIEW' : 'CHECKOUT' ?></a>
                <a href="shop.php" class="bag-shop-link">CONTINUE SHOPPING &rarr;</a>
            </aside>
        </div>
    </main>
    <footer>

        <div class="footer-logo">
            CHWOO
        </div>


        <div class="footer-links">

            <a href="shop.php">
                Shop
            </a>

            <a href="about.html">
                About Us
            </a>

            <a href="contact.html">
                Contact
            </a>

            <a href="orders.html">
                Orders
            </a>

        </div>


        <p>
            &copy; 2026 CHWOO Fashion Studio
        </p>

    </footer>

    <?php include __DIR__ . '/bag_drawer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>
