<?php

namespace Ahamed\FrameworkMcp\Tests\Unit;

use Framework\Cache\MemoTable;
use Ahamed\FrameworkMcp\Facades\Mcp;
use Ahamed\FrameworkMcp\Registrar\MiddlewareRunner;
use Framework\Middlewares\AuthMiddleware;
use Framework\Middlewares\ThrottleRequests;
use Framework\RateLimiting\RateLimiter;
use Framework\Route;
use Ahamed\FrameworkMcp\Tests\Support\Cache\FrozenArrayStore;
use Ahamed\FrameworkMcp\Tests\Support\Cache\FrozenRepository;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\MinimalServer;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\RoutedServer;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\StdioServer;
use Ahamed\FrameworkMcp\Tests\Support\RateLimiting\FrozenRateLimiter;
use WP_Error;
use WP_REST_Request;

class MiddlewareRunnerTest extends McpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        MemoTable::clear();
        ThrottleRequests::forget_headers();

        $now = time();
        $store = (new FrozenArrayStore())->freeze($now);
        $limiter = (new FrozenRateLimiter())->set_cache((new FrozenRepository($store, 'array', false))->freeze($now));
        $limiter->freeze($now);
        $this->app->instance(RateLimiter::class, $limiter);

        Route::set_namespace('my-plugin/v1');
        Route::middleware_alias('throttle', ThrottleRequests::class);
        Route::middleware_alias('auth', AuthMiddleware::class);

        $this->register_provider();
    }

    protected function tearDown(): void
    {
        ThrottleRequests::forget_headers();

        parent::tearDown();
    }

    protected function filter(WP_REST_Request $request, $response = null)
    {
        return framework_test_apply_filters('rest_request_before_callbacks', $response, [], $request);
    }

    public function test_parsing_matches_the_router(): void
    {
        $route_parse = new \ReflectionMethod(Route::class, 'parse_middleware');
        $route_parse->setAccessible(true);

        $runner = new MiddlewareRunner($this->app->make('mcp'));

        foreach (['throttle:60,1', 'throttle', 'throttle:', 'auth', AuthMiddleware::class] as $reference) {
            $this->assertSame($route_parse->invoke(null, $reference), $runner->parse($reference), $reference);
        }
    }

    public function test_throttled_server_answers_429_with_retry_after(): void
    {
        Mcp::server('minimal', MinimalServer::class)->middleware('throttle:2,1');
        $this->boot_lifecycle();

        $this->assertNull($this->filter(new WP_REST_Request('POST', '/my-plugin/v1/mcp/minimal')));
        $this->assertNull($this->filter(new WP_REST_Request('POST', '/my-plugin/v1/mcp/minimal')));

        $rejection = $this->filter(new WP_REST_Request('POST', '/my-plugin/v1/mcp/minimal'));

        $this->assertInstanceOf(WP_Error::class, $rejection);
        $this->assertSame(429, $rejection->get_error_data()['status']);
        $this->assertArrayHasKey('Retry-After', $rejection->get_error_data()['headers']);
    }

    public function test_same_request_object_is_charged_once(): void
    {
        Mcp::server('minimal', MinimalServer::class)->middleware('throttle:1,1');
        $this->boot_lifecycle();

        $request = new WP_REST_Request('POST', '/my-plugin/v1/mcp/minimal');

        $this->assertNull($this->filter($request));
        $this->assertNull($this->filter($request));
    }

    public function test_auth_rejection_keeps_its_status(): void
    {
        Mcp::server('minimal', MinimalServer::class)->middleware(['auth']);
        $this->boot_lifecycle();

        $rejection = $this->filter(new WP_REST_Request('POST', '/my-plugin/v1/mcp/minimal'));

        $this->assertSame('rest_forbidden', $rejection->get_error_code());
        $this->assertSame(401, $rejection->get_error_data()['status']);
    }

    public function test_middleware_is_scoped_to_its_server(): void
    {
        Mcp::server('minimal', MinimalServer::class)->middleware('auth');
        Mcp::server('shop', RoutedServer::class);
        $this->boot_lifecycle();

        $this->assertSame('untouched', $this->filter(new WP_REST_Request('POST', '/shop/v2/ai'), 'untouched'));
        $this->assertSame('untouched', $this->filter(new WP_REST_Request('GET', '/wp/v2/posts'), 'untouched'));
    }

    public function test_stdio_only_server_has_no_http_middleware(): void
    {
        Mcp::server('maintenance', StdioServer::class)->middleware('auth');
        $this->boot_lifecycle();

        $this->assertNull($this->filter(new WP_REST_Request('POST', '/my-plugin/v1/mcp/maintenance')));
    }

    public function test_existing_rejection_passes_through(): void
    {
        Mcp::server('minimal', MinimalServer::class)->middleware('auth');
        $this->boot_lifecycle();

        $earlier = new WP_Error('earlier', 'Rejected by another filter.');

        $this->assertSame($earlier, $this->filter(new WP_REST_Request('POST', '/my-plugin/v1/mcp/minimal'), $earlier));
    }

    public function test_unknown_middleware_is_a_logged_server_error(): void
    {
        Mcp::server('minimal', MinimalServer::class)->middleware('nonexistent:1');
        $this->boot_lifecycle();

        $rejection = $this->filter(new WP_REST_Request('POST', '/my-plugin/v1/mcp/minimal'));

        $this->assertSame(500, $rejection->get_error_data()['status']);
        $this->assertStringContainsString('[nonexistent]', $rejection->get_error_message());
        $this->assertTrue($this->log->contains('[nonexistent]'));
    }
}
