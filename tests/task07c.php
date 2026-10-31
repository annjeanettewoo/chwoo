<?php
// CLI-only HTTP/session integration tests. All mutations use a disposable DB and sessions.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli('127.0.0.1', 'root', '');
$conn->set_charset('utf8mb4');
$testDb = 'chwoo_task07c_test_' . bin2hex(random_bytes(6));
$scratch = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $testDb;
mkdir($scratch);
$log = $scratch . '/server.log';
$originalDb = getenv('CHWOO_DB_NAME');
$originalMail = getenv('CHWOO_MAIL_PREPARE_ONLY');
$server = null; $created = false; $handles = []; $checks = 0; $failure = null;
function check($condition, $label) {
    global $checks;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    $checks++; echo 'PASS: ' . $label . "\n";
}
function liveSnapshot() {
    global $conn;
    $snapshot = [];
    foreach (['products','product_colours','product_variants','customers','orders','order_items','wishlist','customer_addresses'] as $table) {
        $snapshot[$table] = $conn->query('SELECT * FROM chwoo_db.' . $table . ' ORDER BY 1')->fetch_all(MYSQLI_ASSOC);
    }
    return hash('sha256', serialize($snapshot));
}
function sqlScript($sql) {
    global $conn;
    $conn->multi_query($sql);
    do { if ($result = $conn->store_result()) { $result->free(); } }
    while ($conn->more_results() && $conn->next_result());
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
    if (preg_match('/Fatal error|Warning:|Uncaught .*Exception/', $html)) { throw new RuntimeException('PHP error at ' . $path . ': ' . $html); }
    return ['status'=>curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'headers'=>$headers, 'html'=>$html];
}
function browser() {
    global $handles;
    $handle = curl_init();
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_COOKIEFILE=>'', CURLOPT_TIMEOUT=>10]);
    $handles[] = $handle;
    request($handle, 'php/outfit.php');
    return $handle;
}
function sessionFile($handle) {
    global $scratch;
    foreach (curl_getinfo($handle, CURLINFO_COOKIELIST) as $cookie) {
        $parts = explode("\t", $cookie);
        if ($parts[5] === 'PHPSESSID' && preg_match('/^[a-zA-Z0-9,-]+$/D', $parts[6])) {
            return $scratch . '/sess_' . $parts[6];
        }
    }
    throw new RuntimeException('Missing test session');
}
function sessionData($handle) { return unserialize(file_get_contents(sessionFile($handle)), ['allowed_classes'=>false]); }
function bag($handle) { return sessionData($handle)['chwoo_cart'] ?? []; }
function csrf($handle) { return sessionData($handle)['chwoo_form_token']; }
function payload($handle, $mode = 'separates') {
    return $mode === 'dress'
        ? ['form_token'=>csrf($handle), 'outfit_mode'=>'dress', 'dress_product'=>'11', 'dress_colour'=>'11', 'dress_variant'=>'41']
        : ['form_token'=>csrf($handle), 'outfit_mode'=>'separates', 'top_product'=>'1', 'top_colour'=>'1', 'top_variant'=>'1',
            'bottom_product'=>'2', 'bottom_colour'=>'2', 'bottom_variant'=>'5'];
}
function rejected($handle, $data, $label) {
    $before = bag($handle);
    $response = request($handle, 'php/outfit_action.php', $data);
    check($response['status'] === 303 && str_ends_with($response['headers']['location'] ?? '', '/php/outfit.php')
        && bag($handle) === $before, $label . ': redirect and byte-equivalent bag entries');
    $page = request($handle, 'php/outfit.php');
    check(str_contains($page['html'], 'role="alert"') && str_contains($page['html'], 'No outfit pieces were added.')
        && bag($handle) === $before, $label . ': feedback and no partial addition after GET');
}
function field($html, $name) {
    preg_match('/name="' . preg_quote($name, '/') . '" value="([^"]*)"/', $html, $match);
    if (!isset($match[1])) { throw new RuntimeException('Missing ' . $name); }
    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
}
$liveBefore = liveSnapshot();
try {
    $conn->query('CREATE DATABASE `' . $testDb . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true; $conn->select_db($testDb);
    sqlScript(str_replace('chwoo_db', $testDb, file_get_contents(__DIR__ . '/../database/schema.sql')));
    sqlScript(str_replace('chwoo_db', $testDb, file_get_contents(__DIR__ . '/../database/seed_products.sql')));
    $stockBefore = $conn->query('SELECT * FROM product_variants ORDER BY variant_id')->fetch_all(MYSQLI_ASSOC);
    putenv('CHWOO_DB_NAME=' . $testDb); putenv('CHWOO_MAIL_PREPARE_ONLY=1');
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $port = (int)substr(strrchr($address, ':'), 1);
    $base = 'http://127.0.0.1:' . $port . '/chwoo';
    $server = proc_open([PHP_BINARY, '-d', 'disable_functions=mail', '-d', 'session.save_path=' . $scratch,
        '-d', 'session.serialize_handler=php_serialize', '-S', '127.0.0.1:' . $port, '-t', dirname(__DIR__, 2)],
        [['pipe','r'], ['file',$log,'a'], ['file',$log,'a']], $pipes, dirname(__DIR__), null, ['bypass_shell'=>true]);
    if (!is_resource($server)) { throw new RuntimeException('Cannot start test server'); }
    fclose($pipes[0]);
    $ready = false;
    for ($i=0; $i<40; $i++) {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $message, 0.1);
        if ($probe) { fclose($probe); $ready = true; break; }
        usleep(100000);
    }
    check($ready, 'Isolated HTTP server ready with mail disabled');
    $customer = browser();
    check(request($customer, 'php/outfit_action.php')['status'] === 405, 'Action accepts only POST');
    check(request($customer, 'php/outfit.php', [])['status'] === 405, 'Board rejects direct POST');
    $data = payload($customer);
    $response = request($customer, 'php/outfit_action.php', $data);
    check($response['status'] === 303 && str_ends_with($response['headers']['location'] ?? '', '/php/outfit.php?bag=open')
        && bag($customer) === [1=>['variant_id'=>1,'quantity'=>1],5=>['variant_id'=>5,'quantity'=>1]], 'Top + Bottom adds exactly one of each in the existing bag format');
    $page = request($customer, 'php/outfit.php?bag=open');
    check(str_contains($page['html'], 'Your outfit was added to your bag!') && str_contains($page['html'], 'data-open="true"')
        && str_contains($page['html'], 'BAG (2)') && substr_count($page['html'], 'class="chwoo-drawer-item"') === 2,
        'Success feedback, existing drawer, header count and two normal items');
    check(str_contains($page['html'], 'name="top_variant" value="1" checked')
        && str_contains($page['html'], 'name="bottom_variant" value="5" checked'), 'Draft sizes survive success redirect');
    $before = bag($customer);
    request($customer, 'php/outfit.php?bag=open'); request($customer, 'php/outfit.php');
    check(bag($customer) === $before, 'Refreshing success GET never repeats additions');
    request($customer, 'php/outfit_action.php', payload($customer, 'dress'));
    check(bag($customer) === $before + [41=>['variant_id'=>41,'quantity'=>1]], 'Dress adds exactly one and preserves existing Top + Bottom');
    $page = request($customer, 'php/cart.php');
    check(str_contains($page['html'], '3 item(s)') && str_contains($page['html'], 'SGD $152.00'), 'Shared cart count and subtotal are correct');
    // Exercise all missing identities, including size encoded by the exact variant ID.
    foreach (['top_product','top_colour','top_variant','bottom_product','bottom_colour','bottom_variant'] as $name) {
        $bad = payload($customer); unset($bad[$name]); rejected($customer, $bad, 'Missing ' . $name);
    }
    foreach (['dress_product','dress_colour','dress_variant'] as $name) {
        $bad = payload($customer, 'dress'); unset($bad[$name]); rejected($customer, $bad, 'Missing ' . $name);
    }
    foreach (['', '0', '-1', '1.0', '1e0', ' 1', '01', '1junk', '999999999999999999999', ['1'], ['nested'=>['1']]] as $value) {
        $bad = payload($customer); $bad['bottom_variant'] = $value;
        rejected($customer, $bad, 'Malformed variant ' . json_encode($value));
    }
    foreach (['top_product','top_colour','outfit_mode','form_token'] as $name) {
        $bad = payload($customer); $bad[$name] = ['invalid']; rejected($customer, $bad, 'Array-shaped ' . $name);
    }
    $bad = payload($customer); $bad['bottom_variant'] = '999999'; rejected($customer, $bad, 'Nonexistent variant');
    $bad = payload($customer); $bad['bottom_variant'] = '1'; rejected($customer, $bad, 'Duplicated variant');
    $bad = payload($customer); $bad['bottom_product']='11'; $bad['bottom_colour']='11'; $bad['bottom_variant']='41';
    rejected($customer, $bad, 'Incorrect category in the second slot');
    $bad = payload($customer); $bad['top_colour']='2'; rejected($customer, $bad, 'Colour does not belong to selected product and variant');
    $bad = payload($customer); $bad['top_product']='8'; rejected($customer, $bad, 'Product identity mismatch');
    $bad = payload($customer); $bad['form_token']='invalid'; rejected($customer, $bad, 'Invalid CSRF');
    $bad = payload($customer); unset($bad['form_token']); rejected($customer, $bad, 'Missing CSRF');
    $bad = payload($customer); $bad['outfit_mode']='other'; rejected($customer, $bad, 'Unknown mode');
    foreach (['dress_variant'=>'41','quantity'=>'50','selections'=>['extra'=>'1']] as $name=>$value) {
        $bad = payload($customer); $bad[$name]=$value; rejected($customer, $bad, 'Unexpected field ' . $name);
    }
    $bad = payload($customer, 'dress'); $bad['top_variant']='1'; rejected($customer, $bad, 'Inactive top selection in dress mode');
    $conn->query('UPDATE products SET is_active=0 WHERE product_id=2');
    rejected($customer, payload($customer), 'Inactive second product');
    $conn->query('UPDATE products SET is_active=1 WHERE product_id=2');
    $conn->query('UPDATE product_variants SET stock_quantity=0 WHERE variant_id=5');
    rejected($customer, payload($customer), 'Sold-out second piece');
    $page = request($customer, 'php/outfit.php');
    check(str_contains($page['html'], 'name="bottom_variant" value="5" checked disabled'), 'Unavailable size remains in draft and is disabled');
    $conn->query('UPDATE product_variants SET stock_quantity=1 WHERE variant_id=5');
    rejected($customer, payload($customer), 'Previously bagged quantity uses all second-piece stock');
    $conn->query('UPDATE product_variants SET stock_quantity=5 WHERE variant_id=5');
    for ($i=0; $i<4; $i++) { request($customer, 'php/outfit_action.php', payload($customer)); }
    check(bag($customer)[1]['quantity'] === 5 && bag($customer)[5]['quantity'] === 5, 'Repeated valid submissions reach the real stock limit');
    rejected($customer, payload($customer), 'Repeated submission cannot exceed stock limits');
    // Existing bad/stale entries must also survive a failed outfit and its redirect.
    $session = sessionData($customer);
    $session['chwoo_cart'][999999] = ['variant_id'=>999999,'quantity'=>1];
    $session['chwoo_cart']['obsolete'] = ['product_id'=>1,'quantity'=>2];
    file_put_contents(sessionFile($customer), serialize($session));
    rejected($customer, payload($customer), 'Unrelated stale and legacy entries preserved');
    $session['chwoo_cart'] = 'invalid existing bag';
    file_put_contents(sessionFile($customer), serialize($session));
    rejected($customer, payload($customer), 'Malformed existing bag preserved');
    // Existing drawer quantity controls return to this board, using the shared safe allowlist.
    $drawer = browser(); request($drawer, 'php/outfit_action.php', payload($drawer));
    $response = request($drawer, 'php/bag_action.php', ['form_token'=>csrf($drawer),'variant_id'=>'1','action'=>'increase',
        'bag_context'=>'drawer','return_to'=>'php/outfit.php']);
    check(str_ends_with($response['headers']['location'] ?? '', '/php/outfit.php?bag=updated')
        && bag($drawer)[1]['quantity'] === 2, 'Shared drawer controls update quantities and return to outfit board');
    $page = request($drawer, 'php/outfit.php?bag=updated');
    check(str_contains($page['html'], 'data-resume="true"') && str_contains($page['html'], 'BAG (3)'), 'Updated drawer resumes with correct count');
    $response = request($drawer, 'php/bag_action.php', ['form_token'=>csrf($drawer),'variant_id'=>'1','action'=>'decrease',
        'bag_context'=>'drawer','return_to'=>'https://evil.example/php/outfit.php']);
    check(str_ends_with($response['headers']['location'] ?? '', '/php/cart.php?bag=updated'), 'Return allowlist continues to reject external URLs');
    // Register a fictitious customer only in the disposable database, then reach existing review.
    $buyer = browser(); request($buyer, 'php/outfit_action.php', payload($buyer));
    request($buyer, 'php/outfit_action.php', payload($buyer, 'dress'));
    request($buyer, 'php/auth_action.php', ['form_token'=>csrf($buyer),'action'=>'register','full_name'=>'Outfit Test',
        'email'=>'task07c@example.com','password'=>'Fictional07c!','confirm_password'=>'Fictional07c!']);
    check(count(bag($buyer)) === 3, 'Authentication preserves outfit bag');
    request($buyer, 'php/checkout.php', ['form_token'=>csrf($buyer),'action'=>'details','full_name'=>'Outfit Test',
        'email'=>'task07c@example.com','phone'=>'81234567','address'=>'1 Test Street','postal_code'=>'012345','notes'=>'']);
    $page = request($buyer, 'php/checkout.php?step=review');
    check($page['status'] === 200 && str_contains($page['html'], 'Cloud Check Top') && str_contains($page['html'], 'Sunday Midi Skirt')
        && str_contains($page['html'], 'Ivory Lace Mini Dress') && str_contains($page['html'], '152.00')
        && strlen(field($page['html'], 'checkout_token')) === 64, 'Existing checkout review accepts all outfit items and exact totals');
    check((int)$conn->query('SELECT COUNT(*) n FROM orders')->fetch_assoc()['n'] === 0
        && $stockBefore === $conn->query('SELECT * FROM product_variants ORDER BY variant_id')->fetch_all(MYSQLI_ASSOC),
        'Adding and reviewing outfits never deducts stock or creates orders');
    $checkoutToken = field($page['html'], 'checkout_token');
    request($buyer, 'php/payment.php', ['form_token'=>csrf($buyer),'checkout_token'=>$checkoutToken,'action'=>'review']);
    $paymentPage = request($buyer, 'php/payment.php');
    $response = request($buyer, 'php/payment.php', ['form_token'=>csrf($buyer),'checkout_token'=>$checkoutToken,'action'=>'success']);
    $order = $conn->query('SELECT * FROM orders')->fetch_assoc();
    $items = $conn->query('SELECT variant_id,quantity,unit_price FROM order_items ORDER BY variant_id')->fetch_all(MYSQLI_ASSOC);
    check($response['status'] === 303 && str_starts_with($response['headers']['location'] ?? '', 'order_confirmation.php?id=')
        && $order['total_amount'] === '152.00' && $order['order_status'] === 'Confirmed'
        && $order['acknowledgement_status'] === 'Prepared'
        && array_column($items, 'variant_id') === ['1','5','41'] && array_column($items, 'quantity') === ['1','1','1']
        && array_column($items, 'unit_price') === ['42.00','48.00','62.00'],
        'Existing confirmed-order workflow saves all outfit items and prices with mail prepared only');
    check(bag($buyer) === [] && (int)$conn->query('SELECT SUM(stock_quantity) n FROM product_variants WHERE variant_id IN (1,5,41)')->fetch_assoc()['n'] === 12,
        'Only confirmed order deducts one per outfit variant and clears the purchased bag');
    $navigationPages = ['index.php'=>'', 'php/newin.php'=>'NEW IN', 'php/shop.php'=>'SHOP',
        'php/collections.php'=>'COLLECTIONS', 'php/product.php?id=1'=>'SHOP', 'php/outfit.php'=>'LOOKBOOK',
        'php/account.php'=>'', 'php/cart.php'=>'', 'php/checkout.php'=>'',
        'php/order_confirmation.php?id=' . $order['order_id']=>''];
    foreach ($navigationPages as $path=>$activeLabel) {
        $page = request($buyer, $path);
        $document = new DOMDocument(); @$document->loadHTML($page['html']); $xpath = new DOMXPath($document);
        $links = $xpath->query('//nav[contains(@class,"navbar")]//a');
        $actual = [];
        foreach ($links as $link) { $actual[trim($link->textContent)] = $link->getAttribute('href'); }
        $active = $xpath->query('//div[@class="main-nav"]/a[@aria-current="page"]');
        check($page['status'] === 200 && $actual === [
            ''=>'/chwoo/index.php', 'NEW IN'=>'/chwoo/php/newin.php', 'SHOP'=>'/chwoo/php/shop.php',
            'COLLECTIONS'=>'/chwoo/php/collections.php', 'LOOKBOOK'=>'/chwoo/php/outfit.php',
            'ACCOUNT'=>'/chwoo/php/account.php', 'BAG (0)'=>'/chwoo/php/cart.php']
            && $links->length === 7
            && $xpath->query('//nav//a[@href="/chwoo/php/outfit.php"]')->length === 1
            && ($activeLabel === '' ? $active->length === 0 : $active->length === 1 && trim($active->item(0)->textContent) === $activeLabel)
            && $xpath->query('//a[contains(@href,"search.html") or contains(@href,"about.html") or contains(@href,"lookbook.html")]')->length === 0,
            'Exact header links, no duplicates or obsolete destinations, and correct active state on ' . $path);
        foreach ($links as $link) {
            check(request($buyer, substr($link->getAttribute('href'), strlen('/chwoo/')))['status'] === 200,
                'Header target resolves from ' . $path . ': ' . ($link->getAttribute('aria-label') ?: trim($link->textContent) ?: 'Home'));
        }
    }
    $guest = browser(); $page = request($guest, 'php/outfit.php');
    check(str_contains($page['html'], 'href="/chwoo/php/login.php"') && str_contains($page['html'], '>LOGIN</a>')
        && !str_contains($page['html'], '>ACCOUNT</a>'), 'Guest header retains LOGIN instead of ACCOUNT');
    foreach (['shop.html'=>'php/shop.php', 'product.html'=>'php/product.php?id=1'] as $legacy=>$destination) {
        $page = request($guest, $legacy);
        $document = new DOMDocument(); @$document->loadHTML($page['html']); $xpath = new DOMXPath($document);
        check(str_contains($page['html'], 'content="0;url=' . $destination . '"')
            && $xpath->query('//nav//a[@href="php/outfit.php" and normalize-space(.)="LOOKBOOK"]')->length === 1
            && $xpath->query('//a[contains(@href,"search.html") or contains(@href,"about.html") or contains(@href,"lookbook.html")]')->length === 0,
            'Legacy entry retains redirect and has no obsolete Search, About or Lookbook destinations: ' . $legacy);
    }
    require __DIR__ . '/task07c2.php';
    require __DIR__ . '/task07d1.php';
    if (in_array('--preview', $argv, true)) {
        echo "BROWSER PREVIEW: $base/php/outfit.php\nPress Enter after browser checks to clean up.\n";
        fgets(STDIN);
    }
} catch (Throwable $error) {
    $failure = $error; fwrite(STDERR, $error->getMessage() . "\n" . $error->getTraceAsString() . "\n");
} finally {
    foreach ($handles as $handle) { curl_close($handle); }
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($failure && is_file($log)) { fwrite(STDERR, implode("\n", array_slice(explode("\n", file_get_contents($log)), -12)) . "\n"); }
    if ($created && preg_match('/^chwoo_task07c_test_[a-f0-9]{12}$/D', $testDb)) { $conn->query('DROP DATABASE `' . $testDb . '`'); }
    foreach (glob($scratch . '/*') as $file) { if (is_file($file)) { unlink($file); } }
    rmdir($scratch);
    $originalDb === false ? putenv('CHWOO_DB_NAME') : putenv('CHWOO_DB_NAME=' . $originalDb);
    $originalMail === false ? putenv('CHWOO_MAIL_PREPARE_ONLY') : putenv('CHWOO_MAIL_PREPARE_ONLY=' . $originalMail);
}
check(liveSnapshot() === $liveBefore, 'Installed database remains unchanged');
$conn->close();
if ($failure) { exit(1); }
echo $checks . " Task 07C checks passed; disposable database, server and sessions removed.\n";
