ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-cli

# ─── Apt sources ───────────────────────────────────────────────────────────────
# Several Debian releases used by old PHP images are EOL and have moved off the
# standard mirrors entirely (404 on deb.debian.org):
#   Jessie  (8)  → PHP 5.6        EOL 2018
#   Stretch (9)  → PHP 7.0 – 7.1  EOL 2022
#   Buster  (10) → PHP 7.2 – 7.4  EOL 2023
# For those codenames, redirect to archive.debian.org (main repo only — these
# versions receive no security updates anyway) and disable the validity-period
# check so apt-get update does not fail on an expired Release file.
RUN . /etc/os-release 2>/dev/null; \
    case "${VERSION_CODENAME:-}" in \
        jessie|stretch|buster) \
            printf 'deb http://archive.debian.org/debian %s main\n' "${VERSION_CODENAME}" \
                > /etc/apt/sources.list; \
            echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/10archive; \
            ;; \
    esac

# ─── System packages & PHP extensions ─────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        zip \
        libzip-dev \
        libxml2-dev \
        libicu-dev \
        libonig-dev \
    && docker-php-ext-install \
        zip \
        dom \
        intl \
        mbstring \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ─── Xdebug ────────────────────────────────────────────────────────────────────
# Xdebug releases are tightly coupled to PHP major versions:
#   PHP 5.x / 7.0 / 7.1 → xdebug 2.9.8  (last release for those branches)
#   PHP 7.2 / 7.3 / 7.4 → xdebug 3.1.6  (last 3.x release supporting PHP 7)
#   PHP 8.x              → xdebug latest (from PECL)
# Xdebug is installed but kept off by default; enable via XDEBUG_MODE=debug.
RUN PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;') \
    PHP_MINOR=$(php -r 'echo PHP_MINOR_VERSION;') \
    && if [ "$PHP_MAJOR" -lt 7 ] || { [ "$PHP_MAJOR" -eq 7 ] && [ "$PHP_MINOR" -lt 2 ]; }; then \
         pecl install xdebug-2.9.8; \
       elif [ "$PHP_MAJOR" -eq 7 ]; then \
         pecl install xdebug-3.1.6; \
       else \
         pecl install xdebug; \
       fi \
    && docker-php-ext-enable xdebug \
    && echo 'xdebug.mode=off' >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# ─── Composer 2.2 LTS ──────────────────────────────────────────────────────────
# The 2.2.x branch is the long-term-support release and works with PHP 5.3+
# through 8.x, making it usable across all supported PHP versions.
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
