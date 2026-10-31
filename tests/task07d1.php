<?php
// Focused regressions, run by task07c.php in its disposable DB/server/session sandbox.
if (PHP_SAPI !== 'cli' || !isset($testDb, $buyer, $paymentPage)
    || !preg_match('/^chwoo_task07c_test_[a-f0-9]{12}$/D', $testDb)) {
    http_response_code(404); exit;
}
$focusedStart = $checks;
foreach (['login', 'register'] as $authAction) {
    foreach (['wishlist', 'orders', 'checkout'] as $destination) {
        if ($authAction === 'login') {
            $conn->query("DELETE w FROM wishlist w JOIN customers c ON c.customer_id=w.customer_id
                WHERE c.email='task07c@example.com' AND w.product_id=8");
        }
        $guest = browser();
        request($guest, 'php/product.php?id=8', ['form_token'=>csrf($guest), 'action'=>'add',
            'product_id'=>'8', 'colour'=>'8', 'size'=>'XS']);
        $beforeBag = bag($guest); $beforeToken = csrf($guest); $beforeSession = sessionFile($guest);
        $save = ['form_token'=>$beforeToken, 'action'=>'save', 'product_id'=>'8',
            'colour'=>'9', 'size'=>'M', 'return_to'=>'php/product.php?id=8'];
        $response = request($guest, 'php/wishlist_action.php', $save);
        check($response['status'] === 303 && str_ends_with($response['headers']['location'] ?? '', '/php/login.php')
            && sessionData($guest)['chwoo_pending_wishlist_return_to'] === 'php/product.php?id=8&colour=9&size=M',
            "$authAction/$destination: guest save retains selected product destination");
        if ($destination === 'orders') {
            request($guest, 'php/account.php?section=orders');
        } elseif ($destination === 'checkout') {
            request($guest, 'php/account.php?section=orders');
            request($guest, 'php/checkout.php');
        }
        $pending = sessionData($guest);
        $savedBefore = (int)$conn->query('SELECT COUNT(*) n FROM wishlist')->fetch_assoc()['n'];
        $credentials = ['form_token'=>$beforeToken, 'action'=>$authAction, 'full_name'=>'Intent Tester',
            'email'=>$authAction === 'login' ? 'task07c@example.com' : "intent-$destination@example.com",
            'password'=>'Fictional07c!', 'confirm_password'=>'Fictional07c!'];
        $invalid = $credentials; $invalid['form_token'] = 'invalid';
        request($guest, 'php/auth_action.php', $invalid);
        check(!isset(sessionData($guest)['chwoo_customer'])
            && (sessionData($guest)['chwoo_pending_wishlist_product_id'] ?? null) === $pending['chwoo_pending_wishlist_product_id']
            && (sessionData($guest)['chwoo_orders_login'] ?? null) === ($pending['chwoo_orders_login'] ?? null),
            "$authAction/$destination: CSRF rejection preserves pending intents without authenticating");
        $invalid = $credentials;
        if ($authAction === 'login') { $invalid['password'] = 'WrongPassword'; }
        else { $invalid['confirm_password'] = 'Mismatch'; }
        request($guest, 'php/auth_action.php', $invalid);
        check(!isset(sessionData($guest)['chwoo_customer'])
            && (sessionData($guest)['chwoo_pending_wishlist_return_to'] ?? '') === $pending['chwoo_pending_wishlist_return_to']
            && (sessionData($guest)['chwoo_orders_login'] ?? null) === ($pending['chwoo_orders_login'] ?? null)
            && (int)$conn->query('SELECT COUNT(*) n FROM wishlist')->fetch_assoc()['n'] === $savedBefore,
            "$authAction/$destination: failed authentication retains return intents");
        // Client-supplied destinations must never override session-owned intents.
        $credentials['return_to'] = 'https://attacker.example/';
        $response = request($guest, 'php/auth_action.php', $credentials);
        $expected = ['wishlist'=>'php/product.php?id=8&colour=9&size=M',
            'orders'=>'php/account.php?section=orders', 'checkout'=>'php/checkout.php'][$destination];
        check($response['status'] === 303 && ($response['headers']['location'] ?? '') === '/chwoo/' . $expected,
            "$authAction/$destination: successful authentication returns to the correct internal destination");
        $state = sessionData($guest); $id = (int)$state['chwoo_customer']['customer_id'];
        check((int)$conn->query("SELECT COUNT(*) n FROM wishlist WHERE customer_id=$id AND product_id=8")->fetch_assoc()['n'] === 1,
            "$authAction/$destination: pending product is saved exactly once");
        check(array_intersect_key($state, array_flip(['chwoo_pending_wishlist_product_id',
                'chwoo_pending_wishlist_return_to', 'chwoo_orders_login', 'chwoo_checkout_login'])) === []
            && bag($guest) === $beforeBag && csrf($guest) === $beforeToken && sessionFile($guest) !== $beforeSession,
            "$authAction/$destination: consumes obsolete intents, preserves bag/CSRF and regenerates session");
        check(request($guest, $expected)['status'] === 200, "$authAction/$destination: destination renders successfully");
        $response = request($guest, 'php/login.php');
        check(($response['headers']['location'] ?? '') === '/chwoo/php/account.php',
            "$authAction/$destination: later login does not replay old return intents");
    }
}
foreach (['https://attacker.example/', '//attacker.example/', 'php/../index.php',
    'php/product.php%0d%0aLocation:https://attacker.example/', ['php/product.php']] as $unsafeReturn) {
    $guest = browser();
    request($guest, 'php/wishlist_action.php', ['form_token'=>csrf($guest), 'action'=>'save',
        'product_id'=>'1', 'return_to'=>$unsafeReturn]);
    $response = request($guest, 'php/auth_action.php', ['form_token'=>csrf($guest), 'action'=>'login',
        'email'=>'task07c@example.com', 'password'=>'Fictional07c!']);
    check(($response['headers']['location'] ?? '') === '/chwoo/php/shop.php',
        'Unsafe wishlist return falls back to the internal Shop after login');
}
$guest = browser();
request($guest, 'php/wishlist_action.php', ['form_token'=>csrf($guest), 'action'=>'save',
    'product_id'=>'1', 'return_to'=>'php/product.php?id=1']);
request($guest, 'php/account.php?section[]=orders');
$response = request($guest, 'php/auth_action.php', ['form_token'=>csrf($guest), 'action'=>'login',
    'email'=>'task07c@example.com', 'password'=>'Fictional07c!']);
check(($response['headers']['location'] ?? '') === '/chwoo/php/product.php?id=1',
    'Malformed Orders section does not supersede a valid wishlist return');

echo ($checks - $focusedStart) . " Task 07D.1 focused checks passed.\n";
