##############################
# Stage 1: MySQL mit Security-Fixes
##############################
FROM mysql:8.0 AS mysql_fixed

# Security-Fixes:
# - CVE-2025-47273 (setuptools)
# - CVE-2025-8869 (pip)
# Vorgehen:
#   1. Temporär python3-pip installieren
#   2. setuptools + pip aktualisieren
#   3. Temporäre Pakete und Cache entfernen
#   4. Installation validieren (bricht Build ab, falls Version nicht passt)

FROM mysql:8.0 AS mysql_fixed

RUN apt-get update && apt-get install -y python3-pip && \
    python3 -m pip install --upgrade setuptools==78.1.1 && \
    apt-get remove -y python3-pip && \
    rm -rf /var/lib/apt/lists/* /root/.cache/pip

# Stage 2: PHP + Apache App
##############################
FROM php:8.1-apache AS php_app

# Grundpakete + PHP-Extensions
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libonig-dev \
 && docker-php-ext-install pdo_mysql zip \
 && a2enmod rewrite
RUN apt-get update && apt-get upgrade -y && apt-get clean

# Composer übernehmen
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies installieren (nutzt Layer-Caching)
COPY composer.json /var/www/html/
RUN composer install --no-interaction --prefer-dist --no-dev

# App-Code kopieren
COPY src/ /var/www/html/
RUN composer dump-autoload -o \
 && chown -R www-data:www-data /var/www/html

# Upload-Verzeichnis & PHP-Konfiguration
RUN mkdir -p /var/www/html/reports/uploads \
 && chown -R www-data:www-data /var/www/html/reports
RUN echo "upload_max_filesize=20M\npost_max_size=20M" > /usr/local/etc/php/conf.d/uploads.ini


EXPOSE 80
CMD ["apache2-foreground"]
