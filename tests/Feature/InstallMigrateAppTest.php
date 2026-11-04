<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Like InstallUserInDomainTest, every test backs up what it touches in the
 * shared Testbench skeleton, copies in a fixture app and restores the backup
 * afterwards. tests/fixtures/laravel-app is a fresh Laravel 13 app,
 * tests/fixtures/starter-kit adds the files of the React starter kit on top.
 */
const MIGRATE_APP_TOUCHED_DIRECTORIES = ['app', 'config', 'database', 'routes', 'tests', 'resources'];

const MIGRATE_APP_TOUCHED_FILES = ['composer.json', 'CLEAN_ARCHITECTURE.md', 'phparkitect.php', 'bootstrap/app.php', 'bootstrap/providers.php'];

function backUpSkeletonForMigrateApp(): string
{
    $backup = sys_get_temp_dir() . '/clean-arch-migrate-' . bin2hex(random_bytes(6));

    foreach (MIGRATE_APP_TOUCHED_DIRECTORIES as $directory) {
        if (File::isDirectory(base_path($directory))) {
            File::copyDirectory(base_path($directory), "{$backup}/{$directory}");
        }
    }

    foreach (MIGRATE_APP_TOUCHED_FILES as $file) {
        if (File::exists(base_path($file))) {
            File::ensureDirectoryExists(dirname("{$backup}/{$file}"));
            File::copy(base_path($file), "{$backup}/{$file}");
        }
    }

    return $backup;
}

function restoreSkeletonForMigrateApp(string $backup): void
{
    foreach (MIGRATE_APP_TOUCHED_DIRECTORIES as $directory) {
        File::deleteDirectory(base_path($directory));

        if (File::isDirectory("{$backup}/{$directory}")) {
            File::copyDirectory("{$backup}/{$directory}", base_path($directory));
        }
    }

    foreach (MIGRATE_APP_TOUCHED_FILES as $file) {
        File::delete(base_path($file));

        if (File::exists("{$backup}/{$file}")) {
            File::copy("{$backup}/{$file}", base_path($file));
        }
    }

    File::deleteDirectory($backup);
}

function installMigrateAppFixture(bool $starterKit): void
{
    File::deleteDirectory(app_path());
    File::deleteDirectory(resource_path('js'));
    File::delete(config_path('clean-architecture.php'));
    File::copyDirectory(__DIR__ . '/../fixtures/laravel-app', base_path());

    if ($starterKit) {
        File::copyDirectory(__DIR__ . '/../fixtures/starter-kit', base_path());
    }
}

/**
 * Every PHP file under the base path directories the command rewrites.
 *
 * @return array<string, string>
 */
function migratedPhpFiles(): array
{
    $contents = [];

    foreach (['app', 'bootstrap', 'config', 'database', 'routes'] as $directory) {
        foreach (File::allFiles(base_path($directory)) as $file) {
            if ($file->getExtension() === 'php' && ! str_starts_with($file->getRelativePathname(), 'cache/')) {
                $contents["{$directory}/{$file->getRelativePathname()}"] = $file->getContents();
            }
        }
    }

    return $contents;
}

describe('Install with --migrate-app', function () {

    beforeEach(function () {
        $this->skeletonBackup = backUpSkeletonForMigrateApp();
    });

    afterEach(function () {
        restoreSkeletonForMigrateApp($this->skeletonBackup);
    });

    it('migrates a fresh app without a starter kit', function () {
        installMigrateAppFixture(starterKit: false);

        $this->artisan('clean-arch:install', ['--migrate-app' => true])
            ->expectsOutputToContain('Removed: app/Http/Controllers/Controller.php')
            ->expectsOutputToContain('Moved: app/Models/User.php to app/Domain/Users/Models/User.php')
            ->doesntExpectOutputToContain('Left outside the layers')
            ->assertExitCode(0);

        expect(File::isDirectory(app_path('Http')))->toBeFalse()
            ->and(File::isDirectory(app_path('Models')))->toBeFalse()
            ->and(File::exists(app_path('Infrastructure/Http/Controllers/Controller.php')))->toBeTrue()
            ->and(File::exists(app_path('Domain/Users/Models/User.php')))->toBeTrue()
            ->and(File::exists(app_path('Providers/AppServiceProvider.php')))->toBeTrue();
    });

    it('moves the starter kit folders into the layers', function (string $from, string $to, string $namespace) {
        installMigrateAppFixture(starterKit: true);

        $this->artisan('clean-arch:install', ['--migrate-app' => true])
            ->expectsOutputToContain("Moved: {$from} to {$to}")
            ->assertExitCode(0);

        expect(File::exists(base_path($from)))->toBeFalse()
            ->and(File::get(base_path($to)))->toContain("namespace {$namespace};");
    })->with([
        'controller' => ['app/Http/Controllers/Settings/ProfileController.php', 'app/Infrastructure/Http/Controllers/Settings/ProfileController.php', 'App\Infrastructure\Http\Controllers\Settings'],
        'middleware' => ['app/Http/Middleware/HandleInertiaRequests.php', 'app/Infrastructure/Http/Middleware/HandleInertiaRequests.php', 'App\Infrastructure\Http\Middleware'],
        'request'    => ['app/Http/Requests/Settings/ProfileUpdateRequest.php', 'app/Infrastructure/Http/Requests/Settings/ProfileUpdateRequest.php', 'App\Infrastructure\Http\Requests\Settings'],
        'action'     => ['app/Actions/Fortify/CreateNewUser.php', 'app/Application/Actions/Fortify/CreateNewUser.php', 'App\Application\Actions\Fortify'],
        'concern'    => ['app/Concerns/ProfileValidationRules.php', 'app/Application/Concerns/ProfileValidationRules.php', 'App\Application\Concerns'],
    ]);

    it('rewrites every reference and leaves no old namespace behind', function () {
        installMigrateAppFixture(starterKit: true);

        $this->artisan('clean-arch:install', ['--migrate-app' => true])
            ->expectsOutputToContain('Updated: bootstrap/app.php')
            ->expectsOutputToContain('Updated: routes/settings.php')
            ->expectsOutputToContain('Updated: app/Providers/FortifyServiceProvider.php')
            ->assertExitCode(0);

        $files = migratedPhpFiles();

        foreach ($files as $path => $content) {
            expect($content)->not->toMatch('/App\\\\(Http|Actions|Concerns|Models)\\\\/', "{$path} still names an old namespace");
            token_get_all($content, TOKEN_PARSE);
        }

        expect($files['bootstrap/app.php'])->toContain('use App\Infrastructure\Http\Middleware\HandleInertiaRequests;')
            ->and($files['app/Infrastructure/Http/Controllers/Settings/ProfileController.php'])->toContain('use App\Infrastructure\Http\Controllers\Controller;')
            ->and($files['app/Infrastructure/Http/Requests/Settings/ProfileUpdateRequest.php'])->toContain('use App\Application\Concerns\ProfileValidationRules;')
            ->and($files['app/Application/Concerns/ProfileValidationRules.php'])->toContain('use App\Domain\Users\Models\User;');
    });

    it('removes the folders it emptied', function () {
        installMigrateAppFixture(starterKit: true);

        $this->artisan('clean-arch:install', ['--migrate-app' => true])->assertExitCode(0);

        foreach (['Http', 'Actions', 'Concerns', 'Models'] as $directory) {
            expect(File::isDirectory(app_path($directory)))->toBeFalse("app/{$directory} is still there");
        }
    });

    it('rewrites wayfinder imports but not the generated actions', function () {
        installMigrateAppFixture(starterKit: true);
        $generated = File::get(resource_path('js/actions/App/Http/Controllers/Settings/ProfileController.ts'));

        $this->artisan('clean-arch:install', ['--migrate-app' => true])
            ->expectsOutputToContain('Updated: resources/js/pages/settings/profile.tsx')
            ->expectsOutputToContain('wayfinder:generate')
            ->assertExitCode(0);

        expect(File::get(resource_path('js/pages/settings/profile.tsx')))
            ->toContain("from '@/actions/App/Infrastructure/Http/Controllers/Settings/ProfileController';")
            ->toContain("from '@/routes/profile';")
            ->and(File::get(resource_path('js/actions/App/Http/Controllers/Settings/ProfileController.ts')))->toBe($generated);
    });

    it('moves console commands and registers them in bootstrap/app.php once', function () {
        installMigrateAppFixture(starterKit: true);
        File::ensureDirectoryExists(app_path('Console/Commands'));
        File::put(app_path('Console/Commands/SyncRunners.php'), "<?php\n\nnamespace App\\Console\\Commands;\n\nuse Illuminate\\Console\\Command;\n\nclass SyncRunners extends Command {}\n");

        $this->artisan('clean-arch:install', ['--migrate-app' => true])
            ->expectsOutputToContain('Moved: app/Console/Commands/SyncRunners.php to app/Infrastructure/Console/Commands/SyncRunners.php')
            ->assertExitCode(0);
        $this->artisan('clean-arch:install', ['--migrate-app' => true])->assertExitCode(0);

        $bootstrap = File::get(base_path('bootstrap/app.php'));

        expect(substr_count($bootstrap, "__DIR__.'/../app/Infrastructure/Console/Commands'"))->toBe(1)
            ->and($bootstrap)->toContain("return Application::configure(basePath: dirname(__DIR__))\n    ->withCommands([")
            ->and(File::isDirectory(app_path('Console')))->toBeFalse();

        token_get_all($bootstrap, TOKEN_PARSE);
    });

    it('points event discovery at the moved listeners unless the app already configures it', function (string $bootstrap, bool $added) {
        installMigrateAppFixture(starterKit: true);
        File::ensureDirectoryExists(app_path('Listeners'));
        File::put(app_path('Listeners/SendWelcome.php'), "<?php\n\nnamespace App\\Listeners;\n\nclass SendWelcome {}\n");
        File::put(base_path('bootstrap/app.php'), $bootstrap);

        $this->artisan('clean-arch:install', ['--migrate-app' => true])
            ->expectsOutputToContain('Moved: app/Listeners/SendWelcome.php to app/Application/Listeners/SendWelcome.php')
            ->assertExitCode(0);

        $content = File::get(base_path('bootstrap/app.php'));

        expect(str_contains($content, "->withEvents(discover: [\n        __DIR__.'/../app/Application/Listeners',\n    ])"))->toBe($added);

        token_get_all($content, TOKEN_PARSE);
    })->with([
        'default builder'   => ["<?php\n\nuse Illuminate\\Foundation\\Application;\n\nreturn Application::configure(basePath: dirname(__DIR__))\n    ->create();\n", true],
        'withEvents in use' => ["<?php\n\nuse Illuminate\\Foundation\\Application;\n\nreturn Application::configure(basePath: dirname(__DIR__))\n    ->withEvents(discover: [__DIR__.'/../app/Domain'])\n    ->create();\n", false],
    ]);

    it('prints the commands registration when it cannot add it', function () {
        installMigrateAppFixture(starterKit: false);
        File::ensureDirectoryExists(app_path('Console/Commands'));
        File::put(app_path('Console/Commands/SyncRunners.php'), "<?php\n\nnamespace App\\Console\\Commands;\n\nclass SyncRunners {}\n");
        File::put(base_path('bootstrap/app.php'), "<?php\n\nreturn require __DIR__.'/custom.php';\n");

        $this->artisan('clean-arch:install', ['--migrate-app' => true])
            ->expectsOutputToContain("__DIR__.'/../app/Infrastructure/Console/Commands',")
            ->assertExitCode(0);

        expect(File::get(base_path('bootstrap/app.php')))->toBe("<?php\n\nreturn require __DIR__.'/custom.php';\n");
    });

    it('follows the configured layer directories', function () {
        installMigrateAppFixture(starterKit: true);
        config()->set('clean-architecture.directories.infrastructure', 'app/Core/Infrastructure');

        $this->artisan('clean-arch:install', ['--migrate-app' => true])->assertExitCode(0);

        expect(File::get(app_path('Core/Infrastructure/Http/Middleware/HandleAppearance.php')))
            ->toContain('namespace App\Core\Infrastructure\Http\Middleware;')
            ->and(File::get(base_path('bootstrap/app.php')))->toContain('use App\Core\Infrastructure\Http\Middleware\HandleAppearance;');
    });

    it('refuses to change anything when a destination exists', function () {
        installMigrateAppFixture(starterKit: true);
        File::ensureDirectoryExists(app_path('Application/Concerns'));
        File::put(app_path('Application/Concerns/ProfileValidationRules.php'), "<?php\n\n// existing\n");
        $before = migratedPhpFiles();

        $this->artisan('clean-arch:install', ['--migrate-app' => true])
            ->expectsOutputToContain('app/Concerns/ProfileValidationRules.php to app/Application/Concerns/ProfileValidationRules.php')
            ->assertExitCode(1);

        expect(migratedPhpFiles())->toBe($before)
            ->and(File::exists(config_path('clean-architecture.php')))->toBeFalse();
    });

    it('replaces a taken destination with --force', function () {
        installMigrateAppFixture(starterKit: true);
        File::ensureDirectoryExists(app_path('Application/Concerns'));
        File::put(app_path('Application/Concerns/ProfileValidationRules.php'), "<?php\n\n// existing\n");

        $this->artisan('clean-arch:install', ['--migrate-app' => true, '--force' => true])->assertExitCode(0);

        expect(File::get(app_path('Application/Concerns/ProfileValidationRules.php')))->toContain('trait ProfileValidationRules');
    });

    it('treats a customised base controller as a conflict with the generated one', function () {
        installMigrateAppFixture(starterKit: false);
        File::put(app_path('Http/Controllers/Controller.php'), "<?php\n\nnamespace App\\Http\\Controllers;\n\nabstract class Controller\n{\n    protected int \$perPage = 20;\n}\n");

        $this->artisan('clean-arch:install', ['--migrate-app' => true])
            ->expectsOutputToContain('app/Http/Controllers/Controller.php to app/Infrastructure/Http/Controllers/Controller.php')
            ->assertExitCode(1);

        $this->artisan('clean-arch:install', ['--migrate-app' => true, '--force' => true])->assertExitCode(0);

        expect(File::get(app_path('Infrastructure/Http/Controllers/Controller.php')))
            ->toContain('namespace App\Infrastructure\Http\Controllers;')
            ->toContain('protected int $perPage = 20;');
    });

    it('leaves the models in place when there are others besides User', function () {
        installMigrateAppFixture(starterKit: true);
        File::put(app_path('Models/Post.php'), "<?php\n\nnamespace App\\Models;\n\nclass Post\n{\n    public function author(): string\n    {\n        return User::class;\n    }\n}\n");

        $this->artisan('clean-arch:install', ['--migrate-app' => true])
            ->expectsOutputToContain('Kept: app/Models')
            ->expectsOutputToContain('Left outside the layers')
            ->expectsOutputToContain('app/Models/Post.php')
            ->assertExitCode(0);

        expect(File::exists(app_path('Models/User.php')))->toBeTrue()
            ->and(File::exists(app_path('Domain/Users')))->toBeFalse()
            ->and(File::get(app_path('Application/Actions/Fortify/CreateNewUser.php')))->toContain('use App\Models\User;')
            ->and(File::exists(app_path('Infrastructure/Http/Middleware/HandleAppearance.php')))->toBeTrue();
    });

    it('reports the folders it does not move', function () {
        installMigrateAppFixture(starterKit: true);
        File::ensureDirectoryExists(app_path('Policies'));
        File::put(app_path('Policies/PostPolicy.php'), "<?php\n\nnamespace App\\Policies;\n\nclass PostPolicy {}\n");

        $this->artisan('clean-arch:install', ['--migrate-app' => true])
            ->expectsOutputToContain('app/Policies/PostPolicy.php')
            ->doesntExpectOutputToContain('  app/Providers/FortifyServiceProvider.php')
            ->assertExitCode(0);

        expect(File::exists(app_path('Policies/PostPolicy.php')))->toBeTrue();
    });

    it('passes phparkitect with no classes outside the layers after migrating a starter kit app', function () {
        installMigrateAppFixture(starterKit: true);

        $this->artisan('clean-arch:install', ['--migrate-app' => true])->assertExitCode(0);

        $result = checkMigratedAppWithOutsideLayersRule();

        expect(File::get(base_path('phparkitect.php')))->toContain("ClassSet::fromDir(__DIR__ . '/app')")
            ->and($result['output'])->toContain('No violations detected')
            ->and($result['exitCode'])->toBe(0, $result['output']);
    });

    it('fails phparkitect with no classes outside the layers on a starter kit app installed without migrating', function () {
        installMigrateAppFixture(starterKit: true);

        $this->artisan('clean-arch:install')->assertExitCode(0);

        $result = checkMigratedAppWithOutsideLayersRule();

        expect($result['exitCode'])->toBe(1)
            ->and($result['output'])
            ->toContain('App\Http\Middleware\HandleAppearance has 1 violations')
            ->toContain('App\Actions\Fortify\CreateNewUser has 1 violations')
            ->not->toContain('FortifyServiceProvider');
    });
});

/**
 * Generate the rules with 'no_classes_outside_layers' on and run phparkitect
 * on the app in the Testbench skeleton.
 *
 * @return array{exitCode: int, output: string}
 */
function checkMigratedAppWithOutsideLayersRule(): array
{
    config()->set('clean-architecture.validation.rules.no_classes_outside_layers', true);
    Artisan::call('clean-arch:make-arch-rules', ['--force' => true]);

    $root    = dirname(__DIR__, 2);
    $process = new Process([
        $root . '/vendor/bin/phparkitect',
        'check',
        '--config=' . base_path('phparkitect.php'),
        '--autoload=' . $root . '/tests/fixtures/starter-kit-autoload.php',
    ], $root, ['CLEAN_ARCH_APP_PATH' => app_path()]);
    $process->run();

    return [
        'exitCode' => (int) $process->getExitCode(),
        'output'   => $process->getOutput() . $process->getErrorOutput(),
    ];
}
