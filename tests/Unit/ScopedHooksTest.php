<?php

namespace Ahamed\FrameworkMcp\Tests\Unit;

use Ahamed\FrameworkMcp\Facades\Mcp;
use Framework\Route;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\HookedServer;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\MinimalServer;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\WeatherServer;
use WP\MCP\Core\McpServer;
use WP_Error;

class ScopedHooksTest extends McpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::set_namespace('my-plugin/v1');
        $this->register_provider();
    }

    protected function record(string $field, string $value)
    {
        return new class ($field, $value) {
            protected string $field;

            protected string $value;

            public function __construct(string $field, string $value)
            {
                $this->field = $field;
                $this->value = $value;
            }

            public function getName(): string
            {
                return $this->field === 'name' ? $this->value : '';
            }

            public function getUri(): string
            {
                return $this->field === 'uri' ? $this->value : '';
            }
        };
    }

    protected function tool_names(array $records): array
    {
        return array_map(function ($record) {
            return $record->getName();
        }, $records);
    }

    public function test_hidden_component_is_dropped_from_the_list(): void
    {
        Mcp::server('weather', WeatherServer::class);
        $this->boot_lifecycle();

        $records = [$this->record('name', 'kirki-current-weather'), $this->record('name', 'kirki-admin-only')];

        $GLOBALS['framework_test_user_can']['manage_options'] = false;
        $listed = framework_test_apply_filters('mcp_adapter_tools_list', $records, Mcp::get('weather'), null);

        $this->assertSame(['kirki-current-weather'], $this->tool_names($listed));
    }

    public function test_visible_component_stays_listed_for_admins(): void
    {
        Mcp::server('weather', WeatherServer::class);
        $this->boot_lifecycle();

        $records = [$this->record('name', 'kirki-current-weather'), $this->record('name', 'kirki-admin-only')];

        $GLOBALS['framework_test_user_can']['manage_options'] = true;
        $listed = framework_test_apply_filters('mcp_adapter_tools_list', $records, Mcp::get('weather'), null);

        $this->assertCount(2, $listed);
    }

    public function test_hidden_component_cannot_be_called(): void
    {
        Mcp::server('weather', WeatherServer::class);
        $this->boot_lifecycle();

        $GLOBALS['framework_test_user_can']['manage_options'] = false;
        $result = framework_test_apply_filters('mcp_adapter_pre_tool_call', [], 'kirki-admin-only', null, Mcp::get('weather'));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('mcp_component_unavailable', $result->get_error_code());
    }

    public function test_resources_are_keyed_by_uri(): void
    {
        Mcp::server('weather', WeatherServer::class);
        $this->boot_lifecycle();

        $records = [$this->record('uri', 'weather://guidelines')];
        $listed = framework_test_apply_filters('mcp_adapter_resources_list', $records, Mcp::get('weather'), null);

        $this->assertCount(1, $listed);
        $this->assertSame(['uri' => 'weather://guidelines'], framework_test_apply_filters(
            'mcp_adapter_pre_resource_read',
            ['uri' => 'weather://guidelines'],
            'weather://guidelines',
            null,
            Mcp::get('weather')
        ));
    }

    public function test_before_call_can_reject(): void
    {
        Mcp::server('hooked', HookedServer::class);
        $this->boot_lifecycle();

        $result = framework_test_apply_filters('mcp_adapter_pre_tool_call', ['location' => 'Atlantis'], 'kirki-current-weather', null, Mcp::get('hooked'));

        $this->assertSame('unknown_location', $result->get_error_code());
        $this->assertSame(['location' => 'Dhaka'], framework_test_apply_filters(
            'mcp_adapter_pre_tool_call',
            ['location' => 'Dhaka'],
            'kirki-current-weather',
            null,
            Mcp::get('hooked')
        ));
    }

    public function test_after_call_transforms_the_result(): void
    {
        Mcp::server('hooked', HookedServer::class);
        $this->boot_lifecycle();

        $this->assertSame('SUNNY', framework_test_apply_filters('mcp_adapter_tool_call_result', 'sunny', [], 'kirki-current-weather', null, Mcp::get('hooked')));
    }

    public function test_initialize_response_is_adjusted_for_its_server_only(): void
    {
        Mcp::server('hooked', HookedServer::class);
        Mcp::server('minimal', MinimalServer::class);
        $this->boot_lifecycle();

        $hooked = framework_test_apply_filters('mcp_adapter_initialize_response', ['capabilities' => []], Mcp::get('hooked'), null);
        $minimal = framework_test_apply_filters('mcp_adapter_initialize_response', ['capabilities' => []], Mcp::get('minimal'), null);

        $this->assertTrue($hooked['capabilities']['framework']);
        $this->assertSame(['capabilities' => []], $minimal);
    }

    public function test_foreign_servers_pass_through(): void
    {
        Mcp::server('hooked', HookedServer::class);
        $this->boot_lifecycle();

        $impostor = new McpServer('hooked', []);
        $foreign = new McpServer('other-plugin', []);

        foreach ([$impostor, $foreign] as $server) {
            $this->assertSame('sunny', framework_test_apply_filters('mcp_adapter_tool_call_result', 'sunny', [], 'kirki-current-weather', null, $server));
            $this->assertSame(['location' => 'Atlantis'], framework_test_apply_filters('mcp_adapter_pre_tool_call', ['location' => 'Atlantis'], 'kirki-current-weather', null, $server));
        }
    }
}
