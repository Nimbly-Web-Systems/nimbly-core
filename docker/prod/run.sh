#!/bin/bash
set -e

echo "Starting Nimbly v1.1.0"

if [ -d /var/www/nimbly/ext/.git ]; then
    REPO_NAME=$(git -C /var/www/nimbly/ext remote get-url origin 2>/dev/null | sed 's/.*\///' | sed 's/\.git$//')
    REPO_NAME=${REPO_NAME:-nimbly}

    git config --global --add safe.directory /var/www/nimbly/ext
    git config --global user.name "${REPO_NAME}"
    git config --global user.email "${REPO_NAME}@${APP_ENV:-dev}"

    REMOTE_URL=$(git -C /var/www/nimbly/ext remote get-url origin 2>/dev/null || echo "")
    PUBLIC_REMOTE_URL=$(echo "$REMOTE_URL" \
        | sed -E 's|git@github.com:|https://github.com/|' \
        | sed -E 's|https://[^/@]+(:[^/@]+)?@github.com/|https://github.com/|')

    if [ -n "$PUBLIC_REMOTE_URL" ] && [ "$PUBLIC_REMOTE_URL" != "$REMOTE_URL" ]; then
        git -C /var/www/nimbly/ext remote set-url origin "$PUBLIC_REMOTE_URL"
    fi

    # GitHub token used by scheduled ext:sync to push content/data changes.
    if [ -n "$GIT_TOKEN" ]; then
        git config --global credential.helper 'store --file=/root/.git-credentials'
        printf "https://x-access-token:%s@github.com\n" "$GIT_TOKEN" > /root/.git-credentials
        chmod 600 /root/.git-credentials
    elif [ "${APP_ENV:-dev}" = "prod" ] || [ "${APP_ENV:-dev}" = "stage" ]; then
        echo "Notice: GIT_TOKEN is not set; scheduled ext:sync cannot push live content/data changes."
        echo "This is OK for dev-to-live deployments where production content is not edited in place."
    fi

    # Pull latest ext/ changes before starting
    git -C /var/www/nimbly/ext pull --rebase --autostash 2>/dev/null || true
elif [ "${APP_ENV:-dev}" = "prod" ] || [ "${APP_ENV:-dev}" = "stage" ]; then
    echo "Notice: /var/www/nimbly/ext/.git is missing; scheduled ext:sync is disabled."
    echo "This is OK for immutable deployments where production content is not edited in place."
fi

# Idempotent: generates .htaccess, creates missing directories and resources
php /var/www/nimbly/core/cli/nimbly.php system:setup
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
