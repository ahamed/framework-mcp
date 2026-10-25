<?php
/**
 * Facade for the MCP manager.
 * Lives beside the MCP subsystem rather than in Supports\Facades so the subsystem can move into
 * its own package without consumers changing their imports twice.
 *
 * @package    FrameworkMcp
 * @subpackage Facades
 * @since      1.0.0
 */
namespace Ahamed\FrameworkMcp\Facades;

defined('ABSPATH') || exit;

use Framework\Facade;

/**
 * The MCP manager.
 *
 * phpcs:disable Generic.Files.LineLength.TooLong
 *
 * @method static \Ahamed\FrameworkMcp\PendingServer server(string $id, string $server_class)
 * @method static \Ahamed\FrameworkMcp\McpManager category(string $slug, string $label, string $description = '')
 * @method static object|null get(string $id)
 * @method static array servers()
 * @method static array registrations()
 * @method static \Ahamed\FrameworkMcp\Server|null instance(string $id)
 * @method static \Ahamed\FrameworkMcp\Ability|null component(string $id, string $kind, string $key)
 * @method static bool created(string $id)
 * @method static void register_categories()
 * @method static void register_abilities()
 * @method static void create_servers(object $adapter)
 *
 * phpcs:enable Generic.Files.LineLength.TooLong
 */
class Mcp extends Facade
{
    /**
     * Get the accessor.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public static function get_accessor()
    {
        return 'mcp';
    }
}
