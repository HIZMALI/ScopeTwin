FROM node:22-bookworm-slim@sha256:c3de60bf2f9dd0ac6370e6117950ff62d6e339527e7472301c9c78a017978392 AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js tsconfig.json ./
RUN npm run build

FROM dunglas/frankenphp:builder-php8.4-alpine@sha256:0ddba956961804f57707fd6ee38bafb70e3ecc2d750ac04cdfababf7bc6c2959 AS server-build
# Security repair for CVE-2026-78669 in the stock server binary (x/net v0.59.0).
# Use the matching official source/PHP builder; no extra Caddy modules are added.
RUN cd /go/src/app/caddy \
    && go get golang.org/x/net@v0.60.0 \
    && cd frankenphp \
    && GOBIN=/usr/local/bin ../../go.sh install -ldflags "-w -s" -buildvcs=false

FROM dunglas/frankenphp:1-php8.4-alpine@sha256:001bf9290f6047b9af78e7cc04913ac63f839a33131018efd9e52e61b867a5e9 AS php-base
RUN apk upgrade --no-cache && install-php-extensions pdo_pgsql mbstring intl zip opcache
WORKDIR /app

FROM php-base AS dependencies
COPY --from=composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY routes ./routes
COPY database ./database
COPY artisan ./artisan
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts

FROM php-base AS runtime
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr LOG_LEVEL=warning
COPY --from=server-build /usr/local/bin/frankenphp /usr/local/bin/frankenphp
COPY --from=dependencies /app /app
COPY --from=frontend /app/public/build /app/public/build
COPY public /app/public
COPY resources/views /app/resources/views
COPY deploy /app/deploy
COPY deploy/Caddyfile /etc/caddy/Caddyfile
COPY deploy/php.ini /usr/local/etc/php/conf.d/production.ini
RUN addgroup -g 33 app && adduser -D -H -u 33 -G app app \
    && (test -z "$(getcap /usr/local/bin/frankenphp)" || setcap -r /usr/local/bin/frankenphp) \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache /config/caddy /data/caddy \
    && chown -R app:app storage bootstrap/cache /config/caddy /data/caddy \
    && chmod +x deploy/entrypoint.sh
USER app
RUN frankenphp validate --config /etc/caddy/Caddyfile
EXPOSE 8080
HEALTHCHECK --interval=15s --timeout=5s --start-period=25s --retries=5 CMD php deploy/healthcheck.php
ENTRYPOINT ["/app/deploy/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
