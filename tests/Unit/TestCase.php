<?php

namespace Ahamed\FrameworkMcp\Tests\Unit;

use Framework\Application;
use Framework\Container;
use Framework\Facade;
use Framework\Route;
use Framework\Routing\CurrentRoute;
use Framework\Supports\Str;
use Framework\Wordpress\Constants\HookNames;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        $this->reset_container_instance();
        $this->reset_str_macros();
        $this->reset_route_state();
        $this->reset_facade_cache();

        parent::tearDown();
    }

    protected static function package_path(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function bootstrap_application(): Application
    {
        $this->reset_container_instance();

        return Application::get_instance(self::package_path());
    }

    protected function reset_container_instance(): void
    {
        $this->set_container_instance(null);
    }

    protected function set_container_instance(?Container $container): void
    {
        $reflection = new \ReflectionClass(Container::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, $container);
    }

    protected function reset_str_macros(): void
    {
        $reflection = new \ReflectionClass(Str::class);
        $property = $reflection->getProperty('macros');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    protected function reset_facade_cache(): void
    {
        $reflection = new \ReflectionClass(Facade::class);
        $property = $reflection->getProperty('resolved_instance');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    protected function reset_route_state(): void
    {
        CurrentRoute::reset();

        $reflection = new \ReflectionClass(Route::class);

        $defaults = [
            'namespace' => '',
            'site_namespace' => '',
            'routing_method' => Route::ROUTING_REWRITE_RULES,
            'default_hook_name' => HookNames::TEMPLATE_INCLUDE,
            'routes' => [],
            'named_routes' => [],
            'group_stack' => [],
            'instances' => [],
            'site_router' => null,
            'with_site_route' => false,
        ];

        foreach ($defaults as $property_name => $value) {
            if (!$reflection->hasProperty($property_name)) {
                continue;
            }

            $property = $reflection->getProperty($property_name);
            $property->setAccessible(true);
            $property->setValue(null, $value);
        }
    }
}
