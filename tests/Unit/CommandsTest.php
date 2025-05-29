<?php

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

describe('Commands', function () {
    it('can execute install command without errors', function () {
        $exitCode = Artisan::call('clean-arch:install', ['--help' => true]);

        expect($exitCode)->toBe(0);
    });

    it('shows help for make domain command', function () {
        $exitCode = Artisan::call('clean-arch:make-domain', ['--help' => true]);

        expect($exitCode)->toBe(0);
    });

    it('shows help for make action command', function () {
        $exitCode = Artisan::call('clean-arch:make-action', ['--help' => true]);

        expect($exitCode)->toBe(0);
    });

    it('shows help for make service command', function () {
        $exitCode = Artisan::call('clean-arch:make-service', ['--help' => true]);

        expect($exitCode)->toBe(0);
    });

    it('shows help for make controller command', function () {
        $exitCode = Artisan::call('clean-arch:make-controller', ['--help' => true]);

        expect($exitCode)->toBe(0);
    });

    it('shows help for generate package command', function () {
        $exitCode = Artisan::call('clean-arch:generate-package', ['--help' => true]);

        expect($exitCode)->toBe(0);
    });
});

describe('Command Registration', function () {
    it('registers all commands in artisan', function () {
        $commands = Artisan::all();

        expect($commands)
            ->toHaveKey('clean-arch:install')
            ->toHaveKey('clean-arch:make-domain')
            ->toHaveKey('clean-arch:make-action')
            ->toHaveKey('clean-arch:make-service')
            ->toHaveKey('clean-arch:make-controller')
            ->toHaveKey('clean-arch:generate-package');
    });

    it('has command instances that extend laravel command class', function () {
        $commands = Artisan::all();

        expect($commands['clean-arch:install'])
            ->toBeInstanceOf(Command::class);
    });
});
