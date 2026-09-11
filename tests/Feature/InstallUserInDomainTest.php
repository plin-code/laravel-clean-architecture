<?php

use Illuminate\Support\Facades\File;

/**
 * The Testbench skeleton is shared by the whole suite and ships none of the
 * files a fresh Laravel app has (no User model, factory, seeder, provider or
 * routes). Every test backs up the directories it touches, copies in the
 * fixture of a fresh Laravel 13 app, and restores the backup afterwards.
 */
const USER_IN_DOMAIN_TOUCHED_DIRECTORIES = ['app', 'config', 'database', 'routes', 'tests', 'resources'];

const USER_IN_DOMAIN_TOUCHED_FILES = ['composer.json', 'CLEAN_ARCHITECTURE.md'];

function backUpSkeletonForUserInDomain(): string
{
    $backup = sys_get_temp_dir() . '/clean-arch-skeleton-' . bin2hex(random_bytes(6));

    foreach (USER_IN_DOMAIN_TOUCHED_DIRECTORIES as $directory) {
        if (File::isDirectory(base_path($directory))) {
            File::copyDirectory(base_path($directory), "{$backup}/{$directory}");
        }
    }

    foreach (USER_IN_DOMAIN_TOUCHED_FILES as $file) {
        if (File::exists(base_path($file))) {
            File::ensureDirectoryExists($backup);
            File::copy(base_path($file), "{$backup}/{$file}");
        }
    }

    return $backup;
}

function restoreSkeletonForUserInDomain(string $backup): void
{
    foreach (USER_IN_DOMAIN_TOUCHED_DIRECTORIES as $directory) {
        File::deleteDirectory(base_path($directory));

        if (File::isDirectory("{$backup}/{$directory}")) {
            File::copyDirectory("{$backup}/{$directory}", base_path($directory));
        }
    }

    foreach (USER_IN_DOMAIN_TOUCHED_FILES as $file) {
        File::delete(base_path($file));

        if (File::exists("{$backup}/{$file}")) {
            File::copy("{$backup}/{$file}", base_path($file));
        }
    }

    File::deleteDirectory($backup);
}

function installFreshLaravelAppFixture(): void
{
    foreach ([app_path('Domain'), app_path('Application'), app_path('Infrastructure'), app_path('Core')] as $directory) {
        File::deleteDirectory($directory);
    }

    File::delete(config_path('clean-architecture.php'));
    File::copyDirectory(__DIR__ . '/../fixtures/laravel-app', base_path());
}

describe('Install with --user-in-domain', function () {

    beforeEach(function () {
        $this->skeletonBackup = backUpSkeletonForUserInDomain();
        installFreshLaravelAppFixture();
    });

    afterEach(function () {
        restoreSkeletonForUserInDomain($this->skeletonBackup);
    });

    it('moves the user model into the domain', function (?array $config, string $path, string $namespace) {
        foreach ($config ?? [] as $key => $value) {
            config()->set($key, $value);
        }

        $this->artisan('clean-arch:install', ['--user-in-domain' => true])->assertExitCode(0);

        expect(File::exists(base_path($path)))->toBeTrue("Expected the user model at {$path}")
            ->and(File::exists(app_path('Models/User.php')))->toBeFalse()
            ->and(File::get(base_path($path)))
            ->toContain("namespace {$namespace};")
            ->toContain('class User extends Authenticatable')
            ->toContain('function newFactory()');
    })->with([
        'defaults'             => [null, 'app/Domain/Users/Models/User.php', 'App\Domain\Users\Models'],
        'no model directory'   => [['clean-architecture.generation.model_directory' => null], 'app/Domain/Users/User.php', 'App\Domain\Users'],
        'custom domain folder' => [['clean-architecture.directories.domain' => 'app/Core/Domain'], 'app/Core/Domain/Users/Models/User.php', 'App\Core\Domain\Users\Models'],
    ]);

    it('leaves the user model alone without the option', function () {
        $model = File::get(app_path('Models/User.php'));
        $auth  = File::get(config_path('auth.php'));

        $this->artisan('clean-arch:install')->assertExitCode(0);

        expect(File::get(app_path('Models/User.php')))->toBe($model)
            ->and(File::get(config_path('auth.php')))->toBe($auth)
            ->and(File::isDirectory(app_path('Domain/Users')))->toBeFalse()
            ->and(File::exists(app_path('Http/Controllers/Controller.php')))->toBeTrue();
    });
});
