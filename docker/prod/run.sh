#!/bin/bash
set -e

echo "Starting Nimbly v1.1"

# Idempotent: generates .htaccess, creates missing directories and resources
php /var/www/nimbly/core/cli/nimbly.php site:setup

mkdir -p /run/php
/usr/local/sbin/php-fpm -F &

apache2ctl -k start

echo "Ready."
exec tail -f /var/log/apache2/error.log
