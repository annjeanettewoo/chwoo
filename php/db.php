
<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'chwoo_db';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $username, $password, $database);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $error) {
    http_response_code(500);
    exit('Cannot connect to CHWOO database. Check that MySQL is running, import the SQL file, and verify db.php settings.');
}
