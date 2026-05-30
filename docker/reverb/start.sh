#!/usr/bin/env sh
set -e

cd /var/www/html

echo "[reverb] Aguardando vendor/autoload.php (setup do container app)..."
until [ -f vendor/autoload.php ]; do
    sleep 2
done

echo "[reverb] Iniciando WebSocket server em ${REVERB_SERVER_HOST:-0.0.0.0}:${REVERB_SERVER_PORT:-8080} ..."
exec php artisan reverb:start \
    --host="${REVERB_SERVER_HOST:-0.0.0.0}" \
    --port="${REVERB_SERVER_PORT:-8080}" \
    --no-interaction
