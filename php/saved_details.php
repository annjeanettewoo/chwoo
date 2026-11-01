<?php
// Saved Details — Customer-owned delivery-address lookup and validated, deduplicated insertion.
require_once __DIR__ . '/checkout_helpers.php';
// --- Saved Details — Retrieve addresses for Account or checkout reuse ---
// Return newest-first rows for customerId, optionally restricted to one id; a missing address table returns [].
// The kind parameter is retained by callers, but this helper always reads customer_addresses.
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
        return []; // One-off checkout remains usable without saved-address storage.
    }
}
// --- Saved Details — Save an optional checkout address ---
// Accept normalised checkout values and the caller's customer ID; repeat delivery validation before insertion.
// Equivalent name/phone/address/postal-code values share a fingerprint; email/notes are not part of that identity.
function chwooSaveAddress($conn, $customerId, $values) {
    if (chwooCheckoutErrors($values)) { throw new RuntimeException('Please check your delivery details.'); }
    $identity = [];
    // Lowercase and collapse whitespace for comparison without changing the stored presentation values.
    foreach (['full_name','phone','address','postal_code'] as $field) {
        $identity[] = mb_strtolower(preg_replace('/\s+/u', ' ', trim($values[$field])), 'UTF-8');
    }
    $fingerprint = hash('sha256', serialize($identity));
    // The customer/fingerprint unique key makes repeated saves a no-op; existing details are not overwritten.
    $stmt = $conn->prepare('INSERT INTO customer_addresses (customer_id,recipient_name,contact_email,contact_phone,address,postal_code,notes,fingerprint) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id');
    $stmt->bind_param('isssssss', $customerId, $values['full_name'], $values['email'], $values['phone'], $values['address'], $values['postal_code'], $values['notes'], $fingerprint);
    $stmt->execute(); $stmt->close();
}
