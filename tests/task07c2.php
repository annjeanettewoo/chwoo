<?php
// Runs only inside task07c.php's disposable database/server and isolated sessions.
if (PHP_SAPI !== 'cli' || !isset($testDb, $buyer, $paymentPage)) { http_response_code(404); exit; }
function pageDom($html) {
    $document = new DOMDocument(); @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
    return new DOMXPath($document);
}
function stockMessage($response) {
    return trim(pageDom($response['html'])->query('//*[@id="stock-message"]')->item(0)->textContent);
}
foreach (['php/outfit.php','js/outfitBuilder.js','php/outfit_action.php','js/product-selection.js'] as $file) {
    check(!preg_match('/json_encode|JSON\s*\.\s*parse|application\/json|\bfetch\s*\(|XMLHttpRequest|\bajax\b|jquery/i',
        file_get_contents(__DIR__ . '/../' . $file)), 'Base runtime has no excluded data transfer or request APIs: ' . $file);
}
$stockBrowser = browser();
$originalVariants = $conn->query('SELECT variant_id,stock_quantity FROM product_variants WHERE variant_id BETWEEN 29 AND 36')->fetch_all(MYSQLI_ASSOC);
$originalProduct = $conn->query('SELECT product_name FROM products WHERE product_id=8')->fetch_assoc()['product_name'];
$originalColour = $conn->query('SELECT colour_name FROM product_colours WHERE colour_id=8')->fetch_assoc()['colour_name'];
try {
    $conn->query('UPDATE product_variants SET stock_quantity=CASE variant_id WHEN 29 THEN 5 WHEN 30 THEN 2 WHEN 31 THEN 1 WHEN 32 THEN 0
        WHEN 33 THEN 4 WHEN 34 THEN 0 WHEN 35 THEN 1 ELSE 2 END WHERE variant_id BETWEEN 29 AND 36');
    check(stockMessage(request($stockBrowser, 'php/product.php?id=8&colour=8')) === 'Please choose a size.', 'PHP preserves the no-size prompt');
    foreach (['XS'=>5,'S'=>2,'M'=>1,'L'=>0] as $size=>$quantity) {
        $page = request($stockBrowser, 'php/product.php?id=8&colour=8&size=' . $size);
        check(stockMessage($page) === 'Size ' . $size . ($quantity ? ' · ' . $quantity . ' available' : ' is unavailable')
            && pageDom($page['html'])->query('//button[contains(@class,"add-to-bag") and @disabled]')->length === ($quantity ? 0 : 1),
            'PHP selected ' . $size . ' shows exact remaining quantity and correct Add state');
    }
    check(stockMessage(request($stockBrowser, 'php/product.php?id=8&colour=9&size=S')) === 'Size S is unavailable'
        && stockMessage(request($stockBrowser, 'php/product.php?id=8&colour=9&size=XS')) === 'Size XS · 4 available',
        'PHP query-selected colour uses its own size quantities');
    $add = ['form_token'=>csrf($stockBrowser),'action'=>'add','product_id'=>'8','colour'=>'8','size'=>'XS'];
    request($stockBrowser, 'php/product.php?id=8', $add);
    check(stockMessage(request($stockBrowser, 'php/product.php?id=8&colour=8&size=XS')) === 'Size XS · 4 available'
        && stockMessage(request($stockBrowser, 'php/product.php?id=8&colour=9&size=XS')) === 'Size XS · 4 available',
        'Product quantity subtracts this exact bag variant without affecting another colour');
    $before = bag($stockBrowser); $invalid = $add; $invalid['form_token']='invalid';
    $page = request($stockBrowser, 'php/product.php?id=8', $invalid);
    check(bag($stockBrowser) === $before && stockMessage($page) === 'Size XS · 4 available'
        && str_contains($page['html'], 'Please refresh the page and try again.'), 'Failed validation preserves bag and selected snapshot message');
    $missing = $add; unset($missing['size']); $page = request($stockBrowser, 'php/product.php?id=8', $missing);
    check(bag($stockBrowser) === $before && stockMessage($page) === 'Please choose a size.'
        && pageDom($page['html'])->query('//fieldset[contains(@class,"is-invalid")]')->length === 1, 'PHP missing-size error keeps its existing inline treatment');
    $unsafeName = 'Ruffle "Top" <script id="injected">x</script> & Test';
    $unsafeColour = 'Midnight "<&>" \'Test\'';
    $statement = $conn->prepare('UPDATE products SET product_name=? WHERE product_id=8');
    $statement->bind_param('s', $unsafeName); $statement->execute(); $statement->close();
    $statement = $conn->prepare('UPDATE product_colours SET colour_name=? WHERE colour_id=8');
    $statement->bind_param('s', $unsafeColour); $statement->execute(); $statement->close();
    $page = request($stockBrowser, 'php/outfit.php'); $xpath = pageDom($page['html']);
    check($xpath->query('//*[@id="outfit-catalogue" and @hidden]')->length === 1
        && $xpath->query('//*[@id="outfit-catalogue"]//*[@data-product-id]')->length === 13
        && $xpath->query('//*[@id="outfit-catalogue"]//*[@data-colour-id]')->length === 16
        && $xpath->query('//*[@id="outfit-catalogue"]//*[@data-variant-id]')->length === 64,
        'One hidden HTML catalogue contains the real 13 products, 16 colours and 64 variants once');
    check($xpath->query('//*[@data-product-id="8"]')->item(0)->getAttribute('data-name') === $unsafeName
        && $xpath->query('//*[@data-colour-id="8"]')->item(0)->getAttribute('data-name') === $unsafeColour
        && $xpath->query('//script[@id="injected"]')->length === 0,
        'Attribute values round-trip quotes, angle brackets and ampersands without creating executable markup');
    $actual = [];
    foreach ($xpath->query('//*[@id="outfit-catalogue"]//*[@data-variant-id]') as $node) {
        $colour = $node->parentNode; $product = $colour->parentNode;
        $actual[(int)$node->getAttribute('data-variant-id')] = [
            (int)$product->getAttribute('data-product-id'), $product->getAttribute('data-name'), $product->getAttribute('data-category'),
            (int)$colour->getAttribute('data-colour-id'), $colour->getAttribute('data-name'), $colour->getAttribute('data-swatch'),
            $colour->getAttribute('data-image'), (int)$colour->getAttribute('data-price-cents'),
            $node->getAttribute('data-size'), (int)$node->getAttribute('data-available')];
    }
    $expected = [];
    $rows = $conn->query("SELECT p.product_id,p.product_name,p.category,c.colour_id,c.colour_name,c.swatch_hex,c.image_path,
        CAST(c.price*100 AS UNSIGNED) cents,v.variant_id,v.size_code,v.stock_quantity FROM products p
        JOIN product_colours c ON c.product_id=p.product_id JOIN product_variants v ON v.colour_id=c.colour_id
        WHERE p.is_active=1 AND p.category IN ('Tops','Bottoms','Dresses')");
    while ($row = $rows->fetch_assoc()) {
        $id = (int)$row['variant_id'];
        $expected[$id] = [(int)$row['product_id'],$row['product_name'],$row['category'],(int)$row['colour_id'],$row['colour_name'],
            $row['swatch_hex'] ?? '', '/chwoo/' . $row['image_path'],(int)$row['cents'],$row['size_code'],
            max(0,(int)$row['stock_quantity'] - (bag($stockBrowser)[$id]['quantity'] ?? 0))];
    }
    ksort($actual); ksort($expected);
    check($actual === $expected, 'Every HTML catalogue identity, name, swatch, image, price, size and remaining quantity matches the disposable database and bag');
} finally {
    foreach ($originalVariants as $row) { $conn->query('UPDATE product_variants SET stock_quantity=' . (int)$row['stock_quantity'] . ' WHERE variant_id=' . (int)$row['variant_id']); }
    $statement = $conn->prepare('UPDATE products SET product_name=? WHERE product_id=8');
    $statement->bind_param('s', $originalProduct); $statement->execute(); $statement->close();
    $statement = $conn->prepare('UPDATE product_colours SET colour_name=? WHERE colour_id=8');
    $statement->bind_param('s', $originalColour); $statement->execute(); $statement->close();
}
// Resolve every customer-facing footer target from its actual page directory.
$footerPages = array_keys($navigationPages);
$footerPages[] = 'shop.html'; $footerPages[] = 'product.html';
foreach ($footerPages as $path) {
    $page = request($buyer, $path); $xpath = pageDom($page['html']);
    check($xpath->query('//footer//a[contains(@href,"contact.html") or contains(@href,"orders.html") or contains(@href,"about.html")]')->length === 0,
        'No obsolete Contact, Orders or About footer link on ' . $path);
    foreach ($xpath->query('//footer//a') as $link) {
        $href = $link->getAttribute('href');
        $target = str_starts_with($href, '/chwoo/') ? substr($href, 7) : (str_starts_with($path, 'php/') ? 'php/' : '') . $href;
        check(request($buyer, $target)['status'] === 200, 'Footer target resolves from ' . $path . ': ' . $href);
        if (trim($link->textContent) === 'Orders') { check($target === 'php/account.php?section=orders', 'Orders footer points to account history on ' . $path); }
    }
}
$xpath = pageDom($paymentPage['html']);
check($xpath->query('//footer//a[@href="account.php?section=orders"]')->length === 1, 'Existing payment footer already targets order history');
foreach ($xpath->query('//footer//a') as $link) { check(request($buyer, 'php/' . $link->getAttribute('href'))['status'] === 200, 'Existing payment footer target resolves'); }
$ordersGuest = browser();
request($ordersGuest, 'php/product.php?id=8', ['form_token'=>csrf($ordersGuest),'action'=>'add','product_id'=>'8','colour'=>'8','size'=>'XS']);
$before = bag($ordersGuest); $ordersCsrf = csrf($ordersGuest);
$response = request($ordersGuest, 'php/account.php?section=orders');
check($response['status'] === 303 && str_ends_with($response['headers']['location'] ?? '', '/php/login.php'), 'Guest order history requires sign-in');
$login = ['form_token'=>$ordersCsrf,'action'=>'login','email'=>'task07c@example.com','password'=>'WrongTestPassword'];
request($ordersGuest, 'php/auth_action.php', $login);
check(!empty(sessionData($ordersGuest)['chwoo_orders_login']) && bag($ordersGuest) === $before, 'Failed sign-in retains Orders destination and bag');
$login['password']='Fictional07c!'; $response = request($ordersGuest, 'php/auth_action.php', $login);
check(str_ends_with($response['headers']['location'] ?? '', '/php/account.php?section=orders')
    && !isset(sessionData($ordersGuest)['chwoo_orders_login']) && bag($ordersGuest) === $before && csrf($ordersGuest) === $ordersCsrf,
    'Successful sign-in resumes Orders once while preserving bag and CSRF');
check(request($ordersGuest, 'php/account.php?section=orders')['status'] === 200, 'Signed-in customer reaches order history');
// Existing checkout intent keeps its priority; logout clears the added Orders intent.
$session = sessionData($ordersGuest); $session['chwoo_orders_login']=true; $session['chwoo_checkout_login']=true;
file_put_contents(sessionFile($ordersGuest), serialize($session));
$response = request($ordersGuest, 'php/login.php');
check(str_ends_with($response['headers']['location'] ?? '', '/php/checkout.php')
    && !isset(sessionData($ordersGuest)['chwoo_orders_login']), 'Checkout sign-in destination retains precedence and consumes Orders intent');
$session = sessionData($ordersGuest); $session['chwoo_orders_login']=true;
file_put_contents(sessionFile($ordersGuest), serialize($session));
request($ordersGuest, 'php/auth_action.php', ['form_token'=>csrf($ordersGuest),'action'=>'logout']);
check(!isset(sessionData($ordersGuest)['chwoo_orders_login']) && bag($ordersGuest) === $before, 'Logout clears Orders intent and preserves the bag');
$registerGuest = browser(); request($registerGuest, 'php/account.php?section=orders');
$response = request($registerGuest, 'php/auth_action.php', ['form_token'=>csrf($registerGuest),'action'=>'register','full_name'=>'Footer Test',
    'email'=>'task07c2@example.com','password'=>'Fictional07c2!','confirm_password'=>'Fictional07c2!']);
check(str_ends_with($response['headers']['location'] ?? '', '/php/account.php?section=orders'), 'Guest registration also resumes Orders');
