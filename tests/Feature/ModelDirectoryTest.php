<?php

use Illuminate\Support\Facades\File;

/**
 * Every generated PHP file must parse and every `use App\...;` import it
 * declares must resolve to a file that actually exists, following PSR-4
 * (`App\` maps to `app/`). This is what a wrong `model_directory` placeholder
 * would break: a stub could still render, parse and even name its class
 * correctly while importing a model class nothing writes to disk.
 */
function assertModelDirectoryGeneratedFilesAreValid(): void
{
    $files = collect(File::allFiles(app_path()));

    if (File::isDirectory(base_path('tests/Feature/Articles'))) {
        $files = $files->merge(File::allFiles(base_path('tests/Feature/Articles')));
    }

    $files = $files->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php');

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $content = File::get($file->getPathname());

        try {
            token_get_all($content, TOKEN_PARSE);
        } catch (ParseError $e) {
            test()->fail("{$file->getFilename()} failed to parse: {$e->getMessage()}");
        }

        preg_match_all('/^use (App\\\\[^;]+);/m', $content, $matches);

        foreach ($matches[1] as $import) {
            $path = app_path(str_replace('\\', '/', substr($import, strlen('App\\'))) . '.php');

            expect(File::exists($path))->toBeTrue("{$file->getFilename()} imports {$import}, which does not resolve to {$path}");
        }
    }
}

describe('Configurable model directory', function () {

    afterEach(function () {
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

        $migrationPath = database_path('migrations');
        if (File::isDirectory($migrationPath)) {
            foreach (File::files($migrationPath) as $file) {
                if (str_contains($file->getFilename(), 'create_articles_table')) {
                    File::delete($file->getRealPath());
                }
            }
        }
    });

    it('generates the model at the configured directory with every import resolving', function (bool $setConfig, ?string $modelDirectory, string $expectedModelPath, string $expectedNamespace) {
        if ($setConfig) {
            config()->set('clean-architecture.generation.model_directory', $modelDirectory);
        }

        $this->artisan('clean-arch:install')->assertExitCode(0);

        $this->artisan('clean-arch:make-domain', ['name' => 'Article'])
            ->expectsConfirmation('Would you like to generate an Observer?', 'yes')
            ->expectsConfirmation('Would you like to generate a Listener?', 'yes')
            ->expectsConfirmation('Would you like to generate a Job?', 'yes')
            ->expectsConfirmation('Would you like to generate a Mail?', 'yes')
            ->expectsConfirmation('Would you like to generate a Notification?', 'yes')
            ->expectsConfirmation('Would you like to generate an Export?', 'yes')
            ->assertExitCode(0);

        // Standalone commands run afterwards against the same, already
        // generated domain, the way a real project would use them.
        $this->artisan('clean-arch:make-action', ['name' => 'Custom', 'domain' => 'Article'])->assertExitCode(0);
        $this->artisan('clean-arch:make-observer', ['name' => 'Article', 'domain' => 'Article'])->assertExitCode(0);
        $this->artisan('clean-arch:make-mail', ['name' => 'Article'])->assertExitCode(0);
        $this->artisan('clean-arch:make-notification', ['name' => 'Article'])->assertExitCode(0);
        $this->artisan('clean-arch:make-export', ['name' => 'Article'])->assertExitCode(0);
        $this->artisan('clean-arch:make-job', ['name' => 'Article'])->assertExitCode(0);
        $this->artisan('clean-arch:make-listener', ['name' => 'Article'])->assertExitCode(0);
        $this->artisan('clean-arch:make-service', ['name' => 'Article'])->assertExitCode(0);
        $this->artisan('clean-arch:make-controller', ['name' => 'Article'])->assertExitCode(0);

        expect(File::exists(base_path($expectedModelPath)))
            ->toBeTrue("Expected the model at {$expectedModelPath}");

        expect(File::get(base_path($expectedModelPath)))
            ->toContain("namespace {$expectedNamespace};");

        assertModelDirectoryGeneratedFilesAreValid();
    })->with([
        'Models (default, config unset)' => [false, null, 'app/Domain/Articles/Models/Article.php', 'App\Domain\Articles\Models'],
        'null'                           => [true, null, 'app/Domain/Articles/Article.php', 'App\Domain\Articles'],
        'empty string'                   => [true, '', 'app/Domain/Articles/Article.php', 'App\Domain\Articles'],
        'Entities'                       => [true, 'Entities', 'app/Domain/Articles/Entities/Article.php', 'App\Domain\Articles\Entities'],
    ]);
});
