#!/bin/sh
set -e

if [ -d /var/www/html ]; then
    chown -R www-data:www-data /var/www/html/wp-admin /var/www/html/wp-includes /var/www/html/wp-content 2>/dev/null || true
    find /var/www/html -maxdepth 1 -name '*.php' ! -name 'wp-config.php' -exec chown www-data:www-data {} + 2>/dev/null || true
fi

cat > /usr/local/etc/php-fpm.d/zz-docker.conf <<'EOF'
clear_env = no
listen = 9000
EOF

XDEBUG_MODE="${XDEBUG_MODE:-off}"
XDEBUG_CLIENT_HOST="${XDEBUG_CLIENT_HOST:-host.docker.internal}"
XDEBUG_CLIENT_PORT="${XDEBUG_CLIENT_PORT:-9003}"
XDEBUG_IDEKEY="${XDEBUG_IDEKEY:-PHPSTORM}"

if [ "$XDEBUG_MODE" = "off" ]; then
    cat > /usr/local/etc/php/conf.d/zz-xdebug.ini <<'EOF'
; Xdebug disabled (set XDEBUG_MODE=debug in .env and recreate php)
EOF
    cat > /usr/local/etc/php/conf.d/zz-dev.ini <<'EOF'
; Production-like defaults when Xdebug is off
EOF
else
    docker-php-ext-enable xdebug 2>/dev/null || true

    cat > /usr/local/etc/php/conf.d/zz-xdebug.ini <<EOF
xdebug.mode=${XDEBUG_MODE}
xdebug.start_with_request=yes
xdebug.client_host=${XDEBUG_CLIENT_HOST}
xdebug.client_port=${XDEBUG_CLIENT_PORT}
xdebug.idekey=${XDEBUG_IDEKEY}
xdebug.log=/tmp/xdebug.log
xdebug.log_level=7
xdebug.connect_timeout_ms=500
EOF

    cat > /usr/local/etc/php/conf.d/zz-dev.ini <<'EOF'
opcache.enable=0
opcache.enable_cli=0
EOF
fi

exec docker-php-entrypoint "$@"
