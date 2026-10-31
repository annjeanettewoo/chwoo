<?php
// Additional scenarios run inside task06.php's disposable database/server only.
if (PHP_SAPI !== 'cli' || !isset($testDb, $alice, $bob)) { http_response_code(404); exit; }
require_once __DIR__ . '/../php/saved_details.php';
check(countRows('customer_addresses') === 0, 'Unchecked address saving has not persisted any records');
$details = delivery($csrf); $details['save_address'] = '1';
$details['full_name'] = 'Saved Address Owner'; $details['postal_code'] = '654321';
request($alice, 'php/checkout.php', $details);
check(countRows('customer_addresses') === 1, 'Opt-in saves a validated address during checkout');
request($alice, 'php/checkout.php', $details);
check(countRows('customer_addresses') === 1, 'Repeated address saving does not duplicate a record');
$address = chwooSavedDetails($conn, $customerIds['alice'], 'address')[0];
check(str_contains(request($alice, 'php/account.php?section=addresses')['html'], 'Singapore 654321'), 'Account Addresses displays the saved record');
request($alice, 'php/checkout.php', ['form_token'=>$csrf,'action'=>'use_address','address_id'=>$address['id']]);
$page = request($alice, 'php/checkout.php');
check(str_contains($page['html'], 'value="654321"') && str_contains($page['html'], 'value="Saved Address Owner"'), 'Saved address prefills an editable checkout form');
$oneOff = $details; unset($oneOff['save_address']); $oneOff['address'] = '99 One-off Avenue';
request($alice, 'php/checkout.php', $oneOff);
check(str_contains(request($alice, 'php/checkout.php?step=review')['html'], '99 One-off Avenue')
    && chwooSavedDetails($conn, $customerIds['alice'], 'address')[0]['address'] !== '99 One-off Avenue', 'One-off delivery edits reach review without overwriting the saved address');
$bobCsrf = field(request($bob, 'php/account.php')['html'], 'form_token');
add($bob, $bobCsrf, 2, 2, 'S');
request($bob, 'php/checkout.php', ['form_token'=>$bobCsrf,'action'=>'use_address','address_id'=>$address['id']]);
check(str_contains(request($bob, 'php/checkout.php')['html'], 'Please select an address from your account.'), 'Another customer cannot reuse a foreign address');
request($bob, 'php/saved_details_action.php', ['form_token'=>$bobCsrf,'id'=>$address['id'],'kind'=>'address']);
check(countRows('customer_addresses') === 1 && !str_contains(request($bob, 'php/account.php?section=addresses')['html'], 'Singapore 654321'), 'Saved-address reads and removal are isolated by customer');
request($alice, 'php/saved_details_action.php', ['form_token'=>'invalid','id'=>$address['id'],'kind'=>'address']);
check(countRows('customer_addresses') === 1, 'Address removal rejects invalid CSRF');
$details['address'] = '';
request($alice, 'php/checkout.php', $details);
check(countRows('customer_addresses') === 1, 'Invalid delivery details cannot be saved');
request($alice, 'php/saved_details_action.php', ['form_token'=>$csrf,'id'=>$address['id'],'kind'=>'address']);
check(countRows('customer_addresses') === 0, 'Owner can remove a saved address');
$page = request($alice, 'php/order_confirmation.php?id=' . $orderId);
check(str_contains($page['html'], 'Singapore 012345') && str_contains($page['html'], '<dt>Order status</dt>')
    && str_contains($page['html'], '<dt>Order date</dt>') && str_contains($page['html'], '<dt>Payment status</dt>')
    && !str_contains($page['html'], '(simulated)') && !str_contains($page['html'], 'This purchase used demonstration'), 'Saved order snapshot and clean confirmation fields survive address removal');
check(str_contains(chwooAcknowledgementText($order), 'Thank you for shopping with CHWOO!')
    && !str_contains(chwooAcknowledgementText($order), 'simulated payment'), 'Acknowledgement uses customer-facing confirmed-order wording');
check((int)$conn->query("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('saved_cards','customer_payment_methods')")->fetch_assoc()['n'] === 0,
    'No payment-card table exists in the fresh schema');
