<?php
/**
 * Creates an MCP server class in the plugin's app/Mcp/Servers folder, ready to list its tools.
 *
 * @package    FrameworkMcp
 * @subpackage Console
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\Console;

defined('ABSPATH') || exit;

class MakeServerCommand extends MakeComponentCommand
{
    /**
     * The directory under the application's Mcp folder.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $directory = 'Servers';

    /**
     * The stub file name.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $stub = 'mcp-server.stub';

    /**
     * What is generated, for messages.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $noun = 'server';

    /**
     * An example class name for the help text.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $example = 'WeatherServer';
}
