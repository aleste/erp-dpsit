FROM php:8.3-fpm-alpine

# Instalar dependencias del sistema requeridas para Laravel + PostgreSQL
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    bash \
    git \
    curl

# Instalar extensiones PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
        intl \
        bcmath \
        pcntl \
        opcache

# Instalar Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

EXPOSE 9000
# -R permite a php-fpm ejecutarse como root para compatibilidad total con montajes NTFS en Windows/WSLC
CMD ["php-fpm", "-R"]
