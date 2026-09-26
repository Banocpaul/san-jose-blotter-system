# ------------------------------------------------------------
# Frontend assets
# ------------------------------------------------------------
FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY package*.json ./

RUN npm install --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources

RUN npm run build


# ------------------------------------------------------------
# Laravel / PHP runtime
# ------------------------------------------------------------
FROM php:8.3-apache-bookworm

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    ca-certificates \
    curl \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libicu-dev \
    libxml2-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        zip \
        gd \
        intl \
        bcmath \
        opcache \
    && a2enmod rewrite headers deflate expires \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

COPY --from=assets /app/public/build ./public/build

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --classmap-authoritative

RUN sed -ri \
    's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf

RUN printf '%s\n' \
    '<Directory /var/www/html/public>' \
    '    AllowOverride All' \
    '    Options FollowSymLinks' \
    '    Require all granted' \
    '</Directory>' \
    > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

COPY docker/php-production.ini /usr/local/etc/php/conf.d/zz-production-performance.ini
COPY docker/apache-performance.conf /etc/apache2/conf-available/performance.conf

RUN a2enconf performance

RUN chown -R www-data:www-data \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache \
    && chmod -R ug+rwX \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache

COPY docker/start.sh /usr/local/bin/start-app

RUN chmod +x /usr/local/bin/start-app

EXPOSE 10000

CMD ["/usr/local/bin/start-app"]
