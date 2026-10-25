<?php
/**
 * An array fragment; generic bounds map to minItems and maxItems.
 *
 * @package    FrameworkMcp
 * @subpackage JsonSchema\Types
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\JsonSchema\Types;

defined('ABSPATH') || exit;

class ArrayType extends Type
{
    /**
     * @inheritDoc
     */
    public const TYPE = 'array';

    /**
     * @inheritDoc
     */
    public const MIN_KEYWORD = 'minItems';

    /**
     * @inheritDoc
     */
    public const MAX_KEYWORD = 'maxItems';

    /**
     * The schema every item must satisfy.
     *
     * @var Type|null
     *
     * @since 1.0.0
     */
    protected $items;

    /**
     * Declare the schema every item must satisfy.
     *
     * @param Type $items The item schema.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public function items(Type $items)
    {
        $this->items = $items;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function to_array()
    {
        $schema = parent::to_array();

        if ($this->items !== null) {
            $schema['items'] = $this->items->to_array();
        }

        return $schema;
    }
}
