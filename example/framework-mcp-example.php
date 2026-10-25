<?php
/**
 * Framework MCP Example
 *
 * @package     framework-mcp-example
 * Plugin Name: Framework MCP Example
 * Plugin URI: https://github.com/ahamed/framework-mcp
 * Description: Playground plugin that exposes an MCP server through ahamed/framework-mcp and themeum/framework.
 * Version: 0.1.0-dev
 * Author: Sajeeb Ahamed
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Requires Plugins: mcp-adapter
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FRAMEWORK_MCP_EXAMPLE_PATH', plugin_dir_path(__FILE__));

require_once __DIR__ . '/vendor/autoload.php';

add_action('init', function () {
    require_once FRAMEWORK_MCP_EXAMPLE_PATH . 'bootstrap/app.php';
}, 0);
