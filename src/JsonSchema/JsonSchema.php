<?php
/**
 * Fluent factory handed to a tool's schema() and output_schema().
 * Mirrors Laravel's JsonSchema builder: the methods return fragments, and a tool returns a map
 * of argument names to fragments that is serialized as the properties of one object schema.
 *
 * @package    FrameworkMcp
 * @subpackage JsonSchema
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\JsonSchema;

defined('ABSPATH') || exit;

use Ahamed\FrameworkMcp\JsonSchema\Types\ArrayType;
use Ahamed\FrameworkMcp\JsonSchema\Types\BooleanType;
use Ahamed\FrameworkMcp\JsonSchema\Types\IntegerType;
use Ahamed\FrameworkMcp\JsonSchema\Types\NumberType;
use Ahamed\FrameworkMcp\JsonSchema\Types\ObjectType;
use Ahamed\FrameworkMcp\JsonSchema\Types\StringType;
use InvalidArgumentException;

class JsonSchema
{
    /**
     * Create a string fragment.
     *
     * @return StringType
     *
     * @since 1.0.0
     */
    public function string()
    {
        return new StringType();
    }

    /**
     * Create an integer fragment.
     *
     * @return IntegerType
     *
     * @since 1.0.0
     */
    public function integer()
    {
        return new IntegerType();
    }

    /**
     * Create a number fragment.
     *
     * @return NumberType
     *
     * @since 1.0.0
     */
    public function number()
    {
        return new NumberType();
    }

    /**
     * Create a boolean fragment.
     *
     * @return BooleanType
     *
     * @since 1.0.0
     */
    public function boolean()
    {
        return new BooleanType();
    }

    /**
     * Create an array fragment.
     *
     * @return ArrayType
     *
     * @since 1.0.0
     */
    public function array()
    {
        return new ArrayType();
    }

    /**
     * Create an object fragment, optionally with its properties.
     *
     * @param array $properties The properties keyed by name.
     *
     * @return ObjectType
     *
     * @throws InvalidArgumentException When a property is not a schema fragment.
     *
     * @since 1.0.0
     */
    public function object(array $properties = [])
    {
        return (new ObjectType())->properties($properties);
    }

    /**
     * Serialize a property map to an object schema.
     *
     * An empty map yields the bare object schema, which MCP clients read as "no arguments".
     *
     * @param array $properties The properties keyed by name.
     *
     * @return array
     *
     * @throws InvalidArgumentException When a property is not a schema fragment.
     *
     * @since 1.0.0
     */
    public static function object_from_map(array $properties)
    {
        return (new ObjectType())->properties($properties)->to_array();
    }
}
