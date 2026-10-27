<?php
// CHWOO | Basic shopping bag stored in a PHP session.
// Database orders will be created in a later checkout step.
session_start();
require_once __DIR__ . '/db.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['chwoo_form_token'])) {
    $_SESSION['chwoo_form_token'] = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['form_token'] ?? '';
    $key = $_POST['item_key'] ?? '';
    if (is_string($token) && hash_equals($_SESSION['chwoo_form_token'], $token)
        && is_string($key) && isset($_SESSION['chwoo_cart'][$key])) {
        unset($_SESSION['chwoo_cart'][$key]);
        header('Location: cart.php', true, 303);
        exit;
    }
}

$bagCount = 0;
$bagItems = [];
$bagTotal = 0.0;

// The session remembers product IDs and sizes. Actual prices come from MySQL.
$stmt = $conn->prepare('SELECT product_id, product_name, price, image_path
                        FROM products WHERE product_id = ? AND is_active = 1');

foreach ($_SESSION['chwoo_cart'] ?? [] as $key => $entry) {
    $productId = (int)$entry['product_id'];
    $size = (string)$entry['size'];
    $quantity = max(1, (int)$entry['quantity']);
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) { continue; }

    $imagePath = ltrim((string)$row['image_path'], '/\\');
    $hasImage = preg_match('~^images/[a-zA-Z0-9_./-]+$~', $imagePath) &&
                !str_contains($imagePath, '..') && is_file(__DIR__ . '/../' . $imagePath);

    $subtotal = (float)$row['price'] * $quantity;
    $bagTotal += $subtotal;
    $bagCount += $quantity;
    $bagItems[] = [
        'key' => $key,
        'product_id' => $productId,
        'name' => $row['product_name'],
        'price' => (float)$row['price'],
        'size' => $size,
        'quantity' => $quantity,
        'subtotal' => $subtotal,
        'image_path' => $hasImage ? '../' . $imagePath : null
    ];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Bag | CHWOO Fashion Studio</title>
    <link rel="stylesheet" href="../css/chwoo.css">
    <link rel="stylesheet" href="../css/shop.css">
    <link rel="stylesheet" href="../css/cart.css">
    <link rel="stylesheet" href="../css/footer.css">
</head>
<body class="shop-page bag-page">
    <div class="announcement-bar">FREE STANDARD COURIER DELIVERY WITH SGD$80 SPENT</div>
    <header class="shop-header">
        <nav class="navbar" aria-label="Main navigation">
            <a class="logo-link" href="../index.php">
                <img class="logo" src="../images/chwoo-logo.png" alt="CHWOO Fashion Studio">
            </a>
            <div class="main-nav">
                <a href="../newin.php">NEW IN</a>
                <a href="shop.php">SHOP</a>
                <a href="../collections.html">COLLECTIONS</a>
                <a href="../lookbook.html">LOOKBOOK</a>
                <a href="../about.html">ABOUT</a>
            </div>
            <div class="nav-tools">
                <span>SG | SGD $</span>
                <a href="../search.html">SEARCH</a>
                <a href="../login.html">ACCOUNT</a>
                <a href="cart.php" aria-current="page">BAG (<?= $bagCount ?>)</a>
            </div>
        </nav>
    </header>
    <main class="shop-main">
        <div class="breadcrumb"><a href="../index.html">Home</a><span>/</span>Bag</div>
        <p class="shop-eyebrow">CHWOO COLLECTION</p>
        <h1 class="shop-title">Your Bag</h1>
        <p class="shop-description"><?= $bagCount ?> item(s)</p>

        <?php if (!$bagItems): ?>
            <div class="bag-empty">
                <h2>Your bag is empty.</h2>
                <p>Find a little something you love.</p>
                <a href="shop.php" class="bag-shop-link">CONTINUE SHOPPING &rarr;</a>
            </div>
        <?php else: ?>
            <div class="bag-layout">
                <section class="bag-list" aria-label="Items in your bag">
                    <?php foreach ($bagItems as $item): ?>
                        <article class="bag-item">
                            <a class="bag-item-image" href="product.php?id=<?= $item['product_id'] ?>">
                                <?php if ($item['image_path']): ?>
                                    <img src="<?= e($item['image_path']) ?>" alt="<?= e($item['name']) ?>">
                                <?php else: ?>
                                    <span>CHWOO</span>
                                <?php endif; ?>
                            </a>
                            <div class="bag-item-details">
                                <h2><a href="product.php?id=<?= $item['product_id'] ?>"><?= e($item['name']) ?></a></h2>
                                <p>Size: <?= e($item['size']) ?></p>
                                <p>Quantity: <?= $item['quantity'] ?></p>
                                <p>SGD $<?= number_format($item['subtotal'], 2) ?></p>
                                <form method="post" action="cart.php">
                                    <input type="hidden" name="form_token" value="<?= e($_SESSION['chwoo_form_token']) ?>">
                                    <input type="hidden" name="item_key" value="<?= e($item['key']) ?>">
                                    <button type="submit" class="bag-remove">Remove</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
                <aside class="bag-summary">
                    <h2>Order Summary</h2>
                    <div class="bag-total"><span>Subtotal</span><strong>SGD $<?= number_format($bagTotal, 2) ?></strong></div>
                    <p>Shipping and checkout will be added in the next stage.</p>
                    <a href="shop.php" class="bag-shop-link">CONTINUE SHOPPING &rarr;</a>
                </aside>
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

</body>
</html>
<?php $conn->close(); ?>
