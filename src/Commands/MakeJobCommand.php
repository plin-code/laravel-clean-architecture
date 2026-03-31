<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeJobCommand extends Command
{
    protected $signature = 'clean-arch:make-job {name : The name of the job}
                          {--force : Overwrite existing files}';

    protected $description = 'Create a new job in the Application layer';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        $this->info("Creating job: {$name}");
        $this->createJob($name);
        $this->info("Job {$name} created successfully!");

        return self::SUCCESS;
    }

    protected function createJob(string $name): void
    {
        $stub    = $this->getStub('job');
        $content = $this->replacePlaceholders($stub, $name);

        $jobPath = app_path('Application/Jobs');

        if (! $this->files->isDirectory($jobPath)) {
            $this->files->makeDirectory($jobPath, 0755, true);
        }

        $this->files->put("{$jobPath}/{$name}Job.php", $content);
        $this->info("Created: Application/Jobs/{$name}Job.php");
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
