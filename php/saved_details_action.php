<?php
require_once __DIR__ . '/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
$customer = chwooCurrentCustomer();
if (!$customer) { header('Location: login.php', true, 303); exit; }
$section = 'addresses';
$token = $_POST['form_token'] ?? '';
$id = is_string($_POST['id'] ?? null) ? filter_var($_POST['id'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) : false;
if (!is_string($token) || !hash_equals($_SESSION['chwoo_form_token'], $token) || !$id) {
    $_SESSION['chwoo_auth_notice'] = 'Please refresh the page and try again.';
} else {
    require_once __DIR__ . '/db.php';
    $table = 'customer_addresses';
    try {
        $stmt = $conn->prepare('DELETE FROM ' . $table . ' WHERE id=? AND customer_id=?');
        $stmt->bind_param('ii', $id, $customer['customer_id']); $stmt->execute(); $stmt->close();
    } catch (mysqli_sql_exception $error) { $_SESSION['chwoo_auth_notice'] = 'We could not remove this saved detail. Please try again later.'; }
}
header('Location: account.php?section=' . $section, true, 303); exit;
