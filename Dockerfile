FROM php:8.2-fpm-bullseye

# ===============================
# 1️⃣ Dependencias del sistema
# ===============================
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    gnupg \
    ca-certificates \
    unixodbc \
    unixodbc-dev \
    libonig-dev \
    libzip-dev \
    zip \
    && docker-php-ext-install mbstring zip pdo \
    && apt-get clean

# ===============================
# 2️⃣ Microsoft ODBC Driver (Bullseye)
# ===============================
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
    | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && curl -fsSL https://packages.microsoft.com/config/debian/11/prod.list \
    | sed 's|deb |deb [signed-by=/usr/share/keyrings/microsoft-prod.gpg] |g' \
    > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18

# ===============================
# 3️⃣ PHP SQL Server extensions
# ===============================
RUN pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# ===============================
# 4️⃣ Composer
# ===============================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ===============================
# 5️⃣ Laravel App
# ===============================
WORKDIR /var/www
COPY . .

RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 storage

# ===============================
# 6️⃣ PHP deps
# ===============================
RUN composer install --no-dev --optimize-autoloader

# ===============================
# 7️⃣ Laravel cache
# ===============================
RUN php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear

EXPOSE 8000

CMD ["sh","-c","php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
