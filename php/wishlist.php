<?php
// Product-level, account-linked favourites. No catalogue or bag data is copied.
require_once __DIR__ . '/auth.php';
header('Cache-Control: no-store');

function chwooWishlistId($value) {
    return (is_string($value) || is_int($value))
        ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]])
        : false;
}

function chwooWishlistCustomerId() {
    $customer = chwooCurrentCustomer();
    return is_array($customer) ? chwooWishlistId($customer['customer_id'] ?? null) : false;
}

function chwooWishlistCustomerExists($conn, $customerId) {
    $stmt = $conn->prepare('SELECT customer_id FROM customers WHERE customer_id = ?');
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $exists;
}

function chwooWishlistChange($conn, $customerId, $productId, $action) {
    if ($action === 'remove') {
        $stmt = $conn->prepare('SELECT product_id FROM products WHERE product_id = ?');
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) { return 'This piece is no longer available.'; }
        // Ownership is always part of the DELETE, including dashboard removals.
        $stmt = $conn->prepare('DELETE FROM wishlist WHERE customer_id = ? AND product_id = ?');
        $stmt->bind_param('ii', $customerId, $productId);
        $stmt->execute();
        $stmt->close();
        return 'Removed from your wishlist.';
    }
    // INSERT ... SELECT checks current availability; the unique key makes saves idempotent.
    $stmt = $conn->prepare('INSERT INTO wishlist (customer_id, product_id)
        SELECT ?, p.product_id FROM products AS p
        WHERE p.product_id = ? AND p.is_active = 1
          AND EXISTS (SELECT 1 FROM product_colours AS c WHERE c.product_id = p.product_id)
        ON DUPLICATE KEY UPDATE product_id = VALUES(product_id)');
    $stmt->bind_param('ii', $customerId, $productId);
    $stmt->execute();
    $stmt->close();
    // A duplicate save and an unavailable product can both affect zero rows.
    $stmt = $conn->prepare('SELECT w.product_id FROM wishlist AS w
        JOIN products AS p ON p.product_id = w.product_id
        WHERE w.customer_id = ? AND w.product_id = ? AND p.is_active = 1
          AND EXISTS (SELECT 1 FROM product_colours AS c WHERE c.product_id = p.product_id)');
    $stmt->bind_param('ii', $customerId, $productId);
    $stmt->execute();
    $saved = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $saved ? 'Saved to your wishlist.' : 'This piece is no longer available to save.';
}

function chwooWishlistIds($conn, &$error) {
    $error = '';
    $customerId = chwooWishlistCustomerId();
    if (!$customerId) { return []; }
    try {
        $stmt = $conn->prepare('SELECT product_id FROM wishlist WHERE customer_id = ?');
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $ids = [];
        foreach ($stmt->get_result() as $row) { $ids[(int)$row['product_id']] = true; }
        $stmt->close();
        return $ids;
    } catch (mysqli_sql_exception $exception) {
        $error = 'Your wishlist is temporarily unavailable. Please try again later.';
        return null; // Unknown state must not be presented as an empty wishlist.
    }
}

function chwooWishlistItems($conn, $customerId) {
    // Use the current default colour (or first remaining colour), never a saved price.
    $stmt = $conn->prepare('SELECT p.product_id, p.product_name, p.is_active,
            c.colour_id, c.image_path, c.price
        FROM wishlist AS w JOIN products AS p ON p.product_id = w.product_id
        LEFT JOIN product_colours AS c ON c.colour_id = (
            SELECT pc.colour_id FROM product_colours AS pc WHERE pc.product_id = p.product_id
            ORDER BY pc.is_default DESC, pc.colour_id LIMIT 1)
        WHERE w.customer_id = ? ORDER BY w.created_at DESC, w.wishlist_id DESC');
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $items;
}

function chwooWishlistNotice($error) {
    if ($error) {
        echo '<p class="chwoo-wishlist-notice" role="alert">' . chwooAuthEscape($error) . '</p>';
    }
}

function chwooWishlistButton($product, $ids, $text = false) {
    $saved = isset($ids[(int)$product['product_id']]);
    $label = ($saved ? 'Remove ' : 'Save ') . $product['product_name']
        . ($saved ? ' from wishlist' : ' to wishlist');
    ?>
    <button class="wishlist-button chwoo-wishlist-button<?= $saved ? ' is-saved' : '' ?>" type="submit"
        formaction="<?= chwooAuthEscape(chwooAuthRootUrl()) ?>php/wishlist_action.php"
        name="action" value="<?= $saved ? 'remove' : 'save' ?>" formnovalidate
        <?= $ids === null ? 'disabled' : '' ?> aria-pressed="<?= $saved ? 'true' : 'false' ?>"
        aria-label="<?= chwooAuthEscape($ids === null ? 'Wishlist temporarily unavailable' : $label) ?>"
        title="<?= chwooAuthEscape($label) ?>">
        <?php if (!$text): ?><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"
            fill="<?= $saved ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="1.6"
            stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg><?php endif; ?>
        <?php if ($text): ?><span>Remove from wishlist</span><?php endif; ?>
    </button>
    <?php
}

function chwooWishlistForm($product, $ids, $returnTo, $text = false) {
    ?>
    <form method="post" action="<?= chwooAuthEscape(chwooAuthRootUrl()) ?>php/wishlist_action.php"
        class="chwoo-wishlist-form<?= $text ? ' chwoo-wishlist-remove' : '' ?>">
        <input type="hidden" name="form_token" value="<?= chwooAuthEscape($_SESSION['chwoo_form_token']) ?>">
        <input type="hidden" name="product_id" value="<?= (int)$product['product_id'] ?>">
        <input type="hidden" name="return_to" value="<?= chwooAuthEscape($returnTo) ?>">
        <?php chwooWishlistButton($product, $ids, $text); ?>
    </form>
    <?php
}

function chwooWishlistRedirect($returnTo, $message) {
    $_SESSION['chwoo_auth_notice'] = $message;
    header('Location: ' . chwooAuthRootUrl() . chwooReturnTo($returnTo, 'php/shop.php'), true, 303);
    exit;
}

function chwooWishlistAfterAuthentication($conn) {
    if (!isset($_SESSION['chwoo_pending_wishlist_product_id'])) { return; }
    $productId = chwooWishlistId($_SESSION['chwoo_pending_wishlist_product_id']);
    $returnTo = chwooReturnTo($_SESSION['chwoo_pending_wishlist_return_to'] ?? '', 'php/shop.php');
    // Consume once. A repeated login must never toggle a saved product off.
    unset($_SESSION['chwoo_pending_wishlist_product_id'], $_SESSION['chwoo_pending_wishlist_return_to']);
    $message = 'This piece is no longer available to save.';
    if ($productId && ($customerId = chwooWishlistCustomerId())) {
        try {
            $message = chwooWishlistChange($conn, $customerId, $productId, 'save');
        } catch (mysqli_sql_exception $exception) {
            $message = 'You are signed in, but we could not save this piece. Please try its heart again later.';
        }
    }
    unset($_SESSION['chwoo_auth_feedback']);
    chwooWishlistRedirect($returnTo, $message);
}
