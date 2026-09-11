<?php

use Illuminate\Support\Facades\File;

describe('Install command overwrite behaviour', function () {

    beforeEach(function () {
        // Deterministic starting point regardless of what earlier tests in
        // the suite left behind (install writes the config file but does
        // not clean it up itself).
        File::delete(config_path('clean-architecture.php'));
        File::delete(base_path('CLEAN_ARCHITECTURE.md'));

        foreach ([app_path('Domain'), app_path('Application'), app_path('Infrastructure')] as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            }
        }
    });

    afterEach(function () {
        File::delete(config_path('clean-architecture.php'));
        File::delete(base_path('CLEAN_ARCHITECTURE.md'));

        foreach ([app_path('Domain'), app_path('Application'), app_path('Infrastructure')] as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            }
        }
    });

    it('keeps an existing config file untouched without force', function () {
        $this->artisan('clean-arch:install')->assertExitCode(0);

        $custom = "<?php\n\nreturn ['directories' => ['domain' => 'app/Core/Domain']];\n";
        File::put(config_path('clean-architecture.php'), $custom);

        $this->artisan('clean-arch:install')->assertExitCode(0);

        expect(File::get(config_path('clean-architecture.php')))->toBe($custom);
    });

    it('overwrites the config file when force is passed', function () {
        $this->artisan('clean-arch:install')->assertExitCode(0);

        $custom = "<?php\n\nreturn ['directories' => ['domain' => 'app/Core/Domain']];\n";
        File::put(config_path('clean-architecture.php'), $custom);

        $this->artisan('clean-arch:install', ['--force' => true])->assertExitCode(0);

        expect(File::get(config_path('clean-architecture.php')))->not->toBe($custom);
    });

    it('keeps an existing base class untouched without force', function () {
        $this->artisan('clean-arch:install')->assertExitCode(0);

        $path   = app_path('Domain/Shared/BaseModel.php');
        $custom = "<?php\n\n// custom edit made by a user\n";
        File::put($path, $custom);

        $this->artisan('clean-arch:install')->assertExitCode(0);

        expect(File::get($path))->toBe($custom);
    });

    it('overwrites an existing base class when force is passed', function () {
        $this->artisan('clean-arch:install')->assertExitCode(0);

        $path   = app_path('Domain/Shared/BaseModel.php');
        $custom = "<?php\n\n// custom edit made by a user\n";
        File::put($path, $custom);

        $this->artisan('clean-arch:install', ['--force' => true])->assertExitCode(0);

        expect(File::get($path))->not->toBe($custom);
    });

    it('still creates files that do not exist yet', function () {
        $this->artisan('clean-arch:install')->assertExitCode(0);

        expect(File::exists(config_path('clean-architecture.php')))->toBeTrue();
        expect(File::exists(app_path('Domain/Shared/BaseModel.php')))->toBeTrue();
        expect(File::exists(app_path('Infrastructure/Http/Controllers/Controller.php')))->toBeTrue();
        expect(File::exists(app_path('Application/Actions/BaseAction.php')))->toBeTrue();
        expect(File::exists(app_path('Application/Services/BaseService.php')))->toBeTrue();
        expect(File::exists(app_path('Infrastructure/Http/Requests/BaseRequest.php')))->toBeTrue();
        expect(File::exists(base_path('CLEAN_ARCHITECTURE.md')))->toBeTrue();
    });

    it('prints a skip message naming the path and force when a file exists', function () {
        $this->artisan('clean-arch:install')->assertExitCode(0);

        $this->artisan('clean-arch:install')
            ->assertExitCode(0)
            ->expectsOutputToContain('config/clean-architecture.php')
            ->expectsOutputToContain('--force');
    });
});
