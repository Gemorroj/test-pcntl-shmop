FROM php:8.0-fpm-alpine

RUN set -xe \
    && apk update \
    && apk upgrade \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \

    # shmop
    && docker-php-ext-install shmop \
    && docker-php-ext-enable shmop \

    # pcntl
    && docker-php-ext-install pcntl \
    && docker-php-ext-enable pcntl \

    # Cleanup
    && apk del --no-cache .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

# Composer
RUN set -xe \
    && curl -L -o /composer.phar https://github.com/composer/composer/releases/download/2.1.12/composer.phar \
    && chmod 755 /composer.phar

# utilites
RUN set -xe \
    && apk --no-cache add htop unzip


WORKDIR /var/www/app
