<?php
/**
 * Base for the MCP servers an application defines.
 * The class carries every value the adapter's create_server() takes, so a server is fully
 * described in one place; the identifier alone comes from registration, since the same class
 * may be registered under different identifiers by different plugins.
 *
 * A server that defines permission() gates every request through it as the adapter's transport
 * permission callback. It is looked up by name rather than declared here, so a subclass may
 * type-hint dependencies after the request and so that an undefined one keeps the adapter's
 * own default gate.
 *
 * @package FrameworkMcp
 * @since   1.0.0
 */
namespace Ahamed\FrameworkMcp;

defined('ABSPATH') || exit;

use Ahamed\FrameworkMcp\Handlers\LogErrorHandler;
use Framework\Route;
use WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
use WP\MCP\Transport\HttpTransport;

use function Framework\app;

abstract class Server
{
    /**
     * The identifier the server was registered under.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $id = '';

    /**
     * The server name shown to clients. Derived from the class name when null.
     *
     * @var string|null
     *
     * @since 1.0.0
     */
    protected $name;

    /**
     * The server version. The application version when null.
     *
     * @var string|null
     *
     * @since 1.0.0
     */
    protected $version;

    /**
     * What the server offers. Clients receive it as the MCP instructions. Falls back to the name.
     *
     * @var string|null
     *
     * @since 1.0.0
     */
    protected $description;

    /**
     * The REST namespace of the HTTP endpoint. The application REST namespace when null.
     *
     * @var string|null
     *
     * @since 1.0.0
     */
    protected $route_namespace;

    /**
     * The REST route of the HTTP endpoint. mcp/{id} when null.
     *
     * @var string|null
     *
     * @since 1.0.0
     */
    protected $route;

    /**
     * The adapter transport classes. Empty for a server reachable only over WP-CLI STDIO.
     *
     * @var string[]
     *
     * @since 1.0.0
     */
    protected $transports = [HttpTransport::class];

    /**
     * The adapter error handler class.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $error_handler = LogErrorHandler::class;

    /**
     * The adapter observability handler class.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $observability_handler = NullMcpObservabilityHandler::class;

    /**
     * The tool classes the server offers.
     *
     * @var string[]
     *
     * @since 1.0.0
     */
    protected $tools = [];

    /**
     * The resource classes the server offers.
     *
     * @var string[]
     *
     * @since 1.0.0
     */
    protected $resources = [];

    /**
     * The prompt classes the server offers.
     *
     * @var string[]
     *
     * @since 1.0.0
     */
    protected $prompts = [];

    /**
     * Get the identifier the server was registered under.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function id()
    {
        return $this->id;
    }

    /**
     * Set the identifier the server was registered under.
     *
     * @param string $id The identifier.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public function set_id(string $id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the server name.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function name()
    {
        if (!empty($this->name)) {
            return $this->name;
        }

        return ucwords(str_replace('-', ' ', Ability::kebab(substr(strrchr('\\' . static::class, '\\'), 1))));
    }

    /**
     * Get the server version.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function version()
    {
        return !empty($this->version) ? $this->version : (string) app()->version();
    }

    /**
     * Get the description, which clients receive as the MCP instructions.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function description()
    {
        return !empty($this->description) ? $this->description : $this->name();
    }

    /**
     * Get the REST namespace of the HTTP endpoint.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function route_namespace()
    {
        return !empty($this->route_namespace) ? $this->route_namespace : (string) Route::get_namespace();
    }

    /**
     * Get the REST route of the HTTP endpoint.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function route()
    {
        return !empty($this->route) ? $this->route : 'mcp/' . $this->id();
    }

    /**
     * Get the full REST route the endpoint is served at, as WP_REST_Request::get_route() reports it.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function rest_route()
    {
        return '/' . trim($this->route_namespace(), '/') . '/' . trim($this->route(), '/');
    }

    /**
     * Get the adapter transport classes.
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    public function transports()
    {
        return $this->transports;
    }

    /**
     * Get the adapter error handler class.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function error_handler()
    {
        return $this->error_handler;
    }

    /**
     * Get the adapter observability handler class.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function observability_handler()
    {
        return $this->observability_handler;
    }

    /**
     * Get the tool classes.
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    public function tools()
    {
        return $this->tools;
    }

    /**
     * Get the resource classes.
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    public function resources()
    {
        return $this->resources;
    }

    /**
     * Get the prompt classes.
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    public function prompts()
    {
        return $this->prompts;
    }

    /**
     * Adjust the response to a client's initialize request.
     *
     * @param mixed $response The response the adapter built.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function initialize_response($response)
    {
        return $response;
    }

    /**
     * Inspect or replace the arguments before one of this server's components runs.
     *
     * Returning a WP_Error rejects the call without running the component.
     *
     * @param string $kind The component kind: tool, resource, or prompt.
     * @param string $name The tool or prompt name, or the resource URI.
     * @param mixed $arguments The arguments.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function before_call(string $kind, string $name, $arguments)
    {
        return $arguments;
    }

    /**
     * Inspect or replace a component's result before the adapter sends it.
     *
     * @param string $kind The component kind: tool, resource, or prompt.
     * @param string $name The tool or prompt name, or the resource URI.
     * @param mixed $result The result.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function after_call(string $kind, string $name, $result)
    {
        return $result;
    }
}
