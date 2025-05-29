<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeControllerCommand extends Command
{
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
        $force = $this->option('force');

        if (! $isApi && ! $isWeb) {
            $isApi = true; // Default to API
        }

        $this->info("🚀 Creating controller: {$name}");

        if ($isApi) {
            $this->createApiController($name);
        }

        if ($isWeb) {
            $this->createWebController($name);
        }

        $this->info("✅ Controller {$name} created successfully!");

        return self::SUCCESS;
    }

    protected function createApiController(string $name): void
    {
        $stub    = $this->getStub('controller');
        $content = $this->replacePlaceholders($stub, $name);

        $controllersPath = app_path('Infrastructure/API/Controllers');
        if (! $this->files->isDirectory($controllersPath)) {
            $this->files->makeDirectory($controllersPath, 0755, true);
        }

        $this->files->put("{$controllersPath}/{$name}Controller.php", $content);
        $this->info("Created: Infrastructure/API/Controllers/{$name}Controller.php");
    }

    protected function createWebController(string $name): void
    {
        $stub    = $this->getStub('web-controller');
        $content = $this->replacePlaceholders($stub, $name);

        $controllersPath = app_path('Infrastructure/UI/Web/Controllers');
        if (! $this->files->isDirectory($controllersPath)) {
            $this->files->makeDirectory($controllersPath, 0755, true);
        }

        $this->files->put("{$controllersPath}/{$name}Controller.php", $content);
        $this->info("Created: Infrastructure/UI/Web/Controllers/{$name}Controller.php");
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

    protected function getStub(string $stub): string
    {
        $stubPath = __DIR__ . "/../../stubs/{$stub}.stub";

        if (! $this->files->exists($stubPath)) {
            throw new \Exception("Stub file not found: {$stubPath}");
        }

        return $this->files->get($stubPath);
    }
}
