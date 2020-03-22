FROM phusion/baseimage:0.11

EXPOSE 80
EXPOSE 8080

RUN add-apt-repository -y ppa:nginx/stable
RUN add-apt-repository -y ppa:ondrej/php

RUN apt-get update \
    && apt-get install -y \
        nginx \
        php-pgsql \
        php7.4-fpm \
        php7.4-xml \
        npm \
        git \
    && chown -R www-data:www-data /var/lib/nginx \
    && sed -e 's/\/run\/php\/php7.4-fpm.sock/9000/' -i /etc/php/7.4/fpm/pool.d/www.conf \
    && unlink /etc/nginx/sites-enabled/default \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && mkdir /run/php/


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
COPY vendor /app/vendor/
RUN mkdir /app/var
RUN chmod 777 /app/var
COPY .env.local.php /app/.env.local.php

COPY --from=ukraine-corona /usr/src/app/build /app/web


CMD ["/sbin/my_init"]