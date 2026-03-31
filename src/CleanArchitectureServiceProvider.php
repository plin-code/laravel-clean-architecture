<?php

namespace PlinCode\LaravelCleanArchitecture;

use Illuminate\Support\ServiceProvider;
use PlinCode\LaravelCleanArchitecture\Commands\GeneratePackageCommand;
use PlinCode\LaravelCleanArchitecture\Commands\InstallCleanArchitectureCommand;
use PlinCode\LaravelCleanArchitecture\Commands\MakeActionCommand;
use PlinCode\LaravelCleanArchitecture\Commands\MakeControllerCommand;
use PlinCode\LaravelCleanArchitecture\Commands\MakeDomainCommand;
use PlinCode\LaravelCleanArchitecture\Commands\MakeExportCommand;
use PlinCode\LaravelCleanArchitecture\Commands\MakeJobCommand;
use PlinCode\LaravelCleanArchitecture\Commands\MakeListenerCommand;
use PlinCode\LaravelCleanArchitecture\Commands\MakeMailCommand;
use PlinCode\LaravelCleanArchitecture\Commands\MakeNotificationCommand;
use PlinCode\LaravelCleanArchitecture\Commands\MakeObserverCommand;
use PlinCode\LaravelCleanArchitecture\Commands\MakeServiceCommand;
use PlinCode\LaravelCleanArchitecture\Commands\ValidateArchitectureCommand;

class CleanArchitectureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCleanArchitectureCommand::class,
                MakeDomainCommand::class,
                MakeActionCommand::class,
                MakeServiceCommand::class,
                MakeControllerCommand::class,
                MakeObserverCommand::class,
                MakeListenerCommand::class,
                MakeJobCommand::class,
                MakeMailCommand::class,
                MakeNotificationCommand::class,
                MakeExportCommand::class,
                ValidateArchitectureCommand::class,
                GeneratePackageCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../stubs' => base_path('stubs/clean-architecture'),
        ], 'clean-architecture-stubs');

        $this->publishes([
            __DIR__ . '/../config/clean-architecture.php' => config_path('clean-architecture.php'),
        ], 'clean-architecture-config');
    }
}
