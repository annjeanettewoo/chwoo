<?php
// Table layout and inline CSS for mail clients; no remote assets or images.
function chwooAcknowledgementHtml($order, $config = []) {
    $escape = function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
    $historyUrl = chwooOrderHistoryUrl($config);
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Your CHWOO order is confirmed</title></head>
<body style="margin:0;padding:0;background-color:#FFF3C2;color:#4B2E1F;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FFF3C2;"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background-color:#ffffff;color:#4B2E1F;">
    <tr><td style="padding:28px 24px;background-color:#BED1E1;text-align:center;"><p style="margin:0;font-family:Georgia,serif;font-size:36px;letter-spacing:6px;">CHWOO</p><p style="margin:8px 0 0;font-size:12px;letter-spacing:2px;">FASHION STUDIO</p></td></tr>
    <tr><td style="padding:28px 24px 16px;"><p style="margin:0 0 12px;font-size:12px;">ORDER #<?= (int)$order['order_id'] ?></p><h1 style="margin:0 0 20px;font-family:Georgia,serif;font-size:30px;font-weight:normal;line-height:1.2;">Your pieces are confirmed.</h1><p style="margin:0 0 12px;font-size:15px;line-height:1.6;">Hello <?= $escape($order['recipient_name']) ?>,</p><p style="margin:0;font-size:14px;line-height:1.6;">Thank you for shopping with CHWOO! Your order has been confirmed. Here is a little recap of your new favourites.</p></td></tr>
    <tr><td style="padding:12px 24px;">
        <table width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:12px;line-height:1.6;">
            <caption style="text-align:left;padding-bottom:12px;font-family:Georgia,serif;font-size:22px;">Your purchased pieces</caption>
            <thead><tr style="background-color:#BED1E1;"><th scope="col" align="left" style="padding:10px 8px;">Piece</th><th scope="col" align="center" style="padding:10px 4px;">Qty</th><th scope="col" align="right" style="padding:10px 4px;">Unit price</th><th scope="col" align="right" style="padding:10px 8px;">Line total</th></tr></thead>
            <tbody><?php foreach ($order['items'] as $item): ?>
            <tr><td style="padding:14px 8px;border-bottom:1px solid #BED1E1;overflow-wrap:anywhere;"><strong><?= $escape($item['product_name']) ?></strong><br>Colour: <?= $escape($item['selected_colour']) ?><br>Size: <?= $escape($item['selected_size']) ?></td><td align="center" style="padding:14px 4px;border-bottom:1px solid #BED1E1;vertical-align:top;"><?= (int)$item['quantity'] ?></td><td align="right" style="padding:14px 4px;border-bottom:1px solid #BED1E1;vertical-align:top;">SGD&nbsp;$<?= $escape($item['unit_price']) ?></td><td align="right" style="padding:14px 8px;border-bottom:1px solid #BED1E1;vertical-align:top;">SGD&nbsp;$<?= chwooMoney(chwooPriceCents($item['unit_price']) * (int)$item['quantity']) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.6;">
            <tr><td style="padding:16px 8px 6px;">Subtotal</td><td align="right" style="padding:16px 8px 6px;">SGD $<?= $escape($order['item_subtotal']) ?></td></tr>
            <tr><td style="padding:6px 8px 16px;">Delivery</td><td align="right" style="padding:6px 8px 16px;">SGD $<?= $escape($order['shipping_charge']) ?></td></tr>
            <tr style="background-color:#FFF3C2;"><td style="padding:14px 8px;font-size:18px;"><strong>Final total</strong></td><td align="right" style="padding:14px 8px;font-size:18px;"><strong>SGD $<?= $escape($order['total_amount']) ?></strong></td></tr>
        </table>
    </td></tr>
    <tr><td style="padding:16px 24px;"><h2 style="margin:0 0 12px;font-family:Georgia,serif;font-size:22px;font-weight:normal;">Delivery details</h2><p style="margin:0;font-size:14px;line-height:1.7;overflow-wrap:anywhere;"><?= $escape($order['recipient_name']) ?><br><?= nl2br($escape($order['delivery_address'])) ?><br>Contact: <?= $escape($order['contact_phone']) ?></p></td></tr>
    <tr><td align="center" style="padding:20px 24px 32px;"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td align="center" style="background-color:#4B2E1F;"><a href="<?= $escape($historyUrl) ?>" style="display:inline-block;padding:16px 28px;border:1px solid #4B2E1F;color:#FFF3C2;font-size:14px;font-weight:bold;text-decoration:none;">View My Orders</a></td></tr></table><p style="margin:14px 0 0;font-size:12px;line-height:1.6;">Sign in to your CHWOO account to view your orders.</p></td></tr>
    <tr><td style="padding:24px;background-color:#FFF3C2;text-align:center;"><p style="margin:0 0 8px;font-family:Georgia,serif;font-size:18px;">A little playful. Always you.</p><p style="margin:0;font-size:12px;line-height:1.6;">Thank you for choosing CHWOO Fashion Studio.</p></td></tr>
</table>
</td></tr></table>
</body>
</html>
    <?php
    return trim(ob_get_clean());
}
