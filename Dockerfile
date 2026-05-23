FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/uploads

EXPOSE 10000

CMD ["sh", "-c", "sed -i \"s/Listen .*/Listen ${PORT:-10000}/\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \\*:.*/<VirtualHost *:${PORT:-10000}>/\" /etc/apache2/sites-available/000-default.conf && echo 'Deployed PHP files:' && find /var/www/html -maxdepth 1 -type f -name '*.php' -print | sort && apache2-foreground"]
