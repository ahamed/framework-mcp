<?php
/**
 * Creates an MCP prompt class in the plugin's app/Mcp/Prompts folder.
 *
 * @package    FrameworkMcp
 * @subpackage Console
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\Console;

defined('ABSPATH') || exit;

class MakePromptCommand extends MakeComponentCommand
{
    /**
     * The directory under the application's Mcp folder.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $directory = 'Prompts';

    /**
     * The stub file name.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $stub = 'mcp-prompt.stub';

    /**
     * What is generated, for messages.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $noun = 'prompt';

    /**
     * An example class name for the help text.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $example = 'DescribeWeatherPrompt';
}
