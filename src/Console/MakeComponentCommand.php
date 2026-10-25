<?php
/**
 * Shared body of the make:mcp-* generators.
 * The four commands differ only in their directory, stub, and wording, so each subclass
 * declares those and this class writes the file.
 *
 * @package    FrameworkMcp
 * @subpackage Console
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\Console;

defined('ABSPATH') || exit;

use Framework\Console\CommandBase;
use Framework\Console\Synopsis;
use Framework\Supports\Facades\File;
use Framework\Supports\Str;

use function Framework\app;
use function Framework\app_path;

abstract class MakeComponentCommand extends CommandBase
{
    /**
     * The directory under the application's Mcp folder, which is also the namespace segment.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $directory = '';

    /**
     * The stub file name.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $stub = '';

    /**
     * What is generated, for messages: server, tool, resource, or prompt.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $noun = '';

    /**
     * An example class name for the help text.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $example = '';

    /**
     * Prepare the command's synopsis and other metadata.
     *
     * @return void
     *
     * @since 1.0.0
     */
    protected function prepare()
    {
        $this->summary(sprintf('Create a new MCP %s class', $this->noun))
            ->description(sprintf("## EXAMPLES \n\n wp kirki make:mcp-%s %s", $this->noun, $this->example))
            ->synopsis(
                Synopsis::type('positional')
                    ->name('name')
                    ->description(sprintf('The %s class name', $this->noun))
            );
    }

    /**
     * Check if the command passed the validation.
     *
     * @param mixed $args The positional arguments.
     * @param mixed $assoc The associative arguments.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    protected function passed($args, $assoc)
    {
        return !empty($args[0]);
    }

    /**
     * Write the class, refusing to overwrite an existing file.
     *
     * @param mixed $args The positional arguments.
     * @param mixed $assoc The associative arguments.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function run($args, $assoc)
    {
        $class = Str::pascal((string) $args[0]);
        $namespace = app()->qualify_app_namespace('Mcp\\' . $this->directory);
        $output_file = app_path('Mcp/' . $this->directory . '/' . $class . '.php');

        if (File::exists($output_file)) {
            $this->cli_error(sprintf('MCP %s file already exists: %s', $this->noun, $output_file));

            return;
        }

        File::make_dir($output_file);
        File::put($output_file, Str::replace(
            ['{{namespace}}', '{{class_name}}'],
            [$namespace, $class],
            File::get($this->stub_path() . '/' . $this->stub)
        ));

        $this->cli_success(sprintf('MCP %s [%s] created.', $this->noun, $namespace . '\\' . $class));
    }

    /**
     * Get the directory holding the MCP stubs.
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function stub_path()
    {
        return __DIR__ . '/stubs';
    }
}
