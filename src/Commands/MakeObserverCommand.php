<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;

class MakeObserverCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;

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

        $this->info("Creating observer: {$name} for domain: {$domain}");
        $this->createObserver($name, $domain);
        $this->info("Observer {$name} created successfully!");

        return self::SUCCESS;
    }

    protected function createObserver(string $name, string $domain): void
    {
        $stub    = $this->getStub('observer');
        $content = $this->replacePlaceholders($stub, $name, $domain);

        $pluralDomain = Str::plural($domain);
        $directory    = $this->layerDirectory('infrastructure') . "/Observers/{$pluralDomain}";
        $observerPath = base_path($directory);

        if (! $this->files->isDirectory($observerPath)) {
            $this->files->makeDirectory($observerPath, 0755, true);
        }

        $this->files->put("{$observerPath}/{$name}Observer.php", $content);
        $this->info("Created: {$directory}/{$name}Observer.php");
    }

    protected function replacePlaceholders(string $content, string $name, string $domain): string
    {
        $pluralDomain   = Str::plural($domain);
        $domainVariable = Str::camel($domain);

        return str_replace(
            ['{{DomainName}}', '{{PluralDomainName}}', '{{domainVariable}}'],
            [$domain, $pluralDomain, $domainVariable],
            $content
        );
    }
}
