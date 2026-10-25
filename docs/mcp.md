# MCP servers

This guide covers exposing your plugin to AI clients (Claude, Cursor, ChatGPT and others) through the [Model Context Protocol](https://modelcontextprotocol.io). The API follows [Laravel MCP](https://laravel.com/docs/mcp): servers, tools, resources and prompts are small classes. Under the hood every tool, resource and prompt is registered as a WordPress **Ability**, and servers are created through the canonical [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin.

This is the `ahamed/framework-mcp` package. It runs on top of [`themeum/framework`](https://github.com/themeum/framework) `^3.2.7`, which the plugin already uses. Tested against MCP Adapter **0.7.1** and WordPress **6.9+**.

## Table of contents

1. [Quick start](#1-quick-start)
2. [Servers](#2-servers)
3. [Registering servers and middleware](#3-registering-servers-and-middleware)
4. [Tools](#4-tools)
5. [Responses](#5-responses)
6. [Resources](#6-resources)
7. [Prompts](#7-prompts)
8. [Permission and visibility](#8-permission-and-visibility)
9. [Names, categories and exposure](#9-names-categories-and-exposure)
10. [Errors and logging](#10-errors-and-logging)
11. [Server-scoped hooks](#11-server-scoped-hooks)
12. [Connecting a client](#12-connecting-a-client)
13. [Generators](#13-generators)
14. [Where this differs from Laravel](#14-where-this-differs-from-laravel)

---

## 1. Quick start

**1. Require the package.** `themeum/framework` is not on Packagist, so the plugin's `composer.json` lists its GitHub repository (plugins on the framework already do), and Composer resolves this package's dependency on it from there:

```bash
composer require ahamed/framework-mcp
```

If the plugin scopes the framework with php-scoper, scope this package with the same prefix; see "Using with php-scoper" in the README.

**2. Install the MCP Adapter plugin** on the site. Since 0.7.0 the adapter expects to be installed as its own plugin rather than bundled, so this package only *suggests* it. Without the plugin, nothing below runs and nothing errors.

**3. Register the provider.** MCP support is opt-in. Add the provider to your `bootstrap/providers.php`:

```php
return [
    \Ahamed\FrameworkMcp\McpServiceProvider::class,
    \App\Providers\AppServiceProvider::class,
];
```

**4. Write a tool:**

```php
namespace App\Mcp\Tools;

use Ahamed\FrameworkMcp\JsonSchema\JsonSchema;
use Ahamed\FrameworkMcp\Request;
use Ahamed\FrameworkMcp\Response;
use Ahamed\FrameworkMcp\Tool;

class CurrentWeatherTool extends Tool
{
    protected $description = 'Fetches the current weather for a location.';

    protected $read_only = true;

    public function schema(JsonSchema $schema)
    {
        return [
            'location' => $schema->string()->description('City name')->required(),
        ];
    }

    public function handle(Request $request)
    {
        return Response::structured(['location' => $request->string('location'), 'temperature' => 22.5]);
    }
}
```

**5. Write a server and register it** from any service provider's `boot()`:

```php
namespace App\Mcp\Servers;

use Ahamed\FrameworkMcp\Server;

class WeatherServer extends Server
{
    protected $name = 'Weather';

    protected $description = 'Weather information and forecasts.';

    protected $tools = [\App\Mcp\Tools\CurrentWeatherTool::class];
}
```

```php
use Ahamed\FrameworkMcp\Facades\Mcp;

public function boot()
{
    Mcp::server('my-plugin-weather', WeatherServer::class)->middleware(['throttle:60,1']);
}
```

The server is now served at `/wp-json/{your REST namespace}/mcp/my-plugin-weather`, and over WP-CLI STDIO with `wp mcp-adapter serve --server=my-plugin-weather`.

---

## 2. Servers

A server class carries every value the adapter's `create_server()` takes. Each property is read through a method of the same name, so a subclass can compute any of them at runtime by overriding the method instead of declaring the property.

| Property | Default | Notes |
|---|---|---|
| `$name` | Humanized class name | Shown to clients |
| `$version` | The application version | |
| `$description` | `$name` | Sent to clients as the MCP `instructions` |
| `$route_namespace` | The application REST namespace | |
| `$route` | `mcp/{server id}` | |
| `$transports` | `[HttpTransport::class]` | `[]` for a STDIO-only server |
| `$error_handler` | `LogErrorHandler::class` | Any `McpErrorHandlerInterface` class |
| `$observability_handler` | `NullMcpObservabilityHandler::class` | Any `McpObservabilityHandlerInterface` class |
| `$tools`, `$resources`, `$prompts` | `[]` | Class names |

```php
class ShopServer extends Server
{
    protected $route_namespace = 'shop/v2';

    protected $route = 'ai';

    protected $tools = [OrderLookupTool::class];

    public function version()
    {
        return MY_PLUGIN_VERSION;
    }
}
```

### Transport permission

Define `permission()` to gate *every* request to the server, before any tool runs. It receives the framework HTTP request and may also type-hint `WP_REST_Request`. Return `bool` or `WP_Error`.

```php
public function permission(\Framework\Http\Request $request)
{
    return current_user_can('manage_options');
}
```

A server without `permission()` keeps the adapter's default gate, which requires the `read` capability. That capability is filterable through `mcp_adapter_default_transport_permission_user_capability`.

### STDIO-only servers

Every server is reachable over WP-CLI STDIO. Setting `$transports = []` removes the HTTP endpoint, so the server is reachable **only** by someone with shell access. Use this for maintenance or destructive tooling:

```php
class MaintenanceServer extends Server
{
    protected $transports = [];

    protected $tools = [FlushCachesTool::class, RunMigrationsTool::class];
}
```

---

## 3. Registering servers and middleware

```php
Mcp::server('my-plugin-weather', WeatherServer::class);
Mcp::server('my-plugin-shop', ShopServer::class)->middleware(['auth', 'throttle:mcp']);
```

- Register servers from a service provider's `boot()`. Registering after the Abilities API or the adapter has initialized throws a `LogicException`, because the server would otherwise silently never exist.
- Server identifiers are **global to the site**, so prefix them with your plugin's slug.
- `middleware()` accepts everything a route accepts: aliases (`auth`, `throttle`), aliases with arguments (`throttle:60,1`), named limiters (`throttle:mcp`), and class names. It runs, in order, on every HTTP request to that server's endpoint before WordPress checks the route's permission. A rejection keeps its status and headers, so a throttled client receives a `429` with `Retry-After`. Middleware never runs for STDIO sessions.

Lookup:

```php
Mcp::get('my-plugin-weather');   // the adapter's McpServer, once created; null before
Mcp::servers();                  // ['my-plugin-weather' => WeatherServer::class, ...]
```

---

## 4. Tools

A tool extends `Tool` and defines `handle()`.

### Input schema

`schema()` returns a map of argument names to fragments built with the `JsonSchema` builder. The framework wraps the map into an object schema, and WordPress validates every call against it before `handle()` runs.

```php
public function schema(JsonSchema $schema)
{
    return [
        'location' => $schema->string()->description('City name')->min(2)->required(),
        'units' => $schema->string()->enum(['metric', 'imperial'])->default('metric'),
        'days' => $schema->integer()->min(1)->max(14),
        'tags' => $schema->array()->items($schema->string()),
        'geo' => $schema->object([
            'lat' => $schema->number()->required(),
            'lng' => $schema->number()->required(),
        ])->additional_properties(false),
    ];
}
```

| Builder | Modifiers |
|---|---|
| Every type | `description`, `title`, `default`, `enum`, `nullable`, `required` |
| `string()` | `min`/`max` (length), `pattern`, `format` |
| `integer()`, `number()` | `min`/`max` (value) |
| `array()` | `items`, `min`/`max` (items) |
| `object()` | properties, `additional_properties`, `min`/`max` (properties) |

Every value in the map must be a builder fragment; a raw array throws.

### Output schema

`output_schema()` takes the same form and is advertised to clients. When declared, WordPress validates the tool's result against it, so such a tool should return `Response::structured()`.

### Annotations

| Property | Sent as |
|---|---|
| `$read_only` | `readOnlyHint` |
| `$destructive` | `destructiveHint` |
| `$idempotent` | `idempotentHint` |
| `$open_world` | `openWorldHint` |

Undeclared hints are omitted rather than sent as `false`. Merge raw annotations with `annotations()`:

```php
public function annotations()
{
    return ['title' => 'Look up an order'];
}
```

### The request

`handle()`, `permission()` and `should_register()` receive a `Ahamed\FrameworkMcp\Request`:

```php
$request->all();
$request->get('geo.lat', 0);
$request->has('units');
$request->only(['location']);
$request->except(['units']);
$request->string('location');
$request->integer('days', 3);
$request->boolean('detailed');
$request->validate(['location' => 'required|string']);   // framework validation rules
$request->user();
$request->user_id();
```

A failed `validate()` reaches the client as a tool error carrying the validation messages.

### Dependency injection

Components and servers are built by the container. Constructor dependencies are injected, and so is any class type-hinted after the request:

```php
public function handle(Request $request, WeatherRepository $weather)
{
    return Response::text($weather->forecast($request->string('location')));
}
```

---

## 5. Responses

```php
Response::text('Sunny, 22°C');
Response::error('Location not found');                   // a tool error result
Response::structured(['temperature' => 22.5]);
Response::image($png_bytes, 'image/png');                 // raw bytes; encoded for you
Response::blob($pdf_bytes, 'application/pdf', 'file://report.pdf');
Response::embedded('weather://guidelines', $markdown, 'text/markdown');

Response::text('...')->with_meta(['source' => 'weather-api']);
```

`handle()` may also return a plain string, an array (sent as structured content), or a `WP_Error`.

What each component can return is limited by the adapter:

- **Tools** return exactly one response. Returning a list is an error. `Response::text('x')` reaches the client as the JSON text `{"result":"x"}`, because the adapter always encodes tool results. Prefer `structured()` for tools.
- **Resources** may return a list of responses, which become several contents.
- **Prompts** return a list of messages (see [section 7](#7-prompts)).

`blob()` and `embedded()` outside a resource need a URI, because the adapter can only carry them as an embedded resource.

---

## 6. Resources

A resource extends `Resource`, declares a `$uri`, and is read without arguments.

```php
class WeatherGuidelinesResource extends Resource
{
    protected $description = 'How to use the weather tools.';

    protected $uri = 'weather://guidelines';

    protected $mime_type = 'text/markdown';

    protected $audience = ['assistant'];   // annotations: audience, priority, last_modified

    protected $priority = 0.8;

    public function handle(Request $request)
    {
        return Response::text(file_get_contents(__DIR__ . '/guidelines.md'));
    }
}
```

A text response without its own MIME type takes the resource's `$mime_type`.

---

## 7. Prompts

A prompt extends `Prompt`, declares its arguments, and returns messages. MCP prompt arguments are always strings.

```php
use Ahamed\FrameworkMcp\Argument;

class DescribeWeatherPrompt extends Prompt
{
    protected $description = 'Explains the weather in plain language.';

    public function arguments()
    {
        return [
            new Argument('location', 'The city', true),
            new Argument('tone', 'formal or casual'),
        ];
    }

    public function handle(Request $request)
    {
        return [
            Response::text('You are a friendly forecaster.')->as_assistant(),
            Response::text('Describe the weather in ' . $request->string('location')),
        ];
    }
}
```

Messages are from the user unless marked `as_assistant()`. Required arguments are enforced before `handle()` runs.

---

## 8. Permission and visibility

### `permission()`

Each component's `permission()` decides whether the current user may run it with the given arguments:

```php
public function permission(Request $request)
{
    return current_user_can('edit_post', $request->integer('post_id'));
}
```

A component without `permission()` requires the `read` capability. This is the safe default on every channel the ability may be reachable from (see [section 9](#9-names-categories-and-exposure)).

### `should_register()`

`should_register()` hides a component per request. A hidden component is left out of the server's list response and is rejected when called.

```php
public function should_register(Request $request)
{
    return current_user_can('manage_woocommerce');
}
```

It runs once per MCP request, with the current user known and no arguments. An exception thrown inside it hides the component and is logged.

Both methods, like `handle()`, are looked up by name rather than declared on the base class, so you may type-hint extra dependencies after the request.

---

## 9. Names, categories and exposure

**Names.** An ability is named `{app prefix}/{class name}` in kebab case, without the `Tool`, `Resource` or `Prompt` suffix. With prefix `kirki`, `CurrentWeatherTool` becomes `kirki/current-weather`, and clients see it as `kirki-current-weather`. Declare `$name = 'vendor/name'` to choose your own. Two classes resolving to the same name throw. A class listed by several servers is registered once and shared.

**Titles and descriptions.** `$title` defaults to the humanized class name, and `$description` defaults to the title.

**Categories.** Every ability needs a category. The framework registers one for your application, using the kebab-case app prefix, and uses it by default. Declare more with `Mcp::category()`, then reference them from `$category`. WordPress core's `site` and `user` work without declaring them.

```php
Mcp::category('my-plugin-reports', 'Reports', 'Reporting abilities');
```

**Exposure.** An ability is reachable **only** through the servers that list it, unless you opt in:

| Property | Effect |
|---|---|
| `$public = true` | Offered to other clients, including the adapter's default server (`mcp-adapter-execute-ability`) |
| `$show_in_rest = true` | Offered through the core Abilities REST API (`/wp-abilities/v1`) |

The two flags are independent. WordPress would otherwise copy `show_in_rest` from `public`.

---

## 10. Errors and logging

- An exception thrown from `handle()` or `permission()` is written to the framework log. The client sees the real message only for a `ValidationException`, for an `HttpException` with a status below 500, or when `WP_DEBUG` is on. Otherwise it sees "The request could not be completed." The adapter on its own would send the raw exception message, which can leak paths and queries.
- The default `$error_handler`, `LogErrorHandler`, sends the adapter's own diagnostics to the framework log: failed permission callbacks, invalid results, and rejected components. The adapter's built-in default for custom servers discards them.
- A server the adapter refuses to create, such as one with an unknown handler class or a duplicate id, is logged and skipped. The remaining servers still come up.

---

## 11. Server-scoped hooks

The adapter's filters are site-wide. Override these `Server` methods to act only on your own server's requests:

```php
public function initialize_response($response)
{
    $response['instructions'] .= ' Prices are in BDT.';

    return $response;
}

public function before_call(string $kind, string $name, $arguments)
{
    // $kind: tool, resource or prompt. $name: the tool/prompt name or resource URI.
    if ($kind === 'tool' && empty($arguments['store_id'])) {
        return new \WP_Error('missing_store', 'A store_id is required.');
    }

    return $arguments;
}

public function after_call(string $kind, string $name, $result)
{
    return $result;
}
```

Servers created by other plugins are never affected. For behaviour that genuinely is site-wide, use the adapter's filters directly. Examples: `mcp_adapter_create_default_server` turns off the adapter's default server; `mcp_adapter_session_inactivity_timeout` adjusts the session timeout.

---

## 12. Connecting a client

**Over HTTP.** Clients authenticate as a WordPress user, typically with an [Application Password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/). Point the client at the server URL:

```
https://example.com/wp-json/my-plugin/v1/mcp/my-plugin-weather
```

**Over STDIO.** Run the server through WP-CLI as a given user:

```bash
wp mcp-adapter serve --server=my-plugin-weather --user=admin
```

**Inspecting.** The [MCP Inspector](https://github.com/modelcontextprotocol/inspector) works against either:

```bash
npx @modelcontextprotocol/inspector wp mcp-adapter serve --server=my-plugin-weather --user=admin
```

---

## 13. Generators

```bash
wp kirki make:mcp-server WeatherServer
wp kirki make:mcp-tool CurrentWeatherTool
wp kirki make:mcp-resource WeatherGuidelinesResource
wp kirki make:mcp-prompt DescribeWeatherPrompt
```

The commands write to `app/Mcp/{Servers,Tools,Resources,Prompts}` and refuse to overwrite an existing file. They exist only when the MCP provider is registered.

---

## 14. Where this differs from Laravel

The gaps fall into two groups. Some come from PHP 7.4. Others come from what the WordPress MCP Adapter can express for ability-backed components.

**Properties instead of attributes.** PHP 7.4 has no attributes, so `#[Name]`, `#[Description]`, `#[IsReadOnly]`, `#[Uri]` and the rest are protected properties. Each can be overridden through a method of the same name.

**No `Mcp::web()` or `Mcp::local()`.** The server class owns its route and transports. `Mcp::server()` registers it. A local-only server sets `$transports = []` (see [section 2](#stdio-only-servers)), and every server is already reachable through `wp mcp-adapter serve`.

**No resource templates.** The adapter answers `resources/templates/list` with an empty list and looks resources up by exact URI. There is no `HasUriTemplate` or `uri_template()`; declare each concrete URI as its own resource.

**No elicitation.** The adapter supports MCP 2026-07-28 "input required" results only for direct callable tools, not for ability-backed ones.

**One content block per tool result.** Laravel lets a tool return several responses. The adapter emits exactly one block. It also always JSON-encodes tool results, so `Response::text('x')` arrives as `{"result":"x"}`.

**No audio, streaming or notifications.** The adapter has no audio block for ability results and no server-sent events, so there is no `Response::audio()`, no generator responses, and no `Response::notification()`.

**`with_meta()` is dropped on text and structured tool results.** The adapter has no place to carry `_meta` there. It is kept on images, embedded and binary results, resource contents and prompt messages.

**Prompt-level annotations.** MCP prompts carry no annotations, so `Prompt` has no audience or priority properties. Put them on messages with `with_meta()`.

**`permission()` per component.** Laravel has no per-tool permission. Here every component has one, because WordPress requires a permission callback on every ability and the ability may be reachable from more than one server.

**Authentication.** There is no OAuth or Sanctum integration. Clients authenticate as WordPress users, usually with Application Passwords. Gate a server with `permission()` or middleware.

**No `mcp:inspector` command.** Run the MCP Inspector with `npx` (see [section 12](#12-connecting-a-client)).

**No test helpers yet.** Laravel's `WeatherServer::tool(...)->assertOk()` has no equivalent in this release.
