FROM phusion/baseimage:0.11

EXPOSE 80
EXPOSE 8080

WORKDIR /app

RUN add-apt-repository -y ppa:nginx/stable
RUN add-apt-repository -y ppa:ondrej/php
RUN add-apt-repository -y ppa:certbot/certbot

RUN apt-get update \
    && apt-get install -y \
        software-properties-common \
        nginx \
        python-certbot-nginx \
        certbot \
        php-pgsql \
        unzip \
        php7.4-fpm \
        php7.4-xml \
        php7.4-apcu \
        php7.4-curl \
        php7.4-zip \
        npm \
        git \
    && chown -R www-data:www-data /var/lib/nginx \
    && sed -e 's/\/run\/php\/php7.4-fpm.sock/9000/' -i /etc/php/7.4/fpm/pool.d/www.conf \
    && unlink /etc/nginx/sites-enabled/default \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && mkdir /run/php/

COPY --from=composer /usr/bin/composer /usr/bin/composer
    
RUN \
 sed -e 's/;\? \?memory_limit = [0-9a-zA-Z]*/memory_limit = 128M/' -i /etc/php/7.4/fpm/php.ini && \
 sed -e 's/;\? \?max_execution_time = [0-9]*/max_execution_time = 300/' -i /etc/php/7.4/fpm/php.ini && \
# sed -e 's/;opcache.preload=/opcache.preload=\/app\/var\/cache\/prod\/App_KernelProdContainer.preload.php/' -i /etc/php/7.4/fpm/php.ini && \
# sed -e 's/;opcache.preload_user=/opcache.preload_user=www-data/' -i /etc/php/7.4/fpm/php.ini && \
 sed -e 's/;opcache.memory_consumption=[0-9]*/opcache.memory_consumption=256/' -i /etc/php/7.4/fpm/php.ini && \
 sed -e 's/;opcache.max_accelerated_files=[0-9]*/opcache.max_accelerated_files=20000/' -i /etc/php/7.4/fpm/php.ini && \
 sed -e 's/;opcache.validate_timestamps=1/opcache.validate_timestamps=0/' -i /etc/php/7.4/fpm/php.ini && \
 sed -e 's/;realpath_cache_size = [0-9a-zA-Z]*/realpath_cache_size=4096K/' -i /etc/php/7.4/fpm/php.ini && \
 sed -e 's/;realpath_cache_ttl = [0-9]*/realpath_cache_ttl=600/' -i /etc/php/7.4/fpm/php.ini

ARG ENV

COPY docker/nginx /etc/nginx

RUN chown -R www-data:www-data /etc/nginx/sites-enabled/app.conf

RUN rm -rf /etc/service/service
ADD docker/runit/php-fpm.sh /etc/service/php-fpm/run
ADD docker/runit/nginx.sh /etc/service/nginx/run
RUN chmod ugo+x /etc/service/php-fpm/run
RUN chmod ugo+x /etc/service/nginx/run

COPY bin /app/bin/
COPY config /app/config/
COPY public /app/public/
COPY src /app/src/
COPY composer.lock /app
COPY composer.json /app
RUN mkdir /app/var
RUN chmod 777 /app/var
COPY .env /app/.env
COPY .env.${ENV}.local /app/.env.local

RUN composer install --no-dev
RUN composer dump-env prod
RUN composer dump-autoload --no-dev --classmap-authoritative
RUN php bin/console cache:warmup

COPY --from=ukraine-corona /usr/src/app/build /app/web

RUN chown -R www-data:www-data /app/web


CMD ["/sbin/my_init"]