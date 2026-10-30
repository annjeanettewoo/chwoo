<?php
require_once __DIR__ . '/wishlist.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Please use the wishlist controls.');
}
$returnTo = chwooReturnTo($_POST['return_to'] ?? '', 'php/shop.php');
$token = $_POST['form_token'] ?? '';
if (!is_string($token) || !hash_equals($_SESSION['chwoo_form_token'], $token)) {
    chwooWishlistRedirect($returnTo, 'Please refresh the page and try again.');
}
$productId = chwooWishlistId($_POST['product_id'] ?? null);
$action = $_POST['action'] ?? '';
if (!$productId || !in_array($action, ['save', 'remove'], true)) {
    chwooWishlistRedirect($returnTo, 'Invalid wishlist selection.');
}
// The product form submits its current radios even without JavaScript.
if (parse_url($returnTo, PHP_URL_PATH) === 'php/product.php') {
    $query = ['id' => $productId];
    if ($colourId = chwooWishlistId($_POST['colour'] ?? null)) { $query['colour'] = $colourId; }
    if (in_array($_POST['size'] ?? '', ['XS', 'S', 'M', 'L'], true)) { $query['size'] = $_POST['size']; }
    $returnTo = 'php/product.php?' . http_build_query($query);
}
require_once __DIR__ . '/db.php';
try {
    $customerId = chwooWishlistCustomerId();
    if ($customerId && !chwooWishlistCustomerExists($conn, $customerId)) {
        unset($_SESSION['chwoo_customer']);
        session_regenerate_id(true);
        $customerId = false;
    }
    if (!$customerId) {
        if ($action !== 'save') {
            chwooWishlistRedirect($returnTo, 'Please sign in to manage your wishlist.');
        }
        $stmt = $conn->prepare('SELECT p.product_id FROM products AS p WHERE p.product_id = ?
            AND p.is_active = 1 AND EXISTS (SELECT 1 FROM product_colours AS c WHERE c.product_id = p.product_id)');
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$product) { chwooWishlistRedirect($returnTo, 'This piece is no longer available to save.'); }
        $_SESSION['chwoo_pending_wishlist_product_id'] = $productId;
        $_SESSION['chwoo_pending_wishlist_return_to'] = $returnTo;
        chwooAuthRedirect('Please sign in or create an account to save this piece.');
    }
    $message = chwooWishlistChange($conn, $customerId, $productId, $action);
} catch (mysqli_sql_exception $exception) {
    $message = 'We could not update your wishlist. Please try again later.';
}
chwooWishlistRedirect($returnTo, $message);
