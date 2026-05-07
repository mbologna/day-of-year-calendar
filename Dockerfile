FROM php:8.5-apache@sha256:e55e196fca35436fd26ba36e6fd2f2d9846142d0561dc35f74c7d25db1d54318

# Configure document root: use app entrypoint as directory index, disable directory listing
RUN printf '<Directory /var/www/html>\n    DirectoryIndex day-of-year-calendar.php\n    Options -Indexes\n</Directory>\n' \
    > /etc/apache2/conf-available/app.conf \
    && a2enconf app

WORKDIR /var/www/html

# Copy application source
COPY --chown=www-data:www-data day-of-year-calendar.php ./

# config/config.php is injected at runtime via volume mount
