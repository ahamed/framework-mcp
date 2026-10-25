<?php

namespace Ahamed\FrameworkMcp\Tests\Unit\JsonSchema;

use Ahamed\FrameworkMcp\JsonSchema\JsonSchema;
use Ahamed\FrameworkMcp\Tests\Unit\TestCase;
use InvalidArgumentException;
use LogicException;

class JsonSchemaTest extends TestCase
{
    protected JsonSchema $schema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema = new JsonSchema();
    }

    public function test_each_type_serializes_to_its_keyword(): void
    {
        $this->assertSame(['type' => 'string'], $this->schema->string()->to_array());
        $this->assertSame(['type' => 'integer'], $this->schema->integer()->to_array());
        $this->assertSame(['type' => 'number'], $this->schema->number()->to_array());
        $this->assertSame(['type' => 'boolean'], $this->schema->boolean()->to_array());
        $this->assertSame(['type' => 'array'], $this->schema->array()->to_array());
        $this->assertSame(['type' => 'object'], $this->schema->object()->to_array());
    }

    public function test_nullable_described_enum(): void
    {
        $fragment = $this->schema->string()
            ->description('Units')
            ->enum(['metric', 'imperial'])
            ->nullable();

        $this->assertSame([
            'type' => ['string', 'null'],
            'description' => 'Units',
            'enum' => ['metric', 'imperial'],
        ], $fragment->to_array());
    }

    public function test_common_modifiers(): void
    {
        $fragment = $this->schema->integer()->title('Count')->default(10);

        $this->assertSame(['type' => 'integer', 'title' => 'Count', 'default' => 10], $fragment->to_array());
    }

    public function test_required_is_reported_to_the_enclosing_object_only(): void
    {
        $object = $this->schema->object([
            'location' => $this->schema->string()->required(),
            'units' => $this->schema->string(),
        ]);

        $schema = $object->to_array();

        $this->assertSame(['location'], $schema['required']);
        $this->assertSame(['type' => 'string'], $schema['properties']['location']);
    }

    public function test_generic_bounds_map_per_type(): void
    {
        $this->assertSame(['type' => 'string', 'minLength' => 1, 'maxLength' => 5], $this->schema->string()->min(1)->max(5)->to_array());
        $this->assertSame(['type' => 'integer', 'minimum' => 1], $this->schema->integer()->min(1)->to_array());
        $this->assertSame(['type' => 'number', 'maximum' => 2.5], $this->schema->number()->max(2.5)->to_array());
        $this->assertSame(['type' => 'array', 'minItems' => 1], $this->schema->array()->min(1)->to_array());
    }

    public function test_boolean_has_no_bounds(): void
    {
        $this->expectException(LogicException::class);

        $this->schema->boolean()->min(1);
    }

    public function test_string_pattern_and_format(): void
    {
        $this->assertSame(
            ['type' => 'string', 'pattern' => '^[a-z]+$', 'format' => 'email'],
            $this->schema->string()->pattern('^[a-z]+$')->format('email')->to_array()
        );
    }

    public function test_array_items(): void
    {
        $this->assertSame(
            ['type' => 'array', 'items' => ['type' => 'integer']],
            $this->schema->array()->items($this->schema->integer())->to_array()
        );
    }

    public function test_nested_object(): void
    {
        $fragment = $this->schema->object([
            'lat' => $this->schema->number()->required(),
            'lng' => $this->schema->number()->required(),
        ])->additional_properties(false);

        $this->assertSame([
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => ['lat' => ['type' => 'number'], 'lng' => ['type' => 'number']],
            'required' => ['lat', 'lng'],
        ], $fragment->to_array());
    }

    public function test_property_map_becomes_an_object_schema(): void
    {
        $schema = JsonSchema::object_from_map([
            'location' => $this->schema->string()->required(),
        ]);

        $this->assertSame([
            'type' => 'object',
            'properties' => ['location' => ['type' => 'string']],
            'required' => ['location'],
        ], $schema);
    }

    public function test_map_without_required_properties_omits_required(): void
    {
        $schema = JsonSchema::object_from_map(['units' => $this->schema->string()]);

        $this->assertArrayNotHasKey('required', $schema);
    }

    public function test_empty_map_is_a_bare_object(): void
    {
        $this->assertSame(['type' => 'object'], JsonSchema::object_from_map([]));
    }

    public function test_raw_array_in_the_map_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[location]');

        JsonSchema::object_from_map(['location' => ['type' => 'string']]);
    }
}
