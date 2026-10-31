<?php
require_once __DIR__ . '/checkout_helpers.php';
// These tables contain customer-owned addresses only.
function chwooSavedDetails($conn, $customerId, $kind, $id = null) {
    $table = 'customer_addresses';
    try {
        $stmt = $conn->prepare('SELECT * FROM ' . $table . ' WHERE customer_id=?' . ($id !== null ? ' AND id=?' : '') . ' ORDER BY id DESC');
        if ($id !== null) { $stmt->bind_param('ii', $customerId, $id); }
        else { $stmt->bind_param('i', $customerId); }
        $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return $rows;
    } catch (mysqli_sql_exception $error) {
        if ($error->getCode() !== 1146) { throw $error; }
        return []; // New checkout still works before optional saved-detail tables are installed.
    }
}
function chwooSaveAddress($conn, $customerId, $values) {
    if (chwooCheckoutErrors($values)) { throw new RuntimeException('Please check your delivery details.'); }
    $identity = [];
    foreach (['full_name','phone','address','postal_code'] as $field) {
        $identity[] = mb_strtolower(preg_replace('/\s+/u', ' ', trim($values[$field])), 'UTF-8');
    }
    $fingerprint = hash('sha256', serialize($identity));
    $stmt = $conn->prepare('INSERT INTO customer_addresses (customer_id,recipient_name,contact_email,contact_phone,address,postal_code,notes,fingerprint) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id');
    $stmt->bind_param('isssssss', $customerId, $values['full_name'], $values['email'], $values['phone'], $values['address'], $values['postal_code'], $values['notes'], $fingerprint);
    $stmt->execute(); $stmt->close();
}
