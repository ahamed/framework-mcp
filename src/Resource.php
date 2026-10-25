<?php
/**
 * Base for MCP resources: content a client reads by URI.
 * The adapter executes a resource ability without arguments, so a resource declares no input
 * schema and its handle() receives an empty request.
 *
 * @package FrameworkMcp
 * @since   1.0.0
 */
namespace Ahamed\FrameworkMcp;

defined('ABSPATH') || exit;

abstract class Resource extends Ability
{
    /**
     * @inheritDoc
     */
    public const KIND = 'resource';

    /**
     * @inheritDoc
     */
    public const SUFFIX = 'Resource';

    /**
     * The URI clients read the resource by. Required.
     *
     * @var string|null
     *
     * @since 1.0.0
     */
    protected $uri;

    /**
     * The MIME type of the resource's content.
     *
     * @var string|null
     *
     * @since 1.0.0
     */
    protected $mime_type;

    /**
     * Who the content is meant for: user, assistant, or both.
     *
     * @var array|null
     *
     * @since 1.0.0
     */
    protected $audience;

    /**
     * Relative importance from 0.0 to 1.0.
     *
     * @var float|null
     *
     * @since 1.0.0
     */
    protected $priority;

    /**
     * When the content last changed, as an ISO 8601 timestamp.
     *
     * @var string|null
     *
     * @since 1.0.0
     */
    protected $last_modified;

    /**
     * Get the URI.
     *
     * @return string|null
     *
     * @since 1.0.0
     */
    public function uri()
    {
        return $this->uri;
    }

    /**
     * Get the MIME type.
     *
     * @return string|null
     *
     * @since 1.0.0
     */
    public function mime_type()
    {
        return $this->mime_type;
    }

    /**
     * Get the intended audience.
     *
     * @return array|null
     *
     * @since 1.0.0
     */
    public function audience()
    {
        return $this->audience;
    }

    /**
     * Get the relative importance.
     *
     * @return float|null
     *
     * @since 1.0.0
     */
    public function priority()
    {
        return $this->priority;
    }

    /**
     * Get when the content last changed.
     *
     * @return string|null
     *
     * @since 1.0.0
     */
    public function last_modified()
    {
        return $this->last_modified;
    }

    /**
     * @inheritDoc
     */
    public function hints()
    {
        return [
            'audience' => $this->audience(),
            'priority' => $this->priority(),
            'lastModified' => $this->last_modified(),
        ];
    }
}
