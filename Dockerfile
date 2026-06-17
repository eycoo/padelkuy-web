# PadelKuy on Railway (or any container host). PHP 8.2, served by PHP's built-in
# web server with the docroot set to public/ — so lib/ and config/ (DB
# credentials) live ABOVE the web root and are never served over HTTP.
# The built-in server is single-threaded; fine for this project's scale.
FROM php:8.2-cli

RUN docker-php-ext-install pdo_mysql

WORKDIR /var/www/html
COPY . .

# Railway/host injects $PORT; default 8080 locally. Docroot = public/.
EXPOSE 8080
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t public"]
