FROM php:8.2-apache

ENV APP_ENV=production

RUN docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /var/www/html

COPY . /var/www/html/
COPY render/start.sh /usr/local/bin/render-start

RUN chmod +x /usr/local/bin/render-start \
    && mkdir -p /var/www/html/uploads/contributions \
        /var/www/html/uploads/final_reports \
        /var/www/html/uploads/progress \
        /var/www/html/uploads/video_checks \
    && chown -R www-data:www-data /var/www/html/uploads

EXPOSE 10000

CMD ["render-start"]
