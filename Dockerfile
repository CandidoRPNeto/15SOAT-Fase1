# ---- Stage 1: PHP dependencies ----
FROM php:8.4-cli AS deps

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev libzip-dev libxml2-dev libonig-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring xml bcmath zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

# ---- Stage 2: frontend assets ----
FROM node:22-alpine AS frontend

WORKDIR /var/www

COPY package.json package-lock.json ./
RUN npm ci

COPY resources resources
COPY vite.config.js ./

RUN npm run build

# ---- Stage 3: final runtime image ----
FROM php:8.4-cli AS runtime

RUN apt-get update && apt-get install -y \
    curl libpq-dev libzip-dev libxml2-dev libonig-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring xml bcmath zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Datadog APM. O injetor single-step de host (DD_APM_INSTRUMENTATION_ENABLED=docker)
# só tem pacote para Debian/RHEL/SUSE — num host Arch ele falha com "OS not
# supported", então o tracer é instalado na própria imagem, que é Debian.
# dd-trace-php 1.25.1 cobre o PHP 8.4 desta base (ABI 20240924).
RUN curl -sLO https://github.com/DataDog/dd-trace-php/releases/latest/download/datadog-setup.php \
    && php datadog-setup.php --php-bin=all --enable-appsec=false \
    && rm datadog-setup.php

WORKDIR /var/www

COPY --from=deps /var/www .
COPY --from=frontend /var/www/public/build public/build

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs \
    && cp .env.example .env && php artisan key:generate --force

EXPOSE 8000

HEALTHCHECK --interval=10s --timeout=5s --start-period=20s --retries=5 \
    CMD curl -f http://localhost:8000/up || exit 1

CMD ["sh", "-c", "php artisan migrate:fresh --seed --force && php artisan l5-swagger:generate && php artisan config:cache && php artisan serve --host=0.0.0.0 --port=8000"]
