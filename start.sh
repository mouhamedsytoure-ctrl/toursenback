#!/bin/bash
set -e

INI="$(pwd)/php.ini"

php -c "$INI" artisan migrate --force
php -c "$INI" artisan config:cache
php -c "$INI" artisan route:cache
php -c "$INI" artisan view:cache

exec php -c "$INI" -S "0.0.0.0:${PORT:-8000}" -t public
