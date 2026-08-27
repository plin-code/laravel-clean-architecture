<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Concerns\ParsesPhpSource;
use Symfony\Component\Finder\Finder;

class ValidateArchitectureCommand extends Command
{
    use ParsesPhpSource;

    /**
     * Parent class identifying a console command.
     */
    protected const CONSOLE_COMMAND_CLASS = 'Illuminate\\Console\\Command';

    /**
     * Interface identifying a queued job.
     */
    protected const SHOULD_QUEUE_INTERFACE = 'Illuminate\\Contracts\\Queue\\ShouldQueue';

    protected $signature = 'clean-arch:validate';

    protected $description = 'Validate Clean Architecture dependency rules';

    protected Filesystem $files;

    protected int $violationCount = 0;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $this->info('Clean Architecture Validation');
        $this->info('=============================');
        $this->newLine();

        $this->runImportCheck('Domain has no Application imports', 'app/Domain', 'App\\Application\\');
        $this->runImportCheck('Domain has no Infrastructure imports', 'app/Domain', 'App\\Infrastructure\\');
        $this->runImportCheck('Application has no Infrastructure imports', 'app/Application', 'App\\Infrastructure\\');
        $this->runFilePatternCheck('No Observers in Domain', 'app/Domain', '*Observer.php');
        $this->reportViolations('No Jobs in Infrastructure', $this->checkQueuedJobViolations('app/Infrastructure'));
        $this->reportViolations('No Commands in Infrastructure', $this->checkConsoleCommandViolations('app/Infrastructure'));
        $this->runDirectoryCheck('No duplicate Services directory', 'app/Infrastructure/Services');

        $this->newLine();

        if ($this->violationCount > 0) {
            $this->error("Found {$this->violationCount} violation(s).");

            return self::FAILURE;
        }

        $this->info('No violations found.');

        return self::SUCCESS;
    }

    protected function runImportCheck(string $label, string $directory, string $pattern): void
    {
        $path = base_path($directory);
        if (! $this->files->isDirectory($path)) {
            $this->line("  ✓ {$label} (directory not found, skipped)");

            return;
        }

        $this->reportViolations($label, $this->checkImportViolations($directory, $pattern));
    }

    /**
     * @param  list<string>  $violations
     */
    protected function reportViolations(string $label, array $violations): void
    {
        if (empty($violations)) {
            $this->line("  ✓ {$label}");

            return;
        }

        $count = count($violations);
        $this->violationCount += $count;
        $this->line("  ✗ {$label} ({$count} violation(s))");

        foreach ($violations as $violation) {
            $this->line("    - {$violation}");
        }
    }

    public function checkImportViolations(string $directory, string $importPattern): array
    {
        $violations = [];
        $path       = base_path($directory);

        if (! $this->files->isDirectory($path)) {
            return $violations;
        }

        $finder = new Finder;
        $finder->files()->in($path)->name('*.php');

        foreach ($finder as $file) {
            $contents = $file->getContents();
            $lines    = explode("\n", $contents);

            foreach ($lines as $lineNumber => $line) {
                if (str_contains($line, "use {$importPattern}")) {
                    $relativePath = str_replace(base_path() . '/', '', $file->getRealPath());
                    $violations[] = "{$relativePath}:" . ($lineNumber + 1) . ' → ' . trim($line);
                }
            }
        }

        return $violations;
    }

    protected function runFilePatternCheck(string $label, string $directory, string $pattern): void
    {
        $this->reportViolations($label, $this->checkFilePatternViolations($directory, $pattern));
    }

    /**
     * @return list<string>
     */
    public function checkFilePatternViolations(string $directory, string $pattern): array
    {
        $violations = [];
        $path       = base_path($directory);

        if (! $this->files->isDirectory($path)) {
            return $violations;
        }

        $finder = new Finder;
        $finder->files()->in($path)->name($pattern);

        foreach ($finder as $file) {
            $relativePath = str_replace(base_path() . '/', '', $file->getRealPath());
            $violations[] = $relativePath;
        }

        return $violations;
    }

    /**
     * Find console commands, recognised by their parent class rather than by
     * their file name, so that a `CommandPaletteController` is not reported.
     *
     * @return list<string>
     */
    public function checkConsoleCommandViolations(string $directory): array
    {
        return $this->checkClassViolations(
            $directory,
            fn (array $signature): bool => in_array(self::CONSOLE_COMMAND_CLASS, $signature['extends'], true)
        );
    }

    /**
     * Find queued jobs, recognised by the ShouldQueue contract rather than by
     * their file name, so that a `JobApplicationResource` is not reported.
     *
     * @return list<string>
     */
    public function checkQueuedJobViolations(string $directory): array
    {
        return $this->checkClassViolations(
            $directory,
            fn (array $signature): bool => in_array(self::SHOULD_QUEUE_INTERFACE, $signature['implements'], true)
        );
    }

    /**
     * @param  callable(array{extends: list<string>, implements: list<string>}): bool  $matches
     * @return list<string>
     */
    protected function checkClassViolations(string $directory, callable $matches): array
    {
        $violations = [];
        $path       = base_path($directory);

        if (! $this->files->isDirectory($path)) {
            return $violations;
        }

        $finder = new Finder;
        $finder->files()->in($path)->name('*.php');

        foreach ($finder as $file) {
            if ($matches($this->parseClassSignature($file->getContents()))) {
                $violations[] = str_replace(base_path() . '/', '', $file->getRealPath());
            }
        }

        return $violations;
    }

    protected function runDirectoryCheck(string $label, string $directory): void
    {
        if ($this->checkDirectoryNotExists($directory)) {
            $this->violationCount++;
            $this->line("  ✗ {$label}");
            $this->line("    - {$directory} exists");
        } else {
            $this->line("  ✓ {$label}");
        }
    }

    public function checkDirectoryNotExists(string $directory): bool
    {
        return $this->files->isDirectory(base_path($directory));
    }
}
