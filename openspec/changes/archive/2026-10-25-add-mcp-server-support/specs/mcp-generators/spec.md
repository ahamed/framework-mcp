## Purpose

Scaffolds MCP servers, tools, resources, and prompts from WP-CLI, so a consumer starts from a
class that already satisfies the framework's file header, guard, and docblock conventions.

## ADDED Requirements

### Requirement: Generator commands

When the MCP service provider is registered, the commands `make:mcp-server`,
`make:mcp-tool`, `make:mcp-resource`, and `make:mcp-prompt` SHALL be available. Each SHALL
take a class name, normalize it to PascalCase, and write the class into the application's
`Mcp/Servers`, `Mcp/Tools`, `Mcp/Resources`, or `Mcp/Prompts` directory respectively, under
the application's namespace.

#### Scenario: Generate a tool

- **WHEN** `make:mcp-tool current_weather_tool` is run
- **THEN** a `CurrentWeatherTool` class extending the tool base class is written to the application's `Mcp/Tools` directory

#### Scenario: Provider not registered

- **WHEN** the MCP service provider is not registered
- **THEN** none of the MCP generator commands exist

### Requirement: Generated files follow framework conventions

Each generated file SHALL contain a file docblock, the application namespace, the `ABSPATH`
guard, and docblocked members for every method the component is expected to implement.

#### Scenario: Generated server

- **WHEN** `make:mcp-server WeatherServer` is run
- **THEN** the file declares a server class with name, version, description, and empty tool, resource, and prompt lists, and is guarded by `defined('ABSPATH') || exit;`

### Requirement: Existing files are not overwritten

A generator SHALL refuse to write when the target file already exists and SHALL report the
existing path.

#### Scenario: File exists

- **GIVEN** `Mcp/Tools/CurrentWeatherTool.php` already exists
- **WHEN** `make:mcp-tool CurrentWeatherTool` is run
- **THEN** the command reports an error naming the file and leaves it unchanged
