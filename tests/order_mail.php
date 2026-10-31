<?php
// CLI-only, in-memory snapshots and injected transports. No database or mail() calls.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../php/order_mail.php';
$checks = 0;
function mailCheck($condition, $label) {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    $GLOBALS['checks']++; echo 'PASS: ' . $label . "\n";
}
$order = [
    'order_id' => 752, 'recipient_name' => '李 & O\'Neil <guest>',
    'contact_email' => 'customer@example.com', 'contact_phone' => '+65 8123 4567 <phone>',
    'delivery_address' => "10 Test <Street>\n#01-02 & Singapore 012345\nNotes: \"Leave here\"",
    'item_subtotal' => '74.35', 'shipping_charge' => '5.00', 'total_amount' => '79.35', 'order_status' => 'Confirmed',
    'items' => [
        ['product_name' => 'Top <script>alert("x")</script> & lace', 'selected_colour' => 'Blue & "Cream"',
            'selected_size' => 'M<', 'quantity' => 3, 'unit_price' => '19.95'],
        ['product_name' => 'Skirt 李', 'selected_colour' => 'Brown', 'selected_size' => 'S', 'quantity' => 2, 'unit_price' => '7.25'],
    ],
];
$defaults = chwooMailConfig(false);
mailCheck($defaults['enabled'] === false && $defaults['delivery_verified'] === false
    && $defaults['recipient'] === '' && $defaults['sender'] === '', 'Tracked mail defaults are disabled with empty mailboxes');
mailCheck(chwooOrderHistoryUrl($defaults) === 'http://localhost:8000/chwoo/php/account.php?section=orders', 'Default order-history URL is absolute and targets the account orders section');
$config = ['enabled' => true, 'recipient' => 'authorised@localhost', 'sender' => 'store@localhost', 'site_url' => 'https://store.example/chwoo/'];
$url = 'https://store.example/chwoo/php/account.php?section=orders';
$html = chwooAcknowledgementHtml($order, $config);
$text = chwooAcknowledgementText($order, $config);
mailCheck(str_contains($html, '#BED1E1') && str_contains($html, '#4B2E1F') && str_contains($html, '#FFF3C2')
    && str_contains($html, 'CHWOO Fashion Studio') && str_contains($html, 'Your pieces are confirmed.'), 'HTML has CHWOO branding, palette and friendly confirmation');
mailCheck(str_contains($html, '李 &amp; O&#039;Neil &lt;guest&gt;')
    && str_contains($html, 'Top &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; lace')
    && str_contains($html, 'Blue &amp; &quot;Cream&quot;') && str_contains($html, 'M&lt;')
    && str_contains($html, '10 Test &lt;Street&gt;') && str_contains($html, '&lt;phone&gt;')
    && !str_contains($html, '<script>'), 'HTML escapes recipient, product, colour, size, phone and multiline delivery snapshots');
mailCheck(str_contains($html, 'ORDER #752') && str_contains($html, 'SGD&nbsp;$19.95')
    && str_contains($html, 'SGD&nbsp;$59.85') && str_contains($html, 'SGD&nbsp;$14.50')
    && str_contains($html, 'SGD $74.35') && str_contains($html, 'SGD $5.00') && str_contains($html, 'SGD $79.35'),
    'HTML shows both saved items, exact integer-cent line totals, subtotal, delivery and final total');
$dom = new DOMDocument();
$previousErrors = libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors(); libxml_use_internal_errors($previousErrors);
$xpath = new DOMXPath($dom);
mailCheck($xpath->query('//a')->length === 1 && $xpath->query('//a')->item(0)->getAttribute('href') === $url
    && trim($xpath->query('//a')->item(0)->textContent) === 'View My Orders', 'HTML button links to the configured absolute order-history URL');
mailCheck($xpath->query('//tbody/tr')->length === 2 && str_contains($xpath->query('//tbody/tr')->item(0)->textContent, '3')
    && str_contains($xpath->query('//tbody/tr')->item(1)->textContent, '2'), 'HTML renders every purchased item with quantity');
mailCheck($xpath->query('//img|//script|//link|//style|//form')->length === 0 && str_contains($html, 'role="presentation"')
    && str_contains($html, 'style="'), 'Email uses inline CSS and table layout without images, scripts or external styles');
mailCheck(str_contains($text, $order['recipient_name']) && str_contains($text, 'Order #752')
    && str_contains($text, '3 x SGD $19.95 = SGD $59.85') && str_contains($text, '2 x SGD $7.25 = SGD $14.50')
    && str_contains($text, 'Subtotal: SGD $74.35') && str_contains($text, 'Delivery: SGD $5.00')
    && str_contains($text, 'Final amount: SGD $79.35') && str_contains($text, $order['delivery_address'])
    && str_contains($text, 'View My Orders: ' . $url), 'Plain text retains readable saved order, variants, amounts, delivery and history link');
foreach (['/chwoo', 'javascript:alert(1)', 'ftp://localhost/chwoo', 'http://user:pass@localhost/chwoo',
    'http://localhost/chwoo?extra=1', 'http://localhost/chwoo#fragment', "http://localhost/\r\nBcc:injected", []] as $invalidUrl) {
    $rejected = false;
    try { chwooOrderHistoryUrl(['site_url' => $invalidUrl]); } catch (RuntimeException $error) { $rejected = true; }
    mailCheck($rejected, 'Invalid base URL is rejected: ' . json_encode($invalidUrl));
}
$calls = [];
$capture = function ($to, $subject, $body, $headers) use (&$calls) {
    $calls[] = compact('to', 'subject', 'body', 'headers'); return true;
};
mailCheck(chwooLocalAcknowledgement('test', $text, $defaults, $capture, $html) === 'Prepared' && count($calls) === 0,
    'Disabled configuration never invokes transport');
foreach (['customer@example.com', 'authorised@localhost.example', 'Name <authorised@localhost>',
    'one@localhost,two@localhost', "authorised@localhost\r\nBcc:other@localhost", '', [], null] as $invalidRecipient) {
    mailCheck(chwooLocalAcknowledgement('test', $text, array_replace($config, ['recipient' => $invalidRecipient]), $capture, $html) === 'Failed'
        && count($calls) === 0, 'Unapproved recipient form is refused before transport: ' . json_encode($invalidRecipient));
}
mailCheck(chwooLocalAcknowledgement('test', $text, array_replace($config, ['sender' => 'external@example.com']), $capture, $html) === 'Failed'
    && count($calls) === 0, 'External sender is refused before transport');
mailCheck(chwooLocalAcknowledgement("test\r\nBcc:other@localhost", $text, $config, $capture, $html) === 'Failed'
    && count($calls) === 0, 'Header injection in subject is refused before transport');
$originalSmtp = ini_get('SMTP');
ini_set('SMTP', 'external.example');
mailCheck(chwooLocalAcknowledgement('test', $text, $config, $capture, $html) === 'Failed'
    && count($calls) === 0, 'Non-loopback SMTP is refused before transport');
ini_set('SMTP', 'localhost');
mailCheck(chwooLocalAcknowledgement('CHWOO order #752', $text, $config, $capture, $html) === 'Accepted'
    && count($calls) === 1 && $calls[0]['to'] === 'authorised@localhost' && $calls[0]['to'] !== $order['contact_email'],
    'Mock acceptance uses only the explicitly configured local recipient, never customer contact email');
$message = $calls[0];
mailCheck(str_contains($message['headers'], "From: store@localhost\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative;")
    && preg_match('/boundary="(chwoo_[a-f0-9]+)"/', $message['headers'], $match) === 1, 'MIME headers declare the sender and multipart boundary');
$parts = explode('--' . $match[1], $message['body']);
mailCheck(count($parts) === 4 && $parts[3] === "--\r\n", 'MIME has exactly two alternatives and a closing boundary');
foreach ([1 => ['text/plain', $text], 2 => ['text/html', $html]] as $index => [$type, $expected]) {
    [$partHeaders, $encoded] = explode("\r\n\r\n", ltrim($parts[$index], "\r\n"), 2);
    mailCheck(str_contains($partHeaders, 'Content-Type: ' . $type . '; charset=UTF-8')
        && str_contains($partHeaders, 'Content-Transfer-Encoding: quoted-printable')
        && quoted_printable_decode(substr($encoded, 0, -2)) === $expected,
        'UTF-8 ' . $type . ' part decodes exactly to its original content');
}
mailCheck(chwooLocalAcknowledgement('test', $text, $config, function () { return false; }, $html) === 'Failed', 'Mock false transport result is Failed');
mailCheck(chwooLocalAcknowledgement('test', $text, $config, function () { throw new RuntimeException('Mock failure'); }, $html) === 'Failed',
    'Mock transport exception is contained');
ini_set('SMTP', $originalSmtp);
session_destroy();
echo $checks . " order mail checks passed; no database access or actual messages.\n";
