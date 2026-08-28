<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Concerns\ParsesPhpSource;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;
use Symfony\Component\Finder\Finder;

class ValidateArchitectureCommand extends Command
{
    use ParsesPhpSource;
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
        $this->warn('clean-arch:validate is deprecated and will be removed in 3.0.0.');
        $this->warn('Run clean-arch:make-arch-rules and check the generated phparkitect.php instead.');
        $this->newLine();

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
}
