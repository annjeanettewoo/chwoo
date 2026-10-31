<?php
// CHWOO Product Details | IE4727
// Reads product information from MySQL and stores the shopping bag in a PHP session.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/bag.php';
require_once __DIR__ . '/wishlist.php';
$wishlistIds = chwooWishlistIds($conn, $wishlistError);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$productId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$product = null;
$colours = [];
$availableSizes = ['XS', 'S', 'M', 'L'];
if ($productId !== false && $productId !== null) {
    $stmt = $conn->prepare('SELECT product_id, product_name, description, fit_information
                            FROM products WHERE product_id = ? AND is_active = 1');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$product) {
    http_response_code(404);
} else {
    $stmt = $conn->prepare('SELECT c.colour_id, c.colour_name, c.image_path, c.price, c.swatch_hex,
                                   v.variant_id, v.size_code, v.stock_quantity
                            FROM product_colours AS c
                            LEFT JOIN product_variants AS v ON v.colour_id = c.colour_id
                            WHERE c.product_id = ? ORDER BY c.is_default DESC, c.colour_id');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $colourId = (int)$row['colour_id'];
        if (!isset($colours[$colourId])) {
            $imagePath = (string)$row['image_path'];
            $hasImage = preg_match('~^images/[a-zA-Z0-9_./-]+$~', $imagePath)
                && !str_contains($imagePath, '..') && is_file(__DIR__ . '/../' . $imagePath);
            $colours[$colourId] = [
                'name' => $row['colour_name'] !== '' ? $row['colour_name'] : 'Colour unspecified',
                'price' => $row['price'],
                'swatch_hex' => preg_match('/^#[0-9A-Fa-f]{6}$/D', $row['swatch_hex'] ?? '') ? $row['swatch_hex'] : '#ddd3ca',
                'variants' => [],
                'image_url' => $hasImage ? '../' . $imagePath : '',
                'stock' => array_fill_keys($availableSizes, 0)
            ];
        }
        if (in_array($row['size_code'], $availableSizes, true)) {
            $colours[$colourId]['variants'][$row['size_code']] = (int)$row['variant_id'];
            $inBag = $_SESSION['chwoo_cart'][(int)$row['variant_id']]['quantity'] ?? 0;
            $colours[$colourId]['stock'][$row['size_code']] = max(0, (int)$row['stock_quantity'] - $inBag);
        }
    }
    $stmt->close();
}
$sizeGuidance = [];
$stmt = $conn->prepare('SELECT size_code, guidance FROM size_guide ORDER BY sort_order');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if (!empty($row['guidance'])) { $sizeGuidance[] = $row; }
}
$stmt->close();
$error = '';
$success = $_SESSION['chwoo_flash'] ?? '';
unset($_SESSION['chwoo_flash']);
$selectedSize = '';
$selectedColourId = array_key_first($colours);
$requestedColourId = filter_var($_GET['colour'] ?? null, FILTER_VALIDATE_INT);
if (isset($colours[$requestedColourId])) { $selectedColourId = $requestedColourId; }
$requestedSize = $_GET['size'] ?? '';
if (is_string($requestedSize) && isset($colours[$selectedColourId]['variants'][$requestedSize])) {
    $selectedSize = $requestedSize;
}
$sizeError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product) {
    $action = $_POST['action'] ?? 'add';
    $token = $_POST['form_token'] ?? '';
    $submittedProductId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
    $submittedColourId = filter_var($_POST['colour'] ?? null, FILTER_VALIDATE_INT);
    $selectedSize = is_string($_POST['size'] ?? null) ? $_POST['size'] : '';
    if (isset($colours[$submittedColourId])) { $selectedColourId = $submittedColourId; }
    if (!is_string($token) || !hash_equals($_SESSION['chwoo_form_token'], $token)) {
        $error = 'Please refresh the page and try again.';
    } elseif ($submittedProductId !== $productId) {
        $error = 'Invalid product selection.';
    } elseif ($action !== 'add') {
        $error = 'Unknown action.';
    } elseif (!in_array($selectedSize, $availableSizes, true)) {
        $error = 'Please choose a size.';
        $sizeError = true;
    } elseif (!$submittedColourId || !isset($colours[$submittedColourId])) {
        $error = 'Please select a valid colour for this product.';
    } else {
        // Only the parent has an active flag in this schema.
        $stmt = $conn->prepare('SELECT v.variant_id, v.stock_quantity
                                FROM product_variants AS v
                                JOIN product_colours AS c ON c.colour_id = v.colour_id
                                JOIN products AS p ON p.product_id = c.product_id
                                WHERE p.product_id = ? AND p.is_active = 1
                                  AND c.colour_id = ? AND v.size_code = ?');
        $stmt->bind_param('iis', $productId, $submittedColourId, $selectedSize);
        $stmt->execute();
        $variant = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$variant) {
            $error = 'This colour and size combination is unavailable.';
        } else {
            $variantId = (int)$variant['variant_id'];
            $existingQuantity = $_SESSION['chwoo_cart'][$variantId]['quantity'] ?? 0;
            if ((int)$variant['stock_quantity'] < 1) {
                $error = 'Size ' . $selectedSize . ' is unavailable in ' . $colours[$submittedColourId]['name'] . '.';
            } elseif ($existingQuantity >= (int)$variant['stock_quantity']) {
                $error = 'Size ' . $selectedSize . ' is unavailable in ' . $colours[$submittedColourId]['name'] . '.';
            } else {
                $_SESSION['chwoo_cart'][$variantId] = [
                    'variant_id' => $variantId,
                    'quantity' => $existingQuantity + 1
                ];
                $_SESSION['chwoo_flash'] = 'Added to your bag!';
                $query = http_build_query(['id' => $productId, 'colour' => $submittedColourId, 'size' => $selectedSize, 'bag' => 'open']);
                header('Location: product.php?' . $query, true, 303);
                exit;
            }
        }
    }
}
$selectedColour = $colours[$selectedColourId] ?? null;
$bagCount = $chwooBagCount;
$bagNotice = $chwooBagMessage;
if ($product && $selectedColour) {
    $chwooReturnTo = 'php/product.php?' . http_build_query(['id' => $productId, 'colour' => $selectedColourId, 'size' => $selectedSize]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $product ? e($product['product_name']) . ' | CHWOO' : 'Product not found | CHWOO' ?></title>
    <!-- Keep the working shared Shop stylesheet: no edits needed. -->
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/shop.css">
    <link rel="stylesheet" href="../css/product.css?v=3">
    <link rel="stylesheet" href="../css/wishlist.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="<?= chwooEscape($chwooRootUrl) ?>css/bag.css">
</head>
<body class="shop-page product-page">
    <?php include __DIR__ . '/header.php'; ?>
    <?php chwooWishlistNotice($wishlistError); ?>

    <main class="shop-main product-main">
        <div class="breadcrumb">
            <a href="../index.php">Home</a><span>/</span>
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
                <section class="product-gallery" aria-label="Product image">
                    <?php foreach ($colours as $colourId => $colour): ?>
                        <div data-colour-gallery="<?= $colourId ?>" <?= $colourId !== $selectedColourId ? 'hidden' : '' ?>>
                            <div class="product-large-image">
                                <?php if ($colour['image_url']): ?>
                                    <img src="<?= e($colour['image_url']) ?>" alt="<?= e($product['product_name'] . ' — ' . $colour['name']) ?>">
                                <?php else: ?>
                                    <div class="product-no-image">Product photo coming soon</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$colours): ?>
                        <div class="product-large-image"><div class="product-no-image">Product photo coming soon</div></div>
                    <?php endif; ?>
                </section>

                <section class="product-information" aria-labelledby="product-name">
                    <p class="product-eyebrow">CHWOO COLLECTION</p>
                    <h1 id="product-name"><?= e($product['product_name']) ?></h1>
                    <p class="product-price" id="selected-price" aria-live="polite"><?= $selectedColour ? 'SGD $' . number_format((float)$selectedColour['price'], 2) : 'Currently unavailable' ?></p>

                    <?php if ($bagNotice): ?>
                        <div class="product-message" role="status"><?= e($bagNotice) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="product-message success" role="status" data-auto-dismiss>
                            <?= e($success) ?> <a href="cart.php">View bag &rarr;</a>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="product-message error" role="alert"><?= e($error) ?></div>
                    <?php endif; ?>

                    <?php if ($colours): ?>
                        <form action="product.php?id=<?= (int)$productId ?>" method="post" class="product-form">
                            <input type="hidden" name="form_token" value="<?= e($_SESSION['chwoo_form_token']) ?>">
                            <input type="hidden" name="product_id" value="<?= (int)$productId ?>">
                            <input type="hidden" name="return_to" value="<?= e($chwooReturnTo) ?>">
                            <div class="product-colour-header">
                                <span>COLOUR</span>
                                <span class="product-colour-value" id="selected-colour-name"><?= e($selectedColour['name']) ?></span>
                            </div>
                            <fieldset class="product-colours">
                                <legend class="visually-hidden">Choose a colour</legend>
                                <?php foreach ($colours as $colourId => $colour): ?>
                                    <label class="colour-choice" title="<?= e($colour['name']) ?>">
                                        <input type="radio" name="colour" value="<?= $colourId ?>"
                                            <?= $colourId === $selectedColourId ? 'checked' : '' ?> required
                                            aria-label="<?= e($colour['name']) ?>"
                                            data-name="<?= e($colour['name']) ?>"
                                            data-price="SGD $<?= number_format((float)$colour['price'], 2) ?>"
                                            <?php foreach ($availableSizes as $size): ?>
                                                data-stock-<?= strtolower($size) ?>="<?= $colour['stock'][$size] ?>"
                                            <?php endforeach; ?>>
                                        <span class="colour-dot" style="--swatch: <?= e($colour['swatch_hex']) ?>"></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>

                            <div class="product-size-header">
                                <span>SIZE</span>
                                <a href="#size-and-fit">SIZE GUIDE</a>
                            </div>
                            <fieldset class="product-sizes<?= $sizeError ? ' is-invalid' : '' ?>" aria-describedby="stock-message" <?= $sizeError ? 'aria-invalid="true"' : '' ?>>
                                <legend class="visually-hidden">Choose a size</legend>
                                <?php foreach ($availableSizes as $size): ?>
                                    <label class="size-choice<?= $selectedColour['stock'][$size] === 0 ? ' is-unavailable' : '' ?>">
                                        <input type="radio" name="size" value="<?= e($size) ?>"
                                            <?= $selectedSize === $size ? 'checked' : '' ?> required>
                                        <span><?= e($size) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>

                            <?php $selectedQuantity = $selectedColour['stock'][$selectedSize] ?? 0;
                            $selectionAvailable = $selectedSize !== '' && $selectedQuantity > 0; ?>
                            <p id="stock-message" class="product-stock-message<?= $sizeError ? ' error' : ($selectedSize ? ($selectionAvailable ? ' success' : ' error') : '') ?>" role="status" aria-live="polite"><?= !$selectedSize ? 'Please choose a size.' : ($selectionAvailable ? 'Size ' . e($selectedSize) . ' · ' . $selectedQuantity . ' available' : 'Size ' . e($selectedSize) . ' is unavailable') ?></p>
                            <div class="product-action-row">
                                <button class="add-to-bag" type="submit" name="action" value="add" <?= $selectedSize && !$selectionAvailable ? 'disabled' : '' ?>>ADD TO BAG</button>
                                <?php chwooWishlistButton($product, $wishlistIds); ?>
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
                                <?= !empty($product['fit_information']) ? nl2br(e($product['fit_information'])) : 'Size and fit information is not yet available.' ?>
                                <?php foreach ($sizeGuidance as $guide): ?>
                                    <p><?= e($guide['size_code']) ?>: <?= nl2br(e($guide['guidance'])) ?></p>
                                <?php endforeach; ?>
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

            <a href="shop.php">
                Shop
            </a>

            <!-- ABOUT is on hold until a page is available. -->

            <!-- Contact is on hold until a page is available. -->

            <a href="account.php?section=orders">
                Orders
            </a>

        </div>


        <p>
            &copy; 2026 CHWOO Fashion Studio
        </p>

    </footer>

    <script src="../js/product-selection.js" defer></script>
    <?php include __DIR__ . '/bag_drawer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>
