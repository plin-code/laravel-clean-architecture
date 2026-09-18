<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;
use PlinCode\LaravelCleanArchitecture\Concerns\WritesFiles;

class MakeJobCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;
    use WritesFiles;

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

        $this->resolveForce();

        $this->info("Creating job: {$name}");
        $exitCode = $this->createJob($name);

        if ($exitCode === self::SUCCESS) {
            $this->info("Job {$name} created successfully!");
        }

        return $exitCode;
    }

    protected function createJob(string $name): int
    {
        $prefix  = $this->jobPrefix();
        $name    = $this->stripPrefix($this->stripSuffix($name, 'Job'), $prefix);
        $stub    = $this->getStub('job');
        $content = str_replace('{{JobPrefix}}', $prefix, $this->replaceDomainPlaceholders($stub, $name));

        $directory = $this->layerDirectory('application') . '/Jobs';
        $jobPath   = base_path($directory);

        if (! $this->files->isDirectory($jobPath)) {
            $this->files->makeDirectory($jobPath, 0755, true);
        }

        return $this->writeOrFail("{$jobPath}/{$prefix}{$name}Job.php", $content, "{$directory}/{$prefix}{$name}Job.php");
    }
}
