<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Concerns\BuildsArchRules;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;
use PlinCode\LaravelCleanArchitecture\Concerns\WritesFiles;

/**
 * Write a phparkitect configuration built from config/clean-architecture.php.
 *
 * The package does not depend on phparkitect and does not run it: it generates
 * the file, the application installs phparkitect in require-dev and runs
 * `vendor/bin/phparkitect check`.
 */
class MakeArchRulesCommand extends Command
{
    use BuildsArchRules;
    use RendersStubs;
    use ResolvesArchitectureDirectories;
    use WritesFiles;

    protected $signature = 'clean-arch:make-arch-rules {--force : Overwrite an existing phparkitect.php}';

    protected $description = 'Generate a phparkitect configuration from the clean architecture rules';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $this->resolveForce();

        if (($error = $this->allowlistError()) !== null) {
            $this->error($error);

            return self::FAILURE;
        }

        $exitCode = $this->writeOrFail(base_path('phparkitect.php'), $this->renderConfig(), 'phparkitect.php');

        if ($exitCode === self::SUCCESS) {
            $this->line('Install phparkitect with "composer require --dev phparkitect/phparkitect", then run "vendor/bin/phparkitect check".');
        }

        return $exitCode;
    }

    /**
     * Fill the stub with the guard, the rules and the class sets.
     */
    protected function renderConfig(): string
    {
        return str_replace(
            ['{{AutoloadGuard}}', '{{ArchRules}}', '{{ClassSets}}'],
            [$this->autoloadGuard(), implode("\n\n", $this->archRules()), $this->archClassSets()],
            $this->getStub('phparkitect')
        );
    }
}
