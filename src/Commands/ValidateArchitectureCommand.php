<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Concerns\BuildsClassMap;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;
use Symfony\Component\Finder\Finder;

class ValidateArchitectureCommand extends Command
{
    use BuildsClassMap;
    use ResolvesArchitectureDirectories;

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

        $domain         = $this->layerDirectory('domain');
        $application    = $this->layerDirectory('application');
        $infrastructure = $this->layerDirectory('infrastructure');

        $this->runImportCheck('domain_no_application_imports', 'Domain has no Application imports', $domain, $this->layerNamespace('application'));
        $this->runImportCheck('domain_no_infrastructure_imports', 'Domain has no Infrastructure imports', $domain, $this->layerNamespace('infrastructure'));
        $this->runImportCheck('application_no_infrastructure_imports', 'Application has no Infrastructure imports', $application, $this->layerNamespace('infrastructure'));
        $this->runFilePatternCheck('no_observers_in_domain', 'No Observers in Domain', $domain, '*Observer.php');
        $this->runClassCheck('no_jobs_in_infrastructure', 'No Jobs in Infrastructure', fn (): array => $this->checkQueuedJobViolations($infrastructure));
        $this->runClassCheck('no_commands_in_infrastructure', 'No Commands in Infrastructure', fn (): array => $this->checkConsoleCommandViolations($infrastructure));
        $this->runDirectoryCheck('no_duplicate_services_directory', 'No duplicate Services directory', "{$infrastructure}/Services");

        $this->newLine();

        if ($this->violationCount > 0) {
            $this->error("Found {$this->violationCount} violation(s).");

            return self::FAILURE;
        }

        $this->info('No violations found.');

        return self::SUCCESS;
    }

    protected function runImportCheck(string $rule, string $label, string $directory, string $pattern): void
    {
        if ($this->skipsRule($rule, $label)) {
            return;
        }

        $path = base_path($directory);
        if (! $this->files->isDirectory($path)) {
            $this->line("  ✓ {$label} (directory not found, skipped)");

            return;
        }

        $this->reportViolations($label, $this->checkImportViolations($directory, $pattern));
    }

    /**
     * @param  callable(): list<string>  $violations
     */
    protected function runClassCheck(string $rule, string $label, callable $violations): void
    {
        if ($this->skipsRule($rule, $label)) {
            return;
        }

        $this->reportViolations($label, $violations());
    }

    /**
     * Whether a rule is turned off in config/clean-architecture.php.
     *
     * Rules are enabled unless explicitly disabled, so a project without a
     * published config file keeps running all of them.
     */
    protected function isRuleEnabled(string $rule): bool
    {
        return config("clean-architecture.validation.rules.{$rule}", true) !== false;
    }

    protected function skipsRule(string $rule, string $label): bool
    {
        if ($this->isRuleEnabled($rule)) {
            return false;
        }

        $this->line("  - {$label} (disabled)");

        return true;
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

    /**
     * @return list<string>
     */
    public function checkImportViolations(string $directory, string $importPattern): array
    {
        $violations = [];
        $path       = base_path($directory);

        if (! $this->files->isDirectory($path)) {
            return $violations;
        }

        $prefix = ltrim($importPattern, '\\');

        $finder = new Finder;
        $finder->files()->in($path)->name('*.php');

        foreach ($finder as $file) {
            $contents     = $file->getContents();
            $lines        = explode("\n", $contents);
            $relativePath = str_replace(base_path() . '/', '', $file->getRealPath());

            foreach ($this->extractUseStatements($contents) as $statement) {
                if (! str_starts_with($statement['name'], $prefix)) {
                    continue;
                }

                $line         = $statement['line'];
                $source       = trim($lines[$line - 1] ?? '');
                $violations[] = "{$relativePath}:{$line} → {$source}";
            }
        }

        return $violations;
    }

    protected function runFilePatternCheck(string $rule, string $label, string $directory, string $pattern): void
    {
        if ($this->skipsRule($rule, $label)) {
            return;
        }

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
     * Find console commands, recognised by their ancestors rather than by their
     * file name, so that a `CommandPaletteController` is not reported and a
     * command extending a project specific base class still is.
     *
     * @return list<string>
     */
    public function checkConsoleCommandViolations(string $directory): array
    {
        return $this->checkClassViolations($directory, self::CONSOLE_COMMAND_CLASS);
    }

    /**
     * Find queued jobs, recognised by the ShouldQueue contract reached anywhere
     * in their inheritance chain, so that a `JobApplicationResource` is not
     * reported and a job extending an abstract base job still is.
     *
     * @return list<string>
     */
    public function checkQueuedJobViolations(string $directory): array
    {
        return $this->checkClassViolations($directory, self::SHOULD_QUEUE_INTERFACE);
    }

    /**
     * Files under a directory declaring a class that descends from an ancestor.
     *
     * Only classes count. An interface extending `ShouldQueue` is a contract,
     * not a job, and reporting it would be noise.
     *
     * @return list<string>
     */
    protected function checkClassViolations(string $directory, string $ancestor): array
    {
        $path = base_path($directory);

        if (! $this->files->isDirectory($path)) {
            return [];
        }

        $prefix     = rtrim($path, '/') . '/';
        $violations = [];

        foreach ($this->classMap() as $fqcn => $entry) {
            if ($entry['kind'] !== 'class' || ! str_starts_with($entry['path'], $prefix)) {
                continue;
            }

            if (! in_array($ancestor, $this->ancestorsOf($fqcn), true)) {
                continue;
            }

            $violations[$entry['path']] = str_replace(base_path() . '/', '', $entry['path']);
        }

        $violations = array_values($violations);
        sort($violations);

        return $violations;
    }

    protected function runDirectoryCheck(string $rule, string $label, string $directory): void
    {
        if ($this->skipsRule($rule, $label)) {
            return;
        }

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
