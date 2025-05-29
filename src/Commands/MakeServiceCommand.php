<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeServiceCommand extends Command
{
    protected $signature = 'clean-arch:make-service {name : The name of the service}
                          {--force : Overwrite existing files}';

    protected $description = 'Create a new service in the Application layer';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $name  = $this->argument('name');
        $force = $this->option('force');

        $this->info("🚀 Creating service: {$name}");

        $this->createService($name);

        $this->info("✅ Service {$name} created successfully!");

        return self::SUCCESS;
    }

    protected function createService(string $name): void
    {
        $stub    = $this->getStub('service');
        $content = $this->replacePlaceholders($stub, $name);

        $servicesPath = app_path('Application/Services');
        if (! $this->files->isDirectory($servicesPath)) {
            $this->files->makeDirectory($servicesPath, 0755, true);
        }

        $this->files->put("{$servicesPath}/{$name}Service.php", $content);
        $this->info("Created: Application/Services/{$name}Service.php");
    }

    protected function replacePlaceholders(string $content, string $name): string
    {
        $pluralName     = Str::plural($name);
        $domainVariable = Str::camel($name);

        $replacements = [
            '{{DomainName}}'       => $name,
            '{{PluralDomainName}}' => $pluralName,
            '{{domainVariable}}'   => $domainVariable,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
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
