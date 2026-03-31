<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeListenerCommand extends Command
{
    protected $signature = 'clean-arch:make-listener {name : The name of the listener}
                          {--force : Overwrite existing files}';

    protected $description = 'Create a new listener in the Application layer';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        $this->info("Creating listener: {$name}");
        $this->createListener($name);
        $this->info("Listener {$name} created successfully!");

        return self::SUCCESS;
    }

    protected function createListener(string $name): void
    {
        $stub    = $this->getStub('listener');
        $content = $this->replacePlaceholders($stub, $name);

        $listenerPath = app_path('Application/Listeners');

        if (! $this->files->isDirectory($listenerPath)) {
            $this->files->makeDirectory($listenerPath, 0755, true);
        }

        $this->files->put("{$listenerPath}/{$name}Listener.php", $content);
        $this->info("Created: Application/Listeners/{$name}Listener.php");
    }

    protected function replacePlaceholders(string $content, string $name): string
    {
        $pluralName     = Str::plural($name);
        $domainVariable = Str::camel($name);

        return str_replace(
            ['{{DomainName}}', '{{PluralDomainName}}', '{{domainVariable}}'],
            [$name, $pluralName, $domainVariable],
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
