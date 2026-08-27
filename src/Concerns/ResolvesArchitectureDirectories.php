<?php

namespace PlinCode\LaravelCleanArchitecture\Concerns;

/**
 * Read the layer directories from config/clean-architecture.php.
 *
 * The package config is not merged into the application config, so the values
 * below are used whenever the file has not been published.
 */
trait ResolvesArchitectureDirectories
{
    /**
     * @var array<string, string>
     */
    protected array $defaultDirectories = [
        'domain'         => 'app/Domain',
        'application'    => 'app/Application',
        'infrastructure' => 'app/Infrastructure',
    ];

    /**
     * Path of a layer, relative to the base path.
     */
    protected function layerDirectory(string $layer): string
    {
        $configured = config("clean-architecture.directories.{$layer}");

        if (is_string($configured) && trim($configured) !== '') {
            return trim(trim($configured), '/');
        }

        return $this->defaultDirectories[$layer];
    }

    /**
     * Namespace of a layer, derived from its directory, with a trailing separator.
     *
     * `app/Domain` becomes `App\Domain\`, following the psr-4 mapping Laravel
     * ships with. A leading `app` segment is replaced by the configured root
     * namespace, any other first segment is kept.
     */
    protected function layerNamespace(string $layer): string
    {
        $segments = explode('/', $this->layerDirectory($layer));

        if (strtolower((string) reset($segments)) === 'app') {
            array_shift($segments);
        }

        $root     = config('clean-architecture.default_namespace');
        $root     = is_string($root) && trim($root) !== '' ? trim($root, '\\') : 'App';
        $segments = array_filter(array_merge([$root], $segments), fn (string $segment): bool => $segment !== '');

        return implode('\\', $segments) . '\\';
    }
}
