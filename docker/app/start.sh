#!/usr/bin/env sh
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

mkdir -p storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache || true

php artisan key:generate --force || true
php artisan migrate --force || true

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
