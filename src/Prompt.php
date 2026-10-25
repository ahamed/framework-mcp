<?php
/**
 * Base for MCP prompts: message templates a client fetches with string arguments.
 * MCP prompt objects carry no annotations, so audience and priority belong on the returned
 * messages, through Response::with_meta(), rather than on the prompt.
 *
 * @package FrameworkMcp
 * @since   1.0.0
 */
namespace Ahamed\FrameworkMcp;

defined('ABSPATH') || exit;

abstract class Prompt extends Ability
{
    /**
     * @inheritDoc
     */
    public const KIND = 'prompt';

    /**
     * @inheritDoc
     */
    public const SUFFIX = 'Prompt';

    /**
     * Declare the arguments the prompt accepts.
     *
     * @return Argument[]
     *
     * @since 1.0.0
     */
    public function arguments()
    {
        return [];
    }
}
