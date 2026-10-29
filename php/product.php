<?php
// CHWOO Product Details | IE4727
// Reads product information from MySQL and stores the shopping bag in a PHP session.
session_start();
require_once __DIR__ . '/db.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$productId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$product = null;
if ($productId !== false && $productId !== null) {
    $stmt = $conn->prepare('SELECT product_id, product_name, category, price,
                                   image_path, description, stock_quantity
                            FROM products WHERE product_id = ? AND is_active = 1');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$product) { http_response_code(404); }

if (empty($_SESSION['chwoo_form_token'])) {
    $_SESSION['chwoo_form_token'] = bin2hex(random_bytes(16));
}

// These are demonstration variants, not actual size/colour-specific inventory.
// MySQL currently tracks one stock quantity per product.
$availableSizes = ['XS', 'S', 'M', 'L'];
$availableColours = [
    'Powder Blue' => '#BED1E1',
    'Butter Yellow' => '#FFF3C2',
    'Chocolate Brown' => '#4B2E1F'
];

$error = '';
$success = $_SESSION['chwoo_flash'] ?? '';
unset($_SESSION['chwoo_flash']);
$selectedSize = '';
$selectedColour = 'Powder Blue';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product) {
    $action = $_POST['action'] ?? 'add';
    $token = $_POST['form_token'] ?? '';
    $selectedSize = $_POST['size'] ?? '';
    $selectedColour = $_POST['colour'] ?? 'Powder Blue';

    if (!is_string($token) || !hash_equals($_SESSION['chwoo_form_token'], $token)) {
        $error = 'Please refresh the page and try again.';
    } elseif ($action === 'wishlist') {
        if (!isset($_SESSION['chwoo_wishlist'])) { $_SESSION['chwoo_wishlist'] = []; }
        if (isset($_SESSION['chwoo_wishlist'][$productId])) {
            unset($_SESSION['chwoo_wishlist'][$productId]);
            $_SESSION['chwoo_flash'] = 'Removed from your wishlist.';
        } else {
            $_SESSION['chwoo_wishlist'][$productId] = true;
            $_SESSION['chwoo_flash'] = 'Saved to your wishlist.';
        }
        header('Location: product.php?id=' . (int)$productId, true, 303);
        exit;
    } elseif ($action !== 'add') {
        $error = 'Unknown action.';
    } elseif (!is_string($selectedSize) || !in_array($selectedSize, $availableSizes, true)) {
        $error = 'Please select a size.';
    } elseif (!is_string($selectedColour) || !array_key_exists($selectedColour, $availableColours)) {
        $error = 'Please select a colour.';
    } elseif ((int)$product['stock_quantity'] < 1) {
        $error = 'This piece is currently sold out.';
    } else {
        if (!isset($_SESSION['chwoo_cart'])) { $_SESSION['chwoo_cart'] = []; }
        // Cart entries are identified by product + size + colour.
        $key = $productId . ':' . $selectedSize . ':' . str_replace(' ', '-', $selectedColour);
        $alreadyInBag = 0;
        foreach ($_SESSION['chwoo_cart'] as $item) {
            if ((int)$item['product_id'] === (int)$productId) {
                $alreadyInBag += (int)$item['quantity'];
            }
        }
        if ($alreadyInBag + 1 > (int)$product['stock_quantity']) {
            $error = 'There is not enough stock available.';
        } else {
            $existingQuantity = isset($_SESSION['chwoo_cart'][$key])
                ? (int)$_SESSION['chwoo_cart'][$key]['quantity'] : 0;
            $_SESSION['chwoo_cart'][$key] = [
                'product_id' => (int)$productId,
                'size' => $selectedSize,
                'colour' => $selectedColour,
                'quantity' => $existingQuantity + 1
            ];
            $_SESSION['chwoo_flash'] = 'Added to your bag!';
            header('Location: product.php?id=' . (int)$productId, true, 303);
            exit;
        }
    }
}

$bagCount = 0;
foreach ($_SESSION['chwoo_cart'] ?? [] as $item) {
    $bagCount += (int)$item['quantity'];
}
$inWishlist = $product && isset($_SESSION['chwoo_wishlist'][$productId]);

// Reject unsafe image paths and look for actual files in the parent chwoo/images folder.
$imagePath = $product ? ltrim((string)$product['image_path'], '/\\') : '';
$validImagePath = preg_match('~^images/[a-zA-Z0-9_./-]+$~', $imagePath)
    && !str_contains($imagePath, '..');
$imageExists = $product && $validImagePath && is_file(__DIR__ . '/../' . $imagePath);
$imageUrl = $imageExists ? '../' . $imagePath : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $product ? e($product['product_name']) . ' | CHWOO' : 'Product not found | CHWOO' ?></title>
    <!-- Keep the working shared Shop stylesheet: no edits needed. -->
    <link rel="stylesheet" href="../css/chwoo.css">
    <link rel="stylesheet" href="../css/shop.css">
    <link rel="stylesheet" href="../css/product.css?v=3">
    <link rel="stylesheet" href="../css/footer.css">
</head>
<body class="shop-page product-page">
    <div class="announcement-bar">FREE STANDARD COURIER DELIVERY WITH SGD$80 SPENT</div>
    <header class="shop-header">
        <nav class="navbar" aria-label="Main navigation">
            <a class="logo-link" href="../index.php">
                <img class="logo" src="../images/chwoo-logo.png" alt="CHWOO Fashion Studio">
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
                <a href="../search.html">SEARCH</a>
                <a href="../login.html">ACCOUNT</a>
                <a href="cart.php">BAG (<?= $bagCount ?>)</a>
            </div>
        </nav>
    </header>

    <main class="shop-main product-main">
        <div class="breadcrumb">
            <a href="../index.html">Home</a><span>/</span>
            <a href="shop.php">Shop</a><span>/</span>
            <?= $product ? e($product['product_name']) : 'Product not found' ?>
        </div>

        <?php if (!$product): ?>
            <section class="product-not-found">
                <h1>We couldn't find that piece.</h1>
                <p>The product may have been removed.</p>
                <a href="shop.php">Back to Shop</a>
            </section>
        <?php else: ?>
            <div class="product-layout">
                <!-- Gallery: the current database stores one image per product.
                     Repeated thumbnail previews retain the original mock-up layout. -->
                <section class="product-gallery" aria-label="Product image">
                    <div class="product-large-image">
                        <?php if ($imageExists): ?>
                            <img src="<?= e($imageUrl) ?>" alt="<?= e($product['product_name']) ?>">
                        <?php else: ?>
                            <div class="product-no-image">Product photo coming soon</div>
                        <?php endif; ?>
                    </div>
                    <?php if ($imageExists): ?>
                        <div class="product-thumbnails" aria-label="Product thumbnail previews">
                            <?php for ($i = 0; $i < 3; $i++): ?>
                                <div class="product-thumbnail<?= $i === 0 ? ' selected' : '' ?>">
                                    <img src="<?= e($imageUrl) ?>" alt="<?= e($product['product_name']) ?> preview">
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="product-information" aria-labelledby="product-name">
                    <p class="product-eyebrow">CHWOO COLLECTION</p>
                    <h1 id="product-name"><?= e($product['product_name']) ?></h1>
                    <p class="product-price">SGD $<?= number_format((float)$product['price'], 2) ?></p>

                    <?php if ($success): ?>
                        <div class="product-message success" role="status">
                            <?= e($success) ?> <a href="cart.php">View bag &rarr;</a>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="product-message error" role="alert"><?= e($error) ?></div>
                    <?php endif; ?>

                    <?php if ((int)$product['stock_quantity'] > 0): ?>
                        <form action="product.php?id=<?= (int)$productId ?>" method="post" class="product-form">
                            <input type="hidden" name="form_token" value="<?= e($_SESSION['chwoo_form_token']) ?>">
                            <!-- One item per click; quantity editing can be added to Bag later. -->
                            <div class="product-colour-header">
                                <span>COLOUR</span>
                                <span class="product-colour-value" id="selected-colour-name"><?= e($selectedColour) ?></span>
                            </div>
                            <fieldset class="product-colours">
                                <legend class="visually-hidden">Choose a colour</legend>
                                <?php foreach ($availableColours as $colourName => $colourHex): ?>
                                    <label class="colour-choice" title="<?= e($colourName) ?>">
                                        <input type="radio" name="colour" value="<?= e($colourName) ?>"
                                            <?= $selectedColour === $colourName ? 'checked' : '' ?> required>
                                        <span class="colour-dot" style="--swatch: <?= e($colourHex) ?>"></span>
                                        <span class="visually-hidden"><?= e($colourName) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>

                            <div class="product-size-header">
                                <span>SIZE</span>
                                <a href="#size-and-fit">SIZE GUIDE</a>
                            </div>
                            <fieldset class="product-sizes">
                                <legend class="visually-hidden">Choose a size</legend>
                                <?php foreach ($availableSizes as $size): ?>
                                    <label class="size-choice">
                                        <input type="radio" name="size" value="<?= e($size) ?>"
                                            <?= $selectedSize === $size ? 'checked' : '' ?> required>
                                        <span><?= e($size) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>

                            <div class="product-action-row">
                                <button class="add-to-bag" type="submit" name="action" value="add">ADD TO BAG</button>
                                <button class="wishlist-button<?= $inWishlist ? ' is-saved' : '' ?>"
                                    type="submit" name="action" value="wishlist" formnovalidate
                                    aria-label="<?= $inWishlist ? 'Remove from wishlist' : 'Add to wishlist' ?>"
                                    title="<?= $inWishlist ? 'Remove from wishlist' : 'Save to wishlist' ?>">
                                    <?= $inWishlist ? '&#9829;' : '&#9825;' ?>
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="product-sold-out">SOLD OUT</div>
                    <?php endif; ?>

                    <div class="product-accordions">
                        <details>
                            <summary>PRODUCT DESCRIPTION <span class="plus">+</span></summary>
                            <div class="accordion-content">
                                <?= !empty($product['description'])
                                    ? nl2br(e($product['description']))
                                    : 'More details coming soon.' ?>
                            </div>
                        </details>
                        <details id="size-and-fit">
                            <summary>SIZE &amp; FIT <span class="plus">+</span></summary>
                            <div class="accordion-content">
                                Sizes XS, S, M and L are demonstration options. Stock is currently tracked per product.
                            </div>
                        </details>
                        <details>
                            <summary>SHIPPING &amp; RETURNS <span class="plus">+</span></summary>
                            <div class="accordion-content">
                                Free standard courier delivery with SGD$80 spent. Returns policy to be confirmed.
                            </div>
                        </details>
                    </div>
                </section>
            </div>
        <?php endif; ?>
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

    <script>
    // Update the visible colour name when a swatch is selected.
    document.querySelectorAll('input[name="colour"]').forEach(function(input) {
        input.addEventListener('change', function() {
            document.getElementById('selected-colour-name').textContent = this.value;
        });
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>
