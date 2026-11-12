<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/*
| The generated code lands in an app that runs its own formatter and static
| analyser. Valid PHP is not enough: a file Pint rewrites, or that PHPStan
| rejects, has to be fixed by hand right after it is generated. These tests
| run the real tools on what the generators write, the way
| GeneratedRulesTest runs the real phparkitect.
*/

/**
 * Run every generator into the testbench app and copy what they wrote into a
 * fresh directory, so the tools only see generated code.
 *
 * @param  array<string, mixed>  $config
 */
function generateQualitySample(array $config): string
{
    foreach ($config as $key => $value) {
        config()->set("clean-architecture.{$key}", $value);
    }

    Artisan::call('clean-arch:install');

    test()->artisan('clean-arch:make-domain', ['name' => 'Ledger'])
        ->expectsConfirmation('Would you like to generate an Observer?', 'yes')
        ->expectsConfirmation('Would you like to generate a Listener?', 'yes')
        ->expectsConfirmation('Would you like to generate a Job?', 'yes')
        ->expectsConfirmation('Would you like to generate a Mail?', 'yes')
        ->expectsConfirmation('Would you like to generate a Notification?', 'yes')
        ->expectsConfirmation('Would you like to generate an Export?', 'yes')
        ->assertExitCode(0);

    Artisan::call('clean-arch:make-action', ['name' => 'CloseLedger', 'domain' => 'Ledger']);
    Artisan::call('clean-arch:make-controller', ['name' => 'Ledger', '--web' => true]);
    Artisan::call('clean-arch:make-service', ['name' => 'Ledger']);
    Artisan::call('clean-arch:make-arch-rules', ['--force' => true]);

    $target = sys_get_temp_dir() . '/clean-arch-quality-' . uniqid();

    Artisan::call('clean-arch:generate-package', ['name' => 'ledger-tools', 'vendor' => 'acme', '--path' => "{$target}/package"]);

    foreach (['Domain', 'Application', 'Infrastructure'] as $layer) {
        File::copyDirectory(app_path($layer), "{$target}/app/{$layer}");
    }

    File::copyDirectory(base_path('tests/Feature/Ledgers'), "{$target}/tests/Feature/Ledgers");
    File::ensureDirectoryExists("{$target}/database/factories");
    File::ensureDirectoryExists("{$target}/database/migrations");
    File::ensureDirectoryExists("{$target}/config");
    File::copy(database_path('factories/LedgerFactory.php'), "{$target}/database/factories/LedgerFactory.php");

    foreach (File::files(database_path('migrations')) as $file) {
        if (str_contains($file->getFilename(), '_create_ledgers_table')) {
            File::copy($file->getRealPath(), "{$target}/database/migrations/{$file->getFilename()}");
        }
    }

    File::copy(base_path('phparkitect.php'), "{$target}/phparkitect.php");
    File::copy(config_path('clean-architecture.php'), "{$target}/config/clean-architecture.php");

    return $target;
}

function cleanQualitySample(): void
{
    foreach ([app_path('Domain'), app_path('Application'), app_path('Infrastructure'), base_path('tests/Feature/Ledgers')] as $directory) {
        File::deleteDirectory($directory);
    }

    foreach (File::files(database_path('migrations')) as $file) {
        if (str_contains($file->getFilename(), '_create_ledgers_table')) {
            File::delete($file->getRealPath());
        }
    }

    File::delete([
        database_path('factories/LedgerFactory.php'),
        base_path('phparkitect.php'),
        base_path('CLEAN_ARCHITECTURE.md'),
        config_path('clean-architecture.php'),
    ]);
}

/**
 * @param  list<string>  $command
 * @return array{exitCode: int, output: string}
 */
function runQualityTool(array $command): array
{
    $process = new Process($command, dirname(__DIR__, 2));
    $process->setTimeout(300);
    $process->run();

    return [
        'exitCode' => (int) $process->getExitCode(),
        'output'   => $process->getOutput() . $process->getErrorOutput(),
    ];
}

dataset('generator configurations', [
    'defaults' => [[]],
    'variants' => [[
        'default_namespace'                              => 'App',
        'generation.extend_base_classes'                 => false,
        'generation.model_directory'                     => null,
        'generation.job_prefix'                          => null,
        'validation.custom_messages'                     => false,
        'validation.application_infrastructure_allowed'  => ['Mail'],
        'validation.rules.no_classes_outside_layers'     => true,
        'validation.rules.no_commands_in_infrastructure' => true,
    ]],
]);

describe('Generated code quality', function () {
    afterEach(function () {
        if (isset($this->sample)) {
            File::deleteDirectory($this->sample);
        }

        cleanQualitySample();
    });

    it('passes pint with the laravel preset', function (array $config) {
        cleanQualitySample();
        $this->sample = generateQualitySample($config);

        File::put("{$this->sample}/pint.json", json_encode(['preset' => 'laravel']));

        $root   = dirname(__DIR__, 2);
        $result = runQualityTool([
            $root . '/vendor/bin/pint',
            '--test',
            '--config=' . "{$this->sample}/pint.json",
            $this->sample,
        ]);

        expect($result['exitCode'])->toBe(0, $result['output']);
    })->with('generator configurations');

    it('passes phpstan level 7 with larastan', function (array $config) {
        cleanQualitySample();
        $this->sample = generateQualitySample($config);
        $root         = dirname(__DIR__, 2);

        // Stands in for the app's composer autoloader: Larastan instantiates
        // models to read their table, so the classes have to load.
        File::put("{$this->sample}/autoload.php", <<<'PHP'
            <?php

            spl_autoload_register(function (string $class): void {
                foreach (['App\\' => __DIR__.'/app/', 'Database\\Factories\\' => __DIR__.'/database/factories/', 'Acme\\LedgerTools\\' => __DIR__.'/package/src/'] as $prefix => $directory) {
                    $file = $directory.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

                    if (str_starts_with($class, $prefix) && is_file($file)) {
                        require $file;
                    }
                }
            });
            PHP);

        // The web controller and the mailable name views the app has to
        // create, which Larastan checks against the views it can find.
        File::put("{$this->sample}/phpstan.neon", <<<NEON
            includes:
                - {$root}/vendor/larastan/larastan/extension.neon

            parameters:
                level: 7
                paths:
                    - {$this->sample}/app
                    - {$this->sample}/database
                    - {$this->sample}/package/src
                databaseMigrationsPath:
                    - {$this->sample}/database/migrations
                bootstrapFiles:
                    - {$this->sample}/autoload.php
                ignoreErrors:
                    - '#expects view-string\|null, string given#'
            NEON);

        $result = runQualityTool([
            $root . '/vendor/bin/phpstan',
            'analyse',
            '--configuration=' . "{$this->sample}/phpstan.neon",
            '--no-progress',
            '--error-format=raw',
            '--memory-limit=1G',
        ]);

        expect($result['exitCode'])->toBe(0, $result['output']);
    })->with('generator configurations');
});
