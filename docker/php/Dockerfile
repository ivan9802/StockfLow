# Image PHP de StockFlow.
# Étape "base" : PHP-FPM 8.4 et les extensions dont l'application a besoin.
# Étape "dev"  : ajoute Composer et Node pour travailler dans le conteneur.
# Étapes "vendor", "assets" et "prod" : image de production autonome, sans outil
# de développement, code et dépendances copiés dans l'image (docs/07-deployment).

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

# ---------------------------------------------------------------- Production

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

FROM base AS prod

COPY docker/php/opcache-prod.ini /usr/local/etc/php/conf.d/zz-opcache.ini

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Aucun secret dans l'image : le fichier .env est fourni au démarrage par compose.prod.yaml.
RUN rm -rf .env tests node_modules \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
CMD ["php-fpm"]

# Serveur web de production : Nginx avec les fichiers publics et les CSS et JS compilés
# embarqués dans l'image. Chaque déploiement livre donc la version des assets qui va
# avec le code (un volume partagé, lui, garderait l'ancienne version).
FROM nginx:1.28-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
COPY --from=assets /app/public/build /var/www/html/public/build
RUN rm -f /var/www/html/public/index.php /var/www/html/public/hot
