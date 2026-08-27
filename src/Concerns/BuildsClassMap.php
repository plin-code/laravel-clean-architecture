<?php

namespace PlinCode\LaravelCleanArchitecture\Concerns;

use Symfony\Component\Finder\Finder;

/**
 * Build a map of the classes declared by the application and walk their
 * inheritance chains.
 *
 * The map is built by parsing sources, never by autoloading them, so a class
 * whose parent lives in `vendor/` ends the chain. That is a deliberate limit:
 * resolving it would mean loading application code inside a validation command.
 */
trait BuildsClassMap
{
    use ParsesPhpSource;

    /**
     * @var array<string, array{path: string, kind: string, extends: list<string>, implements: list<string>}>|null
     */
    protected ?array $classMap = null;

    /**
     * Every type declared under the scanned directories, keyed by its FQCN.
     *
     * @return array<string, array{path: string, kind: string, extends: list<string>, implements: list<string>}>
     */
    protected function classMap(): array
    {
        if ($this->classMap !== null) {
            return $this->classMap;
        }

        $directories = $this->classMapDirectories();

        if ($directories === []) {
            return $this->classMap = [];
        }

        $map = [];

        $finder = new Finder;
        $finder->files()->in($directories)->name('*.php');

        foreach ($finder as $file) {
            $path = $file->getRealPath();

            if ($path === false) {
                continue;
            }

            foreach ($this->parseClassDeclarations($file->getContents()) as $declaration) {
                $map[$declaration['fqcn']] = [
                    'path'       => $path,
                    'kind'       => $declaration['kind'],
                    'extends'    => $declaration['extends'],
                    'implements' => $declaration['implements'],
                ];
            }
        }

        return $this->classMap = $map;
    }

    /**
     * Every parent class and interface of a type, transitively.
     *
     * @return list<string>
     */
    protected function ancestorsOf(string $fqcn): array
    {
        $map       = $this->classMap();
        $ancestors = [];
        $queue     = [$fqcn];
        $visited   = [$fqcn => true];

        while ($queue !== []) {
            $current = array_shift($queue);
            $entry   = $map[$current] ?? null;

            if ($entry === null) {
                continue;
            }

            foreach (array_merge($entry['extends'], $entry['implements']) as $parent) {
                if (isset($visited[$parent])) {
                    continue;
                }

                $visited[$parent] = true;
                $ancestors[]      = $parent;
                $queue[]          = $parent;
            }
        }

        return $ancestors;
    }

    /**
     * Directories parsed to build the map.
     *
     * `app` is included on top of the three layers because a base class often
     * lives outside them, in the `app/Console/Commands` directory Laravel ships
     * with. Nested directories are dropped so a file is parsed once.
     *
     * @return list<string>
     */
    protected function classMapDirectories(): array
    {
        $candidates = [
            base_path('app'),
            base_path($this->layerDirectory('domain')),
            base_path($this->layerDirectory('application')),
            base_path($this->layerDirectory('infrastructure')),
        ];

        $candidates = array_values(array_unique(array_filter(
            $candidates,
            fn (string $directory): bool => $this->files->isDirectory($directory)
        )));

        usort($candidates, fn (string $a, string $b): int => strlen($a) <=> strlen($b));

        $directories = [];

        foreach ($candidates as $candidate) {
            foreach ($directories as $kept) {
                if (str_starts_with($candidate, rtrim($kept, '/') . '/')) {
                    continue 2;
                }
            }

            $directories[] = $candidate;
        }

        return $directories;
    }
}
