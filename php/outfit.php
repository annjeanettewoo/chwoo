<?php
// Task 07B: a styling preview only. No POST actions or shopping bag writes.
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit('Please use the outfit preview controls.');
}
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/pricing.php';
header('Cache-Control: no-store');
$outfitRoot = chwooAuthRootUrl();

// Read the existing bag without including bag.php, whose loader can remove stale entries.
$outfitBag = is_array($_SESSION['chwoo_cart'] ?? null) ? $_SESSION['chwoo_cart'] : [];
$outfitQuantities = [];
$chwooBagCount = 0;
foreach ($outfitBag as $key => $entry) {
    if (is_array($entry) && is_int($entry['variant_id'] ?? null) && $entry['variant_id'] > 0
        && (string)$key === (string)$entry['variant_id']
        && is_int($entry['quantity'] ?? null) && $entry['quantity'] > 0) {
        $outfitQuantities[$entry['variant_id']] = $entry['quantity'];
        $chwooBagCount += $entry['quantity'];
    }
}

// One query supplies real product, colour and size identities, including sold-out options.
$products = [];
$statement = $conn->prepare("SELECT p.product_id, p.product_name, p.category,
    c.colour_id, c.colour_name, c.swatch_hex, c.image_path, c.price,
    v.variant_id, v.size_code, v.stock_quantity
    FROM products p
    LEFT JOIN product_colours c ON c.product_id = p.product_id
    LEFT JOIN product_variants v ON v.colour_id = c.colour_id
    LEFT JOIN size_guide s ON s.size_code = v.size_code
    WHERE p.is_active = 1 AND p.category IN ('Tops', 'Bottoms', 'Dresses')
    ORDER BY p.product_id, c.is_default DESC, c.colour_id, s.sort_order, v.variant_id");
$statement->execute();
$result = $statement->get_result();
while ($row = $result->fetch_assoc()) {
    $productId = (int)$row['product_id'];
    if (!isset($products[$productId])) {
        $products[$productId] = ['id' => $productId, 'name' => $row['product_name'],
            'category' => $row['category'], 'colours' => []];
    }
    if ($row['colour_id'] === null) { continue; }
    $colourId = (int)$row['colour_id'];
    if (!isset($products[$productId]['colours'][$colourId])) {
        $path = (string)$row['image_path'];
        $hasImage = preg_match('~^images/[a-zA-Z0-9_./-]+$~D', $path)
            && !str_contains($path, '..') && is_file(__DIR__ . '/../' . $path);
        $products[$productId]['colours'][$colourId] = [
            'id' => $colourId, 'name' => $row['colour_name'] ?: 'Colour unspecified',
            'swatch' => preg_match('/^#[0-9a-fA-F]{6}$/D', $row['swatch_hex'] ?? '') ? $row['swatch_hex'] : null,
            'image' => $hasImage ? $outfitRoot . $path : '',
            'priceCents' => chwooPriceCents($row['price']), 'variants' => []
        ];
    }
    if ($row['variant_id'] !== null) {
        $variantId = (int)$row['variant_id'];
        $products[$productId]['colours'][$colourId]['variants'][] = [
            'id' => $variantId, 'size' => $row['size_code'],
            'available' => max(0, (int)$row['stock_quantity'] - ($outfitQuantities[$variantId] ?? 0))
        ];
    }
}
$statement->close();
foreach ($products as &$product) { $product['colours'] = array_values($product['colours']); }
unset($product);
$products = array_values($products);
$slots = [
    'top' => ['label' => 'Top', 'category' => 'Tops', 'number' => '01'],
    'bottom' => ['label' => 'Bottom', 'category' => 'Bottoms', 'number' => '02'],
    'dress' => ['label' => 'Dress', 'category' => 'Dresses', 'number' => '01']
];
foreach ($slots as &$slot) {
    $slot['products'] = array_values(array_filter($products, function ($product) use ($slot) {
        return $product['category'] === $slot['category'];
    }));
    // An initial starting look shows the actual photographs; sizes are always an explicit choice.
    $slot['initial'] = $slot['products'][0] ?? null;
    $slot['colour'] = $slot['initial']['colours'][0] ?? null;
}
unset($slot);
$initialTotal = ($slots['top']['colour']['priceCents'] ?? 0) + ($slots['bottom']['colour']['priceCents'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Build Your CHWOO | Fashion Styling Board</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="../css/outfit.css">
    <script src="../js/outfitBuilder.js" defer></script>
</head>
<body class="outfit-page">
    <?php include __DIR__ . '/header.php'; ?>
    <main class="outfit-main" id="outfit-builder">
        <div class="outfit-breadcrumb"><a href="../index.php">Home</a><span>/</span><a href="shop.php">Shop</a><span>/</span>Build Your CHWOO</div>
        <div class="outfit-intro">
            <div><p class="outfit-eyebrow">A LITTLE MIX. A LITTLE MATCH.</p><h1>Build Your <span>CHWOO.</span></h1></div>
            <p>Your pieces. Your palette. Your kind of everyday.<br>Play with a pairing, or let one dress do it all.</p>
        </div>
        <noscript><p class="outfit-notice">Please enable JavaScript to change pieces and explore your styling board. You can still <a href="shop.php">browse the collection</a>.</p></noscript>
        <div class="outfit-layout">
            <section class="outfit-workspace" aria-labelledby="board-title">
                <div class="outfit-board-heading"><h2 id="board-title">Your styling board</h2><span id="board-mode-label">THE TWO-PIECE EDIT</span></div>
                <div class="outfit-board" id="styling-board" data-mode="separates">
                    <p class="outfit-board-note" aria-hidden="true"><span data-board-lead>a little better</span><br><em data-board-emphasis>together.</em></p>
                    <span class="outfit-board-star" aria-hidden="true">✳</span>
                    <?php foreach ($slots as $key => $slot): ?>
                    <figure class="outfit-photo outfit-photo-<?= $key ?>" data-preview="<?= $key ?>" <?= $key === 'dress' ? 'hidden' : '' ?>>
                        <div class="outfit-photo-bar"><span><?= $slot['number'] ?> / <?= strtoupper($slot['label']) ?></span><button type="button" data-remove="<?= $key ?>" aria-label="Remove <?= strtolower($slot['label']) ?> from styling board" <?= !$slot['initial'] ? 'hidden' : '' ?>>&times;</button></div>
                        <div class="outfit-photo-image">
                            <img data-preview-image <?= !empty($slot['colour']['image']) ? 'src="' . chwooAuthEscape($slot['colour']['image']) . '"' : 'hidden' ?> alt="<?= chwooAuthEscape(($slot['initial']['name'] ?? '') . ' in ' . ($slot['colour']['name'] ?? '')) ?>">
                            <p data-photo-unavailable <?= !empty($slot['colour']['image']) || !$slot['initial'] ? 'hidden' : '' ?>>Photo unavailable for this colour.</p>
                            <button type="button" class="outfit-empty-prompt" data-choose="<?= $key ?>" <?= $slot['initial'] ? 'hidden' : '' ?>><span aria-hidden="true">+</span>Choose a <?= strtolower($slot['label']) ?></button>
                        </div>
                        <figcaption><strong data-preview-name><?= chwooAuthEscape($slot['initial']['name'] ?? 'Your next favourite') ?></strong><span data-preview-detail><?= chwooAuthEscape($slot['colour']['name'] ?? 'Choose a piece to get started') ?><?= $slot['colour'] ? ' · Choose a size' : '' ?></span></figcaption>
                    </figure>
                    <?php endforeach; ?>
                    <span class="outfit-board-signature" aria-hidden="true">STYLED BY YOU / CHWOO</span>
                </div>
                <div class="outfit-board-footer"><p>Real pieces. A fresh perspective.</p><button type="button" id="outfit-clear">Clear board <span aria-hidden="true">↗</span></button></div>
                <a class="outfit-mobile-jump" href="#editor-title">CHOOSE YOUR PIECES <span aria-hidden="true">↓</span></a>
            </section>

            <section class="outfit-editor" aria-labelledby="editor-title">
                <p class="outfit-eyebrow">MAKE IT YOURS</p><h2 id="editor-title" tabindex="-1">Find your combination.</h2>
                <fieldset class="outfit-mode"><legend class="outfit-sr-only">Choose your outfit mode</legend>
                    <label><input type="radio" name="outfit_mode" value="separates" checked><span>TOP + BOTTOM</span></label>
                    <label><input type="radio" name="outfit_mode" value="dress"><span>DRESS</span></label>
                </fieldset>
                <?php foreach ($slots as $key => $slot): ?>
                <fieldset class="outfit-piece" data-slot="<?= $key ?>" <?= $key === 'dress' ? 'hidden disabled' : '' ?>>
                    <legend><span><?= $slot['number'] ?></span> The <?= strtolower($slot['label']) ?></legend>
                    <label class="outfit-sr-only" for="outfit-<?= $key ?>">Choose your <?= strtolower($slot['label']) ?></label>
                    <select id="outfit-<?= $key ?>" data-product aria-describedby="<?= $key ?>-availability">
                        <option value="">Choose a <?= strtolower($slot['label']) ?></option>
                        <?php foreach ($slot['products'] as $product): ?>
                        <option value="<?= $product['id'] ?>" <?= $slot['initial'] && $slot['initial']['id'] === $product['id'] ? 'selected' : '' ?>><?= chwooAuthEscape($product['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$slot['products']): ?><p class="outfit-availability">No <?= strtolower($slot['category']) ?> are available in the collection right now.</p><?php endif; ?>
                    <fieldset class="outfit-colours"><legend>COLOUR <span data-colour-name><?= chwooAuthEscape($slot['colour']['name'] ?? '—') ?></span></legend><div data-colours>
                        <?php foreach ($slot['initial']['colours'] ?? [] as $colour): ?>
                        <label class="outfit-swatch"><input type="radio" name="<?= $key ?>_colour" value="<?= $colour['id'] ?>" <?= $slot['colour']['id'] === $colour['id'] ? 'checked' : '' ?> aria-label="<?= chwooAuthEscape($colour['name']) ?>"><span class="outfit-swatch-dot<?= $colour['swatch'] ? '' : ' is-unspecified' ?>" <?= $colour['swatch'] ? 'style="--swatch: ' . chwooAuthEscape($colour['swatch']) . '"' : '' ?> aria-hidden="true"></span><span class="outfit-sr-only"><?= chwooAuthEscape($colour['name']) ?></span></label>
                        <?php endforeach; ?>
                    </div></fieldset>
                    <fieldset class="outfit-sizes"><legend>SIZE</legend><div data-sizes>
                        <?php foreach ($slot['colour']['variants'] ?? [] as $variant): ?>
                        <label class="outfit-size"><input type="radio" name="<?= $key ?>_variant" value="<?= $variant['id'] ?>" <?= $variant['available'] === 0 ? 'disabled' : '' ?> aria-label="<?= chwooAuthEscape('Size ' . $variant['size'] . ($variant['available'] ? ', ' . $variant['available'] . ' available' : ', unavailable')) ?>"><span><?= chwooAuthEscape($variant['size']) ?></span></label>
                        <?php endforeach; ?>
                    </div></fieldset>
                    <p class="outfit-availability" id="<?= $key ?>-availability" data-availability>Choose a size to check availability.</p>
                </fieldset>
                <?php endforeach; ?>
                <aside class="outfit-summary" aria-labelledby="summary-title">
                    <h3 id="summary-title">The look so far</h3><ul id="outfit-summary-items">
                        <?php foreach (['top', 'bottom'] as $key): ?>
                        <?php if ($slots[$key]['initial']): ?><li><span><?= chwooAuthEscape($slots[$key]['initial']['name']) ?><small><?= chwooAuthEscape($slots[$key]['colour']['name'] ?? 'No colour available') ?> · Size not selected</small></span><strong><?= $slots[$key]['colour'] ? 'SGD $' . chwooMoney($slots[$key]['colour']['priceCents']) : 'Unavailable' ?></strong></li><?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                    <div class="outfit-total"><span>Outfit subtotal</span><output id="outfit-total">SGD $<?= chwooMoney($initialTotal) ?></output></div>
                    <p id="outfit-completeness">Choose a size for each piece to complete your look.</p>
                    <p class="outfit-preview-note">Styling preview only. Pieces are not reserved.<br>Availability reflects this page load and your existing bag.</p>
                </aside>
                <a class="outfit-mobile-jump" href="#board-title">SEE YOUR LOOK <span aria-hidden="true">↑</span></a>
                <a class="outfit-shop-link" href="shop.php">EXPLORE THE COLLECTION <span aria-hidden="true">↗</span></a>
            </section>
        </div>
        <p class="outfit-sr-only" id="outfit-status" role="status" aria-live="polite" aria-atomic="true"></p>
    </main>
    <footer><div class="footer-logo">CHWOO</div><div class="footer-links"><a href="shop.php">Shop</a><a href="collections.php">Collections</a><a href="account.php">Your Account</a></div><p>&copy; 2026 CHWOO Fashion Studio</p></footer>
    <script type="application/json" id="outfit-catalogue"><?= json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
</body>
</html>
<?php $conn->close(); ?>
