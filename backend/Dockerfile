FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    default-mysql-client \
    dos2unix \
    cron \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd sockets zip opcache
RUN pecl install redis && docker-php-ext-enable redis

# ==================== PHP PERFORMANCE CONFIG ====================
RUN { \
    echo "upload_max_filesize = 50M"; \
    echo "post_max_size = 50M"; \
    echo "upload_tmp_dir = /var/www/storage/tmp"; \
    echo "memory_limit = 256M"; \
    echo "max_execution_time = 60"; \
    echo ""; \
    echo "; OPcache"; \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=128"; \
    echo "opcache.interned_strings_buffer=16"; \
    echo "opcache.max_accelerated_files=10000"; \
    echo "opcache.validate_timestamps=1"; \
    echo "opcache.revalidate_freq=2"; \
    echo "opcache.save_comments=1"; \
    echo "opcache.enable_file_override=1"; \
    echo ""; \
    echo "; Realpath cache"; \
    echo "realpath_cache_size=4096K"; \
    echo "realpath_cache_ttl=600"; \
} > /usr/local/etc/php/conf.d/custom.ini

# ==================== PHP-FPM TUNING ====================
RUN { \
    echo "[www]"; \
    echo "listen = 0.0.0.0:9000"; \
    echo "pm = dynamic"; \
    echo "pm.max_children = 50"; \
    echo "pm.start_servers = 8"; \
    echo "pm.min_spare_servers = 4"; \
    echo "pm.max_spare_servers = 16"; \
    echo "pm.max_requests = 1000"; \
    echo "pm.process_idle_timeout = 10s"; \
    echo "request_terminate_timeout = 120"; \
    echo "clear_env = no"; \
} > /usr/local/etc/php-fpm.d/zz-tuning.conf

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www

# Install PHP dependencies at build time so runtime boot does not depend on
# a fresh networked composer install for an empty vendor volume.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts
RUN mkdir -p /opt/vendor && cp -a /var/www/vendor/. /opt/vendor/

# Copy source code
COPY . .

# ==================================================================
# CRITICAL: Copy entrypoint to /usr/local/bin/ (OUTSIDE /var/www)
# so that volume mount (./backend:/var/www) does NOT override it.
# Also fix Windows line endings with dos2unix.
# ==================================================================
RUN cp /var/www/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh \
    && dos2unix /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

# Set permissions
RUN chown -R www-data:www-data /var/www

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
