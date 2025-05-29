<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeActionCommand extends Command
{
    protected $signature = 'clean-arch:make-action {name : The name of the action}
                          {domain : The domain name}
                          {--force : Overwrite existing files}';

    protected $description = 'Create a new action in the specified domain';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $name   = $this->argument('name');
        $domain = $this->argument('domain');
        $force  = $this->option('force');

        $this->info("🚀 Creating action: {$name} for domain: {$domain}");

        $this->createAction($name, $domain);

        $this->info("✅ Action {$name} created successfully!");

        return self::SUCCESS;
    }

    protected function createAction(string $name, string $domain): void
    {
        $stub    = $this->getStub('action');
        $content = $this->replacePlaceholders($stub, $name, $domain);

        $pluralDomain = Str::plural($domain);
        $actionsPath  = app_path("Application/Actions/{$pluralDomain}");

        if (! $this->files->isDirectory($actionsPath)) {
            $this->files->makeDirectory($actionsPath, 0755, true);
        }

        $this->files->put("{$actionsPath}/{$name}Action.php", $content);
        $this->info("Created: Application/Actions/{$pluralDomain}/{$name}Action.php");
    }

    protected function replacePlaceholders(string $content, string $name, string $domain): string
    {
        $pluralDomain   = Str::plural($domain);
        $domainVariable = Str::camel($domain);

        $replacements = [
            '{{ActionName}}'       => $name . 'Action',
            '{{DomainName}}'       => $domain,
            '{{PluralDomainName}}' => $pluralDomain,
            '{{domainVariable}}'   => $domainVariable,
            '{{RequestName}}'      => 'Request',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
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
