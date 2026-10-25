## Why

AI clients (Claude, Cursor, ChatGPT) increasingly reach WordPress sites through the Model
Context Protocol, and WordPress now ships the pieces for it: the Abilities API in core (6.9+)
and the canonical `WordPress/mcp-adapter` plugin that turns abilities into MCP tools,
resources and prompts. Using them directly means a 13-positional-argument
`create_server()` call, hand-written JSON Schema arrays, `wp_register_ability()` boilerplate
on the right hook, and raw adapter filters — none of it in the framework's idiom. Consuming
plugins (Kirki, Kirki Ecommerce, GrowFund) need to expose MCP servers, and Laravel MCP has
already shown what the class-based API should feel like.

## What Changes

- Add a self-contained MCP subsystem under `src/` (`Ahamed\FrameworkMcp\`), built so it can
  later be extracted into its own Composer package by a move and namespace rename. It edits
  no core files and depends only on core's public API.
- Add an opt-in `McpServiceProvider` and a `Ahamed\FrameworkMcp\Facades\Mcp` facade. Consumers add
  the provider to their providers list; it is **not** a base provider.
- Add an abstract `Server` class whose protected properties (each read through an
  overridable getter) carry every `McpAdapter::create_server()` parameter: name, version,
  description, route namespace, route, transports, error handler, observability handler,
  tools, resources, prompts, and a `permission()` method for the transport permission
  callback. The route defaults to the app's REST namespace and `mcp/{server_id}`.
- Add `Mcp::server('id', ServerClass::class)->middleware([...])` registration from any
  service provider, any number of servers. The framework hooks `mcp_adapter_init` once and
  instantiates every registered server there. Middleware uses the framework's existing
  middleware aliases (`auth`, `throttle:60,1`, …) and runs before WordPress's permission
  check so a rejection keeps its status code and headers.
- Add `Mcp::get('id')`, `Mcp::servers()`, and `Mcp::category()` for custom ability
  categories; the app gets a default category registered automatically.
- Add abstract `Tool`, `Resource` and `Prompt` classes. Each registers
  a real WordPress Ability: `handle(Request $request)` becomes the `execute_callback`,
  `permission(Request $request)` the `permission_callback` (default
  `current_user_can('read')`), `schema(JsonSchema $schema)` the `input_schema`, and
  `output_schema(JsonSchema $schema)` the `output_schema`. Prompts declare `arguments()`
  with `Argument` objects. Annotations, title, description, URI, MIME type and `_meta` are
  properties. Ability names derive from the app prefix and class name.
- Add `should_register(Request $request)` per-request conditional visibility, enforced
  through the adapter's list and pre-call filters for framework servers only.
- Add container resolution and method dependency injection for servers and abilities.
- Add `Ahamed\FrameworkMcp\Request` (an arguments bag with framework validation and the current
  user) and `Ahamed\FrameworkMcp\Response` (`text`, `error`, `structured`, `image`, `blob`,
  `embedded`, `as_assistant`, `as_user`, `with_meta`).
- Add a fluent `JsonSchema` builder (`string`, `integer`, `number`, `boolean`, `array`,
  `object` with modifiers) returning Laravel-shaped property maps.
- Add a `LogErrorHandler` routing adapter diagnostics through the framework `Log` facade.
  Exceptions thrown from `handle()` are logged and reach the client as a generic message
  unless they are validation errors, sub-500 HTTP exceptions, or `WP_DEBUG` is on.
- Add server-scoped hook methods on `Server` (`initialize_response`, `before_call`,
  `after_call`) attached to the adapter's global filters only for that server's requests.
- Add `make:mcp-server`, `make:mcp-tool`, `make:mcp-resource`, `make:mcp-prompt` commands.
- Add `docs/mcp.md`, including a "Where this differs from Laravel" section.
- Not included (documented): resource templates, elicitation / input-required tool results,
  audio content, multiple content blocks per tool result, streaming and notifications,
  `Mcp::local()`, `mcp:inspector`, PHP attributes, and test-assertion helpers (deferred to a
  follow-up change).

## Capabilities

### New Capabilities

- `mcp-servers`: Defining MCP servers as classes, registering them through the facade,
  creating them on the adapter's init hook, route and transport configuration, transport
  permission, HTTP middleware, server lookup, categories, server-scoped hooks, and
  behaviour when the adapter is absent.
- `mcp-abilities`: Tool, resource and prompt classes registered as WordPress Abilities —
  naming, metadata and annotations, exposure flags, permission, conditional visibility,
  dependency injection, the MCP request and response objects, and exception handling.
- `mcp-json-schema`: The fluent JSON Schema builder used to declare ability input and
  output schemas.
- `mcp-generators`: WP-CLI generator commands for servers, tools, resources and prompts.

### Modified Capabilities

None.

## Impact

- **New code**: `src/**` (provider, facade, registrar, `Server`, ability base classes,
  `Request`, `Response`, `JsonSchema`, `Argument`, `LogErrorHandler`, console commands,
  stubs); `tests/Unit/**`; `tests/Support/**`; `docs/mcp.md`.
- **Config**: `composer.json` gains a `suggest` entry for `wordpress/mcp-adapter`;
  `phpcs.xml.dist` excludes `src/Console/stubs/`.
- **Test stubs**: `tests/Support/StubsWordPressFunctions.php` gains stubs for the Abilities
  API functions the subsystem calls.
- **Runtime dependency**: the canonical `mcp-adapter` plugin and WordPress 6.9+. Without
  them the subsystem registers nothing and never errors.
- **Scoping**: `WP\MCP\…` and `WP_Ability` references stay unprefixed under the existing
  `scoper.config.php` (every non-`Framework` namespace is excluded), so scoped consumers
  share the one adapter plugin.
- **No breaking changes**: purely additive; no existing public API changes.
