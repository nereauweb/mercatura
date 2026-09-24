#!/usr/bin/env bash
# Mercatura — release steps. Run from the application root after the code is in place.
# Needs PHP 8.4 and Node 22 on PATH (Vite 8). On Debian, keep system Node 18 and
# prefix: sudo -u www-data env PATH="/opt/node22/bin:/usr/local/bin:/usr/bin:/bin" bash deploy/deploy.sh
set -euo pipefail

PHP="${PHP:-php8.4}"

$PHP /usr/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund
npm run build

$PHP artisan down --retry=30 || true
$PHP artisan migrate --force
$PHP artisan storage:link
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache
$PHP artisan queue:restart
$PHP artisan up

echo "Deployed. Remember scout:import if the search index is new."
