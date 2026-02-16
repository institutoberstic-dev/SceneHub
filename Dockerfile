# ===============================
# Base image: PHP 8.2 FPM Bullseye
# ===============================
FROM php:8.2-fpm-bullseye

# ===============================
# Dependencias del sistema
# ===============================
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    gnupg2 \
    ca-certificates \
    unixodbc \
    unixodbc-dev \
    libonig-dev \
    libzip-dev \
    zip \
    libssl-dev \
    g++ \
    make \
    apt-transport-https \
    && docker-php-ext-install mbstring zip bcmath opcache \
    && apt-get clean

# ===============================
# Microsoft ODBC Driver (Bullseye)
# ===============================
RUN curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add - \
    && curl https://packages.microsoft.com/config/debian/11/prod.list \
       -o /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18

# ===============================
#PHP SQL Server extensions
# ===============================
RUN pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# ===============================
# Composer
# ===============================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ===============================
# Laravel App
# ===============================
WORKDIR /var/www
COPY . .

RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 storage

# ===============================
# PHP deps
# ===============================
RUN composer install --no-dev --optimize-autoloader \
    && composer dump-autoload

# ===============================
# Laravel cache
# ===============================
RUN php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear

# ===============================
# Expose port and run
# ===============================
EXPOSE 8000
CMD ["sh","-c","php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
