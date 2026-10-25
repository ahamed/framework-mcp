<?php
/**
 * Base fragment of the fluent JSON Schema builder.
 * Requiredness is held here but never serialized into the fragment itself: JSON Schema states
 * it on the enclosing object, so the object reads it back when it lists its properties.
 *
 * @package    FrameworkMcp
 * @subpackage JsonSchema\Types
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\JsonSchema\Types;

defined('ABSPATH') || exit;

use LogicException;

abstract class Type
{
    /**
     * The JSON Schema type keyword this fragment serializes to.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const TYPE = '';

    /**
     * The keyword a generic minimum maps to for this type, or an empty string when unsupported.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const MIN_KEYWORD = '';

    /**
     * The keyword a generic maximum maps to for this type, or an empty string when unsupported.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const MAX_KEYWORD = '';

    /**
     * The schema keywords set on this fragment, in the order they were declared.
     *
     * @var array
     *
     * @since 1.0.0
     */
    protected $attributes = [];

    /**
     * Whether the enclosing object must list this property as required.
     *
     * @var bool
     *
     * @since 1.0.0
     */
    protected $required = false;

    /**
     * Whether null is accepted alongside the fragment's own type.
     *
     * @var bool
     *
     * @since 1.0.0
     */
    protected $nullable = false;

    /**
     * Describe the value.
     *
     * @param string $description The description.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public function description(string $description)
    {
        return $this->set('description', $description);
    }

    /**
     * Give the value a short title.
     *
     * @param string $title The title.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public function title(string $title)
    {
        return $this->set('title', $title);
    }

    /**
     * Set the value used when none is supplied.
     *
     * @param mixed $value The default value.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public function default($value)
    {
        return $this->set('default', $value);
    }

    /**
     * Limit the value to a fixed set.
     *
     * @param array $values The allowed values.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public function enum(array $values)
    {
        return $this->set('enum', array_values($values));
    }

    /**
     * Accept null alongside the fragment's own type.
     *
     * @param bool $nullable Whether null is accepted.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public function nullable(bool $nullable = true)
    {
        $this->nullable = $nullable;

        return $this;
    }

    /**
     * Mark the value as required by its enclosing object.
     *
     * @param bool $required Whether the value is required.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public function required(bool $required = true)
    {
        $this->required = $required;

        return $this;
    }

    /**
     * Determine whether the enclosing object must list this value as required.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function is_required()
    {
        return $this->required;
    }

    /**
     * Set the lower bound, mapped to the keyword this type uses for one.
     *
     * @param int|float $value The bound.
     *
     * @return static
     *
     * @throws LogicException When the type has no lower bound.
     *
     * @since 1.0.0
     */
    public function min($value)
    {
        return $this->set($this->bound_keyword(static::MIN_KEYWORD, 'minimum'), $value);
    }

    /**
     * Set the upper bound, mapped to the keyword this type uses for one.
     *
     * @param int|float $value The bound.
     *
     * @return static
     *
     * @throws LogicException When the type has no upper bound.
     *
     * @since 1.0.0
     */
    public function max($value)
    {
        return $this->set($this->bound_keyword(static::MAX_KEYWORD, 'maximum'), $value);
    }

    /**
     * Serialize the fragment to a JSON Schema array.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function to_array()
    {
        return array_merge(
            ['type' => $this->nullable ? [static::TYPE, 'null'] : static::TYPE],
            $this->attributes
        );
    }

    /**
     * Set a schema keyword.
     *
     * @param string $keyword The keyword.
     * @param mixed $value The value.
     *
     * @return static
     *
     * @since 1.0.0
     */
    protected function set(string $keyword, $value)
    {
        $this->attributes[$keyword] = $value;

        return $this;
    }

    /**
     * Resolve the keyword a generic bound maps to, rejecting types that have none.
     *
     * @param string $keyword The keyword declared by the type.
     * @param string $bound The bound being set, for the error message.
     *
     * @return string
     *
     * @throws LogicException When the type has no such bound.
     *
     * @since 1.0.0
     */
    protected function bound_keyword(string $keyword, string $bound)
    {
        if ($keyword === '') {
            throw new LogicException(sprintf('A %s schema has no %s.', static::TYPE, $bound));
        }

        return $keyword;
    }
}
