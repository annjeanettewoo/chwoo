<?php
// CHWOO | Basic shopping bag stored in a PHP session.
// Database orders will be created in a later checkout step.
session_start();
require_once __DIR__ . '/bag.php';
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
$bagItems = $chwooBagItems;
$bagCount = $chwooBagCount;
$bagTotal = $chwooBagTotal;
$drawerOpen = ($_GET['bag'] ?? '') === 'open';
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
    <link rel="stylesheet" href="../css/shop.css">
    <link rel="stylesheet" href="../css/cart.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="<?= chwooEscape($chwooRootUrl) ?>css/bag.css">
</head>
<body class="shop-page bag-page">
    <div class="announcement-bar">FREE STANDARD COURIER DELIVERY WITH SGD$80 SPENT</div>
    <header class="shop-header">
        <nav class="navbar" aria-label="Main navigation">
            <a class="logo-link" href="../index.php">
                <img class="logo" src="../images/chwoo-logo.png" alt="CHWOO Fashion Studio">
            </a>
            <div class="main-nav">
                <a href="newin.php">NEW IN</a>
                <a href="shop.php">SHOP</a>
                <a href="../collections.html">COLLECTIONS</a>
                <a href="../lookbook.html">LOOKBOOK</a>
                <a href="../about.html">ABOUT</a>
            </div>
            <div class="nav-tools">
                <span>SG | SGD $</span>
                <a href="../search.html">SEARCH</a>
                <a href="../login.html">ACCOUNT</a>
                <a data-bag-trigger href="cart.php" aria-current="page">BAG (<?= $bagCount ?>)</a>
            </div>
        </nav>
    </header>
    <main class="shop-main">
        <div class="breadcrumb"><a href="../index.html">Home</a><span>/</span>Bag</div>
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
                            <?php chwooBagControls($item, 'php/cart.php', 'full'); ?>
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
                <a href="<?= e($chwooCheckoutUrl) ?>" class="chwoo-bag-primary">CHECKOUT</a>
                <a href="shop.php" class="bag-shop-link">CONTINUE SHOPPING &rarr;</a>
            </aside>
        </div>
    </main>
    <footer>

        <div class="footer-logo">
            CHWOO
        </div>


        <div class="footer-links">

            <a href="php/shop.php">
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
