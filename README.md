# framework-mcp

[Model Context Protocol](https://modelcontextprotocol.io) servers for WordPress plugins built on [`themeum/framework`](https://github.com/themeum/framework).

The API follows [Laravel MCP](https://laravel.com/docs/mcp). Servers, tools, resources and prompts are small classes. Each tool, resource and prompt is registered as a WordPress Ability, and each server is created through the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin.

```php
class SiteInfoTool extends Tool
{
    protected $description = 'Report the site name and WordPress version.';

    protected $read_only = true;

    public function handle(Request $request)
    {
        return Response::structured([
            'name' => get_bloginfo('name'),
            'wordpress_version' => get_bloginfo('version'),
        ]);
    }
}
```

The full guide is in [docs/mcp.md](docs/mcp.md).

## Requirements

- PHP 7.4+
- WordPress 6.9+, for the Abilities API
- The [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin installed and active on the site (tested with 0.7.1)
- `themeum/framework` `^3.2.7`

## Installation

`themeum/framework` is not on Packagist. A plugin that uses it already lists its GitHub repository, and Composer uses that same entry to resolve this package's dependency on the framework:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/themeum/framework.git" }
    ]
}
```

```bash
composer require ahamed/framework-mcp
```

Register the provider in `bootstrap/providers.php`:

```php
return [
    \Ahamed\FrameworkMcp\McpServiceProvider::class,
    // ...
];
```

Then register a server from any service provider's `boot()`:

```php
use Ahamed\FrameworkMcp\Facades\Mcp;

Mcp::server('my-plugin', MyServer::class)->middleware('throttle:30,1');
```

## Using with php-scoper

Plugins usually ship the framework scoped, so `Framework\Route` becomes, say, `Kirki\Ecommerce\Framework\Route`. This package calls the framework, so it must be scoped **with the same prefix**. Otherwise its `Framework\...` references point at classes that don't exist in the scoped build. Its own `Ahamed\FrameworkMcp\` namespace gets the prefix too, so two plugins can bundle different versions without colliding. The MCP Adapter's `WP\MCP\` classes come from a shared plugin and must stay unprefixed.

Run it as a **second scoper pass** with its own config, not as an extra finder in the framework's config. php-scoper writes its output relative to the common parent of all the files it finds, so adding this package to the framework's finder would move the framework's scoped tree.

1. Copy [`scoper.example.php`](scoper.example.php) to your plugin as `scoper.framework-mcp.php`. Set `prefix` to the prefix your framework config uses, and `output-dir` to wherever you keep scoped libraries. The config:
   - prefixes `Framework` and `Ahamed\FrameworkMcp`, and leaves every other namespace alone, `WP\MCP` included;
   - patches docblock types, which php-scoper leaves untouched, so the facade's `@method` lines resolve.
2. Scope it, then prefix the `make:mcp-*` stub templates, which php-scoper copies without rewriting:

   ```bash
   vendor/bin/php-scoper add-prefix --config=scoper.framework-mcp.php --force
   vendor/bin/framework-mcp-prefix-stubs libraries/ahamed/framework-mcp/Console/stubs 'Kirki\Ecommerce'
   ```

3. Autoload the scoped tree the same way you autoload the scoped framework:

   ```json
   "autoload": {
       "psr-4": {
           "Kirki\\Ecommerce\\Ahamed\\FrameworkMcp\\": "libraries/ahamed/framework-mcp/"
       }
   }
   ```

4. Register the **prefixed** provider: `\Kirki\Ecommerce\Ahamed\FrameworkMcp\McpServiceProvider::class`.

`tests/Unit/ScoperRecipeTest.php` loads `scoper.example.php` and checks which namespaces get prefixed, what the patcher does and what the stub script produces. Edit the two together.

## Development

```bash
composer install
composer phpcs && composer test
```

`composer test` runs the unit suite without WordPress: `tests/Support/` stubs the WordPress functions and the MCP Adapter classes the package touches. PHPCS applies PSR-12 plus the framework's custom sniffs, copied into `phpcs/Framework`.

## Playground

`example/` is a minimal plugin that runs this package in a real WordPress, under Docker. It loads `themeum/framework` from GitHub and **symlinks this repository**, so changes under `src/` take effect on the next request. Neither package is scoped there.

```bash
cp .env.example .env
make up               # nginx + php-fpm + MariaDB, https://localhost:20300
make example-install  # composer install for example/ (links this package)
make init             # WordPress, MCP Adapter, activates the example, prints an app password
```

`make init` prints an application password for `admin` once. The example server is then at `https://localhost:20300/wp-json/mcp-example/v1/mcp/mcp-example`. It offers a `mcp-example-site-info` tool, a `framework-mcp-example://site/settings` resource and a `mcp-example-summarize-post` prompt.

To connect Claude Code or Claude Desktop, add this to the MCP settings:

```json
{
    "mcpServers": {
        "framework-mcp-example": {
            "command": "npx",
            "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
            "env": {
                "WP_API_URL": "https://localhost:20300/wp-json/mcp-example/v1/mcp/mcp-example",
                "WP_API_USERNAME": "admin",
                "WP_API_PASSWORD": "<application password from make init>",
                "NODE_TLS_REJECT_UNAUTHORIZED": "0"
            }
        }
    }
}
```

`NODE_TLS_REJECT_UNAUTHORIZED=0` is needed only because the playground uses a self-signed certificate.

Other targets: `make wp CMD="plugin list"`, `make shell`, `make logs`, `make test`, `make down`, `make clean` (also removes the volumes).

## License

GPL-2.0-or-later
