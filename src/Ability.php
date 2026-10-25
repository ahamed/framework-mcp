<?php
/**
 * Base for the classes a server lists as its tools, resources, and prompts.
 * Each becomes one WordPress Ability, so core validates its input and checks its permission
 * no matter which channel calls it.
 *
 * The handle(), permission(), and should_register() methods are looked up by name rather than
 * declared here: a subclass may type-hint extra dependencies after the request, which PHP would reject
 * as an incompatible override of a declared method. A subclass must define handle(); the
 * other two fall back to requiring the read capability and to always being visible.
 *
 * @package FrameworkMcp
 * @since   1.0.0
 */
namespace Ahamed\FrameworkMcp;

defined('ABSPATH') || exit;

use LogicException;

use function Framework\app;

abstract class Ability
{
    /**
     * The MCP component type, written to the ability's meta.mcp.type.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const KIND = '';

    /**
     * The class name suffix dropped when deriving a name, such as Tool in CurrentWeatherTool.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const SUFFIX = '';

    /**
     * The ability name, as namespace/name. Derived from the app prefix and class when null.
     *
     * @var string|null
     *
     * @since 1.0.0
     */
    protected $name;

    /**
     * The human readable title. Derived from the class name when null.
     *
     * @var string|null
     *
     * @since 1.0.0
     */
    protected $title;

    /**
     * What the component does, shown to the model. Falls back to the title when empty.
     *
     * @var string|null
     *
     * @since 1.0.0
     */
    protected $description;

    /**
     * The ability category slug. The application's default category when null.
     *
     * @var string|null
     *
     * @since 1.0.0
     */
    protected $category;

    /**
     * MCP metadata sent to clients as the component's _meta.
     *
     * @var array
     *
     * @since 1.0.0
     */
    protected $meta = [];

    /**
     * Whether the ability is offered to other clients, including the adapter's default server.
     *
     * @var bool
     *
     * @since 1.0.0
     */
    protected $public = false;

    /**
     * Whether the ability is offered through the core Abilities REST API.
     *
     * @var bool
     *
     * @since 1.0.0
     */
    protected $show_in_rest = false;

    /**
     * Get the ability name.
     *
     * @return string
     *
     * @throws LogicException When no name is declared and the application has no prefix.
     *
     * @since 1.0.0
     */
    public function name()
    {
        if (!empty($this->name)) {
            return $this->name;
        }

        return static::application_slug() . '/' . $this->class_slug();
    }

    /**
     * Get the human readable title.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function title()
    {
        if (!empty($this->title)) {
            return $this->title;
        }

        return ucwords(str_replace('-', ' ', $this->class_slug()));
    }

    /**
     * Get the description, falling back to the title because core rejects an empty one.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function description()
    {
        return !empty($this->description) ? $this->description : $this->title();
    }

    /**
     * Get the ability category slug.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function category()
    {
        return !empty($this->category) ? $this->category : static::application_slug();
    }

    /**
     * Get the MCP metadata.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function meta()
    {
        return $this->meta;
    }

    /**
     * Determine whether the ability is offered to other clients.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function is_public()
    {
        return $this->public;
    }

    /**
     * Determine whether the ability is offered through the core Abilities REST API.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function shows_in_rest()
    {
        return $this->show_in_rest;
    }

    /**
     * Extra raw annotations, merged over the ones the declared properties produce.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function annotations()
    {
        return [];
    }

    /**
     * The annotations the declared properties produce, in the adapter's keys.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function hints()
    {
        return [];
    }

    /**
     * The application prefix as a kebab case slug: the default ability namespace and category.
     *
     * @return string
     *
     * @throws LogicException When the application has no prefix.
     *
     * @since 1.0.0
     */
    public static function application_slug()
    {
        $slug = static::kebab((string) app()->prefix());

        if ($slug === '') {
            throw new LogicException(
                'MCP abilities need a namespace: set the application prefix with use_prefix(), '
                . 'or declare $name and $category on the ability.'
            );
        }

        return $slug;
    }

    /**
     * Convert a class name or identifier to a kebab case slug of lowercase letters, digits,
     * and single hyphens, which is the only shape an ability name segment accepts.
     *
     * @param string $value The value to convert.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public static function kebab(string $value)
    {
        $value = preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $value);
        $value = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value));

        return trim(preg_replace('/-+/', '-', $value), '-');
    }

    /**
     * The class short name without its component suffix, as a kebab case slug.
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function class_slug()
    {
        $basename = substr(strrchr('\\' . static::class, '\\'), 1);

        $suffix = static::SUFFIX;

        if ($suffix !== '' && $basename !== $suffix && substr($basename, -strlen($suffix)) === $suffix) {
            $basename = substr($basename, 0, -strlen($suffix));
        }

        return static::kebab($basename);
    }
}
