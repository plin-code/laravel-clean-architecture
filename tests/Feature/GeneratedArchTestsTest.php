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

    it('fails on every rule the fixture breaks and on nothing else', function () {
        expect($this->result['exitCode'])->toBe(1)
            ->and($this->result['output'])->toContain('9 failed, 6 passed');
    });

    it('reports a domain class that uses the other layers, through an alias or a full name', function () {
        expect($this->result['output'])
            ->toContain('Domain\Products\Catalog uses ArchTestsFixture\Application\Actions\Products\PublishProductAction')
            ->toContain('Domain\Products\Catalog uses ArchTestsFixture\Infrastructure\Mail\ProductPublishedMail');
    });

    it('does not take a layer named in a comment or a string for a dependency', function () {
        expect($this->result['output'])->not->toContain('Domain\Products\Product uses');
    });

    it('reports every infrastructure import of the application layer, grouped imports too', function () {
        expect($this->result['output'])
            ->toContain('ProductService uses ArchTestsFixture\Infrastructure\Http\Requests\StoreProductRequest')
            ->toContain('ProductService uses ArchTestsFixture\Infrastructure\Mail\ProductPublishedMail');
    });

    it('reports an observer in the domain layer', function () {
        expect($this->result['output'])->toContain("0 => 'ArchTestsFixture\Domain\Products\ProductObserver'");
    });

    it('reports a queued job in the infrastructure layer, not a queued mail', function () {
        expect($this->result['output'])
            ->toContain("0 => 'ArchTestsFixture\Infrastructure\Jobs\SyncProductsJob'")
            ->not->toContain("=> 'ArchTestsFixture\Infrastructure\Mail\ProductPublishedMail'");
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
            ->not->toContain('StoreProductRequest has no rules()');
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
        ->toContain('8 failed, 5 passed')
        ->not->toContain('ListenersTest');
});

it('lets the application import an allowed infrastructure namespace', function () {
    config()->set('clean-architecture.validation.application_infrastructure_allowed', ['Mail']);

    try {
        $result = runGeneratedArchTests();
    } finally {
        File::deleteDirectory(archTestsFixture() . '/tests');
    }

    expect($result['output'])
        ->toContain('ProductService uses ArchTestsFixture\\Infrastructure\\Http\\Requests\\StoreProductRequest')
        ->not->toContain('ProductService uses ArchTestsFixture\\Infrastructure\\Mail');
});
