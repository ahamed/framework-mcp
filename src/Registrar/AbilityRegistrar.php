<?php
/**
 * Registers component classes as WordPress Abilities, once each.
 * A class listed by several servers is registered a single time and shared, because an ability
 * name is global to the site; two classes resolving to the same name are a conflict, not a
 * duplicate.
 *
 * @package    FrameworkMcp
 * @subpackage Registrar
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\Registrar;

defined('ABSPATH') || exit;

use Ahamed\FrameworkMcp\Ability;
use Ahamed\FrameworkMcp\JsonSchema\JsonSchema;
use Ahamed\FrameworkMcp\Prompt;
use Ahamed\FrameworkMcp\Resource;
use Ahamed\FrameworkMcp\Tool;
use InvalidArgumentException;
use LogicException;

use function Framework\app;

class AbilityRegistrar
{
    /**
     * Runs the components' methods for the ability callbacks.
     *
     * @var AbilityInvoker
     *
     * @since 1.0.0
     */
    protected $invoker;

    /**
     * The resolved component of each registered class.
     *
     * @var array<string, Ability>
     *
     * @since 1.0.0
     */
    protected $components = [];

    /**
     * The class registered under each ability name.
     *
     * @var array<string, string>
     *
     * @since 1.0.0
     */
    protected $classes = [];

    /**
     * Create the registrar.
     *
     * @param AbilityInvoker $invoker Runs the components' methods.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function __construct(AbilityInvoker $invoker)
    {
        $this->invoker = $invoker;
    }

    /**
     * Register a component class as an ability, unless it is registered already.
     *
     * @param string $class The component class.
     * @param string $base The base class the server lists it under.
     *
     * @return Ability The resolved component.
     *
     * @throws InvalidArgumentException When the class does not extend the expected base.
     * @throws LogicException When the class is invalid or its name is taken by another class.
     *
     * @since 1.0.0
     */
    public function register(string $class, string $base)
    {
        if (isset($this->components[$class])) {
            return $this->components[$class];
        }

        if (!is_subclass_of($class, $base)) {
            throw new InvalidArgumentException(sprintf('MCP component [%s] must extend %s.', $class, $base));
        }

        $component = app()->make($class);
        $name = $component->name();

        if (isset($this->classes[$name])) {
            throw new LogicException(sprintf(
                'MCP components [%s] and [%s] both resolve to the ability name [%s]; declare $name on one of them.',
                $this->classes[$name],
                $class,
                $name
            ));
        }

        wp_register_ability($name, $this->arguments($component));

        $this->components[$class] = $component;
        $this->classes[$name] = $class;

        return $component;
    }

    /**
     * Get the registered component of a class.
     *
     * @param string $class The component class.
     *
     * @return Ability|null
     *
     * @since 1.0.0
     */
    public function component(string $class)
    {
        return $this->components[$class] ?? null;
    }

    /**
     * Get the name MCP clients see for an ability: the adapter replaces the slash with a hyphen.
     *
     * @param string $ability_name The ability name.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public static function mcp_name(string $ability_name)
    {
        return str_replace('/', '-', $ability_name);
    }

    /**
     * Build the wp_register_ability() arguments for a component.
     *
     * @param Ability $component The component.
     *
     * @return array
     *
     * @throws LogicException When a resource declares no URI.
     *
     * @since 1.0.0
     */
    public function arguments(Ability $component)
    {
        $arguments = [
            'label' => $component->title(),
            'description' => $component->description(),
            'category' => $component->category(),
            'execute_callback' => function ($input = null) use ($component) {
                return $this->invoker->execute($component, $input);
            },
            'permission_callback' => function ($input = null) use ($component) {
                return $this->invoker->permission($component, $input);
            },
            'meta' => $this->meta($component),
        ];

        if ($component instanceof Tool) {
            $arguments['input_schema'] = JsonSchema::object_from_map($component->schema(new JsonSchema()));

            $output = $component->output_schema(new JsonSchema());

            if (!empty($output)) {
                $arguments['output_schema'] = JsonSchema::object_from_map($output);
            }
        }

        if ($component instanceof Prompt) {
            $arguments['input_schema'] = $this->prompt_schema($component);
        }

        return $arguments;
    }

    /**
     * Build the ability meta.
     *
     * Both exposure flags are always written: core would otherwise copy show_in_rest from
     * public, tying REST exposure to MCP exposure.
     *
     * @param Ability $component The component.
     *
     * @return array
     *
     * @throws LogicException When a resource declares no URI.
     *
     * @since 1.0.0
     */
    protected function meta(Ability $component)
    {
        $annotations = $this->without_nulls(array_merge($component->hints(), $component->annotations()));

        $mcp = [
            'public' => $component->is_public(),
            'type' => $component::KIND,
        ];

        $meta = [
            'public' => $component->is_public(),
            'show_in_rest' => $component->shows_in_rest(),
        ];

        if ($component instanceof Resource) {
            if (empty($component->uri())) {
                throw new LogicException(sprintf('MCP resource [%s] must declare a $uri.', get_class($component)));
            }

            $mcp['uri'] = $component->uri();
            $mcp = array_merge($mcp, $this->without_nulls(['mimeType' => $component->mime_type()]));

            if (!empty($annotations)) {
                $mcp['annotations'] = $annotations;
            }
        } elseif (!empty($annotations)) {
            $meta['annotations'] = $annotations;
        }

        if (!empty($component->meta())) {
            $mcp['_meta'] = $component->meta();
        }

        $meta['mcp'] = $mcp;

        return $meta;
    }

    /**
     * Build a prompt's input schema from its arguments, all strings since MCP prompt arguments are.
     *
     * The adapter derives the prompt's advertised arguments back from this schema.
     *
     * @param Prompt $component The prompt.
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function prompt_schema(Prompt $component)
    {
        $schema = new JsonSchema();
        $properties = [];

        foreach ($component->arguments() as $argument) {
            $property = $schema->string()->required($argument->is_required());

            if ($argument->description() !== '') {
                $property->description($argument->description());
            }

            $properties[$argument->name()] = $property;
        }

        return JsonSchema::object_from_map($properties);
    }

    /**
     * Drop null entries.
     *
     * @param array $values The values.
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function without_nulls(array $values)
    {
        return array_filter($values, function ($value) {
            return $value !== null;
        });
    }
}
