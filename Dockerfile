FROM php:8.5-apache@sha256:eacc0d98992683cb46e4f8f44b2418a0323855dc8b59d32dc54f7a9b90a966dd

# Configure document root: use app entrypoint as directory index, disable directory listing
RUN printf '<Directory /var/www/html>\n    DirectoryIndex day-of-year-calendar.php\n    Options -Indexes\n</Directory>\n' \
    > /etc/apache2/conf-available/app.conf \
    && a2enconf app

WORKDIR /var/www/html

# Copy application source
COPY --chown=www-data:www-data day-of-year-calendar.php ./
COPY --chown=www-data:www-data src/ ./src/
COPY --chown=www-data:www-data assets/ ./assets/

# Entrypoint: wires Apache to the $PORT env var injected by Cloud Run (default 8080)
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# AUTH_TOKEN and other settings can be injected as environment variables
# (Cloud Run env vars or Secret Manager). config/config.php still works via volume mount.
ENV PORT=8080
EXPOSE 8080

CMD ["/usr/local/bin/docker-entrypoint.sh"]
