#!/bin/sh
set -e

# If composer.lock doesn't exist, install dependencies (useful for fresh containers)
if [ -f composer.json ] && [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --optimize-autoloader
fi

exec "$@"
