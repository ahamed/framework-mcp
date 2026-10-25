<?php
/**
 * Runs a server's middleware on requests to its HTTP endpoint.
 * The adapter registers the REST route itself and flattens every rejection from its permission
 * callback to a bare false, which would turn a 429 into a 401. Middleware therefore runs in
 * rest_request_before_callbacks: WordPress applies that filter before the permission callback
 * and sends a returned WP_Error with its own status, skipping the permission and endpoint
 * callbacks.
 *
 * References are resolved with the router's alias registry and grammar. The parsing repeats
 * Route::parse_middleware(), which is protected, so this subsystem stays free of core edits.
 *
 * @package    FrameworkMcp
 * @subpackage Registrar
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\Registrar;

defined('ABSPATH') || exit;

use Framework\Contracts\Middleware;
use Framework\Exceptions\AuthorizationException;
use Framework\Exceptions\HttpException;
use Framework\Exceptions\InvalidMiddlewareException;
use Framework\Http\Request as HttpRequest;
use Ahamed\FrameworkMcp\McpManager;
use Framework\Route;
use Framework\Supports\Facades\Log;
use WP_Error;
use WP_REST_Request;

class MiddlewareRunner
{
    /**
     * The manager holding the servers and their middleware.
     *
     * @var McpManager
     *
     * @since 1.0.0
     */
    protected $manager;

    /**
     * The request the last verdict was computed for.
     *
     * @var WP_REST_Request|null
     *
     * @since 1.0.0
     */
    protected $checked_request;

    /**
     * The last verdict: true to continue, or the rejection.
     *
     * @var true|WP_Error|null
     *
     * @since 1.0.0
     */
    protected $checked_result;

    /**
     * Create the runner.
     *
     * @param McpManager $manager The manager.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function __construct(McpManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Attach the runner to WordPress.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function listen()
    {
        add_filter('rest_request_before_callbacks', function ($response, $handler, $request) {
            return $this->handle($response, $request);
        }, 10, 3);
    }

    /**
     * Run the middleware of the server the request targets, if any.
     *
     * Memoized per request object: a second pass for the same request would charge middleware
     * with side effects, such as the rate limiter, twice.
     *
     * @param mixed $response The response so far; a WP_Error means another filter rejected it.
     * @param mixed $request The REST request.
     *
     * @return mixed The response unchanged, or the rejection.
     *
     * @since 1.0.0
     */
    public function handle($response, $request)
    {
        if ($response instanceof WP_Error || !$request instanceof WP_REST_Request) {
            return $response;
        }

        $middlewares = $this->middlewares_for((string) $request->get_route());

        if (empty($middlewares)) {
            return $response;
        }

        if ($this->checked_request !== $request) {
            $this->checked_result = $this->run($middlewares, $request);
            $this->checked_request = $request;
        }

        return $this->checked_result === true ? $response : $this->checked_result;
    }

    /**
     * Get the middleware of the HTTP server served at a route.
     *
     * @param string $route The REST route.
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function middlewares_for(string $route)
    {
        $route = '/' . trim($route, '/');

        foreach ($this->manager->registrations() as $id => $pending) {
            $server = $this->manager->instance($id);

            if ($server === null || !$this->manager->created($id) || empty($server->transports())) {
                continue;
            }

            if ($server->rest_route() === $route) {
                return $pending->middlewares();
            }
        }

        return [];
    }

    /**
     * Run a middleware pipeline and turn its outcome into a verdict.
     *
     * @param array $middlewares The middleware references.
     * @param WP_REST_Request $rest_request The REST request.
     *
     * @return true|WP_Error
     *
     * @since 1.0.0
     */
    protected function run(array $middlewares, WP_REST_Request $rest_request)
    {
        try {
            $pipeline = array_reduce(
                array_reverse($middlewares),
                function ($next, $middleware) {
                    return function ($request) use ($next, $middleware) {
                        [$class, $parameters] = $this->parse($middleware);

                        return (new $class())->handle($request, $next, ...$parameters);
                    };
                },
                function () {
                    return true;
                }
            );

            $pipeline(HttpRequest::from_wp_rest_request($rest_request));

            return true;
        } catch (HttpException $exception) {
            return new WP_Error(
                $exception->error_code(),
                $exception->getMessage(),
                array_merge(
                    ['status' => $exception->get_status()],
                    $exception->get_headers() ? ['headers' => $exception->get_headers()] : []
                )
            );
        } catch (AuthorizationException $exception) {
            return new WP_Error('rest_forbidden', $exception->getMessage(), ['status' => $exception->getCode()]);
        } catch (InvalidMiddlewareException $exception) {
            Log::error('[mcp] ' . $exception->getMessage());

            return new WP_Error('mcp_invalid_middleware', $exception->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Resolve a middleware reference to its class and arguments.
     *
     * Everything before the first colon is an alias or a class name; everything after it is a
     * comma separated argument list, as on routes.
     *
     * @param mixed $middleware The reference.
     *
     * @return array The class and its arguments.
     *
     * @throws InvalidMiddlewareException When the reference resolves to no middleware class.
     *
     * @since 1.0.0
     */
    public function parse($middleware)
    {
        if (!is_string($middleware)) {
            return [$middleware, []];
        }

        $name = $middleware;
        $parameters = [];

        if (strpos($middleware, ':') !== false) {
            [$name, $arguments] = explode(':', $middleware, 2);

            $parameters = $arguments === '' ? [] : explode(',', $arguments);
        }

        $aliases = Route::get_middleware_aliases();
        $class = $aliases[$name] ?? $name;

        if (!class_exists($class) || !is_subclass_of($class, Middleware::class)) {
            throw new InvalidMiddlewareException(sprintf(
                'Middleware [%s] is not a registered alias and is not a middleware class.',
                $name
            ));
        }

        return [$class, $parameters];
    }
}
