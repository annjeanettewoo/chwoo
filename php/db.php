
<?php
$host = 'localhost';
$username = 'root';
$password = '';
// Optional process-level override lets local tests use a disposable database.
$database = getenv('CHWOO_DB_NAME') ?: 'chwoo_db';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $username, $password, $database);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $error) {
    http_response_code(500);
    exit('Cannot connect to CHWOO database. Check that MySQL is running, import the SQL file, and verify db.php settings.');
}
