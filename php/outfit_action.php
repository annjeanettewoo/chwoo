<?php
// Outfit Builder — Validate every selected piece before updating the shared session bag.
// Adding an outfit checks current stock but neither reserves nor deducts inventory.
require_once __DIR__ . '/auth.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Please use Add Outfit to Bag.');
}
header('Cache-Control: no-store');

// Accept scalar positive integer IDs only; arrays, zero and malformed identities return false.
function chwooOutfitId($value) {
    return is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value)
        ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
}

$token = $_POST['form_token'] ?? '';
$mode = $_POST['outfit_mode'] ?? '';
$error = '';
// Mode defines required categories and an allowlist of submitted identities, not trusted product details.
$slots = $mode === 'dress' ? ['dress' => 'Dresses'] : ['top' => 'Tops', 'bottom' => 'Bottoms'];
$allowed = ['form_token', 'outfit_mode'];
$draft = ['mode' => $mode === 'dress' ? 'dress' : 'separates'];
foreach ($slots as $key => $category) {
    foreach (['product', 'colour', 'variant'] as $field) {
        $name = $key . '_' . $field;
        $allowed[] = $name;
        $draft[$name] = chwooOutfitId($_POST[$name] ?? null) ?: null;
    }
}
// Validate the request before database lookups; browser controls alone cannot enforce these rules.
if (!is_string($token) || !hash_equals($_SESSION['chwoo_form_token'], $token)) {
    $error = 'Please refresh the page and try again. No outfit pieces were added.';
} elseif (!in_array($mode, ['separates', 'dress'], true)) {
    $error = 'Please choose Top + Bottom or Dress. No outfit pieces were added.';
} elseif (array_diff(array_keys($_POST), $allowed)) {
    $error = 'Please submit only the pieces for your chosen outfit mode. No outfit pieces were added.';
} else {
    foreach ($slots as $key => $category) {
        if (!$draft[$key . '_product'] || !$draft[$key . '_colour'] || !$draft[$key . '_variant']) {
            $error = 'Please choose a valid ' . $key . ', colour and size. No outfit pieces were added.';
            break;
        }
    }
}
// Do not load bag.php here: its normal stale-entry cleanup must not run on errors.
$bag = $_SESSION['chwoo_cart'] ?? [];
if ($error === '' && !is_array($bag)) {
    $error = 'Please review your existing bag before adding an outfit. No outfit pieces were added.';
}
if ($error === '') {
    require_once __DIR__ . '/db.php';
    $statement = $conn->prepare('SELECT v.stock_quantity, c.colour_id, c.product_id, p.category, p.is_active
        FROM product_variants v JOIN product_colours c ON c.colour_id = v.colour_id
        JOIN products p ON p.product_id = c.product_id WHERE v.variant_id = ?');
    $seen = [];
    // Resolve each variant through its colour and product; posted identities must agree with that row.
    foreach ($slots as $key => $category) {
        $variantId = $draft[$key . '_variant'];
        if (isset($seen[$variantId])) {
            $error = 'Please choose a different variant for each piece. No outfit pieces were added.';
            break;
        }
        $seen[$variantId] = true;
        $statement->bind_param('i', $variantId);
        $statement->execute();
        $variant = $statement->get_result()->fetch_assoc();
        if (!$variant || (int)$variant['is_active'] !== 1 || $variant['category'] !== $category
            || (int)$variant['product_id'] !== $draft[$key . '_product']
            || (int)$variant['colour_id'] !== $draft[$key . '_colour']) {
            $error = 'Please reselect your ' . $key . ', colour and size from the available collection. No outfit pieces were added.';
            break;
        }
        $existing = $bag[$variantId] ?? null;
        if ($existing !== null && (!is_array($existing) || ($existing['variant_id'] ?? null) !== $variantId
            || !is_int($existing['quantity'] ?? null) || $existing['quantity'] < 1)) {
            $error = 'Please review your existing ' . $key . ' in the bag. No outfit pieces were added.';
            break;
        }
        // Include units already in the bag when checking whether one more piece is available.
        $quantity = $existing['quantity'] ?? 0;
        if ($quantity >= (int)$variant['stock_quantity']) {
            $error = 'Your ' . $key . ' colour and size has no more stock available, including items already in your bag. Please choose another. No outfit pieces were added.';
            break;
        }
        // Build a candidate locally. The session bag is replaced only after every piece passes.
        $bag[$variantId] = ['variant_id' => $variantId, 'quantity' => $quantity + 1];
    }
    $statement->close();
    $conn->close();
}
// All-or-nothing session update, not a database transaction: a failed piece leaves the bag unchanged.
if ($error === '') {
    $_SESSION['chwoo_cart'] = $bag;
    $_SESSION['chwoo_bag_flash'] = 'Your outfit was added to your bag!';
    unset($_SESSION['chwoo_outfit_error']);
} else {
    $_SESSION['chwoo_outfit_error'] = $error;
}
// A valid form token permits draft restoration even after selection errors; the page rechecks the catalogue.
if (is_string($token) && hash_equals($_SESSION['chwoo_form_token'], $token)) {
    $_SESSION['chwoo_outfit_draft'] = $draft;
}
// POST/redirect/GET avoids resubmitting the form on refresh; success opens the shared bag drawer.
header('Location: ' . chwooAuthRootUrl() . 'php/outfit.php' . ($error === '' ? '?bag=open' : ''), true, 303);
exit;
