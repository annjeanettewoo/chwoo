<?php
// Email Acknowledgement — Tracked local-mail defaults; chwooMailConfig() merges recognised local overrides.
// Delivery stays disabled here; authorised machine-local settings belong in the ignored mail_config.local.php.
// PHP mail() uses separately configured loopback SMTP; these values do not provision or verify a mailbox.
return [
    'enabled' => false,
    'delivery_verified' => false, // Local verification metadata, not a delivery guarantee.
    'recipient' => '', // Fixed authorised mailbox only; never the checkout email.
    'sender' => '',
    'site_url' => 'http://localhost:8000/chwoo',
];
