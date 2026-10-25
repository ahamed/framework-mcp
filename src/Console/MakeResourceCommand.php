<?php
/**
 * Creates an MCP resource class in the plugin's app/Mcp/Resources folder.
 *
 * @package    FrameworkMcp
 * @subpackage Console
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\Console;

defined('ABSPATH') || exit;

class MakeResourceCommand extends MakeComponentCommand
{
    /**
     * The directory under the application's Mcp folder.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $directory = 'Resources';

    /**
     * The stub file name.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $stub = 'mcp-resource.stub';

    /**
     * What is generated, for messages.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $noun = 'resource';

    /**
     * An example class name for the help text.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $example = 'WeatherGuidelinesResource';
}
