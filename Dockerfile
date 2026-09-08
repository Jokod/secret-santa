#syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1-php8.3 AS php

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN install-php-extensions \
    apcu \
    intl \
    opcache \
    pdo_mysql \
    zip

FROM php AS builder

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link composer.json composer.lock symfony.lock ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --link . .

RUN mkdir -p var/cache var/log \
    && composer dump-autoload --classmap-authoritative --no-dev \
    && composer dump-env prod \
    && export APP_SECRET=build \
       DATABASE_URL="mysql://root:root@database:3306/santa?serverVersion=8.0&charset=utf8mb4" \
       DEFAULT_URI=https://localhost \
       MAILER_DSN=null://null \
       MESSENGER_TRANSPORT_DSN=in-memory:// \
    && php bin/console importmap:install --no-interaction \
    && php bin/console asset-map:compile --no-interaction \
    && php bin/console cache:warmup --no-interaction \
    && chmod +x bin/console

FROM php AS app

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    SERVER_NAME=:80

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/* \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=builder /app /app

RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -so /dev/null http://localhost/ || exit 1

EXPOSE 80

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
