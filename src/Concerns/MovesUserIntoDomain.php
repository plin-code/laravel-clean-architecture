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

    protected function moveUserIntoDomain(): void
    {
        $destination = $this->userModelDestination();
        $content     = $this->files->get($this->userModelSource());
        $content     = (string) preg_replace('/^namespace\s+[^;]+;/m', "namespace {$this->userModelNamespace()};", $content, 1);
        $content     = $this->addNewFactoryMethod($content);

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

        $factory = preg_match('/^use Database\\\\Factories\\\\UserFactory;/m', $content) === 1
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
}
