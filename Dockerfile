FROM php:8.3-apache

# System deps for the PHP extensions: libxslt1-dev (xsl), libonig-dev (mbstring),
# gettext (i18n), and locales (see below). php:8.3-apache is Debian bookworm with
# live mirrors, so no archive.debian.org pin is needed (unlike the old 5.6 image).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libxslt1-dev \
        libonig-dev \
        gettext \
        locales \
    && rm -rf /var/lib/apt/lists/*

# gettext needs the OS locales generated; otherwise setlocale() fails and every
# translation silently falls back to the source string (i.e. localisation does
# nothing). Generate the locales lunaTools::format_language() maps to: fr_FR for
# French, en_US, and the non-standard en_EN ('en' -> 'en_EN'), the latter aliased
# to the en_US definition since glibc ships no en_EN source.
RUN localedef -i en_US -f UTF-8 en_US.UTF-8 \
    && localedef -i fr_FR -f UTF-8 fr_FR.UTF-8 \
    && { localedef -i en_US -f UTF-8 en_EN.UTF-8 || true; }

# PHP extensions. pdo_mysql is the DB driver (replaced PEAR MDB2 + ext/mysql);
# xsl drives the XSLT view layer; gettext is i18n; mbstring is required by the
# vendored semsol/arc2 3.x RDF library.
RUN docker-php-ext-install pdo_mysql xsl gettext mbstring

# Enable Apache mod_rewrite (needed for clean URLs)
RUN a2enmod rewrite

# Allow .htaccess overrides in the document root
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
