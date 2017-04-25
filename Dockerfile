FROM php:7-alpine

COPY . /app

WORKDIR /app

EXPOSE 80 443 12345

CMD [ "php", "/app/server.php" ]