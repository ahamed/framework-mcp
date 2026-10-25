<?php
/**
 * A boolean fragment. Booleans have no bounds, so min() and max() are rejected.
 *
 * @package    FrameworkMcp
 * @subpackage JsonSchema\Types
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\JsonSchema\Types;

defined('ABSPATH') || exit;

class BooleanType extends Type
{
    /**
     * @inheritDoc
     */
    public const TYPE = 'boolean';

    /**
     * @inheritDoc
     */
    public const MIN_KEYWORD = '';

    /**
     * @inheritDoc
     */
    public const MAX_KEYWORD = '';
}
