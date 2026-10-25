<?php
/**
 * Registers the playground's MCP server.
 */
namespace Example\App\Providers;

defined('ABSPATH') || exit;

use Ahamed\FrameworkMcp\Facades\Mcp;
use Example\App\Mcp\Servers\ExampleServer;
use Framework\ServiceProvider;

class ExampleMcpServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Register the MCP server. Servers must be declared before the adapter initializes.
     *
     * @return void
     */
    public function boot()
    {
        Mcp::server('mcp-example', ExampleServer::class)->middleware('throttle:30,1');
    }
}
