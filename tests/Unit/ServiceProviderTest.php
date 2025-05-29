<?php

use Illuminate\Support\Facades\Artisan;
use PlinCode\LaravelCleanArchitecture\CleanArchitectureServiceProvider;

describe('Service Provider', function () {
    it('registers install command', function () {
        expect(Artisan::all())
            ->toHaveKey('clean-arch:install');
    });

    it('registers make domain command', function () {
        expect(Artisan::all())
            ->toHaveKey('clean-arch:make-domain');
    });

    it('registers make action command', function () {
        expect(Artisan::all())
            ->toHaveKey('clean-arch:make-action');
    });

    it('registers make service command', function () {
        expect(Artisan::all())
            ->toHaveKey('clean-arch:make-service');
    });

    it('registers make controller command', function () {
        expect(Artisan::all())
            ->toHaveKey('clean-arch:make-controller');
    });

    it('registers generate package command', function () {
        expect(Artisan::all())
            ->toHaveKey('clean-arch:generate-package');
    });

    it('is registered in the application', function () {
        $providers = $this->app->getLoadedProviders();

        expect($providers)
            ->toHaveKey(CleanArchitectureServiceProvider::class);
    });
});
