FROM php:8.2-apache

# curl is enabled by default in the official PHP image; make sure mbstring is present too
RUN if ! php -m | grep -q '^mbstring$'; then \
        apt-get update && apt-get install -y --no-install-recommends libonig-dev && \
        docker-php-ext-install mbstring && \
        rm -rf /var/lib/apt/lists/* ; \
    fi

COPY . /var/www/html/

EXPOSE 80
