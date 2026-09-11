<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;

class MakeDomainCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;

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
        $name  = $this->argument('name');
        $force = $this->option('force');

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
        $path         = base_path("{$directory}/{$name}.php");

        if (! $this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0755, true);
        }

        $this->files->put($path, $content);
        $this->info("Created: {$directory}/{$name}.php");
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

        $this->files->put("{$enumsPath}/{$name}Status.php", $content);
        $this->info("Created: {$directory}/{$name}Status.php");
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

            $this->files->put("{$eventsPath}/{$name}{$event}.php", $content);
            $this->info("Created: {$directory}/{$name}{$event}.php");
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

            $this->files->put("{$actionsPath}/{$action}{$name}Action.php", $content);
            $this->info("Created: {$directory}/{$action}{$name}Action.php");
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

        $this->files->put("{$servicesPath}/{$name}Service.php", $content);
        $this->info("Created: {$directory}/{$name}Service.php");
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

        $this->files->put("{$controllersPath}/{$pluralName}Controller.php", $content);
        $this->info("Created: {$directory}/{$pluralName}Controller.php");
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

            $this->files->put("{$requestsPath}/{$request}{$name}Request.php", $content);
            $this->info("Created: {$directory}/{$request}{$name}Request.php");
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

        $this->files->put("{$resourcesPath}/{$name}Resource.php", $content);
        $this->info("Created: {$directory}/{$name}Resource.php");
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

        $this->files->put("{$testsPath}/{$pluralName}Test.php", $content);
        $this->info("Created: tests/Feature/{$pluralName}/{$pluralName}Test.php");
    }

    protected function createMigration(string $name): void
    {
        $tableName = $this->getTableName($name);
        $stub      = $this->getStub('migration');
        $content   = $this->replacePlaceholders($stub, $name);

        $migrationsPath = database_path('migrations');
        if (! $this->files->isDirectory($migrationsPath)) {
            $this->files->makeDirectory($migrationsPath, 0755, true);
        }

        $fileName = date('Y_m_d_His') . "_create_{$tableName}_table.php";

        $this->files->put("{$migrationsPath}/{$fileName}", $content);
        $this->info("Created: database/migrations/{$fileName}");
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

        $this->files->put("{$observerPath}/{$name}Observer.php", $content);
        $this->info("Created: {$directory}/{$name}Observer.php");
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

        $this->files->put("{$listenerPath}/{$name}EventListener.php", $content);
        $this->info("Created: {$directory}/{$name}EventListener.php");
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

        $this->files->put("{$jobsPath}/Process{$name}Job.php", $content);
        $this->info("Created: {$directory}/Process{$name}Job.php");
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

        $this->files->put("{$mailPath}/{$name}Mail.php", $content);
        $this->info("Created: {$directory}/{$name}Mail.php");
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

        $this->files->put("{$notificationsPath}/{$name}Notification.php", $content);
        $this->info("Created: {$directory}/{$name}Notification.php");
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

        $this->files->put("{$exportsPath}/{$name}Export.php", $content);
        $this->info("Created: {$directory}/{$name}Export.php");
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
