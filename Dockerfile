FROM php:7.4-fpm-alpine

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


# utilites
RUN set -xe \
    && apk --no-cache add htop unzip


WORKDIR /var/www/app
