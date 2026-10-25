## Purpose

Lets a consuming plugin write MCP tools, resources, and prompts as small classes that the
framework registers as WordPress Abilities, so they get core's schema validation and
permission model while reading like Laravel MCP components.

## ADDED Requirements

### Requirement: Components are registered as WordPress Abilities

Every tool, resource, and prompt class listed by at least one registered server SHALL be
registered exactly once as a WordPress Ability on the Abilities API init hook, before any
server is created. The ability's execute callback SHALL invoke the class's `handle()`
method, and its permission callback SHALL invoke the class's `permission()` method. A class
listed by several servers SHALL be registered once and SHALL be served by all of them.

#### Scenario: Tool listed by two servers

- **GIVEN** one tool class listed in the tools of two servers
- **WHEN** the Abilities API init hook fires
- **THEN** exactly one ability is registered for it, and both servers expose it

#### Scenario: Wrong base class

- **WHEN** a server lists a class under tools that does not extend the tool base class
- **THEN** registration of that server fails with an exception naming the class and the expected base

### Requirement: Ability naming

An ability's name SHALL be the class's declared `$name` when present. Otherwise it SHALL be
`{application prefix in kebab case}/{class short name in kebab case}`, with a trailing
`Tool`, `Resource`, or `Prompt` suffix removed. Two classes resolving to the same ability
name SHALL be rejected.

#### Scenario: Derived name

- **GIVEN** an application with prefix `kirki` and a class `CurrentWeatherTool`
- **WHEN** the ability is registered
- **THEN** its name is `kirki/current-weather`, and MCP clients see it as `kirki-current-weather`

#### Scenario: Declared name

- **GIVEN** a class declaring `$name = 'shop/order-lookup'`
- **WHEN** the ability is registered
- **THEN** its name is `shop/order-lookup`

#### Scenario: Name collision

- **WHEN** two different classes resolve to the same ability name
- **THEN** registration fails with an exception naming both classes

### Requirement: Descriptive metadata and annotations

Every component SHALL support a title, a description, a category, and MCP `_meta`. When the
title is absent it SHALL be derived from the class name; when the description is absent it
SHALL fall back to the title. When the category is absent the application's default
category SHALL be used.

Tools SHALL support read-only, destructive, idempotent, and open-world hints. Resources
SHALL require a URI and SHALL support a MIME type, audience, priority, and last-modified
timestamp. Prompts SHALL carry no prompt-level annotations, because MCP prompt objects have
none; message-level metadata SHALL be attachable to returned messages instead. Every
component SHALL allow additional raw annotations to be merged over the declared ones.
Undeclared hints SHALL be omitted, not sent as `false`.

#### Scenario: Read-only tool

- **GIVEN** a tool declaring itself read-only and idempotent
- **WHEN** a client lists the server's tools
- **THEN** the tool carries `readOnlyHint: true` and `idempotentHint: true`, and no `destructiveHint`

#### Scenario: Resource without URI

- **WHEN** a resource class with no URI is registered
- **THEN** registration fails with an exception naming the class

### Requirement: Exposure is private by default

A framework-registered ability SHALL be reachable only through the servers that list it,
unless the class opts in. Opting into public SHALL expose it to the adapter's default
server and other Abilities API clients. Opting into REST SHALL expose it through the core
Abilities REST API.

#### Scenario: Default exposure

- **GIVEN** a tool with no exposure flags
- **WHEN** a client uses the adapter's default server to discover abilities
- **THEN** the tool is not listed, and the core Abilities REST API does not list it either

#### Scenario: Opted into public

- **GIVEN** a tool declaring itself public
- **WHEN** a client uses the adapter's default server to discover abilities
- **THEN** the tool is listed

### Requirement: Component permission

`permission(Request $request)` SHALL decide whether the current user may run the component
with the given arguments and SHALL accept a `bool` or `WP_Error` result. A component that
does not override it SHALL require the `read` capability.

#### Scenario: Default permission denies anonymous

- **GIVEN** a tool that does not override `permission()` and is exposed through REST
- **WHEN** an anonymous visitor executes it through the core Abilities REST API
- **THEN** execution is denied

#### Scenario: Custom permission uses arguments

- **GIVEN** a tool whose permission requires the user to be able to edit the post named in its `post_id` argument
- **WHEN** a user calls it for a post they cannot edit
- **THEN** the call is denied and `handle()` does not run

### Requirement: Per-request conditional visibility

`should_register(Request $request)` SHALL be evaluated for each MCP request handled by a
framework server, with the current user available and no tool arguments. A component for
which it returns `false` SHALL be omitted from that server's list response and SHALL be
rejected when called, read, or fetched in that request. A component that does not override
it SHALL always be visible.

#### Scenario: Hidden from non-admins

- **GIVEN** a tool whose `should_register()` returns true only for administrators
- **WHEN** an editor lists the server's tools
- **THEN** the tool is absent from the list

#### Scenario: Hidden component cannot be called

- **GIVEN** the same tool and an editor who knows its name
- **WHEN** the editor calls it directly
- **THEN** the call fails with an error result and `handle()` does not run

### Requirement: Dependency injection

Servers and components SHALL be instantiated through the application container, so their
constructors MAY declare resolvable dependencies. `handle()`, `permission()`, and
`should_register()` MAY declare type-hinted parameters after the request; those SHALL be
resolved from the container on each call.

#### Scenario: Method injection

- **GIVEN** a tool whose `handle()` declares a repository parameter after the request
- **WHEN** the tool is called
- **THEN** `handle()` receives the container-resolved repository

### Requirement: MCP request object

`handle()`, `permission()`, and `should_register()` SHALL receive an MCP request object
holding the call's arguments and giving access to the current user. It SHALL offer reading
all arguments, a single argument with a default, presence checks, subsets, and typed reads
(string, integer, boolean). It SHALL validate the arguments against framework validation
rules and return the validated subset.

#### Scenario: Validation failure

- **GIVEN** a tool whose `handle()` validates that `location` is required
- **WHEN** the tool is called without `location`
- **THEN** the client receives a tool error carrying the validation message, and the rest of `handle()` does not run

#### Scenario: Reading arguments

- **WHEN** a tool is called with `{"units": "metric"}` and reads `units` with default `imperial`
- **THEN** it reads `metric`

### Requirement: Input schema

A tool's `schema()` SHALL receive the JSON Schema builder and return a map of argument names
to schema types; the framework SHALL register it as an object input schema with those
properties and with every property marked required listed as required. A tool that returns
an empty map SHALL get an empty object input schema. Arguments SHALL be validated against
the schema by WordPress before `handle()` runs.

#### Scenario: Required argument enforced by the schema

- **GIVEN** a tool whose schema marks `location` as a required string
- **WHEN** the tool is called with `location` set to an integer
- **THEN** the call fails with a schema validation error and `handle()` does not run

### Requirement: Output schema

A tool's `output_schema()` SHALL be declared the same way as its input schema. When it is
declared, the tool's structured result SHALL be validated against it, and a tool declaring
an output schema SHALL be expected to return structured content.

#### Scenario: Output schema advertised

- **GIVEN** a tool declaring an output schema with a numeric `temperature`
- **WHEN** a client lists the tools
- **THEN** the tool carries that output schema

### Requirement: Prompt arguments

A prompt SHALL declare its arguments as a list of argument objects, each with a name,
description, and required flag. They SHALL be advertised to clients as MCP prompt arguments
and SHALL be enforced as required string inputs.

#### Scenario: Required prompt argument missing

- **GIVEN** a prompt declaring a required `tone` argument
- **WHEN** a client fetches the prompt without `tone`
- **THEN** the client receives an error and the prompt's `handle()` does not run

### Requirement: MCP responses

`handle()` SHALL be able to return a response built as plain text, an error, structured
content, an image, a binary blob, or an embedded resource, and SHALL also be able to return
a plain string, a plain array, or a `WP_Error`. Responses SHALL accept MCP `_meta`. Prompt
responses SHALL be returnable as a list of messages, each marked as coming from the user or
the assistant; unmarked messages SHALL be from the user. Resources SHALL be able to return
several contents.

#### Scenario: Error response

- **WHEN** a tool returns an error response with message `Location not found`
- **THEN** the client receives a tool result flagged as an error with text `Location not found`

#### Scenario: Structured response

- **WHEN** a tool returns structured content `{"temperature": 22.5}`
- **THEN** the client receives that object as structured content and as JSON text

#### Scenario: Image response

- **WHEN** a tool returns an image response with PNG bytes
- **THEN** the client receives an image content block with base64 data and MIME type `image/png`

#### Scenario: Prompt messages

- **WHEN** a prompt returns an assistant message followed by an unmarked message
- **THEN** the client receives two messages with roles `assistant` then `user`

#### Scenario: Resource text

- **GIVEN** a resource with URI `weather://guidelines` and MIME type `text/markdown`
- **WHEN** a client reads it and `handle()` returns a text response
- **THEN** the client receives one content entry with that URI, MIME type, and text

### Requirement: Exception handling

An exception thrown from `handle()` SHALL be logged through the framework log. The client
SHALL receive the exception's message only when it is a validation exception, an HTTP
exception with a status below 500, or when `WP_DEBUG` is enabled; otherwise it SHALL
receive a generic message.

#### Scenario: Unexpected exception in production

- **GIVEN** `WP_DEBUG` is off
- **WHEN** a tool's `handle()` throws a runtime exception containing a database path
- **THEN** the exception is logged and the client receives a generic error message without the path

#### Scenario: Unexpected exception while debugging

- **GIVEN** `WP_DEBUG` is on
- **WHEN** the same exception is thrown
- **THEN** the client receives the exception's message
