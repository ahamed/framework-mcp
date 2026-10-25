<?php
/**
 * A server registration, returned by Mcp::server() so middleware can be attached fluently.
 * Middleware belongs to the registration rather than the class because it guards one HTTP
 * endpoint, and the same server class can be registered behind different ones.
 *
 * @package FrameworkMcp
 * @since   1.0.0
 */
namespace Ahamed\FrameworkMcp;

defined('ABSPATH') || exit;

class PendingServer
{
    /**
     * The identifier the server is registered under.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $id;

    /**
     * The server class.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $server_class;

    /**
     * The middleware references guarding the server's HTTP endpoint, in order.
     *
     * @var array
     *
     * @since 1.0.0
     */
    protected $middlewares = [];

    /**
     * Create a registration.
     *
     * @param string $id The identifier.
     * @param string $server_class The server class.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function __construct(string $id, string $server_class)
    {
        $this->id = $id;
        $this->server_class = $server_class;
    }

    /**
     * Guard the server's HTTP endpoint with middleware, accepting anything a route accepts.
     *
     * @param string|array $middleware A middleware reference or a list of them.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public function middleware($middleware)
    {
        $this->middlewares = array_merge($this->middlewares, (array) $middleware);

        return $this;
    }

    /**
     * Get the identifier.
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
     * Get the server class.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function server_class()
    {
        return $this->server_class;
    }

    /**
     * Get the middleware references.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function middlewares()
    {
        return $this->middlewares;
    }
}
