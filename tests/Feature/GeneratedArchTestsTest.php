<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Run the real Pest against the tests the package generates.
 *
 * Asserting on the text of the generated files would only prove they were
 * written. These tests exist to fail on a class in the wrong shape, so the
 * fixture project in tests/fixtures/arch-tests holds one class that follows
 * each convention and one that breaks it, and Pest runs on it for real.
 */
function archTestsFixture(): string
{
    return dirname(__DIR__) . '/fixtures/arch-tests';
}

/**
 * Generate the tests for the fixture project and run them.
 *
 * @return array{exitCode: int, output: string}
 */
function runGeneratedArchTests(): array
{
    config()->set('clean-architecture.default_namespace', 'ArchTestsFixture');

    Artisan::call('clean-arch:make-arch-tests', ['--force' => true]);

    File::copyDirectory(base_path('tests/Arch'), archTestsFixture() . '/tests/Arch');
    File::deleteDirectory(base_path('tests/Arch'));

    $process = new Process(
        [dirname(__DIR__, 2) . '/vendor/bin/pest', '--no-coverage', '--colors=never'],
        archTestsFixture(),
    );

    $process->run();

    return [
        'exitCode' => (int) $process->getExitCode(),
        'output'   => $process->getOutput() . $process->getErrorOutput(),
    ];
}

describe('Generated architecture tests', function () {
    beforeEach(function () {
        $this->result = runGeneratedArchTests();
    });

    afterEach(function () {
        File::deleteDirectory(archTestsFixture() . '/tests');
    });

    it('fails on the four classes of the fixture that break a convention', function () {
        expect($this->result['exitCode'])->toBe(1)
            ->and($this->result['output'])->toContain('4 failed, 6 passed');
    });

    it('reports an action that is not named after a verb and a subject', function () {
        expect($this->result['output'])
            ->toContain("Failed asserting that 'ExportAction' matches PCRE pattern")
            ->not->toContain("Failed asserting that 'PublishProductAction'");
    });

    it('reports an action with a second public method', function () {
        expect($this->result['output'])
            ->toContain('ArchiveProductAction must expose only execute(), it exposes: execute, restore');
    });

    it('leaves out abstract classes and classes that are not actions', function () {
        expect($this->result['output'])
            ->not->toContain('BaseAction')
            ->not->toContain('ProductData');
    });

    it('reports a listener that queries or writes, with the lines', function () {
        expect($this->result['output'])
            ->toContain('CloseOrdersListener queries or writes (where() on line 9, update() on line 9, DB:: on line 11)')
            ->not->toContain('NotifyTeamListener');
    });

    it('reports a form request without rules', function () {
        expect($this->result['output'])
            ->toContain('UpdateProductRequest has no rules(): it validates nothing')
            ->not->toContain('StoreProductRequest');
    });
});

it('passes on a project that has no listeners yet', function () {
    $listeners = archTestsFixture() . '/app/Application/Listeners';
    $aside     = sys_get_temp_dir() . '/arch-tests-listeners-' . uniqid();

    File::moveDirectory($listeners, $aside);

    try {
        $result = runGeneratedArchTests();
    } finally {
        File::moveDirectory($aside, $listeners);
        File::deleteDirectory(archTestsFixture() . '/tests');
    }

    expect($result['output'])
        ->toContain('3 failed, 5 passed')
        ->not->toContain('ListenersTest');
});
