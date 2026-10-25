<?php

namespace Ahamed\FrameworkMcp\Tests\Unit;

use Framework\Application;
use Framework\Managers\LogManager;
use Ahamed\FrameworkMcp\McpServiceProvider;
use Ahamed\FrameworkMcp\Registrar\AbilityInvoker;
use Ahamed\FrameworkMcp\Registrar\AbilityRegistrar;
use Ahamed\FrameworkMcp\Registrar\ResponseMapper;
use Ahamed\FrameworkMcp\Tests\Support\RecordingLog;
use Ahamed\FrameworkMcp\Tests\Unit\TestCase;
use WP\MCP\Core\McpAdapter;

require_once dirname(__DIR__) . '/Support/Fixtures.php';

abstract class McpTestCase extends TestCase
{
    protected Application $app;

    protected RecordingLog $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reset_mcp_globals();

        $this->app = $this->bootstrap_application();
        $this->app->use_prefix('kirki');

        $this->log = new RecordingLog();
        $this->app->instance(LogManager::class, $this->log);
    }

    protected function tearDown(): void
    {
        $this->reset_mcp_globals();

        parent::tearDown();
    }

    protected function reset_mcp_globals(): void
    {
        $GLOBALS['framework_test_actions'] = [];
        $GLOBALS['framework_test_did_actions'] = [];
        $GLOBALS['framework_test_filters'] = [];
        $GLOBALS['framework_test_abilities'] = [];
        $GLOBALS['framework_test_ability_categories'] = [];
        $GLOBALS['framework_test_user_can'] = [];
        $GLOBALS['framework_test_current_user_id'] = 0;
        $GLOBALS['framework_test_mcp_reached'] = false;
    }

    protected function registrar(): AbilityRegistrar
    {
        return new AbilityRegistrar(new AbilityInvoker(new ResponseMapper()));
    }

    protected function register_provider(): McpServiceProvider
    {
        $provider = new McpServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        return $provider;
    }

    /**
     * Fire the three lifecycle hooks in the order WordPress does, handing the adapter double to
     * mcp_adapter_init.
     */
    protected function boot_lifecycle(?McpAdapter $adapter = null): McpAdapter
    {
        $adapter = $adapter ?? new McpAdapter();

        framework_test_do_action('wp_abilities_api_categories_init');
        framework_test_do_action('wp_abilities_api_init');
        framework_test_do_action('mcp_adapter_init', $adapter);

        return $adapter;
    }

    protected function ability(string $name): array
    {
        $this->assertArrayHasKey($name, $GLOBALS['framework_test_abilities'], "Ability [$name] was not registered.");

        return $GLOBALS['framework_test_abilities'][$name];
    }
}
