#!/bin/bash

echo Starting Nimbly Development Server
a2dissite 000-default > /dev/null
a2ensite nimbly > /dev/null
a2enmod rewrite expires headers > /dev/null

# Behind a load balancer (TRUSTED_PROXIES in .env): Apache's own log shows the visitor too.
php /var/www/nimbly/core/cli/nimbly.php host:proxy-conf > /etc/apache2/conf-available/nimbly-proxy.conf || true
if [ -s /etc/apache2/conf-available/nimbly-proxy.conf ]; then
    a2enmod -q remoteip && a2enconf -q nimbly-proxy
else
    a2disconf -q nimbly-proxy 2>/dev/null || true
fi

mkdir -p /run/php
/usr/local/sbin/php-fpm -F &

apache2ctl -k start

# Chat replies need the agent chat worker; start it every minute (it exits when already running).
# Only this task: the full scheduler (jobs, e-mail, sync) stays a production concern.
(while true; do
    runuser -u www-data -- php /var/www/nimbly/core/cli/nimbly.php agent:chat
    sleep 60
done) &

echo All done!
tail -f /var/log/apache2/error.log
