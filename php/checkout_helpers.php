<?php
// Delivery details only. Products, stock and totals always come from bag.php.
function chwooCheckoutValues($source) {
    $limits = ['full_name' => 120, 'email' => 190, 'phone' => 30,
        'address' => 1000, 'postal_code' => 6, 'notes' => 500];
    $values = [];
    foreach ($limits as $name => $limit) {
        $value = is_string($source[$name] ?? null) ? $source[$name] : '';
        // Keep one extra character so an oversized POST remains invalid.
        $values[$name] = mb_substr(trim(str_replace("\r\n", "\n", $value)), 0, $limit + 1, 'UTF-8');
    }
    return $values;
}

function chwooCheckoutErrors($values) {
    $errors = [];
    if ($values['full_name'] === '') {
        $errors['full_name'] = 'Please enter your full name.';
    } elseif (mb_strlen($values['full_name'], 'UTF-8') > 120 || preg_match('/[\x00-\x1F\x7F]/', $values['full_name'])) {
        $errors['full_name'] = 'Please use up to 120 characters on one line for your full name.';
    }
    if (strlen($values['email']) > 190 || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    $digits = preg_replace('/[^0-9]/', '', $values['phone']);
    if (strlen($values['phone']) > 30 || !preg_match('/\A\+?[0-9][0-9 ()-]*\z/D', $values['phone'])
        || strlen($digits) < 8 || strlen($digits) > 15) {
        $errors['phone'] = 'Please enter a valid contact number.';
    }
    if ($values['address'] === '') {
        $errors['address'] = 'Please enter your delivery address.';
    } elseif (mb_strlen($values['address'], 'UTF-8') > 1000) {
        $errors['address'] = 'Please use up to 1000 characters for your delivery address.';
    }
    if (!preg_match('/\A[0-9]{6}\z/D', $values['postal_code'])) {
        $errors['postal_code'] = 'Please enter a valid 6-digit Singapore postal code.';
    }
    if (mb_strlen($values['notes'], 'UTF-8') > 500) {
        $errors['notes'] = 'Please use up to 500 characters for delivery notes.';
    }
    foreach ($values as $name => $value) {
        if (!mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            $errors[$name] = 'Please check the text entered in this field.';
        }
    }
    return $errors;
}
