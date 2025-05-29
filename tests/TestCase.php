<?php

namespace PlinCode\LaravelCleanArchitecture\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use PlinCode\LaravelCleanArchitecture\CleanArchitectureServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            CleanArchitectureServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
    }
}
