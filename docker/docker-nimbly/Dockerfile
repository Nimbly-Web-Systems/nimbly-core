FROM ubuntu:latest 

RUN userdel -r ubuntu

# Install required software and modules
ENV DEBIAN_FRONTEND noninteractive
RUN apt-get -y update 
RUN apt-get -y upgrade
RUN apt-get -y install software-properties-common ca-certificates lsb-release apt-transport-https 
RUN LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php
RUN apt-get -y install apache2
RUN apt-get -y install php 
RUN apt-get -y install php-mbstring php-gd php-gmp php-curl 
RUN apt-get -y install libapache2-mod-php
RUN apt-get -y install gettext

# Add scripts and configuration files
ADD apache2.conf /etc/apache2/apache2.conf
ADD nimbly.conf /etc/apache2/sites-available/nimbly.conf
ADD run.sh /run.sh
RUN chmod 755 /*.sh

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

