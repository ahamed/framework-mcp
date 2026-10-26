<?php
/**
 * A read-only MCP tool that reports facts about the site, used to exercise the MCP subsystem
 * end to end: input schema, output schema, permission, and a structured response.
 */
namespace Example\App\Mcp\Tools;

defined('ABSPATH') || exit;

use Ahamed\FrameworkMcp\JsonSchema\JsonSchema;
use Ahamed\FrameworkMcp\Request;
use Ahamed\FrameworkMcp\Response;
use Ahamed\FrameworkMcp\Tool;

class SiteInfoTool extends Tool
{
    /**
     * What the tool does, shown to the model.
     *
     * @var string
     */
    protected $description = 'Report the site name, URL, WordPress and PHP versions, and optionally post counts.';

    /**
     * The tool only reads.
     *
     * @var bool
     */
    protected $read_only = true;

    /**
     * Calling it twice gives the same answer.
     *
     * @var bool
     */
    protected $idempotent = true;

    /**
     * Declare the tool's arguments.
     *
     * @param JsonSchema $schema The schema builder.
     *
     * @return array
     */
    public function input_schema(JsonSchema $schema)
    {
        return [
            'include_counts' => $schema->boolean()
                ->description('Also return the number of published posts and pages.')
                ->default(false),
        ];
    }

    /**
     * Declare the shape of the structured result.
     *
     * @param JsonSchema $schema The schema builder.
     *
     * @return array
     */
    public function output_schema(JsonSchema $schema)
    {
        return [
            'name' => $schema->string()->required(),
            'url' => $schema->string()->required(),
            'wordpress_version' => $schema->string()->required(),
            'php_version' => $schema->string()->required(),
            'posts' => $schema->integer()->nullable(),
            'pages' => $schema->integer()->nullable(),
        ];
    }

    /**
     * Decide whether the current user may run the tool.
     *
     * @param Request $request The tool call.
     *
     * @return bool
     */
    public function permission(Request $request)
    {
        return current_user_can('read');
    }

    /**
     * Handle the tool call.
     *
     * @param Request $request The tool call.
     *
     * @return Response
     */
    public function handle(Request $request)
    {
        $counts = $request->boolean('include_counts');

        return Response::structured([
            'name' => get_bloginfo('name'),
            'url' => home_url('/'),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'posts' => $counts ? (int) wp_count_posts('post')->publish : null,
            'pages' => $counts ? (int) wp_count_posts('page')->publish : null,
        ]);
    }
}
