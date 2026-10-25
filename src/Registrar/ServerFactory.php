<?php
/**
 * Turns a framework server into one McpAdapter::create_server() call.
 * The adapter takes thirteen positional arguments; keeping their order in one place is what
 * lets the Server class expose them as named properties.
 *
 * @package    FrameworkMcp
 * @subpackage Registrar
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\Registrar;

defined('ABSPATH') || exit;

use Framework\Http\Request as HttpRequest;
use Ahamed\FrameworkMcp\Server;
use LogicException;
use WP_Error;
use WP_REST_Request;

class ServerFactory
{
    /**
     * Runs the server's permission() with dependency injection.
     *
     * @var AbilityInvoker
     *
     * @since 1.0.0
     */
    protected $invoker;

    /**
     * Create the factory.
     *
     * @param AbilityInvoker $invoker Runs the server's permission().
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function __construct(AbilityInvoker $invoker)
    {
        $this->invoker = $invoker;
    }

    /**
     * Create the server through the adapter.
     *
     * @param object $adapter The adapter instance mcp_adapter_init passed.
     * @param Server $server The server.
     * @param array $tools The tool ability names.
     * @param array $resources The resource ability names.
     * @param array $prompts The prompt ability names.
     *
     * @return mixed The adapter on success, or a WP_Error.
     *
     * @throws LogicException When the server has no REST namespace.
     *
     * @since 1.0.0
     */
    public function create($adapter, Server $server, array $tools, array $resources, array $prompts)
    {
        if ($server->route_namespace() === '') {
            throw new LogicException(sprintf(
                'MCP server [%s] has no REST namespace: declare $route_namespace '
                . 'or set the application REST namespace.',
                $server->id()
            ));
        }

        return $adapter->create_server(
            $server->id(),
            $server->route_namespace(),
            $server->route(),
            $server->name(),
            $server->description(),
            $server->version(),
            $server->transports(),
            $server->error_handler(),
            $server->observability_handler(),
            $tools,
            $resources,
            $prompts,
            $this->permission_callback($server)
        );
    }

    /**
     * Build the transport permission callback, or null to keep the adapter's default gate.
     *
     * @param Server $server The server.
     *
     * @return callable|null
     *
     * @since 1.0.0
     */
    protected function permission_callback(Server $server)
    {
        if (!method_exists($server, 'permission')) {
            return null;
        }

        return function (WP_REST_Request $request) use ($server) {
            $objects = [HttpRequest::from_wp_rest_request($request), $request];
            $result = $this->invoker->call($server, 'permission', $objects);

            return $result instanceof WP_Error ? $result : (bool) $result;
        };
    }
}
