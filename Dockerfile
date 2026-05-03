FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev libzip-dev libxml2-dev libonig-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring xml bcmath zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction
RUN npm ci && npm run build

RUN cp .env.example .env && php artisan key:generate --force

EXPOSE 8000

CMD ["sh", "-c", "php artisan migrate:fresh --seed --force && php artisan l5-swagger:generate && php artisan config:cache && php artisan serve --host=0.0.0.0 --port=8000"]
