<?php
/**
 * A string fragment; generic bounds map to minLength and maxLength.
 *
 * @package    FrameworkMcp
 * @subpackage JsonSchema\Types
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\JsonSchema\Types;

defined('ABSPATH') || exit;

class StringType extends Type
{
    /**
     * @inheritDoc
     */
    public const TYPE = 'string';

    /**
     * @inheritDoc
     */
    public const MIN_KEYWORD = 'minLength';

    /**
     * @inheritDoc
     */
    public const MAX_KEYWORD = 'maxLength';

    /**
     * Require the value to match a regular expression.
     *
     * @param string $pattern The ECMA 262 pattern.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public function pattern(string $pattern)
    {
        return $this->set('pattern', $pattern);
    }

    /**
     * Declare a semantic format such as date-time, email, or uri.
     *
     * @param string $format The format name.
     *
     * @return static
     *
     * @since 1.0.0
     */
    public function format(string $format)
    {
        return $this->set('format', $format);
    }
}
