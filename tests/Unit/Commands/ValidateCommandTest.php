<?php

use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Commands\ValidateArchitectureCommand;

/**
 * Write a PHP file inside the testbench application and return its path
 * relative to the base path.
 */
function writeAppFile(string $relativePath, string $contents): string
{
    $path = base_path($relativePath);
    (new Filesystem)->ensureDirectoryExists(dirname($path));
    (new Filesystem)->put($path, $contents);

    return $relativePath;
}

describe('ValidateArchitectureCommand', function () {
    beforeEach(function () {
        $this->filesystem = new Filesystem;
        $this->command    = new ValidateArchitectureCommand($this->filesystem);

        $mockOutput = mock('Symfony\Component\Console\Output\OutputInterface');
        $mockOutput->shouldReceive('writeln')->andReturn();
        $mockOutput->shouldReceive('write')->andReturn();

        $reflection     = new ReflectionClass($this->command);
        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputProperty->setValue($this->command, $mockOutput);
    });

    afterEach(function () {
        $this->filesystem->deleteDirectory(base_path('app/Infrastructure'));
        $this->filesystem->deleteDirectory(base_path('app/Domain'));
    });

    it('has correct command signature', function () {
        expect($this->command->getName())->toBe('clean-arch:validate');
    });

    it('has correct description', function () {
        expect($this->command->getDescription())->toBe('Validate Clean Architecture dependency rules');
    });

    it('returns empty violations for non-existent directory', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('checkImportViolations');
        $method->setAccessible(true);

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $violations = $method->invoke($this->command, 'app/Domain', 'App\\Application\\');
        expect($violations)->toBeArray()->toBeEmpty();
    });

    it('returns empty violations for non-existent directory in file pattern check', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('checkFilePatternViolations');
        $method->setAccessible(true);

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $violations = $method->invoke($this->command, 'app/Domain', '*Observer*');
        expect($violations)->toBeArray()->toBeEmpty();
    });

    it('detects duplicate services directory when it exists', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('checkDirectoryNotExists');
        $method->setAccessible(true);

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(true);
        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, 'app/Infrastructure/Services');
        expect($result)->toBeTrue();
    });

    it('passes when services directory does not exist in infrastructure', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('checkDirectoryNotExists');
        $method->setAccessible(true);

        $mockFilesystem = mock(Filesystem::class);
        $mockFilesystem->shouldReceive('isDirectory')->andReturn(false);
        $reflection->getProperty('files')->setValue($this->command, $mockFilesystem);

        $result = $method->invoke($this->command, 'app/Infrastructure/Services');
        expect($result)->toBeFalse();
    });

    it('does not report a business class whose name merely contains Command', function () {
        writeAppFile(
            'app/Infrastructure/Http/Controllers/Api/CommandPaletteController.php',
            <<<'PHP'
            <?php

            namespace App\Infrastructure\Http\Controllers\Api;

            class CommandPaletteController
            {
                public function index(): void {}
            }
            PHP
        );

        expect($this->command->checkConsoleCommandViolations('app/Infrastructure'))->toBeEmpty();
    });

    it('reports a class extending the Illuminate console command', function () {
        $path = writeAppFile(
            'app/Infrastructure/Console/Commands/SyncPractices.php',
            <<<'PHP'
            <?php

            namespace App\Infrastructure\Console\Commands;

            use Illuminate\Console\Command;

            class SyncPractices extends Command
            {
                protected $signature = 'sync:practices';
            }
            PHP
        );

        expect($this->command->checkConsoleCommandViolations('app/Infrastructure'))->toBe([$path]);
    });

    it('reports a class extending the fully qualified console command', function () {
        $path = writeAppFile(
            'app/Infrastructure/Console/Commands/PruneCache.php',
            <<<'PHP'
            <?php

            namespace App\Infrastructure\Console\Commands;

            class PruneCache extends \Illuminate\Console\Command {}
            PHP
        );

        expect($this->command->checkConsoleCommandViolations('app/Infrastructure'))->toBe([$path]);
    });

    it('does not report a business class whose name merely contains Job', function () {
        writeAppFile(
            'app/Infrastructure/Http/Resources/JobApplicationResource.php',
            <<<'PHP'
            <?php

            namespace App\Infrastructure\Http\Resources;

            class JobApplicationResource {}
            PHP
        );

        expect($this->command->checkQueuedJobViolations('app/Infrastructure'))->toBeEmpty();
    });

    it('reports a class implementing ShouldQueue', function () {
        $path = writeAppFile(
            'app/Infrastructure/Notifications/SendReport.php',
            <<<'PHP'
            <?php

            namespace App\Infrastructure\Notifications;

            use Illuminate\Contracts\Queue\ShouldQueue;

            class SendReport implements ShouldQueue
            {
                public function handle(): void {}
            }
            PHP
        );

        expect($this->command->checkQueuedJobViolations('app/Infrastructure'))->toBe([$path]);
    });

    it('reports an aliased ShouldQueue implementation', function () {
        $path = writeAppFile(
            'app/Infrastructure/Notifications/SendInvoice.php',
            <<<'PHP'
            <?php

            namespace App\Infrastructure\Notifications;

            use Illuminate\Bus\Queueable;
            use Illuminate\Contracts\Queue\ShouldQueue as Queueable2;

            class SendInvoice implements Queueable2
            {
                use Queueable;
            }
            PHP
        );

        expect($this->command->checkQueuedJobViolations('app/Infrastructure'))->toBe([$path]);
    });

    it('keeps every rule enabled when the config is not published', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('isRuleEnabled');
        $method->setAccessible(true);

        expect($method->invoke($this->command, 'no_commands_in_infrastructure'))->toBeTrue();
        expect($method->invoke($this->command, 'no_duplicate_services_directory'))->toBeTrue();
    });

    it('reads a disabled rule from the config', function () {
        config()->set('clean-architecture.validation.rules.no_commands_in_infrastructure', false);

        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('isRuleEnabled');
        $method->setAccessible(true);

        expect($method->invoke($this->command, 'no_commands_in_infrastructure'))->toBeFalse();
        expect($method->invoke($this->command, 'no_jobs_in_infrastructure'))->toBeTrue();
    });

    it('skips a disabled rule instead of counting violations', function () {
        config()->set('clean-architecture.validation.rules.no_commands_in_infrastructure', false);

        writeAppFile(
            'app/Infrastructure/Console/Commands/SyncPractices.php',
            <<<'PHP'
            <?php

            namespace App\Infrastructure\Console\Commands;

            use Illuminate\Console\Command;

            class SyncPractices extends Command {}
            PHP
        );

        $reflection = new ReflectionClass($this->command);

        $method = $reflection->getMethod('runClassCheck');
        $method->setAccessible(true);
        $method->invoke(
            $this->command,
            'no_commands_in_infrastructure',
            'No Commands in Infrastructure',
            fn (): array => $this->command->checkConsoleCommandViolations('app/Infrastructure')
        );

        $violationCount = $reflection->getProperty('violationCount');
        $violationCount->setAccessible(true);

        expect($violationCount->getValue($this->command))->toBe(0);
    });

    it('counts violations for the same rule when it stays enabled', function () {
        writeAppFile(
            'app/Infrastructure/Console/Commands/SyncPractices.php',
            <<<'PHP'
            <?php

            namespace App\Infrastructure\Console\Commands;

            use Illuminate\Console\Command;

            class SyncPractices extends Command {}
            PHP
        );

        $reflection = new ReflectionClass($this->command);

        $method = $reflection->getMethod('runClassCheck');
        $method->setAccessible(true);
        $method->invoke(
            $this->command,
            'no_commands_in_infrastructure',
            'No Commands in Infrastructure',
            fn (): array => $this->command->checkConsoleCommandViolations('app/Infrastructure')
        );

        $violationCount = $reflection->getProperty('violationCount');
        $violationCount->setAccessible(true);

        expect($violationCount->getValue($this->command))->toBe(1);
    });

    it('falls back to the default directories when the config is not published', function () {
        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('layerDirectory');
        $method->setAccessible(true);

        expect($method->invoke($this->command, 'domain'))->toBe('app/Domain');
        expect($method->invoke($this->command, 'application'))->toBe('app/Application');
        expect($method->invoke($this->command, 'infrastructure'))->toBe('app/Infrastructure');
    });

    it('reads the configured directories and derives their namespaces', function () {
        config()->set('clean-architecture.directories.domain', 'app/Core/Domain/');
        config()->set('clean-architecture.default_namespace', 'Acme');

        $reflection = new ReflectionClass($this->command);

        $directory = $reflection->getMethod('layerDirectory');
        $directory->setAccessible(true);
        expect($directory->invoke($this->command, 'domain'))->toBe('app/Core/Domain');

        $namespace = $reflection->getMethod('layerNamespace');
        $namespace->setAccessible(true);
        expect($namespace->invoke($this->command, 'domain'))->toBe('Acme\\Core\\Domain\\');
    });

    it('scans the configured domain directory', function () {
        config()->set('clean-architecture.directories.domain', 'app/Core');

        $path = writeAppFile(
            'app/Core/Practices/PracticeObserver.php',
            <<<'PHP'
            <?php

            namespace App\Core\Practices;

            class PracticeObserver {}
            PHP
        );

        $reflection = new ReflectionClass($this->command);
        $method     = $reflection->getMethod('layerDirectory');
        $method->setAccessible(true);

        expect($this->command->checkFilePatternViolations($method->invoke($this->command, 'domain'), '*Observer.php'))
            ->toBe([$path]);

        $this->filesystem->deleteDirectory(base_path('app/Core'));
    });

    it('reports a real infrastructure import in domain', function () {
        $path = writeAppFile(
            'app/Domain/Users/Models/User.php',
            <<<'PHP'
            <?php

            namespace App\Domain\Users\Models;

            use App\Infrastructure\Traits\HasSlug;

            class User
            {
                use HasSlug;
            }
            PHP
        );

        expect($this->command->checkImportViolations('app/Domain', 'App\\Infrastructure\\'))
            ->toBe(["{$path}:5 → use App\\Infrastructure\\Traits\\HasSlug;"]);
    });

    it('does not report a trait use inside the class body', function () {
        writeAppFile(
            'app/Domain/Users/Models/Member.php',
            <<<'PHP'
            <?php

            namespace App\Domain\Users\Models;

            class Member
            {
                use App\Infrastructure\Traits\HasSlug;
            }
            PHP
        );

        expect($this->command->checkImportViolations('app/Domain', 'App\\Infrastructure\\'))->toBeEmpty();
    });

    it('does not report a commented out import', function () {
        writeAppFile(
            'app/Domain/Users/Models/Guest.php',
            <<<'PHP'
            <?php

            namespace App\Domain\Users\Models;

            // use App\Infrastructure\Foo;
            /* use App\Infrastructure\Bar; */

            class Guest
            {
                public function label(): string
                {
                    return 'use App\Infrastructure\Baz;';
                }
            }
            PHP
        );

        expect($this->command->checkImportViolations('app/Domain', 'App\\Infrastructure\\'))->toBeEmpty();
    });

    it('does not report a closure use clause', function () {
        writeAppFile(
            'app/Domain/Users/bootstrap.php',
            <<<'PHP'
            <?php

            $prefix = 'App\Infrastructure\\';

            $callback = function () use ($prefix) {
                return $prefix;
            };
            PHP
        );

        expect($this->command->checkImportViolations('app/Domain', 'App\\Infrastructure\\'))->toBeEmpty();
    });

    it('reports every name of a grouped import', function () {
        $path = writeAppFile(
            'app/Domain/Users/Models/Team.php',
            <<<'PHP'
            <?php

            namespace App\Domain\Users\Models;

            use App\Infrastructure\Traits\{HasSlug, HasUuid};

            class Team {}
            PHP
        );

        expect($this->command->checkImportViolations('app/Domain', 'App\\Infrastructure\\'))->toBe([
            "{$path}:5 → use App\\Infrastructure\\Traits\\{HasSlug, HasUuid};",
            "{$path}:5 → use App\\Infrastructure\\Traits\\{HasSlug, HasUuid};",
        ]);
    });

    it('only matches the Observer suffix in domain', function () {
        writeAppFile(
            'app/Domain/Practices/Models/ObserverSlot.php',
            <<<'PHP'
            <?php

            namespace App\Domain\Practices\Models;

            class ObserverSlot {}
            PHP
        );

        $path = writeAppFile(
            'app/Domain/Practices/PracticeObserver.php',
            <<<'PHP'
            <?php

            namespace App\Domain\Practices;

            class PracticeObserver {}
            PHP
        );

        expect($this->command->checkFilePatternViolations('app/Domain', '*Observer.php'))->toBe([$path]);
    });
});
