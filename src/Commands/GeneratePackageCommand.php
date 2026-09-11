<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;
use PlinCode\LaravelCleanArchitecture\Concerns\WritesFiles;

class GeneratePackageCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;
    use WritesFiles;

    protected $signature = 'clean-arch:generate-package {name : The name of the package}
                          {vendor : The vendor name}
                          {--path= : Custom path for the package}
                          {--force : Overwrite existing files}';

    protected $description = 'Generate a new Laravel package with Clean Architecture structure';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $packageName = $this->argument('name');
        $vendor      = $this->argument('vendor');
        $customPath  = $this->option('path');

        $this->resolveForce();

        $this->info("🚀 Generating package: {$vendor}/{$packageName}");

        $path       = $customPath ?: base_path("packages/{$vendor}/{$packageName}");
        $studlyName = Str::studly($packageName);
        $namespace  = Str::studly($vendor) . '\\' . $studlyName;

        $this->createPackageStructure($path, $packageName, $studlyName, $namespace, $vendor);

        $this->info("✅ Package {$vendor}/{$packageName} generated successfully!");
        $this->info("Package location: {$path}");
        $this->newLine();
        $this->info('Next steps:');
        $this->info('1. Add the package to your composer.json repositories');
        $this->info("2. Run: composer require {$vendor}/{$packageName}");

        return self::SUCCESS;
    }

    protected function createPackageStructure(string $path, string $packageName, string $studlyName, string $namespace, string $vendor): void
    {
        // Create directories
        $directories = [
            'src',
            'src/Commands',
            'src/Domain',
            'src/Application/Actions',
            'src/Application/Services',
            'src/Infrastructure/Http/Controllers',
            'stubs',
            'config',
            'tests',
            'tests/Feature',
            'tests/Unit',
        ];

        foreach ($directories as $directory) {
            $fullPath = "{$path}/{$directory}";
            if (! $this->files->isDirectory($fullPath)) {
                $this->files->makeDirectory($fullPath, 0755, true);
                $this->info("Created directory: {$directory}");
            }
        }

        // Create files
        $this->createPackageComposer($path, $packageName, $studlyName, $namespace, $vendor);
        $this->createPackageServiceProvider($path, $studlyName, $namespace);
        $this->createPackageModel($path, $studlyName, $namespace);
        $this->createPackageService($path, $studlyName, $namespace);
        $this->createPackageReadme($path, $packageName, $studlyName, $vendor);
    }

    protected function createPackageComposer(string $path, string $packageName, string $studlyName, string $namespace, string $vendor): void
    {
        $content = json_encode([
            'name'        => "{$vendor}/{$packageName}",
            'description' => "Laravel package for {$studlyName}",
            'type'        => 'library',
            'license'     => 'MIT',
            'authors'     => [
                [
                    'name'  => 'Your Name',
                    'email' => 'your.email@example.com',
                ],
            ],
            'require' => [
                'php'                   => '^8.3',
                'illuminate/support'    => '^12.0|^13.0',
                'illuminate/console'    => '^12.0|^13.0',
                'illuminate/filesystem' => '^12.0|^13.0',
            ],
            'autoload' => [
                'psr-4' => [
                    str_replace('\\', '\\\\', $namespace) . '\\' => 'src/',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    str_replace('\\', '\\\\', $namespace) . '\\Tests\\' => 'tests/',
                ],
            ],
            'extra' => [
                'laravel' => [
                    'providers' => [
                        str_replace('\\', '\\\\', $namespace) . '\\' . $studlyName . 'ServiceProvider',
                    ],
                ],
            ],
            'minimum-stability' => 'stable',
            'prefer-stable'     => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->writePath("{$path}/composer.json", $content, 'composer.json');
    }

    protected function createPackageServiceProvider(string $path, string $studlyName, string $namespace): void
    {
        $stub    = $this->getStub('package-service-provider');
        $content = str_replace(
            ['{{Namespace}}', '{{StudlyName}}'],
            [$namespace, $studlyName],
            $stub
        );

        $this->writePath("{$path}/src/{$studlyName}ServiceProvider.php", $content, "src/{$studlyName}ServiceProvider.php");
    }

    protected function createPackageModel(string $path, string $studlyName, string $namespace): void
    {
        $stub    = $this->getStub('package-model');
        $content = str_replace(
            ['{{Namespace}}', '{{StudlyName}}'],
            [$namespace, $studlyName],
            $stub
        );

        $this->writePath("{$path}/src/{$studlyName}.php", $content, "src/{$studlyName}.php");
    }

    protected function createPackageService(string $path, string $studlyName, string $namespace): void
    {
        $stub    = $this->getStub('package-service');
        $content = str_replace(
            ['{{Namespace}}', '{{StudlyName}}'],
            [$namespace, $studlyName],
            $stub
        );

        $this->writePath("{$path}/src/{$studlyName}Service.php", $content, "src/{$studlyName}Service.php");
    }

    protected function createPackageReadme(string $path, string $packageName, string $studlyName, string $vendor): void
    {
        $stub    = $this->getStub('package-readme');
        $content = str_replace(
            ['{{PackageName}}', '{{StudlyName}}', '{{VendorName}}'],
            [$packageName, $studlyName, $vendor],
            $stub
        );

        $this->writePath("{$path}/README.md", $content, 'README.md');
    }
}
