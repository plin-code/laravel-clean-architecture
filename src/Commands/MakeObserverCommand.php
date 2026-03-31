<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeObserverCommand extends Command
{
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
        $observerPath = app_path("Infrastructure/Observers/{$pluralDomain}");

        if (! $this->files->isDirectory($observerPath)) {
            $this->files->makeDirectory($observerPath, 0755, true);
        }

        $this->files->put("{$observerPath}/{$name}Observer.php", $content);
        $this->info("Created: Infrastructure/Observers/{$pluralDomain}/{$name}Observer.php");
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

    protected function getStub(string $stub): string
    {
        $stubPath = __DIR__ . "/../../stubs/{$stub}.stub";

        if (! $this->files->exists($stubPath)) {
            throw new \Exception("Stub file not found: {$stubPath}");
        }

        return $this->files->get($stubPath);
    }
}
