#!/bin/sh
set -e
cd /var/www/html

# Render (and similar) set PORT; Apache must listen on it when serving HTTP.
if [ "${1:-}" = 'apache2-foreground' ]; then
    PORT="${PORT:-80}"
    if [ -f /etc/apache2/ports.conf ]; then
        sed -i "s/^Listen [[:digit:]]*/Listen ${PORT}/" /etc/apache2/ports.conf
    fi
    if [ -f /etc/apache2/sites-available/000-default.conf ]; then
        sed -i "s/<VirtualHost \*:[[:digit:]]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
    fi
fi

php artisan storage:link --force 2>/dev/null || true

exec docker-php-entrypoint "$@"
