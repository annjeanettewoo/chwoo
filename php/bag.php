<?php
// Shared session bag data. Include before output on pages that offer the drawer.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function chwooEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function chwooImageUrl($path, $rootUrl) {
    return preg_match('~^images/[a-zA-Z0-9_./-]+$~', $path)
        && !str_contains($path, '..') && is_file(__DIR__ . '/../' . $path)
        ? $rootUrl . $path : '';
}

// Demonstration policy: an empty bag is free; otherwise $5 below the $80 threshold.
function chwooDeliveryCharge($subtotal, $itemCount) {
    return $itemCount > 0 && $subtotal < 80 ? 5.0 : 0.0;
}

function chwooBagControls($item, $returnTo, $context = 'drawer') {
    global $chwooRootUrl;
    $label = $item['name'] . ', ' . $item['colour'] . ', size ' . $item['size'];
    ?>
    <form method="post" action="<?= chwooEscape($chwooRootUrl) ?>php/bag_action.php" class="chwoo-bag-controls">
        <input type="hidden" name="form_token" value="<?= chwooEscape($_SESSION['chwoo_form_token']) ?>">
        <input type="hidden" name="variant_id" value="<?= $item['variant_id'] ?>">
        <input type="hidden" name="return_to" value="<?= chwooEscape($returnTo) ?>">
        <input type="hidden" name="bag_context" value="<?= chwooEscape($context) ?>">
        <span class="chwoo-quantity">
            <button type="submit" name="action" value="decrease" aria-label="<?= chwooEscape('Decrease quantity of ' . $label) ?>">&minus;</button>
            <span aria-label="Quantity"><?= $item['quantity'] ?></span>
            <button type="submit" name="action" value="increase" aria-label="<?= chwooEscape('Increase quantity of ' . $label) ?>" <?= $item['quantity'] >= $item['stock'] ? 'disabled' : '' ?>>+</button>
        </span>
        <button type="submit" name="action" value="remove" class="chwoo-trash" title="Remove item" aria-label="<?= chwooEscape('Remove ' . $label . ' from bag') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6M10 10v7M14 10v7"/></svg>
        </button>
    </form>
    <?php
}

$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$chwooRootUrl = rtrim(basename($scriptDirectory) === 'php' ? dirname($scriptDirectory) : $scriptDirectory, '/') . '/';
$chwooPage = basename($scriptDirectory) === 'php' ? 'php/' . basename($_SERVER['SCRIPT_NAME']) : 'index.php';
$chwooReturnTo = chwooReturnTo($chwooPage . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
$chwooCheckoutUrl = $chwooRootUrl . 'php/checkout.php';
if (empty($_SESSION['chwoo_form_token'])) {
    $_SESSION['chwoo_form_token'] = bin2hex(random_bytes(16));
}

// Never guess the variant represented by an obsolete product/size/colour entry.
if (!isset($_SESSION['chwoo_cart'])) {
    $_SESSION['chwoo_cart'] = [];
} elseif (!is_array($_SESSION['chwoo_cart'])) {
    $_SESSION['chwoo_cart'] = [];
    $_SESSION['chwoo_cart_notice'] = 'Outdated or invalid bag entries were removed. Please select those items again.';
}
$chwooBagItems = [];
$chwooBagCount = 0;
$chwooBagTotal = 0.0;
$bagStatement = $conn->prepare('SELECT v.variant_id, v.size_code, v.stock_quantity,
                                      c.colour_id, c.colour_name, c.image_path, c.price,
                                      p.product_id, p.product_name
                               FROM product_variants AS v
                               JOIN product_colours AS c ON c.colour_id = v.colour_id
                               JOIN products AS p ON p.product_id = c.product_id
                               WHERE v.variant_id = ? AND p.is_active = 1');
foreach ($_SESSION['chwoo_cart'] as $key => $entry) {
    if (!is_array($entry) || !isset($entry['variant_id'], $entry['quantity'])
        || !is_int($entry['variant_id']) || $entry['variant_id'] < 1
        || (string)$key !== (string)$entry['variant_id']
        || !is_int($entry['quantity']) || $entry['quantity'] < 1) {
        unset($_SESSION['chwoo_cart'][$key]);
        $_SESSION['chwoo_cart_notice'] = 'Outdated or invalid bag entries were removed. Please select those items again.';
        continue;
    }
    $bagStatement->bind_param('i', $entry['variant_id']);
    $bagStatement->execute();
    $row = $bagStatement->get_result()->fetch_assoc();
    if (!$row) {
        unset($_SESSION['chwoo_cart'][$key]);
        $_SESSION['chwoo_cart_notice'] = 'Unavailable bag entries were removed. Please select those items again.';
        continue;
    }
    $quantity = $entry['quantity'];
    $stock = (int)$row['stock_quantity'];
    $subtotal = (float)$row['price'] * $quantity;
    $chwooBagItems[] = [
        'variant_id' => $entry['variant_id'], 'product_id' => (int)$row['product_id'],
        'colour_id' => (int)$row['colour_id'],
        'name' => $row['product_name'], 'colour' => $row['colour_name'] ?: 'Colour unspecified',
        'size' => $row['size_code'], 'quantity' => $quantity, 'stock' => $stock,
        'price' => (float)$row['price'], 'subtotal' => $subtotal,
        'image_url' => chwooImageUrl($row['image_path'], $chwooRootUrl),
        'availability' => $stock === 0 ? 'This variant is out of stock.'
            : ($quantity > $stock ? 'Only ' . $stock . ' available. Please reduce the quantity.' : '')
    ];
    $chwooBagCount += $quantity;
    $chwooBagTotal += $subtotal;
}
$bagStatement->close();
$chwooDelivery = chwooDeliveryCharge($chwooBagTotal, $chwooBagCount);
$chwooEstimatedTotal = $chwooBagTotal + $chwooDelivery;
$chwooBagMessage = trim(($_SESSION['chwoo_bag_flash'] ?? '') . ' ' . ($_SESSION['chwoo_cart_notice'] ?? ''));
unset($_SESSION['chwoo_bag_flash'], $_SESSION['chwoo_cart_notice']);
