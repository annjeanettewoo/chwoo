<?php
require_once __DIR__ . '/orders.php';

function chwooAcknowledgementText($order) {
    $lines = ['CHWOO order acknowledgement', 'Order #' . $order['order_id'],
        'Hello ' . $order['recipient_name'] . ',', '',
        'Your order was recorded and confirmed using simulated payment. No real payment was taken.', ''];
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
    return implode("\r\n", $lines) . "\r\n";
}
function chwooLocalAcknowledgement($subject, $body, $config, $transport = null) {
    if (!($config['enabled'] ?? false)) { return 'Prepared'; }
    // Only explicitly configured local mailboxes; no visitor-controlled recipient.
    $localMailbox = '/\A[A-Za-z0-9._+-]+@localhost\z/Di';
    if (!preg_match($localMailbox, $config['recipient'] ?? '') || !preg_match($localMailbox, $config['sender'] ?? '')
        || !in_array(strtolower(ini_get('SMTP')), ['localhost', '127.0.0.1', '::1'], true)
        || ini_get('sendmail_path') !== '') { return 'Failed'; }
    $headers = 'From: ' . $config['sender'] . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
    try {
        // The optional callable is used by CLI tests without contacting any mailbox.
        $accepted = $transport ? $transport($config['recipient'], $subject, $body, $headers)
            : @mail($config['recipient'], $subject, $body, $headers);
        return $accepted ? 'Accepted' : 'Failed';
    } catch (Throwable $error) { return 'Failed'; }
}
function chwooAcknowledgeOrder($conn, $orderId, $customerId) {
    $order = chwooSavedOrder($conn, $orderId, $customerId);
    if (!$order || $order['acknowledgement_status'] !== 'Prepared') { return; }
    $config = require __DIR__ . '/mail_config.php';
    // A process-only test setting guarantees fixture purchases cannot send mail.
    if (getenv('CHWOO_MAIL_PREPARE_ONLY') === '1') { $config['enabled'] = false; }
    $status = chwooLocalAcknowledgement('CHWOO order #' . $orderId, chwooAcknowledgementText($order), $config);
    $stmt = $conn->prepare("UPDATE orders SET acknowledgement_status = ?,
        acknowledgement_sent_at = CASE WHEN ? = 'Accepted' THEN CURRENT_TIMESTAMP ELSE NULL END
        WHERE order_id = ? AND customer_id = ? AND acknowledgement_status = 'Prepared'");
    $stmt->bind_param('ssii', $status, $status, $orderId, $customerId); $stmt->execute(); $stmt->close();
}
