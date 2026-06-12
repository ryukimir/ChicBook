FROM php:8.2-apache

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

RUN apt-get update && apt-get install -y \
    libpq-dev \
    msmtp \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_pgsql

RUN echo "upload_max_filesize = 50M\npost_max_size = 55M\nmax_execution_time = 60\nmemory_limit = 256M" > /usr/local/etc/php/conf.d/uploads.ini

RUN a2enmod rewrite

RUN echo 'sendmail_path = "/usr/bin/msmtp -t --host=mailpit --port=1025 -f contact@chicbook.com"' > /usr/local/etc/php/conf.d/mail.ini

WORKDIR /var/www/html

EXPOSE 80