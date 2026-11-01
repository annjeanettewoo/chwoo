<?php
// Shopping Bag — Shared session lines, catalogue lookups, controls and display totals.
// Include before page output so session cleanup and feedback are available to the page and drawer.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/pricing.php';

function chwooEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Return an installation-relative URL only for an existing local image; otherwise let the view show a placeholder.
function chwooImageUrl($path, $rootUrl) {
    return preg_match('~^images/[a-zA-Z0-9_./-]+$~', $path)
        && !str_contains($path, '..') && is_file(__DIR__ . '/../' . $path)
        ? $rootUrl . $path : '';
}

// --- Shopping Bag — Shared quantity and removal controls ---
// Render exact-variant POST actions for full bag, drawer or checkout; the handler validates their return context.
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

// --- Shopping Bag — Installation URLs and form state ---
// Pages at the root and under php/ share one bag; allowlisted return URLs preserve relevant page selections.
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$chwooRootUrl = rtrim(basename($scriptDirectory) === 'php' ? dirname($scriptDirectory) : $scriptDirectory, '/') . '/';
$chwooPage = basename($scriptDirectory) === 'php' ? 'php/' . basename($_SERVER['SCRIPT_NAME']) : 'index.php';
$chwooReturnTo = chwooReturnTo($chwooPage . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
$chwooCheckoutUrl = $chwooRootUrl . 'php/checkout.php';
if (empty($_SESSION['chwoo_form_token'])) {
    $_SESSION['chwoo_form_token'] = bin2hex(random_bytes(16));
}

// --- Shopping Bag — Validate persisted session lines ---
// chwoo_cart[variant_id] = ['variant_id' => int, 'quantity' => positive int] persists across page requests.
// Store no price or stock snapshot here: the current catalogue remains authoritative for those values.
// Outfit Builder can preserve invalid entries after a rejected submission; normal loads remove them with a notice.
$chwooBagPreserveEntries = $chwooBagPreserveEntries ?? false;
if (!isset($_SESSION['chwoo_cart'])) {
    if (!$chwooBagPreserveEntries) { $_SESSION['chwoo_cart'] = []; }
} elseif (!is_array($_SESSION['chwoo_cart'])) {
    if (!$chwooBagPreserveEntries) { $_SESSION['chwoo_cart'] = []; }
    $_SESSION['chwoo_cart_notice'] = $chwooBagPreserveEntries
        ? 'Please review outdated or invalid entries in your bag.'
        : 'Outdated or invalid bag entries were removed. Please select those items again.';
}
// Display rows combine validated session quantities with current catalogue details; count means total units.
$chwooBagItems = [];
$chwooBagCount = 0;
$chwooBagTotal = 0.0;
// --- Shopping Bag — Load current active variants and availability ---
// Resolve product identity through variant -> colour -> product; never infer a variant from old cart formats.
$bagStatement = $conn->prepare('SELECT v.variant_id, v.size_code, v.stock_quantity,
                                      c.colour_id, c.colour_name, c.image_path, c.price,
                                      p.product_id, p.product_name
                               FROM product_variants AS v
                               JOIN product_colours AS c ON c.colour_id = v.colour_id
                               JOIN products AS p ON p.product_id = c.product_id
                               WHERE v.variant_id = ? AND p.is_active = 1');
foreach (is_array($_SESSION['chwoo_cart'] ?? null) ? $_SESSION['chwoo_cart'] : [] as $key => $entry) {
    if (!is_array($entry) || !isset($entry['variant_id'], $entry['quantity'])
        || !is_int($entry['variant_id']) || $entry['variant_id'] < 1
        || (string)$key !== (string)$entry['variant_id']
        || !is_int($entry['quantity']) || $entry['quantity'] < 1) {
        if (!$chwooBagPreserveEntries) { unset($_SESSION['chwoo_cart'][$key]); }
        $_SESSION['chwoo_cart_notice'] = $chwooBagPreserveEntries
            ? 'Please review outdated or invalid entries in your bag.'
            : 'Outdated or invalid bag entries were removed. Please select those items again.';
        continue;
    }
    $bagStatement->bind_param('i', $entry['variant_id']);
    $bagStatement->execute();
    $row = $bagStatement->get_result()->fetch_assoc();
    if (!$row) {
        if (!$chwooBagPreserveEntries) { unset($_SESSION['chwoo_cart'][$key]); }
        $_SESSION['chwoo_cart_notice'] = $chwooBagPreserveEntries
            ? 'Please review unavailable entries in your bag.'
            : 'Unavailable bag entries were removed. Please select those items again.';
        continue;
    }
    $quantity = $entry['quantity'];
    $stock = (int)$row['stock_quantity'];
    $priceCents = chwooPriceCents($row['price']);
    // Multiply integer cents before converting the line total to dollars for the existing views.
    $subtotal = $priceCents * $quantity / 100;
    $chwooBagItems[] = [
        'variant_id' => $entry['variant_id'], 'product_id' => (int)$row['product_id'],
        'colour_id' => (int)$row['colour_id'],
        'name' => $row['product_name'], 'colour' => $row['colour_name'] ?: 'Colour unspecified',
        'size' => $row['size_code'], 'quantity' => $quantity, 'stock' => $stock,
        'price' => (float)$row['price'], 'subtotal' => $subtotal,
        'price_cents' => $priceCents, 'image_path' => $row['image_path'],
        'image_url' => chwooImageUrl($row['image_path'], $chwooRootUrl),
        // Keep stock-short lines visible so customers can reduce/remove them; displaying a bag reserves no stock.
        'availability' => $stock === 0 ? 'This variant is out of stock.'
            : ($quantity > $stock ? 'Only ' . $stock . ' available. Please reduce the quantity.' : '')
    ];
    $chwooBagCount += $quantity;
    $chwooBagTotal += $subtotal;
}
$bagStatement->close();
// --- Shopping Bag — Shared pricing and one-time feedback ---
// Recompute canonical totals in cents instead of relying on the dollar subtotals accumulated above.
// Convert only the display totals to dollars; checkout and order processing reuse the same pricing functions.
$chwooTotals = chwooBagTotals($chwooBagItems);
$chwooBagTotal = $chwooTotals['subtotal_cents'] / 100;
$chwooDelivery = $chwooTotals['delivery_cents'] / 100;
$chwooEstimatedTotal = $chwooTotals['total_cents'] / 100;
// Consume action/cleanup notices once; the page chooses whether the full view or drawer displays them.
$chwooBagMessage = trim(($_SESSION['chwoo_bag_flash'] ?? '') . ' ' . ($_SESSION['chwoo_cart_notice'] ?? ''));
unset($_SESSION['chwoo_bag_flash'], $_SESSION['chwoo_cart_notice']);
