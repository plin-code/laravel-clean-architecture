<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

describe('Generated Factory', function () {

    afterEach(function () {
        foreach (['gadgets', 'gizmos'] as $table) {
            Schema::dropIfExists($table);
        }

        foreach (File::files(database_path('migrations')) as $file) {
            if (preg_match('/_create_(gadgets|gizmos)_table\.php$/', $file->getFilename())) {
                File::delete($file->getRealPath());
            }
        }

        File::delete([database_path('factories/GadgetFactory.php'), database_path('factories/GizmoFactory.php')]);

        foreach ([app_path('Domain'), app_path('Application'), app_path('Infrastructure'), base_path('tests/Feature/Gadgets'), base_path('tests/Feature/Gizmos')] as $dir) {
            File::deleteDirectory($dir);
        }
    });

    // Each row uses its own domain: the classes stay loaded for the rest of the process.
    it('generates a factory the model resolves and the migration accepts', function (string $name, ?string $modelDirectory, string $modelClass) {
        config()->set('clean-architecture.generation.model_directory', $modelDirectory);

        $this->artisan('clean-arch:install');
        $this->artisan('clean-arch:make-domain', ['name' => $name])
            ->expectsConfirmation('Would you like to generate an Observer?', 'no')
            ->expectsConfirmation('Would you like to generate a Listener?', 'no')
            ->expectsConfirmation('Would you like to generate a Job?', 'no')
            ->expectsConfirmation('Would you like to generate a Mail?', 'no')
            ->expectsConfirmation('Would you like to generate a Notification?', 'no')
            ->expectsConfirmation('Would you like to generate an Export?', 'no')
            ->expectsOutputToContain("Created: database/factories/{$name}Factory.php")
            ->assertExitCode(0);

        $migration = collect(File::files(database_path('migrations')))
            ->first(fn ($file) => str_contains($file->getFilename(), '_create_' . strtolower($name) . 's_table'));
        (require $migration->getRealPath())->up();

        // A test psr-4 autoloader standing in for the app's composer autoload.
        $roots = [
            'App\\'                 => app_path() . '/',
            'Database\\Factories\\' => database_path('factories') . '/',
        ];
        $autoloader = function (string $class) use ($roots): void {
            foreach ($roots as $prefix => $directory) {
                $path = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

                if (str_starts_with($class, $prefix) && File::exists($path)) {
                    require_once $path;
                }
            }
        };

        spl_autoload_register($autoloader);

        try {
            $model = $modelClass::factory()->create();
        } finally {
            spl_autoload_unregister($autoloader);
        }

        expect($model)->toBeInstanceOf($modelClass)
            ->and($model->exists)->toBeTrue()
            ->and($model->isActive())->toBeTrue();
    })->with([
        'models directory'   => ['Gadget', 'Models', 'App\Domain\Gadgets\Models\Gadget'],
        'no model directory' => ['Gizmo', null, 'App\Domain\Gizmos\Gizmo'],
    ]);
});
