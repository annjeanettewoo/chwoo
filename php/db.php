
<?php
// Database Connection — Shared MySQLi connection exposed to callers as $conn.
$host = 'localhost';
$username = 'root';
$password = '';
// A process-level database-name override allows an isolated database without changing callers.
$database = getenv('CHWOO_DB_NAME') ?: 'chwoo_db';

// Connection/query failures throw exceptions so callers can handle transaction failures explicitly.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $username, $password, $database);
    // Match catalogue and customer text encoding; HTML escaping remains the renderer's responsibility.
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $error) {
    // Stop the request with a generic setup message rather than exposing connection exception details.
    http_response_code(500);
    exit('Cannot connect to CHWOO database. Check that MySQL is running, import the SQL file, and verify db.php settings.');
}
