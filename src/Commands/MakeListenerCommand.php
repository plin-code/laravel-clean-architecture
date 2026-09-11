<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;
use PlinCode\LaravelCleanArchitecture\Concerns\WritesFiles;

class MakeListenerCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;
    use WritesFiles;

    protected $signature = 'clean-arch:make-listener {name : The name of the listener}
                          {--force : Overwrite existing files}';

    protected $description = 'Create a new listener in the Application layer';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        $this->resolveForce();

        $this->info("Creating listener: {$name}");
        $exitCode = $this->createListener($name);

        if ($exitCode === self::SUCCESS) {
            $this->info("Listener {$name} created successfully!");
        }

        return $exitCode;
    }

    protected function createListener(string $name): int
    {
        $stub    = $this->getStub('listener');
        $content = $this->replaceDomainPlaceholders($stub, $name);

        $directory    = $this->layerDirectory('application') . '/Listeners';
        $listenerPath = base_path($directory);

        if (! $this->files->isDirectory($listenerPath)) {
            $this->files->makeDirectory($listenerPath, 0755, true);
        }

        return $this->writeOrFail("{$listenerPath}/{$name}EventListener.php", $content, "{$directory}/{$name}EventListener.php");
    }
}
