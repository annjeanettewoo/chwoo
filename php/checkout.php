<?php
// Prepare and review a bag; optional address saving is the only database write here.
require_once __DIR__ . '/bag.php';
require_once __DIR__ . '/checkout_helpers.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/saved_details.php';
header('Cache-Control: no-store');
// An empty bag has no active review/payment state or checkout errors to resume.
if (!$chwooBagItems) {
    unset($_SESSION['chwoo_checkout_feedback'], $_SESSION['chwoo_purchase'],
        $_SESSION['chwoo_order_error'], $_SESSION['chwoo_payment_error']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Location: checkout.php', true, 303);
        exit;
    }
}
$identity = chwooCurrentCustomer();
$customer = null;
if ($chwooBagItems && !$identity) {
    $_SESSION['chwoo_checkout_login'] = true;
    if ($chwooBagMessage !== '') { $_SESSION['chwoo_bag_flash'] = $chwooBagMessage; }
    header('Location: ' . $chwooRootUrl . 'php/login.php', true, 303);
    exit;
}
if ($identity) {
    $customerId = (int)$identity['customer_id'];
    $stmt = $conn->prepare('SELECT full_name, email FROM customers WHERE customer_id = ?');
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$customer) {
        unset($_SESSION['chwoo_customer'], $_SESSION['chwoo_checkout'], $_SESSION['chwoo_checkout_feedback']);
        $_SESSION['chwoo_checkout_login'] = true;
        session_regenerate_id(true);
        chwooAuthRedirect('', ['form' => 'Please log in again to continue to checkout.']);
    }
}
if (isset($_SESSION['chwoo_checkout'])
    && ($_SESSION['chwoo_checkout']['customer_id'] ?? null) !== ($identity['customer_id'] ?? null)) {
    unset($_SESSION['chwoo_checkout'], $_SESSION['chwoo_checkout_feedback']);
}
$saved = $_SESSION['chwoo_checkout']['details'] ?? [];
$values = chwooCheckoutValues($saved ?: [
    'full_name' => $customer['full_name'] ?? '', 'email' => $customer['email'] ?? ''
]);
$step = $chwooBagItems && ($_GET['step'] ?? '') === 'review' ? 'review' : 'details';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['form_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['chwoo_form_token'], $token)) {
        $errors['form'] = 'Please refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'use_address' && $identity) {
        $id = is_string($_POST['address_id'] ?? null) ? filter_var($_POST['address_id'], FILTER_VALIDATE_INT) : false;
        $address = $id ? (chwooSavedDetails($conn, $customerId, 'address', $id)[0] ?? null) : null;
        if ($address) {
            $values = chwooCheckoutValues(['full_name'=>$address['recipient_name'], 'email'=>$address['contact_email'],
                'phone'=>$address['contact_phone'], 'address'=>$address['address'], 'postal_code'=>$address['postal_code'], 'notes'=>$address['notes']]);
        } else { $errors['form'] = 'Please select an address from your account.'; }
    } elseif (($_POST['action'] ?? '') !== 'details') {
        $errors['form'] = 'Please use the delivery details form.';
    } elseif (!$chwooBagItems || !$identity) {
        $errors['form'] = 'Your bag is empty. Please add a product before continuing.';
    } else {
        $values = chwooCheckoutValues($_POST);
        $errors = chwooCheckoutErrors($values);
        if (!$errors && ($_POST['save_address'] ?? '') === '1') {
            try { chwooSaveAddress($conn, $customerId, $values); }
            catch (mysqli_sql_exception $error) { $errors['form'] = 'We could not save this address. Please try again or continue with the save option unchecked.'; }
        }
        if (!$errors) {
            $_SESSION['chwoo_checkout'] = ['customer_id' => $customerId, 'details' => $values];
            unset($_SESSION['chwoo_checkout_feedback'], $_SESSION['chwoo_order_error'], $_SESSION['chwoo_payment_error']);
            // Preserve availability warnings removed by the canonical bag loader.
            if ($chwooBagMessage !== '') { $_SESSION['chwoo_bag_flash'] = $chwooBagMessage; }
            header('Location: checkout.php?step=review', true, 303);
            exit;
        }
    }
    $_SESSION['chwoo_checkout_feedback'] = ['values' => $values, 'errors' => $errors];
    if ($chwooBagMessage !== '') { $_SESSION['chwoo_bag_flash'] = $chwooBagMessage; }
    header('Location: checkout.php', true, 303);
    exit;
}
$feedback = $_SESSION['chwoo_checkout_feedback'] ?? [];
unset($_SESSION['chwoo_checkout_feedback']);
if ($feedback) {
    $values = $feedback['values'];
    $errors = $feedback['errors'];
    $step = 'details';
}
if ($chwooBagItems && $step === 'review' && (!$saved || chwooCheckoutErrors(chwooCheckoutValues($saved)))) {
    if ($chwooBagMessage !== '') { $_SESSION['chwoo_bag_flash'] = $chwooBagMessage; }
    header('Location: checkout.php', true, 303);
    exit;
}
// Consume payment-to-review feedback once, after any canonical review redirect.
$orderError = $_SESSION['chwoo_order_error'] ?? '';
unset($_SESSION['chwoo_order_error']);
$stockErrors = array_filter($chwooBagItems, function ($item) { return $item['availability'] !== ''; });
$purchaseToken = $step === 'review' && $chwooBagItems && !$stockErrors
    ? chwooPreparePurchase($chwooBagItems, $values, $customerId) : '';
$drawerOpen = in_array($_GET['bag'] ?? '', ['open', 'updated'], true);
$notice = $drawerOpen ? '' : $chwooBagMessage;
if (!$drawerOpen) { $chwooBagMessage = ''; }

function chwooCheckoutField($name, $label, $type, $autocomplete, $attributes = '', $optional = false) {
    global $values, $errors;
    $error = $errors[$name] ?? '';
    ?>
    <div class="checkout-field checkout-field-<?= $name ?>">
        <label for="<?= $name ?>"><?= $label ?><?= $optional ? ' <span>(optional)</span>' : '' ?></label>
        <?php if ($type === 'textarea'): ?>
        <textarea id="<?= $name ?>" name="<?= $name ?>" rows="<?= $name === 'address' ? 3 : 2 ?>" autocomplete="<?= $autocomplete ?>"
            <?= $optional ? '' : 'required' ?> <?= $attributes ?> aria-describedby="<?= $name ?>-error" aria-invalid="<?= $error ? 'true' : 'false' ?>"><?= chwooEscape($values[$name]) ?></textarea>
        <?php else: ?>
        <input id="<?= $name ?>" name="<?= $name ?>" type="<?= $type ?>" autocomplete="<?= $autocomplete ?>"
            required <?= $attributes ?> value="<?= chwooEscape($values[$name]) ?>" aria-describedby="<?= $name ?>-error" aria-invalid="<?= $error ? 'true' : 'false' ?>">
        <?php endif; ?>
        <p id="<?= $name ?>-error" class="checkout-field-error" aria-live="polite" <?= $error ? '' : 'hidden' ?>><?= chwooEscape($error) ?></p>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $step === 'review' ? 'Order Review' : 'Checkout' ?> | CHWOO</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="../css/bag.css">
    <link rel="stylesheet" href="../css/checkout.css">
    <script src="../js/checkout-validation.js" defer></script>
    <script src="../js/checkout-history.js" defer></script>
</head>
<body class="checkout-page">
<?php include __DIR__ . '/header.php'; ?>
<main class="checkout-main">
    <?php if (!$chwooBagItems): ?>
    <section class="checkout-empty" aria-labelledby="empty-bag-title">
        <span class="checkout-empty-accent" aria-hidden="true">✦</span>
        <p class="checkout-eyebrow">YOUR NEXT FAVOURITES</p>
        <h1 id="empty-bag-title">Your bag is feeling a little lonely.</h1>
        <p>A little something caught your eye? Find your next favourite piece.</p>
        <?php if ($notice): ?><p class="checkout-alert" role="alert"><?= chwooEscape($notice) ?></p><?php endif; ?>
        <a class="checkout-button" href="shop.php">Explore the collection &rarr;</a>
    </section>
    <?php else: ?>
    <a class="checkout-back" href="cart.php?from=checkout">&larr; Back to bag</a>
    <div class="checkout-heading">
        <p class="checkout-eyebrow">YOUR NEXT FAVOURITES</p>
        <h1><?= $step === 'review' ? 'A final look.' : 'Make it yours.' ?></h1>
        <p><?= $step === 'review' ? 'Check your pieces and delivery details. There’s still room for a little change.' : 'A few details, then a chance to review everything.' ?></p>
    </div>
    <ol class="checkout-progress" aria-label="Checkout progress">
        <li <?= $step === 'details' ? 'aria-current="step"' : '' ?>><?= $step === 'review' ? '<a href="checkout.php">' : '' ?><span>01</span> Delivery details<?= $step === 'review' ? '</a>' : '' ?></li>
        <li <?= $step === 'review' ? 'aria-current="step"' : '' ?>><span>02</span> Order review</li>
        <li class="checkout-upcoming"><span>03</span> Payment</li>
    </ol>
    <?php if ($notice): ?><p class="checkout-alert" role="alert"><?= chwooEscape($notice) ?></p><?php endif; ?>
    <?php if ($orderError): ?><p class="checkout-alert" role="alert"><?= chwooEscape($orderError) ?></p><?php endif; ?>
    <?php if ($stockErrors): ?><p class="checkout-alert" role="alert">Some selections have limited or no stock. Please reduce their quantities, remove them or choose another variant below. Your bag has not been reserved.</p><?php endif; ?>
    <div class="checkout-layout">
        <div class="checkout-content">
        <?php if ($step === 'details'): ?>
            <section aria-labelledby="delivery-title">
                <div class="checkout-section-heading"><h2 id="delivery-title">Delivery details</h2><span>Singapore delivery</span></div>
                <p class="checkout-help">All fields are required unless marked optional. You can choose to save this address for future checkouts.</p>
                <?php if (!empty($errors['form'])): ?><p class="checkout-alert" role="alert"><?= chwooEscape($errors['form']) ?></p><?php endif; ?>
                <?php if ($errors && empty($errors['form'])): ?><p class="checkout-alert" role="alert">Please check the highlighted fields.</p><?php endif; ?>
                <?php $addresses = chwooSavedDetails($conn, $customerId, 'address'); ?>
                <?php if ($addresses): ?><form method="post" action="checkout.php" class="checkout-saved-choice">
                    <input type="hidden" name="form_token" value="<?= chwooEscape($_SESSION['chwoo_form_token']) ?>">
                    <label for="address_id">Use a saved address</label><select id="address_id" name="address_id">
                    <?php foreach ($addresses as $address): ?><option value="<?= (int)$address['id'] ?>"><?= chwooEscape($address['recipient_name'] . ' — ' . $address['address'] . ', ' . $address['postal_code']) ?></option><?php endforeach; ?>
                    </select><button type="submit" name="action" value="use_address" class="checkout-button checkout-button-secondary">Use address</button>
                </form><?php endif; ?>
                <form method="post" action="checkout.php" data-checkout-form>
                    <input type="hidden" name="form_token" value="<?= chwooEscape($_SESSION['chwoo_form_token']) ?>">
                    <input type="hidden" name="action" value="details">
                    <div class="checkout-fields">
                        <?php chwooCheckoutField('full_name', 'Full name', 'text', 'name', 'maxlength="120"'); ?>
                        <?php chwooCheckoutField('email', 'Email address', 'email', 'email', 'maxlength="190"'); ?>
                        <?php chwooCheckoutField('phone', 'Contact number', 'tel', 'tel', 'maxlength="30"'); ?>
                        <?php chwooCheckoutField('address', 'Delivery address', 'textarea', 'street-address', 'maxlength="1000"'); ?>
                        <?php chwooCheckoutField('postal_code', 'Singapore postal code', 'text', 'postal-code', 'inputmode="numeric" pattern="[0-9]{6}" maxlength="6"'); ?>
                        <?php chwooCheckoutField('notes', 'Delivery notes', 'textarea', 'off', 'maxlength="500"', true); ?>
                    </div>
                    <label class="checkout-save-option"><input type="checkbox" name="save_address" value="1"> Save this address for next time</label>
                    <div class="checkout-form-actions"><a href="cart.php?from=checkout">Edit bag</a><button type="submit" class="checkout-button">Continue to order review &rarr;</button></div>
                </form>
            </section>
        <?php else: ?>
            <section aria-labelledby="review-products-title" id="review-products">
                <div class="checkout-section-heading"><h2 id="review-products-title">Your selected pieces</h2><a href="cart.php?from=checkout">Edit bag</a></div>
                <p class="checkout-help">Check each colour, size and quantity. To change a colour or size, remove that selection and choose its replacement on the product page. Then return through Checkout in your bag.</p>
                <?php foreach ($chwooBagItems as $item): ?>
                <?php $productUrl = 'product.php?' . http_build_query(['id' => $item['product_id'], 'colour' => $item['colour_id'], 'size' => $item['size']]); ?>
                <article class="checkout-product">
                    <a class="checkout-product-image" href="<?= chwooEscape($productUrl) ?>">
                        <?php if ($item['image_url']): ?><img src="<?= chwooEscape($item['image_url']) ?>" alt="<?= chwooEscape($item['name'] . ' in ' . $item['colour']) ?>"><?php else: ?><span>Product photo unavailable</span><?php endif; ?>
                    </a>
                    <div class="checkout-product-info">
                        <h3><a href="<?= chwooEscape($productUrl) ?>"><?= chwooEscape($item['name']) ?></a></h3>
                        <p>Colour: <?= chwooEscape($item['colour']) ?> <span>&middot;</span> Size: <?= chwooEscape($item['size']) ?></p>
                        <p>Unit price: SGD $<?= number_format($item['price'], 2) ?></p>
                        <p>Quantity: <?= $item['quantity'] ?></p>
                        <?php chwooBagControls($item, 'php/checkout.php?step=review', 'checkout'); ?>
                        <?php if ($item['availability']): ?><p class="checkout-field-error" role="alert"><?= chwooEscape($item['availability']) ?></p><?php endif; ?>
                        <a class="checkout-product-edit" href="<?= chwooEscape($productUrl) ?>">View / change colour or size &rarr;</a>
                    </div>
                    <p class="checkout-line-total"><span>Line subtotal</span><strong>SGD $<?= number_format($item['subtotal'], 2) ?></strong></p>
                </article>
                <?php endforeach; ?>
            </section>
            <section class="checkout-delivery-review" aria-labelledby="review-delivery-title">
                <div class="checkout-section-heading"><h2 id="review-delivery-title">Deliver to</h2><a href="checkout.php">Edit details</a></div>
                <dl class="checkout-delivery-details">
                    <div><dt>Full name</dt><dd><?= chwooEscape($values['full_name']) ?></dd></div>
                    <div><dt>Email address</dt><dd><?= chwooEscape($values['email']) ?></dd></div>
                    <div><dt>Contact number</dt><dd><?= chwooEscape($values['phone']) ?></dd></div>
                    <div><dt>Delivery address</dt><dd><?= nl2br(chwooEscape($values['address'])) ?><br>Singapore <?= chwooEscape($values['postal_code']) ?></dd></div>
                    <?php if ($values['notes'] !== ''): ?><div><dt>Delivery notes</dt><dd><?= nl2br(chwooEscape($values['notes'])) ?></dd></div><?php endif; ?>
                </dl>
            </section>
        <?php endif; ?>
        </div>
        <aside class="checkout-summary" aria-labelledby="summary-title">
            <p class="checkout-eyebrow">A LITTLE SOMETHING FOR YOU</p>
            <h2 id="summary-title">Order summary <span>(<?= $chwooBagCount ?> <?= $chwooBagCount === 1 ? 'item' : 'items' ?>)</span></h2>
            <?php if ($step === 'details'): ?>
            <div class="checkout-mini-products">
                <?php foreach ($chwooBagItems as $item): ?>
                <div class="checkout-mini-product">
                    <?php if ($item['image_url']): ?><img src="<?= chwooEscape($item['image_url']) ?>" alt="<?= chwooEscape($item['name'] . ' in ' . $item['colour']) ?>"><?php else: ?><span class="checkout-photo-placeholder">Photo unavailable</span><?php endif; ?>
                    <div><h3><?= chwooEscape($item['name']) ?></h3><p><?= chwooEscape($item['colour']) ?> / <?= chwooEscape($item['size']) ?></p><p>Qty <?= $item['quantity'] ?> &times; SGD $<?= number_format($item['price'], 2) ?></p><?php if ($item['availability']): ?><p class="checkout-field-error"><?= chwooEscape($item['availability']) ?></p><?php endif; ?></div>
                    <strong>SGD $<?= number_format($item['subtotal'], 2) ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <dl class="checkout-totals">
                <div><dt>Subtotal</dt><dd>SGD $<?= number_format($chwooBagTotal, 2) ?></dd></div>
                <div><dt>Delivery</dt><dd><?= $chwooDelivery > 0 ? 'SGD $' . number_format($chwooDelivery, 2) : 'Complimentary' ?></dd></div>
                <div class="checkout-grand-total"><dt>Estimated total</dt><dd>SGD $<?= number_format($chwooEstimatedTotal, 2) ?></dd></div>
            </dl>
            <p class="checkout-shipping-note">SGD $5 delivery below SGD $80.<br>Complimentary delivery from SGD $80.</p>
            <?php if ($step === 'review'): ?>
            <div class="checkout-next">
                <h3>Everything look right?</h3>
                <p>Confirm your products, colours, sizes, quantities and delivery information above. Use <strong>Edit bag</strong> or <strong>Edit details</strong> to make any final changes.</p>
                <form method="post" action="payment.php">
                    <input type="hidden" name="form_token" value="<?= chwooEscape($_SESSION['chwoo_form_token']) ?>">
                    <input type="hidden" name="checkout_token" value="<?= chwooEscape($purchaseToken) ?>">
                    <button type="submit" name="action" value="review" class="checkout-button" <?= !$purchaseToken ? 'disabled' : '' ?>>Continue to payment &rarr;</button>
                </form>
                <p>Check your selections above before continuing to payment.</p>
            </div>
            <?php else: ?><p class="checkout-help">You can edit your selections and delivery details at the next step.</p><?php endif; ?>
        </aside>
    </div>
    <?php endif; ?>
</main>
<footer><div class="footer-logo">CHWOO</div><div class="footer-links"><a href="shop.php">Shop</a><a href="newin.php">New In</a><a href="account.php">Your Account</a></div><p>&copy; <?= date('Y') ?> CHWOO Fashion Studio</p></footer>
<?php include __DIR__ . '/bag_drawer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>
