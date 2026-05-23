#!/bin/sh
set -eu

APP_PORT="${PORT:-10000}"

sed -i "s/^Listen .*/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:.*/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "Starting Task Performance Optimizer on port ${APP_PORT}"
echo "Database URL configured: $([ -n "${DATABASE_URL:-}${MYSQL_URL:-}" ] && echo yes || echo no)"

exec apache2-foreground
