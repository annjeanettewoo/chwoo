<?php
require_once __DIR__ . '/auth.php';
header('Cache-Control: no-store');
$identity = chwooCurrentCustomer();
if (!$identity) { header('Location: login.php', true, 303); exit; }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/order_mail.php';
$orderId = is_string($_GET['id'] ?? null) ? filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
$order = $orderId ? chwooSavedOrder($conn, $orderId, (int)$identity['customer_id']) : null;
if (!$order) { http_response_code(404); }
require_once __DIR__ . '/bag.php';
$mailConfig = require __DIR__ . '/mail_config.php';
$showAcknowledgementPreview = !($order && ($mailConfig['enabled'] ?? false)
    && ($mailConfig['delivery_verified'] ?? false) && ($order['acknowledgement_status'] ?? '') === 'Accepted');
$mailMessages = ['Prepared' => 'Acknowledgement email prepared for testing. It has not been sent.',
    'Accepted' => 'Acknowledgement accepted by the local mail transport. Mailbox delivery has not been verified.',
    'Failed' => 'The acknowledgement email could not be sent. Your confirmed order is unchanged.'];
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= $order ? 'Order #' . (int)$order['order_id'] : 'Order unavailable' ?> | CHWOO</title>
<link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/header.css"><link rel="stylesheet" href="../css/footer.css"><link rel="stylesheet" href="../css/bag.css"><link rel="stylesheet" href="../css/checkout.css"></head>
<body class="checkout-page">
<?php include __DIR__ . '/header.php'; ?>
<main class="checkout-main">
    <a class="checkout-back" href="account.php?section=orders">&larr; Your order history</a>
    <?php if (!$order): ?>
    <div class="checkout-heading"><h1>Order unavailable.</h1><p>We couldn’t find this order in your account.</p></div>
    <?php else: ?>
    <div class="checkout-heading"><p class="checkout-eyebrow">ORDER #<?= (int)$order['order_id'] ?></p><h1>Your pieces are confirmed.</h1><p>Your order has been recorded. Thank you for shopping with CHWOO.</p></div>
    <dl class="checkout-confirmation-status"><div><dt>Order status</dt><dd><?= chwooEscape($order['order_status']) ?></dd></div><div><dt>Order date</dt><dd><?= chwooEscape(date('j F Y, g:i A', strtotime($order['ordered_at']))) ?></dd></div><div><dt>Payment status</dt><dd><?= chwooEscape($order['payment_status']) ?></dd></div></dl>
    <div class="checkout-layout"><div>
        <section aria-labelledby="purchased-title"><div class="checkout-section-heading"><h2 id="purchased-title">Your purchased pieces</h2></div>
        <?php foreach ($order['items'] as $item): ?>
        <?php $imageUrl = chwooImageUrl($item['product_image_path'] ?? '', $chwooRootUrl); ?>
        <article class="checkout-product">
            <div class="checkout-product-image"><?php if ($imageUrl): ?><img src="<?= chwooEscape($imageUrl) ?>" alt="<?= chwooEscape($item['product_name'] . ' in ' . $item['selected_colour']) ?>"><?php else: ?><span>Product photo unavailable</span><?php endif; ?></div>
            <div class="checkout-product-info"><h3><?= chwooEscape($item['product_name']) ?></h3><p>Colour: <?= chwooEscape($item['selected_colour']) ?> &middot; Size: <?= chwooEscape($item['selected_size']) ?></p><p>Quantity: <?= (int)$item['quantity'] ?></p><p>Unit price: SGD $<?= chwooEscape($item['unit_price']) ?></p></div>
            <p class="checkout-line-total"><span>Line total</span><strong>SGD $<?= chwooMoney(chwooPriceCents($item['unit_price']) * (int)$item['quantity']) ?></strong></p>
        </article>
        <?php endforeach; ?></section>
        <section class="checkout-delivery-review" aria-labelledby="delivery-title"><div class="checkout-section-heading"><h2 id="delivery-title">Delivery details</h2></div>
            <dl class="checkout-delivery-details"><div><dt>Full name</dt><dd><?= chwooEscape($order['recipient_name']) ?></dd></div><div><dt>Email address</dt><dd><?= chwooEscape($order['contact_email']) ?></dd></div><div><dt>Contact number</dt><dd><?= chwooEscape($order['contact_phone']) ?></dd></div><div><dt>Delivery address</dt><dd><?= nl2br(chwooEscape($order['delivery_address'])) ?></dd></div></dl>
        </section>
        <?php if ($showAcknowledgementPreview): ?>
        <section class="checkout-acknowledgement" aria-labelledby="mail-title"><h2 id="mail-title">Order acknowledgement</h2><p><?= chwooEscape($mailMessages[$order['acknowledgement_status'] ?? 'Prepared']) ?></p>
            <details><summary>View acknowledgement email text</summary><p>This is the prepared email content; this preview does not establish delivery.</p><pre><?= chwooEscape(chwooAcknowledgementText($order)) ?></pre></details>
        </section>
        <?php endif; ?>
    </div><aside class="checkout-summary"><p class="checkout-eyebrow">ORDER #<?= (int)$order['order_id'] ?></p><h2>Your order total</h2>
        <dl class="checkout-totals"><div><dt>Subtotal</dt><dd>SGD $<?= chwooEscape($order['item_subtotal']) ?></dd></div><div><dt>Delivery</dt><dd>SGD $<?= chwooEscape($order['shipping_charge']) ?></dd></div><div class="checkout-grand-total"><dt>Final total</dt><dd>SGD $<?= chwooEscape($order['total_amount']) ?></dd></div></dl>
        <a class="checkout-button" href="account.php?section=orders">View your orders</a><p><a href="shop.php">Continue shopping &rarr;</a></p>
    </aside></div>
    <?php endif; ?>
</main>
<footer><div class="footer-logo">CHWOO</div><div class="footer-links"><a href="shop.php">Shop</a><a href="account.php?section=orders">Your Orders</a></div><p>&copy; <?= date('Y') ?> CHWOO Fashion Studio</p></footer>
<?php include __DIR__ . '/bag_drawer.php'; ?></body></html>
<?php $conn->close(); ?>
