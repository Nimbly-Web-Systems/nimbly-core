#!/bin/bash
set -e

echo "Starting Nimbly v1.1"

# Configure git credentials if GIT_TOKEN is set
if [ -n "$GIT_TOKEN" ]; then
    REPO_NAME=$(git -C /var/www/nimbly/ext remote get-url origin 2>/dev/null | sed 's/.*\///' | sed 's/\.git$//')
    REPO_NAME=${REPO_NAME:-nimbly}
    git config --global user.name "${REPO_NAME}"
    git config --global user.email "${REPO_NAME}@${APP_ENV:-dev}"
    git config --global --add safe.directory /var/www/nimbly/ext
    git config --global url."https://${GIT_TOKEN}@github.com/".insteadOf "https://github.com/"
    git config --global url."https://${GIT_TOKEN}@github.com/".insteadOf "git@github.com:"
fi

# Pull latest ext/ changes before starting (catches content added since image build)
if [ -d /var/www/nimbly/ext/.git ]; then
    git -C /var/www/nimbly/ext pull --rebase --autostash 2>/dev/null || true
fi

# Idempotent: generates .htaccess, creates missing directories and resources
php /var/www/nimbly/core/cli/nimbly.php site:setup
chown -R www-data:www-data /var/www/nimbly

mkdir -p /run/php
/usr/local/sbin/php-fpm -F &

apache2ctl -k start

# Run the Nimbly scheduler every minute
(while true; do
    sleep 60
    php /var/www/nimbly/core/cli/nimbly.php schedule:run 2>/dev/null
done) &

echo "Ready."
exec tail -f /var/log/apache2/error.log
