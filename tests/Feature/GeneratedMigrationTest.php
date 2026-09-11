<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

function deleteArticleMigrations(): void
{
    $migrationPath = database_path('migrations');
    if (File::isDirectory($migrationPath)) {
        foreach (File::files($migrationPath) as $file) {
            if (str_contains($file->getFilename(), 'create_articles_table')) {
                File::delete($file->getRealPath());
            }
        }
    }
}

describe('Generated Migration', function () {

    // Other suites generate an Article domain without removing its migration,
    // and the test below reads the first match, so start from a clean slate.
    beforeEach(fn () => deleteArticleMigrations());

    afterEach(function () {
        if (Schema::hasTable('articles')) {
            Schema::dropIfExists('articles');
        }

        deleteArticleMigrations();

        $dirs = [
            app_path('Domain'),
            app_path('Application'),
            app_path('Infrastructure'),
            base_path('tests/Feature/Articles'),
        ];
        foreach ($dirs as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            }
        }
    });

    it('creates a migration with every column the generated code reads or writes', function () {
        $this->artisan('clean-arch:install');

        $this->artisan('clean-arch:make-domain', ['name' => 'Article'])
            ->expectsConfirmation('Would you like to generate an Observer?', 'no')
            ->expectsConfirmation('Would you like to generate a Listener?', 'no')
            ->expectsConfirmation('Would you like to generate a Job?', 'no')
            ->expectsConfirmation('Would you like to generate a Mail?', 'no')
            ->expectsConfirmation('Would you like to generate a Notification?', 'no')
            ->expectsConfirmation('Would you like to generate an Export?', 'no')
            ->assertExitCode(0);

        $migrationPath = collect(File::files(database_path('migrations')))
            ->first(fn ($file) => str_contains($file->getFilename(), 'create_articles_table'));

        expect($migrationPath)->not->toBeNull();

        $migration = require $migrationPath->getRealPath();
        $migration->up();

        expect(Schema::hasColumns('articles', [
            'id',
            'name',
            'description',
            'status',
            'created_at',
            'updated_at',
            'deleted_at',
        ]))->toBeTrue();

        DB::table('articles')->insert([
            'name'        => 'Test Article',
            'description' => 'Test description',
            'status'      => 'active',
        ]);

        $row = DB::table('articles')
            ->whereNull('deleted_at')
            ->where('name', 'Test Article')
            ->first();

        expect($row)->not->toBeNull()
            ->and($row->status)->toBe('active')
            ->and($row->description)->toBe('Test description');
    });
});
