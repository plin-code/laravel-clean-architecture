<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;
use PlinCode\LaravelCleanArchitecture\Concerns\WritesFiles;

class MakeExportCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;
    use WritesFiles;

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

        $this->resolveForce();

        $this->info("Creating export: {$name}");
        $exitCode = $this->createExport($name);

        if ($exitCode === self::SUCCESS) {
            $this->info("Export {$name} created successfully!");
        }

        return $exitCode;
    }

    protected function createExport(string $name): int
    {
        $stub    = $this->getStub('export');
        $content = $this->replaceDomainPlaceholders($stub, $name);

        $directory  = $this->layerDirectory('infrastructure') . '/Exports';
        $exportPath = base_path($directory);

        if (! $this->files->isDirectory($exportPath)) {
            $this->files->makeDirectory($exportPath, 0755, true);
        }

        return $this->writeOrFail("{$exportPath}/{$name}Export.php", $content, "{$directory}/{$name}Export.php");
    }
}
