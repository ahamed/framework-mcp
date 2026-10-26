<?php

/**
 * MCP component and server fixtures. Kept in one file because they are small and only make
 * sense read together with the tests that list them.
 */

namespace Ahamed\FrameworkMcp\Tests\Support\Fixtures;

use Framework\Exceptions\HttpException;
use Ahamed\FrameworkMcp\Argument;
use Ahamed\FrameworkMcp\JsonSchema\JsonSchema;
use Ahamed\FrameworkMcp\Prompt;
use Ahamed\FrameworkMcp\Request;
use Ahamed\FrameworkMcp\Resource;
use Ahamed\FrameworkMcp\Response;
use Ahamed\FrameworkMcp\Server;
use Ahamed\FrameworkMcp\Tool;
use RuntimeException;

class WeatherRepository
{
    public function forecast(string $location): string
    {
        return 'Sunny in ' . $location;
    }
}

class CurrentWeatherTool extends Tool
{
    protected $description = 'Fetches the current weather.';

    protected $read_only = true;

    protected $idempotent = true;

    public function input_schema(JsonSchema $schema)
    {
        return [
            'location' => $schema->string()->description('City')->required(),
            'units' => $schema->string()->enum(['metric', 'imperial']),
        ];
    }

    public function output_schema(JsonSchema $schema)
    {
        return ['temperature' => $schema->number()->required()];
    }

    public function handle(Request $request, WeatherRepository $weather)
    {
        return Response::text($weather->forecast($request->string('location')));
    }
}

class StructuredTool extends Tool
{
    public function handle(Request $request)
    {
        return Response::structured(['temperature' => 22.5]);
    }
}

class ImageTool extends Tool
{
    public function handle(Request $request)
    {
        return Response::image('png-bytes', 'image/png')->with_meta(['source' => 'radar']);
    }
}

class ErrorTool extends Tool
{
    public function handle(Request $request)
    {
        return Response::error('Location not found');
    }
}

class TwoBlocksTool extends Tool
{
    public function handle(Request $request)
    {
        return [Response::text('a'), Response::text('b')];
    }
}

class ValidatingTool extends Tool
{
    public function handle(Request $request)
    {
        $request->validate(['location' => 'required']);

        $GLOBALS['framework_test_mcp_reached'] = true;

        return 'ok';
    }
}

class ThrowingTool extends Tool
{
    public function handle(Request $request)
    {
        throw new RuntimeException('Cannot open /var/www/secret/db.sqlite');
    }
}

class ClientErrorTool extends Tool
{
    public function handle(Request $request)
    {
        throw new HttpException('Order is locked.', 409);
    }
}

class PostEditorTool extends Tool
{
    public function permission(Request $request)
    {
        return $request->integer('post_id') === 1;
    }

    public function handle(Request $request)
    {
        return 'edited';
    }
}

class AdminOnlyTool extends Tool
{
    public function should_register(Request $request)
    {
        return current_user_can('manage_options');
    }

    public function handle(Request $request)
    {
        return 'secret';
    }
}

class NoHandleTool extends Tool
{
}

class NamedTool extends Tool
{
    protected $name = 'shop/order-lookup';

    protected $title = 'Order Lookup';

    protected $category = 'site';

    protected $public = true;

    protected $meta = ['version' => 2];

    public function annotations()
    {
        return ['title' => 'Look up an order'];
    }

    public function handle(Request $request)
    {
        return 'order';
    }
}

class ClashingTool extends Tool
{
    protected $name = 'shop/order-lookup';

    public function handle(Request $request)
    {
        return 'clash';
    }
}

class WeatherGuidelinesResource extends Resource
{
    protected $uri = 'weather://guidelines';

    protected $mime_type = 'text/markdown';

    protected $audience = ['assistant'];

    protected $priority = 0.9;

    public function handle(Request $request)
    {
        return Response::text('# Guidelines');
    }
}

class RadarResource extends Resource
{
    protected $uri = 'weather://radar';

    public function handle(Request $request)
    {
        return [Response::blob('img', 'image/png'), Response::structured(['frames' => 3])];
    }
}

class NoUriResource extends Resource
{
    public function handle(Request $request)
    {
        return 'x';
    }
}

class DescribeWeatherPrompt extends Prompt
{
    public function arguments()
    {
        return [
            new Argument('tone', 'The tone to use', true),
            new Argument('length'),
        ];
    }

    public function handle(Request $request)
    {
        return [
            Response::text('You are a forecaster.')->as_assistant(),
            Response::text('Describe it ' . $request->string('tone')),
        ];
    }
}

class WeatherServer extends Server
{
    protected $name = 'Weather';

    protected $tools = [CurrentWeatherTool::class, AdminOnlyTool::class];

    protected $resources = [WeatherGuidelinesResource::class];

    protected $prompts = [DescribeWeatherPrompt::class];
}

class MinimalServer extends Server
{
    protected $name = 'Minimal';

    protected $tools = [StructuredTool::class];
}

class VersionedServer extends Server
{
    protected $tools = [StructuredTool::class];

    public function version()
    {
        return '9.9.9';
    }
}

class RoutedServer extends Server
{
    protected $route_namespace = 'shop/v2';

    protected $route = 'ai';

    protected $description = 'Shop tools.';
}

class StdioServer extends Server
{
    protected $transports = [];
}

class BrokenHandlerServer extends Server
{
    protected $error_handler = 'Missing\\Handler';
}

class AdminServer extends Server
{
    public function permission(\Framework\Http\Request $request, \WP_REST_Request $rest_request)
    {
        return current_user_can('manage_options') && $rest_request->get_route() !== '';
    }
}

class HookedServer extends Server
{
    protected $tools = [CurrentWeatherTool::class, AdminOnlyTool::class];

    public function initialize_response($response)
    {
        $response['capabilities']['framework'] = true;

        return $response;
    }

    public function before_call(string $kind, string $name, $arguments)
    {
        if ($name === 'kirki-current-weather' && ($arguments['location'] ?? '') === 'Atlantis') {
            return new \WP_Error('unknown_location', 'Atlantis is not a real place.');
        }

        return $arguments;
    }

    public function after_call(string $kind, string $name, $result)
    {
        return is_string($result) ? strtoupper($result) : $result;
    }
}

class NotAServer
{
}
