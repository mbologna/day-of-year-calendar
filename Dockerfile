FROM php:8.2-apache@sha256:2e7c3662a44ccd94d53bdf4b0d21ba12caef2dda89c7ddc55d9709155f368647

# Configure document root: use app entrypoint as directory index, disable directory listing
RUN printf '<Directory /var/www/html>\n    DirectoryIndex day-of-year-calendar.php\n    Options -Indexes\n</Directory>\n' \
    > /etc/apache2/conf-available/app.conf \
    && a2enconf app

WORKDIR /var/www/html

# Copy application source
COPY --chown=www-data:www-data day-of-year-calendar.php ./

# config/config.php is injected at runtime via volume mount
