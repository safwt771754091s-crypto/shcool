FROM php:8.3-apache

RUN apt-get update && apt-get install -y git unzip libzip-dev libpng-dev libonig-dev libxml2-dev libicu-dev default-mysql-client && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip && a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader && \
    chown -R www-data:www-data storage bootstrap/cache && \
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views

# Render expects the HTTP process to bind to PORT=10000.
RUN sed -ri 's!/var/www/html!/var/www/html/public!g; s!Listen 80!Listen 10000!g; s!<VirtualHost \\*:80>!<VirtualHost *:10000>!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf /etc/apache2/ports.conf

EXPOSE 10000
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

CMD ["docker-entrypoint.sh"]
