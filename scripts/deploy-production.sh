#!/usr/bin/env bash
# Run on the Linux server after git pull / first deploy (not on your Windows dev machine unless WSL/Git Bash).
# Prerequisites: .env configured, composer & PHP 8.3+, database reachable.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "==> Composer (no dev)"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "==> Migrate"
php artisan migrate --force

echo "==> Storage link (ignore if already linked)"
php artisan storage:link 2>/dev/null || true

echo "==> Optimize (config, routes, events, Filament, views)"
php artisan optimize

echo "==> Done."
echo "    Next: keep a supervised process for: php artisan queue:work"
echo "    Cron: * * * * * cd $ROOT && php artisan schedule:run >> /dev/null 2>&1"
echo "    Dev note: php artisan optimize:clear"
