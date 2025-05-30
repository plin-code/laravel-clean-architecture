<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeDomainCommand extends Command
{
    protected $signature = 'clean-arch:make-domain {name : The name of the domain}
                          {--force : Overwrite existing files}';

    protected $description = 'Create a new domain with complete Clean Architecture structure';

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

        $this->info("🚀 Creating domain: {$name}");

        // Create domain structure
        $this->createDomainModel($name);
        $this->createDomainEnums($name);
        $this->createDomainEvents($name);
        $this->createActions($name);
        $this->createService($name);
        $this->createController($name);
        $this->createRequests($name);
        $this->createResource($name);
        $this->createTests($name);

        // Create migration
        $this->createMigration($name);

        // Add .gitkeep to empty directories
        $this->addGitKeepFiles($name);

        $this->info("✅ Domain {$name} created successfully!");
        $this->newLine();
        $this->info('Next steps:');
        $this->info('1. Add routes to routes/api.php');
        $this->info('2. Run tests: php artisan test');

        return self::SUCCESS;
    }

    protected function createDomainModel(string $name): void
    {
        $stub    = $this->getStub('domain-model');
        $content = $this->replacePlaceholders($stub, $name);

        $pluralName = Str::plural($name);
        $path       = app_path("Domain/{$pluralName}/{$name}.php");

        if (! $this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0755, true);
        }

        $this->files->put($path, $content);
        $this->info("Created: Domain/{$pluralName}/{$name}.php");
    }

    protected function createDomainEnums(string $name): void
    {
        $stub    = $this->getStub('domain-enum');
        $content = $this->replacePlaceholders($stub, $name);

        $pluralName = Str::plural($name);
        $enumsPath  = app_path("Domain/{$pluralName}/Enums");

        if (! $this->files->isDirectory($enumsPath)) {
            $this->files->makeDirectory($enumsPath, 0755, true);
        }

        $this->files->put("{$enumsPath}/{$name}Status.php", $content);
        $this->info("Created: Domain/{$pluralName}/Enums/{$name}Status.php");
    }

    protected function createDomainEvents(string $name): void
    {
        $events     = ['Created', 'Updated', 'Deleted'];
        $pluralName = Str::plural($name);
        $eventsPath = app_path("Domain/{$pluralName}/Events");

        if (! $this->files->isDirectory($eventsPath)) {
            $this->files->makeDirectory($eventsPath, 0755, true);
        }

        foreach ($events as $event) {
            $stub    = $this->getStub('domain-event');
            $content = $this->replacePlaceholders($stub, $name, [
                'EventName' => $name . $event,
            ]);

            $this->files->put("{$eventsPath}/{$name}{$event}.php", $content);
            $this->info("Created: Domain/{$pluralName}/Events/{$name}{$event}.php");
        }
    }

    protected function createActions(string $name): void
    {
        $actions = [
            'Create'  => 'Create' . $name . 'Request',
            'Update'  => 'Update' . $name . 'Request',
            'Delete'  => '',
            'GetById' => '',
        ];

        $pluralName  = Str::plural($name);
        $actionsPath = app_path("Application/Actions/{$pluralName}");

        if (! $this->files->isDirectory($actionsPath)) {
            $this->files->makeDirectory($actionsPath, 0755, true);
        }

        foreach ($actions as $action => $requestClass) {
            $stub    = $this->getStub('action');
            $content = $this->replacePlaceholders($stub, $name, [
                'ActionName'  => $action . $name . 'Action',
                'RequestName' => $requestClass ?: 'Request',
            ]);

            $this->files->put("{$actionsPath}/{$action}{$name}Action.php", $content);
            $this->info("Created: Application/Actions/{$pluralName}/{$action}{$name}Action.php");
        }
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

    protected function createController(string $name): void
    {
        $stub    = $this->getStub('controller');
        $content = $this->replacePlaceholders($stub, $name);

        $pluralName      = Str::plural($name);
        $controllersPath = app_path('Infrastructure/API/Controllers');

        $this->files->put("{$controllersPath}/{$pluralName}Controller.php", $content);
        $this->info("Created: Infrastructure/API/Controllers/{$pluralName}Controller.php");
    }

    protected function createRequests(string $name): void
    {
        $requests     = ['Create', 'Update'];
        $requestsPath = app_path('Infrastructure/API/Requests');

        foreach ($requests as $request) {
            $stub    = $this->getStub('request');
            $content = $this->replacePlaceholders($stub, $name, [
                'RequestName' => $request . $name . 'Request',
            ]);

            $this->files->put("{$requestsPath}/{$request}{$name}Request.php", $content);
            $this->info("Created: Infrastructure/API/Requests/{$request}{$name}Request.php");
        }
    }

    protected function createResource(string $name): void
    {
        $stub    = $this->getStub('resource');
        $content = $this->replacePlaceholders($stub, $name);

        $resourcesPath = app_path('Infrastructure/API/Resources');
        if (! $this->files->isDirectory($resourcesPath)) {
            $this->files->makeDirectory($resourcesPath, 0755, true);
        }

        $this->files->put("{$resourcesPath}/{$name}Resource.php", $content);
        $this->info("Created: Infrastructure/API/Resources/{$name}Resource.php");
    }

    protected function createTests(string $name): void
    {
        $testsPath = base_path('tests/Feature');
        if (! $this->files->isDirectory($testsPath)) {
            $this->files->makeDirectory($testsPath, 0755, true);
        }

        $stub    = $this->getStub('test');
        $content = $this->replacePlaceholders($stub, $name);

        $pluralName = Str::plural($name);
        $this->files->put("{$testsPath}/{$pluralName}Test.php", $content);
        $this->info("Created: tests/Feature/{$pluralName}Test.php");
    }

    protected function createMigration(string $name): void
    {
        $tableName = $this->getTableName($name);
        $this->call('make:migration', [
            'name'     => "create_{$tableName}_table",
            '--create' => $tableName,
        ]);
        $this->info("Created migration for table: {$tableName}");
    }

    protected function addGitKeepFiles(string $name): void
    {
        $pluralName  = Str::plural($name);
        $directories = [
            app_path("Domain/{$pluralName}"),
            app_path("Domain/{$pluralName}/Enums"),
            app_path("Domain/{$pluralName}/Events"),
            app_path("Application/Actions/{$pluralName}"),
            app_path('Infrastructure/API/Requests'),
            app_path('Infrastructure/API/Resources'),
        ];

        foreach ($directories as $directory) {
            if ($this->files->isDirectory($directory) && count($this->files->files($directory)) === 0) {
                $this->files->put("{$directory}/.gitkeep", '');
                $this->info("Added .gitkeep to: {$directory}");
            }
        }
    }

    protected function replacePlaceholders(string $content, string $name, array $extra = []): string
    {
        $pluralName     = Str::plural($name);
        $domainVariable = Str::camel($name);
        $tableName      = $this->getTableName($name);

        $replacements = array_merge([
            '{{DomainName}}'       => $name,
            '{{PluralDomainName}}' => $pluralName,
            '{{domainVariable}}'   => $domainVariable,
            '{{domain-table}}'     => $tableName,
        ], $extra);

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    protected function getTableName(string $name): string
    {
        return Str::snake(Str::plural($name));
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
