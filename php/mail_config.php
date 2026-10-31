<?php
// App-local demo settings only. Delivery stays disabled until the student's
// authorised mailbox exists on the local mail server. Never use checkout email.
return [
    'enabled' => false,
    'recipient' => '', // e.g. the authorised student's local-account@localhost
    'sender' => '',    // an account permitted by that same local mail server
];
