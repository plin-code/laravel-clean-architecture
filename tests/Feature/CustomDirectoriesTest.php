<?php

use Illuminate\Support\Facades\File;

describe('Custom layer directories', function () {

    beforeEach(function () {
        config()->set('clean-architecture.directories', [
            'domain'         => 'app/Core/Domain',
            'application'    => 'app/Core/Application',
            'infrastructure' => 'app/Core/Infrastructure',
        ]);
    });

    afterEach(function () {
        $paths = [
            app_path('Core'),
            app_path('Domain'),
            app_path('Application'),
            app_path('Infrastructure'),
            base_path('CLEAN_ARCHITECTURE.md'),
            base_path('tests/Feature/Invoices'),
        ];
        foreach ($paths as $path) {
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
            } elseif (File::exists($path)) {
                File::delete($path);
            }
        }

        $migrationPath = database_path('migrations');
        if (File::isDirectory($migrationPath)) {
            foreach (File::files($migrationPath) as $file) {
                if (str_contains($file->getFilename(), 'create_invoices_table')) {
                    File::delete($file->getRealPath());
                }
            }
        }
    });

    it('installs base classes with the namespace of the configured directory', function (string $file, string $namespace) {
        $this->artisan('clean-arch:install')->assertExitCode(0);

        expect(File::exists(base_path($file)))->toBeTrue()
            ->and(File::get(base_path($file)))->toContain("namespace {$namespace};");
    })->with([
        'base model'       => ['app/Core/Domain/Shared/BaseModel.php', 'App\Core\Domain\Shared'],
        'base action'      => ['app/Core/Application/Actions/BaseAction.php', 'App\Core\Application\Actions'],
        'base service'     => ['app/Core/Application/Services/BaseService.php', 'App\Core\Application\Services'],
        'base controller'  => ['app/Core/Infrastructure/Http/Controllers/Controller.php', 'App\Core\Infrastructure\Http\Controllers'],
        'base request'     => ['app/Core/Infrastructure/Http/Requests/BaseRequest.php', 'App\Core\Infrastructure\Http\Requests'],
        'domain exception' => ['app/Core/Infrastructure/Exceptions/DomainException.php', 'App\Core\Infrastructure\Exceptions'],
    ]);

    it('generates classes in the configured directory with a matching namespace', function (string $command, array $arguments, string $file, string $namespace) {
        $this->artisan($command, $arguments)->assertExitCode(0);

        expect(File::exists(base_path($file)))->toBeTrue()
            ->and(File::get(base_path($file)))->toContain("namespace {$namespace};");
    })->with([
        'action'         => ['clean-arch:make-action', ['name' => 'MarkAsPaid', 'domain' => 'Invoice'], 'app/Core/Application/Actions/Invoices/MarkAsPaidAction.php', 'App\Core\Application\Actions\Invoices'],
        'service'        => ['clean-arch:make-service', ['name' => 'Invoice'], 'app/Core/Application/Services/InvoiceService.php', 'App\Core\Application\Services'],
        'api controller' => ['clean-arch:make-controller', ['name' => 'Invoice'], 'app/Core/Infrastructure/Http/Controllers/Api/InvoiceController.php', 'App\Core\Infrastructure\Http\Controllers\Api'],
        'web controller' => ['clean-arch:make-controller', ['name' => 'Invoice', '--web' => true], 'app/Core/Infrastructure/UI/Web/Controllers/InvoiceController.php', 'App\Core\Infrastructure\UI\Web\Controllers'],
        'observer'       => ['clean-arch:make-observer', ['name' => 'Invoice', 'domain' => 'Invoice'], 'app/Core/Infrastructure/Observers/Invoices/InvoiceObserver.php', 'App\Core\Infrastructure\Observers\Invoices'],
        'listener'       => ['clean-arch:make-listener', ['name' => 'Invoice'], 'app/Core/Application/Listeners/InvoiceListener.php', 'App\Core\Application\Listeners'],
        'job'            => ['clean-arch:make-job', ['name' => 'Invoice'], 'app/Core/Application/Jobs/InvoiceJob.php', 'App\Core\Application\Jobs'],
        'mail'           => ['clean-arch:make-mail', ['name' => 'Invoice'], 'app/Core/Infrastructure/Mail/InvoiceMail.php', 'App\Core\Infrastructure\Mail'],
        'notification'   => ['clean-arch:make-notification', ['name' => 'Invoice'], 'app/Core/Infrastructure/Notifications/InvoiceNotification.php', 'App\Core\Infrastructure\Notifications'],
        'export'         => ['clean-arch:make-export', ['name' => 'Invoice'], 'app/Core/Infrastructure/Exports/InvoiceExport.php', 'App\Core\Infrastructure\Exports'],
    ]);

    it('generates a domain whose imports all resolve to generated files', function () {
        $this->artisan('clean-arch:install')->assertExitCode(0);

        $this->artisan('clean-arch:make-domain', ['name' => 'Invoice'])
            ->expectsConfirmation('Would you like to generate an Observer?', 'yes')
            ->expectsConfirmation('Would you like to generate a Listener?', 'yes')
            ->expectsConfirmation('Would you like to generate a Job?', 'yes')
            ->expectsConfirmation('Would you like to generate a Mail?', 'yes')
            ->expectsConfirmation('Would you like to generate a Notification?', 'yes')
            ->expectsConfirmation('Would you like to generate an Export?', 'yes')
            ->assertExitCode(0);

        expect(File::isDirectory(app_path('Domain')))->toBeFalse()
            ->and(File::isDirectory(app_path('Application')))->toBeFalse()
            ->and(File::isDirectory(app_path('Infrastructure')))->toBeFalse();

        $files = collect(File::allFiles(app_path('Core')))
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php');

        expect($files)->not->toBeEmpty();

        foreach ($files as $file) {
            $content = File::get($file->getPathname());

            // psr-4: App\ maps to app/, so the namespace follows the directory.
            $relative = str_replace(app_path() . '/', '', $file->getPath());
            $expected = 'App\\' . str_replace('/', '\\', $relative);
            expect($content)->toContain("namespace {$expected};");

            preg_match_all('/^use (App\\\\[^;]+);/m', $content, $matches);
            foreach ($matches[1] as $class) {
                $path = app_path(str_replace('\\', '/', substr($class, strlen('App\\'))) . '.php');
                expect(File::exists($path))->toBeTrue("{$file->getFilename()} imports {$class}, which does not exist");
            }
        }
    });

    it('uses the configured root namespace in generated classes', function () {
        config()->set('clean-architecture.directories', null);
        config()->set('clean-architecture.default_namespace', 'Acme');

        $this->artisan('clean-arch:make-mail', ['name' => 'Invoice'])->assertExitCode(0);

        expect(File::get(app_path('Infrastructure/Mail/InvoiceMail.php')))
            ->toContain('namespace Acme\Infrastructure\Mail;')
            ->toContain('use Acme\Domain\Invoices\Models\Invoice;');
    });
});
