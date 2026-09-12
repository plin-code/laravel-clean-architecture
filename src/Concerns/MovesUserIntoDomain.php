<?php

namespace PlinCode\LaravelCleanArchitecture\Concerns;

use Symfony\Component\Finder\SplFileInfo;

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

    /**
     * Check, before anything is written, that the move is safe on this app.
     */
    protected function canInstallUserInDomain(): bool
    {
        $destination       = $this->userModelDestination();
        $sourceExists      = $this->files->exists($this->userModelSource());
        $destinationExists = $this->files->exists(base_path($destination));

        if ($sourceExists && $destinationExists) {
            $this->error("Both app/Models/User.php and {$destination} exist. Nothing was changed.");

            return false;
        }

        if (! $sourceExists && ! $destinationExists) {
            $this->error('app/Models/User.php not found, there is no User model to move. Nothing was changed.');

            return false;
        }

        $otherModels = $sourceExists
            ? array_filter($this->files->allFiles(app_path('Models')), fn ($file): bool => $file->getRelativePathname() !== 'User.php')
            : [];

        if ($otherModels !== [] && ! $this->force) {
            $this->error('app/Models holds other models besides User.php, so this does not look like a fresh app. Nothing was changed.');
            $this->line('Read "Moving User into the Domain in an existing app" in the README, or run again with --force to move User anyway.');

            return false;
        }

        return true;
    }

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

        $this->removeModelsDirectory();
        $this->removeHttpDirectory();
    }

    protected function removeModelsDirectory(): void
    {
        $directory = app_path('Models');

        if (! $this->files->isDirectory($directory)) {
            return;
        }

        // Dotfiles such as .gitkeep are placeholders, not content.
        if ($this->files->allFiles($directory) !== []) {
            $this->info('Kept: app/Models, it still holds other files');

            return;
        }

        $this->files->deleteDirectory($directory);
        $this->info('Removed: app/Models');
    }

    /**
     * A fresh app only has the empty abstract `Controllers/Controller.php` in
     * `app/Http`. The directory goes only when that is all it holds and no
     * file in `app/` or `routes/` still names the class.
     */
    protected function removeHttpDirectory(): void
    {
        $directory = app_path('Http');

        if (! $this->files->isDirectory($directory)) {
            return;
        }

        $files = $this->files->allFiles($directory);

        if (count($files) !== 1 || $files[0]->getRelativePathname() !== 'Controllers/Controller.php') {
            $this->info('Kept: app/Http, it holds more than the default base controller');

            return;
        }

        $controller = $this->namespaceOf($files[0]->getContents()) . '\\Controller';

        foreach ($this->filesNaming($controller, ['app', 'routes']) as $path) {
            $this->info("Kept: app/Http, {$path} uses {$controller}");

            return;
        }

        $this->files->deleteDirectory($directory);
        $this->info('Removed: app/Http');
    }

    /**
     * PHP files of the given directories that name the class, as written in
     * code or escaped inside a string.
     *
     * @param  list<string>  $directories
     * @return list<string> paths relative to the base path
     */
    protected function filesNaming(string $class, array $directories): array
    {
        $patterns = [$this->classNamePattern($class), $this->classNamePattern(str_replace('\\', '\\\\', $class))];
        $matches  = [];

        foreach ($this->phpFilesIn($directories) as $path => $file) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $file->getContents()) === 1) {
                    $matches[] = $path;

                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * PHP files of the given directories, outside any vendor or node_modules
     * folder, keyed by their path relative to the base path.
     *
     * @param  list<string>  $directories
     * @return array<string, SplFileInfo>
     */
    protected function phpFilesIn(array $directories): array
    {
        $files = [];

        foreach ($directories as $directory) {
            if (! $this->files->isDirectory(base_path($directory))) {
                continue;
            }

            foreach ($this->files->allFiles(base_path($directory)) as $file) {
                if ($file->getExtension() === 'php' && preg_match('#(^|/)(vendor|node_modules)/#', $file->getRelativePathname()) !== 1) {
                    $files["{$directory}/{$file->getRelativePathname()}"] = $file;
                }
            }
        }

        return $files;
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

        foreach ($this->phpFilesIn($this->userReferenceDirectories) as $path => $file) {
            $content  = $file->getContents();
            $replaced = $content;

            foreach ($patterns as $pattern => $replacement) {
                $replaced = (string) preg_replace($pattern, addcslashes($replacement, '\\$'), $replaced);
            }

            if ($replaced !== $content) {
                $this->files->put($file->getPathname(), $replaced);
                $updated[] = $path;
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
