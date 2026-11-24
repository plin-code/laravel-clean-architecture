<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;
use PlinCode\LaravelCleanArchitecture\Concerns\WritesFiles;

/**
 * Write Pest tests for the conventions the layer rules cannot see.
 *
 * phparkitect checks what a class imports. It says nothing about the shape
 * of a class that sits in the right layer: an action with five public
 * methods, a listener that holds a whole use case, a form request without
 * rules. These tests read the classes from disk and check that shape.
 *
 * The package does not depend on Pest and does not run it: the files belong
 * to the application, which edits them as its conventions grow.
 */
class MakeArchTestsCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;
    use WritesFiles;

    protected $signature = 'clean-arch:make-arch-tests {--force : Overwrite the existing tests}';

    protected $description = 'Generate Pest tests for the conventions of actions, listeners and form requests';

    /**
     * File written under tests/Arch, and the stub it comes from.
     *
     * @var array<string, string>
     */
    protected array $tests = [
        'Classes.php'       => 'arch-tests-classes',
        'ActionsTest.php'   => 'arch-tests-actions',
        'ListenersTest.php' => 'arch-tests-listeners',
        'RequestsTest.php'  => 'arch-tests-requests',
    ];

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $this->resolveForce();

        $this->files->ensureDirectoryExists(base_path('tests/Arch'));

        foreach ($this->tests as $file => $stub) {
            $this->writeFile("tests/Arch/{$file}", $this->renderTest($stub));
        }

        if (! $this->suiteIsConfigured()) {
            $this->line('Add tests/Arch to a test suite in phpunit.xml, otherwise the tests never run.');
        }

        return self::SUCCESS;
    }

    /**
     * Fill the layer directories. The layer namespaces are filled by
     * getStub() as in every other stub.
     */
    protected function renderTest(string $stub): string
    {
        return str_replace(
            ['{{DomainDirectory}}', '{{ApplicationDirectory}}', '{{InfrastructureDirectory}}'],
            [
                $this->layerDirectory('domain'),
                $this->layerDirectory('application'),
                $this->layerDirectory('infrastructure'),
            ],
            $this->getStub($stub)
        );
    }

    /**
     * Whether phpunit.xml already names tests/Arch. Laravel ships with the
     * Unit and Feature suites only, so a new folder is not collected.
     */
    protected function suiteIsConfigured(): bool
    {
        foreach (['phpunit.xml', 'phpunit.xml.dist'] as $file) {
            if ($this->files->exists(base_path($file)) && str_contains($this->files->get(base_path($file)), 'tests/Arch')) {
                return true;
            }
        }

        return false;
    }
}
