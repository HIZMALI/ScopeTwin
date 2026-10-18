#!/bin/sh
set -eu
if [ "${APP_ENV:-}" != production ] || [ "${APP_DEBUG:-false}" != false ] || [ -z "${APP_KEY:-}" ]; then
    echo 'Refusing unsafe runtime: production environment, debug off and a fresh deployment key are required.' >&2
    exit 1
fi
php -r '$key = getenv("APP_KEY"); $raw = str_starts_with($key, "base64:") ? base64_decode(substr($key, 7), true) : $key; exit(is_string($raw) && strlen($raw) === 32 ? 0 : 1);' || {
    echo 'Refusing invalid application encryption key.' >&2
    exit 1
}
case "${APP_URL:-}" in
    https://*) ;;
    http://127.0.0.1:*|http://localhost:*)
        [ "${APP_ALLOW_INSECURE_HTTP:-false}" = true ] || exit 1 ;;
    *) echo 'APP_URL must use HTTPS (loopback rehearsal is the only exception).' >&2; exit 1 ;;
esac
# Never migrate automatically, regenerate keys or seed at boot.
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet
exec "$@"
