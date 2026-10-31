<?php
// Safe shared defaults. Put authorised machine-local settings in the gitignored
// mail_config.local.php instead. See README.md for the Mercury setup.
return [
    'enabled' => false,
    'delivery_verified' => false, // Local verification metadata, not a delivery guarantee.
    'recipient' => '', // Fixed authorised mailbox only; never the checkout email.
    'sender' => '',
    'site_url' => 'http://localhost:8000/chwoo',
];
