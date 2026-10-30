<?php // The data and controls are shared with the full bag, never browser storage. ?>
<div class="chwoo-bag-overlay" id="chwoo-bag-overlay" hidden></div>
<aside class="chwoo-bag-drawer" id="chwoo-bag-drawer" role="dialog" aria-modal="true" aria-labelledby="chwoo-bag-title" aria-hidden="true" inert data-open="<?= ($_GET['bag'] ?? '') === 'open' ? 'true' : 'false' ?>">
    <div class="chwoo-bag-top">
        <button type="button" class="chwoo-bag-close" aria-label="Close shopping bag">&times;</button>
        <h2 id="chwoo-bag-title">Your Bag</h2><span><?= $chwooBagCount ?> ITEM(S)</span>
    </div>
    <div class="chwoo-bag-items">
        <?php if ($chwooBagMessage): ?><p class="chwoo-bag-message" role="status"><?= chwooEscape($chwooBagMessage) ?></p><?php endif; ?>
        <?php if (!$chwooBagItems): ?><p class="chwoo-bag-empty">Your bag is empty. Find something you love.</p><?php endif; ?>
        <?php foreach ($chwooBagItems as $item): ?>
            <article class="chwoo-drawer-item">
                <a class="chwoo-drawer-image" href="<?= chwooEscape($chwooRootUrl) ?>php/product.php?id=<?= $item['product_id'] ?>">
                    <?php if ($item['image_url']): ?><img src="<?= chwooEscape($item['image_url']) ?>" alt="<?= chwooEscape($item['name'] . ' in ' . $item['colour']) ?>"><?php else: ?><span>Photo coming soon</span><?php endif; ?>
                </a>
                <div>
                    <h3><?= chwooEscape($item['name']) ?></h3>
                    <p class="chwoo-bag-meta"><?= chwooEscape($item['colour']) ?> &middot; Size <?= chwooEscape($item['size']) ?></p>
                    <?php chwooBagControls($item, $chwooReturnTo, 'drawer'); ?>
                    <?php if ($item['availability']): ?><p class="chwoo-bag-warning" role="alert"><?= chwooEscape($item['availability']) ?></p><?php endif; ?>
                    <p class="chwoo-line-subtotal">SGD $<?= number_format($item['subtotal'], 2) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="chwoo-bag-bottom">
        <p class="chwoo-shipping-hint"><?= !$chwooBagCount ? 'Delivery is SGD $5 below SGD $80; complimentary from SGD $80.' : ($chwooDelivery > 0 ? 'SGD $' . number_format(80 - $chwooBagTotal, 2) . ' away from complimentary delivery' : 'You qualify for complimentary delivery.') ?></p>
        <div class="chwoo-shipping-track"><span style="width: <?= min(100, $chwooBagTotal / 80 * 100) ?>%"></span></div>
        <div class="chwoo-bag-subtotal"><span>Subtotal</span><strong>SGD $<?= number_format($chwooBagTotal, 2) ?></strong></div>
        <div class="chwoo-bag-subtotal"><span>Delivery</span><span><?= !$chwooBagCount ? 'SGD $0.00' : ($chwooDelivery > 0 ? 'SGD $' . number_format($chwooDelivery, 2) : 'Complimentary') ?></span></div>
        <div class="chwoo-bag-subtotal chwoo-bag-estimated"><span>Estimated total</span><strong>SGD $<?= number_format($chwooEstimatedTotal, 2) ?></strong></div>
        <a class="chwoo-bag-primary" href="<?= chwooEscape($chwooCheckoutUrl) ?>">CHECKOUT</a>
        <a class="chwoo-view-bag" href="<?= chwooEscape($chwooRootUrl) ?>php/cart.php">VIEW FULL BAG</a>
        <small>Demonstration delivery estimate</small>
    </div>
</aside>
<script src="<?= chwooEscape($chwooRootUrl) ?>js/bag.js" defer></script>
