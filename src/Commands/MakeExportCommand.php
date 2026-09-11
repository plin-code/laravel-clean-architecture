<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;

class MakeExportCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;

    protected $signature = 'clean-arch:make-export {name : The name of the export}
                          {--force : Overwrite existing files}';

    protected $description = 'Create a new export in the Infrastructure layer';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        $this->info("Creating export: {$name}");
        $this->createExport($name);
        $this->info("Export {$name} created successfully!");

        return self::SUCCESS;
    }

    protected function createExport(string $name): void
    {
        $stub    = $this->getStub('export');
        $content = $this->replaceDomainPlaceholders($stub, $name);

        $directory  = $this->layerDirectory('infrastructure') . '/Exports';
        $exportPath = base_path($directory);

        if (! $this->files->isDirectory($exportPath)) {
            $this->files->makeDirectory($exportPath, 0755, true);
        }

        $this->files->put("{$exportPath}/{$name}Export.php", $content);
        $this->info("Created: {$directory}/{$name}Export.php");
    }
}
