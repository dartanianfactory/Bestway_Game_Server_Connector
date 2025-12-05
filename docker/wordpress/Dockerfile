FROM wordpress:6.7-php8.3-fpm-alpine

RUN apk update && apk add --no-cache \
    php83-pdo_mysql \
    mysql-client \
    && rm -rf /var/cache/apk/*

COPY entrypoint.sh /usr/local/bin/custom-entrypoint.sh
RUN chmod +x /usr/local/bin/custom-entrypoint.sh

ENTRYPOINT ["/bin/sh", "/usr/local/bin/custom-entrypoint.sh"]
CMD ["php-fpm"]