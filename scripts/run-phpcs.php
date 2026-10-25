<?php

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

$tool = 'phpcs';
$args = array_slice($_SERVER['argv'], 1);

if (isset($args[0]) && $args[0] === 'phpcbf') {
    $tool = 'phpcbf';
    array_shift($args);
}

$argv = array_merge([$tool], $args);
$has_standard = false;

foreach ($argv as $arg) {
    if (strpos($arg, '--standard=') === 0 || $arg === '--standard') {
        $has_standard = true;
        break;
    }
}

if (!$has_standard) {
    $argv[] = '--standard=' . dirname(__DIR__) . '/phpcs.xml.dist';
}

$_SERVER['argv'] = $argv;
$_SERVER['argc'] = count($argv);

require dirname(__DIR__) . '/vendor/squizlabs/php_codesniffer/bin/' . $tool;
