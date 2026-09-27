FROM php:8.5-apache@sha256:70d80539dcacae817d9a1320518b95c86bb9568835ef3a7a024d57a4898c90e4

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
