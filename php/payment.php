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
            throw new RuntimeException('Please return to order review before simulating payment.');
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
        } elseif ($action === 'failure' && ($purchase['ready'] ?? false)) {
            $_SESSION['chwoo_payment_error'] = 'Simulated payment failed. No order was recorded and no stock was deducted. Your bag is saved; you can retry or return to review.';
        } else { throw new RuntimeException('Please use the simulated payment controls.'); }
        header('Location: payment.php', true, 303); exit;
    }
    $purchase = $_SESSION['chwoo_purchase'] ?? [];
    $checkout = $_SESSION['chwoo_checkout'] ?? [];
    $details = chwooCheckoutValues($checkout['details'] ?? []);
    if (!($purchase['ready'] ?? false) || ($purchase['customer_id'] ?? null) !== $customerId
        || ($checkout['customer_id'] ?? null) !== $customerId || chwooCheckoutErrors($details)) {
        throw new RuntimeException('Please complete order review before continuing to payment.');
    }
    require_once __DIR__ . '/bag.php';
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
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Demonstration Payment | CHWOO</title>
<link rel="stylesheet" href="../css/base.css"><link rel="stylesheet" href="../css/header.css"><link rel="stylesheet" href="../css/footer.css"><link rel="stylesheet" href="../css/bag.css"><link rel="stylesheet" href="../css/checkout.css"></head>
<body class="checkout-page">
<?php include __DIR__ . '/header.php'; ?>
<main class="checkout-main">
    <a class="checkout-back" href="checkout.php?step=review">&larr; Return to order review</a>
    <div class="checkout-heading"><p class="checkout-eyebrow">CHWOO DEMONSTRATION</p><h1>One last step.</h1><p>Simulate a payment outcome for your reviewed order.</p></div>
    <ol class="checkout-progress" aria-label="Checkout progress"><li><span>01</span> Delivery details</li><li><a href="checkout.php?step=review"><span>02</span> Order review</a></li><li aria-current="step"><span>03</span> Simulated payment</li></ol>
    <?php if (!empty($_SESSION['chwoo_payment_error'])): ?><p class="checkout-alert" role="alert"><?= chwooEscape($_SESSION['chwoo_payment_error']) ?></p><?php endif; ?>
    <div class="checkout-layout"><section class="checkout-payment" aria-labelledby="payment-title"><h2 id="payment-title">Demonstration payment</h2>
        <p>No real payment is taken. This step does not ask for card details or banking credentials.</p>
        <p>A successful simulation records your order and deducts the purchased stock. A failed simulation keeps your bag so you can try again.</p>
        <form method="post" action="payment.php">
            <input type="hidden" name="form_token" value="<?= chwooEscape($_SESSION['chwoo_form_token']) ?>">
            <input type="hidden" name="checkout_token" value="<?= chwooEscape($purchase['token']) ?>">
            <div class="checkout-payment-actions"><button type="submit" name="action" value="success" class="checkout-button">Simulate Successful Payment</button><button type="submit" name="action" value="failure" class="checkout-button checkout-button-secondary">Simulate Failed Payment</button></div>
        </form><a href="checkout.php?step=review">Edit / review this order</a>
    </section><aside class="checkout-summary"><p class="checkout-eyebrow">YOUR REVIEWED ORDER</p><h2><?= $chwooBagCount ?> <?= $chwooBagCount === 1 ? 'piece' : 'pieces' ?>, just for you.</h2>
        <dl class="checkout-totals"><div><dt>Subtotal</dt><dd>SGD $<?= number_format($chwooBagTotal, 2) ?></dd></div><div><dt>Delivery</dt><dd><?= $chwooDelivery ? 'SGD $' . number_format($chwooDelivery, 2) : 'Complimentary' ?></dd></div><div class="checkout-grand-total"><dt>Total</dt><dd>SGD $<?= number_format($chwooEstimatedTotal, 2) ?></dd></div></dl>
        <p class="checkout-help">Prices and stock are checked again when you submit. If anything changes, you will return to review.</p></aside></div>
</main>
<footer><div class="footer-logo">CHWOO</div><div class="footer-links"><a href="shop.php">Shop</a><a href="account.php?section=orders">Your Orders</a></div><p>&copy; <?= date('Y') ?> CHWOO Fashion Studio</p></footer>
<?php include __DIR__ . '/bag_drawer.php'; ?></body></html>
<?php $conn->close(); ?>
