<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;
use PlinCode\LaravelCleanArchitecture\Concerns\WritesFiles;

class MakeMailCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;
    use WritesFiles;

    protected $signature = 'clean-arch:make-mail {name : The name of the mailable}
                          {--force : Overwrite existing files}';

    protected $description = 'Create a new mailable in the Infrastructure layer';

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

        $this->info("Creating mailable: {$name}");
        $exitCode = $this->createMail($name);

        if ($exitCode === self::SUCCESS) {
            $this->info("Mailable {$name} created successfully!");
        }

        return $exitCode;
    }

    protected function createMail(string $name): int
    {
        $name    = $this->stripSuffix($name, 'Mail');
        $stub    = $this->getStub('mail');
        $content = $this->replaceDomainPlaceholders($stub, $name);

        $directory = $this->layerDirectory('infrastructure') . '/Mail';
        $mailPath  = base_path($directory);

        if (! $this->files->isDirectory($mailPath)) {
            $this->files->makeDirectory($mailPath, 0755, true);
        }

        return $this->writeOrFail("{$mailPath}/{$name}Mail.php", $content, "{$directory}/{$name}Mail.php");
    }
}
