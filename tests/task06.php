<?php
// CLI-only integration suite. Creates/drops only its randomly named test database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../php/order_mail.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli('127.0.0.1', 'root', '');
$conn->set_charset('utf8mb4');
$testDb = 'chwoo_task06_test_' . bin2hex(random_bytes(6));
$server = null; $created = false; $checks = 0; $failure = null;
$log = tempnam(sys_get_temp_dir(), 'chwoo-task06-');
$handles = [];
$originalDb = getenv('CHWOO_DB_NAME');
$originalMailMode = getenv('CHWOO_MAIL_PREPARE_ONLY');
$originalFailureMode = getenv('CHWOO_TEST_PAYMENT_FAILURE');
function check($condition, $label) {
    global $checks;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    $checks++; echo 'PASS: ' . $label . "\n";
}
function liveSnapshot() {
    global $conn;
    $data = [];
    foreach (['products', 'product_colours', 'product_variants', 'customers', 'orders', 'order_items', 'wishlist'] as $table) {
        $data[$table] = $conn->query('SELECT * FROM chwoo_db.' . $table . ' ORDER BY 1')->fetch_all(MYSQLI_ASSOC);
    }
    return hash('sha256', serialize($data));
}
function sqlScript($sql) {
    global $conn;
    $conn->multi_query($sql);
    do { if ($result = $conn->store_result()) { $result->free(); } }
    while ($conn->more_results() && $conn->next_result());
}
function browser() {
    global $handles;
    $handle = curl_init();
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 10]);
    $handles[] = $handle;
    return $handle;
}
function request($handle, $path, $data = null) {
    global $base;
    $headers = [];
    curl_setopt_array($handle, [CURLOPT_URL => $base . '/' . $path, CURLOPT_POST => $data !== null,
        CURLOPT_HEADERFUNCTION => function ($handle, $line) use (&$headers) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) { $headers[strtolower(trim($parts[0]))] = trim($parts[1]); }
            return strlen($line);
        }]);
    if ($data !== null) { curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($data)); }
    $html = curl_exec($handle);
    if ($html === false) { throw new RuntimeException(curl_error($handle)); }
    if (preg_match('/Fatal error|Warning:|Uncaught .*Exception/', $html)) { throw new RuntimeException('PHP error at ' . $path); }
    return ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'headers' => $headers, 'html' => $html];
}
function field($html, $name) {
    preg_match('/name="' . preg_quote($name, '/') . '" value="([^"]*)"/', $html, $match);
    if (!isset($match[1])) { throw new RuntimeException('Missing ' . $name); }
    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
}
function countRows($table) { global $conn; return (int)$conn->query('SELECT COUNT(*) n FROM ' . $table)->fetch_assoc()['n']; }
function stock($id) { global $conn; return (int)$conn->query('SELECT stock_quantity n FROM product_variants WHERE variant_id=' . (int)$id)->fetch_assoc()['n']; }
function add($browser, $csrf, $product = 1, $colour = 1, $size = 'XS') {
    return request($browser, 'php/product.php?id=' . $product, ['form_token' => $csrf, 'action' => 'add', 'product_id' => $product, 'colour' => $colour, 'size' => $size]);
}
function delivery($csrf) {
    return ['form_token' => $csrf, 'action' => 'details', 'full_name' => 'Task Six Tester', 'email' => 'fictional+receipt@example.com',
        'phone' => '+65 8123 4567', 'address' => "10 Test Street\n#01-02", 'postal_code' => '012345', 'notes' => 'Demo only <test>'];
}
function review($browser, $csrf) {
    request($browser, 'php/checkout.php', delivery($csrf));
    $page = request($browser, 'php/checkout.php?step=review');
    return field($page['html'], 'checkout_token');
}
function pay($browser, $csrf, $token, $action) {
    return request($browser, 'php/payment.php', ['form_token' => $csrf, 'checkout_token' => $token, 'action' => $action,
        'customer_id' => 999999, 'quantity' => 99999, 'total_amount' => '0.01']);
}
function fixturePurchase($customerId, $quantities) {
    global $conn;
    $details = delivery(''); unset($details['form_token'], $details['action']);
    $_SESSION = ['chwoo_customer' => ['customer_id' => $customerId],
        'chwoo_checkout' => ['customer_id' => $customerId, 'details' => $details], 'chwoo_cart' => []];
    $items = [];
    foreach ($quantities as $id => $quantity) {
        $_SESSION['chwoo_cart'][$id] = ['variant_id' => $id, 'quantity' => $quantity];
        $row = $conn->query('SELECT v.size_code,c.colour_name,c.image_path,c.price,p.product_id,p.product_name
            FROM product_variants v JOIN product_colours c ON c.colour_id=v.colour_id JOIN products p ON p.product_id=c.product_id WHERE v.variant_id=' . (int)$id)->fetch_assoc();
        $items[] = ['variant_id' => $id, 'product_id' => (int)$row['product_id'], 'name' => $row['product_name'],
            'colour' => $row['colour_name'] ?: 'Colour unspecified', 'size' => $row['size_code'], 'quantity' => $quantity,
            'price_cents' => chwooPriceCents($row['price']), 'image_path' => $row['image_path']];
    }
    $token = chwooPreparePurchase($items, $details, $customerId);
    $_SESSION['chwoo_purchase']['ready'] = true;
    return $token;
}
function startWorker($session) {
    $process = proc_open([PHP_BINARY, __DIR__ . '/order_worker.php'], [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
    if (!is_resource($process)) { throw new RuntimeException('Cannot start concurrent worker'); }
    fwrite($pipes[0], json_encode($session)); fclose($pipes[0]);
    return [$process, $pipes];
}
function finishWorker($worker) {
    [$process, $pipes] = $worker;
    $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0 || $errors !== '') { throw new RuntimeException('Worker failed: ' . $errors); }
    return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}
$liveBefore = liveSnapshot();
try {
    $conn->query('CREATE DATABASE `' . $testDb . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true; $conn->select_db($testDb);
    sqlScript(str_replace('chwoo_db', $testDb, file_get_contents(__DIR__ . '/../database/schema.sql')));
    sqlScript(str_replace('chwoo_db', $testDb, file_get_contents(__DIR__ . '/../database/seed_products.sql')));
    check(countRows('products') === 13 && countRows('product_colours') === 16 && countRows('product_variants') === 64
        && countRows('orders') === 0 && countRows('customers') === 0, 'Fresh canonical schema then seed imports successfully into an isolated database');
    putenv('CHWOO_DB_NAME=' . $testDb);
    putenv('CHWOO_MAIL_PREPARE_ONLY=1');
    putenv('CHWOO_TEST_PAYMENT_FAILURE=1');
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $port = (int)substr(strrchr($address, ':'), 1);
    $base = 'http://127.0.0.1:' . $port . '/chwoo';
    $server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', dirname(__DIR__, 2)],
        [['pipe','r'], ['file',$log,'a'], ['file',$log,'a']], $serverPipes, dirname(__DIR__), null, ['bypass_shell' => true]);
    if (!is_resource($server)) { throw new RuntimeException('Cannot start isolated test server'); }
    fclose($serverPipes[0]);
    $ready = false;
    for ($i = 0; $i < 40; $i++) {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $message, 0.1);
        if ($probe) { fclose($probe); $ready = true; break; }
        usleep(100000);
    }
    check($ready, 'Isolated local PHP server is ready');
    $regression = proc_open([PHP_BINARY, __DIR__ . '/task05.php', $base], [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
    fclose($pipes[0]); $regressionOutput = stream_get_contents($pipes[1]); $regressionErrors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($regression);
    check($exit === 0 && str_contains($regressionOutput, '55 checks passed.'), 'Task 05 regression: 55 checks passed against the new schema (' . trim($regressionErrors) . ')');
    $alice = browser(); $bob = browser(); $guest = browser();
    $customerIds = [];
    foreach (['alice' => $alice, 'bob' => $bob] as $label => $handle) {
        $csrf = field(request($handle, 'php/login.php')['html'], 'form_token');
        $email = 'task06-' . $label . '@example.com'; $password = 'FictionalTest06!';
        request($handle, 'php/auth_action.php', ['form_token' => $csrf, 'action' => 'register', 'full_name' => 'Task Six ' . $label,
            'email' => $email, 'password' => $password, 'confirm_password' => $password]);
        $stmt = $conn->prepare('SELECT customer_id FROM customers WHERE email=?'); $stmt->bind_param('s', $email); $stmt->execute();
        $customerIds[$label] = (int)$stmt->get_result()->fetch_assoc()['customer_id']; $stmt->close();
    }
    $csrf = field(request($alice, 'php/account.php')['html'], 'form_token');
    check(str_contains(request($alice, 'php/account.php?section=orders')['html'], "haven't placed any orders"), 'New customer sees the existing empty order-history design');
    check(request($guest, 'php/payment.php')['status'] === 303 && request($guest, 'php/order_confirmation.php?id=1')['status'] === 303,
        'Payment and confirmation require authentication');
    add($alice, $csrf); add($alice, $csrf);
    request($alice, 'php/wishlist_action.php', ['form_token' => $csrf, 'product_id' => 7, 'action' => 'save']);
    $token = review($alice, $csrf);
    pay($alice, $csrf, $token, 'success');
    check(countRows('orders') === 0 && stock(1) === 5, 'Success POST cannot bypass the review-to-payment transition');
    $page = request($alice, 'php/checkout.php?step=review');
    check(str_contains($page['html'], 'Please return to order review before simulating payment.')
        && !str_contains(request($alice, 'php/checkout.php?step=review')['html'], 'Please return to order review before simulating payment.'),
        'A genuine payment validation error displays once on review and is consumed on refresh');
    pay($alice, $csrf, $token, 'review');
    $page = request($alice, 'php/payment.php');
    check(str_contains($page['headers']['cache-control'] ?? '', 'no-store')
        && str_contains($page['html'], '../js/checkout-history.js'), 'Payment forbids HTTP caching and rechecks restored browser history pages');
    check(str_contains($page['html'], 'Proceed to Payment') && str_contains($page['html'], 'Return to order review')
        && !str_contains($page['html'], 'Simulate') && !preg_match('/name="(?:card|cvv|card_number|card_code|card_expiry)"/', $page['html']), 'Payment handoff has a normal customer interface and no card collection');
    pay($alice, $csrf, $token, 'failure');
    $page = request($alice, 'php/payment.php');
    check(str_contains($page['html'], 'Payment could not be completed.') && !str_contains($page['html'], 'role="alert" data-auto-dismiss'), 'Controlled provider failure stays visible and is not a dismissible success');
    check(countRows('orders') === 0 && stock(1) === 5 && str_contains(request($alice, 'php/cart.php')['html'], '2 item(s)'), 'Failed payment preserves bag and inventory and creates no order');
    pay($alice, 'invalid', $token, 'success');
    check(countRows('orders') === 0 && stock(1) === 5, 'Invalid payment CSRF cannot finalise an order');
    $conn->query('UPDATE product_variants SET stock_quantity=1 WHERE variant_id=1');
    pay($alice, $csrf, $token, 'success');
    check(countRows('orders') === 0 && stock(1) === 1 && str_contains(request($alice, 'php/checkout.php?step=review')['html'], 'Insufficient stock'), 'Stock loss between review and submit prevents purchase');
    $conn->query('UPDATE product_variants SET stock_quantity=5 WHERE variant_id=1');
    $token = review($alice, $csrf); pay($alice, $csrf, $token, 'review');
    $conn->query('UPDATE product_colours SET price=43 WHERE colour_id=1');
    pay($alice, $csrf, $token, 'success');
    check(countRows('orders') === 0 && stock(1) === 5, 'Changed database price requires a new review rather than silently changing the charge');
    $conn->query('UPDATE product_colours SET price=42 WHERE colour_id=1');
    $token = review($alice, $csrf); pay($alice, $csrf, $token, 'review');
    $unrelated = stock(5);
    $success = pay($alice, $csrf, $token, 'success');
    preg_match('/order_confirmation.php\?id=([0-9]+)/', $success['headers']['location'] ?? '', $match);
    $orderId = (int)($match[1] ?? 0);
    check($success['status'] === 303 && $orderId > 0 && countRows('orders') === 1 && countRows('order_items') === 1, 'Successful simulation creates one saved order with its purchased item');
    $order = chwooSavedOrder($conn, $orderId, $customerIds['alice']);
    check($order['payment_status'] === 'Paid' && $order['order_status'] === 'Confirmed' && $order['total_amount'] === '84.00'
        && $order['shipping_charge'] === '0.00' && (int)$order['customer_id'] === $customerIds['alice'], 'Correct owner, statuses and server totals ignore submitted customer, quantity and amount');
    check((int)$order['items'][0]['quantity'] === 2 && $order['items'][0]['unit_price'] === '42.00'
        && $order['items'][0]['selected_size'] === 'XS' && $order['items'][0]['selected_colour'] === 'Midnight Navy', 'Purchased variant and unit-price snapshots are accurate');
    check(stock(1) === 3 && stock(5) === $unrelated, 'Exact purchased quantity is deducted and unrelated inventory is unchanged');
    check(str_contains(request($alice, 'php/cart.php')['html'], 'Your bag is empty.') && request($alice, 'php/account.php')['status'] === 200
        && str_contains(request($alice, 'php/account.php?section=wishlist')['html'], 'Remove from wishlist'), 'Purchase clears the purchased bag while preserving authentication and wishlist');
    $confirmation = request($alice, 'php/order_confirmation.php?id=' . $orderId);
    check($confirmation['status'] === 200 && str_contains($confirmation['html'], 'Your pieces are confirmed.')
        && str_contains($confirmation['html'], 'Singapore 012345') && str_contains($confirmation['html'], 'Demo only &lt;test&gt;'), 'Confirmation displays saved delivery details with escaping');
    check(str_contains($confirmation['html'], 'prepared for testing. It has not been sent.') && $order['acknowledgement_status'] === 'Prepared'
        && $order['acknowledgement_sent_at'] === null, 'Default acknowledgement is accurately labelled prepared, never delivered');
    $oldPayment = request($alice, 'php/payment.php');
    check($oldPayment['status'] === 303 && ($oldPayment['headers']['location'] ?? '') === 'checkout.php',
        'Old payment GET returns to empty checkout without generating a review warning');
    foreach (['php/checkout.php', 'php/checkout.php?step=review'] as $oldUrl) {
        $page = request($alice, $oldUrl);
        check($page['status'] === 200 && str_contains($page['headers']['cache-control'] ?? '', 'no-store')
            && str_contains($page['html'], 'Your bag is feeling a little lonely.')
            && str_contains($page['html'], 'A little something caught your eye? Find your next favourite piece.')
            && str_contains($page['html'], 'Explore the collection')
            && str_contains($page['html'], '../js/checkout-history.js')
            && !str_contains($page['html'], 'class="checkout-progress"')
            && !str_contains($page['html'], 'name="checkout_token"') && !str_contains($page['html'], 'class="checkout-alert"'),
            'Completed checkout URL has a friendly empty state, no payment controls or stale errors: ' . $oldUrl);
    }
    pay($alice, $csrf, $token, 'review');
    $page = request($alice, 'php/checkout.php?step=review');
    check(!str_contains($page['html'], 'class="checkout-alert"')
        && str_contains($page['html'], 'Your bag is feeling a little lonely.')
        && !str_contains(request($alice, 'php/checkout.php')['html'], 'class="checkout-alert"'),
        'Resubmitted old review handoff cannot leave an error on an empty checkout');
    $emptyPost = request($alice, 'php/checkout.php', delivery($csrf) + ['save_address' => '1']);
    check($emptyPost['status'] === 303 && ($emptyPost['headers']['location'] ?? '') === 'checkout.php'
        && countRows('customer_addresses') === 0 && countRows('orders') === 1 && stock(1) === 3
        && !str_contains(request($alice, 'php/checkout.php')['html'], 'name="checkout_token"'),
        'Old delivery POST cannot prepare a purchase or save an address when the bag is empty');
    $again = pay($alice, $csrf, $token, 'success');
    request($alice, 'php/order_confirmation.php?id=' . $orderId);
    check(($again['headers']['location'] ?? '') === 'order_confirmation.php?id=' . $orderId && countRows('orders') === 1 && stock(1) === 3, 'Repeated POST and confirmation refresh do not duplicate orders or stock deduction');
    request($alice, 'php/payment.php?success=1&order_id=' . $orderId);
    check(countRows('orders') === 1, 'URL parameters never establish payment success');
    check(str_contains(request($alice, 'php/account.php?section=orders')['html'], 'order_confirmation.php?id=' . $orderId)
        && str_contains(request($alice, 'php/account.php')['html'], 'order_confirmation.php?id=' . $orderId), 'Order history and Dashboard Recent Orders link to the saved purchase');
    check(request($bob, 'php/order_confirmation.php?id=' . $orderId)['status'] === 404
        && !str_contains(request($bob, 'php/account.php?section=orders')['html'], 'order_confirmation.php?id=' . $orderId), 'Other customers cannot read the order or find it in their history');
    $conn->query("UPDATE product_colours SET price=1,colour_name='Changed',image_path='images/changed.jpg' WHERE colour_id=1");
    $conn->query("UPDATE products SET product_name='Changed product' WHERE product_id=1");
    $page = request($alice, 'php/order_confirmation.php?id=' . $orderId);
    check(str_contains($page['html'], 'Cloud Check Top') && str_contains($page['html'], 'Midnight Navy') && str_contains($page['html'], 'Unit price: SGD $42.00')
        && str_contains($page['html'], 'images/newin1.jpg'), 'Confirmation retains purchased names, colours, images and prices after catalogue edits');
    $conn->query("UPDATE product_colours SET price=42,colour_name='Midnight Navy',image_path='images/newin1.jpg' WHERE colour_id=1");
    $conn->query("UPDATE products SET product_name='Cloud Check Top' WHERE product_id=1");
    $conn->query('UPDATE product_variants SET stock_quantity=1 WHERE variant_id=2');
    add($alice, $csrf, 1, 1, 'S'); $lastToken = review($alice, $csrf); pay($alice, $csrf, $lastToken, 'review'); pay($alice, $csrf, $lastToken, 'success');
    $product = request($alice, 'php/product.php?id=1&colour=1&size=S');
    check(stock(2) === 0 && str_contains($product['html'], 'Size S is unavailable'), 'Final unit can be purchased and product availability reflects zero stock');

    $beforeOrders = countRows('orders'); $beforeItems = countRows('order_items'); $beforeStock = [stock(1), stock(5)];
    $rollbackToken = fixturePurchase($customerIds['alice'], [1 => 1, 5 => 1]); $bagBefore = $_SESSION['chwoo_cart'];
    $conn->query("CREATE TRIGGER task06_fail_item BEFORE INSERT ON order_items FOR EACH ROW BEGIN IF NEW.variant_id=5 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Disposable rollback test'; END IF; END");
    $rolledBack = false;
    try { chwooFinaliseOrder($conn, $customerIds['alice'], $rollbackToken); } catch (mysqli_sql_exception $error) { $rolledBack = true; }
    $conn->query('DROP TRIGGER task06_fail_item');
    check($rolledBack && countRows('orders') === $beforeOrders && countRows('order_items') === $beforeItems
        && [stock(1),stock(5)] === $beforeStock && $_SESSION['chwoo_cart'] === $bagBefore,
        'Injected second-item database failure rolls back the order, earlier item and earlier stock deduction while preserving the bag');
    $badToken = fixturePurchase($customerIds['alice'], [64 => 1]);
    $conn->query('DELETE FROM product_variants WHERE variant_id=64');
    $invalid = false; try { chwooFinaliseOrder($conn, $customerIds['alice'], $badToken); } catch (RuntimeException $error) { $invalid = true; }
    check($invalid && countRows('orders') === $beforeOrders && isset($_SESSION['chwoo_cart'][64]), 'Deleted variant is rejected without partial purchase or bag clearing');
    $conn->query("INSERT INTO product_variants (variant_id,colour_id,size_code,stock_quantity) VALUES (64,16,'L',5)");
    $changedToken = fixturePurchase($customerIds['alice'], [1 => 1]);
    $_SESSION['chwoo_cart'][5] = ['variant_id' => 5, 'quantity' => 1];
    $invalid = false; try { chwooFinaliseOrder($conn, $customerIds['alice'], $changedToken); } catch (RuntimeException $error) { $invalid = true; }
    check($invalid && countRows('orders') === $beforeOrders, 'Bag edits after review require another review');
    $invalidToken = fixturePurchase($customerIds['alice'], [1 => 1]); $_SESSION['chwoo_checkout']['details']['full_name'] = '';
    $invalid = false; try { chwooFinaliseOrder($conn, $customerIds['alice'], $invalidToken); } catch (RuntimeException $error) { $invalid = true; }
    check($invalid && countRows('orders') === $beforeOrders, 'Invalid delivery details are revalidated at final submission');
    $_SESSION = ['chwoo_cart' => [1 => ['variant_id'=>1,'quantity'=>3], 5 => ['variant_id'=>5,'quantity'=>2]], 'chwoo_customer' => ['customer_id'=>$customerIds['alice']], 'keep' => 'wishlist/session data'];
    chwooPurchasedBagCleanup([1=>2]);
    check($_SESSION['chwoo_cart'][1]['quantity'] === 1 && $_SESSION['chwoo_cart'][5]['quantity'] === 2 && $_SESSION['keep'] === 'wishlist/session data', 'Purchased-entry cleanup preserves unrelated entries and extra quantities/session data');
    $multiToken = fixturePurchase($customerIds['alice'], [29=>1, 33=>1]);
    $multi = chwooFinaliseOrder($conn, $customerIds['alice'], $multiToken);
    $multiOrder = chwooSavedOrder($conn, $multi['order_id'], $customerIds['alice']);
    check(count($multiOrder['items']) === 2 && $multiOrder['total_amount'] === '90.00'
        && $multiOrder['items'][0]['selected_colour'] === 'Midnight' && $multiOrder['items'][0]['unit_price'] === '46.00'
        && $multiOrder['items'][1]['selected_colour'] === 'Ivory' && $multiOrder['items'][1]['unit_price'] === '44.00'
        && stock(29) === 4 && stock(33) === 4, 'Multi-variant purchase saves separate colourway prices/items and deducts each exact variant');

    $conn->query('UPDATE product_variants SET stock_quantity=1 WHERE variant_id=4');
    fixturePurchase($customerIds['alice'], [4=>1]); $sessionA = $_SESSION;
    fixturePurchase($customerIds['bob'], [4=>1]); $sessionB = $_SESSION;
    $beforeRace = countRows('orders');
    $conn->begin_transaction(); $conn->query('SELECT variant_id FROM product_variants WHERE variant_id=4 FOR UPDATE');
    $workerA = startWorker($sessionA); $workerB = startWorker($sessionB); usleep(150000); $conn->commit();
    $race = [finishWorker($workerA), finishWorker($workerB)];
    check(count(array_filter($race, function($result){return $result['ok'];})) === 1 && stock(4) === 0 && countRows('orders') === $beforeRace + 1,
        'Concurrent customers competing for one final unit produce exactly one purchase and never negative stock');
    $duplicateToken = fixturePurchase($customerIds['alice'], [3=>1]); $duplicateSession = $_SESSION;
    $beforeRace = countRows('orders'); $beforeStock = stock(3);
    $conn->begin_transaction(); $conn->query('SELECT customer_id FROM customers WHERE customer_id=' . $customerIds['alice'] . ' FOR UPDATE');
    $workerA = startWorker($duplicateSession); $workerB = startWorker($duplicateSession); usleep(150000); $conn->commit();
    $race = [finishWorker($workerA), finishWorker($workerB)];
    check($race[0]['ok'] && $race[1]['ok'] && $race[0]['order_id'] === $race[1]['order_id']
        && countRows('orders') === $beforeRace + 1 && stock(3) === $beforeStock - 1, 'Concurrent duplicate tokens resolve to one order and one deduction');
    $message = chwooAcknowledgementText($order);
    check(str_contains($message, 'Order #' . $orderId) && str_contains($message, 'Task Six Tester')
        && str_contains($message, '2 x SGD $42.00 = SGD $84.00') && str_contains($message, 'Final amount: SGD $84.00'), 'Acknowledgement contains the saved order, display name, variants, quantities and final amount');
    $transportCalls = 0;
    $fake = function () use (&$transportCalls) { $transportCalls++; return false; };
    check(chwooLocalAcknowledgement('test', $message, ['enabled'=>false], $fake) === 'Prepared' && $transportCalls === 0, 'Disabled delivery only prepares email and never contacts a recipient');
    check(chwooLocalAcknowledgement('test', $message, ['enabled'=>true,'recipient'=>'arbitrary@example.com','sender'=>'student@localhost'], $fake) === 'Failed'
        && $transportCalls === 0, 'An external configured recipient is refused before transport');
    $localConfig = ['enabled'=>true,'recipient'=>'test-student@localhost','sender'=>'test-store@localhost'];
    $beforeMail = countRows('orders');
    check(chwooLocalAcknowledgement('test', $message, $localConfig, $fake) === 'Failed' && countRows('orders') === $beforeMail,
        'Simulated mail transport failure does not change committed orders');
    check(chwooLocalAcknowledgement('test', $message, $localConfig, function(){return true;}) === 'Accepted',
        'A mocked local acceptance is classified Accepted, never Delivered (no actual mail sent)');
    $conn->query("UPDATE orders SET acknowledgement_status='Failed' WHERE order_id=$orderId");
    check(str_contains(request($alice, 'php/order_confirmation.php?id=' . $orderId)['html'], 'Your confirmed order is unchanged.'), 'Saved email failure displays accurately on the still-confirmed purchase');
    $schemaToken = $conn->query("SHOW INDEX FROM orders WHERE Key_name='uq_orders_checkout_token'")->fetch_assoc();
    check($schemaToken && (int)$schemaToken['Non_unique'] === 0, 'Database schema enforces checkout-token uniqueness');
    request($alice, 'php/wishlist_action.php', ['form_token'=>$csrf,'product_id'=>7,'action'=>'save']);
    $page = request($alice, 'php/account.php?section=wishlist');
    check(str_contains($page['html'], 'role="status" data-auto-dismiss>Saved to your wishlist.')
        && !str_contains(request($alice, 'php/account.php?section=wishlist')['html'], 'data-auto-dismiss>Saved to your wishlist.'),
        'Success flash is marked for dismissal and does not reappear on refresh');
    request($alice, 'php/wishlist_action.php', ['form_token'=>$csrf,'product_id'=>0,'action'=>'save']);
    $page = request($alice, 'php/account.php');
    check(str_contains($page['html'], 'role="alert" >Invalid wishlist selection.')
        && !str_contains($page['html'], 'data-auto-dismiss>Invalid wishlist selection.'), 'Important wishlist errors are never marked for auto-dismissal');
    add($alice, $csrf, 2, 2, 'XS');
    check(str_contains(request($alice, 'php/product.php?id=2&colour=2&size=XS&bag=open')['html'], 'class="product-message success" role="status" data-auto-dismiss'),
        'Add-to-bag success uses the shared success-only dismissal behavior');
    require __DIR__ . '/saved_addresses.php';
    if (in_array('--preview', $argv, true)) {
        echo "BROWSER PREVIEW: $base\nFictitious test login: task06-alice@example.com / FictionalTest06!\n";
        echo "Saved confirmation: $base/php/order_confirmation.php?id=$orderId\nPress Enter to remove the test database and server.\n";
        fgets(STDIN);
    }
} catch (Throwable $error) {
    $failure = $error; fwrite(STDERR, $error->getMessage() . "\n" . $error->getTraceAsString() . "\n");
} finally {
    foreach ($handles as $handle) { curl_close($handle); }
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($failure && is_file($log)) {
        $lines = explode("\n", file_get_contents($log));
        fwrite(STDERR, implode("\n", array_slice($lines, -14)) . "\n");
    }
    if (is_file($log)) { unlink($log); }
    if ($created && preg_match('/\Achwoo_task06_test_[a-f0-9]{12}\z/D', $testDb)) {
        $conn->rollback(); $conn->query('DROP DATABASE `' . $testDb . '`');
    }
    $originalDb === false ? putenv('CHWOO_DB_NAME') : putenv('CHWOO_DB_NAME=' . $originalDb);
    $originalMailMode === false ? putenv('CHWOO_MAIL_PREPARE_ONLY') : putenv('CHWOO_MAIL_PREPARE_ONLY=' . $originalMailMode);
    $originalFailureMode === false ? putenv('CHWOO_TEST_PAYMENT_FAILURE') : putenv('CHWOO_TEST_PAYMENT_FAILURE=' . $originalFailureMode);
    session_destroy();
}
check(liveSnapshot() === $liveBefore, 'Installed chwoo_db catalogue, stock, customer accounts, wishlist and orders remain unchanged');
$conn->close();
if ($failure) { exit(1); }
echo $checks . " Task 06 checks passed; disposable database and server removed.\n";
