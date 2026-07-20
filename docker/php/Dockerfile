FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    redis-tools \
    net-tools \
    procps \
    openssh-server \
    default-mysql-client \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Disable SSL requirement for MySQL client
RUN printf '[client]\nssl=0\n' > /etc/mysql/conf.d/client.cnf

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Create system user and unlock account for SSH public key auth
RUN groupadd -g 1000 www && useradd -u 1000 -ms /bin/bash -g www www && passwd -d www

# Run PHP-FPM as www instead of www-data so it can write to app files
# Also ensure ECS-injected env vars are passed through to PHP workers
RUN sed -i 's/user = www-data/user = www/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/group = www-data/group = www/g' /usr/local/etc/php-fpm.d/www.conf \
    && echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

# Install mountpoint-s3 for S3 FUSE mount and enable allow-other
# php:8.4-fpm is Debian Bookworm; libfuse2 was renamed to libfuse2t64 there.
# Detect arch so the same Dockerfile works on Apple Silicon (arm64) and ECS (x86_64).
RUN DPKG_ARCH=$(dpkg --print-architecture) && \
    S3_ARCH=$([ "$DPKG_ARCH" = "amd64" ] && echo "x86_64" || echo "$DPKG_ARCH") && \
    apt-get update -qq && \
    curl -fsSL -o /tmp/mount-s3.deb \
        "https://s3.amazonaws.com/mountpoint-s3-release/latest/${S3_ARCH}/mount-s3.deb" && \
    apt-get install -y --no-install-recommends /tmp/mount-s3.deb && \
    rm -f /tmp/mount-s3.deb && \
    echo "user_allow_other" >> /etc/fuse.conf && \
    rm -rf /var/lib/apt/lists/*

# Set up sshd
RUN mkdir -p /var/run/sshd \
    && mkdir -p /home/www/.ssh \
    && chown www:www /home/www/.ssh \
    && chmod 700 /home/www/.ssh

COPY docker/php/sshd_config /etc/ssh/sshd_config
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# Copy application
COPY --chown=www:www . /var/www

COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000 2222

CMD ["/entrypoint.sh"]
