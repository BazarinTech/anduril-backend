# =============================================================================
#  ANDURIL BACKEND -- CONTAINER IMAGE
# =============================================================================
#  Apache + mod_php, because the admin panel depends on .htaccess:
#  admin/.htaccess carries the rewrite that turns /admin/users into users.php,
#  plus php_value directives that only apply under mod_php. An nginx/php-fpm
#  stack ignores both, and every admin page 404s.
#
#  Build:  docker build -t anduril-backend .
#  Run:    docker run -e PORT=8080 -e DB_HOST=... -p 8080:8080 anduril-backend
#
#  Configuration comes from environment variables. config/env.php is excluded
#  by .dockerignore and never reaches the image -- see bootstrap/bootstrap.php
#  for the lookup order (real env var wins over the file).
# =============================================================================

# 8.3 rather than the ea-php81 cPanel was running. This is the version the code
# has actually been exercised against; both lint clean, so the choice is which
# one has evidence behind it.
FROM php:8.3-apache

# -----------------------------------------------------------------------------
# PHP extensions
# -----------------------------------------------------------------------------
# gd is rebuilt with WEBP support on purpose. The stock extension has none, and
# admin/products.php resizes uploads through imagecreatefromwebp()/imagewebp().
# Without it every product upload fails with "WEBP is not supported on this
# server" -- and only when someone tries to add a product, long after the
# deploy looked successful.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libwebp-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libfreetype6-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" gd pdo_mysql; \
    rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------------------------
# Apache
# -----------------------------------------------------------------------------
# rewrite  -- admin/.htaccess extensionless URLs
# headers  -- used by the CORS handling in bootstrap
RUN a2enmod rewrite headers

COPY docker/apache-anduril.conf /etc/apache2/conf-available/anduril.conf
RUN a2enconf anduril

COPY docker/php-anduril.ini /usr/local/etc/php/conf.d/zz-anduril.ini

# -----------------------------------------------------------------------------
# Application
# -----------------------------------------------------------------------------
# No `composer install`, deliberately.
#
# admin/vendor and backend/vendor are committed rather than ignored: both claim
# bazarin-php-library v1.1.0 but backend/vendor carries a hand-patched
# QueryBuilder (selectOR(), among others) that the published v1.1.0 does not.
# Running composer here would overwrite those patches and fatal every endpoint
# that calls them. See the note in .gitignore.
COPY . /var/www/html

# The web server only needs to read the tree. The one directory it may need to
# write is admin/uploads, and only when STORAGE_DRIVER is local -- with a bucket
# configured nothing is written to the container filesystem at all.
RUN set -eux; \
    mkdir -p /var/www/html/admin/uploads; \
    chown -R www-data:www-data /var/www/html/admin/uploads; \
    chmod -R 755 /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Documents intent only. The real port comes from $PORT at runtime, which is
# what Railway sets and routes to.
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
