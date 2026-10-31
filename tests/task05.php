<?php
// Run from CLI: C:\xampp\php\php.exe tests\task05.php [http://localhost:8000/chwoo]
// Uses disposable records only; never imports, resets or edits existing inventory.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../php/checkout_helpers.php';
require_once __DIR__ . '/../php/db.php';
$base = rtrim($argv[1] ?? 'http://localhost:8000/chwoo', '/');
if (!preg_match('~\Ahttp://(?:localhost|127\.0\.0\.1)(?::[0-9]+)?/chwoo\z~', $base)) {
    exit("Use the local CHWOO XAMPP URL.\n");
}
$browser = curl_init();
curl_setopt_array($browser, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 15]);
$checks = 0;
$customerId = null;
$productId = null;
$suffix = bin2hex(random_bytes(6));
$email = 'task05-' . $suffix . '@example.com';
$password = 'Task05-' . bin2hex(random_bytes(10));

function check($condition, $label) {
    global $checks;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    $checks++;
    echo 'PASS: ' . $label . "\n";
}
function request($path, $data = null) {
    global $browser, $base;
    $headers = [];
    curl_setopt_array($browser, [CURLOPT_URL => $base . '/' . $path,
        CURLOPT_POST => $data !== null, CURLOPT_HEADERFUNCTION => function ($handle, $line) use (&$headers) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) { $headers[strtolower(trim($parts[0]))] = trim($parts[1]); }
            return strlen($line);
        }]);
    if ($data !== null) { curl_setopt($browser, CURLOPT_POSTFIELDS, http_build_query($data)); }
    $html = curl_exec($browser);
    if ($html === false) { throw new RuntimeException(curl_error($browser)); }
    if (preg_match('/(?:Fatal error|Warning:|Uncaught .*Exception)/', $html)) {
        throw new RuntimeException('PHP error returned by ' . $path);
    }
    return ['status' => curl_getinfo($browser, CURLINFO_RESPONSE_CODE), 'headers' => $headers, 'html' => $html];
}
function token($html) {
    preg_match('/name="form_token" value="([a-f0-9]+)"/', $html, $match);
    if (!isset($match[1])) { throw new RuntimeException('Missing CSRF token'); }
    return $match[1];
}
function redirectsTo($response, $suffix) {
    return $response['status'] === 303 && str_ends_with($response['headers']['location'] ?? '', $suffix);
}
function countRows($table) {
    global $conn;
    return (int)$conn->query('SELECT COUNT(*) AS n FROM ' . $table)->fetch_assoc()['n'];
}
function catalogueSnapshot() {
    global $conn;
    return hash('sha256', serialize($conn->query('SELECT v.*, c.price, c.image_path, p.is_active
        FROM product_variants v JOIN product_colours c ON c.colour_id=v.colour_id
        JOIN products p ON p.product_id=c.product_id ORDER BY v.variant_id')->fetch_all(MYSQLI_ASSOC)));
}
function navigationIs($html, $expected) {
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $active = $xpath->query('//div[@class="main-nav"]/a[@aria-current="page"]');
    return $expected === '' ? $active->length === 0
        : $active->length === 1 && trim($active->item(0)->textContent) === $expected;
}
$before = [countRows('customers'), countRows('orders'), countRows('order_items'), catalogueSnapshot()];
$failure = null;
try {
    $values = chwooCheckoutValues(['full_name' => '李 A/L O’Neil', 'email' => "o'neil+fashion@example.com",
        'phone' => '+65 (8123) 4567', 'address' => "10 Demo Street\n#01-02", 'postal_code' => '012345', 'notes' => '']);
    check(chwooCheckoutErrors($values) === [], 'Common names, plus/apostrophe email, international phone and leading-zero postal code');
    $invalid = chwooCheckoutValues(['full_name' => ['array'], 'email' => 'bad', 'phone' => '12', 'address' => '', 'postal_code' => '12345', 'notes' => str_repeat('x', 501)]);
    check(count(chwooCheckoutErrors($invalid)) === 6, 'All malformed fields rejected without array type errors');
    $values['full_name'] = str_repeat('a', 121);
    check(isset(chwooCheckoutErrors($values)['full_name']), 'Oversized name remains invalid');

    $page = request('php/checkout.php');
    check($page['status'] === 200 && str_contains($page['html'], 'Your bag is feeling a little lonely.')
        && !str_contains($page['html'], 'class="checkout-progress"') && !str_contains($page['html'], 'name="checkout_token"'), 'Empty checkout is graceful and cannot proceed');
    $page = request('php/product.php?id=1');
    $csrf = token($page['html']);
    check(str_contains($page['html'], '../css/header.css'), 'Product loads the shared navigation styling');
    check(navigationIs($page['html'], 'SHOP'), 'Product marks Shop active');
    $shop = request('php/shop.php');
    $lookbook = request('php/outfit.php');
    $newIn = request('php/newin.php');
    check(navigationIs($shop['html'], 'SHOP') && navigationIs($lookbook['html'], 'LOOKBOOK')
        && navigationIs($newIn['html'], 'NEW IN'), 'Shop, Lookbook and New In have the correct active navigation');
    $row = $conn->query("SELECT v.variant_id, c.product_id, c.colour_id, v.size_code, c.price
        FROM product_variants v JOIN product_colours c ON c.colour_id=v.colour_id
        JOIN products p ON p.product_id=c.product_id WHERE p.is_active=1 AND v.stock_quantity>=2
        ORDER BY v.variant_id LIMIT 1")->fetch_assoc();
    if (!$row) { throw new RuntimeException('Need one existing in-stock variant for the product regression'); }
    $add = request('php/product.php?id=' . $row['product_id'], ['form_token' => $csrf, 'action' => 'add',
        'product_id' => $row['product_id'], 'colour' => $row['colour_id'], 'size' => $row['size_code']]);
    check($add['status'] === 303, 'Existing product can be added with colour and size');
    $page = request('php/checkout.php');
    check(redirectsTo($page, '/php/login.php'), 'Guest checkout redirects to login');
    $login = request('php/login.php');
    check(str_contains($login['html'], 'Your bag is saved.'), 'Login explains the protected checkout workflow');
    $csrf = token($login['html']);
    $register = request('php/auth_action.php', ['form_token' => $csrf, 'action' => 'register', 'full_name' => 'Task Five Tester',
        'email' => $email, 'password' => $password, 'confirm_password' => $password]);
    $stmt = $conn->prepare('SELECT customer_id FROM customers WHERE email=?');
    $stmt->bind_param('s', $email); $stmt->execute();
    $created = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $customerId = $created ? (int)$created['customer_id'] : null;
    check($customerId && redirectsTo($register, '/php/checkout.php'), 'Registration resumes checkout and preserves the bag');
    $page = request('php/checkout.php');
    check(str_contains($page['html'], 'value="Task Five Tester"') && str_contains($page['html'], $email)
        && str_contains($page['html'], 'Order summary'), 'Customer name/email are prefilled with the existing bag');
    check(($page['headers']['cache-control'] ?? '') === 'no-store', 'Delivery and review pages are not cached');
    check(navigationIs($page['html'], '') && navigationIs(request('php/account.php')['html'], '')
        && navigationIs(request('php/cart.php')['html'], ''), 'Checkout, account and bag do not mark unrelated catalogue pages active');
    $csrf = token($page['html']);
    $details = ['form_token' => $csrf, 'action' => 'details', 'full_name' => '李 A/L O’Neil',
        'email' => "o'neil+fashion@example.com", 'phone' => '+65 8123 4567', 'address' => "10 Demo Street\n#01-02",
        'postal_code' => '012345', 'notes' => '<script>test</script> Leave at door'];
    $bad = request('php/checkout.php', array_merge($details, ['email' => 'bad', 'phone' => [], 'postal_code' => '123']));
    check(redirectsTo($bad, 'checkout.php'), 'Invalid delivery POST uses Post/Redirect/Get');
    $page = request('php/checkout.php');
    check(str_contains($page['html'], 'Please enter a valid email address.') && str_contains($page['html'], 'Please enter a valid contact number.')
        && str_contains($page['html'], 'Please enter a valid 6-digit Singapore postal code.') && str_contains($page['html'], 'value="李 A/L O’Neil"')
        && str_contains($page['html'], 'aria-invalid="true"'), 'PHP field errors, accessible associations and preserved values work without JavaScript');
    $bad = request('php/checkout.php', array_merge($details, ['form_token' => 'wrong']));
    $page = request('php/checkout.php');
    check(str_contains($page['html'], 'Please refresh the page and try again.'), 'Invalid checkout CSRF rejected');
    check(redirectsTo(request('php/checkout.php?step=review'), 'checkout.php'), 'Review cannot bypass valid delivery details');
    $ok = request('php/checkout.php', array_merge($details, ['price' => '0.01', 'total' => '0.01']));
    check(redirectsTo($ok, 'checkout.php?step=review'), 'Valid delivery details reach review');
    $page = request('php/checkout.php?step=review');
    check(str_contains($page['html'], 'Your selected pieces') && str_contains($page['html'], 'Size: ' . $row['size_code'])
        && str_contains($page['html'], 'Unit price: SGD $' . number_format((float)$row['price'], 2)), 'Review uses actual selections and database unit prices, ignoring submitted prices');
    check(str_contains($page['html'], '&lt;script&gt;test&lt;/script&gt;') && !str_contains($page['html'], '<script>test</script>'), 'Delivery notes are escaped in review');
    check(str_contains($page['html'], 'Continue to payment') && str_contains($page['html'], 'name="checkout_token"'), 'Review provides the protected payment transition');
    $refreshed = request('php/checkout.php?step=review');
    check($refreshed['html'] === $page['html'], 'Review refresh is stable');
    $page = request('php/checkout.php');
    check(str_contains($page['html'], 'value="012345"'), 'Edit details restores the saved postal code');
    $details['address'] = '20 Updated Demo Road';
    request('php/checkout.php', $details);
    $page = request('php/checkout.php?step=review');
    check(str_contains($page['html'], '20 Updated Demo Road'), 'Edited delivery details appear in review');
    $control = ['form_token' => $csrf, 'variant_id' => $row['variant_id'], 'return_to' => 'php/checkout.php?step=review', 'bag_context' => 'checkout'];
    $update = request('php/bag_action.php', $control + ['action' => 'increase']);
    check(redirectsTo($update, '/php/checkout.php?step=review'), 'Review quantity controls return to review');
    $page = request('php/checkout.php?step=review');
    check(str_contains($page['html'], 'Quantity: 2') && !str_contains($page['html'], 'Bag quantity updated.'), 'Quantity increases silently on review');
    $expected = (float)$row['price'] * 2;
    check(str_contains($page['html'], 'SGD $' . number_format($expected + ($expected < 80 ? 5 : 0), 2)), 'Edited quantities recalculate the estimated total');
    request('php/bag_action.php', $control + ['action' => 'decrease']);
    $page = request('php/checkout.php?step=review');
    check(str_contains($page['html'], 'Quantity: 1'), 'Quantity decreases return the correct selection');
    request('php/bag_action.php', $control + ['action' => 'remove']);
    $page = request('php/checkout.php?step=review');
    check(str_contains($page['html'], 'Your bag is feeling a little lonely.') && !str_contains($page['html'], 'Item removed'), 'Removing the final item produces an empty checkout silently');

    // Disposable product data exercises exact shipping thresholds and stock failures.
    $name = 'Task05 disposable ' . $suffix;
    $stmt = $conn->prepare("INSERT INTO products (product_name,category,is_active) VALUES (?,'Tops',1)");
    $stmt->bind_param('s', $name); $stmt->execute(); $productId = (int)$conn->insert_id; $stmt->close();
    $image = 'images/task05-' . $suffix . '.jpg';
    $stmt = $conn->prepare("INSERT INTO product_colours (product_id,colour_name,image_path,price,is_default) VALUES (?,'Test colour',?,40,1)");
    $stmt->bind_param('is', $productId, $image); $stmt->execute(); $colourId = (int)$conn->insert_id; $stmt->close();
    $conn->query("INSERT INTO product_variants (colour_id,size_code,stock_quantity) VALUES ($colourId,'S',2)");
    $variantId = (int)$conn->insert_id;
    $addData = ['form_token' => $csrf, 'action' => 'add', 'product_id' => $productId, 'colour' => $colourId, 'size' => 'S'];
    request('php/product.php?id=' . $productId, $addData);
    $page = request('php/checkout.php?step=review');
    check(str_contains($page['html'], '<dd>SGD $5.00</dd>') && str_contains($page['html'], '<dd>SGD $45.00</dd>'), 'Below SGD 80 delivery is SGD 5');
    $control['variant_id'] = $variantId;
    request('php/bag_action.php', $control + ['action' => 'increase']);
    $page = request('php/checkout.php?step=review');
    check(str_contains($page['html'], '<dd>Complimentary</dd>') && str_contains($page['html'], '<dd>SGD $80.00</dd>'), 'Exactly SGD 80 delivery is free');
    $drawerControl = array_merge($control, ['bag_context' => 'drawer']);
    $updated = request('php/bag_action.php', $drawerControl + ['action' => 'decrease']);
    check(redirectsTo($updated, '/php/checkout.php?step=review&bag=updated'), 'Drawer update preserves its originating review and open-state flag');
    $page = request('php/checkout.php?step=review&bag=updated');
    check(str_contains($page['html'], 'is-open is-restoring') && str_contains($page['html'], 'data-resume="true"')
        && !str_contains($page['html'], 'Bag quantity updated.'), 'Drawer is server-rendered open without a success banner or replay animation');
    request('php/bag_action.php', $drawerControl + ['action' => 'increase']);
    request('php/bag_action.php', $drawerControl + ['action' => 'increase']);
    $page = request('php/checkout.php?step=review&bag=updated');
    check(str_contains($page['html'], 'No more stock is available'), 'Stock-limit errors remain visible in the open drawer');
    request('php/bag_action.php', array_merge($drawerControl, ['return_to' => 'php/cart.php?from=checkout', 'action' => 'increase']));
    $cartDrawer = request('php/cart.php?from=checkout&bag=updated');
    $document = new DOMDocument(); @$document->loadHTML($cartDrawer['html']);
    $xpath = new DOMXPath($document);
    check(str_contains($xpath->query('//aside[@id="chwoo-bag-drawer"]')->item(0)->textContent, 'No more stock is available'),
        'Cart-origin drawer errors remain inside the open drawer');
    $cart = request('php/cart.php?from=checkout');
    check(str_contains($cart['html'], 'RETURN TO ORDER REVIEW') && str_contains($cart['html'], 'value="php/cart.php?from=checkout"'), 'Full bag retains its explicit return-to-review link');
    $fullControl = array_merge($control, ['bag_context' => 'full', 'return_to' => 'php/cart.php?from=checkout']);
    $updated = request('php/bag_action.php', $fullControl + ['action' => 'decrease']);
    check(redirectsTo($updated, '/php/cart.php?from=checkout'), 'Full bag editing preserves the checkout return context');
    request('php/bag_action.php', $fullControl + ['action' => 'increase']);
    request('php/bag_action.php', array_merge($control, ['form_token' => 'invalid', 'action' => 'remove']));
    $page = request('php/checkout.php?step=review');
    check(str_contains($page['html'], 'Please refresh the page') && str_contains($page['html'], 'Quantity: 2'), 'Bad bag CSRF cannot remove an item');
    request('php/bag_action.php', array_merge($control, ['action' => 'unknown']));
    check(str_contains(request('php/checkout.php?step=review')['html'], 'Invalid bag action.'), 'Invalid bag operations still show meaningful errors');
    $removed = request('php/bag_action.php', $drawerControl + ['action' => 'remove']);
    $page = request('php/checkout.php?step=review&bag=updated');
    check(redirectsTo($removed, '/php/checkout.php?step=review&bag=updated') && str_contains($page['html'], 'data-resume="true"')
        && !str_contains($page['html'], 'Item removed'), 'Successful drawer removal is silent and the drawer stays open');
    request('php/product.php?id=' . $productId, $addData);
    request('php/bag_action.php', $control + ['action' => 'increase']);
    $conn->query("UPDATE product_colours SET price=39.99 WHERE colour_id=$colourId");
    $page = request('php/checkout.php?step=review');
    check(str_contains($page['html'], '<dd>SGD $79.98</dd>') && str_contains($page['html'], '<dd>SGD $84.98</dd>'), 'Review reload revalidates database prices and shipping below the threshold');
    $conn->query("UPDATE product_variants SET stock_quantity=0 WHERE variant_id=$variantId");
    $page = request('php/checkout.php?step=review');
    check(str_contains($page['html'], 'This variant is out of stock.'), 'Unavailable stock is reported without reserving inventory');
    $conn->query("UPDATE product_variants SET stock_quantity=1 WHERE variant_id=$variantId");
    $page = request('php/checkout.php?step=review');
    check(str_contains($page['html'], 'Only 1 available. Please reduce the quantity.'), 'Insufficient stock reports the current database limit');
    $conn->query("UPDATE products SET is_active=0 WHERE product_id=$productId");
    $page = request('php/checkout.php?step=review');
    check(str_contains($page['html'], 'Unavailable bag entries were removed.') && str_contains($page['html'], 'Your bag is feeling a little lonely.'), 'Unavailable product is safely removed with an explanation');
    $conn->query("UPDATE products SET is_active=1 WHERE product_id=$productId");
    request('php/product.php?id=' . $productId, $addData);
    $conn->query("DELETE FROM product_variants WHERE variant_id=$variantId");
    $page = request('php/checkout.php?step=review');
    check(str_contains($page['html'], 'Unavailable bag entries were removed.'), 'Deleted variant is handled safely');
    // Wishlist and account regression on this disposable customer only.
    $wishlist = request('php/wishlist_action.php', ['form_token' => $csrf, 'product_id' => $row['product_id'], 'action' => 'save', 'return_to' => 'php/account.php?section=wishlist']);
    $page = request('php/account.php?section=wishlist');
    check($wishlist['status'] === 303 && str_contains($page['html'], 'Remove from wishlist'), 'Existing persistent wishlist still works');
    $logout = request('php/auth_action.php', ['form_token' => 'invalid', 'action' => 'logout']);
    check(request('php/account.php')['status'] === 200, 'Logout remains CSRF protected');
    request('php/auth_action.php', ['form_token' => $csrf, 'action' => 'logout']);
    $page = request('php/account.php');
    check(redirectsTo($page, '/php/login.php'), 'Valid POST logout removes authenticated access');
    $normalLogin = request('php/auth_action.php', ['form_token' => $csrf, 'action' => 'login', 'email' => $email, 'password' => $password]);
    check(redirectsTo($normalLogin, '/php/account.php'), 'Normal login still opens Account Dashboard');
    request('php/product.php?id=' . $row['product_id'], ['form_token' => $csrf, 'action' => 'add',
        'product_id' => $row['product_id'], 'colour' => $row['colour_id'], 'size' => $row['size_code']]);
    $page = request('php/checkout.php?step=review');
    check(redirectsTo($page, 'checkout.php'), 'Logout clears temporary checkout details even after refilling the bag');
    request('php/auth_action.php', ['form_token' => $csrf, 'action' => 'logout']);
    request('php/wishlist_action.php', ['form_token' => $csrf, 'product_id' => $row['product_id'], 'action' => 'save']);
    request('php/checkout.php');
    $protectedLogin = request('php/auth_action.php', ['form_token' => $csrf, 'action' => 'login', 'email' => $email, 'password' => $password]);
    check(redirectsTo($protectedLogin, '/php/checkout.php') && str_contains(request('php/checkout.php')['html'], 'Order summary'),
        'Existing-customer login resumes checkout with the bag, including a pending wishlist save');
    $page = request('php/account.php?section=wishlist');
    check(str_contains($page['html'], 'Remove from wishlist'), 'Pending wishlist save is preserved during checkout authentication');
    check(countRows('orders') === $before[1] && countRows('order_items') === $before[2], 'No final orders or order items inserted');
} catch (Throwable $error) {
    $failure = $error;
    fwrite(STDERR, $error->getMessage() . "\n");
} finally {
    curl_close($browser);
    if ($customerId) {
        $stmt = $conn->prepare('DELETE FROM customers WHERE customer_id=? AND email=? AND NOT EXISTS (SELECT 1 FROM orders WHERE customer_id=?)');
        $stmt->bind_param('isi', $customerId, $email, $customerId); $stmt->execute(); $stmt->close();
    }
    if ($productId) {
        $conn->query("DELETE v FROM product_variants v JOIN product_colours c ON c.colour_id=v.colour_id WHERE c.product_id=$productId");
        $conn->query("DELETE FROM product_colours WHERE product_id=$productId");
        $stmt = $conn->prepare('DELETE FROM products WHERE product_id=? AND product_name=?');
        $stmt->bind_param('is', $productId, $name); $stmt->execute(); $stmt->close();
    }
}
check(countRows('customers') === $before[0] && countRows('orders') === $before[1] && countRows('order_items') === $before[2]
    && catalogueSnapshot() === $before[3], 'Disposable records cleaned up; existing catalogue prices/stock and order counts unchanged');
$conn->close();
if ($failure) { exit(1); }
echo $checks . " checks passed.\n";
