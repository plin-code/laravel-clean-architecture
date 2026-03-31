<?php

use Illuminate\Support\Facades\File;

describe('Command Integration', function () {

    afterEach(function () {
        $dirs = [
            app_path('Domain'),
            app_path('Application'),
            app_path('Infrastructure'),
            base_path('CLEAN_ARCHITECTURE.md'),
            base_path('packages'),
        ];
        foreach ($dirs as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            } elseif (File::exists($dir)) {
                File::delete($dir);
            }
        }

        // Clean up test Feature directories created by make-domain
        $testFeatureDirs = [
            base_path('tests/Feature/Products'),
            base_path('tests/Feature/Orders'),
        ];
        foreach ($testFeatureDirs as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            }
        }

        // Clean up migration files created by make-domain
        $migrationPath = database_path('migrations');
        if (File::isDirectory($migrationPath)) {
            foreach (File::files($migrationPath) as $file) {
                if (str_contains($file->getFilename(), 'create_products_table') ||
                    str_contains($file->getFilename(), 'create_orders_table')) {
                    File::delete($file->getRealPath());
                }
            }
        }
    });

    it('runs install command', function () {
        $this->artisan('clean-arch:install')
            ->assertExitCode(0);

        expect(File::isDirectory(app_path('Domain')))->toBeTrue();
        expect(File::isDirectory(app_path('Application/Actions')))->toBeTrue();
        expect(File::isDirectory(app_path('Application/Services')))->toBeTrue();
        expect(File::isDirectory(app_path('Application/Jobs')))->toBeTrue();
        expect(File::isDirectory(app_path('Application/Listeners')))->toBeTrue();
        expect(File::isDirectory(app_path('Infrastructure/Http/Controllers/Api')))->toBeTrue();
        expect(File::isDirectory(app_path('Infrastructure/Http/Requests')))->toBeTrue();
        expect(File::isDirectory(app_path('Infrastructure/Http/Resources')))->toBeTrue();
        expect(File::isDirectory(app_path('Infrastructure/Mail')))->toBeTrue();
        expect(File::isDirectory(app_path('Infrastructure/Notifications')))->toBeTrue();
        expect(File::isDirectory(app_path('Infrastructure/Observers')))->toBeTrue();
        expect(File::isDirectory(app_path('Infrastructure/Exports')))->toBeTrue();
        expect(File::exists(app_path('Domain/Shared/BaseModel.php')))->toBeTrue();
        expect(File::exists(app_path('Application/Actions/BaseAction.php')))->toBeTrue();
        expect(File::exists(app_path('Infrastructure/Http/Controllers/Controller.php')))->toBeTrue();
        expect(File::exists(app_path('Infrastructure/Http/Requests/BaseRequest.php')))->toBeTrue();
    });

    it('runs make-action command', function () {
        File::ensureDirectoryExists(app_path('Application/Actions'));

        $this->artisan('clean-arch:make-action', ['name' => 'TestAction', 'domain' => 'User'])
            ->assertExitCode(0);

        expect(File::exists(app_path('Application/Actions/Users/TestActionAction.php')))->toBeTrue();
    });

    it('runs make-service command', function () {
        File::ensureDirectoryExists(app_path('Application/Services'));

        $this->artisan('clean-arch:make-service', ['name' => 'Test'])
            ->assertExitCode(0);

        expect(File::exists(app_path('Application/Services/TestService.php')))->toBeTrue();
    });

    it('runs make-controller command with api flag', function () {
        File::ensureDirectoryExists(app_path('Infrastructure/Http/Controllers/Api'));

        $this->artisan('clean-arch:make-controller', ['name' => 'Test', '--api' => true])
            ->assertExitCode(0);

        expect(File::exists(app_path('Infrastructure/Http/Controllers/Api/TestController.php')))->toBeTrue();
    });

    it('runs make-controller command with web flag', function () {
        $this->artisan('clean-arch:make-controller', ['name' => 'Test', '--web' => true])
            ->assertExitCode(0);

        expect(File::exists(app_path('Infrastructure/UI/Web/Controllers/TestController.php')))->toBeTrue();
    });

    it('runs make-controller command with no flags defaults to api', function () {
        $this->artisan('clean-arch:make-controller', ['name' => 'Default'])
            ->assertExitCode(0);

        expect(File::exists(app_path('Infrastructure/Http/Controllers/Api/DefaultController.php')))->toBeTrue();
    });

    it('runs make-observer command', function () {
        $this->artisan('clean-arch:make-observer', ['name' => 'User', 'domain' => 'User'])
            ->assertExitCode(0);

        expect(File::exists(app_path('Infrastructure/Observers/Users/UserObserver.php')))->toBeTrue();
    });

    it('runs make-listener command', function () {
        $this->artisan('clean-arch:make-listener', ['name' => 'User'])
            ->assertExitCode(0);

        expect(File::exists(app_path('Application/Listeners/UserListener.php')))->toBeTrue();
    });

    it('runs make-job command', function () {
        $this->artisan('clean-arch:make-job', ['name' => 'User'])
            ->assertExitCode(0);

        expect(File::exists(app_path('Application/Jobs/UserJob.php')))->toBeTrue();
    });

    it('runs make-mail command', function () {
        $this->artisan('clean-arch:make-mail', ['name' => 'User'])
            ->assertExitCode(0);

        expect(File::exists(app_path('Infrastructure/Mail/UserMail.php')))->toBeTrue();
    });

    it('runs make-notification command', function () {
        $this->artisan('clean-arch:make-notification', ['name' => 'User'])
            ->assertExitCode(0);

        expect(File::exists(app_path('Infrastructure/Notifications/UserNotification.php')))->toBeTrue();
    });

    it('runs make-export command', function () {
        $this->artisan('clean-arch:make-export', ['name' => 'User'])
            ->assertExitCode(0);

        expect(File::exists(app_path('Infrastructure/Exports/UserExport.php')))->toBeTrue();
    });

    it('runs validate command on clean project', function () {
        $this->artisan('clean-arch:validate')
            ->assertExitCode(0);
    });

    it('runs validate command and detects import violations', function () {
        File::ensureDirectoryExists(app_path('Domain/Users/Models'));
        File::put(
            app_path('Domain/Users/Models/User.php'),
            "<?php\n\nnamespace App\\Domain\\Users\\Models;\n\nuse App\\Infrastructure\\Traits\\HasSlug;\n\nclass User {}\n"
        );

        $this->artisan('clean-arch:validate')
            ->assertExitCode(1);
    });

    it('runs validate command and detects file pattern violations', function () {
        // Create an Observer inside Domain (not allowed)
        File::ensureDirectoryExists(app_path('Domain/Users'));
        File::put(app_path('Domain/Users/UserObserver.php'), "<?php\nclass UserObserver {}\n");

        $this->artisan('clean-arch:validate')
            ->assertExitCode(1);
    });

    it('runs validate command and detects directory violations', function () {
        // Create Infrastructure/Services directory (not allowed)
        File::ensureDirectoryExists(app_path('Infrastructure/Services'));
        File::put(app_path('Infrastructure/Services/.gitkeep'), '');

        $this->artisan('clean-arch:validate')
            ->assertExitCode(1);
    });

    it('runs generate-package command', function () {
        $packagePath = base_path('packages/test-vendor/test-package');

        $this->artisan('clean-arch:generate-package', ['name' => 'test-package', 'vendor' => 'test-vendor'])
            ->assertExitCode(0);

        expect(File::isDirectory($packagePath))->toBeTrue();
        expect(File::exists("{$packagePath}/composer.json"))->toBeTrue();
        expect(File::exists("{$packagePath}/src/TestPackageServiceProvider.php"))->toBeTrue();
        expect(File::exists("{$packagePath}/src/TestPackage.php"))->toBeTrue();
        expect(File::exists("{$packagePath}/src/TestPackageService.php"))->toBeTrue();
        expect(File::exists("{$packagePath}/README.md"))->toBeTrue();
    });

    it('runs make-domain command with all prompts declined', function () {
        $this->artisan('clean-arch:install');

        $this->artisan('clean-arch:make-domain', ['name' => 'Product'])
            ->expectsConfirmation('Would you like to generate an Observer?', 'no')
            ->expectsConfirmation('Would you like to generate a Listener?', 'no')
            ->expectsConfirmation('Would you like to generate a Job?', 'no')
            ->expectsConfirmation('Would you like to generate a Mail?', 'no')
            ->expectsConfirmation('Would you like to generate a Notification?', 'no')
            ->expectsConfirmation('Would you like to generate an Export?', 'no')
            ->assertExitCode(0);

        expect(File::exists(app_path('Domain/Products/Models/Product.php')))->toBeTrue();
        expect(File::exists(app_path('Domain/Products/Enums/ProductStatus.php')))->toBeTrue();
        expect(File::exists(app_path('Domain/Products/Events/ProductCreated.php')))->toBeTrue();
        expect(File::exists(app_path('Application/Actions/Products/CreateProductAction.php')))->toBeTrue();
        expect(File::exists(app_path('Application/Services/ProductService.php')))->toBeTrue();
        expect(File::exists(app_path('Infrastructure/Http/Controllers/Api/ProductsController.php')))->toBeTrue();
    });

    it('runs make-domain command with all prompts accepted', function () {
        $this->artisan('clean-arch:install');

        $this->artisan('clean-arch:make-domain', ['name' => 'Order'])
            ->expectsConfirmation('Would you like to generate an Observer?', 'yes')
            ->expectsConfirmation('Would you like to generate a Listener?', 'yes')
            ->expectsConfirmation('Would you like to generate a Job?', 'yes')
            ->expectsConfirmation('Would you like to generate a Mail?', 'yes')
            ->expectsConfirmation('Would you like to generate a Notification?', 'yes')
            ->expectsConfirmation('Would you like to generate an Export?', 'yes')
            ->assertExitCode(0);

        expect(File::exists(app_path('Domain/Orders/Models/Order.php')))->toBeTrue();
        expect(File::exists(app_path('Infrastructure/Observers/Orders/OrderObserver.php')))->toBeTrue();
        expect(File::exists(app_path('Application/Listeners/OrderEventListener.php')))->toBeTrue();
        expect(File::exists(app_path('Application/Jobs/ProcessOrderJob.php')))->toBeTrue();
        expect(File::exists(app_path('Infrastructure/Mail/OrderMail.php')))->toBeTrue();
        expect(File::exists(app_path('Infrastructure/Notifications/OrderNotification.php')))->toBeTrue();
        expect(File::exists(app_path('Infrastructure/Exports/OrderExport.php')))->toBeTrue();
    });
});
