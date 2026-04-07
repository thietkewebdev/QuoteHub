#!/usr/bin/env bash
# Render runs pre-deploy without a shell; chaining with && breaks argv parsing.
# Use: preDeployCommand: bash scripts/render-predeploy.sh
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

php artisan migrate --force --no-interaction
php artisan storage:link --force
php artisan optimize
