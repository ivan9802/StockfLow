# Image PHP de StockFlow.
# Étape "base" : PHP-FPM 8.4 et les extensions dont l'application a besoin.
# Étape "dev"  : ajoute Composer et Node pour travailler dans le conteneur.
# L'étape de production est ajoutée en phase 7 (docs/07-deployment).

FROM php:8.4-fpm-bookworm AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_mysql gd intl zip bcmath opcache pcntl \
    && apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client unzip git \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-stockflow.ini

WORKDIR /var/www/html

FROM base AS dev

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && git config --system --add safe.directory /var/www/html

COPY docker/php/fpm-dev.conf /usr/local/etc/php-fpm.d/zz-dev.conf

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

CMD ["php-fpm", "-R"]
