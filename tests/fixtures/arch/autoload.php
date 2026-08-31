<?php

/**
 * Autoload file handed to phparkitect when checking the fixture project.
 *
 * The package autoloader resolves the framework classes, the closure below
 * resolves the fixture ones, which are deliberately not registered in
 * composer.json so that autoload-broken.php can leave them unresolvable.
 */

require __DIR__ . '/../../../vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'ArchFixture\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path     = __DIR__ . '/app/' . $relative . '.php';

    if (is_file($path)) {
        require $path;
    }
});
