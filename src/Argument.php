<?php
/**
 * One argument a prompt accepts.
 * MCP prompt arguments are always strings, so an argument carries only a name, a description,
 * and whether it is required.
 *
 * @package FrameworkMcp
 * @since   1.0.0
 */
namespace Ahamed\FrameworkMcp;

defined('ABSPATH') || exit;

class Argument
{
    /**
     * The argument name.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $name;

    /**
     * What the argument is for, shown to the client.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $description;

    /**
     * Whether the client must supply the argument.
     *
     * @var bool
     *
     * @since 1.0.0
     */
    protected $required;

    /**
     * Create a prompt argument.
     *
     * @param string $name The argument name.
     * @param string $description What the argument is for.
     * @param bool $required Whether the client must supply it.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function __construct(string $name, string $description = '', bool $required = false)
    {
        $this->name = $name;
        $this->description = $description;
        $this->required = $required;
    }

    /**
     * Get the argument name.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Get the argument description.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function description()
    {
        return $this->description;
    }

    /**
     * Determine whether the client must supply the argument.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function is_required()
    {
        return $this->required;
    }
}
