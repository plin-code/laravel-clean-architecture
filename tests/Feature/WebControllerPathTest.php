<?php

use Illuminate\Support\Facades\File;

/**
 * `make-controller --web` writes to `UI/Web/Controllers` inside the
 * infrastructure layer, while `--api` always writes to
 * `Http/Controllers/Api`. The web path is configurable through
 * `generation.web_controller_path`, for apps that keep their web
 * controllers next to the API ones, and the generated namespace follows
 * the configured path.
 */
describe('Configurable web controller path', function () {

    afterEach(function () {
        foreach ([app_path('Domain'), app_path('Application'), app_path('Infrastructure')] as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            }
        }
    });

    it('defaults to UI/Web/Controllers', function () {
        $this->artisan('clean-arch:make-controller', ['name' => 'Report', '--web' => true])->assertExitCode(0);

        $path = app_path('Infrastructure/UI/Web/Controllers/ReportController.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::get($path))->toContain('namespace App\Infrastructure\UI\Web\Controllers;');
    });

    it('writes to the configured path with a matching namespace', function () {
        config()->set('clean-architecture.generation.web_controller_path', 'Http/Controllers');

        $this->artisan('clean-arch:make-controller', ['name' => 'Report', '--web' => true])->assertExitCode(0);

        $path = app_path('Infrastructure/Http/Controllers/ReportController.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::get($path))->toContain('namespace App\Infrastructure\Http\Controllers;');
        expect(File::exists(app_path('Infrastructure/UI/Web/Controllers/ReportController.php')))->toBeFalse();
    });

    it('accepts a path given with backslashes or surrounding slashes', function (string $configured) {
        config()->set('clean-architecture.generation.web_controller_path', $configured);

        $this->artisan('clean-arch:make-controller', ['name' => 'Report', '--web' => true])->assertExitCode(0);

        $path = app_path('Infrastructure/Web/Controllers/ReportController.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::get($path))->toContain('namespace App\Infrastructure\Web\Controllers;');
    })->with(['Web\\Controllers', '/Web/Controllers/']);

    it('writes in the infrastructure layer itself when the path is empty', function (?string $configured) {
        config()->set('clean-architecture.generation.web_controller_path', $configured);

        $this->artisan('clean-arch:make-controller', ['name' => 'Report', '--web' => true])->assertExitCode(0);

        $path = app_path('Infrastructure/ReportController.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::get($path))->toContain('namespace App\Infrastructure;');
    })->with([null, '']);

    it('leaves the api controller path alone', function () {
        config()->set('clean-architecture.generation.web_controller_path', 'Http/Controllers');

        $this->artisan('clean-arch:make-controller', ['name' => 'Report', '--api' => true])->assertExitCode(0);

        expect(File::exists(app_path('Infrastructure/Http/Controllers/Api/ReportsController.php')))->toBeTrue();
    });
});
