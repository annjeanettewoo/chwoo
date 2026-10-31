<?php
require_once __DIR__ . '/auth.php';
header('Cache-Control: no-store');
$identity = chwooCurrentCustomer();
if (!$identity) {
    header('Location: ' . chwooAuthRootUrl() . 'php/login.php', true, 303);
    exit;
}
require_once __DIR__ . '/db.php';
$customerId = (int)$identity['customer_id'];
$stmt = $conn->prepare('SELECT full_name, email, created_at FROM customers WHERE customer_id = ?');
$stmt->bind_param('i', $customerId);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$customer) {
    // An account removed from the database must not retain authenticated access.
    unset($_SESSION['chwoo_customer']);
    session_regenerate_id(true);
    chwooAuthRedirect('', ['form' => 'Please log in again.']);
}
$sections = ['dashboard' => 'Dashboard', 'orders' => 'Orders', 'wishlist' => 'Wishlist',
             'details' => 'Account Details', 'cards' => 'Saved Cards'];
$section = $_GET['section'] ?? 'dashboard';
if (!is_string($section) || !isset($sections[$section])) { $section = 'dashboard'; }
$orders = [];
if (in_array($section, ['dashboard', 'orders'], true)) {
    $sql = 'SELECT order_id, ordered_at, total_amount, order_status FROM orders
            WHERE customer_id = ? ORDER BY ordered_at DESC, order_id DESC';
    if ($section === 'dashboard') { $sql .= ' LIMIT 5'; }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
require_once __DIR__ . '/bag.php';
require_once __DIR__ . '/wishlist.php';
$wishlistItems = [];
$wishlistError = '';
if ($section === 'wishlist') {
    try {
        $wishlistItems = chwooWishlistItems($conn, $customerId);
    } catch (mysqli_sql_exception $exception) {
        $wishlistError = 'Your wishlist is temporarily unavailable. Please try again later.';
    }
}
$firstName = explode(' ', $customer['full_name'])[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= chwooAuthEscape($sections[$section]) ?> | Your CHWOO</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="../css/bag.css">
    <link rel="stylesheet" href="../css/account.css">
    <link rel="stylesheet" href="../css/wishlist.css">
</head>
<body class="account-page">
<?php include __DIR__ . '/header.php'; ?>
<main class="account-layout">
    <aside class="account-sidebar" aria-label="Your account">
        <p class="account-eyebrow">YOUR CHWOO</p>
        <p class="account-sidebar-title">A little space<br>just for you.</p>
        <nav class="account-nav" aria-label="Account navigation">
            <?php foreach ($sections as $key => $label): ?>
            <a href="account.php?section=<?= $key ?>" <?= $section === $key ? 'aria-current="page"' : '' ?>><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <form class="account-logout" method="post" action="auth_action.php">
            <input type="hidden" name="form_token" value="<?= chwooAuthEscape($_SESSION['chwoo_form_token']) ?>">
            <button type="submit" name="action" value="logout">Log out</button>
        </form>
    </aside>
    <div class="account-content">
        <div class="account-heading">
            <p class="account-eyebrow"><?= $section === 'dashboard' ? 'MAKE YOURSELF AT HOME' : 'YOUR ACCOUNT' ?></p>
            <h1><?= $section === 'dashboard' ? 'Hello, ' . chwooAuthEscape($firstName) . '.' : $sections[$section] ?></h1>
            <p><?= $section === 'dashboard' ? 'Good to see you. Find your details, your orders and a little inspiration.' : 'All your CHWOO essentials, in one place.' ?></p>
        </div>
        <?php if ($section === 'dashboard'): ?>
        <section class="account-welcome" aria-labelledby="welcome-title">
            <div><p class="account-eyebrow">EVERYDAY, WITH PERSONALITY</p><h2 id="welcome-title">Your next favourite<br>is waiting.</h2><a class="account-button" href="shop.php">Explore the collection &rarr;</a></div>
            <p class="account-welcome-note">A little playful.<br>A little unexpected.<br>Always you.</p>
        </section>
        <?php endif; ?>
        <?php if (in_array($section, ['dashboard', 'details'], true)): ?>
        <section class="account-section" aria-labelledby="details-title">
            <div class="account-section-heading"><h2 id="details-title">Account details</h2><?php if ($section === 'dashboard'): ?><a href="account.php?section=details">View details &rarr;</a><?php endif; ?></div>
            <dl class="account-details">
                <div><dt>Full name</dt><dd><?= chwooAuthEscape($customer['full_name']) ?></dd></div>
                <div><dt>Email address</dt><dd><?= chwooAuthEscape($customer['email']) ?></dd></div>
                <div><dt>With CHWOO since</dt><dd><?= chwooAuthEscape(date('j F Y', strtotime($customer['created_at']))) ?></dd></div>
            </dl>
        </section>
        <?php endif; ?>
        <?php if (in_array($section, ['dashboard', 'orders'], true)): ?>
        <section class="account-section" aria-labelledby="orders-title">
            <div class="account-section-heading"><h2 id="orders-title"><?= $section === 'dashboard' ? 'Recent orders' : 'Order history' ?></h2><?php if ($section === 'dashboard'): ?><a href="account.php?section=orders">View all &rarr;</a><?php endif; ?></div>
            <?php if (!$orders): ?>
            <div class="account-empty"><span class="account-empty-mark" aria-hidden="true">&#10033;</span><h3>You haven't placed any orders yet.</h3><p>Every favourite starts with a little browse.</p><a class="account-text-link" href="shop.php">Find something you love &rarr;</a></div>
            <?php else: ?>
            <div class="account-orders"><table><caption class="account-sr-only">Your <?= $section === 'dashboard' ? 'five most recent orders' : 'order history' ?></caption><thead><tr><th scope="col">Order</th><th scope="col">Date</th><th scope="col">Total</th><th scope="col">Status</th><th scope="col">Details</th></tr></thead><tbody>
                <?php foreach ($orders as $order): ?>
                <tr><td>#<?= (int)$order['order_id'] ?></td><td><?= chwooAuthEscape(date('j M Y', strtotime($order['ordered_at']))) ?></td><td>SGD $<?= number_format((float)$order['total_amount'], 2) ?></td><td><span class="account-order-status"><?= chwooAuthEscape($order['order_status']) ?></span></td><td><a class="account-text-link" href="order_confirmation.php?id=<?= (int)$order['order_id'] ?>" aria-label="View details of order <?= (int)$order['order_id'] ?>">View details</a></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        <?php if ($section === 'wishlist'): ?>
        <section class="account-section" aria-label="Saved products">
            <?php if ($wishlistError): chwooWishlistNotice($wishlistError); ?>
            <?php elseif (!$wishlistItems): ?>
            <div class="account-empty"><span class="account-empty-mark" aria-hidden="true">&#9825;</span><h2>A home for your favourites.</h2><p>See something you love? Tap its heart.<br>Your saved pieces will be waiting here.</p><a class="account-text-link" href="shop.php">Find your favourites &rarr;</a></div>
            <?php else: ?>
            <h2>Your favourite pieces</h2>
            <p class="account-wishlist-intro">Saved by product. Images and prices show the current default colour.</p>
            <div class="account-wishlist-grid">
                <?php foreach ($wishlistItems as $item): ?>
                <?php
                $imageUrl = chwooImageUrl((string)($item['image_path'] ?? ''), $chwooRootUrl);
                $available = (int)$item['is_active'] === 1 && $item['colour_id'] !== null;
                ?>
                <article class="account-wishlist-card">
                    <?php if ($available): ?><a class="account-wishlist-link" href="product.php?id=<?= (int)$item['product_id'] ?>"><?php endif; ?>
                        <div class="account-wishlist-photo">
                            <?php if ($imageUrl): ?><img src="<?= chwooEscape($imageUrl) ?>" alt="<?= chwooEscape($item['product_name']) ?>" loading="lazy">
                            <?php else: ?><span>Product photo unavailable</span><?php endif; ?>
                        </div>
                        <h3><?= chwooEscape($item['product_name']) ?></h3>
                    <?php if ($available): ?></a><?php endif; ?>
                    <?php if ($available): ?><p class="account-wishlist-price">SGD $<?= number_format((float)$item['price'], 2) ?></p>
                    <?php else: ?><p class="account-wishlist-unavailable">Currently unavailable</p><?php endif; ?>
                    <?php chwooWishlistForm($item, [(int)$item['product_id'] => true], 'php/account.php?section=wishlist', true); ?>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        <?php if ($section === 'cards'): ?>
        <section class="account-section"><div class="account-empty"><span class="account-empty-mark" aria-hidden="true">&mdash;</span><h2>No saved cards.</h2><p>Saving payment cards is not available at CHWOO.<br>There are no payment cards stored in your account.</p><a class="account-text-link" href="shop.php">Back to the collection &rarr;</a></div></section>
        <?php endif; ?>
    </div>
</main>
<footer><div class="footer-logo">CHWOO</div><div class="footer-links"><a href="shop.php">Shop</a><a href="newin.php">New In</a><a href="collections.php">Collections</a><a href="account.php">Your Account</a></div><p>&copy; <?= date('Y') ?> CHWOO Fashion Studio</p></footer>
<?php include __DIR__ . '/bag_drawer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>
