<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;
use PlinCode\LaravelCleanArchitecture\Concerns\WritesFiles;

class MakeControllerCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;
    use WritesFiles;

    protected $signature = 'clean-arch:make-controller {name : The name of the controller}
                          {--api : Generate API controller}
                          {--web : Generate Web controller}
                          {--force : Overwrite existing files}';

    protected $description = 'Create a new controller in the Infrastructure layer';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $name  = $this->argument('name');
        $isApi = $this->option('api');
        $isWeb = $this->option('web');

        if (! $isApi && ! $isWeb) {
            $isApi = true; // Default to API
        }

        $this->resolveForce();

        $this->info("🚀 Creating controller: {$name}");

        $exitCode = self::SUCCESS;

        if ($isApi) {
            $exitCode = max($exitCode, $this->createApiController($name));
        }

        if ($isWeb) {
            $exitCode = max($exitCode, $this->createWebController($name));
        }

        if ($exitCode === self::SUCCESS) {
            $this->info("✅ Controller {$name} created successfully!");
        }

        return $exitCode;
    }

    protected function createApiController(string $name): int
    {
        $stub    = $this->getStub('controller');
        $content = $this->replacePlaceholders($stub, $name);

        $pluralName      = Str::plural($name);
        $directory       = $this->layerDirectory('infrastructure') . '/Http/Controllers/Api';
        $controllersPath = base_path($directory);
        if (! $this->files->isDirectory($controllersPath)) {
            $this->files->makeDirectory($controllersPath, 0755, true);
        }

        return $this->writeOrFail("{$controllersPath}/{$pluralName}Controller.php", $content, "{$directory}/{$pluralName}Controller.php");
    }

    protected function createWebController(string $name): int
    {
        $stub    = $this->getStub('web-controller');
        $content = $this->replacePlaceholders($stub, $name);

        $directory       = $this->layerDirectory('infrastructure') . '/UI/Web/Controllers';
        $controllersPath = base_path($directory);
        if (! $this->files->isDirectory($controllersPath)) {
            $this->files->makeDirectory($controllersPath, 0755, true);
        }

        return $this->writeOrFail("{$controllersPath}/{$name}Controller.php", $content, "{$directory}/{$name}Controller.php");
    }

    protected function replacePlaceholders(string $content, string $name): string
    {
        $pluralName     = Str::plural($name);
        $domainVariable = Str::camel($name);

        $replacements = [
            '{{ControllerName}}'   => $name . 'Controller',
            '{{DomainName}}'       => $name,
            '{{PluralDomainName}}' => $pluralName,
            '{{domainVariable}}'   => $domainVariable,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}
