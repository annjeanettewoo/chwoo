<?php
// Order Processing — Purchase signatures, owner-scoped saved orders and transactional finalisation.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/checkout_helpers.php';
require_once __DIR__ . '/pricing.php';

// --- Order Processing — Reviewed purchase identity ---
// Hash sorted variant lines, names/image paths, quantities, cent prices, delivery details and shared totals.
// Recomputing the signature detects changes since review; stock availability is checked separately.
function chwooOrderSignature($items, $details) {
    $lines = [];
    foreach ($items as $item) {
        $lines[$item['variant_id']] = [$item['variant_id'], $item['product_id'], $item['name'],
            $item['colour'], $item['size'], $item['quantity'], $item['price_cents'], $item['image_path']];
    }
    ksort($lines, SORT_NUMERIC);
    return hash('sha256', serialize([$lines, $details, chwooBagTotals($items)]));
}
// Validate the raw session bag and return sorted variant quantities, bounded to the stored unsigned-INT range.
function chwooOrderCart($cart) {
    if (!is_array($cart) || !$cart) { throw new RuntimeException('Your bag is empty. Please review your selections.'); }
    $quantities = [];
    foreach ($cart as $key => $entry) {
        if (!is_array($entry) || !is_int($entry['variant_id'] ?? null) || $entry['variant_id'] < 1
            || (string)$key !== (string)$entry['variant_id'] || !is_int($entry['quantity'] ?? null)
            || $entry['quantity'] < 1 || $entry['quantity'] > 4294967295) {
            throw new RuntimeException('Your bag contains an invalid selection. Please review it.');
        }
        $quantities[$entry['variant_id']] = $entry['quantity'];
    }
    ksort($quantities, SORT_NUMERIC);
    return $quantities;
}
// Retain one token for an unchanged review; changed customer/signature creates a token requiring a new handoff.
// chwoo_purchase holds customer_id, token, signature, reviewed cart quantities and payment-ready state.
function chwooPreparePurchase($items, $details, $customerId) {
    $signature = chwooOrderSignature($items, $details);
    $purchase = $_SESSION['chwoo_purchase'] ?? [];
    if (($purchase['customer_id'] ?? null) !== $customerId || ($purchase['signature'] ?? '') !== $signature) {
        $purchase = ['customer_id' => $customerId, 'token' => bin2hex(random_bytes(32)),
            'signature' => $signature, 'cart' => chwooOrderCart($_SESSION['chwoo_cart']), 'ready' => false];
        $_SESSION['chwoo_purchase'] = $purchase;
    }
    return $purchase['token'];
}
// --- Order Processing — Saved purchases and duplicate-token lookup ---
// Return the customer's saved order/items, or null. Snapshots avoid recalculating purchases from today's catalogue.
function chwooSavedOrder($conn, $orderId, $customerId) {
    $stmt = $conn->prepare('SELECT * FROM orders WHERE order_id = ? AND customer_id = ?');
    $stmt->bind_param('ii', $orderId, $customerId); $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$order) { return null; }
    $stmt = $conn->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY order_item_id');
    $stmt->bind_param('i', $orderId); $stmt->execute();
    $order['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    return $order;
}
// Find an already committed token for this customer; the transaction can request a locking read.
function chwooOrderForToken($conn, $token, $customerId, $lock = false) {
    $stmt = $conn->prepare('SELECT order_id FROM orders WHERE checkout_token = ? AND customer_id = ?' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->bind_param('si', $token, $customerId); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return $row ? (int)$row['order_id'] : null;
}
// After a committed purchase (or its recognised retry), subtract purchased units and discard checkout state.
function chwooPurchasedBagCleanup($quantities) {
    foreach ($quantities as $variantId => $quantity) {
        if (!isset($_SESSION['chwoo_cart'][$variantId])) { continue; }
        $remaining = $_SESSION['chwoo_cart'][$variantId]['quantity'] - $quantity;
        if ($remaining > 0) { $_SESSION['chwoo_cart'][$variantId]['quantity'] = $remaining; }
        else { unset($_SESSION['chwoo_cart'][$variantId]); }
    }
    unset($_SESSION['chwoo_checkout'], $_SESSION['chwoo_checkout_feedback'], $_SESSION['chwoo_purchase']);
}
// --- Order Processing — Confirm the reviewed simulation ---
// Return order_id and replayed; callers use replayed to avoid sending mail again for the same saved purchase.
function chwooFinaliseOrder($conn, $customerId, $token) {
    if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
        throw new RuntimeException('Please return to order review before simulating payment.');
    }
    // Database idempotency also covers retries after a committed request lost its response.
    if ($orderId = chwooOrderForToken($conn, $token, $customerId)) {
        if (($_SESSION['chwoo_purchase']['token'] ?? '') === $token
            && ($_SESSION['chwoo_purchase']['customer_id'] ?? null) === $customerId) {
            chwooPurchasedBagCleanup($_SESSION['chwoo_purchase']['cart']);
        }
        return ['order_id' => $orderId, 'replayed' => true];
    }
    $purchase = $_SESSION['chwoo_purchase'] ?? [];
    // Require this customer's completed review-to-payment handoff, validated details and unchanged quantities.
    $checkout = $_SESSION['chwoo_checkout'] ?? [];
    if (($purchase['customer_id'] ?? null) !== $customerId || !($purchase['ready'] ?? false)
        || !hash_equals($purchase['token'] ?? '', $token) || ($checkout['customer_id'] ?? null) !== $customerId) {
        throw new RuntimeException('Please return to order review before simulating payment.');
    }
    $details = chwooCheckoutValues($checkout['details'] ?? []);
    if (chwooCheckoutErrors($details)) { throw new RuntimeException('Please check your delivery details before continuing.'); }
    $quantities = chwooOrderCart($_SESSION['chwoo_cart'] ?? []);
    if ($quantities !== $purchase['cart']) { throw new RuntimeException('Your bag has changed. Please review it again before payment.'); }
    // --- Order Processing — Atomic order, item snapshots and inventory updates ---
    // These writes must succeed together; otherwise a partial order could consume stock without all items.
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT customer_id FROM customers WHERE customer_id = ? FOR UPDATE');
        $stmt->bind_param('i', $customerId); $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$customer) { throw new RuntimeException('Please sign in again before placing your order.'); }
        // The customer-row lock serialises this customer's confirmations; recheck the token after acquiring it.
        if ($orderId = chwooOrderForToken($conn, $token, $customerId, true)) {
            $conn->rollback();
            chwooPurchasedBagCleanup($purchase['cart']);
            return ['order_id' => $orderId, 'replayed' => true];
        }
        // Reload variants under lock before checking active status, stock and the reviewed catalogue signature.
        $items = [];
        $stmt = $conn->prepare('SELECT v.variant_id, v.stock_quantity, v.size_code,
            c.colour_name, c.price, c.image_path, p.product_id, p.product_name, p.is_active
            FROM product_variants v JOIN product_colours c ON c.colour_id = v.colour_id
            JOIN products p ON p.product_id = c.product_id WHERE v.variant_id = ? FOR UPDATE');
        // Stable variant order reduces deadlock risk when bags overlap.
        foreach ($quantities as $variantId => $quantity) {
            $stmt->bind_param('i', $variantId); $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row || !(int)$row['is_active']) { throw new RuntimeException('A selected variant is no longer available. Please review your bag.'); }
            if ((int)$row['stock_quantity'] < $quantity) {
                throw new RuntimeException('Insufficient stock for ' . $row['product_name'] . ', size ' . $row['size_code'] . '. Please review your bag.');
            }
            $items[] = ['variant_id' => $variantId, 'product_id' => (int)$row['product_id'],
                'name' => $row['product_name'], 'colour' => $row['colour_name'] ?: 'Colour unspecified',
                'size' => $row['size_code'], 'quantity' => $quantity,
                'price_cents' => chwooPriceCents($row['price']), 'image_path' => $row['image_path']];
        }
        $stmt->close();
        if (!hash_equals($purchase['signature'], chwooOrderSignature($items, $details))) {
            throw new RuntimeException('Your selections, prices or delivery details have changed. Please review your order again.');
        }
        // Shared cent totals are formatted as decimal strings for database money fields.
        $totals = chwooBagTotals($items);
        if ($totals['total_cents'] > 9999999999) { throw new RuntimeException('This order exceeds the supported total. Please reduce your bag.'); }
        $subtotal = chwooMoney($totals['subtotal_cents']);
        $shipping = chwooMoney($totals['delivery_cents']);
        $total = chwooMoney($totals['total_cents']);
        $address = $details['address'] . "\nSingapore " . $details['postal_code'];
        if ($details['notes'] !== '') { $address .= "\nDelivery notes: " . $details['notes']; }
        // Save delivery/totals and local Paid/Confirmed simulation statuses; the unique token guards duplicate orders.
        $stmt = $conn->prepare("INSERT INTO orders (checkout_token,customer_id,recipient_name,contact_phone,contact_email,
            delivery_address,item_subtotal,shipping_charge,total_amount,payment_status,order_status)
            VALUES (?,?,?,?,?,?,?,?,?,'Paid','Confirmed')");
        $stmt->bind_param('sisssssss', $token, $customerId, $details['full_name'], $details['phone'], $details['email'],
            $address, $subtotal, $shipping, $total);
        $stmt->execute(); $orderId = (int)$conn->insert_id; $stmt->close();
        // Capture purchased names, image paths, colour/size and unit prices independently of later catalogue edits.
        // The guarded stock UPDATE must affect one row for every item or the entire transaction is rolled back.
        $insert = $conn->prepare('INSERT INTO order_items (order_id,variant_id,product_name,product_image_path,selected_size,selected_colour,quantity,unit_price) VALUES (?,?,?,?,?,?,?,?)');
        $deduct = $conn->prepare('UPDATE product_variants SET stock_quantity = stock_quantity - ? WHERE variant_id = ? AND stock_quantity >= ?');
        foreach ($items as $item) {
            $unitPrice = chwooMoney($item['price_cents']);
            $insert->bind_param('iissssis', $orderId, $item['variant_id'], $item['name'], $item['image_path'],
                $item['size'], $item['colour'], $item['quantity'], $unitPrice);
            $insert->execute();
            $deduct->bind_param('iii', $item['quantity'], $item['variant_id'], $item['quantity']);
            $deduct->execute();
            if ($deduct->affected_rows !== 1) { throw new RuntimeException('Stock changed while processing your order. Please review your bag.'); }
        }
        $insert->close(); $deduct->close();
        // Commit before clearing any bag state; acknowledgement is attempted separately by the payment handler.
        $conn->commit();
    } catch (Throwable $error) {
        // Roll back the header, earlier item inserts and earlier deductions together; leave the session bag intact.
        $conn->rollback();
        throw $error;
    }
    // Never mutate the bag until the database commit has succeeded.
    chwooPurchasedBagCleanup($quantities);
    return ['order_id' => $orderId, 'replayed' => false];
}
