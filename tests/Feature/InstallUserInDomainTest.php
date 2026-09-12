<?php

use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\File;

/**
 * The Testbench skeleton is shared by the whole suite and ships none of the
 * files a fresh Laravel app has (no User model, factory, seeder, provider or
 * routes). Every test backs up the directories it touches, copies in the
 * fixture of a fresh Laravel 13 app, and restores the backup afterwards.
 */
const USER_IN_DOMAIN_TOUCHED_DIRECTORIES = ['app', 'config', 'database', 'routes', 'tests', 'resources'];

const USER_IN_DOMAIN_TOUCHED_FILES = ['composer.json', 'CLEAN_ARCHITECTURE.md'];

function backUpSkeletonForUserInDomain(): string
{
    $backup = sys_get_temp_dir() . '/clean-arch-skeleton-' . bin2hex(random_bytes(6));

    foreach (USER_IN_DOMAIN_TOUCHED_DIRECTORIES as $directory) {
        if (File::isDirectory(base_path($directory))) {
            File::copyDirectory(base_path($directory), "{$backup}/{$directory}");
        }
    }

    foreach (USER_IN_DOMAIN_TOUCHED_FILES as $file) {
        if (File::exists(base_path($file))) {
            File::ensureDirectoryExists($backup);
            File::copy(base_path($file), "{$backup}/{$file}");
        }
    }

    return $backup;
}

function restoreSkeletonForUserInDomain(string $backup): void
{
    foreach (USER_IN_DOMAIN_TOUCHED_DIRECTORIES as $directory) {
        File::deleteDirectory(base_path($directory));

        if (File::isDirectory("{$backup}/{$directory}")) {
            File::copyDirectory("{$backup}/{$directory}", base_path($directory));
        }
    }

    foreach (USER_IN_DOMAIN_TOUCHED_FILES as $file) {
        File::delete(base_path($file));

        if (File::exists("{$backup}/{$file}")) {
            File::copy("{$backup}/{$file}", base_path($file));
        }
    }

    File::deleteDirectory($backup);
}

function installFreshLaravelAppFixture(): void
{
    foreach ([app_path('Domain'), app_path('Application'), app_path('Infrastructure'), app_path('Core')] as $directory) {
        File::deleteDirectory($directory);
    }

    File::delete(config_path('clean-architecture.php'));
    File::copyDirectory(__DIR__ . '/../fixtures/laravel-app', base_path());
}

describe('Install with --user-in-domain', function () {

    beforeEach(function () {
        $this->skeletonBackup = backUpSkeletonForUserInDomain();
        installFreshLaravelAppFixture();
    });

    afterEach(function () {
        restoreSkeletonForUserInDomain($this->skeletonBackup);

        File::deleteDirectory(base_path('storage/app/user-in-domain'));
        File::deleteDirectory(base_path('vendor/user-in-domain'));

        if (File::isDirectory(base_path('vendor')) && File::isEmptyDirectory(base_path('vendor'))) {
            File::deleteDirectory(base_path('vendor'));
        }
    });

    it('moves the user model into the domain', function (?array $config, string $path, string $namespace) {
        foreach ($config ?? [] as $key => $value) {
            config()->set($key, $value);
        }

        $this->artisan('clean-arch:install', ['--user-in-domain' => true])->assertExitCode(0);

        expect(File::exists(base_path($path)))->toBeTrue("Expected the user model at {$path}")
            ->and(File::exists(app_path('Models/User.php')))->toBeFalse()
            ->and(File::get(base_path($path)))
            ->toContain("namespace {$namespace};")
            ->toContain('class User extends Authenticatable')
            ->toContain('function newFactory()');
    })->with([
        'defaults'             => [null, 'app/Domain/Users/Models/User.php', 'App\Domain\Users\Models'],
        'no model directory'   => [['clean-architecture.generation.model_directory' => null], 'app/Domain/Users/User.php', 'App\Domain\Users'],
        'custom domain folder' => [['clean-architecture.directories.domain' => 'app/Core/Domain'], 'app/Core/Domain/Users/Models/User.php', 'App\Core\Domain\Users\Models'],
    ]);

    it('builds the moved model through its factory', function () {
        $this->artisan('clean-arch:install', ['--user-in-domain' => true])->assertExitCode(0);

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
            $user = User::factory()->make();
        } finally {
            spl_autoload_unregister($autoloader);
        }

        expect($user)->toBeInstanceOf(User::class)
            ->and(File::get(database_path('factories/UserFactory.php')))
            ->toContain("    protected \$model = User::class;\n\n    /**");
    });

    it('points the skeleton files at the moved model', function (string $file) {
        $this->artisan('clean-arch:install', ['--user-in-domain' => true])
            ->expectsOutputToContain("Updated: {$file}")
            ->assertExitCode(0);

        expect(File::get(base_path($file)))
            ->not->toContain('App\Models\User')
            ->toContain('App\Domain\Users\Models\User');
    })->with([
        'config/auth.php',
        'database/factories/UserFactory.php',
        'database/seeders/DatabaseSeeder.php',
    ]);

    it('updates references under tests but not under vendor or storage', function () {
        $test = <<<'PHP'
            <?php

            use App\Models\User;
            use App\Models\UserProfile;

            it('finds the user', fn () => expect(User::class)->toBe('App\\Models\\User'));
            PHP;
        $outside = "<?php\n\nreturn App\\Models\\User::class;\n";

        File::ensureDirectoryExists(base_path('tests/Feature'));
        File::put(base_path('tests/Feature/UserTest.php'), $test);
        File::ensureDirectoryExists(base_path('vendor/user-in-domain'));
        File::put(base_path('vendor/user-in-domain/Package.php'), $outside);
        File::ensureDirectoryExists(base_path('storage/app/user-in-domain'));
        File::put(base_path('storage/app/user-in-domain/Cached.php'), $outside);

        $this->artisan('clean-arch:install', ['--user-in-domain' => true])
            ->expectsOutputToContain('Updated: tests/Feature/UserTest.php')
            ->assertExitCode(0);

        expect(File::get(base_path('tests/Feature/UserTest.php')))
            ->toContain('use App\Domain\Users\Models\User;')
            ->toContain('use App\Models\UserProfile;')
            ->toContain("'App\\\\Domain\\\\Users\\\\Models\\\\User'")
            ->and(File::get(base_path('vendor/user-in-domain/Package.php')))->toBe($outside)
            ->and(File::get(base_path('storage/app/user-in-domain/Cached.php')))->toBe($outside);
    });

    it('registers the user in a morph map once', function () {
        $this->artisan('clean-arch:install', ['--user-in-domain' => true])
            ->expectsOutputToContain('Updated: app/Providers/AppServiceProvider.php')
            ->assertExitCode(0);
        $this->artisan('clean-arch:install', ['--user-in-domain' => true])->assertExitCode(0);

        $provider = File::get(app_path('Providers/AppServiceProvider.php'));

        expect($provider)
            ->toContain('use App\Domain\Users\Models\User;')
            ->toContain('use Illuminate\Database\Eloquent\Relations\Relation;')
            ->toContain("'user' => User::class")
            ->and(substr_count($provider, 'Relation::morphMap('))->toBe(1);

        token_get_all($provider, TOKEN_PARSE);
    });

    it('renames the broadcast channel and warns about frontend listeners', function () {
        File::put(base_path('routes/channels.php'), <<<'PHP'
            <?php

            use Illuminate\Support\Facades\Broadcast;

            Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
                return (int) $user->id === (int) $id;
            });
            PHP);
        $script = "Echo.private(`App.Models.User.\${userId}`).notification(console.log);\n";
        File::ensureDirectoryExists(base_path('resources/js'));
        File::put(base_path('resources/js/echo.js'), $script);

        $this->artisan('clean-arch:install', ['--user-in-domain' => true])
            ->expectsOutputToContain('Updated: routes/channels.php')
            ->expectsOutputToContain('resources/js/echo.js')
            ->assertExitCode(0);

        expect(File::get(base_path('routes/channels.php')))
            ->toContain("Broadcast::channel('App.Domain.Users.Models.User.{id}'")
            ->and(File::get(base_path('resources/js/echo.js')))->toBe($script);
    });

    it('removes app/Http and app/Models once they hold nothing of the app', function () {
        $this->artisan('clean-arch:install', ['--user-in-domain' => true])
            ->expectsOutputToContain('Removed: app/Http')
            ->expectsOutputToContain('Removed: app/Models')
            ->assertExitCode(0);

        expect(File::isDirectory(app_path('Http')))->toBeFalse()
            ->and(File::isDirectory(app_path('Models')))->toBeFalse();
    });

    it('keeps app/Http when it holds another controller', function () {
        File::put(app_path('Http/Controllers/HomeController.php'), "<?php\n\nnamespace App\\Http\\Controllers;\n\nclass HomeController extends Controller {}\n");

        $this->artisan('clean-arch:install', ['--user-in-domain' => true])
            ->expectsOutputToContain('Kept: app/Http')
            ->assertExitCode(0);

        expect(File::exists(app_path('Http/Controllers/Controller.php')))->toBeTrue()
            ->and(File::exists(app_path('Http/Controllers/HomeController.php')))->toBeTrue();
    });

    it('keeps the base controller when a route file names it', function () {
        File::append(base_path('routes/web.php'), "\n// extends App\\Http\\Controllers\\Controller\n");

        $this->artisan('clean-arch:install', ['--user-in-domain' => true])
            ->expectsOutputToContain('Kept: app/Http')
            ->assertExitCode(0);

        expect(File::exists(app_path('Http/Controllers/Controller.php')))->toBeTrue();
    });

    it('refuses to move when app/Models holds other models', function () {
        File::put(app_path('Models/Post.php'), "<?php\n\nnamespace App\\Models;\n\nclass Post {}\n");
        $auth = File::get(config_path('auth.php'));

        $this->artisan('clean-arch:install', ['--user-in-domain' => true])
            ->expectsOutputToContain('README')
            ->assertExitCode(1);

        expect(File::exists(app_path('Models/User.php')))->toBeTrue()
            ->and(File::isDirectory(app_path('Domain')))->toBeFalse()
            ->and(File::get(config_path('auth.php')))->toBe($auth);
    });

    it('moves the user next to other models when forced', function () {
        File::put(app_path('Models/Post.php'), "<?php\n\nnamespace App\\Models;\n\nclass Post {}\n");

        $this->artisan('clean-arch:install', ['--user-in-domain' => true, '--force' => true])
            ->expectsOutputToContain('Kept: app/Models')
            ->assertExitCode(0);

        expect(File::exists(app_path('Domain/Users/Models/User.php')))->toBeTrue()
            ->and(File::exists(app_path('Models/User.php')))->toBeFalse()
            ->and(File::exists(app_path('Models/Post.php')))->toBeTrue();
    });

    it('does nothing when the user is already in the domain', function () {
        $this->artisan('clean-arch:install', ['--user-in-domain' => true])->assertExitCode(0);

        $files = collect(['app/Domain/Users/Models/User.php', 'app/Providers/AppServiceProvider.php', 'config/auth.php', 'database/factories/UserFactory.php'])
            ->mapWithKeys(fn (string $file): array => [$file => File::get(base_path($file))]);

        $this->artisan('clean-arch:install', ['--user-in-domain' => true])
            ->expectsOutputToContain('already')
            ->assertExitCode(0);

        foreach ($files as $file => $content) {
            expect(File::get(base_path($file)))->toBe($content);
        }
    });

    it('refuses to move when the destination already exists', function () {
        File::ensureDirectoryExists(app_path('Domain/Users/Models'));
        File::put(app_path('Domain/Users/Models/User.php'), "<?php\n\n// existing\n");
        $auth = File::get(config_path('auth.php'));

        $this->artisan('clean-arch:install', ['--user-in-domain' => true])
            ->expectsOutputToContain('app/Domain/Users/Models/User.php')
            ->assertExitCode(1);

        expect(File::get(app_path('Domain/Users/Models/User.php')))->toBe("<?php\n\n// existing\n")
            ->and(File::exists(app_path('Models/User.php')))->toBeTrue()
            ->and(File::exists(app_path('Domain/Shared/BaseModel.php')))->toBeFalse()
            ->and(File::get(config_path('auth.php')))->toBe($auth);
    });

    it('leaves the user model alone without the option', function () {
        $model = File::get(app_path('Models/User.php'));
        $auth  = File::get(config_path('auth.php'));

        $this->artisan('clean-arch:install')->assertExitCode(0);

        expect(File::get(app_path('Models/User.php')))->toBe($model)
            ->and(File::get(config_path('auth.php')))->toBe($auth)
            ->and(File::isDirectory(app_path('Domain/Users')))->toBeFalse()
            ->and(File::exists(app_path('Http/Controllers/Controller.php')))->toBeTrue();
    });
});
