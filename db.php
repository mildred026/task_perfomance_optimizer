<?php
mysqli_report(MYSQLI_REPORT_OFF);

$database_url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');

if ($database_url) {
    $db_parts = parse_url($database_url);

    $db_host = $db_parts['host'] ?? 'localhost';
    $db_user = $db_parts['user'] ?? 'root';
    $db_pass = $db_parts['pass'] ?? '';
    $db_name = isset($db_parts['path']) ? ltrim($db_parts['path'], '/') : 'group_tracker';
    $db_port = $db_parts['port'] ?? 3306;
} else {
    $db_host = getenv('DB_HOST') ?: 'localhost';
    $db_user = getenv('DB_USER') ?: 'root';
    $db_pass = getenv('DB_PASS') ?: '';
    $db_name = getenv('DB_NAME') ?: 'group_tracker';
    $db_port = getenv('DB_PORT') ?: 3306;
}

$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name, (int) $db_port);

if ($conn->connect_error) {
    error_log(sprintf(
        'Database connection failed: %s; host=%s; port=%s; database=%s; user=%s; has_database_url=%s',
        $conn->connect_error,
        $db_host,
        $db_port,
        $db_name,
        $db_user,
        $database_url ? 'yes' : 'no'
    ));

    die("Database connection failed. Please check the Render database environment variables.");
}
?>
