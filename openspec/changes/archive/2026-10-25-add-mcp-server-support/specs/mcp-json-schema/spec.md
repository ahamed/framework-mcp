## Purpose

Provides a fluent builder for JSON Schema fragments so MCP tool inputs and outputs can be
declared as readable PHP rather than nested arrays, in the shape Laravel MCP uses.

## ADDED Requirements

### Requirement: Schema types

The builder SHALL create schema fragments of type string, integer, number, boolean, array,
and object, and each fragment SHALL serialize to the corresponding JSON Schema array.

#### Scenario: String fragment

- **WHEN** a string fragment is created and serialized
- **THEN** the result is `{"type": "string"}`

### Requirement: Common modifiers

Every fragment SHALL support a description, a title, a default value, an enumeration of
allowed values, nullability, and being marked required. Nullability SHALL serialize as a type
list including `null`. Marking a fragment required SHALL NOT appear inside the fragment
itself; it SHALL be reported to the enclosing object so that the object lists the property
under `required`.

#### Scenario: Nullable described enum

- **WHEN** a string fragment is described as `Units`, limited to `metric` and `imperial`, and made nullable
- **THEN** it serializes to a type of `["string", "null"]` with that description and enumeration

#### Scenario: Required property

- **GIVEN** an object with a required `location` string and an optional `units` string
- **WHEN** it is serialized
- **THEN** `required` contains `location` only

### Requirement: Type-specific constraints

String fragments SHALL support minimum and maximum length, a pattern, and a format. Integer
and number fragments SHALL support minimum and maximum values. Array fragments SHALL support
an item schema and minimum and maximum item counts. Object fragments SHALL support nested
properties and whether additional properties are allowed. A generic minimum or maximum SHALL
serialize to the keyword appropriate for the fragment's type.

#### Scenario: Minimum on different types

- **WHEN** `min(1)` is applied to a string, an integer, and an array fragment
- **THEN** they serialize with `minLength: 1`, `minimum: 1`, and `minItems: 1` respectively

#### Scenario: Nested object

- **WHEN** an object fragment declares a required `lat` number and `lng` number and disallows additional properties
- **THEN** it serializes with both properties, both listed as required, and `additionalProperties: false`

### Requirement: Property maps become object schemas

A property map of names to fragments, as returned from a tool's schema method, SHALL
serialize to an object schema with those properties and a `required` list of the required
ones, omitting `required` when none are. An empty map SHALL serialize to `{"type": "object"}`.
Every value in the map SHALL be a builder fragment; any other value SHALL be rejected.

#### Scenario: Empty map

- **WHEN** an empty property map is serialized
- **THEN** the result is `{"type": "object"}`

#### Scenario: Raw array in the map

- **WHEN** a property map contains a raw array instead of a fragment
- **THEN** serialization fails with an exception naming the property
