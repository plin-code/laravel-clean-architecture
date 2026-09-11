<?php

namespace PlinCode\LaravelCleanArchitecture\Concerns;

/**
 * Move the `User` model of a fresh Laravel app into the Domain layer.
 *
 * The using class must expose a `$files` Filesystem property and use
 * `ResolvesArchitectureDirectories`.
 */
trait MovesUserIntoDomain
{
    /**
     * Directories whose PHP files may name the User class, relative to the
     * base path.
     *
     * @var list<string>
     */
    protected array $userReferenceDirectories = ['app', 'config', 'database', 'routes', 'tests'];

    /**
     * Frontend directories scanned, never modified, for broadcast channel names.
     *
     * @var list<string>
     */
    protected array $userFrontendDirectories = ['resources/js', 'resources/ts'];

    protected function installUserInDomain(): void
    {
        $destination = $this->userModelDestination();

        if (! $this->files->exists($this->userModelSource()) && $this->files->exists(base_path($destination))) {
            $this->info("Skipped: User already lives in {$destination}");

            return;
        }

        $oldClass = $this->namespaceOf($this->files->get($this->userModelSource())) . '\\User';
        $newClass = $this->userModelNamespace() . '\\User';

        $this->moveUserModel($destination);

        $updated = array_merge(
            $this->replaceClassReferences($oldClass, $newClass),
            $this->pointFactoryAtModel($newClass),
            $this->registerUserMorphMap($newClass),
            $this->renameBroadcastChannel($oldClass, $newClass),
        );

        foreach (array_unique($updated) as $path) {
            $this->info("Updated: {$path}");
        }

        $this->warnAboutFrontendChannels($oldClass, $newClass);
    }

    protected function userModelSource(): string
    {
        return app_path('Models/User.php');
    }

    /**
     * Destination relative to the base path, following `directories.domain`
     * and `generation.model_directory`.
     */
    protected function userModelDestination(): string
    {
        $segment = $this->modelDirectorySegment();

        return $this->layerDirectory('domain') . '/Users' . ($segment !== '' ? "/{$segment}" : '') . '/User.php';
    }

    protected function userModelNamespace(): string
    {
        $segment = $this->modelDirectorySegment();

        return $this->layerNamespace('domain') . 'Users' . ($segment !== '' ? "\\{$segment}" : '');
    }

    protected function moveUserModel(string $destination): void
    {
        $content = $this->files->get($this->userModelSource());
        $content = (string) preg_replace('/^namespace\s+[^;]+;/m', "namespace {$this->userModelNamespace()};", $content, 1);
        $content = $this->addNewFactoryMethod($content);

        $this->files->ensureDirectoryExists(dirname(base_path($destination)));
        $this->files->put(base_path($destination), $content);
        $this->files->delete($this->userModelSource());

        $this->info("Moved: app/Models/User.php to {$destination}");
    }

    /**
     * The factory resolver guesses `Database\Factories\<path under App>Factory`
     * from the model class, which does not exist once the model has moved, so
     * the model names its factory explicitly.
     */
    protected function addNewFactoryMethod(string $content): string
    {
        if (str_contains($content, 'function newFactory(')) {
            return $content;
        }

        $factory = str_contains($content, "\nuse Database\\Factories\\UserFactory;")
            ? 'UserFactory'
            : '\\Database\\Factories\\UserFactory';

        $method = <<<PHP

            /**
             * Create a new factory instance for the model.
             */
            protected static function newFactory(): {$factory}
            {
                return {$factory}::new();
            }

        PHP;

        $closingBrace = strrpos($content, '}');

        if ($closingBrace === false) {
            return $content;
        }

        return rtrim(substr($content, 0, $closingBrace)) . "\n" . $method . substr($content, $closingBrace);
    }

    /**
     * Replace the class name, as written in code and as escaped inside a
     * string, in every PHP file of the reference directories.
     *
     * @return list<string> updated paths, relative to the base path
     */
    protected function replaceClassReferences(string $oldClass, string $newClass): array
    {
        $patterns = [
            $this->classNamePattern($oldClass)                            => $newClass,
            $this->classNamePattern(str_replace('\\', '\\\\', $oldClass)) => str_replace('\\', '\\\\', $newClass),
        ];

        $updated = [];

        foreach ($this->userReferenceDirectories as $directory) {
            if (! $this->files->isDirectory(base_path($directory))) {
                continue;
            }

            foreach ($this->files->allFiles(base_path($directory)) as $file) {
                if ($file->getExtension() !== 'php' || preg_match('#(^|/)(vendor|node_modules)/#', $file->getRelativePathname()) === 1) {
                    continue;
                }

                $content  = $file->getContents();
                $replaced = $content;

                foreach ($patterns as $pattern => $replacement) {
                    $replaced = (string) preg_replace($pattern, addcslashes($replacement, '\\$'), $replaced);
                }

                if ($replaced !== $content) {
                    $this->files->put($file->getPathname(), $replaced);
                    $updated[] = "{$directory}/{$file->getRelativePathname()}";
                }
            }
        }

        return $updated;
    }

    /**
     * Match a class name that is not the start of a longer one, so
     * `App\Models\User` leaves `App\Models\UserProfile` alone.
     */
    protected function classNamePattern(string $class): string
    {
        return '/(?<![\w\\\\])' . preg_quote($class, '/') . '(?![\w\\\\])/';
    }

    /**
     * Without a `$model` property the factory guesses its model from its own
     * name, `App\Models\User`, which no longer exists.
     *
     * @return list<string>
     */
    protected function pointFactoryAtModel(string $newClass): array
    {
        $path = database_path('factories/UserFactory.php');

        if (! $this->files->exists($path)) {
            return [];
        }

        $content = $this->files->get($path);

        if (preg_match('/\$model\s*=/', $content) === 1) {
            return [];
        }

        $model    = str_contains($content, "\nuse {$newClass};") ? 'User' : "\\{$newClass}";
        $property = <<<PHP
                /**
                 * The name of the factory's corresponding model.
                 *
                 * @var class-string<{$model}>
                 */
                protected \$model = {$model}::class;


            PHP;

        $replaced = (string) preg_replace('/(class\s+UserFactory\b[^{]*\{\n)/', '$1' . addcslashes($property, '\\$'), $content, 1);

        if ($replaced === $content) {
            return [];
        }

        $this->files->put($path, $replaced);

        return ['database/factories/UserFactory.php'];
    }

    /**
     * Store `user` instead of the class name in polymorphic `*_type` columns.
     *
     * @return list<string>
     */
    protected function registerUserMorphMap(string $newClass): array
    {
        $path = app_path('Providers/AppServiceProvider.php');

        if (! $this->files->exists($path)) {
            $this->warn("Skipped morph map: app/Providers/AppServiceProvider.php not found. Register Relation::morphMap(['user' => \\{$newClass}::class]) yourself.");

            return [];
        }

        $content = $this->files->get($path);

        if (preg_match('/[\'"]user[\'"]\s*=>/', $content) === 1) {
            return [];
        }

        if (str_contains($content, 'morphMap(') || str_contains($content, 'enforceMorphMap(')) {
            $this->warn("Skipped morph map: AppServiceProvider already defines one. Add 'user' => \\{$newClass}::class to it.");

            return [];
        }

        $call = <<<'PHP'

                    Relation::morphMap([
                        'user' => User::class,
                    ]);
            PHP;

        $replaced = (string) preg_replace(
            '/(public\s+function\s+boot\(\)\s*(?::\s*void\s*)?\{)(\s*\/\/[ \t]*(?=\n))?/',
            '$1' . addcslashes($call, '\\$'),
            $content,
            1
        );

        if ($replaced === $content) {
            $this->warn("Skipped morph map: no boot() method in AppServiceProvider. Register Relation::morphMap(['user' => \\{$newClass}::class]) yourself.");

            return [];
        }

        $this->files->put($path, $this->addImports($replaced, [$newClass, 'Illuminate\\Database\\Eloquent\\Relations\\Relation']));

        return ['app/Providers/AppServiceProvider.php'];
    }

    /**
     * Private notification channels are named after the notifiable class.
     *
     * @return list<string>
     */
    protected function renameBroadcastChannel(string $oldClass, string $newClass): array
    {
        $path = base_path('routes/channels.php');

        if (! $this->files->exists($path)) {
            return [];
        }

        $content  = $this->files->get($path);
        $replaced = str_replace(
            str_replace('\\', '.', $oldClass) . '.',
            str_replace('\\', '.', $newClass) . '.',
            $content
        );

        if ($replaced === $content) {
            return [];
        }

        $this->files->put($path, $replaced);

        return ['routes/channels.php'];
    }

    protected function warnAboutFrontendChannels(string $oldClass, string $newClass): void
    {
        $oldChannel = str_replace('\\', '.', $oldClass) . '.';
        $matches    = [];

        foreach ($this->userFrontendDirectories as $directory) {
            if (! $this->files->isDirectory(base_path($directory))) {
                continue;
            }

            foreach ($this->files->allFiles(base_path($directory)) as $file) {
                if (str_contains($file->getContents(), $oldChannel)) {
                    $matches[] = "{$directory}/{$file->getRelativePathname()}";
                }
            }
        }

        if ($matches === []) {
            return;
        }

        $newChannel = str_replace('\\', '.', $newClass) . '.';

        $this->warn("These files listen on {$oldChannel}{id}, rename it to {$newChannel}{id} by hand:");

        foreach ($matches as $match) {
            $this->warn("  {$match}");
        }
    }

    protected function namespaceOf(string $content): string
    {
        return preg_match('/^namespace\s+([^;]+);/m', $content, $matches) === 1
            ? trim($matches[1])
            : 'App\\Models';
    }

    /**
     * Add `use` statements to the import block, keeping it sorted.
     *
     * @param  list<string>  $classes
     */
    protected function addImports(string $content, array $classes): string
    {
        $missing = array_values(array_filter($classes, fn (string $class): bool => ! str_contains($content, "\nuse {$class};")));

        if ($missing === []) {
            return $content;
        }

        $statements = array_map(fn (string $class): string => "use {$class};", $missing);

        if (preg_match('/^(?:use [^;]+;\n)+/m', $content, $block, PREG_OFFSET_CAPTURE) !== 1) {
            return (string) preg_replace('/^(namespace\s+[^;]+;\n)/m', '$1' . "\n" . addcslashes(implode("\n", $statements), '\\$') . "\n", $content, 1);
        }

        $lines = array_merge(explode("\n", rtrim($block[0][0])), $statements);
        usort($lines, fn (string $a, string $b): int => strcasecmp(rtrim($a, ';'), rtrim($b, ';')));

        return substr_replace($content, implode("\n", $lines) . "\n", $block[0][1], strlen($block[0][0]));
    }
}
