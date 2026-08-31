<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Run the real phparkitect against the config the package generates.
 *
 * The other tests assert the generated file is valid PHP. Valid PHP is not
 * enough: an expression built with the wrong argument type parses and then
 * fails at runtime. Only running the tool proves the contract still holds.
 *
 * The fixture project lives in tests/fixtures/arch and is deliberately absent
 * from the composer autoload map, so autoload.php and autoload-broken.php can
 * decide whether its classes resolve.
 */
function fixtureDirectory(): string
{
    return dirname(__DIR__) . '/fixtures/arch';
}

/**
 * Generate the config for the fixture project and return its path.
 */
function generateFixtureConfig(): string
{
    config()->set('clean-architecture.default_namespace', 'ArchFixture');

    Artisan::call('clean-arch:make-arch-rules', ['--force' => true]);

    $generated = base_path('phparkitect.php');
    $target    = fixtureDirectory() . '/phparkitect.php';

    File::copy($generated, $target);
    File::delete($generated);

    return $target;
}

/**
 * @return array{exitCode: int, output: string}
 */
function runPhparkitect(string $config, string $autoload): array
{
    $root = dirname(__DIR__, 2);

    $process = new Process([
        $root . '/vendor/bin/phparkitect',
        'check',
        '--config=' . $config,
        '--autoload=' . $autoload,
    ], $root);

    $process->run();

    return [
        'exitCode' => (int) $process->getExitCode(),
        'output'   => $process->getOutput() . $process->getErrorOutput(),
    ];
}

describe('Generated phparkitect rules', function () {
    beforeEach(function () {
        $this->config = generateFixtureConfig();
    });

    afterEach(function () {
        File::delete($this->config);
    });

    it('reports the violations of the fixture project', function () {
        $result = runPhparkitect($this->config, fixtureDirectory() . '/autoload.php');

        expect($result['exitCode'])->toBe(1)
            ->and($result['output'])
            ->toContain('ArchFixture\Domain\Models\Product')
            ->toContain('should not depend on these namespaces: ArchFixture\Application')
            ->toContain('ArchFixture\Domain\Models\ProductObserver')
            ->toContain('4 violations detected');
    });

    it('follows inheritance chains the removed validate command could not', function () {
        $result = runPhparkitect($this->config, fixtureDirectory() . '/autoload.php');

        expect($result['output'])
            ->toContain('SyncProductsCommand should not be a Illuminate\Console\Command');
    });

    it('leaves a clean layer alone', function () {
        $result = runPhparkitect($this->config, fixtureDirectory() . '/autoload.php');

        expect($result['exitCode'])->toBe(1)
            ->and($result['output'])->not->toContain('ProductService has')
            ->and($result['output'])->not->toContain('Fatal error');
    });

    it('fails loudly instead of passing silently when autoloading does not resolve', function () {
        $result = runPhparkitect($this->config, fixtureDirectory() . '/autoload-broken.php');

        expect($result['exitCode'])->toBe(1)
            ->and($result['output'])
            ->toContain('autoloading does not resolve the application classes')
            ->and($result['output'])->not->toContain('violations detected');
    });
});
