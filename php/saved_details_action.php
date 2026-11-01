<?php
// Saved Details — Remove an address through an authenticated, CSRF-protected POST and return to Account.
require_once __DIR__ . '/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
$customer = chwooCurrentCustomer();
if (!$customer) { header('Location: login.php', true, 303); exit; }
// Validate the submitted record ID; the owner comes only from session identity, never the form.
$section = 'addresses';
$token = $_POST['form_token'] ?? '';
$id = is_string($_POST['id'] ?? null) ? filter_var($_POST['id'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) : false;
if (!is_string($token) || !hash_equals($_SESSION['chwoo_form_token'], $token) || !$id) {
    $_SESSION['chwoo_auth_notice'] = 'Please refresh the page and try again.';
} else {
    require_once __DIR__ . '/db.php';
    $table = 'customer_addresses';
    try {
        // Scope deletion to this customer even if another customer's address ID was submitted.
        $stmt = $conn->prepare('DELETE FROM ' . $table . ' WHERE id=? AND customer_id=?');
        $stmt->bind_param('ii', $id, $customer['customer_id']); $stmt->execute(); $stmt->close();
    } catch (mysqli_sql_exception $error) { $_SESSION['chwoo_auth_notice'] = 'We could not remove this saved detail. Please try again later.'; }
}
// Redirect after POST so refresh does not repeat the removal; any failure notice uses the shared header.
header('Location: account.php?section=' . $section, true, 303); exit;
