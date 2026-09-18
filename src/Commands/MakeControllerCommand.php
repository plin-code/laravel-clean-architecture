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

        if (Str::contains($name, ['/', '\\'])) {
            $this->error("Controller name must not contain '/' or '\\': {$name}. Nested controller directories are not supported.");

            return self::FAILURE;
        }

        $name = $this->stripSuffix($name, 'Controller');

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
        $content = $this->replacePlaceholders($stub, $name, ['{{WebControllerNamespace}}' => $this->webControllerNamespace()]);

        $segment         = $this->webControllerSegment();
        $directory       = rtrim($this->layerDirectory('infrastructure') . '/' . $segment, '/');
        $controllersPath = base_path($directory);
        if (! $this->files->isDirectory($controllersPath)) {
            $this->files->makeDirectory($controllersPath, 0755, true);
        }

        return $this->writeOrFail("{$controllersPath}/{$name}Controller.php", $content, "{$directory}/{$name}Controller.php");
    }

    /**
     * @param  array<string, string>  $extra
     */
    protected function replacePlaceholders(string $content, string $name, array $extra = []): string
    {
        $pluralName     = Str::plural($name);
        $domainVariable = Str::camel($name);

        $replacements = array_merge([
            '{{ControllerName}}'   => $name . 'Controller',
            '{{DomainName}}'       => $name,
            '{{PluralDomainName}}' => $pluralName,
            '{{domainVariable}}'   => $domainVariable,
        ], $extra);

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}
