## 1. Scaffolding and test doubles

- [x] 1.1 Add `"suggest": {"wordpress/mcp-adapter": "Required to expose framework MCP servers (install the canonical MCP Adapter plugin)."}` to `composer.json`
- [x] 1.2 Add `<exclude-pattern>src/Console/stubs/</exclude-pattern>` to `phpcs.xml.dist` beside the existing `src/Console/stubs/` exclusion
- [x] 1.3 Add Abilities API stubs (`wp_register_ability`, `wp_register_ability_category`, `did_action`, `current_user_can` if absent) to `tests/Support/StubsWordPressFunctions.php` behind `function_exists`, recording calls into a resettable static registry
- [x] 1.4 Create `tests/Support/` doubles in the adapter namespaces (`WP\MCP\Core\McpAdapter` recording `create_server()` args and optionally returning `WP_Error`, `WP\MCP\Core\McpServer::get_server_id()`, `WP\MCP\Transport\HttpTransport`, `WP\MCP\Infrastructure\ErrorHandling\Contracts\McpErrorHandlerInterface`, `WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler`), each guarded by `class_exists`/`interface_exists`, and load them from the unit bootstrap
- [x] 1.5 Create `tests/Unit/McpTestCase.php` extending `Framework\Tests\Unit\TestCase` that registers `McpServiceProvider`, resets the manager, the ability registry stub and the adapter double in `tearDown()`

## 2. JsonSchema builder

- [x] 2.1 Create `src/JsonSchema/Types/Type.php` (abstract) with `description, title, default, enum, nullable, required, is_required, to_array` and the per-type `min`/`max` mapping hook
- [x] 2.2 Create `StringType` (`min`/`max` → length, `pattern`, `format`), `IntegerType` and `NumberType` (`min`/`max` → minimum/maximum), `BooleanType`, `ArrayType` (`items`, `min`/`max` → items), `ObjectType` (`properties`, `additional_properties`, computed `required`)
- [x] 2.3 Create `src/JsonSchema/JsonSchema.php` factory (`string/integer/number/boolean/array/object`) and `object_from_map(array $map)` rejecting non-`Type` values with `InvalidArgumentException` naming the key; empty map → `['type' => 'object']`
- [x] 2.4 Unit tests in `tests/Unit/JsonSchema/` covering every scenario in `specs/mcp-json-schema/spec.md`

## 3. Request, Response, Argument

- [x] 3.1 Create `src/Request.php`: `all, get, has, only, except, string, integer, boolean, validate(array $rules), validated, user, user_id`, validating through `Framework\Validation\Validator` and throwing `ValidationException`
- [x] 3.2 Create `src/Response.php` with static constructors `text, error, structured, image, blob, embedded`, instance `as_assistant, as_user, with_meta`, and accessors the registrar uses (`kind()`, `role()`, `meta()`, payload)
- [x] 3.3 Create `src/Argument.php` (name, description, required) with getters
- [x] 3.4 Unit tests for Request reads/validation and Response construction

## 4. Ability base classes and registration

- [x] 4.1 Create `src/Ability.php` (abstract): properties `$name, $title, $description, $category, $meta, $public, $show_in_rest` with getters; `permission(Request)` defaulting to `current_user_can('read')`; `should_register(Request)` defaulting to true; `annotations()` returning `[]`; abstract `handle(Request)`; name and title derivation per design D6
- [x] 4.2 Create `src/Tool.php` with `$read_only, $destructive, $idempotent, $open_world`, `schema(JsonSchema)` and `output_schema(JsonSchema)` returning `[]` by default
- [x] 4.3 Create `src/Resource.php` with required `$uri`, `$mime_type, $audience, $priority, $last_modified`
- [x] 4.4 Create `src/Prompt.php` with `arguments()` returning `[]` (no prompt-level annotation properties: MCP prompts carry none)
- [x] 4.5 Create `src/Registrar/AbilityRegistrar.php` building `wp_register_ability()` args per design D6 (label, description fallback, category, input/output schema, prompt-argument schema, meta annotations with `null` dropped, explicit `public`/`show_in_rest`, `meta.mcp` type/uri/mimeType/`_meta`), with name-collision and wrong-base-class detection
- [x] 4.6 Implement the execute and permission wrappers per design D7: container-resolved, per-request memoized instance; `Request` wrapping; `DependencyResolvable` method injection; Response → adapter shape mapping table for tool, resource and prompt kinds; rejection of a list of Responses from a tool
- [x] 4.7 Implement the exception policy per design D7 (log via `Log::error`, expose message only for `ValidationException`, `HttpException` < 500, or `WP_DEBUG`)
- [x] 4.8 Unit tests in `tests/Unit/AbilityRegistrarTest.php` and `ExecuteWrapperTest.php` covering every scenario in `specs/mcp-abilities/spec.md` except the per-request visibility ones (task 6)

## 5. Server, manager, provider, facade

- [x] 5.1 Create `src/Server.php` (abstract) with the properties and getters from design D4, `id()`/`set_id()`, overridable `initialize_response(array)`, `before_call(string $kind, string $name, $args)`, `after_call(string $kind, string $name, $result)`, and an undefined-by-default `permission()` detected by declaring class
- [x] 5.2 Create `src/PendingServer.php` holding id, class and middleware, with `middleware($middleware)` accepting a string or list and returning `$this`
- [x] 5.3 Create `src/McpManager.php`: `server()`, `category()`, `get()`, `servers()`; validation for duplicate id, non-`Server` class, and registration after `wp_abilities_api_init`/`mcp_adapter_init` (`LogicException`)
- [x] 5.4 Create `src/Registrar/ServerFactory.php` that, inside `mcp_adapter_init`, resolves each server from the container and calls `$adapter->create_server()` with all 13 arguments, logs and skips a `WP_Error`, and stores the adapter server for `Mcp::get()`
- [x] 5.5 Create `src/Handlers/LogErrorHandler.php` implementing `McpErrorHandlerInterface` over the `Log` facade per design D10
- [x] 5.6 Create `src/McpServiceProvider.php`: singleton `McpManager`, `mcp` alias, and in `boot()` the three lifecycle hooks (categories incl. default app category, abilities, adapter init) plus the middleware and scoped-hook filters
- [x] 5.7 Create `src/Facades/Mcp.php` with `@method static` lines for every public `McpManager` method
- [x] 5.8 Unit tests in `tests/Unit/ServerRegistrationTest.php` covering the registration, defaults, route, STDIO-only, missing-adapter, creation-failure, transport-permission, lookup and category scenarios in `specs/mcp-servers/spec.md`

## 6. Middleware and scoped hooks

- [x] 6.1 Create `src/Registrar/MiddlewareRunner.php` per design D5: one `rest_request_before_callbacks` filter, route matching per server, alias/argument parsing against `Route::get_middleware_aliases()`, `HttpException`/`AuthorizationException` → `WP_Error` with status and headers, invalid middleware → logged 500, per-request memoization
- [x] 6.2 Unit test asserting `MiddlewareRunner` and `Route` resolve the same alias/argument fixtures, plus tests for the throttle, scoping and unknown-middleware scenarios
- [x] 6.3 Create `src/Registrar/ScopedHooks.php` per design D8: list, pre, result and initialize filters, mapping adapter server → framework `Server` by id and ignoring foreign servers; record → component mapping by MCP name or URI; memoized `should_register()`
- [x] 6.4 Unit tests for per-request visibility (hidden from list, blocked on call), before-call rejection, after-call transform, initialize-response adjustment, and foreign-server pass-through

## 7. Generators

- [x] 7.1 Create stubs `src/Console/stubs/mcp-{server,tool,resource,prompt}.stub` with file docblock, `{{namespace}}`, ABSPATH guard and docblocked members
- [x] 7.2 Create `src/Console/Make{Server,Tool,Resource,Prompt}Command.php` following `MakeJobCommand`, overriding `stub_path()`, writing to `app_path('Mcp/<Kind>/…')`, refusing to overwrite
- [x] 7.3 Register the four commands from `McpServiceProvider::boot()` via `Command::register()`
- [x] 7.4 Unit tests for generated output and the existing-file refusal

## 8. Documentation

- [x] 8.1 Write `docs/mcp.md` structured like `docs/cache.md`: TOC; quick start (install the adapter plugin, register the provider, a server, one tool); servers and routes; registration and middleware; tools, schemas and responses; resources; prompts; permission and visibility; categories and exposure; error handling; WP-CLI STDIO and connecting clients (MCP Inspector against the REST URL and `wp mcp-adapter serve`); generators; server-scoped hooks; tested adapter version
- [x] 8.2 Add the "Where this differs from Laravel" section: no attributes (properties instead), no `Mcp::web`/`Mcp::local` (server class owns route and transports), resource templates, elicitation, audio, multi-block results, streaming/notifications, `Response::text()` reaching clients as `{"result":…}`, `_meta` on structured tool results, OAuth/Sanctum (use Application Passwords or a server `permission()`), no `mcp:inspector`, no test helpers yet

## 9. Verification

- [x] 9.1 `composer phpcs && composer test:unit` pass
- [x] 9.2 Confirm no file outside `src/`, `tests/`, `docs/mcp.md`, `composer.json` and `phpcs.xml.dist` changed (`git status`)
- [x] 9.3 Verify end to end in the Docker playground with the MCP Adapter plugin active: `tools/list` and `tools/call` over REST via `curl`, a throttled 429, and `wp mcp-adapter serve --server=<id>` (done in the framework-mcp playground on :20300)
- [x] 9.4 Create servers when `mcp_adapter_init` fires before the abilities registry has initialized (the REST order): `create_servers()` reads the registry first so `wp_abilities_api_init` resolves the pending servers; regression test in `ServerRegistrationTest`
