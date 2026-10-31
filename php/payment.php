<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/order_mail.php';
header('Cache-Control: no-store');
$identity = chwooCurrentCustomer();
if (!$identity) {
    $_SESSION['chwoo_checkout_login'] = true;
    header('Location: login.php', true, 303); exit;
}
$customerId = (int)$identity['customer_id'];
$stmt = $conn->prepare('SELECT customer_id FROM customers WHERE customer_id = ?');
$stmt->bind_param('i', $customerId); $stmt->execute();
$customerExists = (bool)$stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$customerExists) {
    unset($_SESSION['chwoo_customer'], $_SESSION['chwoo_checkout'], $_SESSION['chwoo_purchase']);
    $_SESSION['chwoo_checkout_login'] = true;
    chwooAuthRedirect('', ['form' => 'Please sign in again before continuing.']);
}
function chwooPaymentReviewError($message) {
    $_SESSION['chwoo_order_error'] = $message;
    header('Location: checkout.php?step=review', true, 303); exit;
}
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf = $_POST['form_token'] ?? '';
        $token = $_POST['checkout_token'] ?? '';
        $action = $_POST['action'] ?? '';
        if (!is_string($csrf) || !hash_equals($_SESSION['chwoo_form_token'], $csrf)) {
            throw new RuntimeException('Please refresh the page and try again.');
        }
        if ($action === 'success') {
            // Academic mock: this POST represents the assumed provider's success response.
            // Reads the unsanitised session bag before bag.php can remove stale entries.
            $result = chwooFinaliseOrder($conn, $customerId, $token);
            if (!$result['replayed']) {
                // A committed purchase survives every acknowledgement failure.
                try { chwooAcknowledgeOrder($conn, $result['order_id'], $customerId); }
                catch (Throwable $mailError) { error_log('CHWOO acknowledgement: ' . $mailError->getMessage()); }
            }
            unset($_SESSION['chwoo_payment_error'], $_SESSION['chwoo_order_error']);
            header('Location: order_confirmation.php?id=' . $result['order_id'], true, 303); exit;
        }
        $purchase = $_SESSION['chwoo_purchase'] ?? [];
        if (!is_string($token) || !hash_equals($purchase['token'] ?? '', $token)
            || ($purchase['customer_id'] ?? null) !== $customerId) {
            throw new RuntimeException('Please return to order review before continuing to payment.');
        }
        if ($action === 'review') {
            require_once __DIR__ . '/bag.php';
            $checkout = $_SESSION['chwoo_checkout'] ?? [];
            $details = chwooCheckoutValues($checkout['details'] ?? []);
            if (($checkout['customer_id'] ?? null) !== $customerId || chwooCheckoutErrors($details)
                || chwooOrderCart($_SESSION['chwoo_cart'] ?? []) !== $purchase['cart']
                || !hash_equals($purchase['signature'], chwooOrderSignature($chwooBagItems, $details))) {
                throw new RuntimeException('Your checkout has changed. Please review your selections and delivery details again.');
            }
            foreach ($chwooBagItems as $item) {
                if ($item['availability']) { throw new RuntimeException('Please resolve stock warnings before continuing to payment.'); }
            }
            $_SESSION['chwoo_purchase']['ready'] = true;
            unset($_SESSION['chwoo_payment_error'], $_SESSION['chwoo_order_error']);
        } elseif ($action === 'failure' && ($purchase['ready'] ?? false)
            && getenv('CHWOO_TEST_PAYMENT_FAILURE') === '1'
            && preg_match('/\Achwoo_task06_test_[a-f0-9]{12}\z/D', getenv('CHWOO_DB_NAME') ?: '')) {
            // Fixture-only unsuccessful provider response, never enabled by a public URL.
            $_SESSION['chwoo_payment_error'] = 'Payment could not be completed. No order was recorded and no stock was deducted. Your bag is saved; please retry or return to review.';
        } else { throw new RuntimeException('Please use the payment controls.'); }
        header('Location: payment.php', true, 303); exit;
    }
    require_once __DIR__ . '/bag.php';
    // History navigation after purchase is an empty checkout, not a review failure.
    if (!$chwooBagItems) {
        header('Location: checkout.php', true, 303); exit;
    }
    $purchase = $_SESSION['chwoo_purchase'] ?? [];
    $checkout = $_SESSION['chwoo_checkout'] ?? [];
    $details = chwooCheckoutValues($checkout['details'] ?? []);
    if (!($purchase['ready'] ?? false) || ($purchase['customer_id'] ?? null) !== $customerId
        || ($checkout['customer_id'] ?? null) !== $customerId || chwooCheckoutErrors($details)) {
        throw new RuntimeException('Please complete order review before continuing to payment.');
    }
    if (chwooOrderCart($_SESSION['chwoo_cart'] ?? []) !== $purchase['cart']
        || !hash_equals($purchase['signature'], chwooOrderSignature($chwooBagItems, $details))) {
        throw new RuntimeException('Your checkout has changed. Please review it again before payment.');
    }
    foreach ($chwooBagItems as $item) {
        if ($item['availability']) { throw new RuntimeException('Please resolve stock warnings before continuing to payment.'); }
    }
} catch (Throwable $error) {
    if ($error instanceof mysqli_sql_exception || !($error instanceof RuntimeException)) {
        error_log('CHWOO order processing: ' . $error->getMessage());
        chwooPaymentReviewError('We could not process your order. Your bag is saved. Please try again later.');
    }
    chwooPaymentReviewError($error->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Payment | CHWOO</title>
<link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/header.css"><link rel="stylesheet" href="../css/footer.css"><link rel="stylesheet" href="../css/bag.css"><link rel="stylesheet" href="../css/checkout.css">
<script src="../js/checkout-history.js" defer></script></head>
<body class="checkout-page">
<?php include __DIR__ . '/header.php'; ?>
<main class="checkout-main">
    <a class="checkout-back" href="checkout.php?step=review">&larr; Return to order review</a>
    <div class="checkout-heading"><p class="checkout-eyebrow">YOUR CHWOO CHECKOUT</p><h1>Payment</h1><p>Complete your order.</p></div>
    <ol class="checkout-progress" aria-label="Checkout progress"><li><span>01</span> Delivery details</li><li><a href="checkout.php?step=review"><span>02</span> Order review</a></li><li aria-current="step"><span>03</span> Payment</li></ol>
    <?php if (!empty($_SESSION['chwoo_payment_error'])): ?><p class="checkout-alert" role="alert"><?= chwooEscape($_SESSION['chwoo_payment_error']) ?></p><?php endif; ?>
    <div class="checkout-layout"><section class="checkout-payment" aria-labelledby="payment-title"><h2 id="payment-title">Ready when you are.</h2><p>Review your order total, then proceed to complete your purchase.</p>
        <form method="post" action="payment.php">
            <input type="hidden" name="form_token" value="<?= chwooEscape($_SESSION['chwoo_form_token']) ?>">
            <input type="hidden" name="checkout_token" value="<?= chwooEscape($purchase['token']) ?>">
            <div class="checkout-payment-actions"><button type="submit" name="action" value="success" class="checkout-button">Proceed to Payment</button><a class="checkout-button checkout-button-secondary" href="checkout.php?step=review">Return to order review</a></div>
        </form>
    </section><aside class="checkout-summary"><p class="checkout-eyebrow">YOUR ORDER</p><h2>Order summary</h2>
        <div class="checkout-mini-products"><?php foreach ($chwooBagItems as $item): ?><div class="checkout-mini-product">
            <?php if ($item['image_url']): ?><img src="<?= chwooEscape($item['image_url']) ?>" alt="<?= chwooEscape($item['name']) ?>"><?php else: ?><span class="checkout-photo-placeholder">Photo unavailable</span><?php endif; ?>
            <div><h3><?= chwooEscape($item['name']) ?></h3><p><?= chwooEscape($item['colour'] . ' / ' . $item['size']) ?></p><p>Quantity: <?= $item['quantity'] ?></p></div><strong>SGD $<?= number_format($item['subtotal'],2) ?></strong>
        </div><?php endforeach; ?></div>
        <dl class="checkout-totals"><div><dt>Subtotal</dt><dd>SGD $<?= number_format($chwooBagTotal, 2) ?></dd></div><div><dt>Delivery</dt><dd><?= $chwooDelivery ? 'SGD $' . number_format($chwooDelivery, 2) : 'Complimentary' ?></dd></div><div class="checkout-grand-total"><dt>Total</dt><dd>SGD $<?= number_format($chwooEstimatedTotal, 2) ?></dd></div></dl>
        <p class="checkout-help">Prices and stock are checked again when you submit. If anything changes, you will return to review.</p></aside></div>
</main>
<footer><div class="footer-logo">CHWOO</div><div class="footer-links"><a href="shop.php">Shop</a><a href="account.php?section=orders">Your Orders</a></div><p>&copy; <?= date('Y') ?> CHWOO Fashion Studio</p></footer>
<?php include __DIR__ . '/bag_drawer.php'; ?></body></html>
<?php $conn->close(); ?>
