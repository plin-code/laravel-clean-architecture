<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;
use PlinCode\LaravelCleanArchitecture\Concerns\WritesFiles;

class MakeServiceCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;
    use WritesFiles;

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
        $name = $this->argument('name');

        $this->resolveForce();

        $this->info("🚀 Creating service: {$name}");

        $exitCode = $this->createService($name);

        if ($exitCode === self::SUCCESS) {
            $this->info("✅ Service {$name} created successfully!");
        }

        return $exitCode;
    }

    protected function createService(string $name): int
    {
        $name    = $this->stripSuffix($name, 'Service');
        $extend  = $this->shouldExtendBaseClasses((bool) $this->option('no-base'));
        $stub    = $this->getStub('service');
        $content = $this->replacePlaceholders($stub, $name, $this->baseServiceReplacements($extend));

        $directory    = $this->layerDirectory('application') . '/Services';
        $servicesPath = base_path($directory);
        if (! $this->files->isDirectory($servicesPath)) {
            $this->files->makeDirectory($servicesPath, 0755, true);
        }

        return $this->writeOrFail("{$servicesPath}/{$name}Service.php", $content, "{$directory}/{$name}Service.php");
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
