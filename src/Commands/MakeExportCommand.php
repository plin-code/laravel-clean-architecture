<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeExportCommand extends Command
{
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
        $content = $this->replacePlaceholders($stub, $name);

        $exportPath = app_path('Infrastructure/Exports');

        if (! $this->files->isDirectory($exportPath)) {
            $this->files->makeDirectory($exportPath, 0755, true);
        }

        $this->files->put("{$exportPath}/{$name}Export.php", $content);
        $this->info("Created: Infrastructure/Exports/{$name}Export.php");
    }

    protected function replacePlaceholders(string $content, string $name): string
    {
        $pluralName     = Str::plural($name);
        $domainVariable = Str::camel($name);

        return str_replace(
            ['{{DomainName}}', '{{PluralDomainName}}', '{{domainVariable}}'],
            [$name, $pluralName, $domainVariable],
            $content
        );
    }

    protected function getStub(string $stub): string
    {
        $stubPath = __DIR__ . "/../../stubs/{$stub}.stub";

        if (! $this->files->exists($stubPath)) {
            throw new \Exception("Stub file not found: {$stubPath}");
        }

        return $this->files->get($stubPath);
    }
}
