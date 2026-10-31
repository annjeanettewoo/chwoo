<?php
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/order_mail_template.php';

function chwooMailConfig($useLocal = true) {
    $config = require __DIR__ . '/mail_config.php';
    $localPath = __DIR__ . '/mail_config.local.php';
    if ($useLocal && is_file($localPath)) {
        $local = require $localPath;
        if (!is_array($local)) { throw new RuntimeException('Local mail configuration must return an array.'); }
        $config = array_replace($config, array_intersect_key($local, $config));
    }
    return $config;
}
function chwooOrderHistoryUrl($config = []) {
    $siteUrl = $config['site_url'] ?? 'http://localhost:8000/chwoo';
    $parts = is_string($siteUrl) ? parse_url($siteUrl) : false;
    if (!$parts || !filter_var($siteUrl, FILTER_VALIDATE_URL)
        || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
        || preg_match('/[\x00-\x20\x7f]/', $siteUrl)) {
        throw new RuntimeException('Mail site_url must be an absolute HTTP(S) base URL.');
    }
    return rtrim($siteUrl, '/') . '/php/account.php?section=orders';
}
function chwooAcknowledgementText($order, $config = []) {
    $lines = ['CHWOO Fashion Studio', 'Order #' . $order['order_id'],
        'Hello ' . $order['recipient_name'] . ',', '',
        'Thank you for shopping with CHWOO! Your order has been confirmed. Below is a summary of your purchase.', ''];
    foreach ($order['items'] as $item) {
        $lineCents = chwooPriceCents($item['unit_price']) * (int)$item['quantity'];
        $lines[] = $item['product_name'] . ' / ' . $item['selected_colour'] . ' / size ' . $item['selected_size']
            . ' | ' . $item['quantity'] . ' x SGD $' . $item['unit_price'] . ' = SGD $' . chwooMoney($lineCents);
    }
    $lines[] = '';
    $lines[] = 'Subtotal: SGD $' . $order['item_subtotal'];
    $lines[] = 'Delivery: SGD $' . $order['shipping_charge'];
    $lines[] = 'Final amount: SGD $' . $order['total_amount'];
    $lines[] = 'Status: ' . $order['order_status'];
    $lines[] = '';
    $lines[] = 'Delivery details';
    $lines[] = $order['recipient_name'];
    $lines[] = $order['contact_phone'];
    $lines[] = $order['delivery_address'];
    $lines[] = '';
    $lines[] = 'View My Orders: ' . chwooOrderHistoryUrl($config);
    $lines[] = 'Sign in to your CHWOO account to view your orders.';
    $lines[] = '';
    $lines[] = 'A little playful. Always you.';
    $lines[] = 'Thank you for choosing CHWOO Fashion Studio.';
    return implode("\r\n", $lines) . "\r\n";
}
function chwooLocalAcknowledgement($subject, $body, $config, $transport = null, $html = null) {
    if (($config['enabled'] ?? false) !== true) { return 'Prepared'; }
    // Only explicitly configured local mailboxes; no visitor-controlled recipient.
    $localMailbox = '/\A[A-Za-z0-9._+-]+@localhost\z/Di';
    if (!is_string($config['recipient'] ?? null) || !is_string($config['sender'] ?? null)
        || !preg_match($localMailbox, $config['recipient']) || !preg_match($localMailbox, $config['sender'])
        || !in_array(strtolower(ini_get('SMTP')), ['localhost', '127.0.0.1', '::1'], true)
        || ini_get('sendmail_path') !== '' || preg_match('/[\r\n]/', $subject)) { return 'Failed'; }
    $headers = 'From: ' . $config['sender'] . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
    try {
        if ($html !== null) {
            $boundary = 'chwoo_' . bin2hex(random_bytes(24));
            $headers = 'From: ' . $config['sender'] . "\r\nMIME-Version: 1.0\r\n"
                . 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $body = 'This is a multipart message in MIME format.' . "\r\n\r\n"
                . '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: quoted-printable\r\n\r\n" . quoted_printable_encode($body) . "\r\n"
                . '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: quoted-printable\r\n\r\n" . quoted_printable_encode($html) . "\r\n"
                . '--' . $boundary . "--\r\n";
        }
        // The optional callable is used by CLI tests without contacting any mailbox.
        $accepted = $transport ? $transport($config['recipient'], $subject, $body, $headers)
            : @mail($config['recipient'], $subject, $body, $headers);
        return $accepted ? 'Accepted' : 'Failed';
    } catch (Throwable $error) { return 'Failed'; }
}
function chwooAcknowledgeOrder($conn, $orderId, $customerId, $config = null, $transport = null) {
    $order = chwooSavedOrder($conn, $orderId, $customerId);
    if (!$order || $order['acknowledgement_status'] !== 'Prepared'
        || $order['payment_status'] !== 'Paid' || $order['order_status'] !== 'Confirmed') { return; }
    // A process-only test setting guarantees fixture purchases cannot send mail.
    try {
        $config = $config ?? chwooMailConfig();
        if (getenv('CHWOO_MAIL_PREPARE_ONLY') === '1') { $config['enabled'] = false; }
        $status = chwooLocalAcknowledgement('CHWOO order #' . (int)$orderId,
            chwooAcknowledgementText($order, $config), $config, $transport, chwooAcknowledgementHtml($order, $config));
    } catch (Throwable $error) { $status = 'Failed'; }
    $stmt = $conn->prepare("UPDATE orders SET acknowledgement_status = ?,
        acknowledgement_sent_at = CASE WHEN ? = 'Accepted' THEN CURRENT_TIMESTAMP ELSE NULL END
        WHERE order_id = ? AND customer_id = ? AND acknowledgement_status = 'Prepared'");
    $stmt->bind_param('ssii', $status, $status, $orderId, $customerId); $stmt->execute(); $stmt->close();
}
