<?php
/**
 * Base for MCP tools: actions a model can call with arguments.
 * Behaviour hints are declared as nullable properties so an undeclared hint is left out of the
 * tool listing instead of being sent as false, which a client would read as a claim.
 *
 * @package FrameworkMcp
 * @since   1.0.0
 */
namespace Ahamed\FrameworkMcp;

defined('ABSPATH') || exit;

use Ahamed\FrameworkMcp\JsonSchema\JsonSchema;

abstract class Tool extends Ability
{
    /**
     * @inheritDoc
     */
    public const KIND = 'tool';

    /**
     * @inheritDoc
     */
    public const SUFFIX = 'Tool';

    /**
     * Whether the tool leaves its environment unchanged.
     *
     * @var bool|null
     *
     * @since 1.0.0
     */
    protected $read_only;

    /**
     * Whether the tool may destroy or overwrite data.
     *
     * @var bool|null
     *
     * @since 1.0.0
     */
    protected $destructive;

    /**
     * Whether repeating a call with the same arguments has no further effect.
     *
     * @var bool|null
     *
     * @since 1.0.0
     */
    protected $idempotent;

    /**
     * Whether the tool reaches entities outside the site, such as a remote API.
     *
     * @var bool|null
     *
     * @since 1.0.0
     */
    protected $open_world;

    /**
     * Declare the tool's arguments as a map of names to schema fragments.
     *
     * @param JsonSchema $schema The schema builder.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function schema(JsonSchema $schema)
    {
        return [];
    }

    /**
     * Declare the shape of the tool's structured result as a map of names to schema fragments.
     *
     * @param JsonSchema $schema The schema builder.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function output_schema(JsonSchema $schema)
    {
        return [];
    }

    /**
     * Get whether the tool leaves its environment unchanged.
     *
     * @return bool|null
     *
     * @since 1.0.0
     */
    public function read_only()
    {
        return $this->read_only;
    }

    /**
     * Get whether the tool may destroy or overwrite data.
     *
     * @return bool|null
     *
     * @since 1.0.0
     */
    public function destructive()
    {
        return $this->destructive;
    }

    /**
     * Get whether repeating a call has no further effect.
     *
     * @return bool|null
     *
     * @since 1.0.0
     */
    public function idempotent()
    {
        return $this->idempotent;
    }

    /**
     * Get whether the tool reaches entities outside the site.
     *
     * @return bool|null
     *
     * @since 1.0.0
     */
    public function open_world()
    {
        return $this->open_world;
    }

    /**
     * @inheritDoc
     */
    public function hints()
    {
        return [
            'readonly' => $this->read_only(),
            'destructive' => $this->destructive(),
            'idempotent' => $this->idempotent(),
            'openWorldHint' => $this->open_world(),
        ];
    }
}
