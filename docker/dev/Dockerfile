FROM ubuntu:latest

RUN userdel -r ubuntu || true

# Install required software and modules
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get -y update && \
    apt-get -y install \
        apache2 \
        nodejs \
        npm \
        php-cli \
        php-curl \
        php-fpm \
        php-gd \
        php-gmp \
        php-intl \
        php-mbstring && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Add scripts and configuration files
ADD docker/apache2.conf /etc/apache2/apache2.conf
ADD docker/nimbly.conf /etc/apache2/sites-available/nimbly.conf
ADD docker/dev/entrypoint.sh /entrypoint.sh
ADD docker/dev/run.sh /run.sh
RUN chmod 755 /*.sh

# Configure PHP-FPM: fixed TCP socket, Nimbly PHP settings, switch to event MPM
ADD docker/php-nimbly.ini /tmp/php-nimbly.ini
RUN PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;') && \
    ln -sf /usr/sbin/php-fpm${PHP_VER} /usr/local/sbin/php-fpm && \
    sed -i "s|listen = /run/php/php.*-fpm.sock|listen = 127.0.0.1:9000|" /etc/php/${PHP_VER}/fpm/pool.d/www.conf && \
    cp /tmp/php-nimbly.ini /etc/php/${PHP_VER}/fpm/conf.d/99-nimbly.ini && \
    if [ -e /etc/apache2/mods-available/php${PHP_VER}.load ]; then a2dismod php${PHP_VER}; fi && \
    a2dismod mpm_prefork || true && \
    a2enmod mpm_event proxy proxy_fcgi

# Set required permissions
RUN usermod -u 1000 www-data
RUN groupmod -g 1000 www-data
RUN mkdir /var/www/nimbly
RUN mkdir /var/www/nimbly/ext
RUN mkdir /var/www/nimbly/ext/data
RUN chown -R www-data:www-data /var/www/nimbly/ext/

# Add docker ports and volumes
EXPOSE 80
VOLUME ["/var/www/nimbly"]

# Execute the run.sh
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/run.sh"]
