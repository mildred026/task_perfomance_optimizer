<?php
$database_url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
$db_parts = $database_url ? parse_url($database_url) : [];

header('Content-Type: text/plain');

echo "DATABASE_URL present: " . ($database_url ? "yes" : "no") . PHP_EOL;
echo "MYSQL_URL present: " . (getenv('MYSQL_URL') ? "yes" : "no") . PHP_EOL;
echo "Parsed host: " . ($db_parts['host'] ?? '(missing)') . PHP_EOL;
echo "Parsed port: " . ($db_parts['port'] ?? '(missing)') . PHP_EOL;
echo "Parsed database: " . (isset($db_parts['path']) ? ltrim($db_parts['path'], '/') : '(missing)') . PHP_EOL;
echo "Parsed user: " . ($db_parts['user'] ?? '(missing)') . PHP_EOL;
echo "Password present: " . (!empty($db_parts['pass']) ? "yes" : "no") . PHP_EOL;
?>
