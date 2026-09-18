<?php

use Illuminate\Support\Facades\File;

/**
 * `clean-arch:make-controller` builds its target path directly from `name`,
 * with no support for a nested subdirectory. A name containing `/` or `\`,
 * the convention Laravel's own `make:controller` accepts for a namespaced
 * controller, used to reach `Filesystem::put()` with a parent directory
 * that was never created, crashing with an uncaught `ErrorException`
 * instead of a command failure. It is now rejected up front.
 */
describe('make-controller rejects a nested name', function () {

    afterEach(function () {
        if (File::isDirectory(app_path('Infrastructure'))) {
            File::deleteDirectory(app_path('Infrastructure'));
        }
    });

    it('fails loudly instead of crashing on a slash separated name', function () {
        $this->artisan('clean-arch:make-controller', ['name' => 'Web/Auth/SetPasswordController', '--web' => true])
            ->assertExitCode(1)
            ->expectsOutputToContain('Web/Auth/SetPasswordController');

        expect(File::isDirectory(app_path('Infrastructure/UI/Web/Controllers/Web')))->toBeFalse();
    });

    it('fails loudly on a backslash separated name', function () {
        $this->artisan('clean-arch:make-controller', ['name' => 'Web\\Auth\\SetPasswordController', '--api' => true])
            ->assertExitCode(1);
    });
});
