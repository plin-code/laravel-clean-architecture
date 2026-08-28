<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;

class MakeMailCommand extends Command
{
    use RendersStubs;

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

        $this->info("Creating mailable: {$name}");
        $this->createMail($name);
        $this->info("Mailable {$name} created successfully!");

        return self::SUCCESS;
    }

    protected function createMail(string $name): void
    {
        $stub    = $this->getStub('mail');
        $content = $this->replaceDomainPlaceholders($stub, $name);

        $mailPath = app_path('Infrastructure/Mail');

        if (! $this->files->isDirectory($mailPath)) {
            $this->files->makeDirectory($mailPath, 0755, true);
        }

        $this->files->put("{$mailPath}/{$name}Mail.php", $content);
        $this->info("Created: Infrastructure/Mail/{$name}Mail.php");
    }
}
