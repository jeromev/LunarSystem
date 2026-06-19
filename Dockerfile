FROM php:5.6-apache

# This image is based on the now-EOL Debian 9 "stretch", which has been moved
# off the regular mirrors to archive.debian.org, so the stock apt sources 404.
# Point apt at the archive and stop it rejecting the (long-expired) Release
# files; the matching signing keys have also expired, so installs run with
# --allow-unauthenticated below.
RUN set -eux; \
    echo 'deb http://archive.debian.org/debian stretch main' > /etc/apt/sources.list; \
    echo 'deb http://archive.debian.org/debian-security stretch/updates main' >> /etc/apt/sources.list; \
    echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until

# Install system deps for xsl and gettext extensions
RUN apt-get update && apt-get install -y --allow-unauthenticated \
        libxslt1-dev \
        gettext \
    && rm -rf /var/lib/apt/lists/*

# gettext needs the OS locales generated; otherwise setlocale() fails and every
# translation silently falls back to the source string (i.e. localisation does
# nothing). Generate the locales lunaTools::format_language() maps to: fr_FR for
# French, en_US, and the non-standard en_EN ('en' -> 'en_EN'), the latter aliased
# to the en_US definition since glibc ships no en_EN source.
RUN apt-get update && apt-get install -y --allow-unauthenticated locales \
    && rm -rf /var/lib/apt/lists/* \
    && localedef -i en_US -f UTF-8 en_US.UTF-8 \
    && localedef -i fr_FR -f UTF-8 fr_FR.UTF-8 \
    && { localedef -i en_US -f UTF-8 en_EN.UTF-8 || true; }

# Install required PHP extensions
RUN docker-php-ext-install mysql mysqli xsl gettext

# Enable Apache mod_rewrite (needed for clean URLs)
RUN a2enmod rewrite

# Allow .htaccess overrides in the document root
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
