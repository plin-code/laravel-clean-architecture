<?php

use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\CleanArchitectureServiceProvider;
use PlinCode\LaravelCleanArchitecture\Commands\GeneratePackageCommand;
use PlinCode\LaravelCleanArchitecture\Commands\InstallCleanArchitectureCommand;
use PlinCode\LaravelCleanArchitecture\Commands\MakeActionCommand;
use PlinCode\LaravelCleanArchitecture\Commands\MakeControllerCommand;
use PlinCode\LaravelCleanArchitecture\Commands\MakeDomainCommand;
use PlinCode\LaravelCleanArchitecture\Commands\MakeServiceCommand;

describe('CleanArchitectureServiceProvider', function () {
    it('does not register commands when not running in console', function () {
        $mockApp = mock('Illuminate\Foundation\Application');
        $mockApp->shouldReceive('runningInConsole')->andReturn(false);
        $mockApp->shouldNotReceive('commands');

        $provider = new CleanArchitectureServiceProvider($mockApp);
        $provider->register();
    });

    it('has correct command instances available', function () {
        $filesystem = new Filesystem;
        $commands   = [
            'clean-arch:install'          => InstallCleanArchitectureCommand::class,
            'clean-arch:make-domain'      => MakeDomainCommand::class,
            'clean-arch:make-action'      => MakeActionCommand::class,
            'clean-arch:make-service'     => MakeServiceCommand::class,
            'clean-arch:make-controller'  => MakeControllerCommand::class,
            'clean-arch:generate-package' => GeneratePackageCommand::class,
        ];

        foreach ($commands as $signature => $class) {
            $instance = new $class($filesystem);

            expect($instance)->toBeInstanceOf(\Illuminate\Console\Command::class);
            expect($instance->getName())->toBe($signature);
        }
    });

    it('publishes correct assets', function () {
        $provider = new CleanArchitectureServiceProvider($this->app);
        $provider->boot();

        // Check that assets were published by looking at the provider's publishes array
        $publishes = $provider::$publishes[CleanArchitectureServiceProvider::class] ?? [];

        expect($publishes)->not->toBeEmpty();
    });
});
