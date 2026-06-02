FROM php:8.2-apache

# System dependencies
RUN apt-get update && apt-get install -y \
    gnupg2 \
    curl \
    unzip \
    zip \
    git \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    unixodbc-dev \
    autoconf \
    gcc \
    g++ \
    make

# Microsoft repository
RUN curl -sSL https://packages.microsoft.com/keys/microsoft.asc \
    | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg

RUN echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" \
    > /etc/apt/sources.list.d/mssql-release.list

RUN apt-get update

# ODBC Driver 18
RUN ACCEPT_EULA=Y apt-get install -y \
    msodbcsql18 \
    unixodbc

# PHP Extensions
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg

RUN docker-php-ext-install \
    pdo \
    zip \
    intl \
    gd \
    bcmath

# SQL Server Extensions
RUN pecl install sqlsrv-5.11.1 pdo_sqlsrv-5.11.1 \
    && echo "extension=sqlsrv.so" > /usr/local/etc/php/conf.d/30-sqlsrv.ini \
    && echo "extension=pdo_sqlsrv.so" > /usr/local/etc/php/conf.d/30-pdo_sqlsrv.ini

# Redis Extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# PHP config
RUN { \
    echo 'upload_max_filesize=64M'; \
    echo 'post_max_size=64M'; \
    echo 'memory_limit=256M'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

# Apache modules
RUN a2enmod rewrite headers expires

# Set Laravel public/ as document root
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Composer 2.9.x
COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

CMD ["apache2-foreground"]
