<?php
// CHWOO Shop | MySQL-powered catalogue, original CHWOO layout
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/bag.php';

// Show the number of items currently saved in the session bag.
$bagCount = $chwooBagCount;

// Only known category and sort values can affect the SQL query.
$categories = ['All', 'Tops', 'Bottoms', 'Dresses'];
$category = $_GET['category'] ?? 'All';
if (!in_array($category, $categories, true)) {
    $category = 'All';
}

$sortOptions = [
    'featured'   => 'p.product_id ASC',
    'price_asc'  => 'c.price ASC, p.product_id ASC',
    'price_desc' => 'c.price DESC, p.product_id ASC',
    'name_asc'   => 'p.product_name ASC, p.product_id ASC'
];
$sort = $_GET['sort'] ?? 'featured';
if (!is_string($sort) || !array_key_exists($sort, $sortOptions)) {
    $sort = 'featured';
}

// Display the default colour; availability includes inventory across all colours and sizes.
$sql = 'SELECT p.product_id, p.product_name, p.category, c.price, c.image_path,
               COALESCE(SUM(v.stock_quantity), 0) AS stock_quantity
        FROM products AS p
        JOIN product_colours AS c ON c.product_id = p.product_id AND c.is_default = 1
        LEFT JOIN product_colours AS inventory_colour ON inventory_colour.product_id = p.product_id
        LEFT JOIN product_variants AS v ON v.colour_id = inventory_colour.colour_id
        WHERE p.is_active = 1';

if ($category !== 'All') {
    $sql .= ' AND p.category = ?';
}
$sql .= ' GROUP BY p.product_id, p.product_name, p.category, c.colour_id, c.price, c.image_path';
$sql .= ' ORDER BY ' . $sortOptions[$sort];

$stmt = $conn->prepare($sql);
if ($category !== 'All') {
    $stmt->bind_param('s', $category);
}
$stmt->execute();
$products = $stmt->get_result();

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function shopUrl($category, $sort) {
    return 'shop.php?' . http_build_query(['category' => $category, 'sort' => $sort]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop | CHWOO Fashion Studio</title>
    <!-- Shared CHWOO styling, followed by Shop-specific styling. -->
    <link rel="stylesheet" href="../css/shop.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="<?= chwooEscape($chwooRootUrl) ?>css/bag.css">
</head>
<body class="shop-page">

    <?php include __DIR__ . '/header.php'; ?>

    <main class="shop-main">
        <div class="breadcrumb">
            <a href="../index.php">Home</a><span>/</span>Shop
        </div>

        <section aria-label="Shop introduction">
            <p class="shop-eyebrow">CHWOO COLLECTION</p>
            <h1 class="shop-title">Shop</h1>
            <p class="shop-description">Little pieces for everyday dressing.</p>
        </section>

        <div class="shop-toolbar">
            <nav class="category-tabs" aria-label="Product categories">
                <?php foreach ($categories as $tab): ?>
                    <a href="<?= e(shopUrl($tab, $sort)) ?>"
                       class="<?= $category === $tab ? 'active' : '' ?>"
                       <?= $category === $tab ? 'aria-current="page"' : '' ?>>
                        <?= e(strtoupper($tab)) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <form class="sort-form" method="get" action="shop.php">
                <input type="hidden" name="category" value="<?= e($category) ?>">
                <label for="sort">SORT:</label>
                <select id="sort" name="sort" onchange="this.form.submit()">
                    <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Featured</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                    <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A to Z</option>
                </select>
                <noscript><button type="submit">Apply</button></noscript>
            </form>
        </div>

        <?php if ($products->num_rows > 0): ?>
            <section class="shop-grid" aria-label="CHWOO products">
                <?php while ($product = $products->fetch_assoc()): ?>
                    <?php
                    // DB stores paths relative to the chwoo root, e.g. images/newin1.jpg.
                    $imagePath = ltrim((string)$product['image_path'], '/\\');
                    $safePath = preg_match('~^images/[a-zA-Z0-9_./-]+$~', $imagePath)
                                && strpos($imagePath, '..') === false;
                    $imageExists = $safePath && is_file(__DIR__ . '/../' . $imagePath);
                    ?>
                    <article class="shop-product">
                        <a class="shop-product-link" href="product.php?id=<?= (int)$product['product_id'] ?>"
                           aria-label="View <?= e($product['product_name']) ?> details">
                            <div class="shop-image">
                                <?php if ($imageExists): ?>
                                    <img src="<?= e('../' . $imagePath) ?>"
                                         alt="<?= e($product['product_name']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="shop-image-placeholder">Product image coming soon</div>
                                <?php endif; ?>
                            </div>
                            <h2 class="shop-product-name"><?= e($product['product_name']) ?></h2>
                        </a>
                        <p class="shop-product-price">SGD $<?= number_format((float)$product['price'], 2) ?></p>
                        <?php if ((int)$product['stock_quantity'] === 0): ?>
                            <p class="shop-stock">Sold out</p>
                        <?php endif; ?>
                    </article>
                <?php endwhile; ?>
            </section>
        <?php else: ?>
            <p class="shop-empty">No products found in this category.</p>
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
<?php
$stmt->close();
$conn->close();
?>
