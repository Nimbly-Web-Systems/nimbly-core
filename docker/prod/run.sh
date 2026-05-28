#!/bin/bash
set -e

echo "Starting Nimbly v1.1"

if [ -d /var/www/nimbly/ext/.git ]; then
    REPO_NAME=$(git -C /var/www/nimbly/ext remote get-url origin 2>/dev/null | sed 's/.*\///' | sed 's/\.git$//')
    REPO_NAME=${REPO_NAME:-nimbly}

    git config --global --add safe.directory /var/www/nimbly/ext
    git config --global user.name "${REPO_NAME}"
    git config --global user.email "${REPO_NAME}@${APP_ENV:-dev}"

    # Embed token directly in the remote URL so git never prompts for credentials
    if [ -n "$GIT_TOKEN" ]; then
        REMOTE_URL=$(git -C /var/www/nimbly/ext remote get-url origin 2>/dev/null || echo "")
        AUTHED_URL=$(echo "$REMOTE_URL" | sed "s|git@github.com:|https://github.com/|" | sed "s|https://github.com/|https://${GIT_TOKEN}@github.com/|")
        git -C /var/www/nimbly/ext remote set-url origin "$AUTHED_URL"
    fi

    # Pull latest ext/ changes before starting
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
