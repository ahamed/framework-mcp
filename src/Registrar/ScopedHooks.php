<?php
/**
 * Bridges the adapter's site-wide filters to the framework servers they concern.
 * Each filter receives the adapter's server instance; anything other than a server this
 * framework copy created passes through untouched, so other plugins' servers, and other
 * scoped copies of the framework, are never affected.
 *
 * Per-request visibility lives here too: a component whose should_register() declines is
 * removed from list responses and rejected before it runs.
 *
 * @package    FrameworkMcp
 * @subpackage Registrar
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\Registrar;

defined('ABSPATH') || exit;

use Ahamed\FrameworkMcp\McpManager;
use Ahamed\FrameworkMcp\Server;
use WP_Error;

class ScopedHooks
{
    /**
     * The manager holding the servers and their components.
     *
     * @var McpManager
     *
     * @since 1.0.0
     */
    protected $manager;

    /**
     * Runs the components' should_register().
     *
     * @var AbilityInvoker
     *
     * @since 1.0.0
     */
    protected $invoker;

    /**
     * Visibility already decided in this request, keyed by server, kind, and component.
     *
     * @var array<string, bool>
     *
     * @since 1.0.0
     */
    protected $visibility = [];

    /**
     * Create the bridge.
     *
     * @param McpManager $manager The manager.
     * @param AbilityInvoker $invoker Runs should_register().
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function __construct(McpManager $manager, AbilityInvoker $invoker)
    {
        $this->manager = $manager;
        $this->invoker = $invoker;
    }

    /**
     * Attach the bridge to the adapter's filters.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function listen()
    {
        $kinds = [
            'tool' => ['mcp_adapter_tools_list', 'mcp_adapter_pre_tool_call', 'mcp_adapter_tool_call_result'],
            'resource' => [
                'mcp_adapter_resources_list',
                'mcp_adapter_pre_resource_read',
                'mcp_adapter_resource_read_result',
            ],
            'prompt' => ['mcp_adapter_prompts_list', 'mcp_adapter_pre_prompt_get', 'mcp_adapter_prompt_get_result'],
        ];

        foreach ($kinds as $kind => [$list, $before, $after]) {
            add_filter($list, function ($records, $server = null) use ($kind) {
                return $this->filter_list($kind, $records, $server);
            }, 10, 2);

            add_filter($before, function ($arguments, $name = '', $component = null, $server = null) use ($kind) {
                return $this->before($kind, (string) $name, $arguments, $server);
            }, 10, 4);

            // The result filters pass ($result, $arguments, $name, $component, $server).
            add_filter($after, function ($result, ...$context) use ($kind) {
                return $this->after($kind, (string) ($context[1] ?? ''), $result, $context[3] ?? null);
            }, 10, 5);
        }

        add_filter('mcp_adapter_initialize_response', function ($response, $server = null) {
            return $this->initialize($response, $server);
        }, 10, 2);
    }

    /**
     * Drop the components hidden for this request from a list response.
     *
     * @param string $kind The component kind.
     * @param mixed $records The protocol records.
     * @param mixed $adapter_server The adapter server.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function filter_list(string $kind, $records, $adapter_server)
    {
        $id = $this->server_id($adapter_server);

        if ($id === null || !is_array($records)) {
            return $records;
        }

        return array_values(array_filter($records, function ($record) use ($id, $kind) {
            $key = $this->record_key($kind, $record);

            return $key === null || $this->visible($id, $kind, $key);
        }));
    }

    /**
     * Reject a hidden component, then let the server inspect the arguments.
     *
     * @param string $kind The component kind.
     * @param string $name The tool or prompt name, or the resource URI.
     * @param mixed $arguments The arguments.
     * @param mixed $adapter_server The adapter server.
     *
     * @return mixed The arguments, or a WP_Error rejecting the call.
     *
     * @since 1.0.0
     */
    public function before(string $kind, string $name, $arguments, $adapter_server)
    {
        $id = $this->server_id($adapter_server);

        if ($id === null || $arguments instanceof WP_Error) {
            return $arguments;
        }

        if (!$this->visible($id, $kind, $name)) {
            $message = sprintf('%s [%s] is not available.', ucfirst($kind), $name);

            return new WP_Error('mcp_component_unavailable', $message);
        }

        return $this->server($id)->before_call($kind, $name, $arguments);
    }

    /**
     * Let the server inspect or replace a component's result.
     *
     * @param string $kind The component kind.
     * @param string $name The tool or prompt name, or the resource URI.
     * @param mixed $result The result.
     * @param mixed $adapter_server The adapter server.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function after(string $kind, string $name, $result, $adapter_server)
    {
        $id = $this->server_id($adapter_server);

        return $id === null ? $result : $this->server($id)->after_call($kind, $name, $result);
    }

    /**
     * Let the server adjust its initialize response.
     *
     * @param mixed $response The response.
     * @param mixed $adapter_server The adapter server.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function initialize($response, $adapter_server)
    {
        $id = $this->server_id($adapter_server);

        return $id === null ? $response : $this->server($id)->initialize_response($response);
    }

    /**
     * Decide, once per request, whether a server's component is visible.
     *
     * A key that names none of the server's components is left alone: it is not ours to hide.
     *
     * @param string $id The server identifier.
     * @param string $kind The component kind.
     * @param string $key The MCP tool or prompt name, or the resource URI.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    protected function visible(string $id, string $kind, string $key)
    {
        $component = $this->manager->component($id, $kind, $key);

        if ($component === null) {
            return true;
        }

        $memo = $id . '|' . $kind . '|' . $key;

        if (!isset($this->visibility[$memo])) {
            $this->visibility[$memo] = $this->invoker->should_register($component);
        }

        return $this->visibility[$memo];
    }

    /**
     * Read the identifier of a list record: the URI for resources, the name otherwise.
     *
     * @param string $kind The component kind.
     * @param mixed $record The protocol record.
     *
     * @return string|null
     *
     * @since 1.0.0
     */
    protected function record_key(string $kind, $record)
    {
        $field = $kind === 'resource' ? 'uri' : 'name';
        $getter = 'get' . ucfirst($field);

        if (is_object($record) && method_exists($record, $getter)) {
            return (string) $record->$getter();
        }

        if (is_array($record) && isset($record[$field])) {
            return (string) $record[$field];
        }

        return null;
    }

    /**
     * Get the identifier of an adapter server, when this manager created it.
     *
     * @param mixed $adapter_server The adapter server.
     *
     * @return string|null
     *
     * @since 1.0.0
     */
    protected function server_id($adapter_server)
    {
        if (!is_object($adapter_server) || !method_exists($adapter_server, 'get_server_id')) {
            return null;
        }

        $id = (string) $adapter_server->get_server_id();

        return $this->manager->created($id) && $this->manager->get($id) === $adapter_server ? $id : null;
    }

    /**
     * Get a framework server known to exist.
     *
     * @param string $id The identifier.
     *
     * @return Server
     *
     * @since 1.0.0
     */
    protected function server(string $id)
    {
        return $this->manager->instance($id);
    }
}
