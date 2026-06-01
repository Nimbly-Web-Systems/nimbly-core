#!/bin/bash
set -e

if [ -d /var/www/nimbly ]; then
    host_uid="$(stat -c '%u' /var/www/nimbly)"
    host_gid="$(stat -c '%g' /var/www/nimbly)"

    if [ "$host_gid" != "0" ] && [ "$(id -g www-data)" != "$host_gid" ]; then
        groupmod -o -g "$host_gid" www-data
    fi

    if [ "$host_uid" != "0" ] && [ "$(id -u www-data)" != "$host_uid" ]; then
        usermod -o -u "$host_uid" -g www-data www-data
    fi
fi

if [ "$1" = "php" ] && [ "$(id -u)" = "0" ] && [ "$(id -u www-data)" != "0" ]; then
    exec runuser -u www-data -- "$@"
fi

exec "$@"
