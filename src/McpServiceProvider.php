<?php
/**
 * Opt-in provider for MCP server support over the WordPress MCP Adapter plugin.
 * Not a base provider: an application adds it when it exposes MCP servers. Every hook it adds
 * waits for WordPress 6.9's Abilities API and the adapter plugin, so on a site without them
 * nothing it registers ever runs.
 *
 * @package FrameworkMcp
 * @since   1.0.0
 */
namespace Ahamed\FrameworkMcp;

defined('ABSPATH') || exit;

use Ahamed\FrameworkMcp\Console\MakePromptCommand;
use Ahamed\FrameworkMcp\Console\MakeResourceCommand;
use Ahamed\FrameworkMcp\Console\MakeServerCommand;
use Ahamed\FrameworkMcp\Console\MakeToolCommand;
use Ahamed\FrameworkMcp\Registrar\AbilityInvoker;
use Ahamed\FrameworkMcp\Registrar\AbilityRegistrar;
use Ahamed\FrameworkMcp\Registrar\MiddlewareRunner;
use Ahamed\FrameworkMcp\Registrar\ResponseMapper;
use Ahamed\FrameworkMcp\Registrar\ScopedHooks;
use Ahamed\FrameworkMcp\Registrar\ServerFactory;
use Framework\ServiceProvider;
use Framework\Supports\Facades\Command;

class McpServiceProvider extends ServiceProvider
{
    /**
     * The generator commands, keyed by command name.
     *
     * @var array<string, string>
     *
     * @since 1.0.0
     */
    protected $commands = [
        'make:mcp-server' => MakeServerCommand::class,
        'make:mcp-tool' => MakeToolCommand::class,
        'make:mcp-resource' => MakeResourceCommand::class,
        'make:mcp-prompt' => MakePromptCommand::class,
    ];

    /**
     * Register the MCP manager and its collaborators.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function register()
    {
        $this->app->singleton(AbilityInvoker::class, function () {
            return new AbilityInvoker(new ResponseMapper());
        });

        $this->app->singleton(McpManager::class, function () {
            $invoker = $this->app->make(AbilityInvoker::class);

            return new McpManager(new AbilityRegistrar($invoker), new ServerFactory($invoker));
        });

        $this->app->alias('mcp', McpManager::class);
    }

    /**
     * Hook the MCP lifecycle, the HTTP middleware, the server-scoped filters, and the commands.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function boot()
    {
        $manager = $this->app->make(McpManager::class);

        add_action(McpManager::CATEGORIES_HOOK, function () use ($manager) {
            $manager->register_categories();
        });

        add_action(McpManager::ABILITIES_HOOK, function () use ($manager) {
            $manager->register_abilities();
        });

        add_action(McpManager::ADAPTER_HOOK, function ($adapter) use ($manager) {
            $manager->create_servers($adapter);
        });

        (new MiddlewareRunner($manager))->listen();
        (new ScopedHooks($manager, $this->app->make(AbilityInvoker::class)))->listen();

        if ($this->app->is_cli_available()) {
            foreach ($this->commands as $name => $class) {
                Command::register($name, $class);
            }
        }
    }
}
