#!/bin/sh
# Selects the nginx config template based on SSL_ENABLED, then assembles the
# full certificate chain (SSL mode only) before handing off to the nginx entrypoint.
#
# SSL_ENABLED=true  (default): assemble fullchain.pem, use SSL config.
# SSL_ENABLED=false           : use HTTP-only config, skip cert handling.
#
# In both cases, the chosen template is copied into /etc/nginx/templates/ so
# the standard nginx docker-entrypoint.sh can process it with envsubst.

set -e

mkdir -p /etc/nginx/templates

SSL_ENABLED="${SSL_ENABLED:-true}"

if [ "$SSL_ENABLED" = "true" ]; then
    CERT=/etc/nginx/certs/cert.pem
    CA_BUNDLE=/etc/nginx/certs/ca-bundle.pem
    FULLCHAIN=/etc/nginx/certs/fullchain.pem

    if [ -f "$CERT" ] && [ -f "$CA_BUNDLE" ]; then
        cat "$CERT" "$CA_BUNDLE" > "$FULLCHAIN"
        echo "[ssl-entrypoint] fullchain assembled: $(wc -l < "$FULLCHAIN") lines"
    elif [ -f "$CERT" ]; then
        cp "$CERT" "$FULLCHAIN"
        echo "[ssl-entrypoint] no CA bundle found, using cert only"
    else
        echo "[ssl-entrypoint] ERROR: SSL_ENABLED=true but cert.pem not found at $CERT" >&2
        exit 1
    fi

    cp /etc/nginx/ssl.conf.template /etc/nginx/templates/default.conf.template
    echo "[ssl-entrypoint] SSL mode enabled"
else
    cp /etc/nginx/http.conf.template /etc/nginx/templates/default.conf.template
    echo "[ssl-entrypoint] HTTP-only mode (SSL_ENABLED=false)"
fi

exec /docker-entrypoint.sh "$@"
