<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallCleanArchitectureCommand extends Command
{
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
        $directories = [
            'app/Application/Actions',
            'app/Application/Services',
            'app/Application/Jobs',
            'app/Application/Console/Commands',
            'app/Application/Listeners',
            'app/Domain',
            'app/Infrastructure/API/Controllers',
            'app/Infrastructure/API/Requests',
            'app/Infrastructure/API/Resources',
            'app/Infrastructure/UI/Web/Controllers',
            'app/Infrastructure/UI/Web/Views',
            'app/Infrastructure/Mail',
            'app/Infrastructure/Notifications',
            'app/Infrastructure/Observers',
            'app/Infrastructure/Exceptions',
            'app/Infrastructure/Middleware',
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
        $stub = $this->getStub('base-model');
        if (! $this->files->isDirectory(app_path('Domain/Shared'))) {
            $this->files->makeDirectory(app_path('Domain/Shared'), 0755, true);
        }
        $this->files->put(
            app_path('Domain/Shared/BaseModel.php'),
            $stub
        );
        $this->info('Created: Domain/Shared/BaseModel.php');
    }

    protected function createBaseController(): void
    {
        $stub = $this->getStub('base-controller');
        $this->files->put(
            app_path('Infrastructure/API/Controllers/Controller.php'),
            $stub
        );
        $this->info('Created: Infrastructure/API/Controllers/Controller.php');
    }

    protected function createBaseAction(): void
    {
        $stub = $this->getStub('base-action');
        $this->files->put(
            app_path('Application/Actions/BaseAction.php'),
            $stub
        );
        $this->info('Created: Application/Actions/BaseAction.php');
    }

    protected function createBaseService(): void
    {
        $stub = $this->getStub('base-service');
        if (! $this->files->isDirectory(app_path('Application/Services'))) {
            $this->files->makeDirectory(app_path('Application/Services'), 0755, true);
        }
        $this->files->put(
            app_path('Application/Services/BaseService.php'),
            $stub
        );
        $this->info('Created: Application/Services/BaseService.php');
    }

    protected function createBaseRequest(): void
    {
        $stub = $this->getStub('base-request');
        $this->files->put(
            app_path('Infrastructure/API/Requests/BaseRequest.php'),
            $stub
        );
        $this->info('Created: Infrastructure/API/Requests/BaseRequest.php');
    }

    protected function createExceptionClasses(): void
    {
        $exceptions = [
            'DomainException'        => 'domain-exception',
            'ValidationException'    => 'validation-exception',
            'BusinessLogicException' => 'business-logic-exception',
        ];

        foreach ($exceptions as $className => $stub) {
            $content = $this->getStub($stub);
            $this->files->put(
                app_path("Infrastructure/Exceptions/{$className}.php"),
                $content
            );
            $this->info("Created: Infrastructure/Exceptions/{$className}.php");
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

    protected function getStub(string $stub): string
    {
        $stubPath = __DIR__ . "/../../stubs/{$stub}.stub";

        if (! $this->files->exists($stubPath)) {
            throw new \Exception("Stub file not found: {$stubPath}");
        }

        return $this->files->get($stubPath);
    }
}
