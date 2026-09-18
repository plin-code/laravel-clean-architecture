<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;
use PlinCode\LaravelCleanArchitecture\Concerns\WritesFiles;

class MakeObserverCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;
    use WritesFiles;

    protected $signature = 'clean-arch:make-observer {name : The name of the observer}
                          {domain : The domain name}
                          {--force : Overwrite existing files}';

    protected $description = 'Create a new observer in the Infrastructure layer';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $name   = $this->argument('name');
        $domain = $this->argument('domain');

        $this->resolveForce();

        $this->info("Creating observer: {$name} for domain: {$domain}");
        $exitCode = $this->createObserver($name, $domain);

        if ($exitCode === self::SUCCESS) {
            $this->info("Observer {$name} created successfully!");
        }

        return $exitCode;
    }

    protected function createObserver(string $name, string $domain): int
    {
        $name    = $this->stripSuffix($name, 'Observer');
        $stub    = $this->getStub('observer');
        $content = $this->replacePlaceholders($stub, $name, $domain);

        $pluralDomain = Str::plural($domain);
        $directory    = $this->layerDirectory('infrastructure') . "/Observers/{$pluralDomain}";
        $observerPath = base_path($directory);

        if (! $this->files->isDirectory($observerPath)) {
            $this->files->makeDirectory($observerPath, 0755, true);
        }

        return $this->writeOrFail("{$observerPath}/{$name}Observer.php", $content, "{$directory}/{$name}Observer.php");
    }

    /**
     * The observer class is named after `$name`, the file it is written to,
     * while the model it observes follows `$domain`. The two agree in the
     * documented usage, `make-observer Article Article`, and diverge when a
     * name of its own is given, `make-observer StoreObserver Store`, which
     * used to generate a `StoresObserver` class inside `StoreObserver.php`.
     */
    protected function replacePlaceholders(string $content, string $name, string $domain): string
    {
        $pluralDomain   = Str::plural($domain);
        $domainVariable = Str::camel($domain);

        return str_replace(
            ['{{ObserverName}}', '{{DomainName}}', '{{PluralDomainName}}', '{{domainVariable}}'],
            [$name, $domain, $pluralDomain, $domainVariable],
            $content
        );
    }
}
