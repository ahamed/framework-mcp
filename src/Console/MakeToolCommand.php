<?php
/**
 * Creates an MCP tool class in the plugin's app/Mcp/Tools folder.
 *
 * @package    FrameworkMcp
 * @subpackage Console
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\Console;

defined('ABSPATH') || exit;

class MakeToolCommand extends MakeComponentCommand
{
    /**
     * The directory under the application's Mcp folder.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $directory = 'Tools';

    /**
     * The stub file name.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $stub = 'mcp-tool.stub';

    /**
     * What is generated, for messages.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $noun = 'tool';

    /**
     * An example class name for the help text.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $example = 'CurrentWeatherTool';
}
