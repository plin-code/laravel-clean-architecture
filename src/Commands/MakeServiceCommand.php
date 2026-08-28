<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;

class MakeServiceCommand extends Command
{
    use RendersStubs;

    protected $signature = 'clean-arch:make-service {name : The name of the service}
                           {--force : Overwrite existing files}
                           {--no-base : Do not extend BaseService}';

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
        $extend  = $this->shouldExtendBaseClasses((bool) $this->option('no-base'));
        $stub    = $this->getStub('service');
        $content = $this->replacePlaceholders($stub, $name, $this->baseServiceReplacements($extend));

        $servicesPath = app_path('Application/Services');
        if (! $this->files->isDirectory($servicesPath)) {
            $this->files->makeDirectory($servicesPath, 0755, true);
        }

        $this->files->put("{$servicesPath}/{$name}Service.php", $content);
        $this->info("Created: Application/Services/{$name}Service.php");
    }

    protected function replacePlaceholders(string $content, string $name, array $extra = []): string
    {
        $pluralName     = Str::plural($name);
        $domainVariable = Str::camel($name);

        $replacements = array_merge([
            '{{DomainName}}'       => $name,
            '{{PluralDomainName}}' => $pluralName,
            '{{domainVariable}}'   => $domainVariable,
        ], $extra);

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}
