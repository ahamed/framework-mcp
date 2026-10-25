<?php
/**
 * Routes the MCP Adapter's diagnostics into the framework log.
 * The adapter's own default for custom servers discards them, which hides permission callback
 * failures and invalid results; this is the default error handler for framework servers.
 *
 * @package    FrameworkMcp
 * @subpackage Handlers
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\Handlers;

defined('ABSPATH') || exit;

use Framework\Supports\Facades\Log;
use WP\MCP\Infrastructure\ErrorHandling\Contracts\McpErrorHandlerInterface;

class LogErrorHandler implements McpErrorHandlerInterface
{
    /**
     * Log a message from the adapter.
     *
     * The framework log takes one string, so the context is appended as JSON.
     *
     * @param string $message The message.
     * @param array $context The context.
     * @param string $type The severity: error, warning, info, or debug.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function log(string $message, array $context = [], string $type = 'error'): void
    {
        $level = in_array($type, ['error', 'warning', 'info', 'debug'], true) ? $type : 'error';

        if (!empty($context)) {
            $message .= ' ' . wp_json_encode($context);
        }

        Log::$level('[mcp] ' . $message);
    }
}
