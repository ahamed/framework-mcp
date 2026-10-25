## Context

See proposal.md for motivation; the behaviour contract is in `specs/mcp-servers`,
`specs/mcp-abilities`, `specs/mcp-json-schema`, and `specs/mcp-generators`.

The design sits between three moving parts, all verified against source rather than docs:

- **WordPress Abilities API** (core 6.9+). `wp_register_ability()` is only valid during
  `wp_abilities_api_init`; categories only during `wp_abilities_api_categories_init`, which
  fires first. `WP_Ability::execute()` runs `normalize_input → validate_input →
  check_permissions → execute_callback → validate_output`. `check_permissions()` alone does
  **not** validate input. The execute callback receives `$input` only when `input_schema` is
  non-empty, and a non-null input against an empty schema is an error. `label`, `description`
  and `category` are required non-empty strings. `meta.show_in_rest`, when unset, inherits
  `meta.public`.
- **WordPress MCP Adapter 0.7.1** (canonical plugin). `McpAdapter::create_server()` takes 13
  positional arguments and is only valid during `mcp_adapter_init`, which fires on
  `rest_api_init:15` (or `init:20` under WP-CLI). Ability-backed tools produce exactly one
  content block: JSON text plus `structuredContent` for arrays, an image for
  `['type' => 'image', 'results' => bytes]`, an embedded resource for `['type' => 'resource', …]`,
  and an error result for `WP_Error`. The HTTP transport casts a `WP_Error` or any exception
  from `transport_permission_callback` to plain `false`. Its throwable guard returns the raw
  exception message to the client. `resources/templates/list` always answers empty.
  Since 0.7.0 bundling the adapter as a library is deprecated.
- **This framework**. Route middleware runs inside the REST permission callback through
  protected `Route` internals. `Route::get_middleware_aliases()` is the only public seam.
  `DependencyResolvable` resolves method parameters from the container. `ApiExceptionHandler`
  hides messages of 5xx exceptions unless `WP_DEBUG`. The scoper config leaves every
  non-`Framework` namespace unprefixed.

## Goals / Non-Goals

**Goals:**

- One self-contained directory, `src/`, that can be lifted into its own package with a
  move and a namespace rename: no edits to core classes, only core's public API.
- Every `create_server()` parameter reachable from a `Server` subclass, and every adapter
  extension point that has a per-server meaning reachable from a method on it.
- Correct HTTP semantics for middleware rejections (status + headers).
- Unit-testable without WordPress or the adapter installed.

**Non-Goals:**

- Direct (non-ability) adapter components: `McpTool::fromArray()`, prompt builders, and with
  them MRTR / elicitation.
- Resource templates, audio, multi-block tool results, streaming/notifications — the adapter
  cannot express them for ability-backed components.
- Test-assertion helpers for consumers (follow-up change).
- Managing the adapter's site-wide switches (`mcp_adapter_create_default_server`, session
  limits). They affect every plugin on the site; docs point at the raw filters.

## Decisions

### D1. Layout: everything under `Ahamed\FrameworkMcp`

```
src/
  McpServiceProvider.php     registers McpManager singleton, 'mcp' alias, commands, hooks
  McpManager.php             facade target: server(), get(), servers(), category()
  PendingServer.php          returned by Mcp::server(); ->middleware()
  Server.php                 abstract base, create_server() parameters + scoped hooks
  Ability.php                abstract base for the three component kinds
  Tool.php  Resource.php  Prompt.php
  Argument.php               prompt argument value object
  Request.php  Response.php
  JsonSchema/JsonSchema.php  JsonSchema/Types/{Type,StringType,IntegerType,NumberType,
                                              BooleanType,ArrayType,ObjectType}.php
  Registrar/AbilityRegistrar.php    class → wp_register_ability() args, execute wrapper
  Registrar/ServerFactory.php       Server → create_server() call
  Registrar/MiddlewareRunner.php    rest_request_before_callbacks pipeline
  Registrar/ScopedHooks.php         adapter filter → Server method dispatch, should_register
  Handlers/LogErrorHandler.php      McpErrorHandlerInterface → Log facade
  Facades/Mcp.php
  Console/Make{Server,Tool,Resource,Prompt}Command.php
  Console/stubs/mcp-{server,tool,resource,prompt}.stub
```

The facade lives in `Mcp/Facades/` instead of `Supports/Facades/`, and the stubs in
`Mcp/Console/stubs/` instead of `Console/stubs/`. This deliberately breaks the repo's
placement rules, which assume a monolith. When extracted, the package moves this directory
unchanged.

*Alternative considered:* follow the repo layout now and reorganize on extraction. Rejected:
the extraction would then touch core files and a facade's FQCN that consumers import.

### D2. Adapter is a soft runtime dependency

`composer.json` gets `"suggest": {"wordpress/mcp-adapter": "…"}` only. All work happens in
callbacks on `wp_abilities_api_categories_init`, `wp_abilities_api_init`, and
`mcp_adapter_init`. None of these fire without WordPress 6.9+ and the plugin, so an absent
adapter costs three `add_action` calls and never references a missing class. `WP\MCP\…`
symbols are referenced only inside those callbacks, or as `::class` constants (which don't
autoload).

*Alternative:* `require` it. Rejected: it triggers the adapter's 0.7 bundling deprecation, and
two plugins bundling different copies is exactly what the canonical plugin exists to prevent.

### D3. Hook timeline

```
plugins_loaded … provider boot()      Mcp::server()/Mcp::category() collect into McpManager
wp_abilities_api_categories_init      default app category + declared categories
wp_abilities_api_init                 every distinct component class across all servers
                                      → wp_register_ability() once
mcp_adapter_init (rest_api_init:15)   per server: ServerFactory → $adapter->create_server()
rest_api_init (after :15)             adapter transports register REST routes
REST request                          rest_request_before_callbacks → MiddlewareRunner
                                      → adapter permission → ScopedHooks → ability
```

`Mcp::server()` / `Mcp::category()` throw a `LogicException` once
`did_action('wp_abilities_api_init')` or `did_action('mcp_adapter_init')` is true, because a
late server would silently never exist. Server and component **instances** are resolved from
the container at `wp_abilities_api_init`. Components cannot be resolved later, because their
name, title, schema and annotations come from instance getters and are needed to register
the ability. The resolved instance is then reused by the ability callbacks. Keep component
constructors cheap; anything expensive belongs in a dependency type-hinted on `handle()`.

`wp_abilities_api_init` fires with or without the adapter, so on WordPress 6.9+ framework
abilities exist even when the adapter is not active. They stay private unless they opt into
`$public` / `$show_in_rest`, so this is harmless, and `$show_in_rest` abilities keep working.

The default app category is registered only when at least one server is registered and the
application has a prefix. Without a prefix, every ability must declare `$name` and
`$category`, and resolving a derived name throws a `LogicException` that says so.

### D4. Server: properties read through getters

`Server` declares protected properties with defaults:

| Property | Default | `create_server()` arg |
|---|---|---|
| `$name` | humanized class basename | `server_name` |
| `$version` | `app()->version()` | `server_version` |
| `$description` | `''` (falls back to `$name`) | `server_description` (= MCP `instructions`) |
| `$route_namespace` | `null` → `Route::get_namespace()` | `server_route_namespace` |
| `$route` | `null` → `'mcp/' . $id` | `server_route` |
| `$transports` | `[HttpTransport::class]` | `mcp_transports` |
| `$error_handler` | `LogErrorHandler::class` | `error_handler` |
| `$observability_handler` | `NullMcpObservabilityHandler::class` | `observability_handler` |
| `$tools` / `$resources` / `$prompts` | `[]` | resolved ability names |

Each one has a snake_case getter (`name()`, `version()`, `route_namespace()`, …) that the
factory calls. The server id is injected by the manager (`set_id()` / `id()`) because the id
belongs to the registration, not the class. `permission()` is not declared on the base at
all: the factory passes a closure only when `method_exists($server, 'permission')`. Otherwise
it passes `null`, which keeps the adapter's filterable `read` capability default. The closure
injects the framework `Http\Request` and the `WP_REST_Request`, plus container-resolved
dependencies. `rest_route()` composes `/{namespace}/{route}` for middleware matching.

`$description` falls back to `$name` because the adapter's `server_description` cannot be
empty in practice: it is the `instructions` string clients show.

The `HttpTransport::class` / `NullMcpObservabilityHandler::class` defaults are `::class`
constants. These are compile-time strings, so declaring them as property defaults does not
autoload the adapter.

### D5. Middleware runs in `rest_request_before_callbacks`

WordPress core applies `rest_request_before_callbacks` before `permission_callback`, and
skips the permission and endpoint callbacks when the filter returns a `WP_Error`. The error
reaches the client with its own `status`, and `rest_post_dispatch` still runs. The existing
`RateLimiterServiceProvider` header filter therefore keeps working.

`MiddlewareRunner` registers one filter. It matches `$request->get_route()` against the
`/{namespace}/{route}` of each server that has middleware, builds a framework
`Http\Request` via `Request::from_wp_rest_request()`, and runs the pipeline. Each reference is
resolved against `Route::get_middleware_aliases()` with the same `name:arg1,arg2` grammar.
This is a ~25-line duplicate of `Route::parse_middleware()`, kept because that method is
protected and D1 forbids editing `Route`. `HttpException` and `AuthorizationException` map to
`WP_Error` exactly as `Route::compute_permission_result()` does. An unresolvable reference
(`InvalidMiddlewareException`) is logged and answered as a 500 `WP_Error`, not left to fatal.

It is memoized per `WP_REST_Request`, for the same double-invocation reason `Route` documents.

*Alternative:* fold middleware into `transport_permission_callback`. Rejected: the adapter
flattens every rejection to `false`, turning a 429 with `Retry-After` into a bare 401/403.

*Extraction note:* when the package ships, `Route` can gain a public middleware-resolution
seam and `MiddlewareRunner` can drop its copy. This is additive and not required now.

### D6. Components are real abilities

`AbilityRegistrar` turns each class into `wp_register_ability($name, $args)`:

- `label` = `title()`, `description` = `description()` (falls back to the title, because
  core rejects empty descriptions), `category` = `category()`.
- Tools: `input_schema` = `JsonSchema::object_from_map($this->schema(...))`. This is always
  at least `['type' => 'object']`, so the execute callback always receives the input array.
  That avoids core's "input against empty schema" error when a client sends `{}`.
- Resources: **no** `input_schema`. The adapter executes resource abilities with no
  arguments (`$ability->execute()`), and a non-empty schema would make core reject that
  null input. `handle()` receives an empty request.
- `output_schema` only when `output_schema()` returns a non-empty map.
- Prompts: `input_schema` built from `arguments()` as string properties, `required` listing
  required ones. The adapter derives MCP prompt arguments (name, description, required) back
  from it.
- `meta`:
  - Tools: `meta.annotations` with the hints mapped `read_only → readonly`, `destructive`,
    `idempotent`, plus `openWorldHint`. Resources: `meta.mcp.annotations` with `audience`,
    `priority`, `lastModified`, the key the adapter prefers for resources. In both cases
    `annotations()` is merged over the top and `null` values are dropped. Prompts have no
    annotation properties: MCP prompt objects carry none, and the adapter drops them.
  - `public` = `$public`, and `show_in_rest` = `$show_in_rest`, always passed explicitly.
    Core otherwise inherits `show_in_rest` from `public`, which would tie the two
    exposure flags together.
  - `mcp`: `public` = `$public`, `type` = `tool|resource|prompt`, and for resources
    `uri`, `mimeType`. `_meta` = `$meta`.
- `execute_callback` / `permission_callback`: closures over the class name (D7).

Naming: `$name` if set. Otherwise `Ability::kebab(app()->prefix())` + `/` + the kebab of the
class basename with a trailing `Tool|Resource|Prompt` stripped. `Str::snake()` does not split
camel case, so `Ability::kebab()` does that splitting itself. A registry keyed by name detects
collisions across classes and throws `LogicException` naming both.

*Alternative:* `McpTool::fromArray()` direct components. Rejected in grilling: no core
validation, invisible to other Abilities consumers. Its one unique feature (MRTR) is
documented as a gap.

### D7. Execute wrapper: Request, DI, Response mapping, exceptions

`Registrar\AbilityInvoker` runs the callbacks against the instance resolved at registration
(D3). It wraps `$input` in `Ahamed\FrameworkMcp\Request`, and calls the method through
`DependencyResolvable`, so extra type-hinted parameters are injected.

`handle()`, `permission()` and `should_register()` are **not declared** on `Ability`. They
are looked up with `method_exists()`, because PHP rejects an override that adds required
parameters: declaring `handle(Request $request)` on the base would forbid
`handle(Request $request, WeatherRepository $weather)` in a subclass. A missing `handle()`
is reported as a logged error. A missing `permission()` falls back to
`current_user_can('read')`, and a missing `should_register()` to visible.

`Registrar\ResponseMapper` owns the result shapes in the table below.

`handle()` output is normalized for the adapter:

| `handle()` returns | Tools receive | Resources | Prompts |
|---|---|---|---|
| `Response::text($s)` | `$s` (adapter wraps as `{"result": $s}`) | `['text' => $s, 'mimeType' => …]` | message, role user |
| `Response::structured($a)` | `$a` | JSON text | — |
| `Response::error($m)` | `WP_Error('mcp_tool_error', $m)` | `WP_Error` | `WP_Error` |
| `Response::image($b, $mime)` | `['type' => 'image', 'results' => $b, 'mimeType' => $mime]` | blob content | image message content |
| `Response::blob($b, $mime)` | embedded resource, base64 | `['blob' => base64, 'mimeType' => …]` | — |
| `Response::embedded($uri, …)` | `['type' => 'resource', 'resource' => […]]` | content with that URI | resource message content |
| list of Responses | — (rejected: one block only) | list of contents | list of messages |
| string / array / `WP_Error` | passed through | passed through | passed through |

`as_assistant()` / `as_user()` set a role used only by prompts. `with_meta()` attaches `_meta`
where the adapter carries it: content `_meta` for resources and images, and the embedded
block's `_meta`. Structured tool results cannot carry `_meta` in the adapter, so there it is
dropped, and the docs say so.

Exceptions: the wrapper catches `Throwable` first, before WordPress (which would return
`ability_callback_exception`) and the adapter guard (which echoes `getMessage()`), and logs it
via `Log::error()`. It returns `WP_Error` with the real message only for
`ValidationException` (first error per field, joined), for `HttpException` with status
< 500, or under `WP_DEBUG`. Otherwise it returns "The request could not be completed." This
mirrors `ApiExceptionHandler`.

### D8. `should_register()` and server-scoped hooks share one dispatcher

`ScopedHooks` adds the adapter filters once:

- `mcp_adapter_{tools,resources,prompts}_list` (receives `McpServer`)
- `mcp_adapter_pre_{tool_call,resource_read,prompt_get}`
- `mcp_adapter_{tool_call,resource_read,prompt_get}_result`
- `mcp_adapter_initialize_response`

Each handler maps the adapter `McpServer` to our `Server` via `get_server_id()`. It does
nothing for servers the framework did not create, so other plugins' servers are untouched.

- **List filters** drop records whose component's `should_register(Request)` returns false.
  The Request holds no arguments, only the current user. Records map back to classes by MCP
  name (sanitized ability name) for tools and prompts, and by URI for resources.
- **Pre filters** first apply `should_register()` (false → `WP_Error`, so the adapter answers
  with an error result and the ability never runs), then call `Server::before_call($kind, $name,
  $args)`. That returns args, or a `WP_Error` to reject.
- **Result filters** call `Server::after_call($kind, $name, $result)`.
- **Initialize** calls `Server::initialize_response(array $response)`.

`should_register()` results are memoized per request and component.

*Alternative:* evaluate `should_register()` once at server creation. Rejected in grilling:
the current user is not final at `rest_api_init` for cookie-authenticated requests.

### D9. JsonSchema

`JsonSchema` is a factory (`string()`, `integer()`, `number()`, `boolean()`, `array()`,
`object()`). Each `Type` is a fluent fragment with `description, title, default, enum,
nullable, required, min, max, format, pattern, items, properties, additional_properties` and
`to_array()`. `min`/`max` map per type (`minLength`/`minimum`/`minItems`). `required` is
kept on the fragment and read by the enclosing object or the top-level map. `nullable`
serializes as a `["type", "null"]` list. Reserved words such as `array` are legal method
names since PHP 7.0, so `$schema->array()` works on 7.4. `JsonSchema::object_from_map()` rejects non-`Type` values
with an `InvalidArgumentException` naming the key (grilling: no raw-array escape hatch).

### D10. Error handler

`LogErrorHandler::log($message, $context, $type)` forwards to `Log::error|warning|info`
by `$type`, with the context JSON-encoded onto the message. The framework `Log` facade takes
one string. It lives in `Ahamed\FrameworkMcp\Handlers` and is the default `$error_handler`.

### D11. Generators

Four commands extending `CommandBase` that override `stub_path()` to `__DIR__ . '/stubs'`
and write to `app_path('Mcp/<Kind>/<Class>.php')` with
`app()->qualify_app_namespace('Mcp\\<Kind>')`, following `MakeJobCommand`. The provider
registers them with `Command::register()` in `boot()`. `phpcs.xml.dist` gains an
exclude-pattern for `src/Console/stubs/` to match the existing `src/Console/stubs/`.

### D12. Testing without WordPress

- Abilities functions (`wp_register_ability`, `wp_register_ability_category`, `did_action`
  if missing) are added to `tests/Support/StubsWordPressFunctions.php` behind
  `function_exists`. They record calls into a static test registry.
- `tests/Support/` holds test-only doubles declared in the `WP\MCP\…` namespaces:
  `Core\McpAdapter` (records `create_server()` arguments, can be told to return `WP_Error`),
  `Core\McpServer` (`get_server_id()`), and `Transport\HttpTransport` plus the observability
  / error interfaces. They are defined only when the real classes don't exist, which they
  never do in this test process.
- Hook firing reuses the existing `framework_test_do_action()` helper.

## Risks / Trade-offs

- [Adapter API churn: 0.7 already broke 0.6 integrations] → All adapter coupling lives in
  `Registrar/*` and the three handler/transport class constants. The docs state the tested
  adapter version (0.7.1).
- [`Response::text()` on a tool reaches clients as `{"result":"…"}`] → Documented, and
  `structured()` recommended for tools. This can't be fixed without direct components.
- [Duplicated middleware parsing can drift from `Route`] → A unit test asserts that both
  resolve the same alias/argument fixtures. A public `Route` seam is noted for extraction
  time (D5).
- [List-record → class mapping relies on the adapter's name sanitization] → Abilities get
  names that are already MCP-safe (lowercase, `[a-z0-9-]`, one `/` → `-`), so sanitization is
  only the slash replacement, which is stable across adapter versions. Mapping is by
  computed MCP name, with a fallback match on `ability_name` when the record exposes it.
- [Scoped copies in several plugins each register hooks] → Each copy only acts on servers it
  created (D8), and ability names are app-prefixed (D6). Server ids are documented as
  site-global and should be plugin-prefixed.
- [`Mcp::server()` after the hooks fired throws in production] → It's a programming error
  that would otherwise silently drop a server. The message names the hook and says to
  register from a provider's `boot()`.

## Migration Plan

Additive. Consumers opt in by adding `Ahamed\FrameworkMcp\McpServiceProvider` to their providers
and installing the MCP Adapter plugin. Rollback is removing the provider.

## Implementation Notes

- The open question on the default server version was resolved during implementation.
  `Application::version()` exists, so servers default to the application version, which is
  itself `1.0.0` unless the app sets one.
- The four generators share `Console\MakeComponentCommand`. Each subclass declares only its
  directory, stub and wording.
- A hidden component's pre-call rejection uses the `mcp_component_unavailable` error code.
  The adapter reports it as an error result for tools and as an internal error for
  resources and prompts.
- `Response::blob()` takes an optional URI, since outside a resource the adapter can only
  carry binary content as an embedded resource.
