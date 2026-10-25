<?php
/**
 * Holds every MCP server registration and drives them through the WordPress lifecycle.
 * Registration only records intent; categories, abilities, and adapter servers are created on
 * their own hooks because WordPress rejects each of them outside that hook.
 *
 * @package FrameworkMcp
 * @since   1.0.0
 */
namespace Ahamed\FrameworkMcp;

defined('ABSPATH') || exit;

use Ahamed\FrameworkMcp\Registrar\AbilityRegistrar;
use Ahamed\FrameworkMcp\Registrar\ServerFactory;
use Framework\Supports\Facades\Log;
use InvalidArgumentException;
use LogicException;
use Throwable;
use WP_Error;

use function Framework\app;

class McpManager
{
    /**
     * The Abilities API hook categories are registered on.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const CATEGORIES_HOOK = 'wp_abilities_api_categories_init';

    /**
     * The Abilities API hook abilities are registered on.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const ABILITIES_HOOK = 'wp_abilities_api_init';

    /**
     * The adapter hook servers are created on.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public const ADAPTER_HOOK = 'mcp_adapter_init';

    /**
     * Registers component classes as abilities.
     *
     * @var AbilityRegistrar
     *
     * @since 1.0.0
     */
    protected $registrar;

    /**
     * Creates servers through the adapter.
     *
     * @var ServerFactory
     *
     * @since 1.0.0
     */
    protected $factory;

    /**
     * The server registrations, keyed by identifier.
     *
     * @var array<string, PendingServer>
     *
     * @since 1.0.0
     */
    protected $pending = [];

    /**
     * The declared categories, keyed by slug.
     *
     * @var array<string, array>
     *
     * @since 1.0.0
     */
    protected $categories = [];

    /**
     * The resolved servers, keyed by identifier.
     *
     * @var array<string, Server>
     *
     * @since 1.0.0
     */
    protected $servers = [];

    /**
     * Each server's components by kind, keyed by MCP tool or prompt name and by resource URI.
     *
     * @var array<string, array<string, array<string, Ability>>>
     *
     * @since 1.0.0
     */
    protected $components = [];

    /**
     * The adapter's server instances, for the servers it created.
     *
     * @var array<string, object>
     *
     * @since 1.0.0
     */
    protected $created = [];

    /**
     * Create the manager.
     *
     * @param AbilityRegistrar $registrar Registers component classes as abilities.
     * @param ServerFactory $factory Creates servers through the adapter.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function __construct(AbilityRegistrar $registrar, ServerFactory $factory)
    {
        $this->registrar = $registrar;
        $this->factory = $factory;
    }

    /**
     * Register a server under an identifier.
     *
     * The identifier is global to the site, so prefix it with the plugin's own slug.
     *
     * @param string $id The identifier.
     * @param string $server_class The server class.
     *
     * @return PendingServer
     *
     * @throws InvalidArgumentException When the class is not a server.
     * @throws LogicException When the identifier is taken or the adapter already initialized.
     *
     * @since 1.0.0
     */
    public function server(string $id, string $server_class)
    {
        $this->guard_timing(static::ABILITIES_HOOK, 'Servers');
        $this->guard_timing(static::ADAPTER_HOOK, 'Servers');

        if (!is_subclass_of($server_class, Server::class)) {
            throw new InvalidArgumentException(
                sprintf('MCP server [%s] must extend %s.', $server_class, Server::class)
            );
        }

        if (isset($this->pending[$id])) {
            throw new LogicException(sprintf('An MCP server is already registered as [%s].', $id));
        }

        return $this->pending[$id] = new PendingServer($id, $server_class);
    }

    /**
     * Declare an ability category, registered when the Abilities API asks for categories.
     *
     * @param string $slug The category slug.
     * @param string $label The label.
     * @param string $description What the category groups.
     *
     * @return static
     *
     * @throws LogicException When categories were already registered.
     *
     * @since 1.0.0
     */
    public function category(string $slug, string $label, string $description = '')
    {
        $this->guard_timing(static::CATEGORIES_HOOK, 'Categories');

        $this->categories[$slug] = [
            'label' => $label,
            'description' => $description !== '' ? $description : $label,
        ];

        return $this;
    }

    /**
     * Get the adapter's server instance for an identifier, once the adapter created it.
     *
     * @param string $id The identifier.
     *
     * @return object|null
     *
     * @since 1.0.0
     */
    public function get(string $id)
    {
        return $this->created[$id] ?? null;
    }

    /**
     * Get the registered server classes, keyed by identifier.
     *
     * @return array<string, string>
     *
     * @since 1.0.0
     */
    public function servers()
    {
        return array_map(function (PendingServer $pending) {
            return $pending->server_class();
        }, $this->pending);
    }

    /**
     * Get the server registrations.
     *
     * @return array<string, PendingServer>
     *
     * @since 1.0.0
     */
    public function registrations()
    {
        return $this->pending;
    }

    /**
     * Get a resolved server.
     *
     * @param string $id The identifier.
     *
     * @return Server|null
     *
     * @since 1.0.0
     */
    public function instance(string $id)
    {
        return $this->servers[$id] ?? null;
    }

    /**
     * Get one of a server's components.
     *
     * @param string $id The server identifier.
     * @param string $kind The component kind: tool, resource, or prompt.
     * @param string $key The MCP tool or prompt name, or the resource URI.
     *
     * @return Ability|null
     *
     * @since 1.0.0
     */
    public function component(string $id, string $kind, string $key)
    {
        return $this->components[$id][$kind][$key] ?? null;
    }

    /**
     * Determine whether the adapter created a server from this manager.
     *
     * Servers another plugin created under any identifier are not ours to act on.
     *
     * @param string $id The identifier.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function created(string $id)
    {
        return isset($this->created[$id]);
    }

    /**
     * Register the application's default category and every declared one.
     *
     * The default is skipped when no server is registered, or when the application has no
     * prefix, in which case every ability must declare its own category.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function register_categories()
    {
        $categories = $this->categories;

        if (!empty($this->pending) && (string) app()->prefix() !== '') {
            $slug = Ability::application_slug();
            $label = ucwords(str_replace('-', ' ', $slug));

            $categories += [
                $slug => ['label' => $label, 'description' => sprintf('Abilities provided by %s.', $label)],
            ];
        }

        foreach ($categories as $slug => $category) {
            wp_register_ability_category($slug, $category);
        }
    }

    /**
     * Resolve every server and register each component it lists as an ability.
     *
     * @return void
     *
     * @throws InvalidArgumentException When a listed class has the wrong base.
     * @throws LogicException When a component is invalid or two share a name.
     *
     * @since 1.0.0
     */
    public function register_abilities()
    {
        $bases = [
            Tool::KIND => Tool::class,
            Resource::KIND => Resource::class,
            Prompt::KIND => Prompt::class,
        ];

        foreach ($this->pending as $id => $pending) {
            $server = app()->make($pending->server_class())->set_id($id);

            $lists = [
                Tool::KIND => $server->tools(),
                Resource::KIND => $server->resources(),
                Prompt::KIND => $server->prompts(),
            ];

            $this->components[$id] = [];

            foreach ($lists as $kind => $classes) {
                $this->components[$id][$kind] = [];

                foreach ($classes as $class) {
                    $component = $this->registrar->register($class, $bases[$kind]);

                    $this->components[$id][$kind][$this->key($component)] = $component;
                }
            }

            $this->servers[$id] = $server;
        }
    }

    /**
     * Create every resolved server through the adapter.
     *
     * A server the adapter rejects is logged and skipped so the others still come up.
     *
     * Servers are resolved on wp_abilities_api_init, which core fires only when something first
     * reads the abilities registry. Over REST the adapter initializes before that has happened,
     * so read the registry here to resolve the servers before creating them.
     *
     * @param object $adapter The adapter instance mcp_adapter_init passed.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function create_servers($adapter)
    {
        if (!did_action(static::ABILITIES_HOOK) && function_exists('wp_get_abilities')) {
            wp_get_abilities();
        }

        foreach ($this->servers as $id => $server) {
            try {
                $result = $this->factory->create(
                    $adapter,
                    $server,
                    $this->ability_names($id, Tool::KIND),
                    $this->ability_names($id, Resource::KIND),
                    $this->ability_names($id, Prompt::KIND)
                );
            } catch (Throwable $exception) {
                $result = new WP_Error('mcp_server_failed', $exception->getMessage());
            }

            if ($result instanceof WP_Error) {
                Log::error(sprintf('[mcp] Server [%s] was not created: %s', $id, $result->get_error_message()));

                continue;
            }

            $this->created[$id] = method_exists($adapter, 'get_server') ? $adapter->get_server($id) : null;
        }
    }

    /**
     * Get the ability names of a server's components of one kind.
     *
     * @param string $id The server identifier.
     * @param string $kind The component kind.
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    protected function ability_names(string $id, string $kind)
    {
        return array_values(array_map(function (Ability $component) {
            return $component->name();
        }, $this->components[$id][$kind] ?? []));
    }

    /**
     * Get the key the adapter identifies a component by in its requests.
     *
     * @param Ability $component The component.
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function key(Ability $component)
    {
        if ($component instanceof Resource) {
            return (string) $component->uri();
        }

        return AbilityRegistrar::mcp_name($component->name());
    }

    /**
     * Reject a registration made after the hook that consumes it has fired.
     *
     * @param string $hook The hook.
     * @param string $what What is being registered, for the message.
     *
     * @return void
     *
     * @throws LogicException When the hook has fired.
     *
     * @since 1.0.0
     */
    protected function guard_timing(string $hook, string $what)
    {
        if (did_action($hook)) {
            throw new LogicException(sprintf(
                'MCP %s must be registered before the "%s" hook fires; '
                . 'register them from a service provider\'s boot().',
                strtolower($what),
                $hook
            ));
        }
    }
}
