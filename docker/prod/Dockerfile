FROM ubuntu:24.04
ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get -y update && \
    apt-get -y install \
        apache2 \
        git \
        php-cli \
        php-curl \
        php-fpm \
        php-gd \
        php-gmp \
        php-mbstring && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

COPY docker/apache2.conf /etc/apache2/apache2.conf
COPY docker/nimbly.conf  /etc/apache2/sites-available/nimbly.conf
COPY docker/php-nimbly.ini /tmp/php-nimbly.ini

RUN PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;') && \
    ln -sf /usr/sbin/php-fpm${PHP_VER} /usr/local/sbin/php-fpm && \
    sed -i "s|listen = /run/php/php.*-fpm.sock|listen = 127.0.0.1:9000|" /etc/php/${PHP_VER}/fpm/pool.d/www.conf && \
    cp /tmp/php-nimbly.ini /etc/php/${PHP_VER}/fpm/conf.d/99-nimbly.ini && \
    a2dismod mpm_prefork || true && \
    a2enmod mpm_event proxy proxy_fcgi rewrite expires headers && \
    a2dissite 000-default && a2ensite nimbly

WORKDIR /var/www/nimbly
COPY core/    ./core/
COPY index.php ./

RUN mkdir -p ext/data && chown -R www-data:www-data /var/www/nimbly

EXPOSE 80

COPY docker/prod/run.sh /run.sh
RUN chmod 755 /run.sh
CMD ["/run.sh"]
