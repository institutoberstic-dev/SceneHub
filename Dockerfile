FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl unzip libpq-dev libonig-dev libzip-dev zip \
    && docker-php-ext-install pdo pdo_mysql mbstring zip \
    && apt-get clean

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy app files
COPY . .

RUN chown -R www-data:www-data /var/www \
&& chmod -R 775 storage

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader
# RUN composer install --ignore-platform-reqs

# Laravel setup
RUN php artisan config:clear && \
    php artisan route:clear && \
    php artisan view:clear &&

#$Port
EXPOSE 8000

CMD ["sh","-c","php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
