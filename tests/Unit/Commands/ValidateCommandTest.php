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
