<?php
// CLI-only worker for real concurrent transactions in the disposable test DB.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!preg_match('/\Achwoo_task06_test_[a-f0-9]{12}\z/D', getenv('CHWOO_DB_NAME') ?: '')) { exit(2); }
require_once __DIR__ . '/../php/orders.php';
require_once __DIR__ . '/../php/db.php';
$_SESSION = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
try {
    $result = chwooFinaliseOrder($conn, $_SESSION['chwoo_customer']['customer_id'], $_SESSION['chwoo_purchase']['token']);
    echo json_encode(['ok' => true] + $result);
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'error' => $error->getMessage()]);
}
$conn->close();
session_destroy();
