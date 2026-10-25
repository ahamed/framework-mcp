# mcp-servers Specification

## Purpose
Lets a consuming plugin define Model Context Protocol servers as classes and register any
number of them from a service provider, with the framework creating them through the
WordPress MCP Adapter at the right moment and guarding their HTTP endpoint with framework
middleware.
## Requirements
### Requirement: The MCP subsystem is opt-in

The MCP subsystem SHALL be inactive unless the consuming application registers the MCP
service provider. Registering the provider SHALL NOT require changes to any other framework
configuration.

#### Scenario: Provider not registered

- **WHEN** an application boots without the MCP service provider
- **THEN** no MCP, Abilities API, or REST hooks are added by the MCP subsystem

#### Scenario: Provider registered

- **WHEN** an application adds the MCP service provider to its providers
- **THEN** the MCP facade, the MCP generator commands, and server registration become available

### Requirement: Servers are defined as classes

A server SHALL be a class extending the framework's base server. It SHALL declare, as
protected properties, every value the adapter needs to create a server: name, version,
description, REST route namespace, REST route, transports, error handler, observability
handler, and the lists of tool, resource, and prompt classes. Each value SHALL be read
through an overridable method, so a subclass MAY compute any value at runtime instead of
declaring the property.

The description SHALL also be the server's MCP `instructions`, because the adapter sends it
as such.

#### Scenario: Minimal server

- **GIVEN** a server class declaring only a name and a list of tool classes
- **WHEN** the server is created
- **THEN** the adapter receives the declared name and tools, the default version, the default route, HTTP transport, the framework's log-backed error handler, and the adapter's no-op observability handler

#### Scenario: Value computed at runtime

- **GIVEN** a server class that overrides the version method to return a plugin constant
- **WHEN** the server is created
- **THEN** the adapter receives the overridden version, not the property default

### Requirement: Route defaults

When a server does not declare a route namespace, the framework SHALL use the application's
REST namespace. When a server does not declare a route, the framework SHALL use
`mcp/{server_id}`, where `server_id` is the identifier the server was registered under.

#### Scenario: Default route

- **GIVEN** an application whose REST namespace is `my-plugin/v1`
- **WHEN** a server with no route properties is registered as `weather`
- **THEN** its HTTP endpoint is `/wp-json/my-plugin/v1/mcp/weather`

#### Scenario: Declared route

- **GIVEN** a server declaring route namespace `shop/v2` and route `ai`
- **WHEN** it is created
- **THEN** its HTTP endpoint is `/wp-json/shop/v2/ai`

### Requirement: STDIO-only servers

A server declaring an empty transport list SHALL be created without any HTTP endpoint, and
SHALL remain reachable through the adapter's WP-CLI STDIO bridge. Every server, with or
without HTTP transport, SHALL be reachable through that bridge under its registered
identifier.

#### Scenario: Empty transports

- **GIVEN** a server declaring an empty transport list, registered as `maintenance`
- **WHEN** a REST request targets the default route for `maintenance`
- **THEN** no such route exists, and `wp mcp-adapter serve --server=maintenance` still serves it

### Requirement: Server registration through the facade

The facade SHALL accept `Mcp::server($id, $server_class)` from any service provider, any
number of times, and SHALL return a registration object on which `middleware()` MAY be
called with a single middleware reference or a list. The framework SHALL subscribe to the
adapter's `mcp_adapter_init` action once and create every registered server there, in
registration order.

#### Scenario: Several servers

- **WHEN** two providers each register one server under distinct identifiers
- **THEN** both servers are created when `mcp_adapter_init` fires

#### Scenario: Duplicate identifier

- **WHEN** a second server is registered under an identifier that is already registered
- **THEN** registration fails with an exception naming the identifier

#### Scenario: Registration after the adapter initialized

- **WHEN** a server is registered after `mcp_adapter_init` has already fired
- **THEN** registration fails with an exception explaining that servers must be registered before the adapter initializes

#### Scenario: Class is not a server

- **WHEN** a class that does not extend the base server is registered
- **THEN** registration fails with an exception naming the class

### Requirement: Missing adapter is silent

When the MCP Adapter plugin is not active, registering servers SHALL NOT raise errors,
notices, or output; the servers SHALL simply never be created.

#### Scenario: Adapter plugin inactive

- **GIVEN** the MCP Adapter plugin is not active
- **WHEN** an application registers servers and serves a normal request
- **THEN** the request completes with no MCP-related error, notice, or output

### Requirement: Server creation failures are reported

When the adapter rejects a server, the framework SHALL log the adapter's error message
together with the server identifier, and SHALL continue creating the remaining servers.

#### Scenario: Adapter rejects one server

- **GIVEN** two registered servers, the first declaring an error handler class that does not exist
- **WHEN** the servers are created
- **THEN** a log entry names the first server and the adapter's error, and the second server is still created

### Requirement: Transport permission

A server's `permission()` method SHALL be passed to the adapter as the transport permission
callback, receiving the current request, and its `bool|WP_Error` result SHALL decide whether
the request may reach the server at all. A server that does not override `permission()`
SHALL leave the adapter's default transport check in place.

#### Scenario: Server restricts access

- **GIVEN** a server whose `permission()` requires the `manage_options` capability
- **WHEN** a logged-in subscriber sends an MCP request to it
- **THEN** the request is rejected before any tool runs

#### Scenario: Default transport check

- **GIVEN** a server that does not override `permission()`
- **WHEN** it is created
- **THEN** the adapter applies its own default capability check to the server's requests

### Requirement: HTTP middleware on a server

Middleware attached at registration SHALL accept every reference the framework router
accepts — a registered alias, an alias with arguments such as `throttle:60,1`, or a class
name — and SHALL run, in declaration order, on every HTTP request to that server's endpoint
before WordPress evaluates the route's permission. A rejection SHALL reach the client with
the status code and headers the middleware produced. Middleware SHALL NOT run for requests
to other routes, and SHALL NOT run for STDIO sessions.

#### Scenario: Throttled server

- **GIVEN** a server registered with `throttle:2,1`
- **WHEN** a client sends a third request within one minute
- **THEN** the response status is 429 and carries a `Retry-After` header

#### Scenario: Middleware scoped to its server

- **GIVEN** two servers, only the first registered with the `auth` middleware
- **WHEN** an anonymous request reaches the second server
- **THEN** the `auth` middleware does not run for it

#### Scenario: Unknown middleware

- **WHEN** a server is registered with a middleware reference that is neither an alias nor a class
- **THEN** a request to that server is answered with a server error naming the reference, the failure is logged, and the request does not reach the server

### Requirement: Server lookup

The facade SHALL return the adapter's server instance for a registered identifier once it
has been created, and `null` before creation or for an unknown identifier. It SHALL list the
registered identifiers with their server classes.

#### Scenario: Lookup after creation

- **WHEN** `Mcp::get('weather')` is called after `mcp_adapter_init`
- **THEN** it returns the adapter's server instance for `weather`

#### Scenario: Unknown identifier

- **WHEN** `Mcp::get('missing')` is called
- **THEN** it returns `null`

### Requirement: Ability categories

The framework SHALL register a default ability category for the application, whose slug is
the application prefix in kebab case, on the Abilities API categories hook. `Mcp::category($slug,
$label, $description)` SHALL declare further categories that are registered on the same
hook. Core categories `site` and `user` SHALL be usable without declaration.

#### Scenario: Default category

- **GIVEN** an application with prefix `kirki_ecommerce`
- **WHEN** the Abilities API categories hook fires
- **THEN** a category `kirki-ecommerce` is registered

#### Scenario: Custom category

- **WHEN** `Mcp::category('reports', 'Reports', 'Reporting abilities')` is called before the categories hook fires
- **THEN** a category `reports` with that label and description is registered on the hook

### Requirement: Server-scoped protocol hooks

A server SHALL be able to override methods that adjust its `initialize` response, inspect or
reject arguments before any of its tools, resources, or prompts run, and transform their
results afterwards. These methods SHALL be invoked only for requests handled by that server.
A server that does not override them SHALL leave the adapter's behaviour unchanged.

#### Scenario: Initialize response adjusted

- **GIVEN** a server overriding the initialize-response method to add a capability flag
- **WHEN** a client initializes a session with that server
- **THEN** the response carries the flag, and other servers' responses do not

#### Scenario: Call rejected before execution

- **GIVEN** a server whose before-call method returns an error for a given tool
- **WHEN** a client calls that tool on that server
- **THEN** the client receives that error as the tool result and the tool's handler does not run

