<?php

/**
 * Example php-scoper config for a plugin that scopes themeum/framework.
 *
 * Copy it next to the plugin's own scoper config, set 'prefix' to the prefix
 * that config uses, and run it as a second pass:
 *
 *   vendor/bin/php-scoper add-prefix --config=scoper.framework-mcp.php --force
 *   vendor/bin/framework-mcp-prefix-stubs <output-dir>/Console/stubs <prefix>
 *
 * It is a separate pass because php-scoper writes its output relative to the
 * common parent of every file it finds: adding this package to the framework's
 * finder would move the framework's scoped tree and break its autoload mapping.
 */

use Symfony\Component\Finder\Finder;

return [
    'prefix' => 'Kirki\Ecommerce',
    'output-dir' => 'libraries/ahamed/framework-mcp',
    'finders' => [
        Finder::create()
            ->files()
            ->in(__DIR__ . '/vendor/ahamed/framework-mcp/src')
            ->name(['*.php', '*.stub']),
    ],

    // Prefix this package and the framework it calls; leave everything else,
    // the global namespace and the MCP Adapter's WP\MCP classes included, alone.
    'exclude-namespaces' => [
        '~^$~',
        '/^(?!(?:Framework|Ahamed\\\\FrameworkMcp)($|\\\\))/',
    ],
    'expose-global-classes' => true,
    'expose-global-functions' => true,
    'expose-global-constants' => true,

    // php-scoper leaves comments alone, so the facade's @method lines and other
    // docblock types would still name the unprefixed classes.
    'patchers' => [
        static function (string $file_path, string $prefix, string $contents): string {
            return preg_replace_callback(
                '/^\s*\*\s*@(?:method|property|param|return|var|see|throws)\b.*$/m',
                static function (array $line) use ($prefix): string {
                    return preg_replace(
                        '/(?<![\w\\\\])\\\\(Ahamed\\\\FrameworkMcp|Framework)\\\\/',
                        '\\\\' . addcslashes($prefix, '\\') . '\\\\$1\\\\',
                        $line[0]
                    );
                },
                $contents
            );
        },
    ],
];
