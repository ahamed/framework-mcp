<?php
/**
 * The playground's MCP server: one tool, one resource and one prompt.
 */
namespace Example\App\Mcp\Servers;

defined('ABSPATH') || exit;

use Ahamed\FrameworkMcp\Server;
use Example\App\Mcp\Prompts\SummarizePostPrompt;
use Example\App\Mcp\Resources\SiteSettingsResource;
use Example\App\Mcp\Tools\SiteInfoTool;

class ExampleServer extends Server
{
    /**
     * The server name shown to clients.
     *
     * @var string
     */
    protected $name = 'Framework MCP Example';

    /**
     * The server version.
     *
     * @var string
     */
    protected $version = '0.1.0';

    /**
     * What the server offers. Clients receive it as the MCP instructions.
     *
     * @var string
     */
    protected $description = 'Facts about this WordPress site, its reading settings, and a prompt that summarizes a post.';

    /**
     * The tool classes the server offers.
     *
     * @var string[]
     */
    protected $tools = [
        SiteInfoTool::class,
    ];

    /**
     * The resource classes the server offers.
     *
     * @var string[]
     */
    protected $resources = [
        SiteSettingsResource::class,
    ];

    /**
     * The prompt classes the server offers.
     *
     * @var string[]
     */
    protected $prompts = [
        SummarizePostPrompt::class,
    ];
}
