<?php

namespace Ahamed\FrameworkMcp\Tests\Unit;

use Ahamed\FrameworkMcp\Prompt;
use Ahamed\FrameworkMcp\Resource;
use Ahamed\FrameworkMcp\Tool;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\ClashingTool;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\CurrentWeatherTool;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\DescribeWeatherPrompt;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\NamedTool;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\NoUriResource;
use Ahamed\FrameworkMcp\Tests\Support\Fixtures\WeatherGuidelinesResource;
use InvalidArgumentException;
use LogicException;

class AbilityRegistrarTest extends McpTestCase
{
    public function test_derived_name_title_description_and_category(): void
    {
        $this->registrar()->register(CurrentWeatherTool::class, Tool::class);

        $ability = $this->ability('kirki/current-weather');

        $this->assertSame('Current Weather', $ability['label']);
        $this->assertSame('Fetches the current weather.', $ability['description']);
        $this->assertSame('kirki', $ability['category']);
        $this->assertIsCallable($ability['execute_callback']);
        $this->assertIsCallable($ability['permission_callback']);
    }

    public function test_mcp_name_replaces_the_slash(): void
    {
        $this->assertSame('kirki-current-weather', \Ahamed\FrameworkMcp\Registrar\AbilityRegistrar::mcp_name('kirki/current-weather'));
    }

    public function test_declared_name_title_category_and_meta(): void
    {
        $this->registrar()->register(NamedTool::class, Tool::class);

        $ability = $this->ability('shop/order-lookup');

        $this->assertSame('Order Lookup', $ability['label']);
        $this->assertSame('Order Lookup', $ability['description']);
        $this->assertSame('site', $ability['category']);
        $this->assertSame(['version' => 2], $ability['meta']['mcp']['_meta']);
        $this->assertSame(['title' => 'Look up an order'], $ability['meta']['annotations']);
    }

    public function test_application_prefix_is_kebab_cased(): void
    {
        $this->app->use_prefix('kirki_ecommerce');

        $this->registrar()->register(CurrentWeatherTool::class, Tool::class);

        $this->assertSame('kirki-ecommerce', $this->ability('kirki-ecommerce/current-weather')['category']);
    }

    public function test_class_listed_twice_is_registered_once(): void
    {
        $registrar = $this->registrar();

        $first = $registrar->register(CurrentWeatherTool::class, Tool::class);
        $second = $registrar->register(CurrentWeatherTool::class, Tool::class);

        $this->assertSame($first, $second);
        $this->assertCount(1, $GLOBALS['framework_test_abilities']);
    }

    public function test_name_collision_names_both_classes(): void
    {
        $registrar = $this->registrar();
        $registrar->register(NamedTool::class, Tool::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(NamedTool::class);

        $registrar->register(ClashingTool::class, Tool::class);
    }

    public function test_wrong_base_class_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(WeatherGuidelinesResource::class);

        $this->registrar()->register(WeatherGuidelinesResource::class, Tool::class);
    }

    public function test_tool_hints_and_schemas(): void
    {
        $this->registrar()->register(CurrentWeatherTool::class, Tool::class);

        $ability = $this->ability('kirki/current-weather');

        $this->assertSame(['readonly' => true, 'idempotent' => true], $ability['meta']['annotations']);
        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'location' => ['type' => 'string', 'description' => 'City'],
                'units' => ['type' => 'string', 'enum' => ['metric', 'imperial']],
            ],
            'required' => ['location'],
        ], $ability['input_schema']);
        $this->assertSame(['number'], (array) $ability['output_schema']['properties']['temperature']['type']);
    }

    public function test_tool_without_schema_gets_a_bare_object_and_no_output_schema(): void
    {
        $this->registrar()->register(NamedTool::class, Tool::class);

        $ability = $this->ability('shop/order-lookup');

        $this->assertSame(['type' => 'object'], $ability['input_schema']);
        $this->assertArrayNotHasKey('output_schema', $ability);
    }

    public function test_exposure_is_private_by_default_and_both_flags_are_explicit(): void
    {
        $this->registrar()->register(CurrentWeatherTool::class, Tool::class);

        $meta = $this->ability('kirki/current-weather')['meta'];

        $this->assertFalse($meta['public']);
        $this->assertFalse($meta['show_in_rest']);
        $this->assertFalse($meta['mcp']['public']);
        $this->assertSame('tool', $meta['mcp']['type']);
    }

    public function test_public_opt_in_does_not_imply_rest(): void
    {
        $this->registrar()->register(NamedTool::class, Tool::class);

        $meta = $this->ability('shop/order-lookup')['meta'];

        $this->assertTrue($meta['public']);
        $this->assertTrue($meta['mcp']['public']);
        $this->assertFalse($meta['show_in_rest']);
    }

    public function test_resource_meta_and_no_input_schema(): void
    {
        $this->registrar()->register(WeatherGuidelinesResource::class, Resource::class);

        $ability = $this->ability('kirki/weather-guidelines');

        $this->assertArrayNotHasKey('input_schema', $ability);
        $this->assertSame('resource', $ability['meta']['mcp']['type']);
        $this->assertSame('weather://guidelines', $ability['meta']['mcp']['uri']);
        $this->assertSame('text/markdown', $ability['meta']['mcp']['mimeType']);
        $this->assertSame(['audience' => ['assistant'], 'priority' => 0.9], $ability['meta']['mcp']['annotations']);
    }

    public function test_resource_without_uri_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(NoUriResource::class);

        $this->registrar()->register(NoUriResource::class, Resource::class);
    }

    public function test_prompt_arguments_become_a_string_schema(): void
    {
        $this->registrar()->register(DescribeWeatherPrompt::class, Prompt::class);

        $ability = $this->ability('kirki/describe-weather');

        $this->assertSame('prompt', $ability['meta']['mcp']['type']);
        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'tone' => ['type' => 'string', 'description' => 'The tone to use'],
                'length' => ['type' => 'string'],
            ],
            'required' => ['tone'],
        ], $ability['input_schema']);
    }

    public function test_missing_prefix_without_declared_name_explains_itself(): void
    {
        $this->app->use_prefix('');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('use_prefix()');

        $this->registrar()->register(CurrentWeatherTool::class, Tool::class);
    }
}
