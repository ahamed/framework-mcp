<?php

namespace Ahamed\FrameworkMcp\Tests\Unit;

use Ahamed\FrameworkMcp\Facades\Mcp;
use Ahamed\FrameworkMcp\Handlers\LogErrorHandler;
use Ahamed\FrameworkMcp\McpManager;
use Ahamed\FrameworkMcp\PendingServer;
use Framework\Route;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\AdminServer;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\BrokenHandlerServer;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\MinimalServer;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\NotAServer;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\RoutedServer;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\StdioServer;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\VersionedServer;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\WeatherServer;
use InvalidArgumentException;
use LogicException;
use WP\MCP\Core\McpAdapter;
use WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
use WP\MCP\Transport\HttpTransport;
use WP_Error;
use WP_REST_Request;

class ServerRegistrationTest extends McpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::set_namespace('my-plugin/v1');
    }

    public function test_provider_not_registered_adds_no_hooks(): void
    {
        $this->assertArrayNotHasKey(McpManager::ADAPTER_HOOK, $GLOBALS['framework_test_actions']);
        $this->assertArrayNotHasKey(McpManager::ABILITIES_HOOK, $GLOBALS['framework_test_actions']);
    }

    public function test_provider_registers_the_facade_and_hooks(): void
    {
        $this->register_provider();

        $this->assertInstanceOf(PendingServer::class, Mcp::server('weather', WeatherServer::class));
        $this->assertArrayHasKey(McpManager::ADAPTER_HOOK, $GLOBALS['framework_test_actions']);
        $this->assertArrayHasKey('rest_request_before_callbacks', $GLOBALS['framework_test_filters']);
    }

    public function test_minimal_server_gets_every_default(): void
    {
        $this->register_provider();
        Mcp::server('minimal', MinimalServer::class);

        $adapter = $this->boot_lifecycle();
        $arguments = $adapter->get_server('minimal')->arguments;

        $this->assertSame('my-plugin/v1', $arguments['server_route_namespace']);
        $this->assertSame('mcp/minimal', $arguments['server_route']);
        $this->assertSame('Minimal', $arguments['server_name']);
        $this->assertSame('Minimal', $arguments['server_description']);
        $this->assertSame($this->app->version(), $arguments['server_version']);
        $this->assertSame([HttpTransport::class], $arguments['mcp_transports']);
        $this->assertSame(LogErrorHandler::class, $arguments['error_handler']);
        $this->assertSame(NullMcpObservabilityHandler::class, $arguments['observability_handler']);
        $this->assertSame(['kirki/structured'], $arguments['tools']);
        $this->assertSame([], $arguments['resources']);
        $this->assertNull($arguments['transport_permission_callback']);
    }

    public function test_server_lists_resolve_to_ability_names(): void
    {
        $this->register_provider();
        Mcp::server('weather', WeatherServer::class);

        $arguments = $this->boot_lifecycle()->get_server('weather')->arguments;

        $this->assertSame(['kirki/current-weather', 'kirki/admin-only'], $arguments['tools']);
        $this->assertSame(['kirki/weather-guidelines'], $arguments['resources']);
        $this->assertSame(['kirki/describe-weather'], $arguments['prompts']);
    }

    public function test_overridden_getter_wins_over_the_property(): void
    {
        $this->register_provider();
        Mcp::server('versioned', VersionedServer::class);

        $this->assertSame('9.9.9', $this->boot_lifecycle()->get_server('versioned')->arguments['server_version']);
    }

    public function test_declared_route_and_description(): void
    {
        $this->register_provider();
        Mcp::server('shop', RoutedServer::class);

        $arguments = $this->boot_lifecycle()->get_server('shop')->arguments;

        $this->assertSame('shop/v2', $arguments['server_route_namespace']);
        $this->assertSame('ai', $arguments['server_route']);
        $this->assertSame('Shop tools.', $arguments['server_description']);
        $this->assertSame('/shop/v2/ai', Mcp::instance('shop')->rest_route());
    }

    public function test_stdio_only_server_has_no_transports(): void
    {
        $this->register_provider();
        Mcp::server('maintenance', StdioServer::class);

        $this->assertSame([], $this->boot_lifecycle()->get_server('maintenance')->arguments['mcp_transports']);
    }

    public function test_several_servers_are_created_in_order(): void
    {
        $this->register_provider();
        Mcp::server('first', MinimalServer::class);
        Mcp::server('second', RoutedServer::class);

        $this->assertSame(['first', 'second'], array_keys($this->boot_lifecycle()->servers));
    }

    public function test_servers_are_created_when_the_adapter_initializes_before_the_abilities_registry(): void
    {
        $this->register_provider();
        Mcp::server('first', MinimalServer::class);

        // Over REST the adapter fires mcp_adapter_init on rest_api_init, before anything has
        // read the abilities registry, so the registry's lazy init hooks have not fired yet.
        $adapter = new McpAdapter();
        framework_test_do_action('mcp_adapter_init', $adapter);

        $this->assertSame(['first'], array_keys($adapter->servers));
        $this->assertArrayHasKey('kirki/structured', $GLOBALS['framework_test_abilities']);
    }

    public function test_duplicate_identifier_is_rejected(): void
    {
        $this->register_provider();
        Mcp::server('weather', WeatherServer::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('[weather]');

        Mcp::server('weather', MinimalServer::class);
    }

    public function test_registration_after_the_adapter_initialized_is_rejected(): void
    {
        $this->register_provider();
        $this->boot_lifecycle();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('before the');

        Mcp::server('late', MinimalServer::class);
    }

    public function test_class_that_is_not_a_server_is_rejected(): void
    {
        $this->register_provider();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(NotAServer::class);

        Mcp::server('nope', NotAServer::class);
    }

    public function test_missing_adapter_is_silent(): void
    {
        $this->register_provider();
        Mcp::server('weather', WeatherServer::class);

        framework_test_do_action('wp_abilities_api_categories_init');
        framework_test_do_action('wp_abilities_api_init');

        $this->assertNull(Mcp::get('weather'));
        $this->assertSame([], $this->log->lines);
    }

    public function test_rejected_server_is_logged_and_the_rest_still_created(): void
    {
        $this->register_provider();
        Mcp::server('first', BrokenHandlerServer::class);
        Mcp::server('second', MinimalServer::class);

        $adapter = new McpAdapter();
        $adapter->failures['first'] = new WP_Error('invalid_error_handler', 'Error handler class "Missing\\Handler" does not exist.');

        $this->boot_lifecycle($adapter);

        $this->assertTrue($this->log->contains('Server [first] was not created: Error handler class'));
        $this->assertNotNull(Mcp::get('second'));
        $this->assertNull(Mcp::get('first'));
    }

    public function test_missing_rest_namespace_is_logged(): void
    {
        Route::set_namespace('');
        $this->register_provider();
        Mcp::server('minimal', MinimalServer::class);

        $this->boot_lifecycle();

        $this->assertTrue($this->log->contains('has no REST namespace'));
    }

    public function test_server_permission_becomes_the_transport_callback(): void
    {
        $this->register_provider();
        Mcp::server('admin', AdminServer::class);

        $callback = $this->boot_lifecycle()->get_server('admin')->arguments['transport_permission_callback'];
        $request = new WP_REST_Request('POST', '/my-plugin/v1/mcp/admin');

        $GLOBALS['framework_test_user_can']['manage_options'] = false;
        $this->assertFalse($callback($request));

        $GLOBALS['framework_test_user_can']['manage_options'] = true;
        $this->assertTrue($callback($request));
    }

    public function test_lookup(): void
    {
        $this->register_provider();
        Mcp::server('weather', WeatherServer::class);

        $this->assertNull(Mcp::get('weather'));

        $adapter = $this->boot_lifecycle();

        $this->assertSame($adapter->get_server('weather'), Mcp::get('weather'));
        $this->assertNull(Mcp::get('missing'));
        $this->assertSame(['weather' => WeatherServer::class], Mcp::servers());
    }

    public function test_default_and_custom_categories(): void
    {
        $this->app->use_prefix('kirki_ecommerce');
        $this->register_provider();
        Mcp::server('minimal', MinimalServer::class);
        Mcp::category('reports', 'Reports', 'Reporting abilities');

        $this->boot_lifecycle();

        $categories = $GLOBALS['framework_test_ability_categories'];

        $this->assertSame(['label' => 'Reports', 'description' => 'Reporting abilities'], $categories['reports']);
        $this->assertSame('Kirki Ecommerce', $categories['kirki-ecommerce']['label']);
        $this->assertNotSame('', $categories['kirki-ecommerce']['description']);
    }

    public function test_no_default_category_without_servers(): void
    {
        $this->register_provider();

        framework_test_do_action('wp_abilities_api_categories_init');

        $this->assertSame([], $GLOBALS['framework_test_ability_categories']);
    }

    public function test_late_category_is_rejected(): void
    {
        $this->register_provider();
        framework_test_do_action('wp_abilities_api_categories_init');

        $this->expectException(LogicException::class);

        Mcp::category('late', 'Late');
    }
}
