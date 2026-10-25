<?php

/**
 * Stand-ins for the MCP Adapter plugin classes the MCP subsystem touches. The adapter is a
 * runtime plugin, not a Composer dependency, so the real classes never exist in this process.
 */

namespace WP\MCP\Core {

    if (!class_exists(McpServer::class)) {
        class McpServer
        {
            public string $id;

            public array $arguments;

            public function __construct(string $id, array $arguments)
            {
                $this->id = $id;
                $this->arguments = $arguments;
            }

            public function get_server_id(): string
            {
                return $this->id;
            }
        }
    }

    if (!class_exists(McpAdapter::class)) {
        class McpAdapter
        {
            /** @var array<string, McpServer> */
            public array $servers = [];

            /** @var array<string, \WP_Error> Server ids whose creation should fail. */
            public array $failures = [];

            public function create_server(
                string $server_id,
                string $server_route_namespace,
                string $server_route,
                string $server_name,
                string $server_description,
                string $server_version,
                array $mcp_transports,
                ?string $error_handler,
                ?string $observability_handler = null,
                array $tools = [],
                array $resources = [],
                array $prompts = [],
                ?callable $transport_permission_callback = null
            ) {
                if (isset($this->failures[$server_id])) {
                    return $this->failures[$server_id];
                }

                $this->servers[$server_id] = new McpServer($server_id, [
                    'server_id' => $server_id,
                    'server_route_namespace' => $server_route_namespace,
                    'server_route' => $server_route,
                    'server_name' => $server_name,
                    'server_description' => $server_description,
                    'server_version' => $server_version,
                    'mcp_transports' => $mcp_transports,
                    'error_handler' => $error_handler,
                    'observability_handler' => $observability_handler,
                    'tools' => $tools,
                    'resources' => $resources,
                    'prompts' => $prompts,
                    'transport_permission_callback' => $transport_permission_callback,
                ]);

                return $this;
            }

            public function get_server(string $server_id): ?McpServer
            {
                return $this->servers[$server_id] ?? null;
            }
        }
    }
}

namespace WP\MCP\Transport {

    if (!class_exists(HttpTransport::class)) {
        class HttpTransport
        {
        }
    }
}

namespace WP\MCP\Infrastructure\ErrorHandling\Contracts {

    if (!interface_exists(McpErrorHandlerInterface::class)) {
        interface McpErrorHandlerInterface
        {
            public function log(string $message, array $context = [], string $type = 'error'): void;
        }
    }
}

namespace WP\MCP\Infrastructure\Observability {

    if (!class_exists(NullMcpObservabilityHandler::class)) {
        class NullMcpObservabilityHandler
        {
        }
    }
}
