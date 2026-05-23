<?php
mysqli_report(MYSQLI_REPORT_OFF);

$database_url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
$running_in_production = getenv('RENDER') === 'true' || getenv('APP_ENV') === 'production';

if ($database_url) {
    $db_parts = parse_url($database_url);

    $db_host = $db_parts['host'] ?? 'localhost';
    $db_user = isset($db_parts['user']) ? urldecode($db_parts['user']) : 'root';
    $db_pass = isset($db_parts['pass']) ? urldecode($db_parts['pass']) : '';
    $db_name = isset($db_parts['path']) ? ltrim($db_parts['path'], '/') : 'group_tracker';
    $db_port = $db_parts['port'] ?? 3306;
} else {
    if ($running_in_production && !getenv('DB_HOST')) {
        error_log('Database configuration missing in production. Set DATABASE_URL in the service environment variables.');
        die("Database connection failed. DATABASE_URL is not configured.");
    }

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

$conn->set_charset('utf8mb4');

// One-time migration for Railway/MySQL: GROUPS is a reserved keyword in newer MySQL.
// If the old table exists and the new table does not, rename it automatically.
$project_groups_exists = $conn->query("SHOW TABLES LIKE 'project_groups'");
$old_groups_exists = $conn->query("SHOW TABLES LIKE 'groups'");
if (
    $project_groups_exists &&
    $old_groups_exists &&
    $project_groups_exists->num_rows === 0 &&
    $old_groups_exists->num_rows > 0
) {
    if (!$conn->query("RENAME TABLE `groups` TO project_groups")) {
        error_log('Failed to rename `groups` table to project_groups: ' . $conn->error);
    }
}
?>
