#!/bin/bash
set -e

# Mount S3 imports bucket at storage/app/private/imports/ when configured.
# Skipped in local dev (AWS_IMPORTS_BUCKET not set) so local disk is used as-is.
if [ -n "$AWS_IMPORTS_BUCKET" ]; then
    # mount-s3 requires an empty directory; remove any content baked into the image
    rm -rf /var/www/storage/app/private/imports
    mkdir -p /var/www/storage/app/private/imports
    if ! mount-s3 "$AWS_IMPORTS_BUCKET" /var/www/storage/app/private/imports \
        --prefix imports/ \
        --allow-other \
        --allow-delete \
        --allow-overwrite \
        --uid 1000 --gid 1000 \
        --region "${AWS_DEFAULT_REGION:-us-west-2}" 2>&1; then
        echo "WARNING: mount-s3 failed — imports will use local disk" >&2
    fi
fi

# Clear any config cache baked into the image so env vars are always used
php /var/www/artisan config:clear 2>/dev/null || true

# Worker mode: if arguments are passed, exec them directly (skips sshd)
if [ $# -gt 0 ]; then
    exec "$@"
fi

# App mode: set up SSH and start sshd + php-fpm
ssh-keygen -A

# Run the Laravel scheduler (app container only). Backgrounded here so it inherits the
# container environment directly — no cron, no env-file, no PATH issues. schedule:work
# runs schedule:run every minute; the loop restarts it if it ever exits. Runs as www
# (with -p to keep the ECS/compose-injected env) so artisan writes files as the app user.
su -p www -c 'while true; do /usr/local/bin/php /var/www/artisan schedule:work >> /var/www/storage/logs/scheduler.log 2>&1; sleep 60; done' &

if [ -n "$SSH_AUTHORIZED_KEYS" ]; then
    printf '%s\n' "$SSH_AUTHORIZED_KEYS" > /home/www/.ssh/authorized_keys
    chmod 600 /home/www/.ssh/authorized_keys
    chown www:www /home/www/.ssh/authorized_keys
fi

/usr/sbin/sshd

exec php-fpm
