<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Every `make-*` command (and `clean-arch:generate-package`) declares
 * `{--force : Overwrite existing files}` but historically ignored it,
 * silently destroying hand written code on a second run. This suite pins
 * down the fix: single file commands refuse to overwrite and fail loudly,
 * multi file commands skip the existing file and keep going, and `--force`
 * always overwrites.
 */
function cleanupOverwriteFixtures(): void
{
    foreach ([app_path('Domain'), app_path('Application'), app_path('Infrastructure')] as $dir) {
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }

    foreach (['Anvils', 'Articles'] as $plural) {
        $dir = base_path("tests/Feature/{$plural}");
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }

    $migrationPath = database_path('migrations');
    if (File::isDirectory($migrationPath)) {
        foreach (File::files($migrationPath) as $file) {
            if (str_contains($file->getFilename(), 'create_anvils_table') ||
                str_contains($file->getFilename(), 'create_articles_table')) {
                File::delete($file->getRealPath());
            }
        }
    }

    if (File::isDirectory(base_path('packages'))) {
        File::deleteDirectory(base_path('packages'));
    }

    File::delete(config_path('clean-architecture.php'));
    File::delete(base_path('CLEAN_ARCHITECTURE.md'));
}

describe('make commands honour --force', function () {

    beforeEach(fn () => cleanupOverwriteFixtures());

    afterEach(fn () => cleanupOverwriteFixtures());

    it('refuses to overwrite an existing file and overwrites it with --force', function (string $command, array $arguments, string $file) {
        $path = base_path($file);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, '// custom edit made by a user');

        // Two assertions on a Mockery-backed test double can only match two
        // separate output lines, and this command writes both the path and
        // `--force` on the very same line, so the plain Artisan output is
        // asserted against directly instead of `expectsOutputToContain()`.
        $exitCode = Artisan::call($command, $arguments);
        $output   = Artisan::output();

        expect($exitCode)->toBe(1);
        expect($output)->toContain($file);
        expect($output)->toContain('--force');
        expect(File::get($path))->toBe('// custom edit made by a user');

        $exitCode = Artisan::call($command, array_merge($arguments, ['--force' => true]));

        expect($exitCode)->toBe(0);
        expect(File::get($path))->not->toBe('// custom edit made by a user');
    })->with([
        'action'         => ['clean-arch:make-action', ['name' => 'CreateAnvil', 'domain' => 'Anvil'], 'app/Application/Actions/Anvils/CreateAnvilAction.php'],
        'service'        => ['clean-arch:make-service', ['name' => 'Anvil'], 'app/Application/Services/AnvilService.php'],
        'api controller' => ['clean-arch:make-controller', ['name' => 'Anvil', '--api' => true], 'app/Infrastructure/Http/Controllers/Api/AnvilsController.php'],
        'web controller' => ['clean-arch:make-controller', ['name' => 'Anvil', '--web' => true], 'app/Infrastructure/UI/Web/Controllers/AnvilController.php'],
        'observer'       => ['clean-arch:make-observer', ['name' => 'Anvil', 'domain' => 'Anvil'], 'app/Infrastructure/Observers/Anvils/AnvilObserver.php'],
        'listener'       => ['clean-arch:make-listener', ['name' => 'Anvil'], 'app/Application/Listeners/AnvilEventListener.php'],
        'job'            => ['clean-arch:make-job', ['name' => 'Anvil'], 'app/Application/Jobs/ProcessAnvilJob.php'],
        'mail'           => ['clean-arch:make-mail', ['name' => 'Anvil'], 'app/Infrastructure/Mail/AnvilMail.php'],
        'notification'   => ['clean-arch:make-notification', ['name' => 'Anvil'], 'app/Infrastructure/Notifications/AnvilNotification.php'],
        'export'         => ['clean-arch:make-export', ['name' => 'Anvil'], 'app/Infrastructure/Exports/AnvilExport.php'],
    ]);

    it('skips already generated files without --force, keeps other files current, and overwrites everything with --force', function () {
        $decline = function ($pending) {
            return $pending
                ->expectsConfirmation('Would you like to generate an Observer?', 'no')
                ->expectsConfirmation('Would you like to generate a Listener?', 'no')
                ->expectsConfirmation('Would you like to generate a Job?', 'no')
                ->expectsConfirmation('Would you like to generate a Mail?', 'no')
                ->expectsConfirmation('Would you like to generate a Notification?', 'no')
                ->expectsConfirmation('Would you like to generate an Export?', 'no');
        };

        $decline($this->artisan('clean-arch:make-domain', ['name' => 'Article']))->assertExitCode(0);

        $servicePath = app_path('Application/Services/ArticleService.php');
        File::append($servicePath, "\n// custom edit made by a user\n");

        $migrationFile = collect(File::files(database_path('migrations')))
            ->first(fn ($file) => str_contains($file->getFilename(), 'create_articles_table'));
        expect($migrationFile)->not->toBeNull();

        $originalMigrationName = $migrationFile->getFilename();
        File::append($migrationFile->getRealPath(), "\n// custom edit made by a user\n");

        $decline($this->artisan('clean-arch:make-domain', ['name' => 'Article']))
            ->assertExitCode(0)
            ->expectsOutputToContain('Skipped:');

        expect(File::get($servicePath))->toContain('// custom edit made by a user');

        $migrationsAfterSkip = collect(File::files(database_path('migrations')))
            ->filter(fn ($file) => str_contains($file->getFilename(), 'create_articles_table'));
        expect($migrationsAfterSkip)->toHaveCount(1);
        expect($migrationsAfterSkip->first()->getFilename())->toBe($originalMigrationName);
        expect(File::get($migrationsAfterSkip->first()->getRealPath()))->toContain('// custom edit made by a user');

        $decline($this->artisan('clean-arch:make-domain', ['name' => 'Article', '--force' => true]))
            ->assertExitCode(0);

        expect(File::get($servicePath))->not->toContain('// custom edit made by a user');

        $migrationsAfterForce = collect(File::files(database_path('migrations')))
            ->filter(fn ($file) => str_contains($file->getFilename(), 'create_articles_table'));
        expect($migrationsAfterForce)->toHaveCount(1);
        expect($migrationsAfterForce->first()->getFilename())->toBe($originalMigrationName);
        expect(File::get($migrationsAfterForce->first()->getRealPath()))->not->toContain('// custom edit made by a user');
    });

    it('keeps an edited generate-package file on a second run without --force', function () {
        $this->artisan('clean-arch:generate-package', ['name' => 'anvil-package', 'vendor' => 'acme'])
            ->assertExitCode(0);

        $readmePath = base_path('packages/acme/anvil-package/README.md');
        File::append($readmePath, "\n// custom edit made by a user\n");

        $this->artisan('clean-arch:generate-package', ['name' => 'anvil-package', 'vendor' => 'acme'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Skipped:');

        expect(File::get($readmePath))->toContain('// custom edit made by a user');

        $this->artisan('clean-arch:generate-package', ['name' => 'anvil-package', 'vendor' => 'acme', '--force' => true])
            ->assertExitCode(0);

        expect(File::get($readmePath))->not->toContain('// custom edit made by a user');
    });
});
