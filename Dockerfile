# PadelKuy on Railway (or any container host). PHP 8.2 + Apache.
# Docroot is public/ so lib/ and config/ (DB credentials) sit ABOVE the web
# root and are never served over HTTP.
FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql

# Point Apache at public/ instead of /var/www/html.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/*.conf \
      /etc/apache2/apache2.conf \
      /etc/apache2/conf-available/*.conf

COPY . /var/www/html/

# Railway/host injects $PORT; Apache must listen on it (default 80 locally).
EXPOSE 80
CMD ["sh", "-c", "\
  sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && \
  sed -i \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf && \
  apache2-foreground"]
