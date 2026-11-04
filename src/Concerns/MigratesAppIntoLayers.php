<?php

namespace PlinCode\LaravelCleanArchitecture\Concerns;

/**
 * Move the code Laravel and its starter kits keep in app/ into the layers.
 *
 * The using class must expose a `$files` Filesystem property and a `$force`
 * flag, and use `ResolvesArchitectureDirectories` and `MovesUserIntoDomain`,
 * whose reference rewriting this trait reuses.
 */
trait MigratesAppIntoLayers
{
    /**
     * Folders under app/ that move, with the layer and the path inside that
     * layer they move to. Missing folders are skipped.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected array $appMigrationMap = [
        'Http/Controllers' => ['infrastructure', 'Http/Controllers'],
        'Http/Middleware'  => ['infrastructure', 'Http/Middleware'],
        'Http/Requests'    => ['infrastructure', 'Http/Requests'],
        'Http/Resources'   => ['infrastructure', 'Http/Resources'],
        'Console/Commands' => ['infrastructure', 'Console/Commands'],
        'Mail'             => ['infrastructure', 'Mail'],
        'Notifications'    => ['infrastructure', 'Notifications'],
        'Observers'        => ['infrastructure', 'Observers'],
        'Exports'          => ['infrastructure', 'Exports'],
        'Actions'          => ['application', 'Actions'],
        'Services'         => ['application', 'Services'],
        'Jobs'             => ['application', 'Jobs'],
        'Listeners'        => ['application', 'Listeners'],
        'Concerns'         => ['application', 'Concerns'],
    ];

    /**
     * Base classes clean-arch:install writes into the folders above, relative
     * to the layer directory. A moved file must not silently replace them.
     *
     * @var array<string, list<string>>
     */
    protected array $installedBaseClasses = [
        'infrastructure' => ['Http/Controllers/Controller.php', 'Http/Requests/BaseRequest.php'],
        'application'    => ['Actions/BaseAction.php', 'Services/BaseService.php'],
    ];

    /**
     * Frontend directories whose Wayfinder imports are rewritten. The
     * generated actions, routes and wayfinder folders are left to Wayfinder.
     *
     * @var list<string>
     */
    protected array $frontendDirectories = ['resources/js', 'resources/ts'];

    /**
     * Every file to move, source => destination, both relative to the base path.
     *
     * @var array<string, string>
     */
    protected array $appMigrationPlan = [];

    /**
     * Check, before anything is written, that no destination is taken.
     */
    protected function canMigrateApp(): bool
    {
        $this->appMigrationPlan = [];
        $conflicts              = [];

        foreach ($this->appMigrationMap as $source => [$layer, $target]) {
            if (! $this->files->isDirectory(app_path($source))) {
                continue;
            }

            foreach ($this->files->allFiles(app_path($source)) as $file) {
                $from = $this->relativeToBase($file->getPathname());
                $to   = $this->layerDirectory($layer) . "/{$target}/{$file->getRelativePathname()}";

                if ($source === 'Http/Controllers' && $file->getRelativePathname() === 'Controller.php' && $this->isDefaultBaseController($file->getContents())) {
                    continue;
                }

                $installed = in_array("{$target}/{$file->getRelativePathname()}", $this->installedBaseClasses[$layer] ?? [], true);

                if (($installed || $this->files->exists(base_path($to))) && ! $this->force) {
                    $conflicts[] = "{$from} to {$to}";
                }

                $this->appMigrationPlan[$from] = $to;
            }
        }

        if ($conflicts === []) {
            return true;
        }

        $this->error('These files would replace a file of the layers. Nothing was changed:');

        foreach ($conflicts as $conflict) {
            $this->line("  {$conflict}");
        }

        $this->line('Rename or merge them by hand, or run again with --force to replace the destination with the app file.');

        return false;
    }

    protected function migrateApp(): void
    {
        $namespaces = [];

        foreach ($this->appMigrationMap as $source => [$layer, $target]) {
            if ($this->files->isDirectory(app_path($source))) {
                $namespaces[$this->appNamespace() . str_replace('/', '\\', $source)] = $this->layerNamespace($layer) . str_replace('/', '\\', $target);
            }
        }

        if ($namespaces === []) {
            $this->info('Nothing to migrate: none of the folders the layers replace exists in app/');

            return;
        }

        $this->dropDefaultBaseController();

        foreach ($this->appMigrationPlan as $from => $to) {
            $this->files->ensureDirectoryExists(dirname(base_path($to)));
            $this->files->move(base_path($from), base_path($to));
            $this->info("Moved: {$from} to {$to}");
        }

        $updated = array_merge(
            $this->replaceReferences($this->namespacePatterns($namespaces)),
            $this->registerMovedDiscoveryPaths(),
        );

        foreach (array_unique($updated) as $path) {
            $this->info("Updated: {$path}");
        }

        $this->rewriteWayfinderImports($namespaces);
        $this->removeEmptyAppDirectories();
    }

    /**
     * The User model moves with the rest of the app only when it is the only
     * model. Next to other models it stays, like them: moving one model of
     * many splits a namespace whose classes name each other without imports.
     */
    protected function shouldMoveUserWithApp(): bool
    {
        if (! $this->files->exists($this->userModelSource())) {
            return false;
        }

        $others = array_filter($this->files->allFiles(app_path('Models')), fn ($file): bool => $file->getRelativePathname() !== 'User.php');

        if ($others !== []) {
            $this->warn('Kept: app/Models, it holds other models besides User.php. Models are not moved, read "Migrating an existing app into the layers" in the README.');

            return false;
        }

        return true;
    }

    /**
     * Laravel 11 and later ship an empty abstract `Controller`. The base
     * controller written by clean-arch:install replaces it, and the namespace
     * rewrite points its subclasses there.
     */
    protected function dropDefaultBaseController(): void
    {
        $path = app_path('Http/Controllers/Controller.php');

        if (! $this->files->exists($path) || ! $this->isDefaultBaseController($this->files->get($path))) {
            return;
        }

        $this->files->delete($path);
        $this->info('Removed: app/Http/Controllers/Controller.php, the default empty base controller, replaced by ' . $this->layerDirectory('infrastructure') . '/Http/Controllers/Controller.php');
    }

    protected function isDefaultBaseController(string $content): bool
    {
        return preg_match('/^<\?php\s+namespace\s+[^;]+;\s+abstract\s+class\s+Controller\s*\{\s*(\/\/)?\s*\}\s*$/', $content) === 1;
    }

    /**
     * Namespace patterns in code and as escaped inside a string, so
     * `App\Http\Controllers` also moves `App\Http\Controllers\Settings\X`.
     *
     * @param  array<string, string>  $namespaces
     * @return array<string, string>
     */
    protected function namespacePatterns(array $namespaces): array
    {
        $patterns = [];

        foreach ($namespaces as $old => $new) {
            $patterns[$this->classNamePattern($old, true)]                            = $new;
            $patterns[$this->classNamePattern(str_replace('\\', '\\\\', $old), true)] = str_replace('\\', '\\\\', $new);
        }

        return $patterns;
    }

    /**
     * Laravel discovers commands in app/Console/Commands and listeners in
     * app/Listeners only, so the moved ones are registered in bootstrap/app.php.
     * Each `withCommands()` call adds paths, a `withEvents()` call replaces the
     * discovery paths, so an existing `withEvents()` is left for the user.
     *
     * @return list<string>
     */
    protected function registerMovedDiscoveryPaths(): array
    {
        $calls = [
            'Console/Commands' => ['withCommands', '', 'console commands', 'Laravel still registers them'],
            'Listeners'        => ['withEvents', 'discover: ', 'listeners', 'event discovery still finds them'],
        ];

        $path    = base_path('bootstrap/app.php');
        $content = $this->files->exists($path) ? $this->files->get($path) : '';
        $before  = $content;

        foreach ($calls as $source => [$method, $argument, $what, $why]) {
            $prefix = $this->relativeToBase(app_path($source)) . '/';

            if (array_filter(array_keys($this->appMigrationPlan), fn (string $from): bool => str_starts_with($from, $prefix)) === []) {
                continue;
            }

            [$layer, $target] = $this->appMigrationMap[$source];
            $directory        = $this->layerDirectory($layer) . "/{$target}";
            $line             = "__DIR__.'/../{$directory}'";
            $call             = "    ->{$method}({$argument}[\n        {$line},\n    ])";

            if (str_contains(str_replace(' ', '', $content), $line)) {
                continue;
            }

            $replaced = $method === 'withEvents' && str_contains($content, '->withEvents(')
                ? $content
                : (string) preg_replace('/^(return\s+Application::configure\(.*\))[ \t]*$/m', '$1' . "\n" . addcslashes($call, '\\$'), $content, 1);

            if ($replaced === $content) {
                $this->warn("The {$what} moved to {$directory}, add this to the application builder in bootstrap/app.php so {$why}:");
                $this->warn(trim($call));

                continue;
            }

            $content = $replaced;
        }

        if ($content === $before) {
            return [];
        }

        $this->files->put($path, $content);

        return ['bootstrap/app.php'];
    }

    /**
     * Wayfinder generates one TypeScript module per controller, under a path
     * that mirrors the controller namespace, so `@/actions/App/Http/...`
     * imports follow the namespaces that moved.
     *
     * @param  array<string, string>  $namespaces
     */
    protected function rewriteWayfinderImports(array $namespaces): void
    {
        $patterns = [];

        foreach ($namespaces as $old => $new) {
            $patterns['#(?<=actions/)' . preg_quote(str_replace('\\', '/', $old), '#') . '(?=[/\'"`])#'] = str_replace('\\', '/', $new);
        }

        $updated = [];

        foreach ($this->frontendDirectories as $directory) {
            if (! $this->files->isDirectory(base_path($directory))) {
                continue;
            }

            foreach ($this->files->allFiles(base_path($directory)) as $file) {
                $path = "{$directory}/{$file->getRelativePathname()}";

                if (! in_array($file->getExtension(), ['js', 'jsx', 'ts', 'tsx', 'vue', 'svelte'], true)
                    || preg_match('#^resources/(js|ts)/(actions|routes|wayfinder)/|(^|/)node_modules/#', $path) === 1) {
                    continue;
                }

                $content  = $file->getContents();
                $replaced = (string) preg_replace(array_keys($patterns), array_map(fn (string $value): string => addcslashes($value, '\\$'), array_values($patterns)), $content);

                if ($replaced !== $content) {
                    $this->files->put($file->getPathname(), $replaced);
                    $updated[] = $path;
                }
            }
        }

        foreach ($updated as $path) {
            $this->info("Updated: {$path}");
        }

        if ($updated !== []) {
            $this->warn('Wayfinder imports now point at the new controller namespaces. Regenerate them with "php artisan wayfinder:generate", or let the Vite plugin do it on the next build.');
        }
    }

    /**
     * Remove the source folders and their parents once nothing but
     * placeholders such as .gitkeep is left in them.
     */
    protected function removeEmptyAppDirectories(): void
    {
        $directories = [];

        foreach (array_keys($this->appMigrationMap) as $source) {
            $directories[] = $source;

            if (str_contains($source, '/')) {
                $directories[] = dirname($source);
            }
        }

        foreach (array_unique($directories) as $directory) {
            if ($this->files->isDirectory(app_path($directory)) && $this->files->allFiles(app_path($directory)) === []) {
                $this->files->deleteDirectory(app_path($directory));
                $this->info('Removed: ' . $this->relativeToBase(app_path($directory)));
            }
        }
    }

    /**
     * Name the PHP files still in app/ outside the layers, so nothing is left
     * behind silently. Providers stays on purpose.
     */
    protected function reportCodeOutsideLayers(): void
    {
        $layers = array_map(fn (string $layer): string => base_path($this->layerDirectory($layer)), ['domain', 'application', 'infrastructure']);
        $left   = [];

        foreach ($this->files->allFiles(app_path()) as $file) {
            $path = $file->getPathname();

            if ($file->getExtension() !== 'php' || str_starts_with($path, app_path('Providers') . '/')) {
                continue;
            }

            foreach ($layers as $layer) {
                if (str_starts_with($path, $layer . '/')) {
                    continue 2;
                }
            }

            $left[] = $this->relativeToBase($path);
        }

        if ($left === []) {
            return;
        }

        $this->warn('Left outside the layers, move them by hand or list their namespace in validation.outside_layers_allowed:');

        foreach ($left as $path) {
            $this->warn("  {$path}");
        }
    }

    /**
     * Root namespace of app/, the one Laravel's own folders use.
     */
    protected function appNamespace(): string
    {
        try {
            return app()->getNamespace();
        } catch (\RuntimeException) {
            return 'App\\';
        }
    }

    protected function relativeToBase(string $path): string
    {
        return ltrim(substr($path, strlen(base_path())), '/');
    }
}
