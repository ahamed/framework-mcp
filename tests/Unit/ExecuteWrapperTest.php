<?php

namespace Ahamed\FrameworkMcp\Tests\Unit;

use Ahamed\FrameworkMcp\Prompt;
use Ahamed\FrameworkMcp\Registrar\AbilityInvoker;
use Ahamed\FrameworkMcp\Resource;
use Ahamed\FrameworkMcp\Tool;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\ClientErrorTool;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\CurrentWeatherTool;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\DescribeWeatherPrompt;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\ErrorTool;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\ImageTool;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\NoHandleTool;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\PostEditorTool;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\RadarResource;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\StructuredTool;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\ThrowingTool;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\TwoBlocksTool;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\ValidatingTool;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\WeatherGuidelinesResource;
use WP_Error;

class ExecuteWrapperTest extends McpTestCase
{
    protected function execute(string $class, string $base, $input = null)
    {
        $component = $this->registrar()->register($class, $base);

        return $this->ability($component->name())['execute_callback']($input);
    }

    protected function permit(string $class, $input = null)
    {
        $component = $this->registrar()->register($class, Tool::class);

        return $this->ability($component->name())['permission_callback']($input);
    }

    public function test_handle_receives_request_and_injected_dependency(): void
    {
        $this->assertSame('Sunny in Dhaka', $this->execute(CurrentWeatherTool::class, Tool::class, ['location' => 'Dhaka']));
    }

    public function test_structured_response_is_passed_as_an_array(): void
    {
        $this->assertSame(['temperature' => 22.5], $this->execute(StructuredTool::class, Tool::class, []));
    }

    public function test_error_response_becomes_wp_error(): void
    {
        $result = $this->execute(ErrorTool::class, Tool::class, []);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('Location not found', $result->get_error_message());
    }

    public function test_image_response_uses_the_adapter_image_shape(): void
    {
        $this->assertSame(
            ['type' => 'image', 'results' => 'png-bytes', 'mimeType' => 'image/png', '_meta' => ['source' => 'radar']],
            $this->execute(ImageTool::class, Tool::class, [])
        );
    }

    public function test_more_than_one_block_from_a_tool_is_an_error(): void
    {
        $result = $this->execute(TwoBlocksTool::class, Tool::class, []);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertTrue($this->log->contains('one content block'));
    }

    public function test_validation_failure_reports_its_message_and_stops(): void
    {
        $result = $this->execute(ValidatingTool::class, Tool::class, []);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertStringContainsString('required', $result->get_error_message());
        $this->assertFalse($GLOBALS['framework_test_mcp_reached']);
    }

    public function test_unexpected_exception_is_logged_and_hidden(): void
    {
        $result = $this->execute(ThrowingTool::class, Tool::class, []);

        $this->assertSame(AbilityInvoker::GENERIC_MESSAGE, $result->get_error_message());
        $this->assertStringNotContainsString('/var/www', $result->get_error_message());
        $this->assertTrue($this->log->contains('/var/www/secret/db.sqlite'));
    }

    public function test_client_http_exception_keeps_its_message(): void
    {
        $this->assertSame('Order is locked.', $this->execute(ClientErrorTool::class, Tool::class, [])->get_error_message());
    }

    public function test_missing_handle_is_reported(): void
    {
        $result = $this->execute(NoHandleTool::class, Tool::class, []);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertTrue($this->log->contains('must define a handle()'));
    }

    public function test_default_permission_requires_read(): void
    {
        $GLOBALS['framework_test_user_can']['read'] = false;
        $this->assertFalse($this->permit(CurrentWeatherTool::class, []));

        $GLOBALS['framework_test_user_can']['read'] = true;
        $this->assertTrue($this->permit(CurrentWeatherTool::class, []));
    }

    public function test_custom_permission_reads_arguments(): void
    {
        $this->assertTrue($this->permit(PostEditorTool::class, ['post_id' => 1]));
        $this->assertFalse($this->permit(PostEditorTool::class, ['post_id' => 2]));
    }

    public function test_resource_text_uses_its_uri_and_mime_type(): void
    {
        $this->assertEquals(
            [['uri' => 'weather://guidelines', 'mimeType' => 'text/markdown', 'text' => '# Guidelines']],
            $this->execute(WeatherGuidelinesResource::class, Resource::class)
        );
    }

    public function test_resource_returns_several_contents(): void
    {
        $this->assertSame([
            ['uri' => 'weather://radar', 'mimeType' => 'image/png', 'blob' => base64_encode('img')],
            ['uri' => 'weather://radar', 'mimeType' => 'application/json', 'text' => '{"frames":3}'],
        ], $this->execute(RadarResource::class, Resource::class));
    }

    public function test_prompt_messages_keep_their_roles(): void
    {
        $this->assertSame(['messages' => [
            ['role' => 'assistant', 'content' => ['type' => 'text', 'text' => 'You are a forecaster.']],
            ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Describe it calm']],
        ]], $this->execute(DescribeWeatherPrompt::class, Prompt::class, ['tone' => 'calm']));
    }
}
