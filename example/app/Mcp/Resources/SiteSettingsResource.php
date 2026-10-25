<?php
/**
 * A read-only MCP resource with the site's reading and locale settings, used to exercise
 * resources/list and resources/read end to end.
 */
namespace Example\App\Mcp\Resources;

defined('ABSPATH') || exit;

use Ahamed\FrameworkMcp\Request;
use Ahamed\FrameworkMcp\Resource;
use Ahamed\FrameworkMcp\Response;

class SiteSettingsResource extends Resource
{
    /**
     * What the resource holds, shown to the client.
     *
     * @var string
     */
    protected $description = 'The site title, tagline, language, timezone and reading settings.';

    /**
     * The URI clients read the resource by.
     *
     * @var string
     */
    protected $uri = 'framework-mcp-example://site/settings';

    /**
     * The resource's content type.
     *
     * @var string
     */
    protected $mime_type = 'application/json';

    /**
     * The resource is context for the model.
     *
     * @var string[]
     */
    protected $audience = ['assistant'];

    /**
     * Decide whether the current user may read the resource.
     *
     * @param Request $request The read request.
     *
     * @return bool
     */
    public function permission(Request $request)
    {
        return current_user_can('read');
    }

    /**
     * Read the resource.
     *
     * @param Request $request The read request; resources take no arguments.
     *
     * @return Response
     */
    public function handle(Request $request)
    {
        return Response::text((string) wp_json_encode([
            'title' => get_bloginfo('name'),
            'tagline' => get_bloginfo('description'),
            'language' => get_locale(),
            'timezone' => wp_timezone_string(),
            'front_page' => get_option('show_on_front'),
            'posts_per_page' => (int) get_option('posts_per_page'),
        ]));
    }
}
