<?php

/**
 * Bootstrap file handed to Pest when it runs the generated tests on the
 * fixture project.
 *
 * The package autoloader resolves the framework classes, the closure below
 * resolves the fixture ones, which are not registered in composer.json.
 */

require __DIR__ . '/../../../vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'ArchTestsFixture\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
