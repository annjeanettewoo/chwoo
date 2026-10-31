<?php
// Included by task07c.php before other fixtures; never mutate an installed store.
if (PHP_SAPI !== 'cli' || !isset($testDb, $conn, $base)
    || !preg_match('/^chwoo_task07c_test_[a-f0-9]{12}$/D', $testDb)
    || $conn->query('SELECT DATABASE()')->fetch_row()[0] !== $testDb) {
    http_response_code(404); exit;
}
$catalogueStart = $checks;
$catalogueBrowser = browser();
function catalogueDom($html) {
    $document = new DOMDocument();
    @$document->loadHTML($html);
    return new DOMXPath($document);
}
function catalogueIds($html, $shop = false) {
    $xpath = catalogueDom($html);
    $ids = [];
    $cards = $shop ? 'shop-product' : 'product-card';
    foreach ($xpath->query('//article[@class="' . $cards . '"]/a') as $link) {
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
        $ids[] = (int)$query['id'];
    }
    return $ids;
}
function newestCatalogueIds() {
    global $conn;
    return array_map('intval', array_column($conn->query('SELECT p.product_id FROM products p
        JOIN product_colours c ON c.product_id=p.product_id AND c.is_default=1
        WHERE p.is_active=1 ORDER BY p.product_id DESC LIMIT 8')->fetch_all(MYSQLI_ASSOC), 'product_id'));
}
$expectedNew = newestCatalogueIds();
check(count($expectedNew) === 8, 'Seed has at least eight eligible New In products');
foreach (['', '?page=2', '?page=99999', '?page=-1', '?page=bad', '?page[]=2'] as $query) {
    $page = request($catalogueBrowser, 'php/newin.php' . $query);
    $xpath = catalogueDom($page['html']);
    check($page['status'] === 200 && catalogueIds($page['html']) === $expectedNew
        && count(array_unique(catalogueIds($page['html']))) === 8,
        'New In returns the same eight distinct newest active products: ' . ($query ?: 'no query'));
    check($xpath->query('//nav[@aria-label="New In pages"] | //a[contains(@href,"page=")] | //link[contains(@href,"newin-pagination")]')->length === 0,
        'New In has no pagination controls or obsolete stylesheet: ' . ($query ?: 'no query'));
    check($xpath->query('//a[contains(@class,"view-all") and @href="/chwoo/php/shop.php"]')->length === 1,
        'Shop All Pieces links to the complete catalogue');
}
$home = request($catalogueBrowser, 'index.php');
check(catalogueIds($home['html']) === array_slice($expectedNew, 0, 4), 'Homepage previews the first four New In products');
$homeDom = catalogueDom($home['html']);
check($homeDom->query('//a[(@class="shop-button" or @class="view-all") and @href="php/newin.php"]')->length === 2,
    'Homepage New In buttons lead to New In');
check(request($catalogueBrowser, 'php/collections.php')['status'] === 404,
    'Removed Collections page returns HTTP 404');
check(!is_file(__DIR__ . '/../css/collections.css'), 'Collections-only stylesheet is removed');
foreach (array_merge(glob(__DIR__ . '/../php/*.php'), glob(__DIR__ . '/../*.php'), glob(__DIR__ . '/../*.html')) as $source) {
    check(!preg_match('~(?:href|src)=["\'][^"\']*(?:collections\.(?:php|html|css))~i', file_get_contents($source)),
        'No removed Collections destination in customer source: ' . basename($source));
}
$sortOrders = ['featured'=>'p.product_id ASC', 'price_asc'=>'c.price ASC,p.product_id ASC',
    'price_desc'=>'c.price DESC,p.product_id ASC', 'name_asc'=>'p.product_name ASC,p.product_id ASC'];
foreach (['All', 'Tops', 'Bottoms', 'Dresses'] as $category) {
    foreach ($sortOrders as $sort => $order) {
        $sql = 'SELECT p.product_id FROM products p JOIN product_colours c
            ON c.product_id=p.product_id AND c.is_default=1 WHERE p.is_active=1';
        if ($category !== 'All') { $sql .= " AND p.category='" . $category . "'"; }
        $expected = array_map('intval', array_column($conn->query($sql . ' ORDER BY ' . $order)->fetch_all(MYSQLI_ASSOC), 'product_id'));
        $page = request($catalogueBrowser, 'php/shop.php?' . http_build_query(['category'=>$category, 'sort'=>$sort]));
        check($page['status'] === 200 && catalogueIds($page['html'], true) === $expected,
            'Shop shows all eligible products in correct order: ' . $category . '/' . $sort);
        if ($category === 'All' && $sort === 'featured') {
            check(count($expected) === 13, 'Shop All retains all thirteen seed products, beyond the New In limit');
        }
    }
}
foreach (['?category[]=Tops&sort[]=price_asc', '?category=invalid&sort=invalid'] as $query) {
    $page = request($catalogueBrowser, 'php/shop.php' . $query);
    check($page['status'] === 200 && count(catalogueIds($page['html'], true)) === 13,
        'Shop handles malformed or unknown filters using its full catalogue');
}
foreach (['php/shop.php'=>true, 'php/newin.php'=>false] as $path=>$isShop) {
    $page = request($catalogueBrowser, $path);
    $xpath = catalogueDom($page['html']);
    foreach ($xpath->query('//article/a') as $link) {
        $href = $link->getAttribute('href');
        $productPath = 'php/product.php?' . parse_url($href, PHP_URL_QUERY);
        $detail = request($catalogueBrowser, $productPath);
        parse_str(parse_url($href, PHP_URL_QUERY), $productQuery);
        $id = (int)$productQuery['id'];
        $row = $conn->query('SELECT p.product_name,c.image_path FROM products p JOIN product_colours c
            ON c.product_id=p.product_id AND c.is_default=1 WHERE p.product_id=' . $id)->fetch_assoc();
        check($detail['status'] === 200 && str_contains($detail['html'], htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8')),
            $path . ': card links to correct active product ' . $id);
        $image = $xpath->query('.//img', $link)->item(0);
        check($image && str_ends_with($image->getAttribute('src'), $row['image_path']),
            $path . ': card uses default-colour image for product ' . $id);
    }
}
// Mutate only the guarded disposable database. Restore seed fields even on failure.
$activeBefore = $conn->query('SELECT product_id,is_active FROM products')->fetch_all(MYSQLI_ASSOC);
$photoBefore = $conn->query('SELECT colour_id,image_path FROM product_colours')->fetch_all(MYSQLI_ASSOC);
try {
    foreach ([8, 3, 0] as $remaining) {
        $keep = array_column(array_slice($activeBefore, 0, $remaining), 'product_id');
        $conn->query('UPDATE products SET is_active=' . ($keep ? 'IF(product_id IN (' . implode(',', $keep) . '),1,0)' : '0'));
        $page = request($catalogueBrowser, 'php/newin.php?page=2');
        check($page['status'] === 200 && catalogueIds($page['html']) === newestCatalogueIds()
            && count(catalogueIds($page['html'])) === $remaining,
            'New In handles ' . $remaining . ' active products without exposing inactive products');
        $shop = request($catalogueBrowser, 'php/shop.php');
        check($shop['status'] === 200 && count(catalogueIds($shop['html'], true)) === $remaining,
            'Shop retains entire active selection of ' . $remaining . ' products');
        if ($remaining === 0) {
            check(str_contains($page['html'], 'New pieces are on their way.')
                && str_contains($shop['html'], 'No products found in this category.'), 'Both empty catalogues have useful feedback');
        }
    }
    $conn->query('UPDATE products SET is_active=1');
    $conn->query("UPDATE product_colours SET image_path=CONCAT('images/task09a-missing-',colour_id,'.jpg') WHERE is_default=1");
    foreach (['php/newin.php'=>'product-photo-placeholder', 'php/shop.php'=>'shop-image-placeholder'] as $path=>$class) {
        $page = request($catalogueBrowser, $path); $xpath = catalogueDom($page['html']);
        check($page['status'] === 200 && $xpath->query('//div[@class="' . $class . '"]')->length === ($path === 'php/newin.php' ? 8 : 13)
            && $xpath->query('//article//img')->length === 0, $path . ': missing images use labelled placeholders');
    }
} finally {
    foreach ($activeBefore as $row) {
        $conn->query('UPDATE products SET is_active=' . (int)$row['is_active'] . ' WHERE product_id=' . (int)$row['product_id']);
    }
    $restore = $conn->prepare('UPDATE product_colours SET image_path=? WHERE colour_id=?');
    foreach ($photoBefore as $row) { $restore->bind_param('si', $row['image_path'], $row['colour_id']); $restore->execute(); }
    $restore->close();
}
echo ($checks - $catalogueStart) . " Task 09A catalogue checks passed.\n";
