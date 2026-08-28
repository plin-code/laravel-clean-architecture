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

    it('generates action and request classes without leftover placeholders', function () {
        $this->artisan('clean-arch:install');

        $this->artisan('clean-arch:make-domain', ['name' => 'Invoice'])
            ->expectsConfirmation('Would you like to generate an Observer?', 'no')
            ->expectsConfirmation('Would you like to generate a Listener?', 'no')
            ->expectsConfirmation('Would you like to generate a Job?', 'no')
            ->expectsConfirmation('Would you like to generate a Mail?', 'no')
            ->expectsConfirmation('Would you like to generate a Notification?', 'no')
            ->expectsConfirmation('Would you like to generate an Export?', 'no')
            ->assertExitCode(0);

        $action = File::get(app_path('Application/Actions/Invoices/CreateInvoiceAction.php'));
        expect($action)->toContain('class CreateInvoiceAction');
        expect($action)->not->toContain('{{');

        $request = File::get(app_path('Infrastructure/Http/Requests/CreateInvoiceRequest.php'));
        expect($request)->toContain('class CreateInvoiceRequest');
        expect($request)->not->toContain('{{');
    });

    it('generates every domain file as valid php', function () {
        $this->artisan('clean-arch:install');

        $this->artisan('clean-arch:make-domain', ['name' => 'Receipt'])
            ->expectsConfirmation('Would you like to generate an Observer?', 'no')
            ->expectsConfirmation('Would you like to generate a Listener?', 'no')
            ->expectsConfirmation('Would you like to generate a Job?', 'no')
            ->expectsConfirmation('Would you like to generate a Mail?', 'no')
            ->expectsConfirmation('Would you like to generate a Notification?', 'no')
            ->expectsConfirmation('Would you like to generate an Export?', 'no')
            ->assertExitCode(0);

        $files = [
            app_path('Domain/Receipts/Models/Receipt.php'),
            app_path('Application/Actions/Receipts/CreateReceiptAction.php'),
            app_path('Application/Actions/Receipts/GetByIdReceiptAction.php'),
            app_path('Application/Services/ReceiptService.php'),
            app_path('Infrastructure/Http/Requests/CreateReceiptRequest.php'),
            app_path('Infrastructure/Http/Requests/UpdateReceiptRequest.php'),
            app_path('Infrastructure/Http/Controllers/Api/ReceiptsController.php'),
        ];

        foreach ($files as $file) {
            expect(File::exists($file))->toBeTrue();
            expect(token_get_all(File::get($file), TOKEN_PARSE))->toBeArray();
        }
    });

    it('includes custom messages in generated requests by default', function () {
        $this->artisan('clean-arch:install');

        config()->set('clean-architecture.validation.custom_messages', true);

        $this->artisan('clean-arch:make-domain', ['name' => 'Article'])
            ->expectsConfirmation('Would you like to generate an Observer?', 'no')
            ->expectsConfirmation('Would you like to generate a Listener?', 'no')
            ->expectsConfirmation('Would you like to generate a Job?', 'no')
            ->expectsConfirmation('Would you like to generate a Mail?', 'no')
            ->expectsConfirmation('Would you like to generate a Notification?', 'no')
            ->expectsConfirmation('Would you like to generate an Export?', 'no')
            ->assertExitCode(0);

        $requestPath = app_path('Infrastructure/Http/Requests/CreateArticleRequest.php');

        expect(File::exists($requestPath))->toBeTrue();

        $content = File::get($requestPath);

        expect($content)
            ->toContain('public function messages(): array')
            ->toContain('public function rules(): array')
            ->not->toContain('{{#custom_messages');

        $exitCode = 0;
        exec('php -l ' . escapeshellarg($requestPath) . ' 2>&1', $output, $exitCode);

        expect($exitCode)->toBe(0)
            ->and(implode("\n", $output))->toContain('No syntax errors');
    });

    it('omits custom messages from generated requests when disabled', function () {
        $this->artisan('clean-arch:install');

        config()->set('clean-architecture.validation.custom_messages', false);

        $this->artisan('clean-arch:make-domain', ['name' => 'Comment'])
            ->expectsConfirmation('Would you like to generate an Observer?', 'no')
            ->expectsConfirmation('Would you like to generate a Listener?', 'no')
            ->expectsConfirmation('Would you like to generate a Job?', 'no')
            ->expectsConfirmation('Would you like to generate a Mail?', 'no')
            ->expectsConfirmation('Would you like to generate a Notification?', 'no')
            ->expectsConfirmation('Would you like to generate an Export?', 'no')
            ->assertExitCode(0);

        $requestPath = app_path('Infrastructure/Http/Requests/CreateCommentRequest.php');

        expect(File::exists($requestPath))->toBeTrue();

        $content = File::get($requestPath);

        expect($content)
            ->toContain('public function rules(): array')
            ->not->toContain('public function messages')
            ->not->toContain('{{#custom_messages')
            ->not->toContain('{{/custom_messages');

        $exitCode = 0;
        exec('php -l ' . escapeshellarg($requestPath) . ' 2>&1', $output, $exitCode);

        expect($exitCode)->toBe(0);
    });

    it('keeps the rest of the request identical when custom messages are dropped', function () {
        $this->artisan('clean-arch:install');

        $enabledContent = '';

        config()->set('clean-architecture.validation.custom_messages', true);
        $this->artisan('clean-arch:make-domain', ['name' => 'Enabled'])
            ->expectsConfirmation('Would you like to generate an Observer?', 'no')
            ->expectsConfirmation('Would you like to generate a Listener?', 'no')
            ->expectsConfirmation('Would you like to generate a Job?', 'no')
            ->expectsConfirmation('Would you like to generate a Mail?', 'no')
            ->expectsConfirmation('Would you like to generate a Notification?', 'no')
            ->expectsConfirmation('Would you like to generate an Export?', 'no')
            ->assertExitCode(0);

        $enabledContent = File::get(app_path('Infrastructure/Http/Requests/CreateEnabledRequest.php'));

        config()->set('clean-architecture.validation.custom_messages', false);
        $this->artisan('clean-arch:make-domain', ['name' => 'Disabled'])
            ->expectsConfirmation('Would you like to generate an Observer?', 'no')
            ->expectsConfirmation('Would you like to generate a Listener?', 'no')
            ->expectsConfirmation('Would you like to generate a Job?', 'no')
            ->expectsConfirmation('Would you like to generate a Mail?', 'no')
            ->expectsConfirmation('Would you like to generate a Notification?', 'no')
            ->expectsConfirmation('Would you like to generate an Export?', 'no')
            ->assertExitCode(0);

        $disabledContent = File::get(app_path('Infrastructure/Http/Requests/CreateDisabledRequest.php'));

        $shared = [
            'namespace App\Infrastructure\Http\Requests;',
            'class Create',
            'extends BaseRequest',
            'public function authorize(): bool',
            'return true;',
            'public function rules(): array',
            "'name' => 'required|string|max:255'",
            "'description' => 'nullable|string|max:1000'",
            "'status' => 'required|string|in:active,inactive,pending'",
        ];

        foreach ($shared as $needle) {
            expect($disabledContent)->toContain($needle);
        }

        expect($disabledContent)->not->toContain('public function messages');
        expect($enabledContent)->toContain('public function messages');
    });

    it('generates service with base class by default', function () {
        $this->artisan('clean-arch:install');

        $this->artisan('clean-arch:make-service', ['name' => 'Product'])
            ->assertExitCode(0);

        $path    = app_path('Application/Services/ProductService.php');
        $content = File::get($path);

        expect($content)
            ->toContain('use App\Application\Services\BaseService;')
            ->toContain('class ProductService extends BaseService');
    });

    it('generates service without base class with no-base flag', function () {
        $this->artisan('clean-arch:install');

        $this->artisan('clean-arch:make-service', ['name' => 'Order', '--no-base' => true])
            ->assertExitCode(0);

        $path    = app_path('Application/Services/OrderService.php');
        $content = File::get($path);

        expect($content)
            ->not->toContain('BaseService')
            ->not->toContain('extends BaseService')
            ->toContain('class OrderService');

        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
        expect($exitCode)->toBe(0);
    });

    it('falls back to config when no-base flag is absent for service', function () {
        $this->artisan('clean-arch:install');

        config()->set('clean-architecture.generation.extend_base_classes', false);

        $this->artisan('clean-arch:make-service', ['name' => 'Article'])
            ->assertExitCode(0);

        $path    = app_path('Application/Services/ArticleService.php');
        $content = File::get($path);

        expect($content)
            ->not->toContain('BaseService')
            ->toContain('class ArticleService');
    });

    it('generates action with base class by default', function () {
        $this->artisan('clean-arch:install');

        $this->artisan('clean-arch:make-action', ['name' => 'CreateUser', 'domain' => 'User'])
            ->assertExitCode(0);

        $path    = app_path('Application/Actions/Users/CreateUserAction.php');
        $content = File::get($path);

        expect($content)
            ->toContain('use App\Application\Actions\BaseAction;')
            ->toContain('class CreateUserAction extends BaseAction');
    });

    it('generates action without base class with no-base flag', function () {
        $this->artisan('clean-arch:install');

        $this->artisan('clean-arch:make-action', ['name' => 'UpdateUser', 'domain' => 'User', '--no-base' => true])
            ->assertExitCode(0);

        $path    = app_path('Application/Actions/Users/UpdateUserAction.php');
        $content = File::get($path);

        expect($content)
            ->not->toContain('BaseAction')
            ->not->toContain('extends BaseAction')
            ->toContain('class UpdateUserAction');

        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
        expect($exitCode)->toBe(0);
    });

    it('generates make-domain service and action without base classes with no-base flag', function () {
        $this->artisan('clean-arch:install');

        $this->artisan('clean-arch:make-domain', ['name' => 'Brand', '--no-base' => true])
            ->expectsConfirmation('Would you like to generate an Observer?', 'no')
            ->expectsConfirmation('Would you like to generate a Listener?', 'no')
            ->expectsConfirmation('Would you like to generate a Job?', 'no')
            ->expectsConfirmation('Would you like to generate a Mail?', 'no')
            ->expectsConfirmation('Would you like to generate a Notification?', 'no')
            ->expectsConfirmation('Would you like to generate an Export?', 'no')
            ->assertExitCode(0);

        $servicePath = app_path('Application/Services/BrandService.php');
        $actionPath  = app_path('Application/Actions/Brands/CreateBrandAction.php');

        expect(file_exists($servicePath))->toBeTrue();
        expect(file_exists($actionPath))->toBeTrue();

        $serviceContent = File::get($servicePath);
        $actionContent  = File::get($actionPath);

        expect($serviceContent)
            ->not->toContain('BaseService')
            ->not->toContain('extends BaseService');

        expect($actionContent)
            ->not->toContain('BaseAction')
            ->not->toContain('extends BaseAction');

        exec('php -l ' . escapeshellarg($servicePath) . ' 2>&1', $output, $serviceExit);
        expect($serviceExit)->toBe(0);

        exec('php -l ' . escapeshellarg($actionPath) . ' 2>&1', $output, $actionExit);
        expect($actionExit)->toBe(0);
    });
});
