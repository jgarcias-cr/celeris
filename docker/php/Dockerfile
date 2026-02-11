FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git unzip libonig-dev libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev zlib1g-dev libxml2-dev \
  && docker-php-ext-configure gd --with-jpeg --with-freetype \
  && docker-php-ext-install pdo pdo_mysql mbstring intl zip gd xml \
  && pecl install xdebug || true \
  && docker-php-ext-enable xdebug || true \
  && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Custom php.ini
COPY php.ini /usr/local/etc/php/conf.d/99-custom.ini

WORKDIR /var/www/html

# Entrypoint script to install deps if needed
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
CMD ["php-fpm"]
