<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class ValidateArchitectureCommand extends Command
{
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
        $this->runFilePatternCheck('No Observers in Domain', 'app/Domain', '*Observer*');
        $this->runFilePatternCheck('No Jobs in Infrastructure', 'app/Infrastructure', '*Job*');
        $this->runFilePatternCheck('No Commands in Infrastructure', 'app/Infrastructure', '*Command*');
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

        $violations = $this->checkImportViolations($directory, $pattern);

        if (empty($violations)) {
            $this->line("  ✓ {$label}");
        } else {
            $count = count($violations);
            $this->violationCount += $count;
            $this->line("  ✗ {$label} ({$count} violation(s))");
            foreach ($violations as $violation) {
                $this->line("    - {$violation}");
            }
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
        $violations = $this->checkFilePatternViolations($directory, $pattern);

        if (empty($violations)) {
            $this->line("  ✓ {$label}");
        } else {
            $count = count($violations);
            $this->violationCount += $count;
            $this->line("  ✗ {$label} ({$count} violation(s))");
            foreach ($violations as $violation) {
                $this->line("    - {$violation}");
            }
        }
    }

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
