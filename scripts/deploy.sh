#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$(pwd)}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
cd "$APP_DIR"

echo "[AutoCar] preflight"
$PHP_BIN artisan about --only=environment >/dev/null
$PHP_BIN artisan autocar:backup

echo "[AutoCar] dependencies"
$COMPOSER_BIN install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "[AutoCar] database"
$PHP_BIN artisan migrate --force

echo "[AutoCar] caches"
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo "[AutoCar] workers"
$PHP_BIN artisan queue:restart

# A reverse proxy/load balancer should switch traffic only after this health check succeeds.
$PHP_BIN artisan route:list --path=health >/dev/null

echo "[AutoCar] deployment completed"
