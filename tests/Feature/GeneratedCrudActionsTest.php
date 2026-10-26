<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

describe('Generated CRUD actions', function () {

    afterEach(function () {
        Schema::dropIfExists('sprockets');

        foreach (File::files(database_path('migrations')) as $file) {
            if (str_ends_with($file->getFilename(), '_create_sprockets_table.php')) {
                File::delete($file->getRealPath());
            }
        }

        foreach ([app_path('Domain'), app_path('Application'), app_path('Infrastructure'), base_path('tests/Feature/Sprockets')] as $dir) {
            File::deleteDirectory($dir);
        }
    });

    it('reads, updates and deletes the record the controller passes by id', function () {
        $this->artisan('clean-arch:install');
        $this->artisan('clean-arch:make-domain', ['name' => 'Sprocket'])
            ->expectsConfirmation('Would you like to generate an Observer?', 'no')
            ->expectsConfirmation('Would you like to generate a Listener?', 'no')
            ->expectsConfirmation('Would you like to generate a Job?', 'no')
            ->expectsConfirmation('Would you like to generate a Mail?', 'no')
            ->expectsConfirmation('Would you like to generate a Notification?', 'no')
            ->expectsConfirmation('Would you like to generate an Export?', 'no')
            ->assertExitCode(0);

        $migration = collect(File::files(database_path('migrations')))
            ->first(fn ($file) => str_ends_with($file->getFilename(), '_create_sprockets_table.php'));
        (require $migration->getRealPath())->up();

        // A test psr-4 autoloader standing in for the app's composer autoload.
        $autoloader = function (string $class): void {
            $path = app_path(str_replace('\\', '/', substr($class, strlen('App\\'))) . '.php');

            if (str_starts_with($class, 'App\\') && File::exists($path)) {
                require_once $path;
            }
        };

        spl_autoload_register($autoloader);

        try {
            $created = app('App\Application\Actions\Sprockets\CreateSprocketAction')
                ->execute(['name' => 'Original', 'description' => 'First', 'status' => 'active']);

            $found   = app('App\Application\Actions\Sprockets\GetByIdSprocketAction')->execute($created->id);
            $missing = app('App\Application\Actions\Sprockets\GetByIdSprocketAction')->execute($created->id + 1);
            $updated = app('App\Application\Actions\Sprockets\UpdateSprocketAction')
                ->execute(['name' => 'Renamed', 'description' => 'Second'], $created->id);

            app('App\Application\Actions\Sprockets\DeleteSprocketAction')->execute($created->id);
        } finally {
            spl_autoload_unregister($autoloader);
        }

        $row = DB::table('sprockets')->where('id', $created->id)->first();

        expect($found->id)->toBe($created->id)
            ->and($missing)->toBeNull()
            ->and($updated->id)->toBe($created->id)
            ->and($updated->name)->toBe('Renamed')
            ->and(DB::table('sprockets')->count())->toBe(1)
            ->and($row->name)->toBe('Renamed')
            ->and($row->deleted_at)->not->toBeNull();
    });
});
