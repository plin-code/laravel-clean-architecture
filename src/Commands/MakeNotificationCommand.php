<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;
use PlinCode\LaravelCleanArchitecture\Concerns\ResolvesArchitectureDirectories;
use PlinCode\LaravelCleanArchitecture\Concerns\WritesFiles;

class MakeNotificationCommand extends Command
{
    use RendersStubs;
    use ResolvesArchitectureDirectories;
    use WritesFiles;

    protected $signature = 'clean-arch:make-notification {name : The name of the notification}
                          {--force : Overwrite existing files}';

    protected $description = 'Create a new notification in the Infrastructure layer';

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

        $this->info("Creating notification: {$name}");
        $exitCode = $this->createNotification($name);

        if ($exitCode === self::SUCCESS) {
            $this->info("Notification {$name} created successfully!");
        }

        return $exitCode;
    }

    protected function createNotification(string $name): int
    {
        $stub    = $this->getStub('notification');
        $content = $this->replaceDomainPlaceholders($stub, $name);

        $directory        = $this->layerDirectory('infrastructure') . '/Notifications';
        $notificationPath = base_path($directory);

        if (! $this->files->isDirectory($notificationPath)) {
            $this->files->makeDirectory($notificationPath, 0755, true);
        }

        return $this->writeOrFail("{$notificationPath}/{$name}Notification.php", $content, "{$directory}/{$name}Notification.php");
    }
}
