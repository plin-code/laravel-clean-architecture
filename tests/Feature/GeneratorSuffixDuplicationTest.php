<?php

use Illuminate\Support\Facades\File;

/**
 * `make-mail`, `make-action` and `make-controller` bake a fixed suffix
 * (`Mail`, `Action`, `Controller`) into the class they generate. Passing a
 * name that already carries that suffix used to double it up, `UserMail`
 * becoming `UserMailMail`, and for `make-mail` it also corrupted the
 * imported domain model, since the same name feeds both the class suffix
 * and the domain lookup.
 */
describe('make commands do not double an already present suffix', function () {

    afterEach(function () {
        foreach ([app_path('Domain'), app_path('Application'), app_path('Infrastructure')] as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            }
        }
    });

    it('does not double the Mail suffix and keeps the domain import correct', function () {
        $this->artisan('clean-arch:make-mail', ['name' => 'UserMail'])->assertExitCode(0);

        $path = app_path('Infrastructure/Mail/UserMail.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::exists(app_path('Infrastructure/Mail/UserMailMail.php')))->toBeFalse();

        $content = File::get($path);
        expect($content)->toContain('class UserMail extends Mailable');
        expect($content)->toContain('use App\Domain\Users\Models\User;');
        expect($content)->not->toContain('UserMailMail');
    });

    it('still appends the Mail suffix when the name does not carry it', function () {
        $this->artisan('clean-arch:make-mail', ['name' => 'User'])->assertExitCode(0);

        expect(File::exists(app_path('Infrastructure/Mail/UserMail.php')))->toBeTrue();
    });

    it('is case sensitive when checking the Mail suffix', function () {
        $this->artisan('clean-arch:make-mail', ['name' => 'Newsmail'])->assertExitCode(0);

        // "Newsmail" ends with lowercase "mail", not "Mail", so it is not
        // treated as already carrying the suffix.
        expect(File::exists(app_path('Infrastructure/Mail/NewsmailMail.php')))->toBeTrue();
    });

    it('does not double the Action suffix', function () {
        $this->artisan('clean-arch:make-action', ['name' => 'SendMagicLinkAction', 'domain' => 'MagicLink'])
            ->assertExitCode(0);

        $path = app_path('Application/Actions/MagicLinks/SendMagicLinkAction.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::exists(app_path('Application/Actions/MagicLinks/SendMagicLinkActionAction.php')))->toBeFalse();
        expect(File::get($path))->toContain('class SendMagicLinkAction extends BaseAction');
    });

    it('still appends the Action suffix when the name does not carry it', function () {
        $this->artisan('clean-arch:make-action', ['name' => 'CreateUser', 'domain' => 'User'])
            ->assertExitCode(0);

        expect(File::exists(app_path('Application/Actions/Users/CreateUserAction.php')))->toBeTrue();
    });

    it('does not double the Controller suffix on the api variant', function () {
        $this->artisan('clean-arch:make-controller', ['name' => 'UsersController', '--api' => true])
            ->assertExitCode(0);

        $path = app_path('Infrastructure/Http/Controllers/Api/UsersController.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::get($path))->toContain('class UsersController extends Controller');
    });

    it('does not double the Controller suffix on the web variant', function () {
        $this->artisan('clean-arch:make-controller', ['name' => 'SetPasswordController', '--web' => true])
            ->assertExitCode(0);

        $path = app_path('Infrastructure/UI/Web/Controllers/SetPasswordController.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::exists(app_path('Infrastructure/UI/Web/Controllers/SetPasswordControllerController.php')))->toBeFalse();
        expect(File::get($path))->toContain('class SetPasswordController extends Controller');
    });

    it('still appends the Controller suffix when the name does not carry it', function () {
        $this->artisan('clean-arch:make-controller', ['name' => 'Report', '--web' => true])
            ->assertExitCode(0);

        expect(File::exists(app_path('Infrastructure/UI/Web/Controllers/ReportController.php')))->toBeTrue();
    });
    it('does not double the Export suffix', function () {
        $this->artisan('clean-arch:make-export', ['name' => 'RegistrationsExport'])->assertExitCode(0);

        $path = app_path('Infrastructure/Exports/RegistrationsExport.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::exists(app_path('Infrastructure/Exports/RegistrationsExportExport.php')))->toBeFalse();
        expect(File::get($path))->toContain('class RegistrationsExport');
    });

    it('still appends the Export suffix when the name does not carry it', function () {
        $this->artisan('clean-arch:make-export', ['name' => 'Registrations'])->assertExitCode(0);

        expect(File::exists(app_path('Infrastructure/Exports/RegistrationsExport.php')))->toBeTrue();
    });

    it('does not double the Notification suffix', function () {
        $this->artisan('clean-arch:make-notification', ['name' => 'InvoicePaidNotification'])->assertExitCode(0);

        $path = app_path('Infrastructure/Notifications/InvoicePaidNotification.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::exists(app_path('Infrastructure/Notifications/InvoicePaidNotificationNotification.php')))->toBeFalse();
        expect(File::get($path))->toContain('class InvoicePaidNotification extends Notification');
    });

    it('still appends the Notification suffix when the name does not carry it', function () {
        $this->artisan('clean-arch:make-notification', ['name' => 'InvoicePaid'])->assertExitCode(0);

        expect(File::exists(app_path('Infrastructure/Notifications/InvoicePaidNotification.php')))->toBeTrue();
    });

    it('does not double the Service suffix and keeps the domain import correct', function () {
        $this->artisan('clean-arch:make-service', ['name' => 'ContactTopicsService'])->assertExitCode(0);

        $path = app_path('Application/Services/ContactTopicsService.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::exists(app_path('Application/Services/ContactTopicsServiceService.php')))->toBeFalse();

        $content = File::get($path);
        expect($content)->toContain('class ContactTopicsService');
        expect($content)->toContain('use App\Domain\ContactTopics\Models\ContactTopics;');
        expect($content)->not->toContain('ContactTopicsServiceService');
    });

    it('still appends the Service suffix when the name does not carry it', function () {
        $this->artisan('clean-arch:make-service', ['name' => 'ContactTopics'])->assertExitCode(0);

        expect(File::exists(app_path('Application/Services/ContactTopicsService.php')))->toBeTrue();
    });

    it('does not double the Observer suffix', function () {
        $this->artisan('clean-arch:make-observer', ['name' => 'StoreObserver', 'domain' => 'Store'])
            ->assertExitCode(0);

        $path = app_path('Infrastructure/Observers/Stores/StoreObserver.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::exists(app_path('Infrastructure/Observers/Stores/StoreObserverObserver.php')))->toBeFalse();
        expect(File::get($path))->toContain('class StoreObserver');
    });

    it('still appends the Observer suffix when the name does not carry it', function () {
        $this->artisan('clean-arch:make-observer', ['name' => 'Store', 'domain' => 'Store'])
            ->assertExitCode(0);

        expect(File::exists(app_path('Infrastructure/Observers/Stores/StoreObserver.php')))->toBeTrue();
    });

    it('does not double the EventListener suffix', function () {
        $this->artisan('clean-arch:make-listener', ['name' => 'UserEventListener'])->assertExitCode(0);

        $path = app_path('Application/Listeners/UserEventListener.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::exists(app_path('Application/Listeners/UserEventListenerEventListener.php')))->toBeFalse();
        expect(File::get($path))->toContain('class UserEventListener');
    });

    it('also accepts the shorter Listener suffix', function () {
        // `EventListener` is the suffix the command appends, but `FooListener`
        // is the name Laravel users expect to be able to pass, so both land on
        // the same generated file.
        $this->artisan('clean-arch:make-listener', ['name' => 'UserListener'])->assertExitCode(0);

        expect(File::exists(app_path('Application/Listeners/UserEventListener.php')))->toBeTrue();
        expect(File::exists(app_path('Application/Listeners/UserListenerEventListener.php')))->toBeFalse();
    });

    it('still appends the EventListener suffix when the name does not carry it', function () {
        $this->artisan('clean-arch:make-listener', ['name' => 'User'])->assertExitCode(0);

        expect(File::exists(app_path('Application/Listeners/UserEventListener.php')))->toBeTrue();
    });

    it('does not double the Job suffix nor the Process prefix', function () {
        $this->artisan('clean-arch:make-job', ['name' => 'ProcessGeocodeStoreJob'])->assertExitCode(0);

        $path = app_path('Application/Jobs/ProcessGeocodeStoreJob.php');
        expect(File::exists($path))->toBeTrue();
        expect(File::exists(app_path('Application/Jobs/ProcessProcessGeocodeStoreJobJob.php')))->toBeFalse();

        $content = File::get($path);
        expect($content)->toContain('class ProcessGeocodeStoreJob implements ShouldQueue');
        expect($content)->toContain('use App\Domain\GeocodeStores\Models\GeocodeStore;');
    });

    it('strips the Process prefix on its own', function () {
        $this->artisan('clean-arch:make-job', ['name' => 'ProcessGeocodeStore'])->assertExitCode(0);

        expect(File::exists(app_path('Application/Jobs/ProcessGeocodeStoreJob.php')))->toBeTrue();
        expect(File::exists(app_path('Application/Jobs/ProcessProcessGeocodeStoreJob.php')))->toBeFalse();
    });

    it('still wraps the name in Process and Job when it carries neither', function () {
        $this->artisan('clean-arch:make-job', ['name' => 'GeocodeStore'])->assertExitCode(0);

        expect(File::exists(app_path('Application/Jobs/ProcessGeocodeStoreJob.php')))->toBeTrue();
    });
});
