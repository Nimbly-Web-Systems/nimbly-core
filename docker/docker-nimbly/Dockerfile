FROM ubuntu:26.04

RUN userdel -r ubuntu

# Install required software and modules
ENV DEBIAN_FRONTEND noninteractive
RUN apt-get -y update
RUN apt-get -y upgrade
RUN apt-get -y install software-properties-common ca-certificates lsb-release apt-transport-https
RUN LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php
RUN apt-get -y install apache2
RUN apt-get -y install php8.5
RUN apt-get -y install php8.5-mbstring php8.5-gd php8.5-gmp php8.5-curl
RUN apt-get -y install php8.5-fpm
RUN apt-get -y install gettext
RUN apt-get -y install nodejs npm

# Add scripts and configuration files
ADD apache2.conf /etc/apache2/apache2.conf
ADD nimbly.conf /etc/apache2/sites-available/nimbly.conf
ADD run.sh /run.sh
RUN chmod 755 /*.sh

# Configure PHP-FPM: fixed TCP socket, Nimbly PHP settings, switch to event MPM
ADD php-nimbly.ini /tmp/php-nimbly.ini
RUN PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;') && \
    ln -sf /usr/sbin/php-fpm${PHP_VER} /usr/local/sbin/php-fpm && \
    sed -i "s|listen = /run/php/php.*-fpm.sock|listen = 127.0.0.1:9000|" /etc/php/${PHP_VER}/fpm/pool.d/www.conf && \
    cp /tmp/php-nimbly.ini /etc/php/${PHP_VER}/fpm/conf.d/99-nimbly.ini && \
    a2dismod php${PHP_VER} mpm_prefork; a2enmod mpm_event proxy proxy_fcgi

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
CMD ["/run.sh"]
