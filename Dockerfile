FROM php:5.6-apache

# Install system deps for xsl and gettext extensions
RUN apt-get update && apt-get install -y \
        libxslt1-dev \
        gettext \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions
RUN docker-php-ext-install mysql mysqli xsl gettext

# Enable Apache mod_rewrite (needed for clean URLs)
RUN a2enmod rewrite

# Allow .htaccess overrides in the document root
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
