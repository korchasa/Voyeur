FROM composer/composer:alpine

WORKDIR /app

COPY . .

RUN composer install

EXPOSE 80 443 12345

CMD [ "php", "/app/server.php" ]