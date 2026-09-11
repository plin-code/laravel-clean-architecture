<?php

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Assert;

/**
 * Every generated .php file under the Domain, Application and Infrastructure
 * layer directories must be syntactically valid PHP, with no leftover
 * `{{placeholder}}` markers.
 *
 * @return array<int, string>
 */
function collectGeneratedPhpFiles(): array
{
    $files = [];

    foreach ([app_path('Domain'), app_path('Application'), app_path('Infrastructure')] as $directory) {
        if (! File::isDirectory($directory)) {
            continue;
        }

        foreach (File::allFiles($directory) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getRealPath();
            }
        }
    }

    return $files;
}

function assertGeneratedPhpFilesAreValid(): void
{
    $files = collectGeneratedPhpFiles();

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $code = File::get($file);

        Assert::assertStringNotContainsString('{{', $code, "Leftover placeholder found in {$file}");

        try {
            $tokens = token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $e) {
            Assert::fail("{$file} is not valid php: {$e->getMessage()}");
        }

        Assert::assertIsArray($tokens, "{$file} did not tokenize as valid php");
    }
}

describe('Generated PHP Validity', function () {

    afterEach(function () {
        $dirs = [
            app_path('Domain'),
            app_path('Application'),
            app_path('Infrastructure'),
            base_path('CLEAN_ARCHITECTURE.md'),
        ];
        foreach ($dirs as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            } elseif (File::exists($dir)) {
                File::delete($dir);
            }
        }

        $testFeatureDirs = [
            base_path('tests/Feature/Authors'),
            base_path('tests/Feature/Widgets'),
        ];
        foreach ($testFeatureDirs as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            }
        }

        $migrationPath = database_path('migrations');
        if (File::isDirectory($migrationPath)) {
            foreach (File::files($migrationPath) as $file) {
                if (str_contains($file->getFilename(), 'create_authors_table') ||
                    str_contains($file->getFilename(), 'create_widgets_table')) {
                    File::delete($file->getRealPath());
                }
            }
        }
    });

    it('generates every domain file, including events, as valid placeholder-free php', function () {
        $this->artisan('clean-arch:install');

        $this->artisan('clean-arch:make-domain', ['name' => 'Author'])
            ->expectsConfirmation('Would you like to generate an Observer?', 'yes')
            ->expectsConfirmation('Would you like to generate a Listener?', 'yes')
            ->expectsConfirmation('Would you like to generate a Job?', 'yes')
            ->expectsConfirmation('Would you like to generate a Mail?', 'yes')
            ->expectsConfirmation('Would you like to generate a Notification?', 'yes')
            ->expectsConfirmation('Would you like to generate an Export?', 'yes')
            ->assertExitCode(0);

        expect(File::exists(app_path('Domain/Authors/Events/AuthorCreated.php')))->toBeTrue();
        expect(File::exists(app_path('Domain/Authors/Events/AuthorUpdated.php')))->toBeTrue();
        expect(File::exists(app_path('Domain/Authors/Events/AuthorDeleted.php')))->toBeTrue();

        assertGeneratedPhpFilesAreValid();
    });

    it('generates valid placeholder-free php for standalone make commands', function (string $command, array $arguments) {
        $this->artisan($command, $arguments)->assertExitCode(0);

        assertGeneratedPhpFilesAreValid();
    })->with([
        'make-action'         => ['clean-arch:make-action', ['name' => 'Generate', 'domain' => 'Widget']],
        'make-controller api' => ['clean-arch:make-controller', ['name' => 'Widget', '--api' => true]],
        'make-controller web' => ['clean-arch:make-controller', ['name' => 'Widget', '--web' => true]],
        'make-observer'       => ['clean-arch:make-observer', ['name' => 'Widget', 'domain' => 'Widget']],
        'make-service'        => ['clean-arch:make-service', ['name' => 'Widget']],
        'make-job'            => ['clean-arch:make-job', ['name' => 'Widget']],
        'make-listener'       => ['clean-arch:make-listener', ['name' => 'Widget']],
        'make-mail'           => ['clean-arch:make-mail', ['name' => 'Widget']],
        'make-notification'   => ['clean-arch:make-notification', ['name' => 'Widget']],
        'make-export'         => ['clean-arch:make-export', ['name' => 'Widget']],
    ]);
});
