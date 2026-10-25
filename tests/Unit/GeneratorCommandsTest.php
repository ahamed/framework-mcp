<?php

namespace Ahamed\FrameworkMcp\Tests\Unit;

use Framework\Application;
use Framework\Filesystem\Filesystem;
use Ahamed\FrameworkMcp\Console\MakePromptCommand;
use Ahamed\FrameworkMcp\Console\MakeResourceCommand;
use Ahamed\FrameworkMcp\Console\MakeServerCommand;
use Ahamed\FrameworkMcp\Console\MakeToolCommand;
use Ahamed\FrameworkMcp\Prompt;
use Ahamed\FrameworkMcp\Resource;
use Ahamed\FrameworkMcp\Server;
use Ahamed\FrameworkMcp\Tool;
use Ahamed\FrameworkMcp\Tests\Support\Cache\TestFilesystem;
use Ahamed\FrameworkMcp\Tests\Unit\TestCase;
use RuntimeException;

class GeneratorCommandsTest extends TestCase
{
    protected string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir() . '/framework-mcp-commands-' . uniqid();
        mkdir($this->base . '/app', 0777, true);
        file_put_contents($this->base . '/composer.json', json_encode([
            'autoload' => ['psr-4' => ['Acme\\Shop\\' => 'app/']],
        ]));

        $this->reset_container_instance();
        $app = Application::get_instance($this->base);
        $app->use_prefix('shop');
        $app->instance(Filesystem::class, new TestFilesystem());
    }

    protected function tearDown(): void
    {
        (new TestFilesystem())->delete($this->base, true);

        parent::tearDown();
    }

    public function generators(): array
    {
        return [
            'server' => [MakeServerCommand::class, 'weather_server', 'Servers/WeatherServer.php', 'WeatherServer', Server::class],
            'tool' => [MakeToolCommand::class, 'current_weather_tool', 'Tools/CurrentWeatherTool.php', 'CurrentWeatherTool', Tool::class],
            'resource' => [MakeResourceCommand::class, 'GuidelinesResource', 'Resources/GuidelinesResource.php', 'GuidelinesResource', Resource::class],
            'prompt' => [MakePromptCommand::class, 'DescribePrompt', 'Prompts/DescribePrompt.php', 'DescribePrompt', Prompt::class],
        ];
    }

    /**
     * @dataProvider generators
     */
    public function test_generates_a_valid_component(string $command, string $argument, string $path, string $class, string $base): void
    {
        (new $command())->run([$argument], []);

        $file = $this->base . '/app/Mcp/' . $path;
        $code = (string) file_get_contents($file);
        $namespace = 'Acme\\Shop\\Mcp\\' . explode('/', $path)[0];

        $this->assertStringContainsString('namespace ' . $namespace . ';', $code);
        $this->assertStringContainsString("defined('ABSPATH') || exit;", $code);
        $this->assertStringStartsWith("<?php\n/**", $code);

        require_once $file;

        $this->assertTrue(is_subclass_of($namespace . '\\' . $class, $base));
    }

    public function test_generated_server_declares_empty_lists(): void
    {
        (new MakeServerCommand())->run(['ShopServer'], []);

        $code = (string) file_get_contents($this->base . '/app/Mcp/Servers/ShopServer.php');

        foreach (['$name', '$version', '$description', '$tools', '$resources', '$prompts'] as $property) {
            $this->assertStringContainsString('protected ' . $property, $code);
        }
    }

    public function test_refuses_to_overwrite(): void
    {
        (new MakeToolCommand())->run(['LookupTool'], []);

        $file = $this->base . '/app/Mcp/Tools/LookupTool.php';
        file_put_contents($file, 'edited');

        try {
            (new MakeToolCommand())->run(['LookupTool'], []);
            $this->fail('The command did not stop.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('LookupTool.php', $exception->getMessage());
        }

        $this->assertSame('edited', file_get_contents($file));
    }
}
