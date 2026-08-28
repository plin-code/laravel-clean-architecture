<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;

class InstallCleanArchitectureCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;

    protected $signature = 'clean-arch:install
                          {--force : Overwrite existing files}';

    protected $description = 'Install Clean Architecture structure in Laravel project';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $this->info('🚀 Installing Clean Architecture...');

        // Create directory structure
        $this->createDirectoryStructure();

        // Create base classes
        $this->createBaseClasses();

        // Update composer.json autoload
        $this->updateComposerAutoload();

        // Create config file
        $this->createConfigFile();

        // Create README
        $this->createReadme();

        $this->info('✅ Clean Architecture installed successfully!');
        $this->newLine();
        $this->info('Next steps:');
        $this->info('1. Run: composer dump-autoload');
        $this->info('2. Create your first domain: php artisan clean-arch:make-domain Users');
        $this->info('3. Check the generated README.md for documentation');

        return self::SUCCESS;
    }

    protected function createDirectoryStructure(): void
    {
        $domain         = $this->layerDirectory('domain');
        $application    = $this->layerDirectory('application');
        $infrastructure = $this->layerDirectory('infrastructure');

        $directories = [
            $domain,
            "{$application}/Actions",
            "{$application}/Services",
            "{$application}/Jobs",
            "{$application}/Listeners",
            "{$application}/Console/Commands",
            "{$infrastructure}/Http/Controllers/Api",
            "{$infrastructure}/Http/Middleware",
            "{$infrastructure}/Http/Requests",
            "{$infrastructure}/Http/Resources",
            "{$infrastructure}/UI",
            "{$infrastructure}/Mail",
            "{$infrastructure}/Notifications",
            "{$infrastructure}/Observers",
            "{$infrastructure}/Exports",
            "{$infrastructure}/Validation",
            "{$infrastructure}/Exceptions",
        ];

        foreach ($directories as $directory) {
            if (! $this->files->isDirectory(base_path($directory))) {
                $this->files->makeDirectory(base_path($directory), 0755, true);
                $this->info("Created directory: {$directory}");
            }
        }
    }

    protected function createBaseClasses(): void
    {
        $this->createBaseModel();
        $this->createBaseController();
        $this->createBaseAction();
        $this->createBaseService();
        $this->createBaseRequest();
        $this->createExceptionClasses();
    }

    protected function createBaseModel(): void
    {
        $stub      = $this->getStub('base-model');
        $directory = $this->layerDirectory('domain') . '/Shared';

        if (! $this->files->isDirectory(base_path($directory))) {
            $this->files->makeDirectory(base_path($directory), 0755, true);
        }

        $this->files->put(base_path("{$directory}/BaseModel.php"), $stub);
        $this->info("Created: {$directory}/BaseModel.php");
    }

    protected function createBaseController(): void
    {
        $stub      = $this->getStub('base-controller');
        $directory = $this->layerDirectory('infrastructure') . '/Http/Controllers';

        if (! $this->files->isDirectory(base_path($directory))) {
            $this->files->makeDirectory(base_path($directory), 0755, true);
        }

        $this->files->put(base_path("{$directory}/Controller.php"), $stub);
        $this->info("Created: {$directory}/Controller.php");
    }

    protected function createBaseAction(): void
    {
        $stub      = $this->getStub('base-action');
        $directory = $this->layerDirectory('application') . '/Actions';

        $this->files->put(base_path("{$directory}/BaseAction.php"), $stub);
        $this->info("Created: {$directory}/BaseAction.php");
    }

    protected function createBaseService(): void
    {
        $stub      = $this->getStub('base-service');
        $directory = $this->layerDirectory('application') . '/Services';

        if (! $this->files->isDirectory(base_path($directory))) {
            $this->files->makeDirectory(base_path($directory), 0755, true);
        }

        $this->files->put(base_path("{$directory}/BaseService.php"), $stub);
        $this->info("Created: {$directory}/BaseService.php");
    }

    protected function createBaseRequest(): void
    {
        $stub      = $this->getStub('base-request');
        $directory = $this->layerDirectory('infrastructure') . '/Http/Requests';

        if (! $this->files->isDirectory(base_path($directory))) {
            $this->files->makeDirectory(base_path($directory), 0755, true);
        }

        $this->files->put(base_path("{$directory}/BaseRequest.php"), $stub);
        $this->info("Created: {$directory}/BaseRequest.php");
    }

    protected function createExceptionClasses(): void
    {
        $exceptions = [
            'DomainException'        => 'domain-exception',
            'ValidationException'    => 'validation-exception',
            'BusinessLogicException' => 'business-logic-exception',
        ];

        $directory = $this->layerDirectory('infrastructure') . '/Exceptions';

        foreach ($exceptions as $className => $stub) {
            $content = $this->getStub($stub);
            $this->files->put(base_path("{$directory}/{$className}.php"), $content);
            $this->info("Created: {$directory}/{$className}.php");
        }
    }

    protected function updateComposerAutoload(): void
    {
        $composerPath = base_path('composer.json');
        $composer     = json_decode($this->files->get($composerPath), true);

        if (! isset($composer['autoload']['psr-4']['App\\'])) {
            $composer['autoload']['psr-4']['App\\'] = 'app/';
            $this->files->put($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Updated composer.json autoload');
        }
    }

    protected function createConfigFile(): void
    {
        $stub = $this->getStub('config');
        if (! $this->files->isDirectory(config_path())) {
            $this->files->makeDirectory(config_path(), 0755, true);
        }
        $this->files->put(config_path('clean-architecture.php'), $stub);
        $this->info('Created: config/clean-architecture.php');
    }

    protected function createReadme(): void
    {
        $stub = $this->getStub('readme');
        $this->files->put(base_path('CLEAN_ARCHITECTURE.md'), $stub);
        $this->info('Created: CLEAN_ARCHITECTURE.md');
    }
}
