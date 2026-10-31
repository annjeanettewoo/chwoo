<?php
// Rendered only inside the authenticated account page, never as a public endpoint.
if (!isset($customerId, $section) || !chwooCurrentCustomer()) { http_response_code(404); exit; }
$kind = 'address';
$savedDetails = chwooSavedDetails($conn, $customerId, $kind);
?>
<section class="account-section">
<?php if (!$savedDetails): ?>
<div class="account-empty"><h2>No saved addresses yet.</h2><p>You can save an address during checkout.</p><a class="account-text-link" href="shop.php">Explore the collection &rarr;</a></div>
<?php else: ?>
<div class="account-saved-list">
<?php foreach ($savedDetails as $detail): ?>
<article class="account-saved-detail">
<h2><?= chwooEscape($detail['recipient_name']) ?></h2><p><?= chwooEscape($detail['contact_phone']) ?><br><?= nl2br(chwooEscape($detail['address'])) ?><br>Singapore <?= chwooEscape($detail['postal_code']) ?></p>

<form method="post" action="saved_details_action.php"><input type="hidden" name="form_token" value="<?= chwooEscape($_SESSION['chwoo_form_token']) ?>"><input type="hidden" name="id" value="<?= (int)$detail['id'] ?>"><button type="submit" aria-label="Remove address for <?= chwooEscape($detail['recipient_name']) ?>">Remove</button></form>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>
