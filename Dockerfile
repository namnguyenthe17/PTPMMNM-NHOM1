
FROM php:8.2-cli

RUN docker-php-ext-install pdo_mysql mysqli

WORKDIR /app

COPY ./PTPMMNM-NHOM1/ /app/

EXPOSE 10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t public public/router.php"]
