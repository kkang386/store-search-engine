#!/bin/sh
set -e

mkdir -p /etc/nginx/certs

printf '%s\n' "$SSL_CERT"        > /etc/nginx/certs/cert.pem
printf '%s\n' "$SSL_KEY"         > /etc/nginx/certs/key.pem
printf '%s\n' "$SSL_CA_BUNDLE_1" > /etc/nginx/certs/ca-bundle.pem
printf '%s\n' "$SSL_CA_BUNDLE_2" >> /etc/nginx/certs/ca-bundle.pem

chmod 600 /etc/nginx/certs/key.pem

envsubst '${APP_DOMAIN}' < /etc/nginx/conf.d/default.conf > /tmp/default.conf
mv /tmp/default.conf /etc/nginx/conf.d/default.conf

exec nginx -g "daemon off;"
