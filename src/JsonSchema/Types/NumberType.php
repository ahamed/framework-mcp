<?php
/**
 * A number fragment; generic bounds map to minimum and maximum.
 *
 * @package    FrameworkMcp
 * @subpackage JsonSchema\Types
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\JsonSchema\Types;

defined('ABSPATH') || exit;

class NumberType extends Type
{
    /**
     * @inheritDoc
     */
    public const TYPE = 'number';

    /**
     * @inheritDoc
     */
    public const MIN_KEYWORD = 'minimum';

    /**
     * @inheritDoc
     */
    public const MAX_KEYWORD = 'maximum';
}
