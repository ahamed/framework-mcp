<?php
/**
 * An object fragment, and the serializer for the property maps tools return from input_schema().
 * It is the one place that turns each property's own requiredness into the object's required
 * list, so a nested object and a top-level map follow the same rule.
 *
 * @package    FrameworkMcp
 * @subpackage JsonSchema\Types
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\JsonSchema\Types;

defined('ABSPATH') || exit;

use InvalidArgumentException;

class ObjectType extends Type
{
    /**
     * @inheritDoc
     */
    public const TYPE = 'object';

    /**
     * @inheritDoc
     */
    public const MIN_KEYWORD = 'minProperties';

    /**
     * @inheritDoc
     */
    public const MAX_KEYWORD = 'maxProperties';

    /**
     * The declared properties, keyed by name.
     *
     * @var array<string, Type>
     *
     * @since 1.0.0
     */
    protected $properties = [];

    /**
     * Declare the object's properties.
     *
     * @param array<string, Type> $properties The properties keyed by name.
     *
     * @return static
     *
     * @throws InvalidArgumentException When a property is not a schema fragment.
     *
     * @since 1.0.0
     */
    public function properties(array $properties)
    {
        foreach ($properties as $name => $property) {
            if (!$property instanceof Type) {
                throw new InvalidArgumentException(sprintf(
                    'The schema for property [%s] must be built with the JsonSchema builder, %s given.',
                    $name,
                    is_object($property) ? get_class($property) : gettype($property)
                ));
            }
        }

        $this->properties = $properties;

        return $this;
    }

    /**
     * Allow or forbid properties beyond the declared ones.
     *
     * @param bool $allowed Whether additional properties are allowed.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public function additional_properties(bool $allowed)
    {
        return $this->set('additionalProperties', $allowed);
    }

    /**
     * @inheritDoc
     */
    public function to_array()
    {
        $schema = parent::to_array();

        if (empty($this->properties)) {
            return $schema;
        }

        $required = [];

        foreach ($this->properties as $name => $property) {
            $schema['properties'][$name] = $property->to_array();

            if ($property->is_required()) {
                $required[] = $name;
            }
        }

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }
}
