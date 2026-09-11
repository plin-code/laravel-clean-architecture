<?php

use Illuminate\Support\Facades\File;

/**
 * Assert a generated PHP file declares a class (or enum) whose short name
 * equals the file basename, in a namespace matching its directory per PSR-4
 * (`App\` maps to `app/`, `Tests\` maps to `tests/`).
 *
 * A file whose declared class does not match its own name cannot be
 * autoloaded, so this is the contract every generator must honor.
 */
function assertFileDeclaresMatchingClass(string $path): void
{
    expect(File::exists($path))->toBeTrue("Expected generated file to exist: {$path}");

    $content = File::get($path);

    preg_match('/^namespace\s+([^;]+);/m', $content, $namespaceMatch);
    preg_match('/^(?:abstract\s+|final\s+)?(?:class|enum|interface|trait)\s+(\w+)/m', $content, $typeMatch);

    expect($namespaceMatch)->not->toBeEmpty("No namespace declaration found in {$path}");
    expect($typeMatch)->not->toBeEmpty("No class/enum declaration found in {$path}");

    $basename = pathinfo($path, PATHINFO_FILENAME);

    expect($typeMatch[1])->toBe($basename, "{$path} declares {$typeMatch[1]}, but the file is named {$basename}.php");

    $relative  = ltrim(str_replace(base_path(), '', $path), '/');
    $directory = dirname($relative);
    $segments  = explode('/', $directory);
    $root      = array_shift($segments);

    $rootNamespace = match ($root) {
        'app'   => 'App',
        'tests' => 'Tests',
        default => throw new RuntimeException("Unhandled PSR-4 root directory: {$root}"),
    };

    $expectedNamespace = implode('\\', array_merge([$rootNamespace], $segments));

    expect($namespaceMatch[1])->toBe($expectedNamespace, "{$path} declares namespace {$namespaceMatch[1]}, expected {$expectedNamespace}");
}

describe('Generated class names match their files', function () {

    afterEach(function () {
        $dirs = [
            app_path('Domain'),
            app_path('Application'),
            app_path('Infrastructure'),
        ];
        foreach ($dirs as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            }
        }

        foreach (['Articles', 'Reports', 'Authors'] as $plural) {
            $dir = base_path("tests/Feature/{$plural}");
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            }
        }

        $migrationPath = database_path('migrations');
        if (File::isDirectory($migrationPath)) {
            foreach (File::files($migrationPath) as $file) {
                if (str_contains($file->getFilename(), 'create_authors_table')) {
                    File::delete($file->getRealPath());
                }
            }
        }
    });

    it('generates a file whose declared class matches its filename', function (string $command, array $arguments, string $file) {
        $this->artisan($command, $arguments)->assertExitCode(0);

        assertFileDeclaresMatchingClass(base_path($file));
    })->with([
        'job'                      => ['clean-arch:make-job', ['name' => 'Article'], 'app/Application/Jobs/ProcessArticleJob.php'],
        'listener'                 => ['clean-arch:make-listener', ['name' => 'Article'], 'app/Application/Listeners/ArticleEventListener.php'],
        'api controller (default)' => ['clean-arch:make-controller', ['name' => 'Report'], 'app/Infrastructure/Http/Controllers/Api/ReportsController.php'],
        'web controller'           => ['clean-arch:make-controller', ['name' => 'Report', '--web' => true], 'app/Infrastructure/UI/Web/Controllers/ReportController.php'],
        'export'                   => ['clean-arch:make-export', ['name' => 'Article'], 'app/Infrastructure/Exports/ArticleExport.php'],
        'mail'                     => ['clean-arch:make-mail', ['name' => 'Article'], 'app/Infrastructure/Mail/ArticleMail.php'],
        'notification'             => ['clean-arch:make-notification', ['name' => 'Article'], 'app/Infrastructure/Notifications/ArticleNotification.php'],
        'observer'                 => ['clean-arch:make-observer', ['name' => 'Article', 'domain' => 'Article'], 'app/Infrastructure/Observers/Articles/ArticleObserver.php'],
        'service'                  => ['clean-arch:make-service', ['name' => 'Article'], 'app/Application/Services/ArticleService.php'],
        'action'                   => ['clean-arch:make-action', ['name' => 'CreateArticle', 'domain' => 'Article'], 'app/Application/Actions/Articles/CreateArticleAction.php'],
    ]);

    it('generates a domain whose files all declare a matching class', function () {
        $this->artisan('clean-arch:install')->assertExitCode(0);

        $this->artisan('clean-arch:make-domain', ['name' => 'Author'])
            ->expectsConfirmation('Would you like to generate an Observer?', 'yes')
            ->expectsConfirmation('Would you like to generate a Listener?', 'yes')
            ->expectsConfirmation('Would you like to generate a Job?', 'yes')
            ->expectsConfirmation('Would you like to generate a Mail?', 'yes')
            ->expectsConfirmation('Would you like to generate a Notification?', 'yes')
            ->expectsConfirmation('Would you like to generate an Export?', 'yes')
            ->assertExitCode(0);

        $files = [
            app_path('Domain/Authors/Models/Author.php'),
            app_path('Domain/Authors/Enums/AuthorStatus.php'),
            app_path('Domain/Authors/Events/AuthorCreated.php'),
            app_path('Domain/Authors/Events/AuthorUpdated.php'),
            app_path('Domain/Authors/Events/AuthorDeleted.php'),
            app_path('Application/Actions/Authors/CreateAuthorAction.php'),
            app_path('Application/Actions/Authors/UpdateAuthorAction.php'),
            app_path('Application/Actions/Authors/DeleteAuthorAction.php'),
            app_path('Application/Actions/Authors/GetByIdAuthorAction.php'),
            app_path('Application/Services/AuthorService.php'),
            app_path('Infrastructure/Http/Controllers/Api/AuthorsController.php'),
            app_path('Infrastructure/Http/Requests/CreateAuthorRequest.php'),
            app_path('Infrastructure/Http/Requests/UpdateAuthorRequest.php'),
            app_path('Infrastructure/Http/Resources/AuthorResource.php'),
            app_path('Infrastructure/Observers/Authors/AuthorObserver.php'),
            app_path('Application/Listeners/AuthorEventListener.php'),
            app_path('Application/Jobs/ProcessAuthorJob.php'),
            app_path('Infrastructure/Mail/AuthorMail.php'),
            app_path('Infrastructure/Notifications/AuthorNotification.php'),
            app_path('Infrastructure/Exports/AuthorExport.php'),
            base_path('tests/Feature/Authors/AuthorsTest.php'),
        ];

        foreach ($files as $file) {
            assertFileDeclaresMatchingClass($file);
        }
    });
});
