<?php

namespace Ahamed\FrameworkMcp\Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Component\Finder\Finder;

class ScoperRecipeTest extends BaseTestCase
{
    protected string $workspace;

    protected array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $root = dirname(__DIR__, 2);
        $this->workspace = sys_get_temp_dir() . '/framework-mcp-scoper-' . uniqid();

        mkdir($this->workspace . '/vendor/ahamed', 0777, true);
        symlink($root, $this->workspace . '/vendor/ahamed/framework-mcp');
        copy($root . '/scoper.example.php', $this->workspace . '/scoper.php');

        $this->config = require $this->workspace . '/scoper.php';
    }

    protected function tearDown(): void
    {
        unlink($this->workspace . '/vendor/ahamed/framework-mcp');
        $this->remove($this->workspace);

        parent::tearDown();
    }

    public function test_finder_collects_the_package_sources_and_stubs(): void
    {
        $paths = [];

        foreach ($this->config['finders'] as $finder) {
            $this->assertInstanceOf(Finder::class, $finder);

            foreach ($finder as $file) {
                $paths[] = $file->getRelativePathname();
            }
        }

        $this->assertContains('Tool.php', $paths);
        $this->assertContains('Console/stubs/mcp-tool.stub', $paths);
    }

    public function test_only_framework_and_this_package_are_prefixed(): void
    {
        $prefixed = ['Framework', 'Framework\\Http', 'Ahamed\\FrameworkMcp', 'Ahamed\\FrameworkMcp\\Registrar'];
        $left_alone = ['', 'WP\\MCP\\Core', 'Ahamed', 'Ahamed\\Other', 'FrameworkX', 'Ahamed\\FrameworkMcpX', 'App\\Mcp'];

        foreach ($prefixed as $namespace) {
            $this->assertFalse($this->excluded($namespace), "[$namespace] should be prefixed.");
        }

        foreach ($left_alone as $namespace) {
            $this->assertTrue($this->excluded($namespace), "[$namespace] should be left alone.");
        }
    }

    public function test_patcher_prefixes_docblock_types(): void
    {
        $patcher = $this->config['patchers'][0];

        $source = implode("\n", [
            '/**',
            ' * @method static \\Ahamed\\FrameworkMcp\\PendingServer server(string $id)',
            ' * @param \\Framework\\Http\\Request $request',
            ' * @return \\Kirki\\Ecommerce\\Framework\\Route',
            ' * Mentions \\Framework\\Route in prose.',
            ' */',
        ]);

        $patched = $patcher('src/Facades/Mcp.php', 'Kirki\\Ecommerce', $source);

        $this->assertStringContainsString('@method static \\Kirki\\Ecommerce\\Ahamed\\FrameworkMcp\\PendingServer', $patched);
        $this->assertStringContainsString('@param \\Kirki\\Ecommerce\\Framework\\Http\\Request', $patched);
        $this->assertStringContainsString('@return \\Kirki\\Ecommerce\\Framework\\Route', $patched);
        $this->assertStringNotContainsString('Kirki\\Ecommerce\\Kirki', $patched);
        $this->assertStringContainsString('Mentions \\Framework\\Route in prose.', $patched);
    }

    public function test_stub_script_prefixes_both_namespaces_once(): void
    {
        $stubs = $this->workspace . '/stubs';
        mkdir($stubs);

        foreach (glob(dirname(__DIR__, 2) . '/src/Console/stubs/*.stub') as $stub) {
            copy($stub, $stubs . '/' . basename($stub));
        }

        $this->run_stub_script($stubs);
        $this->run_stub_script($stubs);

        $tool = file_get_contents($stubs . '/mcp-tool.stub');
        $server = file_get_contents($stubs . '/mcp-server.stub');

        $this->assertStringContainsString('use Kirki\\Ecommerce\\Ahamed\\FrameworkMcp\\Tool;', $tool);
        $this->assertStringContainsString('use Kirki\\Ecommerce\\Ahamed\\FrameworkMcp\\Server;', $server);
        $this->assertStringContainsString('namespace {{namespace}};', $tool);
        $this->assertStringNotContainsString('Kirki\\Ecommerce\\Kirki', $tool);
        $this->assertDoesNotMatchRegularExpression('/(?<![\w\\\\])Ahamed\\\\FrameworkMcp\\\\/', $tool);
    }

    protected function excluded(string $namespace): bool
    {
        foreach ($this->config['exclude-namespaces'] as $pattern) {
            if (preg_match($pattern, $namespace) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function run_stub_script(string $stubs): void
    {
        $script = dirname(__DIR__, 2) . '/bin/framework-mcp-prefix-stubs';

        exec(
            sprintf('%s %s %s %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($script), escapeshellarg($stubs), escapeshellarg('Kirki\\Ecommerce')),
            $output,
            $status
        );

        $this->assertSame(0, $status, implode("\n", $output));
    }

    protected function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . '/' . $entry);
            }
        }

        rmdir($path);
    }
}
