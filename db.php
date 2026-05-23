<?php
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'group_tracker';
$db_port = getenv('DB_PORT') ?: 3306;

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, (int) $db_port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
