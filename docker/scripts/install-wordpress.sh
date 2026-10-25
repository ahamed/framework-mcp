#!/usr/bin/env bash
set -euo pipefail

WP_PATH="/var/www/html"
PLUGIN_SLUG="framework-mcp-example"
MCP_ADAPTER_ZIP="https://github.com/WordPress/mcp-adapter/releases/download/${MCP_ADAPTER_VERSION}/mcp-adapter.zip"

echo "Ensuring WordPress core is present..."
if [ ! -f "${WP_PATH}/wp-includes/version.php" ]; then
    echo "Downloading WordPress..."
    wp core download --path="${WP_PATH}" --allow-root
fi

echo "Waiting for database..."
until wp db check --path="${WP_PATH}" --allow-root 2>/dev/null; do
    echo "Database not ready, retrying in 2s..."
    sleep 2
done

if wp core is-installed --path="${WP_PATH}" --allow-root 2>/dev/null; then
    echo "WordPress is already installed."
else
    echo "Installing WordPress..."
    wp core install \
        --path="${WP_PATH}" \
        --url="${WP_URL}" \
        --title="${WP_TITLE}" \
        --admin_user="${WP_ADMIN_USER}" \
        --admin_password="${WP_ADMIN_PASSWORD}" \
        --admin_email="${WP_ADMIN_EMAIL}" \
        --skip-email \
        --allow-root
fi

# The Abilities API the MCP Adapter builds on ships with WordPress 6.9.
wp core update --path="${WP_PATH}" --allow-root

if wp plugin is-installed mcp-adapter --path="${WP_PATH}" --allow-root 2>/dev/null; then
    echo "MCP Adapter is already installed."
else
    echo "Installing MCP Adapter ${MCP_ADAPTER_VERSION}..."
    wp plugin install "${MCP_ADAPTER_ZIP}" --path="${WP_PATH}" --allow-root
fi

wp plugin activate mcp-adapter --path="${WP_PATH}" --allow-root

if [ ! -f "${WP_PATH}/wp-content/plugins/${PLUGIN_SLUG}/vendor/autoload.php" ]; then
    echo "The example plugin has no vendor/ yet: run make example-install, then make init again."
    exit 1
fi

wp plugin activate "${PLUGIN_SLUG}" --path="${WP_PATH}" --allow-root

wp rewrite structure '/%postname%/' --path="${WP_PATH}" --allow-root 2>/dev/null || true
wp rewrite flush --path="${WP_PATH}" --allow-root

if wp user application-password exists "${WP_ADMIN_USER}" mcp-client --path="${WP_PATH}" --allow-root 2>/dev/null; then
    echo "Application password 'mcp-client' already exists; delete it in the admin to issue a new one."
else
    echo "Application password for ${WP_ADMIN_USER} (shown once, put it in your MCP client config):"
    wp user application-password create "${WP_ADMIN_USER}" mcp-client --porcelain --path="${WP_PATH}" --allow-root
fi

chown -R www-data:www-data "${WP_PATH}/wp-admin" "${WP_PATH}/wp-includes" "${WP_PATH}/wp-content" 2>/dev/null || true
find "${WP_PATH}" -maxdepth 1 -name '*.php' ! -name 'wp-config.php' -exec chown www-data:www-data {} + 2>/dev/null || true

echo "WordPress setup complete: ${WP_URL}"
