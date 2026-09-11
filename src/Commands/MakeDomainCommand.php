<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;
use PlinCode\LaravelCleanArchitecture\Concerns\WritesFiles;

class MakeDomainCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;
    use WritesFiles;

    protected $signature = 'clean-arch:make-domain {name : The name of the domain}
                        {--force : Overwrite existing files}
                        {--no-base : Do not extend BaseService and BaseAction}';

    protected $description = 'Create a new domain with complete Clean Architecture structure';

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

        $this->info("Creating domain: {$name}");

        $this->createDomainModel($name);
        $this->createDomainEnums($name);
        $this->createDomainEvents($name);
        $this->createActions($name);
        $this->createService($name);
        $this->createController($name);
        $this->createRequests($name);
        $this->createResource($name);
        $this->createTests($name);
        $this->createMigration($name);

        if ($this->confirm('Would you like to generate an Observer?', false)) {
            $this->createObserver($name);
        }
        if ($this->confirm('Would you like to generate a Listener?', false)) {
            $this->createListener($name);
        }
        if ($this->confirm('Would you like to generate a Job?', false)) {
            $this->createJob($name);
        }
        if ($this->confirm('Would you like to generate a Mail?', false)) {
            $this->createMail($name);
        }
        if ($this->confirm('Would you like to generate a Notification?', false)) {
            $this->createNotification($name);
        }
        if ($this->confirm('Would you like to generate an Export?', false)) {
            $this->createExport($name);
        }

        $this->addGitKeepFiles($name);

        $this->info("Domain {$name} created successfully!");

        return self::SUCCESS;
    }

    protected function createDomainModel(string $name): void
    {
        $stub    = $this->getStub('domain-model');
        $content = $this->replacePlaceholders($stub, $name);

        $pluralName   = Str::plural($name);
        $modelSegment = $this->modelDirectorySegment();
        $directory    = $this->layerDirectory('domain') . "/{$pluralName}" . ($modelSegment !== '' ? "/{$modelSegment}" : '');

        if (! $this->files->isDirectory(base_path($directory))) {
            $this->files->makeDirectory(base_path($directory), 0755, true);
        }

        $this->writeFile("{$directory}/{$name}.php", $content);
    }

    protected function createDomainEnums(string $name): void
    {
        $stub    = $this->getStub('domain-enum');
        $content = $this->replacePlaceholders($stub, $name);

        $pluralName = Str::plural($name);
        $directory  = $this->layerDirectory('domain') . "/{$pluralName}/Enums";
        $enumsPath  = base_path($directory);

        if (! $this->files->isDirectory($enumsPath)) {
            $this->files->makeDirectory($enumsPath, 0755, true);
        }

        $this->writeFile("{$directory}/{$name}Status.php", $content);
    }

    protected function createDomainEvents(string $name): void
    {
        $events     = ['Created', 'Updated', 'Deleted'];
        $pluralName = Str::plural($name);
        $directory  = $this->layerDirectory('domain') . "/{$pluralName}/Events";
        $eventsPath = base_path($directory);

        if (! $this->files->isDirectory($eventsPath)) {
            $this->files->makeDirectory($eventsPath, 0755, true);
        }

        foreach ($events as $event) {
            $stub    = $this->getStub('domain-event');
            $content = $this->replacePlaceholders($stub, $name, [
                '{{EventName}}' => $name . $event,
            ]);

            $this->writeFile("{$directory}/{$name}{$event}.php", $content);
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
        $directory   = $this->layerDirectory('application') . "/Actions/{$pluralName}";
        $actionsPath = base_path($directory);

        if (! $this->files->isDirectory($actionsPath)) {
            $this->files->makeDirectory($actionsPath, 0755, true);
        }

        $extend = $this->shouldExtendBaseClasses((bool) $this->option('no-base'));

        foreach ($actions as $action => $requestClass) {
            $stub    = $this->getStub('action');
            $content = $this->replacePlaceholders($stub, $name, array_merge($this->baseActionReplacements($extend), [
                '{{ActionName}}'  => $action . $name . 'Action',
                '{{RequestName}}' => $requestClass ?: 'Request',
            ]));

            $this->writeFile("{$directory}/{$action}{$name}Action.php", $content);
        }
    }

    protected function createService(string $name): void
    {
        $extend  = $this->shouldExtendBaseClasses((bool) $this->option('no-base'));
        $stub    = $this->getStub('service');
        $content = $this->replacePlaceholders($stub, $name, $this->baseServiceReplacements($extend));

        $directory    = $this->layerDirectory('application') . '/Services';
        $servicesPath = base_path($directory);
        if (! $this->files->isDirectory($servicesPath)) {
            $this->files->makeDirectory($servicesPath, 0755, true);
        }

        $this->writeFile("{$directory}/{$name}Service.php", $content);
    }

    protected function createController(string $name): void
    {
        $stub    = $this->getStub('controller');
        $content = $this->replacePlaceholders($stub, $name);

        $pluralName      = Str::plural($name);
        $directory       = $this->layerDirectory('infrastructure') . '/Http/Controllers/Api';
        $controllersPath = base_path($directory);

        if (! $this->files->isDirectory($controllersPath)) {
            $this->files->makeDirectory($controllersPath, 0755, true);
        }

        $this->writeFile("{$directory}/{$pluralName}Controller.php", $content);
    }

    protected function createRequests(string $name): void
    {
        $requests     = ['Create', 'Update'];
        $directory    = $this->layerDirectory('infrastructure') . '/Http/Requests';
        $requestsPath = base_path($directory);

        if (! $this->files->isDirectory($requestsPath)) {
            $this->files->makeDirectory($requestsPath, 0755, true);
        }

        foreach ($requests as $request) {
            $stub = $this->getStub('request');
            $stub = $this->applyOptionalBlock(
                $stub,
                'custom_messages',
                (bool) config('clean-architecture.validation.custom_messages', true)
            );
            $content = $this->replacePlaceholders($stub, $name, [
                '{{RequestName}}' => $request . $name . 'Request',
            ]);

            $this->writeFile("{$directory}/{$request}{$name}Request.php", $content);
        }
    }

    protected function createResource(string $name): void
    {
        $stub    = $this->getStub('resource');
        $content = $this->replacePlaceholders($stub, $name);

        $directory     = $this->layerDirectory('infrastructure') . '/Http/Resources';
        $resourcesPath = base_path($directory);
        if (! $this->files->isDirectory($resourcesPath)) {
            $this->files->makeDirectory($resourcesPath, 0755, true);
        }

        $this->writeFile("{$directory}/{$name}Resource.php", $content);
    }

    protected function createTests(string $name): void
    {
        $pluralName = Str::plural($name);
        $testsPath  = base_path("tests/Feature/{$pluralName}");

        if (! $this->files->isDirectory($testsPath)) {
            $this->files->makeDirectory($testsPath, 0755, true);
        }

        $stub    = $this->getStub('test');
        $content = $this->replacePlaceholders($stub, $name);

        $this->writeFile("tests/Feature/{$pluralName}/{$pluralName}Test.php", $content);
    }

    /**
     * The migration file name carries a timestamp, so a plain path check
     * never matches an existing migration for the same table. Any existing
     * `*_create_{table}_table.php` is treated as the file to skip or
     * overwrite (keeping its original name) instead of adding a second
     * migration for the same table.
     */
    protected function createMigration(string $name): void
    {
        $tableName = $this->getTableName($name);
        $stub      = $this->getStub('migration');
        $content   = $this->replacePlaceholders($stub, $name);

        $migrationsPath = database_path('migrations');
        if (! $this->files->isDirectory($migrationsPath)) {
            $this->files->makeDirectory($migrationsPath, 0755, true);
        }

        $existing = collect($this->files->files($migrationsPath))
            ->first(fn ($file): bool => (bool) preg_match('/_create_' . preg_quote($tableName, '/') . '_table\.php$/', $file->getFilename()));

        $fileName = $existing?->getFilename() ?? date('Y_m_d_His') . "_create_{$tableName}_table.php";

        $this->writePath("{$migrationsPath}/{$fileName}", $content, "database/migrations/{$fileName}");
    }

    protected function addGitKeepFiles(string $name): void
    {
        $pluralName     = Str::plural($name);
        $domain         = $this->layerDirectory('domain');
        $application    = $this->layerDirectory('application');
        $infrastructure = $this->layerDirectory('infrastructure');
        $modelSegment   = $this->modelDirectorySegment();
        $directories    = array_filter([
            $modelSegment !== '' ? base_path("{$domain}/{$pluralName}/{$modelSegment}") : null,
            base_path("{$domain}/{$pluralName}/Enums"),
            base_path("{$domain}/{$pluralName}/Events"),
            base_path("{$application}/Actions/{$pluralName}"),
            base_path("{$infrastructure}/Http/Requests"),
            base_path("{$infrastructure}/Http/Resources"),
        ]);

        foreach ($directories as $directory) {
            if ($this->files->isDirectory($directory) && count($this->files->files($directory)) === 0) {
                $this->files->put("{$directory}/.gitkeep", '');
            }
        }
    }

    protected function createObserver(string $name): void
    {
        $stub    = $this->getStub('observer');
        $content = $this->replacePlaceholders($stub, $name);

        $pluralName   = Str::plural($name);
        $directory    = $this->layerDirectory('infrastructure') . "/Observers/{$pluralName}";
        $observerPath = base_path($directory);

        if (! $this->files->isDirectory($observerPath)) {
            $this->files->makeDirectory($observerPath, 0755, true);
        }

        $this->writeFile("{$directory}/{$name}Observer.php", $content);
    }

    protected function createListener(string $name): void
    {
        $stub    = $this->getStub('listener');
        $content = $this->replacePlaceholders($stub, $name);

        $directory    = $this->layerDirectory('application') . '/Listeners';
        $listenerPath = base_path($directory);

        if (! $this->files->isDirectory($listenerPath)) {
            $this->files->makeDirectory($listenerPath, 0755, true);
        }

        $this->writeFile("{$directory}/{$name}EventListener.php", $content);
    }

    protected function createJob(string $name): void
    {
        $stub    = $this->getStub('job');
        $content = $this->replacePlaceholders($stub, $name);

        $directory = $this->layerDirectory('application') . '/Jobs';
        $jobsPath  = base_path($directory);

        if (! $this->files->isDirectory($jobsPath)) {
            $this->files->makeDirectory($jobsPath, 0755, true);
        }

        $this->writeFile("{$directory}/Process{$name}Job.php", $content);
    }

    protected function createMail(string $name): void
    {
        $stub    = $this->getStub('mail');
        $content = $this->replacePlaceholders($stub, $name);

        $directory = $this->layerDirectory('infrastructure') . '/Mail';
        $mailPath  = base_path($directory);

        if (! $this->files->isDirectory($mailPath)) {
            $this->files->makeDirectory($mailPath, 0755, true);
        }

        $this->writeFile("{$directory}/{$name}Mail.php", $content);
    }

    protected function createNotification(string $name): void
    {
        $stub    = $this->getStub('notification');
        $content = $this->replacePlaceholders($stub, $name);

        $directory         = $this->layerDirectory('infrastructure') . '/Notifications';
        $notificationsPath = base_path($directory);

        if (! $this->files->isDirectory($notificationsPath)) {
            $this->files->makeDirectory($notificationsPath, 0755, true);
        }

        $this->writeFile("{$directory}/{$name}Notification.php", $content);
    }

    protected function createExport(string $name): void
    {
        $stub    = $this->getStub('export');
        $content = $this->replacePlaceholders($stub, $name);

        $directory   = $this->layerDirectory('infrastructure') . '/Exports';
        $exportsPath = base_path($directory);

        if (! $this->files->isDirectory($exportsPath)) {
            $this->files->makeDirectory($exportsPath, 0755, true);
        }

        $this->writeFile("{$directory}/{$name}Export.php", $content);
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
}
