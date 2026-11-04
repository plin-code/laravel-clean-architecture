<?php

/**
 * Autoload file handed to phparkitect when checking an app migrated by
 * clean-arch:install --migrate-app, standing in for `composer dump-autoload`.
 *
 * CLEAN_ARCH_APP_PATH points at the migrated app/ directory. Inertia and
 * Fortify are not dependencies of the package, so the parent class and the
 * trait the reflection based rules load with the infrastructure classes get
 * empty stand-ins.
 */

namespace {
    require __DIR__ . '/../../vendor/autoload.php';
}

namespace Inertia {
    if (! class_exists(Middleware::class)) {
        class Middleware {}
    }
}

namespace Laravel\Fortify {
    if (! trait_exists(InteractsWithTwoFactorState::class)) {
        trait InteractsWithTwoFactorState {}
    }
}

namespace {
    spl_autoload_register(static function (string $class): void {
        if (! str_starts_with($class, 'App\\')) {
            return;
        }

        $path = getenv('CLEAN_ARCH_APP_PATH') . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';

        if (is_file($path)) {
            require $path;
        }
    });
}
