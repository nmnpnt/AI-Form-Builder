# =========================
# Stage 1: Build Vite
# =========================
FROM node:20 AS frontend

WORKDIR /var/www

COPY package*.json ./

RUN npm install

COPY . .

RUN npm run build


# =========================
# Stage 2: Laravel
# =========================
FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo_mysql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

COPY --from=frontend /var/www/public/build ./public/build

RUN composer install \
    --optimize-autoloader \
    --no-interaction \
    --no-dev \
    --no-scripts

CMD php artisan serve --host=0.0.0.0 --port=${PORT}