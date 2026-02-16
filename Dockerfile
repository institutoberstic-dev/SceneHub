FROM php:8.2-fpm

# ===============================
#  Dependencias del sistema
# ===============================
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    gnupg2 \
    unixodbc \
    unixodbc-dev \
    libonig-dev \
    libzip-dev \
    libpq-dev \
    zip \
    && docker-php-ext-install mbstring zip pdo \
    && apt-get clean

# Microsoft ODBC Driver for SQL Server (Debian 12)
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
    | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && curl -fsSL https://packages.microsoft.com/config/debian/11/prod.list \
    | sed 's|deb |deb [signed-by=/usr/share/keyrings/microsoft-prod.gpg] |g' \
    > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18

# ===============================
#  Extensiones PHP sqlsrv
# ===============================
RUN pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# ===============================
#  Composer
# ===============================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ===============================
# App Laravel
# ===============================
WORKDIR /var/www
COPY . .

# Permisos Laravel
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 storage

# ===============================
# Dependencias PHP
# ===============================
RUN composer install --no-dev --optimize-autoloader

# ===============================
#  Limpieza de cache Laravel
# ===============================
RUN php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear

# ===============================
#  Puerto y arranque
# ===============================
EXPOSE 8000

CMD ["sh","-c","php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
