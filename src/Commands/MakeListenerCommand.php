<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;

class MakeListenerCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;

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

        $this->info("Creating listener: {$name}");
        $this->createListener($name);
        $this->info("Listener {$name} created successfully!");

        return self::SUCCESS;
    }

    protected function createListener(string $name): void
    {
        $stub    = $this->getStub('listener');
        $content = $this->replaceDomainPlaceholders($stub, $name);

        $directory    = $this->layerDirectory('application') . '/Listeners';
        $listenerPath = base_path($directory);

        if (! $this->files->isDirectory($listenerPath)) {
            $this->files->makeDirectory($listenerPath, 0755, true);
        }

        $this->files->put("{$listenerPath}/{$name}EventListener.php", $content);
        $this->info("Created: {$directory}/{$name}EventListener.php");
    }
}
