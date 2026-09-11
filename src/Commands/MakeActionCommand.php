<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;
use PlinCode\LaravelCleanArchitecture\Concerns\WritesFiles;

class MakeActionCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;
    use WritesFiles;

    protected $signature = 'clean-arch:make-action {name : The name of the action}
                           {domain : The domain name}
                           {--force : Overwrite existing files}
                           {--no-base : Do not extend BaseAction}';

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

        $this->resolveForce();

        $this->info("🚀 Creating action: {$name} for domain: {$domain}");

        $exitCode = $this->createAction($name, $domain);

        if ($exitCode === self::SUCCESS) {
            $this->info("✅ Action {$name} created successfully!");
        }

        return $exitCode;
    }

    protected function createAction(string $name, string $domain): int
    {
        $extend  = $this->shouldExtendBaseClasses((bool) $this->option('no-base'));
        $stub    = $this->getStub('action');
        $content = $this->replacePlaceholders($stub, $name, $this->baseActionReplacements($extend), $domain);

        $pluralDomain = Str::plural($domain);
        $directory    = $this->layerDirectory('application') . "/Actions/{$pluralDomain}";
        $actionsPath  = base_path($directory);

        if (! $this->files->isDirectory($actionsPath)) {
            $this->files->makeDirectory($actionsPath, 0755, true);
        }

        return $this->writeOrFail("{$actionsPath}/{$name}Action.php", $content, "{$directory}/{$name}Action.php");
    }

    protected function replacePlaceholders(string $content, string $name, array $extra, string $domain): string
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

        return str_replace(array_keys(array_merge($replacements, $extra)), array_values(array_merge($replacements, $extra)), $content);
    }
}
